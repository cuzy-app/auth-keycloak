<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\authclient;

use humhub\modules\authKeycloak\models\AuthKeycloak;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\Module;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\User;
use humhub\modules\user\services\AuthClientUserService;
use PDOException;
use Yii;
use yii\authclient\InvalidResponseException;
use yii\authclient\OpenIdConnect;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;
use yii\helpers\BaseInflector;

class Keycloak extends OpenIdConnect
{
    public const DEFAULT_NAME = 'Keycloak';

    /**
     * @inheritdoc
     */
    public $apiBaseUrl;
    /**
     * @var bool
     */
    protected $_userSynced = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        // web-token/jwt-library is not loaded here on purpose: yii\authclient\OpenIdConnect needs it
        // for ID token validation and HumHub core requires it (^4.1). Bundling a copy in this module
        // made the module's autoloader shadow core's for any Jose class not yet loaded, so token
        // validation could run against a mix of two library versions.
        $config = new ConfigureForm();
        $this->issuerUrl = $config->baseUrl . '/realms/' . $config->realm;
        $this->apiBaseUrl = $this->issuerUrl . '/protocol/openid-connect';

        parent::init();
    }

    /**
     * @param $request
     * @param $accessToken
     * @return void
     */
    public function applyAccessTokenToRequest($request, $accessToken)
    {
        $data = $request->getData();
        $data['Authorization'] = 'Bearer ' . $accessToken->getToken();
        $request->setHeaders($data);
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return self::DEFAULT_NAME;
    }

    /**
     * @inheridoc
     */
    public function getUser()
    {
        $userAttributes = $this->getUserAttributes();

        if (array_key_exists('id', $userAttributes)) {
            $userAuth = Auth::findOne(['source' => self::DEFAULT_NAME, 'source_id' => $userAttributes['id']]);
            if ($userAuth !== null && $userAuth->user !== null) {
                return $userAuth->user;
            }
        }

        if (array_key_exists('email', $userAttributes)) {
            $userByEmail = User::findOne(['email' => $userAttributes['email']]);
            if ($userByEmail !== null && $this->isUserSafeToLink($userByEmail, $userAttributes['id'] ?? null)) {
                return $userByEmail;
            }
        }

        if (array_key_exists('username', $userAttributes)) {
            $userByUsername = User::findOne(['username' => $userAttributes['username']]);
            if ($userByUsername !== null && $this->isUserSafeToLink($userByUsername, $userAttributes['id'] ?? null)) {
                return $userByUsername;
            }
        }

        return null;
    }

    /**
     * Called among others by `user/controllers/AuthController::authSuccess()`
     * @inheridoc
     */
    public function getUserAttributes()
    {
        // Avoid looping getUserAttributes()
        if (!$this->_userSynced) {
            $this->_userSynced = true;
            $this->syncUserAttributes();
        }

        return parent::getUserAttributes();
    }

    /**
     * Checks whether a HumHub user found via email/username fallback is safe to link
     * to the current Keycloak identity.
     *
     * Returns false when the user already has a Keycloak auth record whose source_id
     * differs from the incoming one. Without this guard a Keycloak principal whose
     * email matches an existing HumHub account could impersonate that account even
     * though the two principals have completely different sub claims (source_ids).
     *
     * @param User        $user     The HumHub user found by the fallback lookup.
     * @param string|null $sourceId The source_id (sub) of the currently authenticating Keycloak principal.
     * @return bool
     */
    protected function isUserSafeToLink(User $user, ?string $sourceId): bool
    {
        // No source_id means we cannot reliably identify the Keycloak principal – block linking.
        if ($sourceId === null) {
            return false;
        }

        $existingAuth = Auth::findOne([
            'user_id' => $user->id,
            'source' => self::DEFAULT_NAME,
        ]);

        // No existing Keycloak auth record → safe to link (first Keycloak login for this user).
        // Existing record matches the current source_id → same principal, safe.
        // Existing record has a different source_id → different Keycloak identity already owns
        // this account; block linking to prevent impersonation.
        return $existingAuth === null || $existingAuth->source_id === $sourceId;
    }

    /**
     * Creates or updates the Keycloak Auth record for a given user.
     *
     * Extracted from syncUserAttributes() so the same logic can be called
     * from Events::onAfterLogin() as well. This is needed to recover the
     * Auth record for newly registered users: syncUserAttributes() runs
     * during OAuth callback processing, but at that point a brand-new user
     * does not yet exist in the database, so getUser() returns null and
     * the original logic exits before the Auth record can be written.
     * EVENT_AFTER_LOGIN fires after the user has been persisted, so calling
     * this method from there guarantees the Auth record is created on the
     * very first login.
     *
     * @param User $user
     * @return void
     */
    public function createOrUpdateAuthRecord(User $user)
    {
        $userAttributes = $this->getUserAttributes();
        $sourceId = $userAttributes['id'] ?? null;
        if (!$sourceId) {
            return;
        }

        $auth = AuthKeycloak::findOne([
            'source' => self::DEFAULT_NAME,
            'source_id' => $sourceId,
        ]);

        // Make sure authClient is not doubly assigned
        if ($auth !== null && $auth->user_id !== $user->id) {
            $auth->delete();
            $auth = null;
        }

        // Get Keycloak shared session identifier
        $sid = Yii::$app->request->get('session_state');

        if ($auth === null) {
            $auth = new AuthKeycloak([
                'user_id' => $user->id,
                'source' => self::DEFAULT_NAME,
                'source_id' => (string)$sourceId,
                'keycloak_sid' => $sid,
            ]);
            $auth->save();
        } elseif ($auth->keycloak_sid !== $sid) {
            $auth->keycloak_sid = $sid;
            $auth->save();
        }
    }

    /**
     * @inheridoc
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function syncUserAttributes()
    {
        $user = $this->getUser();
        if ($user === null) {
            return;
        }

        $userAttributes = $this->getUserAttributes();

        try {
            (new AuthClientUserService($user))->add($this);
        } catch (PDOException) {
        }

        $this->createOrUpdateAuthRecord($user);

        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');
        $settings = $module->settings;
        $updateHumhubEmailFromBrokerEmail = (bool)$settings->get('updateHumhubEmailFromBrokerEmail');
        $updateHumhubUsernameFromBrokerUsername = (bool)$settings->get('updateHumhubUsernameFromBrokerUsername');

        if (
            $updateHumhubEmailFromBrokerEmail
            && $user->email !== $userAttributes['email']
        ) {
            $user->email = $userAttributes['email'];
            $user->save();
        }

        if (
            $updateHumhubUsernameFromBrokerUsername
            && isset($userAttributes['username'])
            && $user->username !== $userAttributes['username']
        ) {
            $user->username = $userAttributes['username'];
            $user->save();
        }
    }

    /**
     * @inheridoc
     */
    protected function initUserAttributes()
    {
        try {
            return $this->api('userinfo');
        } catch (InvalidResponseException|\Exception) {
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultName()
    {
        return self::DEFAULT_NAME;
    }

    /**
     * @inheridoc
     */
    protected function defaultTitle()
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');
        return $module->settings->get('title', Yii::t('AuthKeycloakModule.base', ConfigureForm::DEFAULT_TITLE));
    }

    protected function defaultViewOptions()
    {
        return [
            'cssIcon' => 'fa fa-sign-in',
            'buttonBackgroundColor' => '#e0492f',
        ];
    }

    protected function defaultNormalizeUserAttributeMap()
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');

        return [
            'id' => 'sub',
            'username' => $module->settings->get('usernameMapper'),
            'firstname' => 'given_name',
            'lastname' => 'family_name',
            'email' => 'email',
        ];
    }

    /**
     * If the username sent by Keycloak is the user's email, it is replaced by a username auto-generated from the first and last name (CamelCase formatted)
     * @inerhitdoc
     * @throws InvalidConfigException
     */
    protected function normalizeUserAttributes($attributes)
    {
        $attributes = parent::normalizeUserAttributes($attributes);
        if (
            isset($attributes['username'], $attributes['email'])
            && $attributes['username'] === $attributes['email']
        ) {
            $attributes['username'] = BaseInflector::id2camel(
                BaseInflector::slug(
                    $attributes['firstname'] . ' ' . $attributes['lastname'],
                ),
            );
        }
        return $attributes;
    }
}

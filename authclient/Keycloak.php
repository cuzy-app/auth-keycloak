<?php
/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/humhub-modules-auth-keycloak
 * @license https://github.com/cuzy-app/humhub-modules-auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\authclient;

use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\Module;
use humhub\modules\user\authclient\interfaces\PrimaryClient;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\User;
use humhub\modules\user\services\AuthClientUserService;
use PDOException;
use Yii;
use yii\authclient\InvalidResponseException;
use yii\authclient\OAuth2;
use yii\db\StaleObjectException;
use yii\helpers\BaseInflector;

/**
 * With PrimaryClient, the user will have the `auth_mode` field in the `user` table set to 'Keycloak'.
 * This will avoid showing the "Change Password" tab when logged in with Keycloak
 */
class Keycloak extends OAuth2 implements PrimaryClient
{
    public const DEFAULT_NAME = 'Keycloak';

    /**
     * @inheritdoc
     */
    public $authUrl;

    /**
     * @inheritdoc
     */
    public $tokenUrl;

    /**
     * @inheritdoc
     */
    public $apiBaseUrl;

    /**
     * @var bool
     */
    protected $_userSynced = false;

    /**
     * @inheridoc
     */
    public $scope = 'openid';

    /**
     * @inheritdoc
     */
    public function init()
    {
        $config = new ConfigureForm();

        $this->apiBaseUrl = $config->baseUrl . '/realms/' . $config->realm . '/protocol/openid-connect';
        $this->authUrl = $this->apiBaseUrl . '/auth';
        $this->tokenUrl = $this->apiBaseUrl . '/token';

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
     * @inheridoc
     */
    protected function initUserAttributes()
    {
        try {
            return $this->api('userinfo');
        } catch (InvalidResponseException|\Exception $e) {
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return self::DEFAULT_NAME;
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
        $settings = $module->settings;

        return $settings->get('title', Yii::t('AuthKeycloakModule.base', ConfigureForm::DEFAULT_TITLE));
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
        $settings = $module->settings;

        return [
            'id' => 'sub',
            'username' => $settings->get('usernameMapper'),
            'firstname' => 'given_name',
            'lastname' => 'family_name',
            'email' => 'email',
        ];
    }

    /**
     * If the username sent by Keycloak is the user's email, it is replaced by a username auto-generated from the first and last name (CamelCase formatted)
     * @inerhitdoc
     */
    protected function normalizeUserAttributes($attributes)
    {
        $attributes = parent::normalizeUserAttributes($attributes);
        if (
            isset($attributes['username'], $attributes['email'])
            && $attributes['username'] === $attributes['email']
        ) {
            /* @var $userModule \humhub\modules\user\Module */
            $userModule = Yii::$app->getModule('user');
            $attributes['username'] = BaseInflector::id2camel(
                BaseInflector::slug(
                    $attributes['firstname'] . ' ' . $attributes['lastname']
                )
            );
        }
        return $attributes;
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
            if ($userByEmail !== null) {
                return $userByEmail;
            }
        }

        if (array_key_exists('username', $userAttributes)) {
            $userByUsername = User::findOne(['username' => $userAttributes['username']]);
            if ($userByUsername !== null) {
                return $userByUsername;
            }
        }

        return null;
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
        } catch (PDOException $e) {
        }
        KeycloakHelpers::storeAndGetAuthSourceId($user, $userAttributes['id'] ?? null);

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
}
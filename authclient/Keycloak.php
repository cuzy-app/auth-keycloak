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
use humhub\modules\space\models\Space;
use humhub\modules\user\authclient\AuthClientHelpers;
use humhub\modules\user\authclient\interfaces\PrimaryClient;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\Invite;
use humhub\modules\user\models\User;
use Yii;
use yii\authclient\OAuth2;
use yii\helpers\BaseInflector;
use yii\helpers\Url;

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

    public function applyAccessTokenToRequest($request, $accessToken)
    {
        $data = $request->getData();
        $data['Authorization'] = 'Bearer ' . $accessToken->getToken();
        $request->setHeaders($data);
    }

    public function redirectToBroker()
    {
        Yii::$app->session->set('loginRememberMe', true);
        // Try to set a better return URL after login
        $urlToRedirect = Url::current([], true);
        if ($token = Yii::$app->request->get('token')) {
            $invite = Invite::findOne(['token' => $token]);
            if ($invite !== null) {
                $space = Space::findOne($invite->space_invite_id);
                if ($space !== null) {
                    $urlToRedirect = $space->getUrl(true);
                }
            }
        }
        if (!$this->redirectUrlIsValid($urlToRedirect)) {
            $urlToRedirect = Yii::$app->request->referrer;
        }
        if ($this->redirectUrlIsValid($urlToRedirect)) {
            Yii::$app->user->setReturnUrl($urlToRedirect);
        }

        // Redirect to broker
        // The `return` will prevent logging user if URL doesn't exist
        return Yii::$app->getResponse()->redirect($this->buildAuthUrl());
    }

    /**
     * @param string|null $url
     * @return bool
     */
    protected function redirectUrlIsValid(?string $url)
    {
        // URL is another website
        if (strpos($url, Url::base(true)) !== 0) {
            return false;
        }
        // URL is not for the user module
        if (strpos($url, Url::to(['/user'], true)) !== 0) {
            return true;
        }
        // URL is for the user module: URL is valid only for these controllers
        return
            strpos($url, Url::to(['/user/account'], true)) === 0
            || strpos($url, Url::to(['/user/people'], true)) === 0
            || strpos($url, Url::to(['/user/profile'], true)) === 0;
    }

    /**
     * @inheritdoc
     */
    public function getReturnUrl()
    {
        return Url::to(['/user/auth/external', 'authclient' => static::DEFAULT_NAME], true);
    }

    protected function initUserAttributes()
    {
        return $this->api('userinfo');
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

        $userAuth = Auth::findOne(['source' => static::DEFAULT_NAME, 'source_id' => $userAttributes['id']]);

        if ($userAuth !== null && $userAuth->user !== null) {
            return $userAuth->user;
        }

        $userByEmail = User::findOne(['email' => $userAttributes['email']]);
        if ($userByEmail !== null) {
            return $userByEmail;
        }

        $userByUsername = User::findOne(['username' => $userAttributes['username']]);
        if ($userByUsername !== null) {
            return $userByUsername;
        }

        return null;
    }

    /**
     * @inheridoc
     */
    public function syncUserAttributes()
    {
        $user = $this->getUser();
        if ($user === null) {
            return;
        }

        $userAttributes = $this->getUserAttributes();

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

        AuthClientHelpers::storeAuthClientForUser($this, $user);
    }
}
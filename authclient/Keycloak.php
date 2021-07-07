<?php
/**
 * * Keycloak Sign-In
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\authKeycloak\authclient;

use Yii;
use yii\authclient\OAuth2;
use yii\helpers\Url;
use humhub\modules\user\models\Auth;


class Keycloak extends OAuth2
{
    /**
     * @inheritdoc
     * https://broker-domain.tdl/auth/realms/master/protocol/openid-connect/auth
     */
    public $authUrl;
 
    /**
     * @inheritdoc
     * https://broker-domain.tdl/auth/realms/master/protocol/openid-connect/token
     */
    public $tokenUrl;
 
    /**
     * @inheritdoc
     * https://broker-domain.tdl/auth/realms/master/protocol/openid-connect
     */
    public $apiBaseUrl;

    /**
     * @var string attribute to match user tables with email or id
     */
    public $idAttribute = 'id';

    /**
     * @var string Keycloak mapper for username: 'preferred_username', 'sub' (to use Keycloak ID) or other custom Token Claim Name
     */
    public $usernameMapper = 'preferred_username';
    
    /**
     * @var boolean auto login (possible only if anonymous registration is allowed)
     */
    public $autoLogin = false;

    /**
     * @var boolean remove user's Keycloak sessions after logout
     * Uses Keycloak API ($this->keycloakApiParams are required)
     */
    public $removeKeycloakSessionsAfterLogout = false;

    /**
     * @var boolean hide username field in registration form
     */
    public $hideRegistrationUsernameField = false;

    /**
     * @var bool update Humhub's user email from broker's (Keycloak) user email on login
     * Possible only if $this->idAttribute value is set to 'id'
     */
    public $updateHumhubEmailFromBrokerEmail = true;

    /**
     * @var bool update broker's (Keycloak) user email on Humhub's user email update
     * Uses Keycloak API ($this->keycloakApiParams are required)
     */
    public $updatedBrokerEmailFromHumhubEmail = false;

    /**
     * @var array Keycloak API params for authentification
     * Example:
     [
        'realm'=>'master',
        'username'=>'an_admin_user_name',
        'password'=>'admin_user_password',
        'baseUri'=>'https://broker-domain.tdl'
     ]
     */
    public $keycloakApiParams = [];


    protected function initUserAttributes()
    {
        return $this->api('userinfo');
    }


    public function applyAccessTokenToRequest($request, $accessToken)
    {
        $data = $request->getData();
        $data['Authorization'] = 'Bearer ' . $accessToken->getToken();
        $request->setHeaders($data);
    }


    protected function defaultName()
    {
        return 'keycloak';
    }

    protected function defaultTitle()
    {
        return 'keycloak';
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
        $userAttributeMap = [
            'username' => $this->usernameMapper,
            'firstname' => 'given_name',
            'lastname' => 'family_name',
            'email' => 'email',
        ];
        if ($this->idAttribute === 'id') {
            // Use Keycloak ID (sub) to match user table
            return array_merge(['id' => 'sub'], $userAttributeMap);
        }
        // Use Keycloak email to match user table
        return $userAttributeMap;
    }

    public function redirectToBroker()
    {
        Yii::$app->session->set('loginRememberMe', true);

        // Try to set a better return URL after login
        $urlToRedirect = Url::current([], true);
        if (strpos($urlToRedirect, Url::to('/user/auth', true)) === 0) {
            $urlToRedirect = Yii::$app->request->referrer;
        }
        if (
            strpos($urlToRedirect, Url::to('/user/auth', true)) !== 0
            && strpos($urlToRedirect, Url::base(true)) === 0 // Referrer URL is not an other website
        ) {
            Yii::$app->user->setReturnUrl($urlToRedirect);
        }

        // Redirect to broker
        // The `return` will prevent logging user if URL doesn't exists
        return Yii::$app->getResponse()->redirect($this->buildAuthUrl());
    }

    /**
     * @inheritdoc
     */
    public function getReturnUrl()
    {
        return Url::to('/user/auth/external?authclient=Keycloak', true);
    }


    /**
     * @inheritdoc
     * Update Humhub's user email if emails is different on Keycloak
     * @throws \yii\base\InvalidConfigException
     */
    public function getUserAttributes()
    {
        if ($this->updateHumhubEmailFromBrokerEmail && $this->idAttribute === 'id') {
            $userAttributes = $this->normalizeUserAttributes($this->initUserAttributes());

            $userAuth = Auth::findOne(['source' => $this->defaultName(), 'source_id' => $userAttributes['id']]);
            if ($userAuth !== null && $userAuth->user->email !== $userAttributes['email']) {
                $userAuth->user->email = $userAttributes['email'];
                $userAuth->user->save();
            }
        }

        return parent::getUserAttributes();
    }
}
<?php
/**
 * * Keycloak Sign-In
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\authKeycloak\authclient;

use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\Module;
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


    public function init()
    {
        $config = ConfigureForm::getInstance();

        $this->authUrl = $config->authUrl;
        $this->tokenUrl = $config->tokenUrl;
        $this->apiBaseUrl = $config->apiBaseUrl;

        parent::init();
    }


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
        return 'Keycloak';
    }

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

        $userAttributeMap = [
            'username' => $settings->get('usernameMapper'),
            'firstname' => 'given_name',
            'lastname' => 'family_name',
            'email' => 'email',
        ];

        if ($settings->get('idAttribute') === 'id') {
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
        return Url::to(['/user/auth/external', 'authclient' => 'Keycloak'], true);
    }


    /**
     * @inheritdoc
     * Update Humhub's user email if emails is different on Keycloak
     * @throws \yii\base\InvalidConfigException
     */
    public function getUserAttributes()
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');
        $settings = $module->settings;

        if ($settings->get('updateHumhubEmailFromBrokerEmail') && $settings->get('idAttribute') === 'id') {
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
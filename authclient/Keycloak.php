<?php
/**
 * * Keycloak Sign-In
 * @link https://github.com/cuzy-app/humhub-modules-auth-keycloak
 * @license https://github.com/cuzy-app/humhub-modules-auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\authclient;

use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\Module;
use humhub\modules\user\models\Auth;
use Yii;
use yii\authclient\OAuth2;
use yii\base\InvalidConfigException;
use yii\helpers\Url;


class Keycloak extends OAuth2
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
        if (!$this->redirectUrlIsValid($urlToRedirect)) {
            $urlToRedirect = Yii::$app->request->referrer;
        }
        if ($this->redirectUrlIsValid($urlToRedirect)) {
            Yii::$app->user->setReturnUrl($urlToRedirect);
        }

        // Redirect to broker
        // The `return` will prevent logging user if URL doesn't exists
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
        return Url::to(['/user/auth/external', 'authclient' => 'Keycloak'], true);
    }

    /**
     * @inheritdoc
     * Update Humhub's user email if emails is different on Keycloak
     * @throws InvalidConfigException
     */
    public function getUserAttributes()
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');
        $settings = $module->settings;

        if ($settings->get('updateHumhubEmailFromBrokerEmail')) {
            $userAttributes = $this->normalizeUserAttributes($this->initUserAttributes());

            $userAuth = Auth::findOne(['source' => static::DEFAULT_NAME, 'source_id' => $userAttributes['id']]);
            if ($userAuth !== null && $userAuth->user->email !== $userAttributes['email']) {
                $userAuth->user->email = $userAttributes['email'];
                $userAuth->user->save();
            }
        }

        return parent::getUserAttributes();
    }

    protected function initUserAttributes()
    {
        return $this->api('userinfo');
    }

    protected function defaultName()
    {
        return static::DEFAULT_NAME;
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

        return [
            'id' => 'sub',
            'username' => $settings->get('usernameMapper'),
            'firstname' => 'given_name',
            'lastname' => 'family_name',
            'email' => 'email',
        ];
    }


}
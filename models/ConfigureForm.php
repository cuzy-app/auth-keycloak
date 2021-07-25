<?php

namespace humhub\modules\authKeycloak\models;

use Yii;
use yii\base\Model;
use yii\helpers\Url;
use humhub\modules\authKeycloak\Module;

/**
 * The module configuration model
 */
class ConfigureForm extends Model
{
    /**
     * @var boolean Enable this authclient
     */
    public $enabled;

    /**
     * @var string the client id provided by Keycloak
     */
    public $clientId;

    /**
     * @var string the client secret provided by Keycloak
     */
    public $clientSecret;

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
     * @var string readonly
     */
    public $redirectUri;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['clientId', 'clientSecret', 'authUrl', 'tokenUrl', 'apiBaseUrl'], 'required'],
            [['enabled'], 'boolean'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'enabled' => Yii::t('AuthKeycloakModule.base', 'Enabled'),
            'clientId' => Yii::t('AuthKeycloakModule.base', 'Client ID'),
            'clientSecret' => Yii::t('AuthKeycloakModule.base', 'Client secret'),
            'authUrl' => Yii::t('AuthKeycloakModule.base', 'Auth Url:'),
            'tokenUrl' => Yii::t('AuthKeycloakModule.base', 'Token Url:'),
            'apiBaseUrl' => Yii::t('AuthKeycloakModule.base', 'API Url:'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeHints()
    {
        return [
        ];
    }

    /**
     * Loads the current module settings
     */
    public function loadSettings()
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');

        $settings = $module->settings;

        $this->enabled = (boolean)$settings->get('enabled');
        $this->clientId = $settings->get('clientId');
        $this->clientSecret = $settings->get('clientSecret');
        $this->authUrl = $settings->get('authUrl');
        $this->tokenUrl = $settings->get('tokenUrl');
        $this->apiBaseUrl = $settings->get('apiBaseUrl');

        $this->redirectUri = Url::to(['/user/auth/external', 'authclient' => 'keycloak'], true);
    }

    /**
     * Saves module settings
     */
    public function saveSettings()
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');

        $module->settings->set('enabled', (boolean)$this->enabled);
        $module->settings->set('clientId', $this->clientId);
        $module->settings->set('clientSecret', $this->clientSecret);
        $module->settings->set('authUrl', $this->authUrl);
        $module->settings->set('tokenUrl', $this->tokenUrl);
        $module->settings->set('apiBaseUrl', $this->apiBaseUrl);

        return true;
    }

    /**
     * Returns a loaded instance of this configuration model
     */
    public static function getInstance()
    {
        $config = new static;
        $config->loadSettings();

        return $config;
    }

}
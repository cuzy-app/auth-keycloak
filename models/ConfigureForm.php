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
     * @var boolean
     */
    public $enabled = false;

    /**
     * @var string
     */
    public $clientId;

    /**
     * @var string
     */
    public $clientSecret;

    /**
     * @var string
     */
    public $authUrl;
 
    /**
     * @var string
     */
    public $tokenUrl;
 
    /**
     * @var string
     */
    public $apiBaseUrl;

    /**
     * @var string readonly
     */
    public $redirectUri;

    /**
     * @var string
     */
    public $idAttribute = 'id';

    /**
     * @var string
     */
    public $usernameMapper = 'preferred_username';

    /**
     * @var string
     */
    public $title;

    /**
     * @var bool
     */
    public $autoLogin = false;

    /**
     * @var bool
     */
    public $hideRegistrationUsernameField = false;

    /**
     * @var bool
     */
    public $removeKeycloakSessionsAfterLogout = false;

    /**
     * @var bool
     */
    public $updateHumhubEmailFromBrokerEmail = true;

    /**
     * @var bool
     */
    public $updatedBrokerEmailFromHumhubEmail = false;

    /**
     * @var string
     */
    public $apiRealm = 'master';

    /**
     * @var string
     */
    public $apiUsername = '';

    /**
     * @var string
     */
    public $apiPassword = '';

    /**
     * @var string
     */
    public $apiRootUrl = '';


    const DEFAULT_TITLE = 'Connect with Keycloak';


    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['clientId', 'clientSecret', 'authUrl', 'tokenUrl', 'apiBaseUrl', 'idAttribute', 'usernameMapper'], 'required'],
            [['clientId', 'clientSecret', 'authUrl', 'tokenUrl', 'apiBaseUrl', 'idAttribute', 'usernameMapper', 'title', 'apiRealm', 'apiUsername', 'apiPassword', 'apiRootUrl'], 'string'],
            [['enabled', 'autoLogin', 'hideRegistrationUsernameField', 'removeKeycloakSessionsAfterLogout', 'updateHumhubEmailFromBrokerEmail', 'updatedBrokerEmailFromHumhubEmail'], 'boolean'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'enabled' => Yii::t('AuthKeycloakModule.base', 'Enable this auth client'),
            'clientId' => Yii::t('AuthKeycloakModule.base', 'Client ID'),
            'clientSecret' => Yii::t('AuthKeycloakModule.base', 'Client secret'),
            'authUrl' => Yii::t('AuthKeycloakModule.base', 'Auth Url:'),
            'tokenUrl' => Yii::t('AuthKeycloakModule.base', 'Token Url:'),
            'apiBaseUrl' => Yii::t('AuthKeycloakModule.base', 'API Url:'),
            'idAttribute' => Yii::t('AuthKeycloakModule.base', 'Attribute to match user tables with `email` or `id`'),
            'usernameMapper' => Yii::t('AuthKeycloakModule.base', 'Keycloak mapper for username: `preferred_username`, `sub` (to use Keycloak ID) or other custom Token Claim Name'),
            'title' => Yii::t('AuthKeycloakModule.base', 'Title of the button (if autoLogin is disabled)'),
            'autoLogin' => Yii::t('AuthKeycloakModule.base', 'Automatic login'),
            'hideRegistrationUsernameField' => Yii::t('AuthKeycloakModule.base', 'Hide username field in registration form'),
            'removeKeycloakSessionsAfterLogout' => Yii::t('AuthKeycloakModule.base', 'Remove user\'s Keycloak sessions after logout'),
            'updateHumhubEmailFromBrokerEmail' => Yii::t('AuthKeycloakModule.base', 'Update Humhub\'s user email from broker\'s (Keycloak) user email on login'),
            'updatedBrokerEmailFromHumhubEmail' => Yii::t('AuthKeycloakModule.base', 'Update broker\'s (Keycloak) user email on Humhub\'s user email update'),
            'apiRealm' => Yii::t('AuthKeycloakModule.base', 'Keycloak API realm'),
            'apiUsername' => Yii::t('AuthKeycloakModule.base', 'Keycloak API admin username'),
            'apiPassword' => Yii::t('AuthKeycloakModule.base', 'Keycloak API admin password'),
            'apiRootUrl' => Yii::t('AuthKeycloakModule.base', 'Keycloak API root URL'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeHints()
    {
        return [
            'clientId' => Yii::t('AuthKeycloakModule.base', 'The client id provided by Keycloak'),
            'clientSecret' => Yii::t('AuthKeycloakModule.base', 'Client secret is in the "Credentials" tab (if in the settings "Access Type" is set to "confidential")'),
            'authUrl' => 'https://idp-domain.tdl/auth/realms/master/protocol/openid-connect/auth',
            'tokenUrl' => 'https://idp-domain.tdl/auth/realms/master/protocol/openid-connect/token',
            'apiBaseUrl' => 'https://idp-domain.tdl/auth/realms/master/protocol/openid-connect',
            'title' => Yii::t('AuthKeycloakModule.base', 'If you set a custom title, it will not be translated to the user\'s language unless you have a custom translation file in the protected/config folder. Leave blank to set default title.'),
            'autoLogin' => Yii::t('AuthKeycloakModule.base', 'Possible only if anonymous registration is allowed in the admin users settings'),
            'removeKeycloakSessionsAfterLogout' => Yii::t('AuthKeycloakModule.base', 'Uses Keycloak API (Keycloak API settings must be defined)'),
            'updateHumhubEmailFromBrokerEmail' => Yii::t('AuthKeycloakModule.base', 'Possible only if attribute value is set to `id`'),
            'updatedBrokerEmailFromHumhubEmail' => Yii::t('AuthKeycloakModule.base', 'Uses Keycloak API (Keycloak API settings must be defined)'),
            'apiRootUrl' => 'https://idp-domain.tdl',
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

        $this->enabled = (boolean)$settings->get('enabled', $this->enabled);
        $this->clientId = $settings->get('clientId');
        $this->clientSecret = $settings->get('clientSecret');
        $this->authUrl = $settings->get('authUrl');
        $this->tokenUrl = $settings->get('tokenUrl');
        $this->apiBaseUrl = $settings->get('apiBaseUrl');
        $this->idAttribute = $settings->get('idAttribute', $this->idAttribute);
        $this->usernameMapper = $settings->get('usernameMapper', $this->usernameMapper);
        $this->title = $settings->get('title', Yii::t('AuthKeycloakModule.base', static::DEFAULT_TITLE));
        $this->autoLogin = (boolean)$settings->get('autoLogin', $this->autoLogin);
        $this->hideRegistrationUsernameField = (boolean)$settings->get('hideRegistrationUsernameField', $this->hideRegistrationUsernameField);
        $this->removeKeycloakSessionsAfterLogout = (boolean)$settings->get('removeKeycloakSessionsAfterLogout', $this->removeKeycloakSessionsAfterLogout);
        $this->updateHumhubEmailFromBrokerEmail = (boolean)$settings->get('updateHumhubEmailFromBrokerEmail', $this->updateHumhubEmailFromBrokerEmail);
        $this->updatedBrokerEmailFromHumhubEmail = (boolean)$settings->get('updatedBrokerEmailFromHumhubEmail', $this->updatedBrokerEmailFromHumhubEmail);
        $this->apiRealm = $settings->get('apiRealm', $this->apiRealm);
        $this->apiUsername = $settings->get('apiUsername', $this->apiUsername);
        $this->apiPassword = $settings->get('apiPassword', $this->apiPassword);
        $this->apiRootUrl = $settings->get('apiRootUrl', $this->apiRootUrl);

        $this->redirectUri = Url::to(['/user/auth/external', 'authclient' => 'Keycloak'], true);
    }

    /**
     * Saves module settings
     */
    public function saveSettings()
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');

        $module->settings->set('enabled', $this->enabled);
        $module->settings->set('clientId', $this->clientId);
        $module->settings->set('clientSecret', $this->clientSecret);
        $module->settings->set('authUrl', $this->authUrl);
        $module->settings->set('tokenUrl', $this->tokenUrl);
        $module->settings->set('apiBaseUrl', $this->apiBaseUrl);
        $module->settings->set('idAttribute', $this->idAttribute);
        $module->settings->set('usernameMapper', $this->usernameMapper);
        if (!$this->title) {
            $this->title = static::DEFAULT_TITLE;
        }
        $module->settings->set('title', $this->title);
        $module->settings->set('hideRegistrationUsernameField', $this->hideRegistrationUsernameField);

        // API settings
        $module->settings->set('apiRealm', $this->apiRealm);
        $module->settings->set('apiUsername', $this->apiUsername);
        $module->settings->set('apiPassword', $this->apiPassword);
        $module->settings->set('apiRootUrl', $this->apiRootUrl);

        // Following settings can be enable only if API settings are entered
        if (!$this->hasApiParams()) {
            $this->removeKeycloakSessionsAfterLogout = false;
            $this->updatedBrokerEmailFromHumhubEmail = false;
        }
        $module->settings->set('removeKeycloakSessionsAfterLogout', $this->removeKeycloakSessionsAfterLogout);
        $module->settings->set('updatedBrokerEmailFromHumhubEmail', $this->updatedBrokerEmailFromHumhubEmail);

        // Following setting can be enable only if `idAttribute` has for value 'id
        if ($this->idAttribute !== 'id') {
            $this->updateHumhubEmailFromBrokerEmail = false;
        }
        $module->settings->set('updateHumhubEmailFromBrokerEmail', $this->updateHumhubEmailFromBrokerEmail);

        return true;
    }

    /**
     * @return bool
     */
    public function hasApiParams()
    {
        return $this->apiRealm && $this->apiUsername && $this->apiPassword && $this->apiRootUrl;
    }

    /**
     * Returns a loaded instance of this configuration model
     * @return static
     */
    public static function getInstance()
    {
        $config = new static();
        $config->loadSettings();

        return $config;
    }

}
<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\models;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\jobs\GroupsFullSync;
use humhub\modules\authKeycloak\Module;
use Yii;
use yii\base\Model;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * The module configuration model
 */
class ConfigureForm extends Model
{
    public const DEFAULT_TITLE = 'Connect with Keycloak';

    public const GROUP_SYNC_MODE_NONE = '';
    public const GROUP_SYNC_MODE_HH_TO_KC = 'hh2kc';
    public const GROUP_SYNC_MODE_KC_TO_HH = 'kc2hh';
    public const GROUP_SYNC_MODE_FULL = 'full';
    public const GROUP_SYNC_MODE_HH_TO_KC_NO_DEL = 'hh2kcNoDel';
    public const GROUP_SYNC_MODE_KC_TO_HH_NO_DEL = 'kc2hhNoDel';
    public const GROUP_SYNC_MODE_FULL_NO_KC_DEL = 'fullNoKcDel';
    public const GROUP_SYNC_MODE_FULL_NO_HH_DEL = 'fullNoHhDel';
    public const GROUP_SYNC_MODE_FULL_NO_DEL = 'fullNoDel';

    /**
     * @var bool
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
    public $realm = 'master';
    /**
     * @var string
     */
    public $baseUrl;
    /**
     * @var string readonly
     */
    public $redirectUri;
    /**
     * @var string readonly
     */
    public $backChannelLogoutUrl;
    /**
     * @var string
     */
    public $usernameMapper = 'sub';
    /**
     * @var string
     */
    public $title;
    /**
     * @var bool
     */
    public $hideRegistrationUsernameField = false;
    /**
     * @var bool
     */
    public $hideAdminUserEditPassword = false;
    /**
     * @var bool
     */
    public $removeKeycloakSessionsAfterLogout = false;
    /**
     * @var bool
     */
    public $updateHumhubUsernameFromBrokerUsername = false;
    /**
     * @var bool
     */
    public $updatedBrokerUsernameFromHumhubUsername = false;
    /**
     * @var bool
     */
    public $updateHumhubEmailFromBrokerEmail = false;
    /**
     * @var bool
     */
    public $updatedBrokerEmailFromHumhubEmail = false;
    /**
     * @var bool
     */
    public $addChangePasswordFormToAccount = false;
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
    public $groupsSyncMode = self::GROUP_SYNC_MODE_NONE;


    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['clientId', 'clientSecret', 'realm', 'baseUrl', 'usernameMapper'], 'required'],
            [['clientId', 'clientSecret', 'baseUrl', 'usernameMapper', 'title', 'realm', 'apiUsername', 'apiPassword'], 'string'],
            [['enabled', 'hideRegistrationUsernameField', 'hideAdminUserEditPassword', 'removeKeycloakSessionsAfterLogout', 'updateHumhubUsernameFromBrokerUsername', 'updatedBrokerUsernameFromHumhubUsername', 'updateHumhubEmailFromBrokerEmail', 'updatedBrokerEmailFromHumhubEmail', 'addChangePasswordFormToAccount'], 'boolean'],
            [['groupsSyncMode'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');
        $settings = $module->settings;

        $this->enabled = (bool)$settings->get('enabled', $this->enabled);
        $this->clientId = $settings->get('clientId');
        $this->clientSecret = $settings->get('clientSecret');
        $this->realm = $settings->get('realm', $this->realm);
        $this->baseUrl = $settings->get('baseUrl');
        $this->usernameMapper = $settings->get('usernameMapper', $this->usernameMapper);
        $this->title = $settings->get('title', Yii::t('AuthKeycloakModule.base', self::DEFAULT_TITLE));
        $this->hideRegistrationUsernameField = (bool)$settings->get('hideRegistrationUsernameField', $this->hideRegistrationUsernameField);
        $this->hideAdminUserEditPassword = (bool)$settings->get('hideAdminUserEditPassword', $this->hideAdminUserEditPassword);
        $this->removeKeycloakSessionsAfterLogout = (bool)$settings->get('removeKeycloakSessionsAfterLogout', $this->removeKeycloakSessionsAfterLogout);
        $this->updateHumhubUsernameFromBrokerUsername = (bool)$settings->get('updateHumhubUsernameFromBrokerUsername', $this->updateHumhubUsernameFromBrokerUsername);
        $this->updatedBrokerUsernameFromHumhubUsername = (bool)$settings->get('updatedBrokerUsernameFromHumhubUsername', $this->updatedBrokerUsernameFromHumhubUsername);
        $this->updateHumhubEmailFromBrokerEmail = (bool)$settings->get('updateHumhubEmailFromBrokerEmail', $this->updateHumhubEmailFromBrokerEmail);
        $this->updatedBrokerEmailFromHumhubEmail = (bool)$settings->get('updatedBrokerEmailFromHumhubEmail', $this->updatedBrokerEmailFromHumhubEmail);
        $this->addChangePasswordFormToAccount = (bool)$settings->get('addChangePasswordFormToAccount', $this->addChangePasswordFormToAccount);
        $this->apiUsername = $settings->get('apiUsername', $this->apiUsername);
        $this->apiPassword = $settings->get('apiPassword', $this->apiPassword);
        $this->groupsSyncMode = $settings->get('groupsSyncMode', $this->groupsSyncMode);

        $this->redirectUri = Url::to(['/user/auth/external', 'authclient' => Keycloak::DEFAULT_NAME], true);
        $this->backChannelLogoutUrl = Url::to(['/auth-keycloak/back-channel/logout'], true);
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'enabled' => Yii::t('AuthKeycloakModule.base', 'Enable this auth client'),
            'clientId' => Yii::t('AuthKeycloakModule.base', 'Client ID'),
            'clientSecret' => Yii::t('AuthKeycloakModule.base', 'Client secret key'),
            'realm' => Yii::t('AuthKeycloakModule.base', 'Realm name'),
            'baseUrl' => Yii::t('AuthKeycloakModule.base', 'Base URL'),
            'usernameMapper' => Yii::t('AuthKeycloakModule.base', 'Keycloak attribute to use to get username on account creation'),
            'title' => Yii::t('AuthKeycloakModule.base', 'Title of the button'),
            'hideRegistrationUsernameField' => Yii::t('AuthKeycloakModule.base', 'Hide username field in registration form'),
            'hideAdminUserEditPassword' => Yii::t('AuthKeycloakModule.base', 'In admin, hide password fields in edit user form'),
            'removeKeycloakSessionsAfterLogout' => Yii::t('AuthKeycloakModule.base', 'Remove user\'s Keycloak sessions after logout'),
            'updateHumhubUsernameFromBrokerUsername' => Yii::t('AuthKeycloakModule.base', 'Update user\'s username on HumHub when changed on Keycloak'),
            'updatedBrokerUsernameFromHumhubUsername' => Yii::t('AuthKeycloakModule.base', 'Update user\'s username on Keycloak when changed on HumHub'),
            'updateHumhubEmailFromBrokerEmail' => Yii::t('AuthKeycloakModule.base', 'Update user\'s email on HumHub when changed on Keycloak'),
            'updatedBrokerEmailFromHumhubEmail' => Yii::t('AuthKeycloakModule.base', 'Update user\'s email on Keycloak when changed on HumHub'),
            'addChangePasswordFormToAccount' => Yii::t('AuthKeycloakModule.base', 'Add a page in account settings allowing users to change their Keycloak password'),
            'apiUsername' => Yii::t('AuthKeycloakModule.base', 'Keycloak API admin username'),
            'apiPassword' => Yii::t('AuthKeycloakModule.base', 'Keycloak API admin password'),
            'groupsSyncMode' => Yii::t('AuthKeycloakModule.base', 'Synchronize groups and their members'),
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
            'realm' => Yii::t('AuthKeycloakModule.base', 'Called {nameInEnglish} in english', ['nameInEnglish' => '“Realm”']),
            'baseUrl' => 'Depending on your configuration: https://idp-domain.tdl or https://idp-domain.tdl/auth',
            'usernameMapper' => Yii::t('AuthKeycloakModule.base', '`preferred_username` (to use Keycloak username), `sub` (to use Keycloak ID) or other custom Token Claim Name'),
            'title' => Yii::t('AuthKeycloakModule.base', 'If you set a custom title, it will not be translated to the user\'s language unless you have a custom translation file in the protected/config folder. Leave blank to set default title.'),
            'hideRegistrationUsernameField' => Yii::t('AuthKeycloakModule.base', 'If the username sent by Keycloak is the user\'s email, it is replaced by a username auto-generated from the first and last name (CamelCase formatted)'),
            'hideAdminUserEditPassword' => Yii::t('AuthKeycloakModule.base', 'For administrators allowed to manage users'),
            'apiUsername' => Yii::t('AuthKeycloakModule.base', 'This admin user must be created in the same realm as the one entered in the {RealmName} field. If your realm is {masterRealmName}, just assign the {adminRoleName} role to this user. Otherwise, you need to add the {realmManagementClientRole} Client Role and assign all Roles. {MoreInformationHere}', [
                'RealmName' => '“' . Yii::t('AuthKeycloakModule.base', 'Realm name') . '”',
                'masterRealmName' => '“master”',
                'adminRoleName' => '“admin”',
                'realmManagementClientRole' => '“realm-management”',
                'MoreInformationHere' => Html::a(Yii::t('AuthKeycloakModule.base', 'More informations here.'), 'https://stackoverflow.com/a/65054444', ['target' => '_blank']),
            ]),
            'updatedBrokerUsernameFromHumhubUsername' => Yii::t('AuthKeycloakModule.base', 'Will only work if in Keycloak\'s realm settings "Email as username" is disabled and "Edit username" is enabled.'),
            'groupsSyncMode' => Yii::t('AuthKeycloakModule.base', 'HumHub to Keycloak sync is done in real time. Keycloak to HumHub sync is done once a day. Keycloak subgroups are not synced.'),
        ];
    }

    /**
     * @return array
     */
    public function groupsSyncModeItems()
    {
        return [
            self::GROUP_SYNC_MODE_NONE => Yii::t('AuthKeycloakModule.base', 'No sync'),
            self::GROUP_SYNC_MODE_HH_TO_KC => Yii::t('AuthKeycloakModule.base', 'Sync HumHub towards Keycloak'),
            self::GROUP_SYNC_MODE_KC_TO_HH => Yii::t('AuthKeycloakModule.base', 'Sync Keycloak towards HumHub'),
            self::GROUP_SYNC_MODE_FULL => Yii::t('AuthKeycloakModule.base', 'Sync both ways'),
            self::GROUP_SYNC_MODE_HH_TO_KC_NO_DEL => Yii::t('AuthKeycloakModule.base', 'Sync HumHub towards Keycloak (but no removal on Keycloak)'),
            self::GROUP_SYNC_MODE_KC_TO_HH_NO_DEL => Yii::t('AuthKeycloakModule.base', 'Sync Keycloak towards HumHub (but no removal on HumHub)'),
            self::GROUP_SYNC_MODE_FULL_NO_KC_DEL => Yii::t('AuthKeycloakModule.base', 'Sync both ways (but no removal on Keycloak)'),
            self::GROUP_SYNC_MODE_FULL_NO_HH_DEL => Yii::t('AuthKeycloakModule.base', 'Sync both ways (but no removal on HumHub)'),
            self::GROUP_SYNC_MODE_FULL_NO_DEL => Yii::t('AuthKeycloakModule.base', 'Sync both ways (but no removal on Keycloak or HumHub)'),
        ];
    }

    /**
     * Saves module settings
     */
    public function save()
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');

        $module->settings->set('enabled', $this->enabled);
        $module->settings->set('clientId', trim((string)$this->clientId));
        $module->settings->set('clientSecret', trim((string)$this->clientSecret));
        $module->settings->set('realm', trim((string)$this->realm));
        $module->settings->set('baseUrl', rtrim(trim((string)$this->baseUrl), '/'));
        $module->settings->set('usernameMapper', trim((string)$this->usernameMapper));
        if (!$this->title) {
            $this->title = self::DEFAULT_TITLE;
        }
        $module->settings->set('title', $this->title);
        $module->settings->set('hideRegistrationUsernameField', $this->hideRegistrationUsernameField);
        $module->settings->set('hideAdminUserEditPassword', $this->hideAdminUserEditPassword);
        $module->settings->set('apiUsername', $this->apiUsername);
        $module->settings->set('apiPassword', $this->apiPassword);
        $module->settings->set('groupsSyncMode', $this->groupsSyncMode);

        // Following settings can be enabled only if API settings are entered
        if (!$this->hasApiParams()) {
            $this->removeKeycloakSessionsAfterLogout = false;
            $this->updatedBrokerUsernameFromHumhubUsername = false;
            $this->updatedBrokerEmailFromHumhubEmail = false;
            $this->addChangePasswordFormToAccount = false;
        }
        $module->settings->set('removeKeycloakSessionsAfterLogout', $this->removeKeycloakSessionsAfterLogout);
        $module->settings->set('updateHumhubUsernameFromBrokerUsername', $this->updateHumhubUsernameFromBrokerUsername);
        $module->settings->set('updatedBrokerUsernameFromHumhubUsername', $this->updatedBrokerUsernameFromHumhubUsername);
        $module->settings->set('updateHumhubEmailFromBrokerEmail', $this->updateHumhubEmailFromBrokerEmail);
        $module->settings->set('updatedBrokerEmailFromHumhubEmail', $this->updatedBrokerEmailFromHumhubEmail);
        $module->settings->set('addChangePasswordFormToAccount', $this->addChangePasswordFormToAccount);

        // Add groups sync to jobs
        if (
            $this->enabled
            && $this->groupsSyncMode !== self::GROUP_SYNC_MODE_NONE
            && $this->hasApiParams()
        ) {
            Yii::$app->queue->push(new GroupsFullSync(['firstSync' => true]));
        }

        return true;
    }

    /**
     * @return bool
     */
    public function hasApiParams()
    {
        return $this->baseUrl && $this->realm && $this->apiUsername && $this->apiPassword;
    }

    /**
     * @param bool $canRemoveOnKeycloak
     * @return bool
     */
    public function syncHumhubGroupsToKeycloak(bool $canRemoveOnKeycloak = false)
    {
        $hh2Kc = in_array($this->groupsSyncMode, [
            self::GROUP_SYNC_MODE_HH_TO_KC,
            self::GROUP_SYNC_MODE_FULL,
            self::GROUP_SYNC_MODE_FULL_NO_HH_DEL,
        ], true);
        if ($canRemoveOnKeycloak) {
            return $hh2Kc;
        }
        return $hh2Kc || in_array($this->groupsSyncMode, [
            self::GROUP_SYNC_MODE_HH_TO_KC_NO_DEL,
            self::GROUP_SYNC_MODE_FULL_NO_DEL,
            self::GROUP_SYNC_MODE_FULL_NO_KC_DEL,
        ], true);
    }

    /**
     * @param $canRemoveOnHumhub
     * @return bool
     */
    public function syncKeycloakGroupsToHumhub($canRemoveOnHumhub = false)
    {
        $hh2Kc = in_array($this->groupsSyncMode, [
            self::GROUP_SYNC_MODE_KC_TO_HH,
            self::GROUP_SYNC_MODE_FULL,
            self::GROUP_SYNC_MODE_FULL_NO_KC_DEL,
        ], true);
        if ($canRemoveOnHumhub) {
            return $hh2Kc;
        }
        return $hh2Kc || in_array($this->groupsSyncMode, [
            self::GROUP_SYNC_MODE_KC_TO_HH_NO_DEL,
            self::GROUP_SYNC_MODE_FULL_NO_DEL,
            self::GROUP_SYNC_MODE_FULL_NO_HH_DEL,
        ], true);
    }
}

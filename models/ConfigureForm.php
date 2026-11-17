<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.app](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\models;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\jobs\GroupsFullSync;
use humhub\modules\authKeycloak\jobs\UsersFullSync;
use humhub\modules\authKeycloak\Module;
use humhub\modules\authKeycloak\source\KeycloakUserSource;
use humhub\modules\user\models\Profile;
use humhub\modules\user\models\User;
use humhub\modules\user\services\UserSourceService;
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
     * What a stored secret (the client secret, the API admin password) renders as once saved.
     * Submitting this value unchanged keeps the stored secret; clearing the field and submitting
     * empty deletes it. Never echo a real stored secret back into the form: any other admin could
     * read or copy it via the password field's reveal icon.
     */
    public const SECRET_PLACEHOLDER = '••••••••';

    /**
     * Keycloak API authentication with an admin user (username and password, "password" grant)
     */
    public const API_AUTH_METHOD_USER = 'user';
    /**
     * Keycloak API authentication with the service account of the client (client ID and secret, "client_credentials" grant)
     */
    public const API_AUTH_METHOD_SERVICE_ACCOUNT = 'serviceAccount';

    /**
     * Settings requiring write access to the Keycloak API, disabled if {@see $apiReadOnly} is enabled
     */
    public const API_WRITE_ATTRIBUTES = [
        'removeKeycloakSessionsAfterLogout',
        'updatedBrokerUsernameFromHumhubUsername',
        'updatedBrokerEmailFromHumhubEmail',
        'addChangePasswordFormToAccount',
        'disableKeycloakUserOnDelete',
    ];

    /**
     * Groups sync modes writing to Keycloak => read-only mode replacing them if {@see $apiReadOnly} is enabled
     */
    public const API_READ_ONLY_GROUP_SYNC_MODES = [
        self::GROUP_SYNC_MODE_HH_TO_KC => self::GROUP_SYNC_MODE_NONE,
        self::GROUP_SYNC_MODE_HH_TO_KC_NO_DEL => self::GROUP_SYNC_MODE_NONE,
        self::GROUP_SYNC_MODE_FULL => self::GROUP_SYNC_MODE_KC_TO_HH,
        self::GROUP_SYNC_MODE_FULL_NO_KC_DEL => self::GROUP_SYNC_MODE_KC_TO_HH,
        self::GROUP_SYNC_MODE_FULL_NO_HH_DEL => self::GROUP_SYNC_MODE_KC_TO_HH_NO_DEL,
        self::GROUP_SYNC_MODE_FULL_NO_DEL => self::GROUP_SYNC_MODE_KC_TO_HH_NO_DEL,
    ];

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
     * @var string one mapping per line: `humhub-profile-field=keycloak-attribute` (or `username=keycloak-attribute`)
     */
    public $attributeMapping = '';
    /**
     * @var string
     */
    public $title;
    /**
     * @var bool Redirect the login page to Keycloak
     */
    public $forceKeycloakAuth = false;
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
    public $trustOnlyVerifiedEmail = false;
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
     * @var bool
     */
    public $disableKeycloakUserOnDelete = false;
    /**
     * @var string
     */
    public $apiAuthMethod = self::API_AUTH_METHOD_USER;
    /**
     * @var string
     */
    public $apiUsername = '';
    /**
     * @var string
     */
    public $apiPassword = '';
    /**
     * @var bool The API admin user has only read permissions on Keycloak
     */
    public $apiReadOnly = false;
    /**
     * @var bool Create, update and deactivate HumHub users from the Keycloak users (hourly)
     */
    public $syncUsers = false;
    /**
     * @var bool The users sync adopts the HumHub users of other user sources matching a Keycloak user (migration)
     */
    public $adoptExistingUsers = false;
    /**
     * @var string
     */
    public $groupsSyncMode = self::GROUP_SYNC_MODE_NONE;

    /**
     * @var string|null cache of {@see getUserSourceCountHint()}
     */
    private ?string $userSourceCountHint = null;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['clientId', 'clientSecret', 'realm', 'baseUrl'], 'required'],
            [['clientId', 'clientSecret', 'baseUrl', 'title', 'realm', 'apiUsername', 'apiPassword'], 'string'],
            [['apiAuthMethod'], 'in', 'range' => [self::API_AUTH_METHOD_USER, self::API_AUTH_METHOD_SERVICE_ACCOUNT]],
            [['enabled', 'forceKeycloakAuth', 'hideRegistrationUsernameField', 'hideAdminUserEditPassword', 'trustOnlyVerifiedEmail', 'removeKeycloakSessionsAfterLogout', 'updateHumhubUsernameFromBrokerUsername', 'updatedBrokerUsernameFromHumhubUsername', 'updateHumhubEmailFromBrokerEmail', 'updatedBrokerEmailFromHumhubEmail', 'addChangePasswordFormToAccount', 'disableKeycloakUserOnDelete', 'apiReadOnly', 'syncUsers', 'adoptExistingUsers'], 'boolean'],
            [['groupsSyncMode'], 'safe'],
            [['attributeMapping'], 'string'],
            [['attributeMapping'], 'validateAttributeMapping'],
        ];
    }

    /**
     * Checks the format of each line and that the HumHub field is a profile field or the username
     */
    public function validateAttributeMapping($attribute): void
    {
        $profile = new Profile();
        foreach (explode("\n", (string)$this->$attribute) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('=', $line, 2));
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                $this->addError($attribute, Yii::t('AuthKeycloakModule.base', 'Invalid line "{line}", the expected format is {format}.', [
                    'line' => $line,
                    'format' => 'humhub-profile-field=keycloak-attribute',
                ]));
            } elseif ($parts[0] !== 'username' && ($parts[0] === 'user_id' || !$profile->hasAttribute($parts[0]))) {
                $this->addError($attribute, Yii::t('AuthKeycloakModule.base', '"{field}" is not a HumHub profile field.', [
                    'field' => $parts[0],
                ]));
            }
        }
    }

    /**
     * @param string|null $attributeMapping one mapping per line: `humhub-profile-field=keycloak-attribute` (or `username=keycloak-attribute`)
     * @return array HumHub profile field (or `username`) => Keycloak attribute (claim name)
     */
    public static function parseAttributeMapping(?string $attributeMapping): array
    {
        $mapping = [];
        foreach (explode("\n", (string)$attributeMapping) as $line) {
            $parts = array_map('trim', explode('=', $line, 2));
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '' && $parts[0] !== 'user_id') {
                $mapping[$parts[0]] = $parts[1];
            }
        }
        return $mapping;
    }

    /**
     * @return array HumHub profile field (or `username`) => Keycloak attribute (claim name)
     */
    public function getAttributeMappingArray(): array
    {
        return static::parseAttributeMapping($this->attributeMapping);
    }

    /**
     * Replaces the stored secrets with the placeholder for display. This class doubles as the
     * settings accessor for every real usage (`KeycloakApi`, `Keycloak` auth client, the group
     * sync jobs and events all build their own `new ConfigureForm()`), so masking cannot live in
     * {@see init()} — that would hand the literal placeholder to Keycloak as the client secret or
     * admin password. Only the config controller may call this, and only right before rendering
     * the form.
     */
    public function maskSecretsForDisplay(): void
    {
        if ((string)$this->clientSecret !== '') {
            $this->clientSecret = self::SECRET_PLACEHOLDER;
        }
        if ((string)$this->apiPassword !== '') {
            $this->apiPassword = self::SECRET_PLACEHOLDER;
        }
    }

    /**
     * If a submitted secret is still the placeholder, the admin left it untouched: restore the
     * real value so {@see save()} does not overwrite it with the placeholder bullets.
     */
    public function restoreSecretsIfUnchanged(?string $storedClientSecret, ?string $storedApiPassword): void
    {
        if ($this->clientSecret === self::SECRET_PLACEHOLDER) {
            $this->clientSecret = $storedClientSecret;
        }
        if ($this->apiPassword === self::SECRET_PLACEHOLDER) {
            $this->apiPassword = $storedApiPassword;
        }
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
        $this->attributeMapping = $settings->get('attributeMapping', $this->attributeMapping);
        $this->title = $settings->get('title', Yii::t('AuthKeycloakModule.base', self::DEFAULT_TITLE));
        $this->forceKeycloakAuth = (bool)$settings->get('forceKeycloakAuth', $this->forceKeycloakAuth);
        $this->hideRegistrationUsernameField = (bool)$settings->get('hideRegistrationUsernameField', $this->hideRegistrationUsernameField);
        $this->hideAdminUserEditPassword = (bool)$settings->get('hideAdminUserEditPassword', $this->hideAdminUserEditPassword);
        $this->trustOnlyVerifiedEmail = (bool)$settings->get('trustOnlyVerifiedEmail', $this->trustOnlyVerifiedEmail);
        $this->removeKeycloakSessionsAfterLogout = (bool)$settings->get('removeKeycloakSessionsAfterLogout', $this->removeKeycloakSessionsAfterLogout);
        $this->updateHumhubUsernameFromBrokerUsername = (bool)$settings->get('updateHumhubUsernameFromBrokerUsername', $this->updateHumhubUsernameFromBrokerUsername);
        $this->updatedBrokerUsernameFromHumhubUsername = (bool)$settings->get('updatedBrokerUsernameFromHumhubUsername', $this->updatedBrokerUsernameFromHumhubUsername);
        $this->updateHumhubEmailFromBrokerEmail = (bool)$settings->get('updateHumhubEmailFromBrokerEmail', $this->updateHumhubEmailFromBrokerEmail);
        $this->updatedBrokerEmailFromHumhubEmail = (bool)$settings->get('updatedBrokerEmailFromHumhubEmail', $this->updatedBrokerEmailFromHumhubEmail);
        $this->addChangePasswordFormToAccount = (bool)$settings->get('addChangePasswordFormToAccount', $this->addChangePasswordFormToAccount);
        $this->disableKeycloakUserOnDelete = (bool)$settings->get('disableKeycloakUserOnDelete', $this->disableKeycloakUserOnDelete);
        $this->apiAuthMethod = $settings->get('apiAuthMethod', $this->apiAuthMethod);
        $this->apiUsername = $settings->get('apiUsername', $this->apiUsername);
        $this->apiPassword = $settings->get('apiPassword', $this->apiPassword);
        $this->groupsSyncMode = $settings->get('groupsSyncMode', $this->groupsSyncMode);
        $this->apiReadOnly = (bool)$settings->get('apiReadOnly', $this->apiReadOnly);
        $this->syncUsers = (bool)$settings->get('syncUsers', $this->syncUsers);
        $this->adoptExistingUsers = (bool)$settings->get('adoptExistingUsers', $this->adoptExistingUsers);
        $this->applyApiReadOnly();

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
            'attributeMapping' => Yii::t('AuthKeycloakModule.base', 'Attribute mapping'),
            'title' => Yii::t('AuthKeycloakModule.base', 'Title of the button'),
            'forceKeycloakAuth' => Yii::t('AuthKeycloakModule.base', 'Always sign in with Keycloak'),
            'hideRegistrationUsernameField' => Yii::t('AuthKeycloakModule.base', 'Hide username field in registration form'),
            'hideAdminUserEditPassword' => Yii::t('AuthKeycloakModule.base', 'In admin, hide password fields in edit user form'),
            'trustOnlyVerifiedEmail' => Yii::t('AuthKeycloakModule.base', 'Use the email sent by Keycloak only if it is verified on Keycloak (recommended if Keycloak users can set an email without verifying it)'),
            'removeKeycloakSessionsAfterLogout' => Yii::t('AuthKeycloakModule.base', 'Remove user\'s Keycloak sessions after logout'),
            'updateHumhubUsernameFromBrokerUsername' => Yii::t('AuthKeycloakModule.base', 'Update user\'s username on HumHub when changed on Keycloak'),
            'updatedBrokerUsernameFromHumhubUsername' => Yii::t('AuthKeycloakModule.base', 'Update user\'s username on Keycloak when changed on HumHub'),
            'updateHumhubEmailFromBrokerEmail' => Yii::t('AuthKeycloakModule.base', 'Update user\'s email on HumHub when changed on Keycloak'),
            'updatedBrokerEmailFromHumhubEmail' => Yii::t('AuthKeycloakModule.base', 'Update user\'s email on Keycloak when changed on HumHub'),
            'addChangePasswordFormToAccount' => Yii::t('AuthKeycloakModule.base', 'Add a page in account settings allowing users to change their Keycloak password'),
            'disableKeycloakUserOnDelete' => Yii::t('AuthKeycloakModule.base', 'Deactivate user\'s account on Keycloak when deleted on HumHub'),
            'apiAuthMethod' => Yii::t('AuthKeycloakModule.base', 'Keycloak API authentication'),
            'apiUsername' => Yii::t('AuthKeycloakModule.base', 'Keycloak API admin username'),
            'apiPassword' => Yii::t('AuthKeycloakModule.base', 'Keycloak API admin password'),
            'groupsSyncMode' => Yii::t('AuthKeycloakModule.base', 'Groups sync'),
            'apiReadOnly' => Yii::t('AuthKeycloakModule.base', 'The API user or service account has only read permissions'),
            'syncUsers' => Yii::t('AuthKeycloakModule.base', 'Fetch/Update users automatically'),
            'adoptExistingUsers' => Yii::t('AuthKeycloakModule.base', 'Adopt existing users (migration)'),
        ];
    }

    /**
     * @return string number of HumHub users of the Keycloak user source, empty if the user source is disabled
     */
    protected function getUserSourceCountHint(): string
    {
        // attributeHints() is called for each field
        return $this->userSourceCountHint ??= $this->buildUserSourceCountHint();
    }

    private function buildUserSourceCountHint(): string
    {
        $collection = UserSourceService::getCollection();
        if (!$collection->hasUserSource(KeycloakUserSource::SOURCE_ID)) {
            return '';
        }
        /** @var KeycloakUserSource $source */
        $source = $collection->getUserSource(KeycloakUserSource::SOURCE_ID);
        $counts = $source->countUsersByStatus();

        return ' ' . Yii::t('AuthKeycloakModule.base', 'Users currently assigned to the Keycloak user source: {total} ({enabled} enabled, {deactivated} deactivated, {needApproval} awaiting approval, {softDeleted} soft deleted).', [
            'total' => array_sum($counts),
            'enabled' => $counts[User::STATUS_ENABLED],
            'deactivated' => $counts[User::STATUS_DEACTIVATED],
            'needApproval' => $counts[User::STATUS_NEED_APPROVAL],
            'softDeleted' => $counts[User::STATUS_SOFT_DELETED],
        ]);
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
            'attributeMapping' => Yii::t('AuthKeycloakModule.base', 'One mapping per line, format: {format}, e.g. {example}. The Keycloak attribute is the claim name sent on sign in and the user attribute name read by the users sync: on Keycloak, add a {userAttributeMapper} mapper to the client\'s dedicated scope with the same name for {userAttribute} and {tokenClaimName}, and {addToUserinfo} enabled. Mapped profile fields are updated on each sign in and by the users sync, and cannot be changed on HumHub anymore. Default mappings: {defaults}. For the username, use e.g. {usernameExample} to use the Keycloak username instead of the Keycloak ID: it is used on account creation, and updated only if {updateUsername} is enabled. To display all the attributes of a Keycloak user, run {command}.', [
                'format' => '`humhub-profile-field=keycloak-attribute`',
                'example' => '`phone_work=phoneNumber`',
                'userAttributeMapper' => '“User Attribute”',
                'userAttribute' => '“User Attribute”',
                'tokenClaimName' => '“Token Claim Name”',
                'addToUserinfo' => '“Add to userinfo”',
                'command' => '`php yii auth-keycloak/show-user <username>`',
                'defaults' => '`username=sub`, `firstname=given_name`, `lastname=family_name`',
                'usernameExample' => '`username=preferred_username`',
                'updateUsername' => '“' . Yii::t('AuthKeycloakModule.base', 'Update user\'s username on HumHub when changed on Keycloak') . '”',
            ]),
            'title' => Yii::t('AuthKeycloakModule.base', 'If you set a custom title, it will not be translated to the user\'s language unless you have a custom translation file in the protected/config folder. Leave blank to set default title.'),
            'forceKeycloakAuth' => Yii::t('AuthKeycloakModule.base', 'The login page and the invite links redirect to Keycloak. Warning: make sure that at least one administrator can sign in with Keycloak. The HumHub login form remains available at: {url}. Recommended: also enable {removeSessions}, otherwise users are signed in again right after signing out.', [
                'url' => Html::tag('code', Html::encode(Url::to(['/user/auth/login', 'noKeycloak' => 1], true))),
                'removeSessions' => '“' . Yii::t('AuthKeycloakModule.base', 'Remove user\'s Keycloak sessions after logout') . '”',
            ]),
            'hideRegistrationUsernameField' => Yii::t('AuthKeycloakModule.base', 'If the username sent by Keycloak is the user\'s email, it is replaced by a username auto-generated from the first and last name (CamelCase formatted)'),
            'hideAdminUserEditPassword' => Yii::t('AuthKeycloakModule.base', 'For administrators allowed to manage users'),
            'trustOnlyVerifiedEmail' => Yii::t('AuthKeycloakModule.base', 'If enabled, an email that is not verified on Keycloak is ignored when signing in: it is not used to sign in to an existing HumHub account with the same email, to create a HumHub account (if an email is required, signing in fails until the email is verified on Keycloak), or to update the HumHub email. Users already connected with Keycloak can still sign in. Keycloak must send the {emailVerifiedClaim} claim (it does by default). If disabled, the email sent by Keycloak is always trusted: if Keycloak allows setting an email without verifying it (e.g. self-registration without email verification), anyone can sign in to a HumHub account by using its email on Keycloak.', ['emailVerifiedClaim' => '`email_verified`']),
            'apiAuthMethod' => Yii::t('AuthKeycloakModule.base', 'With the service account, the client ID and secret above are used to access the API: in Keycloak, enable {serviceAccountsRoles} in the client settings and assign the roles of the {realmManagementClientRole} client to the service account (in the client\'s {serviceAccountsRolesTab} tab). No admin user is needed and {directAccessGrants} can stay disabled.', [
                'serviceAccountsRoles' => '“Service accounts roles”',
                'realmManagementClientRole' => '“realm-management”',
                'serviceAccountsRolesTab' => '“Service accounts roles”',
                'directAccessGrants' => '“Direct access grants”',
            ]),
            'apiUsername' => Yii::t('AuthKeycloakModule.base', 'This admin user must be created in the same realm as the one entered in the {RealmName} field. If your realm is {masterRealmName}, just assign the {adminRoleName} role to this user. Otherwise, you need to add the {realmManagementClientRole} Client Role and assign all Roles. {MoreInformationHere}', [
                'RealmName' => '“' . Yii::t('AuthKeycloakModule.base', 'Realm name') . '”',
                'masterRealmName' => '“master”',
                'adminRoleName' => '“admin”',
                'realmManagementClientRole' => '“realm-management”',
                'MoreInformationHere' => Html::a(Yii::t('AuthKeycloakModule.base', 'More informations here.'), 'https://stackoverflow.com/a/65054444', ['target' => '_blank']),
            ]),
            'updatedBrokerUsernameFromHumhubUsername' => Yii::t('AuthKeycloakModule.base', 'Will only work if in Keycloak\'s realm settings "Email as username" is disabled and "Edit username" is enabled.'),
            'addChangePasswordFormToAccount' => Yii::t('AuthKeycloakModule.base', 'Warning: this page does not ask for the current password (users signing in with Keycloak may not know it). Anyone with access to an open session of the user can therefore change the user\'s Keycloak password.'),
            'disableKeycloakUserOnDelete' => Yii::t('AuthKeycloakModule.base', 'The Keycloak account is disabled (not deleted) and its Keycloak sessions are removed. It can be re-enabled on Keycloak. The Keycloak API admin user is never disabled.'),
            'groupsSyncMode' => Yii::t('AuthKeycloakModule.base', 'Synchronizes the HumHub groups and subgroups, and their members, with the Keycloak groups having the same name. User accounts are not created or updated (see {syncUsers}). HumHub to Keycloak sync is done in real time. Keycloak to HumHub sync is done once a day, and for the signed-in user on each sign in. Keycloak groups deeper than the first level of subgroups are not synced.', [
                'syncUsers' => '“' . Yii::t('AuthKeycloakModule.base', 'Fetch/Update users automatically') . '”',
            ]),
            'apiReadOnly' => Yii::t('AuthKeycloakModule.base', 'Instead of all roles, assign only the {roles} roles of the {realmManagementClientRole} client to the API user or service account. Settings writing to Keycloak are then disabled.', [
                'roles' => '“view-users”, “query-groups”, “view-realm”',
                'realmManagementClientRole' => '“realm-management”',
            ]),
            'syncUsers' => Yii::t('AuthKeycloakModule.base', 'Every hour, HumHub accounts are created for new Keycloak users, updated (first name, last name and, depending on the settings above, username and email), and deactivated if the user is disabled or removed on Keycloak. Can also be run with the command {command}.', [
                'command' => '`php yii auth-keycloak/sync-users`',
            ]) . $this->getUserSourceCountHint(),
            'adoptExistingUsers' => Yii::t('AuthKeycloakModule.base', 'While enabled, the users sync takes over the HumHub accounts of other user sources (e.g. local or LDAP) matching an enabled Keycloak user, instead of skipping them: accounts already linked to the Keycloak user, or having the same email if not linked to another Keycloak user. They are assigned to the Keycloak user source and updated from Keycloak, and can then only sign in with Keycloak. Soft deleted accounts and accounts awaiting approval are not adopted. Intended for migrations to Keycloak: enable it for the migration only, then disable it. Every adoption is logged. Recommended: also enable {trustOnlyVerifiedEmail}.', [
                'trustOnlyVerifiedEmail' => '“' . Yii::t('AuthKeycloakModule.base', 'Use the email sent by Keycloak only if it is verified on Keycloak (recommended if Keycloak users can set an email without verifying it)') . '”',
            ]),
        ];
    }

    /**
     * @return array
     */
    public function apiAuthMethodItems(): array
    {
        return [
            self::API_AUTH_METHOD_USER => Yii::t('AuthKeycloakModule.base', 'Admin user (username and password)'),
            self::API_AUTH_METHOD_SERVICE_ACCOUNT => Yii::t('AuthKeycloakModule.base', 'Service account of the client (client ID and secret)'),
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
        $module->settings->set('attributeMapping', trim((string)$this->attributeMapping));
        if (!$this->title) {
            $this->title = self::DEFAULT_TITLE;
        }
        $module->settings->set('title', $this->title);
        $module->settings->set('forceKeycloakAuth', $this->forceKeycloakAuth);
        $module->settings->set('hideRegistrationUsernameField', $this->hideRegistrationUsernameField);
        $module->settings->set('hideAdminUserEditPassword', $this->hideAdminUserEditPassword);
        $module->settings->set('trustOnlyVerifiedEmail', $this->trustOnlyVerifiedEmail);
        $module->settings->set('apiAuthMethod', $this->apiAuthMethod);
        $module->settings->set('apiUsername', $this->apiUsername);
        $module->settings->set('apiPassword', $this->apiPassword);
        $module->settings->set('apiReadOnly', $this->apiReadOnly);

        // Following settings can be enabled only if API settings are entered
        if (!$this->hasApiParams()) {
            $this->removeKeycloakSessionsAfterLogout = false;
            $this->updatedBrokerUsernameFromHumhubUsername = false;
            $this->updatedBrokerEmailFromHumhubEmail = false;
            $this->addChangePasswordFormToAccount = false;
            $this->disableKeycloakUserOnDelete = false;
            $this->syncUsers = false;
            $this->adoptExistingUsers = false;
        }
        $this->applyApiReadOnly();
        $module->settings->set('groupsSyncMode', $this->groupsSyncMode);
        $module->settings->set('syncUsers', $this->syncUsers);
        $module->settings->set('adoptExistingUsers', $this->adoptExistingUsers);
        $module->settings->set('removeKeycloakSessionsAfterLogout', $this->removeKeycloakSessionsAfterLogout);
        $module->settings->set('updateHumhubUsernameFromBrokerUsername', $this->updateHumhubUsernameFromBrokerUsername);
        $module->settings->set('updatedBrokerUsernameFromHumhubUsername', $this->updatedBrokerUsernameFromHumhubUsername);
        $module->settings->set('updateHumhubEmailFromBrokerEmail', $this->updateHumhubEmailFromBrokerEmail);
        $module->settings->set('updatedBrokerEmailFromHumhubEmail', $this->updatedBrokerEmailFromHumhubEmail);
        $module->settings->set('addChangePasswordFormToAccount', $this->addChangePasswordFormToAccount);
        $module->settings->set('disableKeycloakUserOnDelete', $this->disableKeycloakUserOnDelete);

        // Add groups sync to jobs
        if (
            $this->enabled
            && $this->groupsSyncMode !== self::GROUP_SYNC_MODE_NONE
            && $this->hasApiParams()
        ) {
            Yii::$app->queue->push(new GroupsFullSync(['firstSync' => true]));
        }

        // Add users sync to jobs
        if (
            $this->enabled
            && $this->syncUsers
        ) {
            Yii::$app->queue->push(new UsersFullSync());
        }

        return true;
    }

    /**
     * Disables the settings requiring write access to the Keycloak API if the API is read-only
     */
    protected function applyApiReadOnly(): void
    {
        if (!$this->apiReadOnly) {
            return;
        }
        foreach (self::API_WRITE_ATTRIBUTES as $attribute) {
            $this->$attribute = false;
        }
        $this->groupsSyncMode = self::API_READ_ONLY_GROUP_SYNC_MODES[$this->groupsSyncMode] ?? $this->groupsSyncMode;
    }

    /**
     * @return bool
     */
    public function hasApiParams()
    {
        if (!$this->baseUrl || !$this->realm) {
            return false;
        }
        if ($this->isApiServiceAccount()) {
            return $this->clientId && $this->clientSecret;
        }
        return $this->apiUsername && $this->apiPassword;
    }

    /**
     * @return bool if the Keycloak API is accessed with the service account of the client
     */
    public function isApiServiceAccount(): bool
    {
        return $this->apiAuthMethod === self::API_AUTH_METHOD_SERVICE_ACCOUNT;
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

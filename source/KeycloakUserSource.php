<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.app](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\source;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\components\KeycloakApi;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\Module;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\forms\Registration;
use humhub\modules\user\models\User;
use humhub\modules\user\services\UserSourceService;
use humhub\modules\user\source\BaseUserSource;
use humhub\modules\user\source\UserSourceInterface;
use Yii;
use yii\authclient\ClientInterface;
use yii\helpers\VarDumper;

/**
 * KeycloakUserSource owns users provisioned through the Keycloak auth client.
 *
 * Replaces the pre-1.19 `PrimaryClient` marker that set `user.auth_mode = 'Keycloak'`.
 * Registered by {@see \humhub\modules\authKeycloak\Events::onUserSourceCollectionSet()}
 * when {@see Module::$provideUserSource} is enabled (default: true).
 *
 * @since 1.6.0
 */
class KeycloakUserSource extends BaseUserSource
{
    public const SOURCE_ID = Keycloak::DEFAULT_NAME;

    /**
     * Results of a user sync, see {@see syncUsers()}
     */
    public const SYNC_CREATED = 'created';
    public const SYNC_ADOPTED = 'adopted';
    public const SYNC_UPDATED = 'updated';
    public const SYNC_UNCHANGED = 'unchanged';
    public const SYNC_ENABLED = 'enabled';
    public const SYNC_DISABLED = 'disabled';
    public const SYNC_SKIPPED = 'skipped';
    public const SYNC_FAILED = 'failed';

    /**
     * Steps of a user sync, see {@see syncUsers()}
     */
    public const PROGRESS_LIST = 'list';
    public const PROGRESS_SYNC = 'sync';

    /**
     * @var int|null number of Keycloak users retrieved by the last {@see syncUsers()}
     */
    public $lastSyncKeycloakUsersCount;

    /**
     * @var bool|null whether the last {@see syncUsers()} listed all the Keycloak users (otherwise, no user is deactivated)
     */
    public $lastSyncIsComplete;

    /**
     * @var float|null duration in seconds of the retrieval of the Keycloak users by the last {@see syncUsers()}
     */
    public $lastSyncListDuration;

    public function init()
    {
        parent::init();

        if ($this->id === '') {
            $this->id = self::SOURCE_ID;
        }
        if ($this->allowedAuthClientIds === []) {
            $this->allowedAuthClientIds = [self::SOURCE_ID];
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title !== '' ? $this->title : 'Keycloak';
    }

    /**
     * Only the Keycloak auth client writes the managed attributes on sign in;
     * other allowed auth clients (e.g. password) only authenticate. The users
     * sync calls {@see UserSourceService::updateUser()} directly.
     *
     * Consulted since HumHub 1.19.4.
     */
    public function isAttributeSyncAllowed(ClientInterface $authClient): bool
    {
        return $authClient instanceof Keycloak;
    }

    /**
     * Managed attributes are derived from the existing module settings so
     * pre-1.19 sync configuration is preserved verbatim:
     *  - `updateHumhubEmailFromBrokerEmail`    → `email` is locked & synced
     *  - `updateHumhubUsernameFromBrokerUsername` → `username` is locked & synced
     *
     * Admins who previously disabled auto-sync keep the same behaviour and
     * the affected fields stay editable in the admin UI.
     *
     * With the `syncUsers` setting, the first and last names are also synced (hourly and on sign in).
     * The profile fields of the `attributeMapping` setting are always synced (on sign in and by the users sync).
     * A `username` mapping only sets the claim used for the username, which is synced only with `updateHumhubUsernameFromBrokerUsername`.
     */
    public function getManagedAttributes(): array
    {
        $module = $this->getKeycloakModule();
        if ($module === null) {
            return [];
        }

        $attrs = [];
        if ((bool) $module->settings->get('updateHumhubEmailFromBrokerEmail')) {
            $attrs[] = 'email';
        }
        if ((bool) $module->settings->get('updateHumhubUsernameFromBrokerUsername')) {
            $attrs[] = 'username';
        }
        if ((bool) $module->settings->get('syncUsers')) {
            $attrs[] = 'firstname';
            $attrs[] = 'lastname';
        }
        foreach (array_keys(ConfigureForm::parseAttributeMapping($module->settings->get('attributeMapping'))) as $field) {
            if ($field !== 'username') {
                $attrs[] = $field;
            }
        }

        return array_values(array_unique($attrs));
    }

    public function requiresApproval(?string $authClientId = null): bool
    {
        if ($authClientId !== null && in_array($authClientId, $this->trustedAuthClientIds, true)) {
            return false;
        }

        /** @var \humhub\modules\user\Module $userModule */
        $userModule = Yii::$app->getModule('user');
        return (bool) $userModule->settings->get('auth.needApproval');
    }

    public function getUsernameStrategy(): string
    {
        return UserSourceInterface::USERNAME_AUTO_GENERATE;
    }

    /**
     * Creates a HumHub user with `user_source = 'Keycloak'`.
     *
     * Note: the user_auth row is written by the auth client's
     * {@see Keycloak::createOrUpdateAuthRecord()} (which also stores
     * `keycloak_sid`). It is NOT created here.
     */
    public function createUser(array $attributes): ?User
    {
        return $this->registerUser($attributes, $this->requiresApproval(self::SOURCE_ID));
    }

    /**
     * @param bool $requiresApproval users created by the sync are not approved by an admin, as for LDAP
     * @param string|null $error set to the reason if the user could not be created
     */
    private function registerUser(array $attributes, bool $requiresApproval, ?string &$error = null): ?User
    {
        $registration = $this->buildRegistration($attributes, $requiresApproval);
        if ($registration === null) {
            $error = 'no valid username could be resolved';
            return null;
        }

        $registration->getUser()->user_source = $this->getId();

        if (!$registration->register()) {
            $error = $this->formatErrors($registration->getErrors());
            Yii::warning(
                'KeycloakUserSource: could not create user. Errors: '
                . VarDumper::dumpAsString($registration->getErrors()),
                'auth-keycloak',
            );
            return null;
        }

        return $registration->getUser();
    }

    private function buildRegistration(array $attributes, bool $requiresApproval): ?Registration
    {
        $registration = new Registration(enableEmailField: true, enablePasswordForm: false);
        $registration->enableUserApproval = $requiresApproval;

        unset(
            $attributes['id'],
            $attributes['guid'],
            $attributes['contentcontainer_id'],
            $attributes['user_source'],
            $attributes['status'],
        );

        // Users created by the sync cannot fill a registration form, so the username must be valid and unique
        $username = $this->getUsernameResolver()->resolve($attributes, $this->getUsernameStrategy());
        if ($username === null) {
            return null;
        }
        $attributes['username'] = $username;

        $registration->getUser()->setAttributes($attributes, false);
        $registration->getProfile()->setAttributes($attributes, false);
        $registration->getGroupUser()->setAttributes($attributes, false);
        $registration->setModels();

        return $registration;
    }

    /**
     * Full users sync: creates, updates, re-enables and deactivates the HumHub users from the Keycloak users.
     * Called by {@see \humhub\modules\authKeycloak\jobs\UsersFullSync} and the `auth-keycloak/sync-users` console command.
     *
     * Only users owned by this source are updated or deactivated. Keycloak users whose email is already used
     * by another HumHub account are skipped (they are linked on their first sign in with Keycloak, if allowed),
     * unless the `adoptExistingUsers` setting is enabled: the HumHub accounts of other user sources are then
     * adopted, see {@see findAdoptionCandidate()} and {@see adoptUser()}.
     *
     * @param callable|null $onProgress function (string $step, int $done, ?int $total, int $pass):
     * $step is PROGRESS_LIST (retrieving the Keycloak users, $pass being the number of the list request,
     * see {@see KeycloakApi::getAllUsers()}) or PROGRESS_SYNC (syncing the HumHub users)
     * @return array|null number of users per result (SYNC_* constants), null if the sync could not run
     */
    public function syncUsers(?callable $onProgress = null): ?array
    {
        $authClient = Yii::$app->authClientCollection->hasClient(Keycloak::DEFAULT_NAME)
            ? Yii::$app->authClientCollection->getClient(Keycloak::DEFAULT_NAME)
            : null;
        if (!$authClient instanceof Keycloak) {
            Yii::error('Users sync aborted: the Keycloak auth client is not enabled', 'auth-keycloak');
            return null;
        }

        // Abort on any error: with a partial list, missing users would be deactivated
        $start = microtime(true);
        $keycloakUsers = Yii::createObject(KeycloakApi::class)->getAllUsers(
            $isComplete,
            $onProgress !== null ? static fn($listedCount, $totalCount, $pass) => $onProgress(self::PROGRESS_LIST, $listedCount, $totalCount, $pass) : null,
        );
        $this->lastSyncListDuration = microtime(true) - $start;
        if ($keycloakUsers === null) {
            Yii::error('Users sync aborted: the Keycloak users could not be retrieved', 'auth-keycloak');
            return null;
        }
        if ($keycloakUsers === []) {
            Yii::error('Users sync aborted: no users were returned by Keycloak', 'auth-keycloak');
            return null;
        }
        $this->lastSyncKeycloakUsersCount = count($keycloakUsers);
        $this->lastSyncIsComplete = $isComplete;

        $results = array_fill_keys([
            self::SYNC_CREATED,
            self::SYNC_ADOPTED,
            self::SYNC_UPDATED,
            self::SYNC_UNCHANGED,
            self::SYNC_ENABLED,
            self::SYNC_DISABLED,
            self::SYNC_SKIPPED,
            self::SYNC_FAILED,
        ], 0);
        $config = new ConfigureForm();
        $apiUsername = mb_strtolower(trim((string)$config->apiUsername));
        $isKeycloakTrusted = $config->adoptExistingUsers === ConfigureForm::ADOPT_USERS_TRUSTED;
        $syncedUserIds = [];
        // Skipped or failed Keycloak users, with the reason
        $notSynced = [];

        foreach ($keycloakUsers as $i => $keycloakUser) {
            if ($onProgress !== null && $i % 10 === 0) {
                $onProgress(self::PROGRESS_SYNC, $i, count($keycloakUsers), 1);
            }
            if (empty($keycloakUser['id'])) {
                continue;
            }
            $attributes = $authClient->normalizeApiUserAttributes($keycloakUser);
            $isEnabledOnKeycloak = (bool)($keycloakUser['enabled'] ?? true);

            // The API user and the service accounts are technical accounts: no HumHub account is created or adopted for them
            $technicalReason = null;
            if (mb_strtolower((string)($keycloakUser['username'] ?? '')) === $apiUsername) {
                $technicalReason = 'Keycloak API user';
            } elseif (!empty($keycloakUser['serviceAccountClientId'])) {
                $technicalReason = 'service account of the client "' . $keycloakUser['serviceAccountClientId'] . '"';
            }
            $canAdopt = $config->isAdoptingExistingUsers() && $isEnabledOnKeycloak && $technicalReason === null;

            $reason = null;
            $notAdoptedReason = null;
            $user = $this->findUser($attributes) ?? ($canAdopt ? $this->findAdoptionCandidate($attributes, $isKeycloakTrusted, $notAdoptedReason) : null);
            if ($user !== null) {
                // Only the users of this source are deactivated if not synced
                $syncedUserIds[$user->id] = true;
                $result = $canAdopt && $this->isAdoptable($user, $isKeycloakTrusted)
                    ? $this->adoptUser($user, $attributes, $reason)
                    : $this->syncUser($user, $attributes, $isEnabledOnKeycloak, $reason);
                $results[$result]++;
                if ($reason !== null) {
                    $notSynced[$result][] = $this->formatKeycloakUser($keycloakUser) . ': ' . $reason;
                }
                continue;
            }

            // Do not create accounts for disabled users, the API user, service accounts and already used emails
            if (!$isEnabledOnKeycloak) {
                $reason = 'disabled on Keycloak';
            } elseif ($technicalReason !== null) {
                $reason = $technicalReason;
            } elseif (
                !empty($attributes['email'])
                && ($existingUser = User::findOne(['email' => $attributes['email']])) !== null
            ) {
                $reason = 'email ' . $attributes['email'] . ' already used by the HumHub user ' . $existingUser->username
                    . ' (ID ' . $existingUser->id . ', user source "' . $existingUser->user_source . '")'
                    . ($notAdoptedReason !== null ? ', not adopted: ' . $notAdoptedReason : '');
            }
            if ($reason !== null) {
                $results[self::SYNC_SKIPPED]++;
                $notSynced[self::SYNC_SKIPPED][] = $this->formatKeycloakUser($keycloakUser) . ': ' . $reason;
                continue;
            }

            $user = $this->registerUser($attributes, false, $reason);
            if ($user === null) {
                $results[self::SYNC_FAILED]++;
                $notSynced[self::SYNC_FAILED][] = $this->formatKeycloakUser($keycloakUser) . ': could not create the HumHub user (' . $reason . ')';
                continue;
            }
            $this->addAuth($user, $attributes['id']);
            $syncedUserIds[$user->id] = true;
            $results[self::SYNC_CREATED]++;
        }

        if ($onProgress !== null) {
            $onProgress(self::PROGRESS_SYNC, count($keycloakUsers), count($keycloakUsers), 1);
        }

        // Deactivate users removed from Keycloak, only if all the Keycloak users have been listed
        if ($isComplete) {
            $query = User::find()->where(['user_source' => $this->getId(), 'status' => User::STATUS_ENABLED]);
            foreach ($query->each() as $user) {
                /** @var User $user */
                if (!isset($syncedUserIds[$user->id]) && (new UserSourceService($user))->deleteUser()) {
                    Yii::warning('Users sync: deactivated user ' . $user->username . ' (' . $user->id . '), not found on Keycloak', 'auth-keycloak');
                    $results[self::SYNC_DISABLED]++;
                }
            }
        } else {
            Yii::warning('Users sync: the list of Keycloak users may be incomplete, so no HumHub user is deactivated for not being found on Keycloak', 'auth-keycloak');
        }

        foreach ($notSynced as $result => $lines) {
            Yii::warning(
                'Users sync: ' . count($lines) . ' Keycloak users ' . $result
                . " (Keycloak ID, username, first name, last name: reason):\n" . implode("\n", $lines),
                'auth-keycloak',
            );
        }

        Yii::info('Users sync done in ' . round(microtime(true) - $start) . ' s (retrieving the Keycloak users: ' . round($this->lastSyncListDuration) . ' s): ' . VarDumper::dumpAsString($results), 'auth-keycloak');

        return $results;
    }

    /**
     * Finds the HumHub user linked to the Keycloak user.
     * A user of this source not linked yet (e.g. created before the Auth record) is linked by email.
     *
     * @param array $attributes normalized Keycloak user attributes
     * @return User|null
     */
    public function findUser(array $attributes): ?User
    {
        $auth = Auth::findOne(['source' => Keycloak::DEFAULT_NAME, 'source_id' => (string)$attributes['id']]);
        if ($auth !== null && $auth->user !== null) {
            return $auth->user;
        }

        // The email is missing if not verified on Keycloak and only verified emails are trusted
        if (empty($attributes['email'])) {
            return null;
        }
        $user = User::findOne(['email' => $attributes['email'], 'user_source' => $this->getId()]);
        if (
            $user === null
            // Another Keycloak user is already linked to this account
            || Auth::find()->where(['source' => Keycloak::DEFAULT_NAME, 'user_id' => $user->id])->exists()
        ) {
            return null;
        }

        $this->addAuth($user, $attributes['id']);
        return $user;
    }

    /**
     * Whether the users sync may adopt the HumHub user (`adoptExistingUsers` setting): only users of another user source.
     * Soft deleted users and users awaiting approval are never adopted. Deactivated users (e.g. by a HumHub admin)
     * are adopted, and then enabled, only if Keycloak is trusted.
     *
     * @param bool $isKeycloakTrusted `adoptExistingUsers` setting is ADOPT_USERS_TRUSTED
     */
    private function isAdoptable(User $user, bool $isKeycloakTrusted): bool
    {
        return $user->user_source !== $this->getId()
            && in_array((int)$user->status, $isKeycloakTrusted ? [User::STATUS_ENABLED, User::STATUS_DEACTIVATED] : [User::STATUS_ENABLED], true);
    }

    /**
     * Finds the HumHub user of another user source having the email of a Keycloak user not linked to a HumHub user yet.
     * A user already linked to another Keycloak user is not adopted.
     *
     * Unless Keycloak is trusted, an email is no proof that the Keycloak user owns the HumHub account, so the email
     * must be verified on Keycloak (whatever the `trustOnlyVerifiedEmail` setting), system administrators are never
     * adopted by email (only through an existing link), and the user source of the HumHub user must allow linking by email.
     *
     * @param array $attributes normalized Keycloak user attributes
     * @param bool $isKeycloakTrusted `adoptExistingUsers` setting is ADOPT_USERS_TRUSTED
     * @param string|null $notAdoptedReason set to the reason if a HumHub user has the email but is not adopted
     */
    private function findAdoptionCandidate(array $attributes, bool $isKeycloakTrusted, ?string &$notAdoptedReason = null): ?User
    {
        // The email is missing if not verified on Keycloak and only verified emails are trusted
        if (empty($attributes['email'])) {
            return null;
        }
        $user = User::findOne(['email' => $attributes['email']]);
        if (
            $user === null
            || !$this->isAdoptable($user, $isKeycloakTrusted)
            || Auth::find()->where(['source' => Keycloak::DEFAULT_NAME, 'user_id' => $user->id])->exists()
        ) {
            return null;
        }

        if ($isKeycloakTrusted) {
            return $user;
        }
        if (!filter_var($attributes['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $notAdoptedReason = 'the email is not verified on Keycloak';
        } elseif ($user->isSystemAdmin()) {
            $notAdoptedReason = 'system administrators are adopted only if already linked to Keycloak';
        } elseif (!UserSourceService::getForUser($user)->getUserSource()->allowEmailAutoLink()) {
            $notAdoptedReason = 'the user source "' . $user->user_source . '" does not allow linking by email';
        }

        return $notAdoptedReason === null ? $user : null;
    }

    /**
     * Adopts a HumHub user of another user source (`adoptExistingUsers` setting): assigns it to this source, links it
     * to the Keycloak user (if not linked yet) and syncs it like the users of this source. All or nothing: if the
     * user cannot be synced, it keeps its user source. A username already taken by another user is not applied.
     * A deactivated user (adopted only if Keycloak is trusted) is enabled, as the Keycloak user is enabled.
     *
     * @param array $attributes normalized Keycloak user attributes (of an enabled Keycloak user)
     * @param string|null $reason set to the reason if the adoption failed
     * @return string SYNC_ADOPTED or SYNC_FAILED
     */
    private function adoptUser(User $user, array $attributes, ?string &$reason = null): string
    {
        if (
            isset($attributes['username'])
            && in_array('username', $this->getManagedAttributes(), true)
            && User::find()->where(['username' => $attributes['username']])->andWhere(['!=', 'id', $user->id])->exists()
        ) {
            Yii::warning('Users sync: the username ' . $attributes['username'] . ' is already used by another HumHub user, so the adopted user '
                . $user->username . ' (' . $user->id . ') keeps its username', 'auth-keycloak');
            unset($attributes['username']);
        }

        $previousSource = $user->user_source;
        // The rollback only restores the database: the side effects of the user update (event handlers of other modules,
        // search index, email or username update jobs of a queue not stored in the database) are not undone
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Without validation and events, as for an attribute not editable by the user
            $user->updateAttributes(['user_source' => $this->getId()]);
            if (
                !Auth::find()->where(['source' => Keycloak::DEFAULT_NAME, 'user_id' => $user->id])->exists()
                && !$this->addAuth($user, $attributes['id'])
            ) {
                $reason = 'could not link the adopted HumHub user ' . $user->username . ' (ID ' . $user->id . ') to the Keycloak user';
                $result = self::SYNC_FAILED;
            } else {
                $result = $this->syncUser($user, $attributes, true, $reason);
                if ($result === self::SYNC_FAILED || $reason !== null) {
                    $reason = 'could not adopt the HumHub user of the user source "' . $previousSource . '": ' . $reason;
                    $result = self::SYNC_FAILED;
                }
            }

            if ($result === self::SYNC_FAILED) {
                $transaction->rollBack();
                $user->refresh();
                return self::SYNC_FAILED;
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $message = 'Users sync: adopted the HumHub user ' . $user->username . ' (' . $user->id . ', previous user source: "' . $previousSource . '")'
            . ($result === self::SYNC_ENABLED ? ' and enabled it' : '');
        if ($user->isSystemAdmin()) {
            Yii::warning($message . '. This user is a system administrator.', 'auth-keycloak');
        } else {
            Yii::info($message, 'auth-keycloak');
        }

        return self::SYNC_ADOPTED;
    }

    /**
     * @param string|null $reason set to the reason if the user is skipped or the sync failed
     * @return string sync result (SYNC_* constant)
     */
    private function syncUser(User $user, array $attributes, bool $isEnabledOnKeycloak, ?string &$reason = null): string
    {
        // Users owned by another source (e.g. local users who also sign in with Keycloak) are not managed
        if ($user->user_source !== $this->getId()) {
            $reason = 'linked to the HumHub user ' . $user->username . ' (ID ' . $user->id . ') of the user source "' . $user->user_source . '"';
            return self::SYNC_SKIPPED;
        }
        if ($user->status === User::STATUS_SOFT_DELETED) {
            $reason = 'linked to the soft deleted HumHub user ' . $user->username . ' (ID ' . $user->id . ')';
            return self::SYNC_SKIPPED;
        }

        $service = new UserSourceService($user);
        $managedValuesBefore = $this->getManagedValues($user);
        $isUpdated = $service->updateUser($attributes);
        if (!$isUpdated) {
            $reason = 'could not update the HumHub user ' . $user->username . ' (ID ' . $user->id . '): '
                . $this->formatErrors(array_merge($user->getErrors(), $user->profile?->getErrors() ?? []));
            // Discard the invalid values, so that the status can be saved
            $user->refresh();
        }

        // Even if the attributes could not be updated (e.g. email already used), the status must be synced
        if (!$isEnabledOnKeycloak && $user->status === User::STATUS_ENABLED) {
            if ($service->deleteUser()) {
                return self::SYNC_DISABLED;
            }
            $reason = 'could not deactivate the HumHub user ' . $user->username . ' (ID ' . $user->id . ')';
            return self::SYNC_FAILED;
        }
        if ($isEnabledOnKeycloak && $user->status === User::STATUS_DEACTIVATED) {
            if ($this->enableUser($user)) {
                return self::SYNC_ENABLED;
            }
            $reason = 'could not enable the HumHub user ' . $user->username . ' (ID ' . $user->id . ')';
            return self::SYNC_FAILED;
        }
        if (!$isUpdated) {
            return self::SYNC_FAILED;
        }

        return $managedValuesBefore === $this->getManagedValues($user) ? self::SYNC_UNCHANGED : self::SYNC_UPDATED;
    }

    /**
     * @return int[] number of HumHub users of this source, by status (User::STATUS_* => count)
     */
    public function countUsersByStatus(): array
    {
        $counts = array_fill_keys([User::STATUS_ENABLED, User::STATUS_DEACTIVATED, User::STATUS_NEED_APPROVAL, User::STATUS_SOFT_DELETED], 0);
        $rows = User::find()
            ->select(['status', 'count' => 'COUNT(*)'])
            ->where(['user_source' => $this->getId()])
            ->groupBy('status')
            ->asArray()
            ->all();
        foreach ($rows as $row) {
            $counts[(int)$row['status']] = (int)$row['count'];
        }
        return $counts;
    }

    private function formatKeycloakUser(array $keycloakUser): string
    {
        return implode(' ', array_map(
            static fn($name) => ($keycloakUser[$name] ?? '') !== '' ? $keycloakUser[$name] : '-',
            ['id', 'username', 'firstName', 'lastName'],
        ));
    }

    /**
     * @param array $errors attribute => error messages (or model => attribute => error messages)
     */
    private function formatErrors(array $errors): string
    {
        $messages = [];
        array_walk_recursive($errors, static function ($message) use (&$messages) {
            $messages[] = $message;
        });
        return $messages !== [] ? implode(' ', array_unique($messages)) : 'unknown error';
    }

    private function getManagedValues(User $user): array
    {
        $values = [];
        foreach ($this->getManagedAttributes() as $attribute) {
            $values[$attribute] = $user->hasAttribute($attribute)
                ? $user->getAttribute($attribute)
                : $user->profile?->getAttribute($attribute);
        }
        return $values;
    }

    private function addAuth(User $user, string $keycloakUserId): bool
    {
        $auth = new Auth([
            'user_id' => $user->id,
            'source' => Keycloak::DEFAULT_NAME,
            'source_id' => $keycloakUserId,
        ]);
        if (!$auth->save()) {
            Yii::warning(
                'Users sync: could not link the user ID ' . $user->id . ' to the Keycloak user ID ' . $keycloakUserId
                . '. Errors: ' . VarDumper::dumpAsString($auth->getErrors()),
                'auth-keycloak',
            );
            return false;
        }
        return true;
    }

    private function getKeycloakModule(): ?Module
    {
        /** @var Module|null $module */
        $module = Yii::$app->getModule('auth-keycloak');
        return $module;
    }
}

<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.app](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\components;

use GuzzleHttp\Command\Exception\CommandClientException;
use GuzzleHttp\Command\Exception\CommandException;
use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\models\GroupKeycloak;
use humhub\modules\authKeycloak\Module;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\User;
use Keycloak\Admin\KeycloakClient;
use Keycloak\Admin\TokenStorages\RuntimeTokenStorage;
use Yii;
use yii\base\Component;

class KeycloakApi extends Component
{
    /**
     * Maximum users in the results
     */
    public const MAX_USERS_RESULT = 100;

    /**
     * Maximum number of times the users are listed, see {@see getAllUsers()}
     */
    public const MAX_USERS_LIST_PASSES = 3;

    /**
     * KeycloakClient methods: https://github.com/MohammadWaleed/keycloak-admin-client
     * @var KeycloakClient|null
     */
    public $api;
    /**
     * @var array
     */
    public $realm;

    /**
     * @param int $groupKeycloakId
     * @return bool
     */
    public function removeGroup($groupKeycloakId)
    {
        if (
            !$this->isConnected()
            || !$groupKeycloakId
        ) {
            return false;
        }
        $result = $this->api->removeGroup(['id' => $groupKeycloakId]);

        return !$this->hasError($result, 'Keycloak groups to HumHub groups users synchronization with the API failed');
    }

    /**
     * @return bool
     */
    public function isConnected()
    {
        return $this->api !== null && $this->realm !== null;
    }

    /**
     * @param int $groupId
     * @return bool
     */
    public function renameGroup($groupId)
    {
        if (
            !$this->isConnected()
            || ($groupKeycloak = GroupKeycloak::getKeycloakGroup($groupId)) === null
        ) {
            return false;
        }

        $result = $this->api->updateGroup([
            'id' => $groupKeycloak->keycloak_id,
            'name' => $groupKeycloak->name,
        ]);

        return !$this->hasError($result, 'Error removing group ID ' . $groupKeycloak->id);
    }

    /**
     * @param int $userId
     * @return string[] Keycloak group IDs (UUIDs)
     */
    public function getUserGroups($userId)
    {
        if (
            !$this->isConnected()
            || ($userAuth = $this->getUserAuth($userId)) === null
        ) {
            return [];
        }
        $result = $this->api->getUserGroups(['id' => $userAuth->source_id]);
        if ($this->hasError($result, 'Error retrieving user\'s groups from Keycloak (user ID: ' . $userId . ')')) {
            return [];
        }
        if (!is_array($result)) {
            Yii::error('Error retrieving user\'s groups from Keycloak for user ID: ' . $userId . ' (result is not an array)', 'auth-keycloak');
            return [];
        }
        return array_map(static fn($group) => (string)$group['id'], $result);
    }

    /**
     * User Keycloak authentification
     * @param $userId
     * @return Auth|null
     */
    public function getUserAuth($userId)
    {
        return Auth::find()
            ->where(['source' => Keycloak::DEFAULT_NAME, 'user_id' => $userId])
            ->orderBy(['id' => SORT_DESC]) // get the latest if it has multiple
            ->one();
    }

    /**
     * @param string $email
     * @param bool $updateAuthTable
     * @return string|null
     */
    public function getUserId(string $email)
    {
        if (!$this->isConnected()) {
            return null;
        }
        try {
            // The API client validates query parameters as strings
            $result = $this->api->getUsers(['email' => $email, 'exact' => 'true']);
        } catch (CommandClientException|CommandException $e) {
            Yii::error('Error retrieving user from Keycloak (email: ' . $email . '): ' . $e->getMessage(), 'auth-keycloak');
            return null;
        }
        if ($this->hasError($result, 'Error retrieving user from Keycloak (email: ' . $email . ')')) {
            return null;
        }
        if (!is_array($result)) {
            Yii::error('Error retrieving user from Keycloak for user email: ' . $email . ' (result is not an array)', 'auth-keycloak');
            return null;
        }
        return $result[0]['id'] ?? null;
    }

    /**
     * @param int $userId
     * @param int $groupId
     * @param bool $createGroupIfNotExists
     * @return bool
     */
    public function addUserToGroup($userId, $groupId, bool $createGroupIfNotExists = true)
    {
        if (
            !$this->isConnected()
            || ($userAuth = $this->getUserAuth($userId)) === null
        ) {
            return false;
        }

        // Check if the HumHub group is linked to a Keycloak group
        if (($groupKeycloak = GroupKeycloak::getKeycloakGroup($groupId)) === null) {
            // Try linking or creating it
            if ($createGroupIfNotExists && $this->linkSameGroupNameOrCreateGroup($groupId)) {
                $groupKeycloak = GroupKeycloak::getKeycloakGroup($groupId);
            } else {
                return false;
            }
        }

        $result = $this->api->addUserToGroup([
            'id' => $userAuth->source_id,
            'groupId' => $groupKeycloak->keycloak_id,
        ]);
        return !$this->hasError($result, 'Error adding group ID ' . $groupId . ' to user ID ' . $userId);
    }

    /**
     * Links the HumHub group to the Keycloak group having the same name and the same parent, or creates it on Keycloak.
     * The Keycloak group of a HumHub subgroup is a subgroup of the Keycloak group linked to its parent group
     * (which is linked or created first).
     *
     * @param int $groupId
     * @return bool
     */
    public function linkSameGroupNameOrCreateGroup($groupId)
    {
        if (
            !$this->isConnected()
            || ($groupKeycloak = GroupKeycloak::findOne($groupId)) === null
            || ($keycloakGroups = $this->getGroupsTree()) === null
        ) {
            return false;
        }

        // Already linked
        if ($groupKeycloak->keycloak_id !== null && isset($keycloakGroups[$groupKeycloak->keycloak_id])) {
            return true;
        }

        $parentKeycloakId = null;
        if ($groupKeycloak->parent_group_id !== null) {
            if (!$this->linkSameGroupNameOrCreateGroup($groupKeycloak->parent_group_id)) {
                return false;
            }
            $parentKeycloakId = GroupKeycloak::getKeycloakGroup($groupKeycloak->parent_group_id)?->keycloak_id;
            // The parent group may have been created on Keycloak
            if ($parentKeycloakId === null || ($keycloakGroups = $this->getGroupsTree()) === null) {
                return false;
            }
        }

        // Check if a group with the same name and parent already exists, otherwise create it
        $keycloakGroupId = $this->findGroupId($keycloakGroups, $groupKeycloak->name, $parentKeycloakId);
        if ($keycloakGroupId === null) {
            $result = $parentKeycloakId === null
                ? $this->api->createGroup(['name' => $groupKeycloak->name])
                : $this->api->createChildGroup(['groupId' => $parentKeycloakId, 'name' => $groupKeycloak->name]);
            if ($this->hasError($result, 'Error creating group ID ' . $groupKeycloak->id)) {
                return false;
            }
            // Keycloak returns the created group only for subgroups
            $keycloakGroupId = $result['id'] ?? $this->findGroupId($this->getGroupsTree() ?? [], $groupKeycloak->name, $parentKeycloakId);
            if ($keycloakGroupId === null) {
                return false;
            }
        }

        // A Keycloak group can be linked to only one HumHub group (HumHub group names are not unique)
        if (GroupKeycloak::find()->where(['keycloak_id' => $keycloakGroupId])->andWhere(['!=', 'id', $groupKeycloak->id])->exists()) {
            Yii::warning('Could not link the group ID ' . $groupKeycloak->id . ' to the Keycloak group ' . $keycloakGroupId . ', already linked to another group', 'auth-keycloak');
            return false;
        }

        // No save(), as the group events would sync the group to Keycloak again
        $groupKeycloak->updateAttributes(['keycloak_id' => $keycloakGroupId]);
        return true;
    }

    /**
     * Moves the Keycloak groups so that their parent matches the HumHub parent group, for this group and its current
     * and former subgroups (the HumHub admin updates the parent groups without triggering any event)
     *
     * @param int $groupId
     * @return bool
     */
    public function syncGroupHierarchy($groupId): bool
    {
        if (
            !$this->isConnected()
            || ($groupKeycloak = GroupKeycloak::getKeycloakGroup($groupId)) === null
            || ($keycloakGroups = $this->getGroupsTree()) === null
        ) {
            return false;
        }

        $keycloakGroupIds = array_merge(
            [$groupKeycloak->keycloak_id],
            array_keys(array_filter($keycloakGroups, static fn($keycloakGroup) => $keycloakGroup['parentId'] === $groupKeycloak->keycloak_id)),
        );
        $groups = GroupKeycloak::find()
            ->where(['keycloak_id' => $keycloakGroupIds])
            ->orWhere(['and', ['parent_group_id' => $groupKeycloak->id], ['not', ['keycloak_id' => null]]])
            ->all();
        // Top level groups first: a group can become the parent of other groups only once on the top level
        usort($groups, static fn($a, $b) => ($a->parent_group_id !== null) <=> ($b->parent_group_id !== null));

        $success = true;
        foreach ($groups as $group) {
            $success = $this->moveGroup($group, $keycloakGroups) && $success;
        }
        return $success;
    }

    /**
     * Before removing a Keycloak group (which removes its subgroups on Keycloak), moves its subgroups linked
     * to HumHub groups to the top level, as HumHub does with the subgroups of a deleted group
     *
     * @param string|null $keycloakGroupId
     * @return void
     */
    public function moveSubgroupsToTopLevel(?string $keycloakGroupId): void
    {
        if (
            !$this->isConnected()
            || !$keycloakGroupId
            || ($keycloakGroups = $this->getGroupsTree()) === null
        ) {
            return;
        }

        $subgroupIds = array_keys(array_filter($keycloakGroups, static fn($keycloakGroup) => $keycloakGroup['parentId'] === $keycloakGroupId));
        foreach (GroupKeycloak::find()->where(['keycloak_id' => $subgroupIds])->all() as $group) {
            $this->moveGroup($group, $keycloakGroups);
        }
    }

    /**
     * Moves the Keycloak group linked to this HumHub group under the Keycloak group linked to its parent group,
     * or to the top level if it has no parent group
     *
     * @param GroupKeycloak $group
     * @param array $keycloakGroups {@see getGroupsTree()}
     * @return bool
     */
    private function moveGroup(GroupKeycloak $group, array $keycloakGroups): bool
    {
        // Groups deeper than the subgroups level are not synced
        if (($keycloakGroup = $keycloakGroups[$group->keycloak_id] ?? null) === null) {
            return false;
        }

        $parentKeycloakId = null;
        if ($group->parent_group_id !== null) {
            $parentKeycloakId = GroupKeycloak::getKeycloakGroup($group->parent_group_id)?->keycloak_id;
            if ($parentKeycloakId === null || !isset($keycloakGroups[$parentKeycloakId])) {
                return false;
            }
        }
        if ($keycloakGroup['parentId'] === $parentKeycloakId) {
            return true;
        }

        // Posting an existing group moves it
        $params = ['id' => $group->keycloak_id, 'name' => $keycloakGroup['name']];
        $result = $parentKeycloakId === null
            ? $this->api->createGroup($params)
            : $this->api->createChildGroup(['groupId' => $parentKeycloakId] + $params);
        return !$this->hasError($result, 'Error moving the Keycloak group of the group ID ' . $group->id);
    }

    /**
     * @param array $keycloakGroups {@see getGroupsTree()}
     * @param string $name
     * @param string|null $parentKeycloakId
     * @return string|null
     */
    private function findGroupId(array $keycloakGroups, string $name, ?string $parentKeycloakId): ?string
    {
        foreach ($keycloakGroups as $keycloakGroupId => $keycloakGroup) {
            if ($keycloakGroup['name'] === $name && $keycloakGroup['parentId'] === $parentKeycloakId) {
                return (string)$keycloakGroupId;
            }
        }
        return null;
    }

    /**
     * Returns the Keycloak groups synced with HumHub: the top level groups and their subgroups
     * (HumHub supports only one level of subgroups, deeper Keycloak groups are ignored)
     *
     * @return array|null Keycloak group ID => ['name' => string, 'parentId' => string|null], parent groups first;
     * null if an error occurred (never a partial list)
     */
    public function getGroupsTree(): ?array
    {
        if (!$this->isConnected()) {
            return null;
        }

        $topGroups = $this->getAllPages(
            fn($first, $max) => $this->api->getGroups(['first' => $first, 'max' => $max]),
            'Error retrieving groups from Keycloak',
        );
        if ($topGroups === null) {
            return null;
        }

        $groups = [];
        foreach ($topGroups as $topGroup) {
            $groups[(string)$topGroup['id']] = ['name' => (string)$topGroup['name'], 'parentId' => null];
        }
        foreach ($topGroups as $topGroup) {
            // Keycloak < 23 returns the subgroups with their parent, newer versions only their number
            $subgroups = $topGroup['subGroups'] ?? [];
            if ($subgroups === [] && !empty($topGroup['subGroupCount'])) {
                $subgroups = $this->getAllPages(
                    fn($first, $max) => $this->api->getGroupChildren(['id' => $topGroup['id'], 'first' => $first, 'max' => $max]),
                    'Error retrieving subgroups of the Keycloak group ' . $topGroup['id'],
                );
                if ($subgroups === null) {
                    return null;
                }
            }
            foreach ($subgroups as $subgroup) {
                $groups[(string)$subgroup['id']] = ['name' => (string)$subgroup['name'], 'parentId' => (string)$topGroup['id']];
            }
        }

        return $groups;
    }

    /**
     * @param string $keycloakGroupId
     * @return bool|null null if an error occurred
     */
    public function groupExists(string $keycloakGroupId): ?bool
    {
        if (!$this->isConnected()) {
            return null;
        }
        try {
            $result = $this->api->getGroup(['id' => $keycloakGroupId]);
        } catch (CommandClientException|CommandException $e) {
            Yii::error('Error retrieving the Keycloak group ' . $keycloakGroupId . ': ' . $e->getMessage(), 'auth-keycloak');
            return null;
        }
        if (($result['error'] ?? null) === 'Could not find group by id') {
            return false;
        }
        if (
            $this->hasError($result, 'Error retrieving the Keycloak group ' . $keycloakGroupId)
            || !is_array($result)
            || empty($result['id'])
        ) {
            return null;
        }
        return true;
    }

    /**
     * @param callable $request function (int $first, int $max): array
     * @param string $errorMessage
     * @param int|null $total number of results: if set, pages are requested up to it, even after a page having
     * less than the maximum results (with a user federation, Keycloak may return such pages before the last one)
     * @param callable|null $onPage called after each page: function (int $resultsCount)
     * @param int $pageSize
     * @param bool $untilEmptyPage if no total is given, request pages until an empty one (instead of until a page having
     * less than the maximum results), as Keycloak may return such pages before the last one with a user federation
     * @return array|null all the results, null if an error occurred
     */
    private function getAllPages(callable $request, string $errorMessage, ?int $total = null, ?callable $onPage = null, int $pageSize = self::MAX_USERS_RESULT, bool $untilEmptyPage = false): ?array
    {
        $first = 0;
        $results = [];
        do {
            try {
                $page = $request($first, $pageSize);
            } catch (CommandClientException|CommandException $e) {
                Yii::error($errorMessage . ': ' . $e->getMessage(), 'auth-keycloak');
                return null;
            }
            if ($this->hasError($page, $errorMessage) || !is_array($page)) {
                return null;
            }
            array_push($results, ...array_values($page));
            $first += $pageSize;
            if ($onPage !== null) {
                $onPage(count($results));
            }
        } while ($total !== null ? $first < $total : ($untilEmptyPage ? $page !== [] : count($page) === $pageSize));

        return $results;
    }

    /**
     * @param int $userId
     * @param int $groupId
     * @return bool
     */
    public function deleteUserFromGroup($userId, $groupId)
    {
        if (
            !$this->isConnected()
            || ($userAuth = $this->getUserAuth($userId)) === null
            || ($groupKeycloak = GroupKeycloak::getKeycloakGroup($groupId)) === null
        ) {
            return false;
        }
        $result = $this->api->deleteUserFromGroup([
            'id' => $userAuth->source_id,
            'groupId' => $groupKeycloak->keycloak_id,
        ]);
        return !$this->hasError($result, 'Error deleting group ID ' . $groupId . ' to user ID ' . $userId);
    }

    /**
     * @inerhitdoc
     */
    public function init()
    {
        parent::init();

        $config = new ConfigureForm();
        if (
            !$config->enabled
            || !$config->hasApiParams()
            || !Yii::$app->authClientCollection->hasClient(Keycloak::DEFAULT_NAME)
        ) {
            return;
        }

        // require_once is idempotent, so no class_exists() sentinel: a sentinel can be satisfied by
        // another module's (or core's) copy of the library and leave this autoloader unregistered.
        require_once Yii::getAlias('@auth-keycloak/vendor/autoload.php');

        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');

        $tokenStorage = new RuntimeTokenStorage();
        $this->api = KeycloakClient::factory([
            'realm' => $config->realm,
            'client_id' => $config->clientId,
            'client_secret' => $config->clientSecret,
            'grant_type' => $config->isApiServiceAccount() ? 'client_credentials' : 'password',
            'username' => $config->apiUsername,
            'password' => $config->apiPassword,
            'baseUri' => $config->baseUrl . '/',
            'scope' => 'openid email',
            'verify' => $module->apiVerifySsl,
            'timeout' => Yii::$app->request->isConsoleRequest ? $module->apiSyncTimeout : $module->apiTimeout,
            'connect_timeout' => $module->apiConnectTimeout,
            'token_storage' => $tokenStorage,
            // Gets the access token with the timeouts above, before the library's own token middleware
            'middlewares' => [new RefreshTokenWithTimeout($tokenStorage)],
        ]);
        if ($config->realm !== 'master') {
            $this->api->setRealmName($config->realm);
        }

        try {
            $realm = $this->api->getRealm();
        } catch (CommandClientException|CommandException $e) {
            Yii::error('Error trying to connect to Keycloak API: ' . $e, 'auth-keycloak');
            return;
        }

        // HTTP errors (e.g. 401 Unauthorized) are not thrown by the library, but returned as the response
        if ($this->hasError($realm, 'Error trying to connect to Keycloak API')) {
            return;
        }
        if (!isset($realm['realm'])) {
            // E.g. an HTML page of a reverse proxy
            Yii::error('Error trying to connect to Keycloak API: unexpected response: ' . mb_substr(json_encode($realm), 0, 500), 'auth-keycloak');
            return;
        }
        $this->realm = $realm;
    }

    /**
     * @param User $user
     * @return bool|null
     */
    public function updateUserUsername(User $user)
    {
        if (
            !$this->isConnected()
            || ($userAuth = $this->getUserAuth($user->id)) === null
        ) {
            return null;
        }

        // Do not update username if Keycloak username is the email
        if (
            isset($this->realm['registrationEmailAsUsername'])
            && $this->realm['registrationEmailAsUsername']
        ) {
            return null;
        }

        // Update username
        $result = $this->api->updateUser([
            'id' => $userAuth->source_id,
            'username' => $user->username,
        ]);
        return !$this->hasError($result, 'Error saving user\'s new username on Keycloak for user ID: ' . $user->id);
    }

    /**
     * @param User $user
     * @return bool|null
     */
    public function updateUserEmail(User $user)
    {
        if (
            !$this->isConnected()
            || ($userAuth = $this->getUserAuth($user->id)) === null
        ) {
            return null;
        }
        // Update email
        $result = $this->api->updateUser(array_merge(
            [
                'id' => $userAuth->source_id,
                'email' => $user->email,
            ],
            ((isset($this->realm['registrationEmailAsUsername']) && $this->realm['registrationEmailAsUsername']) ? ['username' => $user->email] : []),
        ));
        return !$this->hasError($result, 'Error saving user\'s new email on Keycloak for user ID: ' . $user->id);
    }

    /**
     * @param User $user
     * @param string $newPassword
     * @return bool|string string is the error message if present
     */
    public function resetUserPassword(User $user, string $newPassword)
    {
        if (
            !$this->isConnected()
            || ($userAuth = $this->getUserAuth($user->id)) === null
        ) {
            return null;
        }
        // Update password
        $result = $this->api->resetUserPassword([
            'id' => $userAuth->source_id,
            'value' => $newPassword,
        ]);
        return $this->hasError($result, 'Error saving user\'s new password on Keycloak for user ID: ' . $user->id, true, 'Invalid password')
            ? $result['error_description'] ?? false
            : true;
    }

    /**
     * Disables the user's account on Keycloak and removes all their Keycloak sessions
     * @param string $keycloakUserId
     * @return bool
     */
    public function disableUser(string $keycloakUserId)
    {
        if (!$this->isConnected()) {
            return false;
        }

        $keycloakUser = $this->api->getUser(['id' => $keycloakUserId]);
        if ($this->hasError($keycloakUser, 'Error retrieving user from Keycloak (Keycloak user ID: ' . $keycloakUserId . ')')) {
            return false;
        }

        // Never disable the API admin user (Keycloak usernames are lowercase), as it would break all the API features
        $apiUsername = mb_strtolower(trim((string)(new ConfigureForm())->apiUsername));
        if (mb_strtolower((string)($keycloakUser['username'] ?? '')) === $apiUsername) {
            Yii::warning('The Keycloak API admin user (Keycloak user ID: ' . $keycloakUserId . ') has not been disabled on Keycloak', 'auth-keycloak');
            return false;
        }

        $result = $this->api->updateUser([
            'id' => $keycloakUserId,
            'enabled' => false,
        ]);
        if ($this->hasError($result, 'Error disabling user on Keycloak (Keycloak user ID: ' . $keycloakUserId . ')')) {
            return false;
        }

        $result = $this->api->logoutUser(['id' => $keycloakUserId]);
        return !$this->hasError($result, 'Error removing user\'s sessions on Keycloak (Keycloak user ID: ' . $keycloakUserId . ')');
    }

    /**
     * @param int $userId
     * @return bool|null
     */
    public function revokeUserSession($userId)
    {
        if (
            !$this->isConnected()
            || ($userAuth = $this->getUserAuth($userId)) === null
        ) {
            return false;
        }

        $result = $this->api->getUserSessions(['id' => $userAuth->source_id]);
        if ($this->hasError($result, 'Error retrieving user\'s sessions from Keycloak (user ID: ' . $userId . ')')) {
            return false;
        }
        if (!is_array($result)) {
            Yii::error('Error retrieving user\'s sessions from Keycloak for user ID: ' . $userId . ' (result is not an array)', 'auth-keycloak');
            return false;
        }

        foreach ($result as $session) {
            if (isset($session['id'])) {
                // revoke session
                $result = $this->api->revokeUserSession([
                    'session' => $session['id'],
                ]);
                if ($this->hasError($result, 'Error revoking user\'s session on Keycloak for user ID: ' . $userId)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param string $keycloakUserId
     * @return array|null Keycloak user representation, null if not found or an error occurred (logged)
     */
    public function getUser(string $keycloakUserId): ?array
    {
        if (!$this->isConnected()) {
            return null;
        }
        try {
            $result = $this->api->getUser(['id' => $keycloakUserId]);
        } catch (CommandClientException|CommandException $e) {
            if ($e->getResponse()?->getStatusCode() === 404) {
                return null;
            }
            Yii::error('Error retrieving user from Keycloak (ID: ' . $keycloakUserId . '): ' . $e->getMessage(), 'auth-keycloak');
            return null;
        }
        if (
            $this->hasError($result, 'Error retrieving user from Keycloak (ID: ' . $keycloakUserId . ')')
            || !is_array($result)
            || empty($result['id'])
        ) {
            return null;
        }
        return $result;
    }

    /**
     * Finds a Keycloak user by its exact username or email
     * @param string $usernameOrEmail
     * @return array|null Keycloak user representation, null if not found or an error occurred
     */
    public function findUser(string $usernameOrEmail): ?array
    {
        if (!$this->isConnected()) {
            return null;
        }
        foreach (['username', 'email'] as $param) {
            try {
                $result = $this->api->getUsers([$param => $usernameOrEmail, 'exact' => 'true']);
            } catch (CommandClientException|CommandException $e) {
                Yii::error('Error retrieving user from Keycloak (' . $param . ': ' . $usernameOrEmail . '): ' . $e->getMessage(), 'auth-keycloak');
                return null;
            }
            if (
                $this->hasError($result, 'Error retrieving user from Keycloak (' . $param . ': ' . $usernameOrEmail . ')')
                || !is_array($result)
            ) {
                return null;
            }
            if (count($result) === 1) {
                return reset($result);
            }
        }
        return null;
    }

    /**
     * Returns all users of the realm (Keycloak user representations).
     *
     * With a user federation (e.g. LDAP), Keycloak may return pages having less than the maximum results before
     * the last one, and a different list on each request. So the pages are requested up to the number of users
     * of the realm, and the list is requested again (merging the lists) until it is complete,
     * at most {@see MAX_USERS_LIST_PASSES} times.
     *
     * @param bool|null $isComplete set to false if the list is still incomplete after all passes
     * (or the number of users could not be retrieved)
     * @param callable|null $onProgress called after each page: function (int $listedCount, ?int $totalCount, int $pass)
     * @return array|null null if an error occurred
     */
    public function getAllUsers(?bool &$isComplete = null, ?callable $onProgress = null): ?array
    {
        $isComplete = false;
        if (!$this->isConnected()) {
            return null;
        }

        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');
        $pageSize = max(1, (int)$module->usersListPageSize);
        $count = $this->getUserCount();
        $users = [];
        for ($pass = 1; $pass <= self::MAX_USERS_LIST_PASSES; $pass++) {
            if ($onProgress !== null) {
                $onProgress(count($users), $count, $pass);
            }
            // Return null (not a partial list) so that callers do not consider missing users as removed from Keycloak
            $pageUsers = $this->getAllPages(
                fn($first, $max) => $this->api->getUsers(['first' => $first, 'max' => $max]),
                'Error retrieving users from Keycloak',
                $count,
                $onProgress !== null ? static fn($listedCount) => $onProgress($listedCount, $count, $pass) : null,
                $pageSize,
            );
            if ($pageUsers === null) {
                return null;
            }
            // Indexed by ID, as a user may be returned in several pages or passes
            foreach ($pageUsers as $user) {
                if (!empty($user['id'])) {
                    $users[$user['id']] ??= $user;
                }
            }

            if ($count === null) {
                break;
            }
            if (count($users) >= $count) {
                $isComplete = true;
                break;
            }
            Yii::warning('Retrieving users from Keycloak: ' . count($users) . ' users listed, but the realm has ' . $count . ' users (pass ' . $pass . ' of ' . self::MAX_USERS_LIST_PASSES . ')', 'auth-keycloak');
        }

        return array_values($users);
    }

    /**
     * @return int|null number of users of the realm, null if an error occurred
     */
    public function getUserCount(): ?int
    {
        if (!$this->isConnected()) {
            return null;
        }
        try {
            $result = $this->api->getUserCount();
        } catch (CommandClientException|CommandException $e) {
            Yii::error('Error retrieving the number of users from Keycloak: ' . $e->getMessage(), 'auth-keycloak');
            return null;
        }
        if ($this->hasError($result, 'Error retrieving the number of users from Keycloak')) {
            return null;
        }
        // The response body is a number
        $count = is_array($result) ? ($result['content'] ?? null) : $result;
        return is_numeric($count) ? (int)$count : null;
    }

    /**
     * @param $groupId
     * @return array|null
     */
    public function getGroupMemberIds($groupId)
    {
        if (!$this->isConnected()) {
            return null;
        }
        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');
        // Return null (not a partial list) so that callers do not consider missing members as removed from the group
        $members = $this->getAllPages(
            fn($first, $max) => $this->api->getGroupMembers(['id' => $groupId, 'first' => $first, 'max' => $max]),
            'Error retrieving members of the Keycloak group ID ' . $groupId,
            null,
            null,
            max(1, (int)$module->usersListPageSize),
            true,
        );
        if ($members === null) {
            return null;
        }

        return array_values(array_unique(array_filter(array_map(static fn($member) => $member['id'] ?? null, $members))));
    }

    /**
     * @param $response
     * @param string|null $message
     * @param bool $addErrorToLog
     * @param string|null $errorToIgnoreForLog by default, 'User not found' as we do not check if the user exists to avoid an extra API request
     * @return bool
     */
    protected function hasError($response, ?string $message = null, bool $addErrorToLog = true, ?string $errorToIgnoreForLog = 'User not found')
    {
        $errors = [];
        if (!empty($response['error'])) {
            $errors[] = $response['error'];
        }
        if (!empty($response['errorMessage'])) {
            $errors[] = $response['errorMessage'];
        }
        if (!empty($response['error_description'])) {
            $errors[] = $response['error_description'];
        }
        if (count($errors) > 0) {
            $errorMessage = implode(' | ', $errors);
            if ($addErrorToLog && !str_contains($errorMessage, (string) $errorToIgnoreForLog)) {
                Yii::error(
                    'Auth Keycloak module error' . ($message ? ': ' . $message : '') . '. Error message: ' . $errorMessage,
                    'auth-keycloak',
                );
            }
            return true;
        }
        return false;
    }
}

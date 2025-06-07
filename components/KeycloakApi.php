<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
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
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;

class KeycloakApi extends Component
{
    /**
     * Maximum users in the results
     */
    public const MAX_USERS_RESULT = 100;
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
     * @return int[]
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
        return array_map(static function ($group) {
            return (int)$group['id'];
        }, $result);
    }

    /**
     * User Keycloak authentification
     * @param $userId
     * @return Auth|null
     */
    public function getUserAuth($userId)
    {
        $auth = Auth::find()
            ->where(['source' => Keycloak::DEFAULT_NAME, 'user_id' => $userId])
            ->orderBy(['id' => SORT_DESC]) // get the latest if it has multiple
            ->one();

        if ($auth === null) {
            $user = User::findOne(['id' => $userId, 'auth_mode' => Keycloak::DEFAULT_NAME]);
            if ($user !== null) {
                $keycloakUserId = $this->getUserId($user->email);
                if ($keycloakUserId !== null) {
                    $auth = Auth::findOne(['source' => Keycloak::DEFAULT_NAME, 'source_id' => $keycloakUserId]);
                }
            }
        }

        return $auth;
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
        $result = $this->api->getUsers(['email' => $email]);
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

        // Check if Keycloak group exists
        if (($groupKeycloak = GroupKeycloak::findOne($groupId)) === null) {
            // Try creating it
            if ($createGroupIfNotExists && $this->linkSameGroupNameOrCreateGroup($groupId)) {
                $groupKeycloak = GroupKeycloak::findOne($groupId);
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
     * @param int $groupId
     * @return bool
     */
    public function linkSameGroupNameOrCreateGroup($groupId)
    {
        if (
            !$this->isConnected()
            || ($groupKeycloak = GroupKeycloak::findOne($groupId)) === null
        ) {
            return false;
        }

        // Check if group with same name already exists
        if (($keycloakGroupId = $this->getGroupIdFromName($groupKeycloak->name)) !== null) {
            $groupKeycloak->keycloak_id = $keycloakGroupId;
            return $groupKeycloak->save();
        }

        // Try creating group
        $result = $this->api->createGroup(['name' => $groupKeycloak->name]);

        if ($this->hasError($result, 'Error creating group ID ' . $groupKeycloak->id)) {
            return false;
        }

        // Get group ID
        $keycloakGroupId = $this->getGroupIdFromName($groupKeycloak->name);

        $groupKeycloak->keycloak_id = $keycloakGroupId;
        return $groupKeycloak->save();
    }

    /**
     * @param string $groupName
     * @return string|null
     */
    public function getGroupIdFromName(string $groupName)
    {
        if (!$this->isConnected()) {
            return null;
        }
        return array_search($groupName, $this->getGroupsNamesById(), true) ?: null;
    }

    /**
     * @return array|null id => name
     */
    public function getGroupsNamesById()
    {
        if (!$this->isConnected()) {
            return null;
        }
        $result = $this->api->getGroups();
        if ($this->hasError($result, 'Error retrieving groups')) {
            return null;
        }
        if (!is_array($result)) {
            Yii::error('Error retrieving groups from Keycloak (result is not an array)', 'auth-keycloak');
            return null;
        }
        return ArrayHelper::map($result, 'id', 'name');
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

        if (!class_exists('Keycloak\Admin\KeycloakClient')) {
            require_once Yii::getAlias('@auth-keycloak/vendor/autoload.php');
        }

        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');

        $this->api = KeycloakClient::factory([
            'realm' => $config->realm,
            'client_id' => $config->clientId,
            'client_secret' => $config->clientSecret,
            'username' => $config->apiUsername,
            'password' => $config->apiPassword,
            'baseUri' => $config->baseUrl . '/',
            'scope' => 'openid',
            'verify' => $module->apiVerifySsl,
        ]);
        if ($config->realm !== 'master') {
            $this->api->setRealmName($config->realm);
        }

        try {
            $this->realm = $this->api->getRealm();
        } catch (CommandClientException|CommandException $e) {
            Yii::error('Error trying to connect to Keycloak API: ' . $e, 'auth-keycloak');
            return;
        }
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
        return $this->hasError($result, 'Error saving user\'s new password on Keycloak for user ID: ' . $user->id, true, 'Invalid password') ?
            $result['error_description'] ?? false :
            true;
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
     * @param $groupId
     * @return array|null
     */
    public function getGroupMemberIds($groupId)
    {
        if (!$this->isConnected()) {
            return null;
        }
        $first = 0;
        $memberIds = [];
        while (!isset($currentMembers) || count($currentMembers) === self::MAX_USERS_RESULT) {
            $currentMembers = $this->api->getGroupMembers([
                'id' => $groupId,
                'first' => $first,
                'max' => self::MAX_USERS_RESULT,
            ]);
            $currentMemberIds = array_map(static function ($member) {
                return $member['id'] ?? null;
            }, $currentMembers);
            $currentMemberIds = array_filter($currentMemberIds);
            $memberIds = array_merge($memberIds, $currentMemberIds);
            $first += self::MAX_USERS_RESULT;
        }

        return $memberIds;
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
            if ($addErrorToLog && strpos($errorMessage, $errorToIgnoreForLog) === false) {
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

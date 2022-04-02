<?php
/**
 * * Keycloak Sign-In
 * @link https://github.com/cuzy-app/humhub-modules-auth-keycloak
 * @license https://github.com/cuzy-app/humhub-modules-auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\components;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\models\GroupKeycloak;
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

        if (!empty($result['errorMessage'])) {
            Yii::error('Auth Keycloak module: Keycloak groups to Humhub groups users synchronization with the API failed...', 'auth-keycloak');
            return false;
        }
        return true;
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

        if (!empty($result['errorMessage'])) {
            Yii::error('Error removing group ID ' . $groupKeycloak->id . ' on Keycloak: ' . $result['errorMessage'], 'auth-keycloak');
            return false;
        }
        return true;
    }

    /**
     * @param int $userId
     * @return int[]
     */
    public function getUserGroups($userId)
    {
        if (
            !$this->isConnected()
            || ($userAuth = static::getUserAuth($userId)) === null
        ) {
            return [];
        }
        $result = $this->api->getUserGroups(['id' => $userAuth->source_id]);
        if (
            !empty($result['errorMessage'])
            || !is_array($result)
        ) {
            Yii::error('Error retrieving users groups from Keycloak (user ID: ' . $userId . '): ' . ($result['errorMessage'] ?? 'result is not an array'), 'auth-keycloak');
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
    public static function getUserAuth($userId)
    {
        return Auth::find()
            ->where(['source' => Keycloak::DEFAULT_NAME, 'user_id' => $userId])
            ->orderBy(['id' => SORT_DESC]) // get the latest if it has multiple
            ->one();
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
            || ($userAuth = static::getUserAuth($userId)) === null
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
            'groupId' => $groupKeycloak->keycloak_id
        ]);
        if (!empty($result['errorMessage'])) {
            Yii::error('Error adding group ID ' . $groupId . ' to user ID ' . $userId . ' on Keycloak: ' . $result['errorMessage'], 'auth-keycloak');
            return false;
        }
        return true;
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

        if (!empty($result['errorMessage'])) {
            Yii::error('Error creating group ID ' . $groupKeycloak->id . ' on Keycloak: ' . $result['errorMessage'], 'auth-keycloak');
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
        if (
            !empty($result['errorMessage'])
            || !is_array($result)
        ) {
            Yii::error('Error retrieving groups from Keycloak: ' . ($result['errorMessage'] ?? 'result is not an array'), 'auth-keycloak');
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
            || ($userAuth = static::getUserAuth($userId)) === null
            || ($groupKeycloak = GroupKeycloak::getKeycloakGroup($groupId)) === null
        ) {
            return false;
        }
        $result = $this->api->deleteUserFromGroup([
            'id' => $userAuth->source_id,
            'groupId' => $groupKeycloak->keycloak_id
        ]);
        if (!empty($result['errorMessage'])) {
            Yii::error('Error deleting group ID ' . $groupId . ' to user ID ' . $userId . ' on Keycloak: ' . $result['errorMessage'], 'auth-keycloak');
            return false;
        }
        return true;
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
            || !Yii::$app->authClientCollection->hasClient('Keycloak')
        ) {
            return;
        }

        if (!class_exists('Keycloak\Admin\KeycloakClient')) {
            require Yii::getAlias('@auth-keycloak/vendor/autoload.php');
        }

        $this->api = KeycloakClient::factory([
            'realm' => 'master', // The admin user must be in master realm
            'username' => $config->apiUsername,
            'password' => $config->apiPassword,
            'baseUri' => $config->baseUrl . '/',
        ]);
        if ($config->realm !== 'master') {
            $this->api->setRealmName($config->realm);
        }

        $this->realm = $this->api->getRealm();
    }

    /**
     * @param User $user
     * @return bool|null
     */
    public function updateUserEmail(User $user)
    {
        if (
            !$this->isConnected()
            || ($userAuth = static::getUserAuth($user->id)) === null
        ) {
            return null;
        }
        // Update email
        $result = $this->api->updateUser(array_merge(
            [
                'id' => $userAuth->source_id,
                'email' => $user->email,
            ],
            ((isset($this->realm['registrationEmailAsUsername']) && $this->realm['registrationEmailAsUsername']) ? ['username' => $user->email] : [])
        ));
        if (!empty($result['errorMessage'])) {
            Yii::error('Error saving user\'s new email on Keycloak (user ID: ' . $user->id . '): ' . $result['errorMessage'], 'auth-keycloak');
            return false;
        }
        return true;
    }

    /**
     * @param int $userId
     * @return bool|null
     */
    public function revokeUserSession($userId)
    {
        if (
            !$this->isConnected()
            || ($userAuth = static::getUserAuth($userId)) === null
        ) {
            return null;
        }

        $keycloakApi = new KeycloakApi();
        if (!$keycloakApi->isConnected()) {
            return false;
        }
        $api = $keycloakApi->api;

        $config = new ConfigureForm();

        // Search for the client used with this Humhub
        foreach ($api->getClients() as $clientDefinition) {
            if (
                !isset($clientDefinition['clientId'], $clientDefinition['id'])
                || $clientDefinition['clientId'] !== $config->clientId
            ) {
                continue;
            }

            // Get id of the client (different from clientId)
            $idOfClient = $clientDefinition['id'];

            // Get user sessions
            $clientSessions = $api->getClientSessions([
                'id' => $idOfClient,
            ]);
            foreach ($clientSessions as $session) {
                if (
                    isset($session['id'])
                    && $userAuth->source_id === $session['userId']
                ) {
                    // revoke session
                    $result = $api->revokeUserSession([
                        'session' => $session['id'],
                    ]);
                    if (!empty($result['errorMessage'])) {
                        Yii::error('Error revoking user\'s session on Keycloak (user ID: ' . $userId . '): ' . $result['errorMessage'], 'auth-keycloak');
                        return false;
                    }
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
        while (!isset($currentMembers) || count($currentMembers) === static::MAX_USERS_RESULT) {
            $currentMembers = $this->api->getGroupMembers([
                'id' => $groupId,
                'first' => $first,
                'max' => static::MAX_USERS_RESULT,
            ]);
            $currentMemberIds = array_map(static function ($member) {
                return $member['id'] ?? null;
            }, $currentMembers);
            $currentMemberIds = array_filter($currentMemberIds);
            $memberIds = array_merge($memberIds, $currentMemberIds);
            $first += static::MAX_USERS_RESULT;
        }

        return $memberIds;
    }
}
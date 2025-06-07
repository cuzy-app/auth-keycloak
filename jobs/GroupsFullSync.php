<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\jobs;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\components\KeycloakApi;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\models\GroupKeycloak;
use humhub\modules\queue\ActiveJob;
use humhub\modules\queue\interfaces\ExclusiveJobInterface;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\User;
use ReflectionClass;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;
use yii\helpers\ArrayHelper;
use yii\queue\RetryableJobInterface;

class GroupsFullSync extends ActiveJob implements ExclusiveJobInterface, RetryableJobInterface
{
    /**
     * On first sync, HumHub groups and members are added to Keycloak
     * Then, changes on HumHub are sync on real time to Keycloak (in Events.php)
     * But changes on Keycloak are sync by cron to HumHub (in this class)
     * @var bool
     */
    public $firstSync = false;

    /**
     * @var KeycloakApi
     */
    protected $keycloakApi;

    /**
     * @var array
     */
    protected $keycloakGroupsNamesById = [];

    /**
     * @var array Keycloak group ID => [Keycloak user ID]
     * Contains only users that have an account on HumHub
     */
    protected $keycloakGroupsMembers = [];

    /**
     * @var array HumHub group ID => [HumHub user ID]
     * Contains only users that have logged in with Keycloak
     */
    protected $humhubGroupsMembers = [];

    /**
     * @var GroupKeycloak[]
     */
    protected $humhubGroupsByKeycloakId = [];

    /**
     * @var array
     */
    protected $usersKeycloakIdToHumhubId = [];

    /**
     * @inhertidoc
     * @var int maximum 1 hour
     */
    private $maxExecutionTime = 60 * 60;

    /**
     * @inheritdoc
     * @return void
     * @throws InvalidConfigException
     * @throws StaleObjectException
     * @throws Throwable
     */
    public function run()
    {
        $config = new ConfigureForm();
        if (
            !$config->enabled
            || !$config->apiUsername
            || !$config->apiPassword
            || $config->groupsSyncMode === ConfigureForm::GROUP_SYNC_MODE_NONE
        ) {
            return;
        }
        $this->keycloakApi = new KeycloakApi();

        if (!$this->initKeycloakGroupsNamesById()) {
            return;
        }
        $this->initHumhubGroupsByKeycloakId();

        if ($config->syncKeycloakGroupsToHumhub()) {
            $this->addKeycloakGroupsToHumhub();
        }
        if ($this->firstSync && $config->syncHumhubGroupsToKeycloak()) {
            $this->addHumhubGroupsToKeycloak();
        }

        $this->initUsersKeycloakIdToHumhubId();
        $this->initKeycloakGroupsMembers();
        $this->initHumhubGroupsMembers();

        if ($config->syncKeycloakGroupsToHumhub()) {
            $this->addKeycloakUsersToHumhubGroups();
        }
        if ($this->firstSync) {
            if ($config->syncHumhubGroupsToKeycloak()) {
                $this->addHumhubUsersToKeycloakGroups();
            }
        } else {
            if ($config->syncKeycloakGroupsToHumhub(true)) {
                $this->deleteHumhubGroups();
                $this->deleteHumhubUsersFromHumhubGroups();
            }
            if ($config->syncKeycloakGroupsToHumhub()) {
                $this->renameHumhubGroups();
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getTtr()
    {
        return $this->maxExecutionTime;
    }

    /**
     * @inheritDoc
     */
    public function canRetry($attempt, $error)
    {
        $errorMessage = $error ? $error->getMessage() : '';
        Yii::error('Error with Groups full sync job: ' . $errorMessage, 'auth-keycloak');
        return false;
    }

    /**
     * @inerhitdoc
     * Must not exceed to 50 chars
     */
    public function getExclusiveJobId()
    {
        return 'auth-keycloak.' . (new ReflectionClass($this))->getShortName();
    }

    /**
     * @return bool
     */
    protected function initKeycloakGroupsNamesById()
    {
        $this->keycloakGroupsNamesById = $this->keycloakApi->getGroupsNamesById();
        if (!is_array($this->keycloakGroupsNamesById)) {
            Yii::error('Error retrieving groups on Keycloak: ', 'auth-keycloak');
            return false;
        }
        return true;
    }

    /**
     * @return void
     */
    protected function initHumhubGroupsByKeycloakId()
    {
        $this->humhubGroupsByKeycloakId = GroupKeycloak::find()
            ->where(['not', ['keycloak_id' => null]])
            ->indexBy('keycloak_id')
            ->all();
        // Remove groups not in Keycloak
        foreach ($this->humhubGroupsByKeycloakId as $keycloakGroupId => $humhubGroup) {
            if (!array_key_exists($keycloakGroupId, $this->keycloakGroupsNamesById)) {
                unset($this->humhubGroupsByKeycloakId[$keycloakGroupId]);
            }
        }
    }

    /**
     * @return void
     */
    protected function addKeycloakGroupsToHumhub()
    {
        $groupsKeycloakByHumhubName = GroupKeycloak::find()
            ->where(['keycloak_id' => null])
            ->indexBy('name')
            ->all();
        $allHumhubGroupsByKeycloakId = GroupKeycloak::find()
            ->where(['not', ['keycloak_id' => null]])
            ->indexBy('keycloak_id')
            ->all();
        foreach ($this->keycloakGroupsNamesById as $keycloakGroupId => $keycloakGroupName) {
            // Check if HumHub group exists
            if (!array_key_exists($keycloakGroupId, $allHumhubGroupsByKeycloakId)) {
                // Search for existing HumHub group with same name
                if (array_key_exists($keycloakGroupName, $groupsKeycloakByHumhubName)) {
                    $groupKeycloak = $groupsKeycloakByHumhubName[$keycloakGroupName];
                } else { // Add missing group to HumHub
                    $groupKeycloak = new GroupKeycloak();
                    $groupKeycloak->name = $keycloakGroupName;
                }
                $groupKeycloak->keycloak_id = $keycloakGroupId;
                if (!$groupKeycloak->save()) {
                    continue;
                }
                $this->humhubGroupsByKeycloakId[$keycloakGroupId] = $groupKeycloak;
            }
        }
    }

    /**
     * Only on first sync, then sync from HumHub is done by events
     * @return void
     */
    protected function addHumhubGroupsToKeycloak()
    {
        foreach (GroupKeycloak::find()->each() as $humhubGroup) {
            // Check if Keycloak group exists
            if (
                $humhubGroup->keycloak_id === null
                || !array_key_exists($humhubGroup->keycloak_id, $this->keycloakGroupsNamesById)
            ) {
                // Link to a same group name on Keycloak or create group on Keycloak
                if ($this->keycloakApi->linkSameGroupNameOrCreateGroup($humhubGroup->id)) {
                    // Update HumHub group with the new created Keycloak ID
                    $humhubGroup = GroupKeycloak::findOne($humhubGroup->id);
                    $this->keycloakGroupsNamesById[$humhubGroup->keycloak_id] = $humhubGroup->name;
                }
            }
        }
    }

    /**
     * @return void
     */
    protected function initUsersKeycloakIdToHumhubId()
    {
        $auths = Auth::find()
            ->joinWith('user')
            ->andWhere(['user.status' => User::STATUS_ENABLED])
            ->andWhere(['user_auth.source' => Keycloak::DEFAULT_NAME])
            ->orderBy(['user_auth.id' => SORT_ASC]) // Get the latest if it has multiple
            ->indexBy('user_id') // Removes duplicated
            ->all();
        $this->usersKeycloakIdToHumhubId = ArrayHelper::map($auths, 'source_id', 'user_id');
    }

    /**
     * @return void
     */
    protected function initKeycloakGroupsMembers()
    {
        foreach ($this->keycloakGroupsNamesById as $keycloakGroupId => $keycloakGroupName) {
            $this->keycloakGroupsMembers[$keycloakGroupId] = [];
            foreach ($this->keycloakApi->getGroupMemberIds($keycloakGroupId) as $keycloakUserId) {
                // If this user has an account on HumHub
                if ($this->getHumhubUserId($keycloakUserId) !== null) {
                    $this->keycloakGroupsMembers[$keycloakGroupId][] = $keycloakUserId;
                }
            }
        }
    }

    /**
     * @param $keycloakUserId
     * @return string|null
     */
    protected function getHumhubUserId($keycloakUserId)
    {
        return $this->usersKeycloakIdToHumhubId[$keycloakUserId] ?? null;
    }

    /**
     * @return void
     */
    protected function initHumhubGroupsMembers()
    {
        foreach ($this->humhubGroupsByKeycloakId as $humhubGroup) {
            $this->humhubGroupsMembers[$humhubGroup->id] = [];
            foreach ($humhubGroup->groupUsers as $groupUser) {
                $userId = $groupUser->user_id;
                if ($this->isKeycloakUser($userId)) {
                    $this->humhubGroupsMembers[$humhubGroup->id][] = $userId;
                }
            }
        }
    }

    /**
     * @param $humhubUserId
     * @return bool
     */
    protected function isKeycloakUser($humhubUserId)
    {
        return in_array($humhubUserId, $this->usersKeycloakIdToHumhubId);
    }

    /**
     * @return void
     * @throws InvalidConfigException
     */
    protected function addKeycloakUsersToHumhubGroups()
    {
        foreach ($this->keycloakGroupsMembers as $keycloakGroupId => $keycloakUserIds) {
            foreach ($keycloakUserIds as $keycloakUserId) {
                $humhubUserId = $this->getHumhubUserId($keycloakUserId);
                $humhubGroup = $this->humhubGroupsByKeycloakId[$keycloakGroupId];
                $humhubGroupMembers = $this->humhubGroupsMembers[$humhubGroup->id];
                if ($humhubUserId !== null && !in_array($humhubUserId, $humhubGroupMembers)) {
                    $humhubGroup->addUser($humhubUserId);
                    $this->humhubGroupsMembers[$humhubGroup->id][] = $humhubUserId;
                }
            }
        }
    }

    /**
     * Only on first sync, then sync from HumHub is done by events
     * @return void
     */
    protected function addHumhubUsersToKeycloakGroups()
    {
        foreach ($this->humhubGroupsMembers as $humhubGroupId => $humhubUserIds) {
            $keycloakGroupId = $this->getKeycloakGroupId($humhubGroupId);
            $keycloakGroupMembers = $this->keycloakGroupsMembers[$keycloakGroupId];
            foreach ($humhubUserIds as $humhubUserId) {
                $keycloakUserId = $this->getKeycloakUserId($humhubUserId);
                if (
                    $keycloakUserId !== null
                    && !in_array($keycloakUserId, $keycloakGroupMembers)
                ) {
                    $this->keycloakApi->addUserToGroup($humhubUserId, $humhubGroupId);
                    $this->keycloakGroupsMembers[$keycloakGroupId][] = $keycloakUserId;
                }
            }
        }
    }

    /**
     * @param $humhubGroupId
     * @return string|null
     */
    protected function getKeycloakGroupId($humhubGroupId)
    {
        foreach ($this->humhubGroupsByKeycloakId as $humhubGroup) {
            if ($humhubGroup->id === $humhubGroupId) {
                return $humhubGroup->keycloak_id;
            }
        }
        return null;
    }

    /**
     * @param $humhubUserId
     * @return string|null
     */
    protected function getKeycloakUserId($humhubUserId)
    {
        return array_search($humhubUserId, $this->usersKeycloakIdToHumhubId) ?: null;
    }

    /**
     * Check for deleted groups on Keycloak
     * @return void
     * @throws StaleObjectException
     * @throws Throwable
     */
    protected function deleteHumhubGroups()
    {
        foreach ($this->humhubGroupsByKeycloakId as $keycloakGroupId => $humhubGroup) {
            if (!array_key_exists($keycloakGroupId, $this->keycloakGroupsNamesById)) {
                $humhubGroup->delete();
                unset($this->humhubGroupsByKeycloakId[$keycloakGroupId]);
            }
        }
    }

    /**
     * Check for delete users in Keycloak groups
     * @return void
     * @throws StaleObjectException
     * @throws Throwable
     */
    protected function deleteHumhubUsersFromHumhubGroups()
    {
        foreach ($this->humhubGroupsMembers as $humhubGroupId => $humhubUserIds) {
            $keycloakGroupId = $this->getKeycloakGroupId($humhubGroupId);
            $keycloakGroupMembers = $this->keycloakGroupsMembers[$keycloakGroupId];
            $humhubGroup = $this->humhubGroupsByKeycloakId[$keycloakGroupId];
            foreach ($humhubUserIds as $humhubUserKey => $humhubUserId) {
                $keycloakUserId = $this->getKeycloakUserId($humhubUserId);
                if (!in_array($keycloakUserId, $keycloakGroupMembers)) {
                    $humhubGroup->removeUser($humhubUserId);
                    unset($this->humhubGroupsMembers[$humhubGroupId][$humhubUserKey]);
                }
            }
        }
    }

    /**
     * Check if some groups have been renamed on Keycloak
     * @return void
     * @throws Throwable
     */
    protected function renameHumhubGroups()
    {
        foreach ($this->keycloakGroupsNamesById as $keycloakGroupId => $keycloakGroupName) {
            $humhubGroup = $this->humhubGroupsByKeycloakId[$keycloakGroupId];
            if ($humhubGroup->name !== $keycloakGroupName) {
                $humhubGroup->name = $keycloakGroupName;
                $humhubGroup->save();
            }
        }
    }
}

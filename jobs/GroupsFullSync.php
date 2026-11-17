<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.app](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\jobs;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\components\KeycloakApi;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\models\GroupKeycloak;
use humhub\modules\queue\ActiveJob;
use humhub\modules\queue\interfaces\ExclusiveJobInterface;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\GroupUser;
use humhub\modules\user\models\User;
use ReflectionClass;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;
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
     * @var array Keycloak group ID => ['name' => string, 'parentId' => string|null], see {@see KeycloakApi::getGroupsTree()}
     */
    protected $keycloakGroups = [];

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
     * @var GroupKeycloak[] HumHub groups linked to a Keycloak group that is not in {@see $keycloakGroups} anymore
     */
    protected $humhubGroupsMissingOnKeycloak = [];

    /**
     * @var array Keycloak user ID => HumHub user ID
     */
    protected $usersKeycloakIdToHumhubId = [];

    /**
     * @var array HumHub user ID => Keycloak user ID (reverse of [[$usersKeycloakIdToHumhubId]], for O(1) lookups)
     */
    protected $usersHumhubIdToKeycloakId = [];

    /**
     * @var string[][] names of the Keycloak groups, by result: 'created', 'linked' (to an existing HumHub group)
     * and 'withoutMembers' (not created, as they have no members with a HumHub account)
     */
    public $results = ['created' => [], 'linked' => [], 'withoutMembers' => []];

    /**
     * @var bool|null whether the sync ran entirely, null if it was not run (disabled)
     */
    public $isCompleted;

    /**
     * @var callable|null called after retrieving the members of a Keycloak group (console only, not serialized):
     * function (int $groupsDone, int $groupsTotal)
     */
    public $onProgress;

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
            || !$config->hasApiParams()
            || $config->groupsSyncMode === ConfigureForm::GROUP_SYNC_MODE_NONE
        ) {
            return;
        }
        $this->isCompleted = false;
        $this->keycloakApi = new KeycloakApi();

        if (!$this->initKeycloakGroups()) {
            return;
        }
        $this->initHumhubGroupsByKeycloakId();
        $this->initUsersKeycloakIdToHumhubId();

        // The members of the groups to create are retrieved while adding them, as only groups having members are created
        if ($config->syncKeycloakGroupsToHumhub() && !$this->addKeycloakGroupsToHumhub()) {
            return;
        }
        if (!$this->initKeycloakGroupsMembers()) {
            return;
        }
        if ($this->firstSync && $config->syncHumhubGroupsToKeycloak()) {
            $this->addHumhubGroupsToKeycloak();
            // Groups have been linked or created on Keycloak
            if (!$this->initKeycloakGroups()) {
                return;
            }
            $this->initHumhubGroupsByKeycloakId();
            if (!$this->initKeycloakGroupsMembers()) {
                return;
            }
        }

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
                $this->updateHumhubGroups();
            }
        }

        if ($this->results['withoutMembers'] !== []) {
            Yii::warning(
                'Groups full sync: ' . count($this->results['withoutMembers'])
                . ' Keycloak groups not created on HumHub, as they have no members with a HumHub account: '
                . implode(', ', $this->results['withoutMembers']),
                'auth-keycloak',
            );
        }
        $this->isCompleted = true;
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
    protected function initKeycloakGroups()
    {
        $keycloakGroups = $this->keycloakApi->getGroupsTree();
        if (!is_array($keycloakGroups)) {
            Yii::error('Groups full sync aborted: the Keycloak groups could not be retrieved', 'auth-keycloak');
            return false;
        }
        $this->keycloakGroups = $keycloakGroups;
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
        $this->humhubGroupsMissingOnKeycloak = [];
        foreach ($this->humhubGroupsByKeycloakId as $keycloakGroupId => $humhubGroup) {
            if (!array_key_exists($keycloakGroupId, $this->keycloakGroups)) {
                $this->humhubGroupsMissingOnKeycloak[] = $humhubGroup;
                unset($this->humhubGroupsByKeycloakId[$keycloakGroupId]);
            }
        }
    }

    /**
     * Links the Keycloak groups to the HumHub groups having the same name and the same parent, or creates them on HumHub.
     * Keycloak subgroups are HumHub subgroups of the HumHub group linked to their parent.
     * Groups are created only if they (or one of their subgroups) have members with a HumHub account,
     * hidden in the directory and not selectable at registration.
     * @return bool false if the members of a group could not be retrieved
     */
    protected function addKeycloakGroupsToHumhub()
    {
        $allHumhubGroupsByKeycloakId = GroupKeycloak::find()
            ->where(['not', ['keycloak_id' => null]])
            ->indexBy('keycloak_id')
            ->all();
        // Parent groups come first
        foreach ($this->keycloakGroups as $keycloakGroupId => $keycloakGroup) {
            // Check if HumHub group exists
            if (array_key_exists($keycloakGroupId, $allHumhubGroupsByKeycloakId)) {
                continue;
            }

            $parentGroupId = null;
            if ($keycloakGroup['parentId'] !== null) {
                $parentGroup = $this->humhubGroupsByKeycloakId[$keycloakGroup['parentId']] ?? null;
                if ($parentGroup === null) {
                    continue;
                }
                $parentGroupId = $parentGroup->id;
            }

            // Search for existing HumHub group with same name and parent, or add missing group to HumHub
            $groupKeycloak = GroupKeycloak::find()
                ->where(['keycloak_id' => null, 'name' => $keycloakGroup['name'], 'parent_group_id' => $parentGroupId])
                ->one();
            if ($groupKeycloak === null) {
                $hasMembers = $this->hasKeycloakGroupMembers($keycloakGroupId);
                if ($hasMembers === null) {
                    return false;
                }
                if (!$hasMembers) {
                    $this->results['withoutMembers'][] = $keycloakGroup['name'];
                    continue;
                }
                $groupKeycloak = new GroupKeycloak();
                $groupKeycloak->name = $keycloakGroup['name'];
                $groupKeycloak->parent_group_id = $parentGroupId;
                $groupKeycloak->show_at_directory = 0;
                $groupKeycloak->show_at_registration = 0;
            }
            $isNew = $groupKeycloak->isNewRecord;
            $groupKeycloak->keycloak_id = $keycloakGroupId;
            if (!$groupKeycloak->save()) {
                Yii::warning('Groups full sync: could not save the HumHub group for the Keycloak group "' . $keycloakGroup['name'] . '". Errors: ' . json_encode($groupKeycloak->getErrors()), 'auth-keycloak');
                continue;
            }
            $this->results[$isNew ? 'created' : 'linked'][] = $keycloakGroup['name'];
            $this->humhubGroupsByKeycloakId[$keycloakGroupId] = $groupKeycloak;
        }
        return true;
    }

    /**
     * @param string $keycloakGroupId
     * @return bool|null whether the Keycloak group or one of its subgroups has members with a HumHub account,
     * null if the members of a group could not be retrieved
     */
    protected function hasKeycloakGroupMembers($keycloakGroupId)
    {
        $groupIds = [$keycloakGroupId];
        foreach ($this->keycloakGroups as $subgroupId => $keycloakGroup) {
            if ($keycloakGroup['parentId'] === $keycloakGroupId) {
                $groupIds[] = $subgroupId;
            }
        }
        foreach ($groupIds as $groupId) {
            if (!$this->initKeycloakGroupMembers($groupId)) {
                return null;
            }
            if (!empty($this->keycloakGroupsMembers[$groupId])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Only on first sync, then sync from HumHub is done by events
     * @return void
     */
    protected function addHumhubGroupsToKeycloak()
    {
        // Parent groups first, so that their subgroups are created under them
        $humhubGroups = GroupKeycloak::find()->all();
        usort($humhubGroups, static fn($a, $b) => ($a->parent_group_id !== null) <=> ($b->parent_group_id !== null));
        foreach ($humhubGroups as $humhubGroup) {
            // Check if Keycloak group exists
            if (
                $humhubGroup->keycloak_id === null
                || !array_key_exists($humhubGroup->keycloak_id, $this->keycloakGroups)
            ) {
                // Link to a same group name (and parent) on Keycloak or create group on Keycloak
                $this->keycloakApi->linkSameGroupNameOrCreateGroup($humhubGroup->id);
            }
        }
    }

    /**
     * @return void
     */
    protected function initUsersKeycloakIdToHumhubId()
    {
        // Only the two columns needed, no model hydration (the users are not eager-loaded either)
        $rows = Auth::find()
            ->select(['user_auth.user_id', 'user_auth.source_id'])
            ->innerJoin('user', 'user.id = user_auth.user_id')
            ->andWhere(['user.status' => User::STATUS_ENABLED])
            ->andWhere(['user_auth.source' => Keycloak::DEFAULT_NAME])
            ->orderBy(['user_auth.id' => SORT_ASC]) // Get the latest if it has multiple
            ->asArray()
            ->all();

        $this->usersHumhubIdToKeycloakId = [];
        foreach ($rows as $row) {
            $this->usersHumhubIdToKeycloakId[(int)$row['user_id']] = $row['source_id']; // Removes duplicated
        }
        $this->usersKeycloakIdToHumhubId = [];
        foreach ($this->usersHumhubIdToKeycloakId as $humhubUserId => $keycloakUserId) {
            $this->usersKeycloakIdToHumhubId[$keycloakUserId] = $humhubUserId;
        }
    }

    /**
     * Retrieves the members of the Keycloak groups not retrieved yet
     * @return bool false if the members of a group could not be retrieved
     */
    protected function initKeycloakGroupsMembers()
    {
        foreach (array_keys($this->keycloakGroups) as $keycloakGroupId) {
            if (!$this->initKeycloakGroupMembers($keycloakGroupId)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Retrieves the members of the Keycloak group, if not retrieved yet
     * @param string $keycloakGroupId
     * @return bool false if the members could not be retrieved
     */
    protected function initKeycloakGroupMembers($keycloakGroupId)
    {
        if (array_key_exists($keycloakGroupId, $this->keycloakGroupsMembers)) {
            return true;
        }
        $keycloakUserIds = $this->keycloakApi->getGroupMemberIds($keycloakGroupId);
        if ($keycloakUserIds === null) {
            // Abort, otherwise all members of this group would be removed from the HumHub group
            Yii::error('Groups full sync aborted: members of the Keycloak group ID ' . $keycloakGroupId . ' could not be retrieved', 'auth-keycloak');
            return false;
        }
        $this->keycloakGroupsMembers[$keycloakGroupId] = [];
        foreach ($keycloakUserIds as $keycloakUserId) {
            // If this user has an account on HumHub
            if ($this->getHumhubUserId($keycloakUserId) !== null) {
                $this->keycloakGroupsMembers[$keycloakGroupId][] = $keycloakUserId;
            }
        }
        if ($this->onProgress !== null) {
            ($this->onProgress)(count($this->keycloakGroupsMembers), count($this->keycloakGroups));
        }
        return true;
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
            // Member ids only, no GroupUser model per member
            $memberIds = GroupUser::find()
                ->select('user_id')
                ->where(['group_id' => $humhubGroup->id])
                ->column();
            foreach ($memberIds as $userId) {
                $userId = (int)$userId;
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
        return isset($this->usersHumhubIdToKeycloakId[(int)$humhubUserId]);
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
                $humhubGroup = $this->humhubGroupsByKeycloakId[$keycloakGroupId] ?? null;
                if ($humhubGroup === null) {
                    continue;
                }
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
        return $this->usersHumhubIdToKeycloakId[(int)$humhubUserId] ?? null;
    }

    /**
     * Deletes the HumHub groups whose Keycloak group has been deleted.
     * The admin and protected groups are only unlinked from Keycloak.
     * @return void
     * @throws StaleObjectException
     * @throws Throwable
     */
    protected function deleteHumhubGroups()
    {
        // Subgroups first
        $humhubGroups = $this->humhubGroupsMissingOnKeycloak;
        usort($humhubGroups, static fn($a, $b) => ($b->parent_group_id !== null) <=> ($a->parent_group_id !== null));

        foreach ($humhubGroups as $humhubGroup) {
            // Make sure the group has been deleted on Keycloak (e.g. not moved deeper than the subgroups level)
            if ($this->keycloakApi->groupExists($humhubGroup->keycloak_id) !== false) {
                continue;
            }

            // Unlink first, so that the group events do not try to update the Keycloak group
            $keycloakGroupId = $humhubGroup->keycloak_id;
            $humhubGroup->updateAttributes(['keycloak_id' => null]);

            if ($humhubGroup->is_admin_group || $humhubGroup->is_protected) {
                Yii::warning('Groups full sync: the Keycloak group ' . $keycloakGroupId . ' has been deleted, the admin or protected group "' . $humhubGroup->name . '" (ID ' . $humhubGroup->id . ') is only unlinked', 'auth-keycloak');
                continue;
            }

            Yii::warning('Groups full sync: deleting the group "' . $humhubGroup->name . '" (ID ' . $humhubGroup->id . '), its Keycloak group ' . $keycloakGroupId . ' has been deleted', 'auth-keycloak');
            $humhubGroup->delete();
        }
        $this->humhubGroupsMissingOnKeycloak = [];
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
     * Check if some groups have been renamed or moved to another parent on Keycloak
     * @return void
     * @throws Throwable
     */
    protected function updateHumhubGroups()
    {
        foreach ($this->keycloakGroups as $keycloakGroupId => $keycloakGroup) {
            $humhubGroup = $this->humhubGroupsByKeycloakId[$keycloakGroupId] ?? null;
            if ($humhubGroup === null) {
                continue;
            }

            $parentGroupId = null;
            if ($keycloakGroup['parentId'] !== null) {
                $parentGroupId = ($this->humhubGroupsByKeycloakId[$keycloakGroup['parentId']] ?? null)?->id;
                if ($parentGroupId === null) {
                    continue;
                }
                $parentGroupId = (int)$parentGroupId;
            }
            $currentParentGroupId = $humhubGroup->parent_group_id === null ? null : (int)$humhubGroup->parent_group_id;
            if (
                $parentGroupId !== null
                && $currentParentGroupId !== $parentGroupId
                && GroupKeycloak::find()->where(['parent_group_id' => $humhubGroup->id])->exists()
            ) {
                // HumHub supports only one level of subgroups
                Yii::warning('Groups full sync: the group "' . $humhubGroup->name . '" (ID ' . $humhubGroup->id . ') has subgroups and cannot become a subgroup', 'auth-keycloak');
                $parentGroupId = $currentParentGroupId;
            }

            if ($humhubGroup->name !== $keycloakGroup['name'] || $currentParentGroupId !== $parentGroupId) {
                $humhubGroup->name = $keycloakGroup['name'];
                $humhubGroup->parent_group_id = $parentGroupId;
                $humhubGroup->save();
            }
        }
    }
}

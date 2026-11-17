<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 */

namespace humhub\modules\authKeycloak\tests\codeception\unit;

use humhub\modules\authKeycloak\components\KeycloakApi;
use humhub\modules\authKeycloak\jobs\GroupsFullSync;
use humhub\modules\authKeycloak\jobs\GroupsUserSync;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\models\GroupKeycloak;
use humhub\modules\authKeycloak\tests\codeception\_support\KeycloakTestCase;
use humhub\modules\authKeycloak\tests\codeception\_support\KeycloakTestServer;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\User;

class GroupsSyncTest extends KeycloakTestCase
{
    public function testKeycloakToHumhubWithSubgroups()
    {
        $this->configureModule(['groupsSyncMode' => ConfigureForm::GROUP_SYNC_MODE_KC_TO_HH]);
        [$user1, $user1KeycloakId] = $this->createLinkedUser('User1');
        [$user2, $user2KeycloakId] = $this->createLinkedUser('User2');

        $sales = KeycloakTestServer::createGroup('Sales');
        $north = KeycloakTestServer::createGroup('North', $sales);
        $deep = KeycloakTestServer::createGroup('Deep', $north);
        $south = KeycloakTestServer::createGroup('South', $sales);
        KeycloakTestServer::createGroup('Empty');
        KeycloakTestServer::addUserToGroup($user1KeycloakId, $north);
        KeycloakTestServer::addUserToGroup($user1KeycloakId, $south);
        KeycloakTestServer::addUserToGroup($user2KeycloakId, $deep);

        $job = new GroupsFullSync(['firstSync' => true]);
        $job->run();
        $this->assertTrue($job->isCompleted);
        $this->assertSame(['Sales', 'North', 'South'], $job->results['created']);
        $this->assertSame(['Empty'], $job->results['withoutMembers']);

        $this->assertHumhubGroups(['Sales', 'Sales/North', 'Sales/South']);
        $this->assertTrue($this->getHumhubGroup($north)->isMember($user1));
        // Groups deeper than the subgroups level are ignored
        $this->assertNull(GroupKeycloak::findOne(['name' => 'Deep']));
        // Groups without members are not created
        $this->assertNull(GroupKeycloak::findOne(['name' => 'Empty']));
        // Created groups are hidden and not selectable at registration
        $this->assertEquals(0, $this->getHumhubGroup($north)->show_at_directory);
        $this->assertEquals(0, $this->getHumhubGroup($north)->show_at_registration);

        // Changes on Keycloak
        KeycloakTestServer::request('POST', '/groups', ['id' => $south, 'name' => 'South']);
        KeycloakTestServer::request('DELETE', '/groups/' . $north);
        $east = KeycloakTestServer::createGroup('East', $sales);
        KeycloakTestServer::addUserToGroup($user2KeycloakId, $east);
        KeycloakTestServer::request('PUT', '/groups/' . $sales, ['name' => 'Sales Team']);

        (new GroupsFullSync())->run();

        $this->assertHumhubGroups(['Sales Team', 'Sales Team/East', 'South']);
        $this->assertTrue($this->getHumhubGroup($east)->isMember($user2));
        $this->assertNull(GroupKeycloak::findOne(['name' => 'North']));
    }

    public function testMembersRemovedOnKeycloakAreRemovedOnHumhub()
    {
        $this->configureModule(['groupsSyncMode' => ConfigureForm::GROUP_SYNC_MODE_KC_TO_HH]);
        [$user1, $user1KeycloakId] = $this->createLinkedUser('User1');
        $sales = KeycloakTestServer::createGroup('Sales');
        KeycloakTestServer::addUserToGroup($user1KeycloakId, $sales);
        (new GroupsFullSync(['firstSync' => true]))->run();
        $group = $this->getHumhubGroup($sales);
        $user2 = User::findOne(['username' => 'User2']);
        // Local member, not managed by Keycloak
        $this->assertTrue($group->addUser($user2));
        $this->assertTrue($group->isMember($user2));
        $this->assertTrue($group->isMember($user1));

        KeycloakTestServer::request('DELETE', '/users/' . $user1KeycloakId . '/groups/' . $sales);
        (new GroupsFullSync())->run();

        $this->assertFalse($group->isMember($user1));
        $this->assertTrue($group->isMember($user2));
    }

    public function testAdminGroupIsSyncedButNeverDeleted()
    {
        $this->configureModule(['groupsSyncMode' => ConfigureForm::GROUP_SYNC_MODE_KC_TO_HH]);
        [$user1, $user1KeycloakId] = $this->createLinkedUser('User1');
        $adminGroup = Group::getAdminGroup();
        $keycloakAdminGroup = KeycloakTestServer::createGroup($adminGroup->name);
        KeycloakTestServer::addUserToGroup($user1KeycloakId, $keycloakAdminGroup);

        (new GroupsFullSync(['firstSync' => true]))->run();
        $this->assertSame($keycloakAdminGroup, GroupKeycloak::findOne($adminGroup->id)->keycloak_id);
        $this->assertTrue($adminGroup->isMember($user1));

        KeycloakTestServer::request('DELETE', '/groups/' . $keycloakAdminGroup);
        (new GroupsFullSync())->run();

        $adminGroup = GroupKeycloak::findOne($adminGroup->id);
        $this->assertNotNull($adminGroup);
        $this->assertNull($adminGroup->keycloak_id);
    }

    public function testSignInAddsUserToSubgroups()
    {
        $this->configureModule(['groupsSyncMode' => ConfigureForm::GROUP_SYNC_MODE_KC_TO_HH]);
        [$user1, $user1KeycloakId] = $this->createLinkedUser('User1');
        [, $user2KeycloakId] = $this->createLinkedUser('User2');
        $sales = KeycloakTestServer::createGroup('Sales');
        $north = KeycloakTestServer::createGroup('North', $sales);
        KeycloakTestServer::addUserToGroup($user2KeycloakId, $north);
        (new GroupsFullSync(['firstSync' => true]))->run();

        KeycloakTestServer::addUserToGroup($user1KeycloakId, $north);
        (new GroupsUserSync(['userId' => $user1->id]))->run();

        $this->assertTrue($this->getHumhubGroup($north)->isMember($user1));
    }

    public function testHumhubToKeycloakFirstSync()
    {
        $this->configureModule(['groupsSyncMode' => ConfigureForm::GROUP_SYNC_MODE_HH_TO_KC]);
        [$user1, $user1KeycloakId] = $this->createLinkedUser('User1');
        $editors = Group::findOne(['name' => 'Editors']);
        $editors->addUser($user1);

        (new GroupsFullSync(['firstSync' => true]))->run();

        // The fixture subgroup "Editors" of "Moderators"
        $this->assertContains('Moderators/Editors', $this->getKeycloakGroupPaths());
        $this->assertContains($user1KeycloakId, (new KeycloakApi())->getGroupMemberIds(GroupKeycloak::findOne($editors->id)->keycloak_id));
    }

    public function testHumhubToKeycloakRealTime()
    {
        $this->configureModule(['groupsSyncMode' => ConfigureForm::GROUP_SYNC_MODE_HH_TO_KC]);
        [$user1, $user1KeycloakId] = $this->createLinkedUser('User1');

        $marketing = new Group(['name' => 'Marketing']);
        $this->assertTrue($marketing->save());
        // The admin form sets the parent group before the insert event
        $online = new Group(['name' => 'Online']);
        $online->parent_group_id = $marketing->id;
        $this->assertTrue($online->save());
        $this->assertContains('Marketing/Online', $this->getKeycloakGroupPaths());

        // The admin form sets the parent group without event, then saves the group
        $online->updateAttributes(['parent_group_id' => null]);
        $online->save();
        $this->assertContains('Online', $this->getKeycloakGroupPaths());
        $this->assertNotContains('Marketing/Online', $this->getKeycloakGroupPaths());

        $online->updateAttributes(['parent_group_id' => $marketing->id]);
        $online->save();
        $this->assertContains('Marketing/Online', $this->getKeycloakGroupPaths());

        // Subgroups removed from the parent group form
        Group::updateAll(['parent_group_id' => null], ['id' => $online->id]);
        $marketing->save();
        $this->assertContains('Online', $this->getKeycloakGroupPaths());

        $online->updateAttributes(['parent_group_id' => $marketing->id]);
        $online->save();
        $online->addUser($user1);
        $onlineKeycloakId = GroupKeycloak::findOne($online->id)->keycloak_id;
        $this->assertSame([$user1KeycloakId], (new KeycloakApi())->getGroupMemberIds($onlineKeycloakId));

        // Deleting the parent group: the subgroup becomes a top level group on both sides
        Group::findOne($marketing->id)->delete();
        $paths = $this->getKeycloakGroupPaths();
        $this->assertContains('Online', $paths);
        $this->assertNotContains('Marketing', $paths);
        $this->assertNull(Group::findOne($online->id)->parent_group_id);
    }

    /**
     * @return array [HumHub user, Keycloak user ID]
     */
    private function createLinkedUser(string $username): array
    {
        $user = User::findOne(['username' => $username]);
        $keycloakUserId = KeycloakTestServer::createUser([
            'username' => strtolower($username),
            'email' => $user->email,
            'firstName' => $username,
            'lastName' => 'Test',
        ]);
        $this->linkUser($user, $keycloakUserId);
        return [$user, $keycloakUserId];
    }

    private function getHumhubGroup(string $keycloakGroupId): GroupKeycloak
    {
        $group = GroupKeycloak::findOne(['keycloak_id' => $keycloakGroupId]);
        $this->assertNotNull($group, 'No HumHub group linked to the Keycloak group ' . $keycloakGroupId);
        return $group;
    }

    /**
     * Asserts the paths of the HumHub groups linked to Keycloak
     */
    private function assertHumhubGroups(array $expectedPaths): void
    {
        $paths = [];
        foreach (GroupKeycloak::find()->where(['not', ['keycloak_id' => null]])->all() as $group) {
            $parent = $group->parent_group_id ? GroupKeycloak::findOne($group->parent_group_id) : null;
            $paths[] = ($parent ? $parent->name . '/' : '') . $group->name;
        }
        sort($paths);
        sort($expectedPaths);
        $this->assertSame($expectedPaths, $paths);
    }

    /**
     * @return string[] paths of the Keycloak groups (top level groups and their subgroups)
     */
    private function getKeycloakGroupPaths(): array
    {
        $groups = (new KeycloakApi())->getGroupsTree();
        $this->assertIsArray($groups);
        $paths = [];
        foreach ($groups as $group) {
            $paths[] = ($group['parentId'] ? $groups[$group['parentId']]['name'] . '/' : '') . $group['name'];
        }
        return $paths;
    }
}

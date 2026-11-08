<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace tests\codeception\unit\modules\authKeycloak;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\jobs\GroupsFullSync;
use humhub\modules\authKeycloak\models\GroupKeycloak;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\User;
use ReflectionClass;
use tests\codeception\_support\HumHubDbTestCase;
use Yii;

/**
 * The user maps of the groups full sync are built from ids only and must give the same answers as before
 * (latest auth record per user wins, disabled users excluded, group members resolved by id).
 */
class GroupsFullSyncMapsTest extends HumHubDbTestCase
{
    private function insertAuth(int $userId, string $sourceId, string $source = Keycloak::DEFAULT_NAME): void
    {
        Yii::$app->db->createCommand()->insert('user_auth', [
            'user_id' => $userId,
            'source' => $source,
            'source_id' => $sourceId,
        ])->execute();
    }

    private function call(GroupsFullSync $job, string $method, ...$args)
    {
        $m = (new ReflectionClass($job))->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke($job, ...$args);
    }

    private function get(GroupsFullSync $job, string $property)
    {
        $p = (new ReflectionClass($job))->getProperty($property);
        $p->setAccessible(true);
        return $p->getValue($job);
    }

    private function set(GroupsFullSync $job, string $property, $value): void
    {
        $p = (new ReflectionClass($job))->getProperty($property);
        $p->setAccessible(true);
        $p->setValue($job, $value);
    }

    public function testUserMapsAreBuiltFromEnabledKeycloakUsersOnly(): void
    {
        $this->insertAuth(2, 'kc-user1-old');
        $this->insertAuth(2, 'kc-user1'); // later record of the same user wins
        $this->insertAuth(3, 'kc-user2');
        $this->insertAuth(5, 'kc-disabled'); // DisabledUser (status 0): excluded
        $this->insertAuth(4, 'other-source-id', 'ldap'); // other auth source: excluded

        $job = new GroupsFullSync();
        $this->call($job, 'initUsersKeycloakIdToHumhubId');

        $this->assertSame(['kc-user1' => 2, 'kc-user2' => 3], $this->get($job, 'usersKeycloakIdToHumhubId'));
        $this->assertSame([2 => 'kc-user1', 3 => 'kc-user2'], $this->get($job, 'usersHumhubIdToKeycloakId'));

        $this->assertSame(2, $this->call($job, 'getHumhubUserId', 'kc-user1'));
        $this->assertNull($this->call($job, 'getHumhubUserId', 'kc-disabled'));
        $this->assertSame('kc-user2', $this->call($job, 'getKeycloakUserId', 3));
        $this->assertSame('kc-user2', $this->call($job, 'getKeycloakUserId', '3'));
        $this->assertNull($this->call($job, 'getKeycloakUserId', 4));
        $this->assertTrue($this->call($job, 'isKeycloakUser', 2));
        $this->assertFalse($this->call($job, 'isKeycloakUser', 5));
    }

    public function testHumhubGroupMembersKeepOnlyKeycloakUsers(): void
    {
        $this->becomeUser('Admin');
        $this->insertAuth(2, 'kc-user1');

        $group = new GroupKeycloak(['name' => 'Synced', 'keycloak_id' => 'kc-group-1']);
        $this->assertTrue($group->save());
        $group->addUser(User::findOne(2)); // Keycloak user
        $group->addUser(User::findOne(3)); // local user

        $job = new GroupsFullSync();
        $this->set($job, 'humhubGroupsByKeycloakId', ['kc-group-1' => GroupKeycloak::findOne($group->id)]);
        $this->call($job, 'initUsersKeycloakIdToHumhubId');
        $this->call($job, 'initHumhubGroupsMembers');

        $this->assertSame([$group->id => [2]], $this->get($job, 'humhubGroupsMembers'));
    }
}

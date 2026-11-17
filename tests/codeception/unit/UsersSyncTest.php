<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 */

namespace humhub\modules\authKeycloak\tests\codeception\unit;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\components\KeycloakApi;
use humhub\modules\authKeycloak\source\KeycloakUserSource;
use humhub\modules\authKeycloak\tests\codeception\_support\KeycloakTestCase;
use humhub\modules\authKeycloak\tests\codeception\_support\KeycloakTestServer;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\User;
use Yii;

class UsersSyncTest extends KeycloakTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->configureModule([
            'syncUsers' => true,
            'attributeMapping' => "username=preferred_username\nphone_work=phoneNumber\ncity=locality",
        ]);

        // The fixtures are loaded only once (`cleanup: false`): restore the fixture users changed by previous tests (e.g. adopted)
        foreach (require Yii::getAlias('@modules/user/tests/codeception/fixtures/data/user.php') as $row) {
            User::updateAll(array_intersect_key($row, array_flip(['username', 'email', 'status', 'user_source'])), ['id' => $row['id']]);
        }
    }

    public function testCreateUpdateAndDeactivate()
    {
        $countsBefore = $this->getUserSource()->countUsersByStatus();
        $keycloakUserId = KeycloakTestServer::createUser([
            'username' => 'kc.alice',
            'email' => 'alice@keycloak.test',
            'firstName' => 'Alice',
            'lastName' => 'Keycloak',
            'attributes' => ['phoneNumber' => ['+49 111'], 'locality' => ['Berlin', 'Munich']],
        ]);

        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_CREATED]);
        $user = User::findOne(['email' => 'alice@keycloak.test']);
        $this->assertNotNull($user);
        $this->assertSame(KeycloakUserSource::SOURCE_ID, $user->user_source);
        $this->assertSame('kc.alice', $user->username);
        $this->assertSame('Alice', $user->profile->firstname);
        $this->assertSame('+49 111', $user->profile->phone_work);
        $this->assertSame('Berlin', $user->profile->city);

        // Attribute changed and attribute removed on Keycloak
        KeycloakTestServer::updateUser($keycloakUserId, ['attributes' => ['phoneNumber' => ['+49 222']]]);
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_UPDATED]);
        $user->refresh();
        $user->profile->refresh();
        $this->assertSame('+49 222', $user->profile->phone_work);
        $this->assertEmpty($user->profile->city);

        // Disabled, then enabled again on Keycloak
        KeycloakTestServer::updateUser($keycloakUserId, ['enabled' => false]);
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_DISABLED]);
        $user->refresh();
        $this->assertSame(User::STATUS_DEACTIVATED, (int)$user->status);

        KeycloakTestServer::updateUser($keycloakUserId, ['enabled' => true]);
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_ENABLED]);
        $user->refresh();
        $this->assertSame(User::STATUS_ENABLED, (int)$user->status);

        // Removed on Keycloak (another user remains, otherwise the sync is aborted)
        KeycloakTestServer::createUser(['username' => 'kc.bob', 'email' => 'bob@keycloak.test', 'firstName' => 'Bob', 'lastName' => 'Keycloak']);
        KeycloakTestServer::request('DELETE', '/users/' . $keycloakUserId);
        $source = $this->getUserSource();
        $results = $source->syncUsers();
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_DISABLED]);
        $user->refresh();
        $this->assertSame(User::STATUS_DEACTIVATED, (int)$user->status);

        $this->assertSame(1, $source->lastSyncKeycloakUsersCount);
        // Alice (deactivated) and Bob (enabled)
        $counts = $source->countUsersByStatus();
        $this->assertSame(array_sum($countsBefore) + 2, array_sum($counts));
        $this->assertSame(1, $counts[User::STATUS_ENABLED]);
    }

    public function testNoDeactivationIfTheListIsIncomplete()
    {
        $keycloakUserId = KeycloakTestServer::createUser(['username' => 'kc.alice', 'email' => 'alice@keycloak.test', 'firstName' => 'Alice', 'lastName' => 'Keycloak']);
        KeycloakTestServer::createUser(['username' => 'kc.bob', 'email' => 'bob@keycloak.test', 'firstName' => 'Bob', 'lastName' => 'Keycloak']);
        $this->getUserSource()->syncUsers();
        KeycloakTestServer::request('DELETE', '/users/' . $keycloakUserId);

        // Keycloak reports more users than listed (e.g. user federation importing users while listing)
        Yii::$container->set(KeycloakApi::class, new class () extends KeycloakApi {
            public function getUserCount(): ?int
            {
                return 1000;
            }
        });
        try {
            static::logInitialize();
            $results = $this->getUserSource()->syncUsers();
        } finally {
            Yii::$container->clear(KeycloakApi::class);
        }

        $this->assertSame(0, $results[KeycloakUserSource::SYNC_DISABLED]);
        $this->assertSame(User::STATUS_ENABLED, (int)User::findOne(['email' => 'alice@keycloak.test'])->status);
        static::assertLogRegexCount(KeycloakApi::MAX_USERS_LIST_PASSES, '/users listed, but the realm has 1000 users/');
        static::assertLog('Users sync: the list of Keycloak users may be incomplete, so no HumHub user is deactivated for not being found on Keycloak');
    }

    public function testUsersWithExistingEmailAreSkipped()
    {
        // The email of the fixture user "User1", owned by the local user source
        $keycloakUserId = KeycloakTestServer::createUser(['username' => 'kc.user1', 'email' => 'user1@example.com', 'firstName' => 'User', 'lastName' => 'One']);

        static::logInitialize();
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_CREATED]);
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_SKIPPED]);
        $this->assertSame('local', User::findOne(['email' => 'user1@example.com'])->user_source);

        // The skipped users are logged with the reason
        static::assertLog(
            "Users sync: 1 Keycloak users skipped (Keycloak ID, username, first name, last name: reason):\n"
            . $keycloakUserId . ' kc.user1 User One: email user1@example.com already used by the HumHub user User1 (ID 2, user source "local")',
        );
    }

    public function testExistingUsersAreAdoptedByEmail()
    {
        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => true]);
        // The emails of the fixture users "User1" (enabled) and "DisabledUser" (deactivated), owned by the local user source
        $keycloakUserId = KeycloakTestServer::createUser(['username' => 'kc.user1', 'email' => 'user1@example.com', 'firstName' => 'Adopted', 'lastName' => 'User']);
        KeycloakTestServer::createUser(['username' => 'kc.disabled', 'email' => 'disabled@example.com', 'firstName' => 'Disabled', 'lastName' => 'User']);

        static::logInitialize();
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(2, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_CREATED]);
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_SKIPPED]);

        $user = User::findOne(['email' => 'user1@example.com']);
        $this->assertSame(KeycloakUserSource::SOURCE_ID, $user->user_source);
        $this->assertSame('User1', $user->username);
        $this->assertSame('Adopted', $user->profile->firstname);
        $this->assertTrue(Auth::find()->where(['source' => Keycloak::DEFAULT_NAME, 'source_id' => $keycloakUserId, 'user_id' => $user->id])->exists());
        // Logged as a warning if a previous test made the user a system administrator
        static::assertLogRegexCount(1, '/^Users sync: adopted the HumHub user User1 \(2, previous user source: "local"\)/');

        // The deactivated user is enabled, as on Keycloak
        $user = User::findOne(['email' => 'disabled@example.com']);
        $this->assertSame(KeycloakUserSource::SOURCE_ID, $user->user_source);
        $this->assertSame(User::STATUS_ENABLED, (int)$user->status);

        // The adopted users are then synced as the other users of the source
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertSame(2, $results[KeycloakUserSource::SYNC_UNCHANGED]);
    }

    public function testLinkedUsersOfAnotherSourceAreAdopted()
    {
        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => true, 'updateHumhubEmailFromBrokerEmail' => true]);
        // Local users who already signed in with Keycloak, having another email on Keycloak
        $this->linkUser(User::findOne(['username' => 'User2']), KeycloakTestServer::createUser(['username' => 'kc.user2', 'email' => 'user2@keycloak.test', 'firstName' => 'User', 'lastName' => 'Two']));
        // This email is already used by another HumHub user, so the user cannot be updated
        $this->linkUser(User::findOne(['username' => 'User3']), KeycloakTestServer::createUser(['username' => 'kc.user3', 'email' => 'admin@example.com', 'firstName' => 'User', 'lastName' => 'Three']));

        static::logInitialize();
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_FAILED]);

        $user = User::findOne(['username' => 'User2']);
        $this->assertSame(KeycloakUserSource::SOURCE_ID, $user->user_source);
        $this->assertSame('user2@keycloak.test', $user->email);

        // The failed adoption is rolled back
        $user = User::findOne(['username' => 'User3']);
        $this->assertSame('local', $user->user_source);
        $this->assertSame('user3@example.com', $user->email);
        static::assertLogRegexCount(1, '/kc\.user3 User Three: could not adopt the HumHub user of the user source "local": could not update the HumHub user User3 \(ID 4\)/');
    }

    public function testAdoptedUserKeepsItsUsernameIfAlreadyUsed()
    {
        $this->configureModule([
            'syncUsers' => true,
            'adoptExistingUsers' => true,
            'updateHumhubUsernameFromBrokerUsername' => true,
            'attributeMapping' => 'username=preferred_username',
        ]);
        // The username of the fixture user "User2"
        KeycloakTestServer::createUser(['username' => 'user2', 'email' => 'user1@example.com', 'firstName' => 'User', 'lastName' => 'One']);

        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $user = User::findOne(['email' => 'user1@example.com']);
        $this->assertSame(KeycloakUserSource::SOURCE_ID, $user->user_source);
        $this->assertSame('User1', $user->username);
    }

    public function testUsersNotAdopted()
    {
        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => true]);
        // Disabled on Keycloak
        KeycloakTestServer::createUser(['username' => 'kc.user1', 'email' => 'user1@example.com', 'firstName' => 'User', 'lastName' => 'One', 'enabled' => false]);
        // Awaiting approval on HumHub
        KeycloakTestServer::createUser(['username' => 'kc.unapproved', 'email' => 'unapproved@example.com', 'firstName' => 'Unapproved', 'lastName' => 'User']);
        // Linked to another Keycloak user
        $this->linkUser(User::findOne(['username' => 'User2']), 'another-keycloak-user-id');
        KeycloakTestServer::createUser(['username' => 'kc.user2', 'email' => 'user2@example.com', 'firstName' => 'User', 'lastName' => 'Two']);
        // Soft deleted on HumHub
        User::findOne(['username' => 'User3'])->updateAttributes(['status' => User::STATUS_SOFT_DELETED]);
        KeycloakTestServer::createUser(['username' => 'kc.user3', 'email' => 'user3@example.com', 'firstName' => 'User', 'lastName' => 'Three']);

        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_CREATED]);
        $this->assertSame(4, $results[KeycloakUserSource::SYNC_SKIPPED]);
        foreach (['User1', 'UnapprovedUser', 'User2', 'User3'] as $username) {
            $this->assertSame('local', User::findOne(['username' => $username])->user_source, $username);
        }
    }
}

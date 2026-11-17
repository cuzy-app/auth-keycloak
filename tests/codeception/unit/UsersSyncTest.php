<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 */

namespace humhub\modules\authKeycloak\tests\codeception\unit;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\components\KeycloakApi;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\source\KeycloakUserSource;
use humhub\modules\authKeycloak\tests\codeception\_support\KeycloakTestCase;
use humhub\modules\authKeycloak\tests\codeception\_support\KeycloakTestServer;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\GroupUser;
use humhub\modules\user\models\User;
use Yii;
use yii\log\Logger;

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
        // and the system administrators (e.g. added by the groups sync tests)
        $adminGroup = Group::getAdminGroup();
        $fixtureAdminIds = array_column(array_filter(
            require Yii::getAlias('@modules/user/tests/codeception/fixtures/data/group_user.php'),
            static fn($row) => (int)$row['group_id'] === $adminGroup->id,
        ), 'user_id');
        GroupUser::deleteAll(['and', ['group_id' => $adminGroup->id], ['not in', 'user_id', $fixtureAdminIds]]);
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
        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => ConfigureForm::ADOPT_USERS_SAFE]);
        // The email of the fixture user "User1", owned by the local user source (verified on Keycloak)
        $keycloakUserId = KeycloakTestServer::createUser(['username' => 'kc.user1', 'email' => 'user1@example.com', 'firstName' => 'Adopted', 'lastName' => 'User']);

        static::logInitialize();
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_CREATED]);
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_SKIPPED]);

        $user = User::findOne(['email' => 'user1@example.com']);
        $this->assertSame(KeycloakUserSource::SOURCE_ID, $user->user_source);
        $this->assertSame('User1', $user->username);
        $this->assertSame('Adopted', $user->profile->firstname);
        $this->assertTrue(Auth::find()->where(['source' => Keycloak::DEFAULT_NAME, 'source_id' => $keycloakUserId, 'user_id' => $user->id])->exists());
        static::assertLogRegexCount(1, '/^Users sync: adopted the HumHub user User1 \(2, previous user source: "local"\)$/', Logger::LEVEL_INFO);

        // The adopted users are then synced as the other users of the source
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_UNCHANGED]);
    }

    public function testUnverifiedEmailsAreNotAdopted()
    {
        // The email of the fixture user "User1", not verified on Keycloak
        KeycloakTestServer::createUser(['username' => 'kc.user1', 'email' => 'user1@example.com', 'emailVerified' => false, 'firstName' => 'User', 'lastName' => 'One']);

        // Even if the email sent by Keycloak is always trusted
        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => ConfigureForm::ADOPT_USERS_SAFE, 'trustOnlyVerifiedEmail' => false]);
        static::logInitialize();
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_SKIPPED]);
        $this->assertUserNotAdopted('User1');
        static::assertLogRegexCount(1, '/kc\.user1 User One: email user1@example\.com already used by the HumHub user User1 \(ID 2, user source "local"\), not adopted: the email is not verified on Keycloak$/m');

        // The unverified email is ignored
        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => ConfigureForm::ADOPT_USERS_SAFE, 'trustOnlyVerifiedEmail' => true]);
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertUserNotAdopted('User1');
    }

    public function testSystemAdministratorsAreAdoptedOnlyIfLinked()
    {
        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => ConfigureForm::ADOPT_USERS_SAFE]);
        // The email of the fixture user "Admin", a system administrator (verified on Keycloak)
        $keycloakUserId = KeycloakTestServer::createUser(['username' => 'kc.admin', 'email' => 'admin@example.com', 'firstName' => 'Admin', 'lastName' => 'Tester']);

        static::logInitialize();
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_SKIPPED]);
        $this->assertUserNotAdopted('Admin');
        static::assertLogRegexCount(1, '/kc\.admin Admin Tester: email admin@example\.com already used by the HumHub user Admin \(ID 1, user source "local"\), not adopted: system administrators are adopted only if already linked to Keycloak$/m');

        // Linked by signing in with Keycloak (or by connecting Keycloak in the account settings)
        $this->linkUser(User::findOne(['username' => 'Admin']), $keycloakUserId);
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertSame(KeycloakUserSource::SOURCE_ID, User::findOne(['username' => 'Admin'])->user_source);
        static::assertLogRegexCount(1, '/^Users sync: adopted the HumHub user Admin \(1, previous user source: "local"\)\. This user is a system administrator\.$/', Logger::LEVEL_WARNING);
    }

    public function testTechnicalAccountsAreNotAdopted()
    {
        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => ConfigureForm::ADOPT_USERS_SAFE, 'apiUsername' => 'kc.api']);
        // The API user, having the email of the fixture user "User1"
        KeycloakTestServer::createUser(['username' => 'kc.api', 'email' => 'user1@example.com', 'firstName' => 'Api', 'lastName' => 'User']);
        // Service accounts (not listed by the Keycloak API): one linked to "User2", one having the email of "User3"
        $this->linkUser(User::findOne(['username' => 'User2']), 'service-account-linked');
        Yii::$container->set(KeycloakApi::class, new class () extends KeycloakApi {
            public function getAllUsers(?bool &$isComplete = null, ?callable $onProgress = null): ?array
            {
                $users = parent::getAllUsers($isComplete, $onProgress);
                foreach (['linked' => 'sa@keycloak.test', 'email' => 'user3@example.com'] as $name => $email) {
                    $users[] = ['id' => 'service-account-' . $name, 'username' => 'service-account-' . $name, 'email' => $email, 'emailVerified' => true, 'enabled' => true, 'serviceAccountClientId' => $name];
                }
                return $users;
            }
        });
        try {
            $results = $this->getUserSource()->syncUsers();
        } finally {
            Yii::$container->clear(KeycloakApi::class);
        }

        $this->assertSame(0, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_CREATED]);
        $this->assertSame(3, $results[KeycloakUserSource::SYNC_SKIPPED]);
        foreach (['User1', 'User2', 'User3'] as $username) {
            $this->assertSame('local', User::findOne(['username' => $username])->user_source, $username);
        }
    }

    public function testFailedAdoptionByEmailIsRolledBack()
    {
        // The required last name is mapped to an attribute missing on Keycloak, so the user cannot be updated
        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => ConfigureForm::ADOPT_USERS_SAFE, 'attributeMapping' => 'lastname=missingAttribute']);
        KeycloakTestServer::createUser(['username' => 'kc.user1', 'email' => 'user1@example.com', 'firstName' => 'User', 'lastName' => 'One']);
        $lastname = User::findOne(['username' => 'User1'])->profile->lastname;

        static::logInitialize();
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_FAILED]);
        $this->assertUserNotAdopted('User1');
        $this->assertSame($lastname, User::findOne(['username' => 'User1'])->profile->lastname);
        static::assertLogRegexCount(1, '/kc\.user1 User One: could not adopt the HumHub user of the user source "local": could not update the HumHub user User1 \(ID 2\)/');
    }

    public function testAdoptedUsersRemainSyncedWhenTheOptionIsDisabled()
    {
        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => ConfigureForm::ADOPT_USERS_SAFE]);
        $keycloakUserId = KeycloakTestServer::createUser(['username' => 'kc.user1', 'email' => 'user1@example.com', 'firstName' => 'User', 'lastName' => 'One']);
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_ADOPTED]);

        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => ConfigureForm::ADOPT_USERS_NONE]);
        KeycloakTestServer::updateUser($keycloakUserId, ['firstName' => 'Changed']);
        // The email of the fixture user "User2"
        KeycloakTestServer::createUser(['username' => 'kc.user2', 'email' => 'user2@example.com', 'firstName' => 'User', 'lastName' => 'Two']);

        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_UPDATED]);
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_SKIPPED]);
        $this->assertSame('Changed', User::findOne(['username' => 'User1'])->profile->firstname);
        $this->assertUserNotAdopted('User2');
    }

    public function testAdoptExistingUsersRequiresTheUsersSync()
    {
        $form = new ConfigureForm();
        $form->syncUsers = false;
        $form->adoptExistingUsers = ConfigureForm::ADOPT_USERS_TRUSTED;
        $form->save();
        $this->assertFalse((new ConfigureForm())->isAdoptingExistingUsers());

        // Without the API parameters, the users sync is disabled
        $form = new ConfigureForm();
        $form->clientSecret = '';
        $form->syncUsers = true;
        $form->adoptExistingUsers = ConfigureForm::ADOPT_USERS_SAFE;
        $form->save();
        $form = new ConfigureForm();
        $this->assertFalse($form->syncUsers);
        $this->assertFalse($form->isAdoptingExistingUsers());
    }

    public function testAllMatchesAreAdoptedIfKeycloakIsTrusted()
    {
        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => ConfigureForm::ADOPT_USERS_TRUSTED]);
        // Email not verified on Keycloak
        KeycloakTestServer::createUser(['username' => 'kc.user1', 'email' => 'user1@example.com', 'emailVerified' => false, 'firstName' => 'User', 'lastName' => 'One']);
        // System administrator
        KeycloakTestServer::createUser(['username' => 'kc.admin', 'email' => 'admin@example.com', 'firstName' => 'Admin', 'lastName' => 'Tester']);
        // Deactivated on HumHub: "DisabledUser" by email, "AdminNotMember" linked
        KeycloakTestServer::createUser(['username' => 'kc.disabled', 'email' => 'disabled@example.com', 'firstName' => 'Disabled', 'lastName' => 'User']);
        User::findOne(['username' => 'AdminNotMember'])->updateAttributes(['status' => User::STATUS_DEACTIVATED]);
        $this->linkUser(User::findOne(['username' => 'AdminNotMember']), KeycloakTestServer::createUser(['username' => 'kc.deactivated', 'email' => 'deactivated@keycloak.test', 'firstName' => 'Deactivated', 'lastName' => 'User']));
        // Still not adopted: awaiting approval on HumHub
        KeycloakTestServer::createUser(['username' => 'kc.unapproved', 'email' => 'unapproved@example.com', 'firstName' => 'Unapproved', 'lastName' => 'User']);

        static::logInitialize();
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(4, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertSame(1, $results[KeycloakUserSource::SYNC_SKIPPED]);
        foreach (['User1', 'Admin', 'DisabledUser', 'AdminNotMember'] as $username) {
            $user = User::findOne(['username' => $username]);
            $this->assertSame(KeycloakUserSource::SOURCE_ID, $user->user_source, $username);
            $this->assertSame(User::STATUS_ENABLED, (int)$user->status, $username);
        }
        $this->assertUserNotAdopted('UnapprovedUser');
        static::assertLogRegexCount(1, '/^Users sync: adopted the HumHub user Admin \(1, previous user source: "local"\)\. This user is a system administrator\.$/', Logger::LEVEL_WARNING);
        static::assertLogRegexCount(1, '/^Users sync: adopted the HumHub user DisabledUser \(5, previous user source: "local"\) and enabled it$/', Logger::LEVEL_INFO);

        // Unless the unverified emails are ignored
        KeycloakTestServer::createUser(['username' => 'kc.user2', 'email' => 'user2@example.com', 'emailVerified' => false, 'firstName' => 'User', 'lastName' => 'Two']);
        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => ConfigureForm::ADOPT_USERS_TRUSTED, 'trustOnlyVerifiedEmail' => true]);
        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertUserNotAdopted('User2');
    }

    public function testLinkedUsersOfAnotherSourceAreAdopted()
    {
        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => ConfigureForm::ADOPT_USERS_SAFE, 'updateHumhubEmailFromBrokerEmail' => true]);
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
            'adoptExistingUsers' => ConfigureForm::ADOPT_USERS_SAFE,
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
        $this->configureModule(['syncUsers' => true, 'adoptExistingUsers' => ConfigureForm::ADOPT_USERS_SAFE]);
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
        // Deactivated on HumHub (e.g. by an admin): "DisabledUser" by email, "AdminNotMember" linked
        KeycloakTestServer::createUser(['username' => 'kc.disabled', 'email' => 'disabled@example.com', 'firstName' => 'Disabled', 'lastName' => 'User']);
        User::findOne(['username' => 'AdminNotMember'])->updateAttributes(['status' => User::STATUS_DEACTIVATED]);
        $this->linkUser(User::findOne(['username' => 'AdminNotMember']), KeycloakTestServer::createUser(['username' => 'kc.deactivated', 'email' => 'deactivated@keycloak.test', 'firstName' => 'Deactivated', 'lastName' => 'User']));

        $results = $this->getUserSource()->syncUsers();
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_ADOPTED]);
        $this->assertSame(0, $results[KeycloakUserSource::SYNC_CREATED]);
        $this->assertSame(6, $results[KeycloakUserSource::SYNC_SKIPPED]);
        foreach (['User1', 'UnapprovedUser', 'User2', 'User3', 'DisabledUser', 'AdminNotMember'] as $username) {
            $this->assertSame('local', User::findOne(['username' => $username])->user_source, $username);
        }
        foreach (['DisabledUser', 'AdminNotMember'] as $username) {
            $this->assertSame(User::STATUS_DEACTIVATED, (int)User::findOne(['username' => $username])->status, $username);
        }
    }

    private function assertUserNotAdopted(string $username): void
    {
        $user = User::findOne(['username' => $username]);
        $this->assertSame('local', $user->user_source, $username);
        $this->assertFalse(Auth::find()->where(['source' => Keycloak::DEFAULT_NAME, 'user_id' => $user->id])->exists(), $username);
    }
}

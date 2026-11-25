<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.app](https://www.cuzy.app)
 */

namespace tests\codeception\unit\modules\authKeycloak;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\source\KeycloakUserSource;
use humhub\modules\user\controllers\AuthController;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\User;
use humhub\modules\user\services\AuthClientService;
use humhub\modules\user\services\AutoMapResult;
use humhub\modules\user\services\UserSourceService;
use tests\codeception\_support\HumHubDbTestCase;
use Yii;

/**
 * A Keycloak sign-in must only find the HumHub user linked to its `sub` (or, through the module's guarded
 * email fallback, a user not yet linked to another Keycloak identity), never another user's account.
 */
class KeycloakUserLookupTest extends HumHubDbTestCase
{
    private const VICTIM_ID = 3;
    private const VICTIM_EMAIL = 'user2@example.com';
    private const SUB_EXISTING = 'b1c2d3e4-0000-4000-8000-000000000001';
    private const SUB_UNLINKED = 'c9d8e7f6-0000-4000-8000-000000000002';
    private const SUB_OTHER = 'de305d54-75b4-431b-adb2-eb6b9e546014';
    private const SUB_NUMERIC_PREFIX = '3fa85f64-5717-4562-b3fc-2c963f66afa6';

    protected function setUp(): void
    {
        parent::setUp();

        $settings = Yii::$app->getModule('auth-keycloak')->settings;
        $settings->set('usernameMapper', 'preferred_username');
        // Managed attributes: a wrong login would overwrite the victim's email and username
        $settings->set('updateHumhubEmailFromBrokerEmail', true);
        $settings->set('updateHumhubUsernameFromBrokerUsername', true);
        UserSourceService::getCollection()->setUserSource(KeycloakUserSource::SOURCE_ID, ['class' => KeycloakUserSource::class]);
        Auth::deleteAll(['source' => Keycloak::DEFAULT_NAME]);
        static::logout();
    }

    /**
     * Subs that MariaDB casts to the victim's ID (3) when compared with `user.id`
     */
    public static function victimIdSubProvider(): array
    {
        return [
            'digit then letter' => ['3fa85f64-5717-4562-b3fc-2c963f66afa6'],
            'scientific notation (3e00 = 3)' => ['3e00f64a-5717-4562-b3fc-2c963f66afa6'],
            'leading zero' => ['03fa85f6-5717-4562-b3fc-2c963f66afa6'],
            'leading zeros' => ['00000003-5717-4562-b3fc-2c963f66afa6'],
            'non-UUID sub equal to the ID' => ['3'],
        ];
    }

    private function setKeycloakSource(int $userId): void
    {
        User::updateAll(['user_source' => KeycloakUserSource::SOURCE_ID], ['id' => $userId]);
    }

    private function insertAuth(int $userId, string $sourceId): void
    {
        Yii::$app->db->createCommand()->insert('user_auth', [
            'user_id' => $userId,
            'source' => Keycloak::DEFAULT_NAME,
            'source_id' => $sourceId,
        ])->execute();
    }

    private function createClient(string $sub, string $email): Keycloak
    {
        $client = new Keycloak(['clientId' => 'humhub', 'clientSecret' => 'secret']);
        $client->setUserAttributes([
            'sub' => $sub,
            'email' => $email,
            'preferred_username' => 'newkeycloakuser',
            'given_name' => 'New',
            'family_name' => 'User',
        ]);
        return $client;
    }

    private function createVictim(): void
    {
        $this->setKeycloakSource(self::VICTIM_ID);
        $this->insertAuth(self::VICTIM_ID, self::SUB_EXISTING);
    }

    /**
     * Runs the end of the core sign-in (what `AuthAction` calls after a successful Keycloak authentication)
     */
    private function signIn(Keycloak $client): void
    {
        $controller = new AuthController('auth', Yii::$app->getModule('user'));
        $controller->onAuthSuccess($client);
    }

    private function assertVictimUntouched(): void
    {
        $victim = User::findOne(self::VICTIM_ID);
        $this->assertSame(self::VICTIM_EMAIL, $victim->email);
        $this->assertSame('User2', $victim->username);
        $this->assertSame(
            [self::SUB_EXISTING],
            Auth::find()->select('source_id')->where(['user_id' => self::VICTIM_ID, 'source' => Keycloak::DEFAULT_NAME])->column(),
        );
    }

    /**
     * @dataProvider victimIdSubProvider
     */
    public function testUnknownSubDoesNotMatchUserWithSameNumericId(string $sub): void
    {
        $this->createVictim();

        $client = $this->createClient($sub, 'unknown@example.com');
        $service = new AuthClientService($client);

        $this->assertNull($service->getUser());
        $this->assertNotSame(AutoMapResult::Mapped, $service->autoMapToExistingUser());
        $this->assertNull(Auth::findOne(['source' => Keycloak::DEFAULT_NAME, 'source_id' => $sub]));
        $this->assertVictimUntouched();
    }

    /**
     * @dataProvider victimIdSubProvider
     */
    public function testSignInWithUnknownSubIsNotLoggedInAsUserWithSameNumericId(string $sub): void
    {
        $this->createVictim();

        $this->signIn($this->createClient($sub, 'unknown@example.com'));

        $this->assertVictimUntouched();
        $this->assertNotEquals(self::VICTIM_ID, Yii::$app->user->id);
        // The new Keycloak user got its own account
        $newUserId = Auth::findOne(['source' => Keycloak::DEFAULT_NAME, 'source_id' => $sub])?->user_id;
        $this->assertNotNull($newUserId);
        $this->assertNotEquals(self::VICTIM_ID, $newUserId);
        $this->assertSame('unknown@example.com', User::findOne($newUserId)->email);
    }

    public function testEmailOfUserLinkedToAnotherSubIsNotAutoLinked(): void
    {
        $this->createVictim();

        $client = $this->createClient(self::SUB_OTHER, self::VICTIM_EMAIL);
        $service = new AuthClientService($client);

        $this->assertSame(AutoMapResult::Denied, $service->autoMapToExistingUser());
        $this->assertNull($service->getUser());
        $this->assertNull(Auth::findOne(['source' => Keycloak::DEFAULT_NAME, 'source_id' => self::SUB_OTHER]));
        $this->assertVictimUntouched();
    }

    public function testSignInWithEmailOfUserLinkedToAnotherSubIsNotLoggedIn(): void
    {
        $this->createVictim();

        $this->signIn($this->createClient(self::SUB_OTHER, self::VICTIM_EMAIL));

        $this->assertVictimUntouched();
        $this->assertTrue(Yii::$app->user->isGuest);
        $this->assertNull(Auth::findOne(['source' => Keycloak::DEFAULT_NAME, 'source_id' => self::SUB_OTHER]));
    }

    public function testUserLinkedToSubIsFound(): void
    {
        $this->createVictim();

        $client = $this->createClient(self::SUB_EXISTING, self::VICTIM_EMAIL);
        $service = new AuthClientService($client);

        $this->assertSame(self::VICTIM_ID, $service->getUser()?->id);
        $this->assertSame(AutoMapResult::Mapped, $service->autoMapToExistingUser());
    }

    public function testSignInWithLinkedSubIsLoggedIn(): void
    {
        $this->createVictim();

        $this->signIn($this->createClient(self::SUB_EXISTING, self::VICTIM_EMAIL));

        $this->assertSame(self::VICTIM_ID, Yii::$app->user->id);
    }

    public function testUnlinkedKeycloakUserIsLinkedByEmail(): void
    {
        $this->setKeycloakSource(self::VICTIM_ID);

        $client = $this->createClient(self::SUB_UNLINKED, self::VICTIM_EMAIL);
        $service = new AuthClientService($client);

        $this->assertSame(AutoMapResult::Mapped, $service->autoMapToExistingUser());
        $this->assertSame(self::VICTIM_ID, $service->getUser()?->id);
        $this->assertSame(self::VICTIM_ID, Auth::findOne(['source' => Keycloak::DEFAULT_NAME, 'source_id' => self::SUB_UNLINKED])?->user_id);
    }

    public function testUserSourceIdDiffersFromAuthClientId(): void
    {
        $sourceId = UserSourceService::getCollection()->getUserSource(KeycloakUserSource::SOURCE_ID)->getId();
        $clientId = $this->createClient(self::SUB_NUMERIC_PREFIX, 'unknown@example.com')->getId();

        $this->assertNotSame(strtolower($clientId), strtolower($sourceId));
    }

    public function testKeycloakUserSourceClaimsKeycloakAuthClient(): void
    {
        $source = UserSourceService::getCollection()->findUserSourceForAuthClient(Keycloak::DEFAULT_NAME);

        $this->assertInstanceOf(KeycloakUserSource::class, $source);
        $this->assertSame(KeycloakUserSource::SOURCE_ID, $source->getId());
    }

    public function testEmailAutoLinkIsDisabled(): void
    {
        $this->assertFalse(UserSourceService::getCollection()->getUserSource(KeycloakUserSource::SOURCE_ID)->allowEmailAutoLink());
    }
}

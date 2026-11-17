<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 */

namespace humhub\modules\authKeycloak\tests\codeception\_support;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\Module;
use humhub\modules\authKeycloak\source\KeycloakUserSource;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\User;
use humhub\modules\user\services\UserSourceService;
use tests\codeception\_support\HumHubDbTestCase;
use Yii;

/**
 * Base class of the tests running against the Keycloak test server: the test realm is recreated
 * and the module is configured to use it before each test.
 * The tests are skipped if Keycloak is not available (see {@see KeycloakTestServer}).
 */
abstract class KeycloakTestCase extends HumHubDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (($reason = KeycloakTestServer::getUnavailableReason()) !== null) {
            $this->markTestSkipped('Keycloak is not available: ' . $reason);
        }

        KeycloakTestServer::resetRealm();
        // Not reset by the fixtures, and the Keycloak users of the previous tests do not exist anymore
        Auth::deleteAll(['source' => Keycloak::DEFAULT_NAME]);
        $this->configureModule();
    }

    /**
     * @param array $settings module settings overriding the defaults
     */
    protected function configureModule(array $settings = []): void
    {
        $settings = array_merge([
            'enabled' => true,
            'baseUrl' => KeycloakTestServer::getUrl(),
            'realm' => KeycloakTestServer::REALM,
            'clientId' => KeycloakTestServer::CLIENT_ID,
            'clientSecret' => KeycloakTestServer::CLIENT_SECRET,
            'apiAuthMethod' => ConfigureForm::API_AUTH_METHOD_SERVICE_ACCOUNT,
            'apiUsername' => '',
            'attributeMapping' => '',
            'syncUsers' => false,
            'adoptExistingUsers' => ConfigureForm::ADOPT_USERS_NONE,
            'groupsSyncMode' => ConfigureForm::GROUP_SYNC_MODE_NONE,
            'trustOnlyVerifiedEmail' => false,
            'updateHumhubUsernameFromBrokerUsername' => false,
            'updateHumhubEmailFromBrokerEmail' => false,
        ], $settings);

        foreach ($settings as $name => $value) {
            $this->getKeycloakModule()->settings->set($name, $value);
        }

        // The auth client reads the settings when created
        Yii::$app->authClientCollection->setClient(Keycloak::DEFAULT_NAME, [
            'class' => Keycloak::class,
            'clientId' => KeycloakTestServer::CLIENT_ID,
            'clientSecret' => KeycloakTestServer::CLIENT_SECRET,
        ]);
    }

    protected function getKeycloakModule(): Module
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');
        return $module;
    }

    protected function getAuthClient(): Keycloak
    {
        /** @var Keycloak $authClient */
        $authClient = Yii::$app->authClientCollection->getClient(Keycloak::DEFAULT_NAME);
        return $authClient;
    }

    protected function getUserSource(): KeycloakUserSource
    {
        /** @var KeycloakUserSource $source */
        $source = UserSourceService::getCollection()->getUserSource(KeycloakUserSource::SOURCE_ID);
        return $source;
    }

    /**
     * Links a HumHub user to a Keycloak user, as on the first sign in
     */
    protected function linkUser(User $user, string $keycloakUserId): void
    {
        (new Auth(['user_id' => $user->id, 'source' => Keycloak::DEFAULT_NAME, 'source_id' => $keycloakUserId]))->save();
    }
}

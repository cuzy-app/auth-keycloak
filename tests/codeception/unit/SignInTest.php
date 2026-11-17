<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 */

namespace humhub\modules\authKeycloak\tests\codeception\unit;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\tests\codeception\_support\KeycloakTestCase;
use humhub\modules\authKeycloak\tests\codeception\_support\KeycloakTestServer;
use humhub\modules\user\services\AuthClientService;
use Yii;

/**
 * Sign in without browser: the tokens are requested with the "password" grant, then the user attributes are
 * fetched from the userinfo endpoint by the auth client, as after the authorization code flow.
 */
class SignInTest extends KeycloakTestCase
{
    public function testUserinfoAttributesAreMappedAndSynced()
    {
        $this->configureModule(['attributeMapping' => "username=preferred_username\n" . 'phone_work=' . KeycloakTestServer::USERINFO_ATTRIBUTE]);
        $keycloakUserId = KeycloakTestServer::createUser([
            'username' => 'kc.dave',
            'email' => 'dave@keycloak.test',
            'firstName' => 'Dave',
            'lastName' => 'Keycloak',
            'attributes' => [KeycloakTestServer::USERINFO_ATTRIBUTE => ['+49 444']],
        ]);

        $authClient = $this->signIn('kc.dave');
        $attributes = $authClient->getUserAttributes();
        $this->assertSame($keycloakUserId, $attributes['id']);
        $this->assertSame('kc.dave', $attributes['username']);
        $this->assertSame('dave@keycloak.test', $attributes['email']);
        $this->assertSame('+49 444', $attributes['phone_work']);

        // Account creation with the mapped attributes
        $user = $this->getUserSource()->createUser($attributes);
        $this->assertNotNull($user);
        $this->assertSame('kc.dave', $user->username);
        $this->assertSame('+49 444', $user->profile->phone_work);
        $this->linkUser($user, $keycloakUserId);

        // Next sign in: the mapped profile fields are updated
        KeycloakTestServer::updateUser($keycloakUserId, ['attributes' => [KeycloakTestServer::USERINFO_ATTRIBUTE => ['+49 555']]]);
        $authClient = $this->signIn('kc.dave');
        $this->assertTrue((new AuthClientService($authClient))->updateUser($user));
        $user->profile->refresh();
        $this->assertSame('+49 555', $user->profile->phone_work);
    }

    private function signIn(string $username): Keycloak
    {
        // A new auth client instance, as the user attributes are cached
        $this->configureModule([
            'attributeMapping' => $this->getKeycloakModule()->settings->get('attributeMapping'),
        ]);
        $authClient = $this->getAuthClient();
        $authClient->setAccessToken(['params' => KeycloakTestServer::getUserTokens($username)]);
        return $authClient;
    }
}

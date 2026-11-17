<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 */

namespace humhub\modules\authKeycloak\tests\codeception\unit;

use humhub\modules\authKeycloak\components\KeycloakApi;
use humhub\modules\authKeycloak\tests\codeception\_support\KeycloakTestCase;
use humhub\modules\authKeycloak\tests\codeception\_support\KeycloakTestServer;

class ApiConnectionTest extends KeycloakTestCase
{
    public function testConnected()
    {
        $this->assertTrue((new KeycloakApi())->isConnected());
    }

    /**
     * The token is issued, but the Keycloak API rejects it (HTTP 401 Unauthorized): the library returns the error
     * response instead of throwing an exception
     */
    public function testNotConnectedIfTheApiRejectsTheToken()
    {
        // Tokens issued before "not before" are rejected, including the new ones
        // (decoded as objects, as empty JSON objects must stay objects)
        $realm = KeycloakTestServer::request('GET', '', null, [200], false);
        $realm->notBefore = time() + 3600;
        KeycloakTestServer::request('PUT', '', $realm);

        $this->assertFalse((new KeycloakApi())->isConnected());
    }

    public function testNotConnectedWithAWrongClientSecret()
    {
        $this->configureModule(['clientSecret' => 'wrong-secret']);

        $this->assertFalse((new KeycloakApi())->isConnected());
    }
}

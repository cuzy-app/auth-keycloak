<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 */

namespace humhub\modules\authKeycloak\tests\codeception\unit;

use humhub\modules\authKeycloak\commands\KeycloakController;
use humhub\modules\authKeycloak\tests\codeception\_support\KeycloakTestCase;
use humhub\modules\authKeycloak\tests\codeception\_support\KeycloakTestServer;
use yii\console\ExitCode;

class ShowUserCommandTest extends KeycloakTestCase
{
    public function testShowUser()
    {
        $this->configureModule(['attributeMapping' => "username=preferred_username\nphone_work=phoneNumber"]);
        $keycloakUserId = KeycloakTestServer::createUser([
            'username' => 'kc.carol',
            'email' => 'carol@keycloak.test',
            'firstName' => 'Carol',
            'lastName' => 'Keycloak',
            'attributes' => ['phoneNumber' => ['+49 333'], 'department' => ['Sales']],
        ]);

        // By Keycloak username, ID and email
        foreach (['kc.carol', $keycloakUserId, 'carol@keycloak.test'] as $search) {
            [$exitCode, $output] = $this->runShowUser($search);
            $this->assertSame(ExitCode::OK, $exitCode, $output);
            $this->assertStringContainsString($keycloakUserId, $output);
            $this->assertMatchesRegularExpression('/department\s+│ Sales/', $output);
            $this->assertMatchesRegularExpression('/phone_work\s+│ phoneNumber\s+│ \+49 333/', $output);
            $this->assertStringContainsString('no linked HumHub user', $output);
        }

        [$exitCode, $output] = $this->runShowUser('unknown');
        $this->assertSame(ExitCode::UNSPECIFIED_ERROR, $exitCode);
        $this->assertStringContainsString('no Keycloak user found', $output);
    }

    /**
     * @return array [exit code, output]
     */
    private function runShowUser(string $user): array
    {
        $controller = new class ('auth-keycloak', $this->getKeycloakModule()) extends KeycloakController {
            public string $output = '';

            public function stdout($string)
            {
                $this->output .= $string;
                return strlen($string);
            }

            public function stderr($string)
            {
                return $this->stdout($string);
            }
        };

        ob_start();
        $exitCode = $controller->actionShowUser($user);
        $controller->output .= ob_get_clean();

        return [$exitCode, $controller->output];
    }
}

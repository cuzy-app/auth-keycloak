<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 */

namespace humhub\modules\authKeycloak\tests\codeception\unit;

use m261016_120000_move_username_mapper_to_attribute_mapping;
use tests\codeception\_support\HumHubDbTestCase;
use Yii;

class MigrationTest extends HumHubDbTestCase
{
    public function testCustomUsernameMapperIsMoved()
    {
        $this->setSettings(['usernameMapper' => 'preferred_username', 'attributeMapping' => 'phone_work=phoneNumber']);
        $this->migrate();

        $this->assertNull($this->getSettings()->get('usernameMapper'));
        $this->assertSame("username=preferred_username\nphone_work=phoneNumber", $this->getSettings()->get('attributeMapping'));
    }

    public function testDefaultUsernameMapperIsRemoved()
    {
        $this->setSettings(['usernameMapper' => 'sub', 'attributeMapping' => 'phone_work=phoneNumber']);
        $this->migrate();

        $this->assertNull($this->getSettings()->get('usernameMapper'));
        $this->assertSame('phone_work=phoneNumber', $this->getSettings()->get('attributeMapping'));
    }

    private function migrate(): void
    {
        require_once dirname(__DIR__, 3) . '/migrations/m261016_120000_move_username_mapper_to_attribute_mapping.php';
        ob_start();
        (new m261016_120000_move_username_mapper_to_attribute_mapping())->safeUp();
        ob_end_clean();
        // The migration uses its own settings manager
        $this->getSettings()->reload();
    }

    private function setSettings(array $settings): void
    {
        foreach ($settings as $name => $value) {
            $this->getSettings()->set($name, $value);
        }
    }

    private function getSettings()
    {
        return Yii::$app->getModule('auth-keycloak')->settings;
    }
}

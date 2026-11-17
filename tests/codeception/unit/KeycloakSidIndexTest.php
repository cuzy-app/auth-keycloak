<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace tests\codeception\unit\modules\authKeycloak;

use tests\codeception\_support\HumHubDbTestCase;
use Yii;

class KeycloakSidIndexTest extends HumHubDbTestCase
{
    public function testKeycloakSidIsIndexed(): void
    {
        $names = [];
        foreach (Yii::$app->db->createCommand('SHOW INDEX FROM `user_auth`')->queryAll() as $row) {
            $names[$row['Key_name']][] = $row['Column_name'];
        }
        $this->assertSame(['keycloak_sid'], $names['idx-user_auth-keycloak_sid'] ?? null);
    }
}

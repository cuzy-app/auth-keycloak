<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.app](https://www.cuzy.app)
 */

namespace tests\codeception\unit\modules\authKeycloak;

use humhub\components\Migration;
use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\source\KeycloakUserSource;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\User;
use tests\codeception\_support\HumHubDbTestCase;
use Yii;

/**
 * Keycloak users end with the `keycloak_source` user source, whether they come from a pre-1.6 install
 * (backfill, then rename) or from a 1.6.0 - 1.6.5 install (`Keycloak` user source).
 */
class UserSourceMigrationTest extends HumHubDbTestCase
{
    private function migration(string $name): Migration
    {
        require_once Yii::getAlias('@authkeycloak/migrations/' . $name . '.php');
        return new $name(['db' => Yii::$app->db, 'compact' => true]);
    }

    private function userSource(int $userId): string
    {
        return User::find()->select('user_source')->where(['id' => $userId])->scalar();
    }

    public function testUpgradeFromBefore16(): void
    {
        Auth::deleteAll(['source' => Keycloak::DEFAULT_NAME]);
        Yii::$app->db->createCommand()->insert('user_auth', [
            'user_id' => 2,
            'source' => Keycloak::DEFAULT_NAME,
            'source_id' => 'kc-user1',
        ])->execute();

        ob_start();
        $this->migration('m260515_120000_backfill_user_source')->safeUp();
        $this->migration('m261125_120000_rename_user_source')->safeUp();
        ob_end_clean();

        $this->assertSame(KeycloakUserSource::SOURCE_ID, $this->userSource(2));
        $this->assertSame('local', $this->userSource(3));
    }

    public function testRenameUpAndDown(): void
    {
        User::updateAll(['user_source' => 'Keycloak'], ['id' => 2]);
        $migration = $this->migration('m261125_120000_rename_user_source');

        ob_start();
        $migration->safeUp();
        ob_end_clean();
        $this->assertSame(KeycloakUserSource::SOURCE_ID, $this->userSource(2));
        $this->assertSame('local', $this->userSource(3));

        ob_start();
        $migration->safeDown();
        ob_end_clean();
        $this->assertSame('Keycloak', $this->userSource(2));
        $this->assertSame('local', $this->userSource(3));
    }
}

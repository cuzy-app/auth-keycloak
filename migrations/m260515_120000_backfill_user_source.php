<?php

use humhub\components\Migration;
use humhub\modules\authKeycloak\authclient\Keycloak;

/**
 * Claims existing Keycloak users for the new `KeycloakUserSource`.
 *
 * Before HumHub 1.19, Keycloak users were identified by `user.auth_mode = 'Keycloak'`.
 * Core's 1.19 migration drops the `auth_mode` column and resets every user to
 * `user_source = 'local'`. This migration walks the (already-populated)
 * `user_auth` table and reclaims any user with a Keycloak auth row, so the
 * UserSource registered by this module owns them on first login post-upgrade.
 */
class m260515_120000_backfill_user_source extends Migration
{
    public function safeUp()
    {
        $this->execute(
            'UPDATE {{%user}} u'
            . ' INNER JOIN {{%user_auth}} ua ON ua.user_id = u.id'
            . ' SET u.user_source = :source'
            . ' WHERE ua.source = :source'
            . '   AND u.user_source = :local',
            [
                ':source' => Keycloak::DEFAULT_NAME,
                ':local' => 'local',
            ],
        );
    }

    public function safeDown()
    {
        $this->execute(
            'UPDATE {{%user}} SET user_source = :local WHERE user_source = :source',
            [
                ':source' => Keycloak::DEFAULT_NAME,
                ':local' => 'local',
            ],
        );
    }
}

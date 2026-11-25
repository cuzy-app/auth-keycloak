<?php

use humhub\components\Migration;

/**
 * Renames the user source of Keycloak users from `Keycloak` to `keycloak_source`.
 *
 * The user source ID must differ from the auth client ID (`Keycloak`), otherwise core's
 * `AuthClientService::getUser()` looks the Keycloak `sub` up as a HumHub `user.id`.
 * See {@see \humhub\modules\authKeycloak\source\KeycloakUserSource::SOURCE_ID}.
 */
class m261125_120000_rename_user_source extends Migration
{
    public function safeUp()
    {
        $this->update('{{%user}}', ['user_source' => 'keycloak_source'], ['user_source' => 'Keycloak']);
    }

    public function safeDown()
    {
        $this->update('{{%user}}', ['user_source' => 'Keycloak'], ['user_source' => 'keycloak_source']);
    }
}

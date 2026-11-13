<?php

use humhub\components\Migration;

/**
 * The back-channel logout looks a user up by `user_auth.keycloak_sid` (a column of this module): index it.
 *
 * The `(source, source_id)` lookup of every login is on core columns and is left to a core index.
 */
class m261108_100000_add_keycloak_sid_index extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->safeCreateIndex('idx-user_auth-keycloak_sid', '{{%user_auth}}', 'keycloak_sid');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->safeDropIndex('idx-user_auth-keycloak_sid', '{{%user_auth}}');
    }
}

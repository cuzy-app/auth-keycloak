<?php

use humhub\components\Migration;
use humhub\components\SettingsManager;

/**
 * Moves the former `usernameMapper` setting into the profile fields mapping (`attributeMapping`).
 *
 * The username claim is now configured with a `username=<claim>` line of the mapping.
 * Without such a line, `sub` is used, as it was the default of `usernameMapper`.
 */
class m261016_120000_move_username_mapper_to_attribute_mapping extends Migration
{
    public function safeUp()
    {
        $settings = new SettingsManager(['moduleId' => 'auth-keycloak']);

        $usernameMapper = trim((string)$settings->get('usernameMapper'));
        if ($usernameMapper !== '' && $usernameMapper !== 'sub') {
            $attributeMapping = trim((string)$settings->get('attributeMapping'));
            $settings->set('attributeMapping', trim('username=' . $usernameMapper . "\n" . $attributeMapping));
        }
        $settings->delete('usernameMapper');
    }

    public function safeDown()
    {
        echo "m261016_120000_move_username_mapper_to_attribute_mapping cannot be reverted.\n";

        return false;
    }
}

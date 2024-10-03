<?php

use humhub\components\Migration;

class uninstall extends Migration
{
    public function up()
    {
        $this->safeDropColumn('{{%group}}', 'keycloak_id');
    }

    public function down()
    {
        echo "uninstall does not support migration down.\n";
        return false;
    }

}

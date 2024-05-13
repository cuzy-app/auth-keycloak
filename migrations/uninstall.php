<?php

use humhub\components\Migration;
use humhub\modules\user\models\Group;

class uninstall extends Migration
{

    public function up()
    {
        $tableSchema = Yii::$app->getDb()->getSchema()->getTableSchema(Group::tableName(), true);
        if (in_array('keycloak_id', $tableSchema->columnNames, true)) {
            $this->safeDropColumn('{{%group}}', 'keycloak_id');
        }
    }

    public function down()
    {
        echo "uninstall does not support migration down.\n";
        return false;
    }

}

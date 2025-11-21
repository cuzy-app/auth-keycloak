<?php

use humhub\components\Migration;
use humhub\modules\authKeycloak\Module;
use humhub\modules\user\models\Group;

/**
 * Class m220316_073759_initial
 */
class m220316_073759_initial extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableSchema = Yii::$app->getDb()->getSchema()->getTableSchema(Group::tableName(), true);
        if (!in_array('keycloak_id', $tableSchema->columnNames, true)) {
            $this->safeAddColumn('{{%group}}', 'keycloak_id', $this->string(36)->after('id'));
        }

        // Migration to version 0.5.0
        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');
        if ($module?->isEnabled) {
            $settings = $module->settings;
            $oldApiBaseUrl = $settings->get('apiBaseUrl');
            if ($oldApiBaseUrl) {
                $posRealms = strpos((string) $oldApiBaseUrl, '/realms/');
                $baseUrl = substr((string) $oldApiBaseUrl, 0, $posRealms);
                $realm = substr((string) $oldApiBaseUrl, $posRealms + 8, strpos((string) $oldApiBaseUrl, '/protocol/') - $posRealms - 8);
                $module->settings->set('baseUrl', $baseUrl);
                $module->settings->set('realm', $realm);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220316_073759_initial cannot be reverted.\n";

        return false;
    }
}

<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\models;

use humhub\modules\user\models\Group;
use yii\db\ActiveRecord;

/**
 * @inerhitdoc
 *
 * @property string $keycloak_id
 */
class GroupKeycloak extends Group
{
    /**
     * Get group only if this group exists on Keycloak
     * @param $id
     * @return array|GroupKeycloak|ActiveRecord|null
     */
    public static function getKeycloakGroup($id)
    {
        return self::find()
            ->andWhere(['id' => $id])
            ->andWhere(['not', ['keycloak_id' => null]])
            ->one();
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios['changeType'] = ['keycloak_id'];
        return $scenarios;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            [['keycloak_id'], 'string', 'max' => 36],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return array_merge(parent::attributeLabels(), [
            'keycloak_id' => 'Keycloak ID',
        ]);
    }
}

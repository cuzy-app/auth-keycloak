<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\jobs;

use humhub\modules\authKeycloak\components\KeycloakApi;
use humhub\modules\queue\ActiveJob;
use humhub\modules\user\models\User;
use Throwable;
use yii\base\InvalidConfigException;

class UpdateUserEmail extends ActiveJob
{
    /**
     * @var int
     */
    public $userId;

    /**
     * @inheritdoc
     * @return void
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function run()
    {
        $user = User::find()->active()->andWhere(['id' => $this->userId])->one();
        if ($user === null) {
            return;
        }

        (new KeycloakApi())->updateUserEmail($user);
    }
}

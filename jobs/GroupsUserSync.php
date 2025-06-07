<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\jobs;

use humhub\modules\authKeycloak\components\KeycloakApi;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\models\GroupKeycloak;
use humhub\modules\queue\ActiveJob;
use humhub\modules\user\models\User;
use Throwable;
use yii\base\InvalidConfigException;

class GroupsUserSync extends ActiveJob
{
    /**
     * @var int
     */
    public $userId;

    /**
     * @var KeycloakApi
     */
    protected $keycloakApi;

    /**
     * @var GroupKeycloak[]
     */
    protected $humhubGroupsByKeycloakId;

    /**
     * @inhertidoc
     * @var int maximum 1 hour
     */
    private $maxExecutionTime = 60 * 5;

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

        $config = new ConfigureForm();
        if (
            !$config->enabled
            || !$config->apiUsername
            || !$config->apiPassword
            || !$config->syncKeycloakGroupsToHumhub()
        ) {
            return;
        }

        $this->keycloakApi = new KeycloakApi();

        $this->humhubGroupsByKeycloakId = GroupKeycloak::find()
            ->where(['not', ['keycloak_id' => null]])
            ->indexBy('keycloak_id')
            ->all();

        // Add HumHub user to HumHub groups
        foreach ($this->keycloakApi->getUserGroups($user->id) as $keycloakGroupId) {
            if (!array_key_exists($keycloakGroupId, $this->humhubGroupsByKeycloakId)) {
                continue;
            }
            $humhubGroup = $this->humhubGroupsByKeycloakId[$keycloakGroupId];
            $humhubGroup->addUser($user);
        }
    }
}

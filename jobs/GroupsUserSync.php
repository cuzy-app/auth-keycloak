<?php
/**
 * * Keycloak Sign-In
 * @link https://github.com/cuzy-app/humhub-modules-auth-keycloak
 * @license https://github.com/cuzy-app/humhub-modules-auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\jobs;


use humhub\modules\authKeycloak\components\KeycloakApi;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\models\GroupKeycloak;
use humhub\modules\queue\ActiveJob;
use Throwable;
use yii\base\InvalidConfigException;
use yii\queue\RetryableJobInterface;


class GroupsUserSync extends ActiveJob implements RetryableJobInterface
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
    private $maxExecutionTime = 60 * 60;

    /**
     * @inheritdoc
     * @return void
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function run()
    {
        if (!$this->userId) {
            return;
        }

        $config = new ConfigureForm();
        if (!$config->enabled || !$config->syncKeycloakGroupsToHumhub()) {
            return;
        }

        $this->keycloakApi = new KeycloakApi();

        $this->humhubGroupsByKeycloakId = GroupKeycloak::find()
            ->where(['not', ['keycloak_id' => null]])
            ->indexBy('keycloak_id')
            ->all();

        // Add Humhub user to Humhub groups
        foreach ($this->keycloakApi->getUserGroups($this->userId) as $keycloakGroupId) {
            if (!array_key_exists($keycloakGroupId, $this->humhubGroupsByKeycloakId)) {
                continue;
            }
            $humhubGroup = $this->humhubGroupsByKeycloakId[$keycloakGroupId];
            $humhubGroup->addUser($this->userId);
        }
    }

    /**
     * @inheritDoc
     */
    public function getTtr()
    {
        return $this->maxExecutionTime;
    }

    /**
     * @inheritDoc for RetryableJobInterface
     */
    public function canRetry($attempt, $error)
    {
        return false;
    }
}

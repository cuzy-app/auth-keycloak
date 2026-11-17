<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.app](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\jobs;

use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\source\KeycloakUserSource;
use humhub\modules\queue\ActiveJob;
use humhub\modules\queue\interfaces\ExclusiveJobInterface;
use humhub\modules\user\services\UserSourceService;
use ReflectionClass;
use Yii;
use yii\queue\RetryableJobInterface;

/**
 * Creates, updates and deactivates the HumHub users from the Keycloak users
 * @see KeycloakUserSource::syncUsers()
 */
class UsersFullSync extends ActiveJob implements ExclusiveJobInterface, RetryableJobInterface
{
    /**
     * @var int maximum 1 hour
     */
    private $maxExecutionTime = 60 * 60;

    /**
     * @inheritdoc
     */
    public function run()
    {
        $config = new ConfigureForm();
        if (
            !$config->enabled
            || !$config->syncUsers
            || !$config->hasApiParams()
        ) {
            return;
        }

        $collection = UserSourceService::getCollection();
        if (!$collection->hasUserSource(KeycloakUserSource::SOURCE_ID)) {
            Yii::error('Users sync aborted: the Keycloak user source is disabled (`provideUserSource` module property)', 'auth-keycloak');
            return;
        }

        /** @var KeycloakUserSource $source */
        $source = $collection->getUserSource(KeycloakUserSource::SOURCE_ID);
        $source->syncUsers();
    }

    /**
     * @inheritDoc
     */
    public function getTtr()
    {
        return $this->maxExecutionTime;
    }

    /**
     * @inheritDoc
     */
    public function canRetry($attempt, $error)
    {
        $errorMessage = $error ? $error->getMessage() : '';
        Yii::error('Error with Users full sync job: ' . $errorMessage, 'auth-keycloak');
        return false;
    }

    /**
     * @inerhitdoc
     * Must not exceed to 50 chars
     */
    public function getExclusiveJobId()
    {
        return 'auth-keycloak.' . (new ReflectionClass($this))->getShortName();
    }
}

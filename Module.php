<?php
/**
 * * Keycloak Sign-In
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\authKeycloak;

use Yii;
use yii\helpers\Url;
use humhub\components\Module as BaseModule;

class Module extends BaseModule
{
    
    /**
     * @var string defines the icon
     */
    public $icon = 'sign-in';

    /**
     * @var string defines path for resources, including the screenshots path for the marketplace
     */
    public $resourcesPath = 'resources';

        /**
     * @inheritdoc
     */
    public function getConfigUrl()
    {
        return Url::to(['/auth-keycloak/admin']);
    }

    /**
     * @inheritdoc
     */
    public function disable()
    {
        // Cleanup all module data, don't remove the parent::disable()!!!
        parent::disable();
    }

    public function getName()
    {
        return 'Keycloak Sign-In';
    }

    public function getDescription()
    {
        return 'Integrating Keycloak Sign-In (OAuth 2.0)';
    }
}

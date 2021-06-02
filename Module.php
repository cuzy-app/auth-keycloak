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

class Module extends \humhub\components\Module
{
    
    /**
     * @var string defines the icon
     */
    public $icon = 'fa-sign-in';

    /**
     * @var string defines path for resources, including the screenshots path for the marketplace
     */
    public $resourcesPath = 'resources';


    public function getName()
    {
        return 'Keycloak Sign-In';
    }

    public function getDescription()
    {
        return 'Integrating Keycloak Sign-In (OAuth 2.0)';
    }
}

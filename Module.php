<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak;

use humhub\components\Module as BaseModule;
use yii\helpers\Url;

class Module extends BaseModule
{
    /**
     * @var string defines the icon
     */
    public $icon = 'sign-in';

    /**
     * @var bool When connecting to Keycloak API, check if SSL certificate is valid
     */
    public $apiVerifySsl = true;

    /**
     * @var int|float Maximum duration in seconds of a request to the Keycloak API (0 to wait indefinitely)
     */
    public $apiTimeout = 10;

    /**
     * @var int|float Maximum duration in seconds to connect to the Keycloak API (0 to wait indefinitely)
     */
    public $apiConnectTimeout = 5;

    /**
     * @inheritdoc
     */
    public function getConfigUrl()
    {
        return Url::to(['/auth-keycloak/config']);
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

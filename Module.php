<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
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
     * @var bool Register a dedicated UserSource for Keycloak-provisioned users.
     *
     * Default: true. Mirrors the pre-1.19 `PrimaryClient` semantic — users that
     * log in via Keycloak are owned by this module (locked profile attributes
     * per the `updateHumhubEmailFromBrokerEmail` / `updateHumhubUsernameFromBrokerUsername`
     * settings, password tab hidden, etc.).
     *
     * Set to false in `protected/config/common.php` to opt out — Keycloak users
     * then fall back to `LocalUserSource`. See `docs/CHANGELOG.md` 1.6.0 for the
     * trade-offs.
     *
     * @since 1.6.0
     */
    public bool $provideUserSource = true;

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

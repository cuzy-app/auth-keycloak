<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.app](https://www.cuzy.app)
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
     * @var int|float Maximum duration in seconds of a request to the Keycloak API from the console
     * (background jobs such as the users and groups sync, console commands), used instead of `apiTimeout`
     * as listing many users can take much longer (e.g. with LDAP user federation) (0 to wait indefinitely)
     */
    public $apiSyncTimeout = 600;

    /**
     * @var int Number of users per request when listing all the Keycloak users (users sync, `list-users` command)
     * or the members of a Keycloak group (groups sync).
     * With a user federation (e.g. LDAP), Keycloak may skip some users at the page boundaries, so larger pages
     * give a more complete list, but each request takes longer (see `apiSyncTimeout`)
     */
    public $usersListPageSize = 500;

    /**
     * @var int|float Maximum duration in seconds to connect to the Keycloak API (0 to wait indefinitely)
     */
    public $apiConnectTimeout = 5;

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

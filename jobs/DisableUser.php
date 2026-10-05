<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\jobs;

use humhub\modules\authKeycloak\components\KeycloakApi;
use humhub\modules\queue\ActiveJob;

/**
 * Deactivates a user's account on Keycloak
 * The Keycloak user ID is passed (and not the HumHub user ID) because the user's Keycloak Auth record
 * is removed when the user is deleted on HumHub
 */
class DisableUser extends ActiveJob
{
    /**
     * @var string
     */
    public $keycloakUserId;

    /**
     * @inheritdoc
     * @return void
     */
    public function run()
    {
        if (!$this->keycloakUserId) {
            return;
        }

        (new KeycloakApi())->disableUser($this->keycloakUserId);
    }
}

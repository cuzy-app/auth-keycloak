<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\source;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\Module;
use humhub\modules\user\models\forms\Registration;
use humhub\modules\user\models\User;
use humhub\modules\user\source\BaseUserSource;
use humhub\modules\user\source\UserSourceInterface;
use Yii;
use yii\helpers\VarDumper;

/**
 * KeycloakUserSource owns users provisioned through the Keycloak auth client.
 *
 * Replaces the pre-1.19 `PrimaryClient` marker that set `user.auth_mode = 'Keycloak'`.
 * Registered by {@see \humhub\modules\authKeycloak\Events::onUserSourceCollectionSet()}
 * when {@see Module::$provideUserSource} is enabled (default: true).
 *
 * @since 1.6.0
 */
class KeycloakUserSource extends BaseUserSource
{
    public const SOURCE_ID = Keycloak::DEFAULT_NAME;

    public function init()
    {
        parent::init();

        if ($this->id === '') {
            $this->id = self::SOURCE_ID;
        }
        if ($this->allowedAuthClientIds === []) {
            $this->allowedAuthClientIds = [self::SOURCE_ID];
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title !== '' ? $this->title : 'Keycloak';
    }

    /**
     * Managed attributes are derived from the existing module settings so
     * pre-1.19 sync configuration is preserved verbatim:
     *  - `updateHumhubEmailFromBrokerEmail`    → `email` is locked & synced
     *  - `updateHumhubUsernameFromBrokerUsername` → `username` is locked & synced
     *
     * Admins who previously disabled auto-sync keep the same behaviour and
     * the affected fields stay editable in the admin UI.
     */
    public function getManagedAttributes(): array
    {
        $module = $this->getKeycloakModule();
        if ($module === null) {
            return [];
        }

        $attrs = [];
        if ((bool) $module->settings->get('updateHumhubEmailFromBrokerEmail')) {
            $attrs[] = 'email';
        }
        if ((bool) $module->settings->get('updateHumhubUsernameFromBrokerUsername')) {
            $attrs[] = 'username';
        }

        return $attrs;
    }

    public function requiresApproval(?string $authClientId = null): bool
    {
        if ($authClientId !== null && in_array($authClientId, $this->trustedAuthClientIds, true)) {
            return false;
        }

        /** @var \humhub\modules\user\Module $userModule */
        $userModule = Yii::$app->getModule('user');
        return (bool) $userModule->settings->get('auth.needApproval');
    }

    public function getUsernameStrategy(): string
    {
        return UserSourceInterface::USERNAME_AUTO_GENERATE;
    }

    /**
     * Creates a HumHub user with `user_source = 'Keycloak'`.
     *
     * Note: the user_auth row is written by the auth client's
     * {@see Keycloak::createOrUpdateAuthRecord()} (which also stores
     * `keycloak_sid`). It is NOT created here.
     */
    public function createUser(array $attributes): ?User
    {
        $registration = $this->buildRegistration($attributes);
        if ($registration === null) {
            return null;
        }

        $registration->getUser()->user_source = $this->getId();

        if (!$registration->register()) {
            Yii::warning(
                'KeycloakUserSource: could not create user. Errors: '
                . VarDumper::dumpAsString($registration->getErrors()),
                'auth-keycloak',
            );
            return null;
        }

        return $registration->getUser();
    }

    private function buildRegistration(array $attributes): ?Registration
    {
        $registration = new Registration(enableEmailField: true, enablePasswordForm: false);
        $registration->enableUserApproval = $this->requiresApproval(self::SOURCE_ID);

        unset(
            $attributes['id'],
            $attributes['guid'],
            $attributes['contentcontainer_id'],
            $attributes['user_source'],
            $attributes['status'],
        );

        $registration->getUser()->setAttributes($attributes, false);
        $registration->getProfile()->setAttributes($attributes, false);
        $registration->getGroupUser()->setAttributes($attributes, false);
        $registration->setModels();

        return $registration;
    }

    private function getKeycloakModule(): ?Module
    {
        /** @var Module|null $module */
        $module = Yii::$app->getModule('auth-keycloak');
        return $module;
    }
}

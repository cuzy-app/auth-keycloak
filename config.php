<?php
/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/humhub-modules-auth-keycloak
 * @license https://github.com/cuzy-app/humhub-modules-auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

use humhub\modules\authKeycloak\Events;
use humhub\modules\user\authclient\Collection;
use humhub\modules\user\controllers\AuthController;
use humhub\modules\user\controllers\RegistrationController;
use humhub\modules\user\models\forms\Registration;
use humhub\modules\user\models\User as ModelUser;
use yii\web\User as ComponentUser;

/** @noinspection MissedFieldInspection */
return [
    'id' => 'auth-keycloak',
    'class' => humhub\modules\authKeycloak\Module::class,
    'namespace' => 'humhub\modules\authKeycloak',
    'events' => [
        ['class' => AuthController::class, 'event' => AuthController::EVENT_BEFORE_ACTION, 'callback' => [Events::class, 'onUserAuthControllerBeforeAction']],
        ['class' => RegistrationController::class, 'event' => RegistrationController::EVENT_BEFORE_ACTION, 'callback' => [Events::class, 'onUserRegistrationControllerBeforeAction']],
        ['class' => Registration::class, 'event' => Registration::EVENT_BEFORE_RENDER, 'callback' => [Events::class, 'onUserRegistrationFormBeforeRender']],
        ['class' => ModelUser::class, 'event' => ModelUser::EVENT_AFTER_UPDATE, 'callback' => [Events::class, 'onModelUserAfterUpdate']],
        ['class' => ComponentUser::class, 'event' => ComponentUser::EVENT_AFTER_LOGOUT, 'callback' => [Events::class, 'onComponentUserAfterLogout']],
        [Collection::class, Collection::EVENT_AFTER_CLIENTS_SET, [Events::class, 'onAuthClientCollectionInit']]
    ],
];
?>
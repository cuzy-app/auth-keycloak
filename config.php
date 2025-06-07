<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

use humhub\commands\CronController;
use humhub\compat\HForm;
use humhub\modules\authKeycloak\Events;
use humhub\modules\user\authclient\Collection;
use humhub\modules\user\controllers\AuthController;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\GroupUser;
use humhub\modules\user\models\User;
use humhub\modules\user\widgets\AccountProfileMenu;
use yii\web\User as UserComponent;

/** @noinspection MissedFieldInspection */
return [
    'id' => 'auth-keycloak',
    'class' => humhub\modules\authKeycloak\Module::class,
    'namespace' => 'humhub\modules\authKeycloak',
    'events' => [
        [
            'class' => AuthController::class,
            'event' => AuthController::EVENT_AFTER_LOGIN,
            'callback' => [Events::class, 'onAfterLogin'],
        ],
        [
            'class' => HForm::class,
            'event' => HForm::EVENT_BEFORE_RENDER,
            'callback' => [Events::class, 'onHFormBeforeRender'],
        ],
        [
            'class' => User::class,
            'event' => User::EVENT_AFTER_UPDATE,
            'callback' => [Events::class, 'onModelUserAfterUpdate'],
        ],
        [
            'class' => UserComponent::class,
            'event' => UserComponent::EVENT_AFTER_LOGOUT,
            'callback' => [Events::class, 'onComponentUserAfterLogout'],
        ],
        [
            'class' => Collection::class,
            'event' => Collection::EVENT_AFTER_CLIENTS_SET,
            'callback' => [Events::class, 'onAuthClientCollectionInit'],
        ],
        [
            'class' => Group::class,
            'event' => Group::EVENT_AFTER_INSERT,
            'callback' => [
                Events::class,
                'onModelGroupAfterInsert',
            ],
        ],
        [
            'class' => Group::class,
            'event' => Group::EVENT_AFTER_DELETE,
            'callback' => [
                Events::class,
                'onModelGroupAfterDelete',
            ],
        ],
        [
            'class' => Group::class,
            'event' => Group::EVENT_AFTER_UPDATE,
            'callback' => [
                Events::class,
                'onModelGroupAfterUpdate',
            ],
        ],
        [
            'class' => GroupUser::class,
            'event' => GroupUser::EVENT_AFTER_INSERT,
            'callback' => [
                Events::class,
                'onModelGroupUserAfterInsert',
            ],
        ],
        [
            'class' => GroupUser::class,
            'event' => GroupUser::EVENT_AFTER_DELETE,
            'callback' => [
                Events::class,
                'onModelGroupUserAfterDelete',
            ],
        ],
        [
            'class' => CronController::class,
            'event' => CronController::EVENT_ON_DAILY_RUN,
            'callback' => [Events::class, 'onCronDailyRun'],
        ],
        [
            'class' => Auth::class,
            'event' => Auth::EVENT_AFTER_INSERT,
            'callback' => [Events::class, 'onAuthAfterInsert'],
        ],
        [
            'class' => Auth::class,
            'event' => Auth::EVENT_AFTER_UPDATE,
            'callback' => [Events::class, 'onAuthAfterUpdate'],
        ],
        [
            'class' => AccountProfileMenu::class,
            'event' => AccountProfileMenu::EVENT_INIT,
            'callback' => [Events::class, 'onAccountProfileMenuInit'],
        ],
    ],
];

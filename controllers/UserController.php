<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\controllers;

use humhub\modules\authKeycloak\components\KeycloakApi;
use humhub\modules\user\components\BaseAccountController;
use Yii;

class UserController extends BaseAccountController
{
    public function actionChangePassword()
    {
        if (
            ($post = Yii::$app->request->post())
            && ($newPassword = $post['newPassword'] ?? null)
        ) {
            $newPasswordConfirm = $post['newPasswordConfirm'] ?? null;
            if ($newPassword !== $newPasswordConfirm) {
                $this->view->error(Yii::t('AuthKeycloakModule.base', 'Password confirmation does not match.'));
            } else {
                $keycloakApi = new KeycloakApi();
                $return = $keycloakApi->resetUserPassword(Yii::$app->user->identity, $newPassword);
                if ($return === true) {
                    $this->view->saved();
                } else {
                    $this->view->error($return ?: Yii::t('AuthKeycloakModule.base', 'The new password could not be saved.'));
                }
            }
        }

        return $this->render('change-password', []);
    }
}

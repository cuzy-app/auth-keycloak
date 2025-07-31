<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\authKeycloak\components\KeycloakApi;
use humhub\modules\authKeycloak\models\ConfigureForm;
use Yii;

/**
 * Module configuation
 */
class ConfigController extends Controller
{
    /**
     * Render admin only page
     *
     * @return string
     */
    public function actionIndex()
    {
        $model = new ConfigureForm();

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->save()) {
            $this->view->saved();
        }

        $keycloakApi = new KeycloakApi();
        $apiAuthentificationSuccess = $keycloakApi->isConnected();

        return $this->render('index', [
            'model' => $model,
            'apiAuthentificationSuccess' => $apiAuthentificationSuccess,
        ]);
    }
}

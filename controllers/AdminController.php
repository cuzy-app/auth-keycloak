<?php
/**
 * * Keycloak Sign-In
 * @link https://github.com/cuzy-app/humhub-modules-auth-keycloak
 * @license https://github.com/cuzy-app/humhub-modules-auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\authKeycloak\models\ConfigureForm;
use Yii;

/**
 * Module configuation
 */
class AdminController extends Controller
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

        return $this->render('index', ['model' => $model]);
    }
}
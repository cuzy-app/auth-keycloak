<?php

/* @var $this \humhub\modules\ui\view\components\View */
/* @var $model \humhubContrib\authKeycloak\models\ConfigureForm */

use yii\bootstrap\ActiveForm;
use humhub\libs\Html;

?>
<div class="container-fluid">
    <div class="panel panel-default">
        <div class="panel-heading">
            <?= Yii::t('AuthKeycloakModule.base', '<strong>Keycloak</strong> Sign-In configuration') ?></div>

        <div class="panel-body">
            <p>
                <?= Html::a(Yii::t('AuthKeycloakModule.base', 'Keycloak Documentation'), 'https://www.keycloak.org/documentation', ['class' => 'btn btn-primary pull-right btn-sm', 'target' => '_blank']); ?>
                <?= Yii::t('AuthKeycloakModule.base', 'Please follow the Keycloak instructions to create the required <strong>OAuth client</strong> credentials.'); ?>
                <br>
            </p>
            <br>

            <?php $form = ActiveForm::begin(['id' => 'configure-form', 'enableClientValidation' => false, 'enableClientScript' => false]); ?>

            <?= $form->field($model, 'enabled')->checkbox(); ?>

            <br>
            <?= $form->field($model, 'clientId'); ?>
            <?= $form->field($model, 'clientSecret')->textInput(['type' => 'password']); ?>
            <br>
            <?= $form->field($model, 'authUrl'); ?>
            <?= $form->field($model, 'tokenUrl'); ?>
            <?= $form->field($model, 'apiBaseUrl'); ?>
            <br>
            <?= $form->field($model, 'redirectUri')->textInput(['readonly' => true]); ?>
            <br>
            <?= $form->field($model, 'idAttribute'); ?>
            <?= $form->field($model, 'usernameMapper'); ?>
            <br>

            <h4><?= Yii::t('AuthKeycloakModule.base', 'Advanced settings (optional)'); ?></h4>

            <?= $form->field($model, 'title'); ?>
            <?= $form->field($model, 'autoLogin')->checkbox(); ?>
            <?= $form->field($model, 'hideRegistrationUsernameField')->checkbox(); ?>
            <?= $form->field($model, 'removeKeycloakSessionsAfterLogout')->checkbox(); ?>
            <?= $form->field($model, 'updateHumhubEmailFromBrokerEmail')->checkbox(); ?>
            <?= $form->field($model, 'updatedBrokerEmailFromHumhubEmail')->checkbox(); ?>
            <br>

            <h4><?= Yii::t('AuthKeycloakModule.base', 'Keycloak API settings'); ?></h4>

            <?= $form->field($model, 'apiRealm'); ?>
            <?= $form->field($model, 'apiUsername'); ?>
            <?= $form->field($model, 'apiPassword')->textInput(['type' => 'password']); ?>
            <?= $form->field($model, 'apiRootUrl'); ?>

            <div class="form-group">
                <?= Html::saveButton() ?>
            </div>

            <?php ActiveForm::end(); ?>

        </div>
    </div>
</div>
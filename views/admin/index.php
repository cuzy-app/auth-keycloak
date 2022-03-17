<?php

/* @var $this View */

/* @var $model ConfigureForm */

use humhub\libs\Html;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\ui\form\widgets\ActiveForm;
use humhub\modules\ui\view\components\View;

?>
<div class="container-fluid">
    <div class="panel panel-default">
        <div class="panel-heading">
            <?= Yii::t('AuthKeycloakModule.base', '<strong>Keycloak</strong> Sign-In configuration') ?></div>

        <div class="panel-body">
            <div class="alert alert-info">
                <div><?= Yii::t('AuthKeycloakModule.base', 'On Keycloak, create a client for Humhub and configure it:') ?></div>
                <ul>
                    <li><?= Yii::t('AuthKeycloakModule.base', '{Settings} tab -> {AccessType}: choose {confidential}. Save settings.', ['Settings' => '“Settings”', 'AccessType' => '“Access Type”', 'confidential' => '“confidential”',]) ?></li>
                    <li><?= Yii::t('AuthKeycloakModule.base', '{Credentials} tab: copy the secret key', ['Credentials' => '“Credentials”']) ?></li>
                    <li><?= Yii::t('AuthKeycloakModule.base', '{Mappers} tab:', ['Mappers' => '“Mappers”']) ?></li>
                    <ul>
                        <li><?= Yii::t('AuthKeycloakModule.base', 'Button {AddBuiltin} and check theses attributes:', ['AddBuiltin' => '“Add builtin”']) ?>
                            “family name”, “email”, “given name”, “username”
                        </li>
                        <li><?= Yii::t('AuthKeycloakModule.base', 'Edit {usernameAttribute} and in {TokenClaimName}, replace {preferredUsernameAttribute} with {idAttribute}', ['usernameAttribute' => '“username”', 'TokenClaimName' => '“Token Claim Name”', 'preferredUsernameAttribute' => '“preferred_username”', 'idAttribute' => '“id”']) ?></li>
                    </ul>
                </ul>
            </div>
            <br>

            <?php $form = ActiveForm::begin(['id' => 'configure-form', 'enableClientValidation' => false, 'enableClientScript' => false]) ?>

            <?= $form->field($model, 'enabled')->checkbox() ?>
            <?= $form->field($model, 'clientId') ?>
            <?= $form->field($model, 'clientSecret')->textInput(['type' => 'password']) ?>
            <?= $form->field($model, 'realm') ?>
            <?= $form->field($model, 'baseUrl') ?>
            <?= $form->field($model, 'redirectUri')->textInput(['readonly' => true]) ?>
            <?= $form->field($model, 'usernameMapper') ?>

            <?= $form->beginCollapsibleFields(Yii::t('AuthKeycloakModule.base', 'Advanced settings (optional)')) ?>
            <?= $form->field($model, 'title') ?>
            <?= $form->field($model, 'autoLogin')->checkbox() ?>
            <?= $form->field($model, 'hideRegistrationUsernameField')->checkbox() ?>
            <?= $form->endCollapsibleFields(); ?>

            <?= $form->beginCollapsibleFields(Yii::t('AuthKeycloakModule.base', 'Advanced settings requiring an admin user for the API (optional)')) ?>
            <?= $form->field($model, 'apiUsername') ?>
            <?= $form->field($model, 'apiPassword')->textInput(['type' => 'password']) ?>
            <?= $form->field($model, 'removeKeycloakSessionsAfterLogout')->checkbox() ?>
            <?= $form->field($model, 'updateHumhubEmailFromBrokerEmail')->checkbox() ?>
            <?= $form->field($model, 'updatedBrokerEmailFromHumhubEmail')->checkbox() ?>
            <?= $form->field($model, 'groupsSyncMode')->dropDownList($model->groupsSyncModeItems()) ?>
            <?= $form->endCollapsibleFields(); ?>

            <?= Html::saveButton() ?>

            <?php ActiveForm::end() ?>

        </div>
    </div>
</div>
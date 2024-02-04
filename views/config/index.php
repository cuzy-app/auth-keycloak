<?php

/* @var $this View */

/* @var $model ConfigureForm */

/* @var $apiAuthentificationSuccess bool */

use humhub\libs\Html;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\Module;
use humhub\modules\ui\form\widgets\ActiveForm;
use humhub\modules\ui\view\components\View;
use humhub\widgets\Button;
use yii\bootstrap\Alert;

/** @var Module $module */
$module = Yii::$app->getModule('auth-keycloak');

$requirements = include $module->basePath . '/' . 'requirements.php';
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('AuthKeycloakModule.base', '<strong>Keycloak</strong> Sign-In configuration') ?>
        <div class="help-block"><?= $module->getDescription() ?></div>
    </div>

    <div class="panel-body">
        <?php if ($requirements): ?>
            <?= Alert::widget([
                'options' => ['class' => 'alert-danger'],
                'body' => Html::tag('strong', 'A requirement is not met: ' . $requirements),
            ]) ?>
        <?php endif; ?>

        <div class="alert alert-info">
            This module was created and is maintained by
            <a href="https://www.cuzy.app/"
               target="_blank">CUZY.APP (view other modules)</a>.
            <br>
            It's free, but it's the result of a lot of design and maintenance work over time.
            <br>
            If it's useful to you, please consider
            <a href="https://www.cuzy.app/checkout/donate/"
               target="_blank">making a donation</a>
            or
            <a href="https://github.com/cuzy-app/humhub-modules-auth-keycloak"
               target="_blank">participating in the code</a>.
            Thanks!
        </div>

        <div>
            <div><?= Yii::t('AuthKeycloakModule.base', 'On Keycloak, create a client for Humhub and configure it:') ?></div>
            <ul>
                <li><?= Yii::t('AuthKeycloakModule.base', '{Settings} tab -> {ClientAuthenticationOn} (for Keycloak version <20: {AccessTypeValue}).', [
                        'ClientAuthenticationOn' => '“Client authentication”: “On”',
                        'Settings' => '“Settings”',
                        'AccessTypeValue' => '“Access Type”: “confidential”',
                    ]) ?></li>
                <li><?= Yii::t('AuthKeycloakModule.base', '{Settings} tab -> {ValidRedirectURIsValue}.', [
                        'Settings' => '“Settings”',
                        'ValidRedirectURIsValue' => '“Valid redirect URIs”: ' . Html::tag('code', $model->redirectUri),
                    ]) ?></li>
                <li><?= Yii::t('AuthKeycloakModule.base', '{Credentials} tab: copy the secret key', ['Credentials' => '“Credentials”']) ?></li>
            </ul>
            <div><?= Yii::t('AuthKeycloakModule.base', 'If you want to enable {BackChannelLogout} (which allows removing user sessions automatically when signing out from Keycloak), configure the client {LogoutSettings}:', [
                    'BackChannelLogout' => '"Back-Channel Logout"',
                    'LogoutSettings' => '"Logout settings"',
                ]) ?></div>
            <ul>
                <li>Backchannel logout URL: <?= Html::tag('code', $model->backChannelLogoutUrl) ?></li>
                <li>Backchannel logout session required: On</li>
                <li>Backchannel logout revoke offline sessions: On</li>
            </ul>
        </div>
        <br>

        <?php $form = ActiveForm::begin(['acknowledge' => true]) ?>

        <?= $form->field($model, 'enabled')->checkbox() ?>
        <?= $form->field($model, 'baseUrl') ?>
        <?= $form->field($model, 'realm') ?>
        <?= $form->field($model, 'clientId') ?>
        <?= $form->field($model, 'clientSecret')->textInput(['type' => 'password']) ?>
        <?= $form->field($model, 'redirectUri')->textInput(['readonly' => true]) ?>
        <?= $form->field($model, 'usernameMapper') ?>

        <?= $form->beginCollapsibleFields(Yii::t('AuthKeycloakModule.base', 'Advanced settings (optional)')) ?>
        <?= $form->field($model, 'title') ?>
        <?= $form->field($model, 'hideRegistrationUsernameField')->checkbox() ?>
        <?= $form->field($model, 'hideAdminUserEditPassword')->checkbox() ?>
        <?= $form->endCollapsibleFields(); ?>

        <?= $form->beginCollapsibleFields(Yii::t('AuthKeycloakModule.base', 'Advanced settings requiring an admin user for the API (optional)')) ?>

        <?php if ($model->apiUsername) : ?>
            <?= Alert::widget([
                'options' => ['class' => 'alert-' . ($apiAuthentificationSuccess ? 'success' : 'danger')],
                'body' => $apiAuthentificationSuccess ?
                    Yii::t('AuthKeycloakModule.base', 'Authentication to Keycloak API succeeded!') :
                    Yii::t('AuthKeycloakModule.base', 'Authentication to Keycloak API failed!') . ' ' . Button::info(Yii::t('AuthKeycloakModule.base', 'View error log'))->link(['/admin/logging', 'levels[]' => 1])
            ]) ?>
        <?php endif; ?>

        <?= $form->field($model, 'apiUsername') ?>
        <?= $form->field($model, 'apiPassword')->textInput(['type' => 'password']) ?>
        <?= $form->field($model, 'removeKeycloakSessionsAfterLogout')->checkbox() ?>
        <?= $form->field($model, 'updateHumhubUsernameFromBrokerUsername')->checkbox() ?>
        <?= $form->field($model, 'updatedBrokerUsernameFromHumhubUsername')->checkbox() ?>
        <?= $form->field($model, 'updateHumhubEmailFromBrokerEmail')->checkbox() ?>
        <?= $form->field($model, 'updatedBrokerEmailFromHumhubEmail')->checkbox() ?>
        <?= $form->field($model, 'addChangePasswordFormToAccount')->checkbox() ?>
        <?= $form->field($model, 'groupsSyncMode')->dropDownList($model->groupsSyncModeItems()) ?>
        <?= $form->endCollapsibleFields(); ?>

        <?= Html::saveButton() ?>

        <?php ActiveForm::end() ?>

    </div>
</div>

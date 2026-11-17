<?php

/* @var $this View */

/* @var $model ConfigureForm */

/* @var $apiAuthentificationSuccess bool */

use humhub\components\View;
use humhub\helpers\Html;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\Module;
use humhub\widgets\bootstrap\Alert;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;

/** @var Module $module */
$module = Yii::$app->getModule('auth-keycloak');

$requirementsFile = $module->basePath . '/' . 'requirements.php';
$requirements = file_exists($requirementsFile) ? (include $module->basePath . '/' . 'requirements.php') : null;
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('AuthKeycloakModule.base', '<strong>Keycloak</strong> Sign-In configuration') ?>
        <div class="text-body-secondary"><?= $module->getDescription() ?></div>
    </div>

    <div class="panel-body">
        <?php if ($requirements): ?>
            <?= Alert::danger(Html::tag('strong', 'A requirement is not met: ' . $requirements)) ?>
        <?php endif; ?>

        <?php if ($model->isAdoptingExistingUsers()): ?>
            <?= Alert::warning(Yii::t('AuthKeycloakModule.base', '{adoptExistingUsers} is enabled: the users sync takes over the matching HumHub accounts of other user sources. Disable it once the migration to Keycloak is done.', [
                'adoptExistingUsers' => Html::tag('strong', Html::encode($model->getAttributeLabel('adoptExistingUsers'))),
            ])) ?>
        <?php endif; ?>

        <div class="alert alert-info cuzy-free-module-info" role="alert">
            This module was created and is maintained by
            <a href="https://www.cuzy.app/"
               target="_blank">CUZY.app (view other modules)</a>.
            <br>
            It's free, but it's the result of a lot of design and maintenance work over time.
            <br>
            If it's useful to you, please consider
            <a href="https://www.cuzy.app/checkout/donate/"
               target="_blank">making a donation</a>
            or
            <a href="https://github.com/cuzy-app/auth-keycloak"
               target="_blank">participating in the code</a>.
            Thanks!
        </div>

        <div>
            <div><?= Yii::t('AuthKeycloakModule.base', 'On Keycloak, create a client for HumHub and configure it:') ?></div>
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
        <?php // A stored secret is never echoed into HTML: the field carries the bullet
              // placeholder, and submitting it unchanged keeps the real value (see
              // ConfigController::actionIndex()). ?>
        <?= $form->field($model, 'clientSecret')->textInput(['type' => 'password', 'autocomplete' => 'new-password']) ?>
        <?= $form->field($model, 'redirectUri')->textInput(['readonly' => true]) ?>
        <?= $form->field($model, 'attributeMapping')->textarea(['rows' => 4, 'placeholder' => "username=preferred_username\nphone_work=phoneNumber\ncity=locality"]) ?>

        <?= $form->beginCollapsibleFields(Yii::t('AuthKeycloakModule.base', 'Advanced settings (optional)')) ?>
        <?= $form->field($model, 'title') ?>
        <?= $form->field($model, 'forceKeycloakAuth')->checkbox() ?>
        <?= $form->field($model, 'hideRegistrationUsernameField')->checkbox() ?>
        <?= $form->field($model, 'hideAdminUserEditPassword')->checkbox() ?>
        <?= $form->field($model, 'trustOnlyVerifiedEmail')->checkbox() ?>
        <?= $form->endCollapsibleFields(); ?>

        <?= $form->beginCollapsibleFields(Yii::t('AuthKeycloakModule.base', 'Advanced settings requiring access to the Keycloak API (optional)')) ?>

        <?php if ($model->hasApiParams()) : ?>
            <?= Alert::instance(
                $apiAuthentificationSuccess
                    ? Yii::t('AuthKeycloakModule.base', 'Authentication to Keycloak API succeeded!')
                    : Yii::t('AuthKeycloakModule.base', 'Authentication to Keycloak API failed!') . ' ' . Button::info(Yii::t('AuthKeycloakModule.base', 'View error log'))->link(['/admin/logging', 'levels[]' => 1]),
                $apiAuthentificationSuccess ? 'success' : 'danger',
            ) ?>
        <?php endif; ?>

        <?= $form->field($model, 'apiAuthMethod')->dropDownList($model->apiAuthMethodItems()) ?>
        <?= $form->field($model, 'apiUsername') ?>
        <?php // Same masking as the client secret above (see ConfigController::actionIndex()). ?>
        <?= $form->field($model, 'apiPassword')->textInput(['type' => 'password', 'autocomplete' => 'new-password']) ?>
        <?= $form->field($model, 'apiReadOnly')->checkbox() ?>
        <?= $form->field($model, 'syncUsers')->checkbox(['disabled' => !$module->provideUserSource]) ?>
        <?= $form->field($model, 'adoptExistingUsers')->dropDownList($model->adoptExistingUsersItems(), ['disabled' => !$module->provideUserSource]) ?>
        <?= $form->field($model, 'removeKeycloakSessionsAfterLogout')->checkbox() ?>
        <?= $form->field($model, 'updateHumhubUsernameFromBrokerUsername')->checkbox() ?>
        <?= $form->field($model, 'updatedBrokerUsernameFromHumhubUsername')->checkbox() ?>
        <?= $form->field($model, 'updateHumhubEmailFromBrokerEmail')->checkbox() ?>
        <?= $form->field($model, 'updatedBrokerEmailFromHumhubEmail')->checkbox() ?>
        <?= $form->field($model, 'addChangePasswordFormToAccount')->checkbox() ?>
        <?= $form->field($model, 'disableKeycloakUserOnDelete')->checkbox() ?>
        <?= $form->field($model, 'groupsSyncMode')->dropDownList($model->groupsSyncModeItems()) ?>
        <?= $form->endCollapsibleFields(); ?>

        <?= Html::saveButton() ?>

        <?php ActiveForm::end() ?>

        <?php
        // The admin user fields are not used with the service account
        $this->registerJs('
            (function () {
                var $authMethod = $(' . json_encode('#' . Html::getInputId($model, 'apiAuthMethod')) . ');
                var $userFields = $(' . json_encode(implode(',', [
                    '.field-' . Html::getInputId($model, 'apiUsername'),
                    '.field-' . Html::getInputId($model, 'apiPassword'),
                ])) . ');

                function apply() {
                    $userFields.toggle($authMethod.val() !== ' . json_encode(ConfigureForm::API_AUTH_METHOD_SERVICE_ACCOUNT) . ');
                }

                $authMethod.on("change", apply);
                apply();
            })();
        ');

        // The users are adopted by the users sync
        $this->registerJs('
            (function () {
                var $syncUsers = $(' . json_encode('#' . Html::getInputId($model, 'syncUsers')) . ');
                var $adoptExistingUsers = $(' . json_encode('#' . Html::getInputId($model, 'adoptExistingUsers')) . ');

                function apply() {
                    var disabled = !$syncUsers.is(":checked") || $syncUsers.is(":disabled");
                    if (disabled) {
                        $adoptExistingUsers.val(' . json_encode(ConfigureForm::ADOPT_USERS_NONE) . ');
                    }
                    $adoptExistingUsers.prop("disabled", disabled);
                }

                $syncUsers.on("change", apply);
                apply();
            })();
        ');

        // Settings requiring write access to the Keycloak API are disabled while the API is read-only
        $this->registerJs('
            (function () {
                var $readOnly = $(' . json_encode('#' . Html::getInputId($model, 'apiReadOnly')) . ');
                var $writeInputs = $(' . json_encode(implode(',', array_map(
                    static fn($attribute) => '#' . Html::getInputId($model, $attribute),
                    ConfigureForm::API_WRITE_ATTRIBUTES,
                ))) . ');
                var $groupsSyncMode = $(' . json_encode('#' . Html::getInputId($model, 'groupsSyncMode')) . ');
                var readOnlyGroupsSyncModes = ' . json_encode(ConfigureForm::API_READ_ONLY_GROUP_SYNC_MODES) . ';

                function apply() {
                    var readOnly = $readOnly.is(":checked");
                    if (readOnly) {
                        $writeInputs.prop("checked", false);
                    }
                    $writeInputs.prop("disabled", readOnly);
                    $groupsSyncMode.find("option").each(function () {
                        $(this).prop("disabled", readOnly && this.value in readOnlyGroupsSyncModes);
                    });
                    if (readOnly && $groupsSyncMode.val() in readOnlyGroupsSyncModes) {
                        $groupsSyncMode.val(readOnlyGroupsSyncModes[$groupsSyncMode.val()]);
                    }
                }

                $readOnly.on("change", apply);
                apply();
            })();
        ') ?>

    </div>
</div>

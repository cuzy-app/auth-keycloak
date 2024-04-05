<?php
/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

use humhub\libs\Html;
use yii\web\View;

/**
 * @var $this View
 */
?>

<?php $this->beginContent('@user/views/account/_userProfileLayout.php') ?>
<div class="help-block">
    <?= Yii::t('AuthKeycloakModule.base', 'Your current password can be changed here.') ?>
</div>

<?= Html::beginForm() ?>

<div class="input-group">
    <label class="control-label" for="labelName"><?= Yii::t('AuthKeycloakModule.base', 'New password') ?> *</label>
    <?= Html::textInput('newPassword', null, ['class' => 'form-control', 'type' => 'password', 'required' => true]) ?>
</div>

<div class="input-group">
    <label class="control-label"
           for="labelName"><?= Yii::t('AuthKeycloakModule.base', 'Confirm new password') ?> *</label>
    <?= Html::textInput('newPasswordConfirm', null, ['class' => 'form-control', 'type' => 'password', 'required' => true]) ?>
</div>
<br>
<?= Html::saveButton() ?>
<?= Html::endForm() ?>

<?php $this->endContent(); ?>

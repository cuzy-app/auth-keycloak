<?php
/**
 * * Keycloak Sign-In
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\authKeycloak;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\user\authclient\Collection;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\User;
use yii\base\ActionEvent;
use Yii;
use yii\base\Event;

class Events
{
    /**
     * @param Event $event
     */
    public static function onAuthClientCollectionInit($event)
    {
        /** @var Collection $authClientCollection */
        $authClientCollection = $event->sender;

        $config = ConfigureForm::getInstance();
        if ($config->enabled) {
            $authClientCollection->setClient('Keycloak', [
                'class' => Keycloak::class,
                'clientId' => $config->clientId,
                'clientSecret' => $config->clientSecret,
                'authUrl' => $config->authUrl,
                'tokenUrl' => $config->tokenUrl,
                'apiBaseUrl' => $config->apiBaseUrl
            ]);
        }
    }

    /**
     * Adds auto login possibility
     * @param ActionEvent $event
     * @throws \yii\base\InvalidConfigException
     */
    public static function onUserAuthControllerBeforeAction(ActionEvent $event)
    {
        if (
            !Yii::$app->user->isGuest
            || !Yii::$app->authClientCollection->hasClient('Keycloak')
            || $event->action->id !== 'login'
            || !Yii::$app->getModule('user')->settings->get('auth.anonymousRegistration') // if anonymous registration is not allowed and someone try to create an account, do not redirect to broker to avoid looping redirections
        ) {
            return;
        }

        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');
        $settings = $module->settings;

        $authClient = Yii::$app->authClientCollection->getClient('Keycloak');

        if ($settings->get('autoLogin')) {
            $event->isValid = false;
            return $authClient->redirectToBroker();
        }
    }

    /**
     * Adds auto login possibility
     * @param ActionEvent $event
     * @return mixed
     * @throws \yii\base\InvalidConfigException
     */
    public static function onUserRegistrationControllerBeforeAction(ActionEvent $event)
    {
        if (
            !Yii::$app->user->isGuest
            || Yii::$app->request->isConsoleRequest
            || Yii::$app->controller->module->id === 'admin'
            || !Yii::$app->authClientCollection->hasClient('Keycloak')
            || $event->action->id !== 'index'
            || !Yii::$app->request->get('token') // If not invited
        ) {
            return;
        }

        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');
        $settings = $module->settings;

        $authClient = Yii::$app->authClientCollection->getClient('Keycloak');

        if ($settings->get('autoLogin')) {
            $event->isValid = false;
            return $authClient->redirectToBroker();
        }
    }

    /**
     * Registration form: hide username field
     * @param Event $event
     */
    public static function onUserRegistrationFormBeforeRender ($event)
    {
        if (
            Yii::$app->request->isConsoleRequest
            || Yii::$app->controller->module->id === 'admin'
            || Yii::$app->request->get('token') // If invited
            || !Yii::$app->authClientCollection->hasClient('Keycloak')
        ) {
            return;
        }

        /** @var Module $module */
        $module = Yii::$app->getModule('auth-keycloak');
        $settings = $module->settings;
        if (!$settings->get('hideRegistrationUsernameField')) {
            return;
        }

        /** @var \humhub\modules\user\models\forms\Registration $hform */
        $hform = $event->sender;

        unset($hform->definition['elements']['User']['title']);
        $hform->definition['elements']['User']['elements']['username']['type'] = 'hidden';
    }

    /**
     * If user email has changed in Humhub, update it on the broker (IdP)
     * @param $event
     * @throws \yii\base\InvalidConfigException
     */
    public static function onModelUserAfterUpdate($event)
    {
        if (!isset($event->sender)) {
            return;
        }

        /** @var User $user */
        $user = $event->sender;

        // Get changed attributes
        $changedAttributes = $event->changedAttributes;

        // If email has changed
        if (array_key_exists('email', $changedAttributes)) {

            if (Yii::$app->authClientCollection->hasClient('Keycloak')) {

                $config = ConfigureForm::getInstance();
                if ($config->updatedBrokerEmailFromHumhubEmail && $config->hasApiParams()) {

                    $userAuth = Auth::findOne(['source' => 'Keycloak', 'user_id' => $user->id]);
                    if ($userAuth !== null) {

                        if (!class_exists('Keycloak\Admin\KeycloakClient')) {
                            require Yii::getAlias('@auth-keycloak/vendor/autoload.php');
                        }

                        $client = \Keycloak\Admin\KeycloakClient::factory([
                            'realm' => $config->apiRealm,
                            'username' => $config->apiUsername,
                            'password' => $config->apiPassword,
                            'baseUri' => $config->apiRootUrl,
                        ]);
                        $realm = $client->getRealm();

                        // Update email
                        $client->updateUser(array_merge(
                            [
                                'id' => $userAuth->source_id,
                                'email' => $user->email,
                            ],
                            ($realm['registrationEmailAsUsername'] ? ['username' => $user->email] : [])
                        ));
                    }
                }
            }
        }
    }

    /**
     * Remove session on Keycloak after logout
     * @param $event
     */
    public static function onComponentUserAfterLogout($event)
    {
        if (!Yii::$app->authClientCollection->hasClient('Keycloak')) {
            return;
        }

        /** @var User $user */
        $user = $event->identity;

        $config = ConfigureForm::getInstance();
        if ($config->removeKeycloakSessionsAfterLogout && $config->hasApiParams()) {

            $userAuth = Auth::findOne(['source' => 'Keycloak', 'user_id' => $user->id]);
            if ($userAuth !== null) {

                if (!class_exists('Keycloak\Admin\KeycloakClient')) {
                    require Yii::getAlias('@auth-keycloak/vendor/autoload.php');
                }

                $client = \Keycloak\Admin\KeycloakClient::factory([
                    'realm' => $config->apiRealm,
                    'username' => $config->apiUsername,
                    'password' => $config->apiPassword,
                    'baseUri' => $config->apiRootUrl,
                ]);

                // Search for client with clientId of $authClient
                foreach ($client->getClients() as $clientDefinition) {
                    if (
                        isset($clientDefinition['clientId'])
                        && $clientDefinition['clientId'] === $config->clientId
                        && isset($clientDefinition['id'])
                    ) {
                        // Get id of the client (different from clientId)
                        $idOfClient = $clientDefinition['id'];

                        // Get user sessions
                        $clientSessions = $client->getClientSessions([
                            'id' => $idOfClient,
                        ]);
                        foreach($clientSessions as $session) {
                            if (isset($session['id'])) {

                                // revoke session
                                $client->revokeUserSession([
                                    'session' => $session['id'],
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }
}

<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use humhub\modules\authKeycloak\models\AuthKeycloak;
use humhub\modules\user\models\Session;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\JsonParser;
use yii\web\NotFoundHttpException;

class BackChannelController extends Controller
{
    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    /**
     * @inerhitdoc
     * Do not enforce authentication.
     */
    public $access = ControllerAccess::class;

    public bool $debugMode = false;

    /**
     * @inheritdoc
     */
    protected $doNotInterceptActionIds = ['*'];

    public function beforeAction($action)
    {
        Yii::$app->response->format = 'json';
        Yii::$app->request->setBodyParams(null);
        Yii::$app->request->parsers['application/json'] = JsonParser::class;

        return parent::beforeAction($action);
    }

    /**
     * https://openid.net/specs/openid-connect-backchannel-1_0.html
     * https://auth0.com/docs/authenticate/login/logout/back-channel-logout
     * @return \yii\web\Response
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function actionLogout()
    {
        // Get Logout token
        $logoutToken = Yii::$app->request->post('logout_token');
        if (!$logoutToken) {
            $this->sendError('Missing logout token');
        }

        // Decode it to get Keycloak shared session identifier
        [$header, $payload, $signature] = explode('.', $logoutToken);
        $payloadDecoded = json_decode(base64_decode($payload), true);
        $sid = $payloadDecoded['sid'] ?? null;
        if (!$sid) {
            $this->sendError('Missing sid in logout token');
        }

        // Search for a Keycloak authenticated user
        $auth = AuthKeycloak::findOne(['keycloak_sid' => $sid]);
        if (!$auth) {
            $this->sendError('User Auth not found for sid ' . $sid);
        }

        // Delete all user sessions
        foreach (Session::findAll(['user_id' => $auth->user_id]) as $session) {
            $session->delete();
        }

        return $this->asJson(['status' => 'success']);
    }

    /**
     * @throws NotFoundHttpException
     */
    private function sendError(string $errorMsg): void
    {
        if ($this->debugMode) {
            Yii::error($errorMsg, 'auth-keycloak');
        }
        throw new NotFoundHttpException('Keycloak: ' . $errorMsg);
    }

}

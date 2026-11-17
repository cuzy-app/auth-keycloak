<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 */

namespace humhub\modules\authKeycloak\tests\codeception\unit;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\Events;
use humhub\modules\user\controllers\AuthController;
use humhub\modules\user\controllers\RegistrationController;
use tests\codeception\_support\HumHubDbTestCase;
use Yii;
use yii\base\Action;
use yii\base\ActionEvent;

/**
 * "Always sign in with Keycloak": redirection of the login page and of the invite links
 */
class ForceKeycloakAuthTest extends HumHubDbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Yii::$app->request->setQueryParams([]);
        Yii::$app->response->headers->removeAll();
        Yii::$app->response->content = null;
        Yii::$app->session->removeAllFlashes();
        $this->configure(true);
    }

    public function testLoginPageRedirectsToKeycloak()
    {
        $event = $this->login();
        $this->assertFalse($event->isValid);
        $this->assertStringContainsString('authclient=Keycloak', Yii::$app->response->headers->get('Location'));
    }

    public function testLoginModalNavigatesToKeycloak()
    {
        // The login modal is loaded by AJAX: the browser must navigate, not the XHR
        Yii::$app->request->headers->set('X-Requested-With', 'XMLHttpRequest');
        try {
            $event = $this->login();
        } finally {
            Yii::$app->request->headers->remove('X-Requested-With');
        }
        $this->assertFalse($event->isValid);
        $this->assertNull(Yii::$app->response->headers->get('Location'));
        $this->assertStringContainsString('authclient=Keycloak', Yii::$app->response->content);
    }

    public function testNoRedirectIfDisabled()
    {
        $this->configure(false);
        $this->assertTrue($this->login()->isValid);
    }

    public function testBypassUrl()
    {
        Yii::$app->request->setQueryParams(['noKeycloak' => 1]);
        $this->assertTrue($this->login()->isValid);
    }

    public function testNoRedirectForPostRequests()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertTrue($this->login()->isValid);
    }

    public function testNoRedirectWhenAnErrorIsDisplayed()
    {
        // e.g. "Your account is not approved yet!" after signing in with Keycloak
        Yii::$app->session->setFlash('error', 'Error');
        $this->assertTrue($this->login()->isValid);
    }

    public function testOtherAuthActionsAreNotRedirected()
    {
        $this->assertTrue($this->authAction('external')->isValid);
    }

    public function testInviteLinksRedirectToKeycloak()
    {
        $this->assertTrue($this->registration('index')->isValid);

        Yii::$app->request->setQueryParams(['token' => 'abc']);
        $this->assertFalse($this->registration('index')->isValid);
        $this->assertFalse($this->registration('by-link')->isValid);

        Yii::$app->request->setQueryParams(['token' => 'abc', 'noKeycloak' => 1]);
        $this->assertTrue($this->registration('index')->isValid);
    }

    private function configure(bool $forceKeycloakAuth): void
    {
        foreach ([
            'enabled' => true,
            'forceKeycloakAuth' => $forceKeycloakAuth,
            'baseUrl' => 'http://keycloak.test',
            'realm' => 'test',
            'clientId' => 'humhub',
            'clientSecret' => 'secret',
        ] as $name => $value) {
            Yii::$app->getModule('auth-keycloak')->settings->set($name, $value);
        }
        Yii::$app->authClientCollection->setClient(Keycloak::DEFAULT_NAME, ['class' => Keycloak::class]);
    }

    private function login(): ActionEvent
    {
        return $this->authAction('login');
    }

    private function authAction(string $actionId): ActionEvent
    {
        $event = new ActionEvent(new Action($actionId, new AuthController('auth', Yii::$app->getModule('user'))));
        Events::onAuthControllerBeforeAction($event);
        return $event;
    }

    private function registration(string $actionId): ActionEvent
    {
        $event = new ActionEvent(new Action($actionId, new RegistrationController('registration', Yii::$app->getModule('user'))));
        Events::onRegistrationControllerBeforeAction($event);
        return $event;
    }
}

<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 */

/*
 * Bootstraps the test runner: chains into the HumHub core test bootstrap so
 * the module's tests share the same Yii application + fixtures pipeline as
 * the core suite. Then starts the Keycloak test server (Docker container) if
 * no Keycloak is reachable, see KeycloakTestServer.
 */

use humhub\modules\authKeycloak\tests\codeception\_support\KeycloakTestServer;

$testRoot = dirname(__DIR__);

\Codeception\Configuration::append(['test_root' => $testRoot]);
codecept_debug('Module root: ' . $testRoot);

$humhubPath = getenv('HUMHUB_PATH');
if ($humhubPath === false) {
    $moduleConfig = require $testRoot . '/config/test.php';
    if (isset($moduleConfig['humhub_root'])) {
        $humhubPath = $moduleConfig['humhub_root'];
    } else {
        $humhubPath = dirname(__DIR__, 5);
    }
}

\Codeception\Configuration::append(['humhub_root' => $humhubPath]);
codecept_debug('HumHub Root: ' . $humhubPath);

$globalConfig = require $humhubPath . '/protected/humhub/tests/codeception/_loadConfig.php';
require $globalConfig['humhub_root'] . '/protected/humhub/tests/codeception/_bootstrap.php';

/*
 * Register the module's namespace alias. The module may be loaded via a module
 * autoload path rather than from `protected/modules/`, so the test runner would
 * not resolve the `humhub\modules\authKeycloak\*` classes on its own.
 */
Yii::setAlias('@humhub/modules/authKeycloak', dirname($testRoot));
Yii::setAlias('@auth-keycloak', dirname($testRoot));

require_once __DIR__ . '/_support/KeycloakTestServer.php';
KeycloakTestServer::start();

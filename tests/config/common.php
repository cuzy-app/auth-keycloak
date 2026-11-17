<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 */

/*
 * Shared test config. The module may be checked out outside of `protected/modules/`
 * (e.g. in a separate modules folder), so the directory containing it is added
 * to the module autoload paths.
 */

return [
    'params' => [
        'moduleAutoloadPaths' => [dirname(__DIR__, 3)],
    ],
];

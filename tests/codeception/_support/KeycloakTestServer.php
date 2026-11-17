<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 */

namespace humhub\modules\authKeycloak\tests\codeception\_support;

use RuntimeException;

/**
 * Keycloak server used by the tests.
 *
 * {@see start()} is called by the test bootstrap: if no Keycloak is reachable at {@see getUrl()},
 * a Keycloak Docker container is started (and removed at the end of the test run).
 * {@see resetRealm()} recreates the test realm before each test, with the Keycloak admin REST API.
 *
 * Environment variables:
 * - `KEYCLOAK_TEST_URL`: URL of an already running Keycloak (admin user `admin` / `admin`), no container is started
 * - `KEYCLOAK_TESTS=0`: skips the tests requiring Keycloak
 * - `KEYCLOAK_TEST_KEEP=1`: keeps the started container after the test run (faster local re-runs)
 */
class KeycloakTestServer
{
    public const IMAGE = 'quay.io/keycloak/keycloak:26.0.7';
    public const CONTAINER = 'auth-keycloak-tests';
    public const DEFAULT_URL = 'http://localhost:8181';
    public const ADMIN_USERNAME = 'admin';
    public const ADMIN_PASSWORD = 'admin';

    public const REALM = 'humhub-test';
    public const CLIENT_ID = 'humhub';
    public const CLIENT_SECRET = 'humhub-test-secret';
    public const USER_PASSWORD = 'secret';
    /**
     * User attribute sent as claim with the same name in the userinfo
     */
    public const USERINFO_ATTRIBUTE = 'phoneNumber';

    private const START_TIMEOUT = 180;

    private static ?string $unavailableReason = null;
    private static ?string $adminToken = null;
    private static int $adminTokenTime = 0;

    public static function getUrl(): string
    {
        return rtrim(getenv('KEYCLOAK_TEST_URL') ?: self::DEFAULT_URL, '/');
    }

    /**
     * @return string|null why Keycloak is not available, null if it is
     */
    public static function getUnavailableReason(): ?string
    {
        return self::$unavailableReason;
    }

    public static function start(): void
    {
        if (getenv('KEYCLOAK_TESTS') === '0') {
            self::$unavailableReason = 'disabled with KEYCLOAK_TESTS=0';
            return;
        }
        if (self::isReady()) {
            return;
        }
        if (getenv('KEYCLOAK_TEST_URL')) {
            self::$unavailableReason = 'not reachable at ' . self::getUrl();
            return;
        }

        if (self::docker(['version'])[0] !== 0) {
            self::$unavailableReason = 'Docker is not available to start a Keycloak container';
            return;
        }

        codecept_debug('Starting the Keycloak container ' . self::IMAGE);
        self::docker(['rm', '-f', self::CONTAINER]);
        [$exitCode, $output] = self::docker([
            'run', '-d', '--rm',
            '--name', self::CONTAINER,
            '-p', (parse_url(self::getUrl(), PHP_URL_PORT) ?: 80) . ':8080',
            '-e', 'KC_BOOTSTRAP_ADMIN_USERNAME=' . self::ADMIN_USERNAME,
            '-e', 'KC_BOOTSTRAP_ADMIN_PASSWORD=' . self::ADMIN_PASSWORD,
            self::IMAGE,
            'start-dev',
        ]);
        if ($exitCode !== 0) {
            self::$unavailableReason = 'the Keycloak container could not be started: ' . $output;
            return;
        }
        if (getenv('KEYCLOAK_TEST_KEEP') !== '1') {
            register_shutdown_function(static fn() => self::docker(['rm', '-f', self::CONTAINER]));
        }

        $start = time();
        while (!self::isReady()) {
            if (time() - $start > self::START_TIMEOUT) {
                self::$unavailableReason = 'the Keycloak container did not start within ' . self::START_TIMEOUT . ' seconds';
                return;
            }
            sleep(2);
        }
        codecept_debug('Keycloak started in ' . (time() - $start) . ' seconds');
    }

    /**
     * Recreates the test realm: a confidential client with a service account (having the roles required by the module)
     * and a userinfo mapper for {@see USERINFO_ATTRIBUTE}. Unmanaged user attributes are enabled.
     */
    public static function resetRealm(): void
    {
        self::adminRequest('DELETE', '/admin/realms/' . self::REALM, null, [204, 404]);
        self::adminRequest('POST', '/admin/realms', [
            'realm' => self::REALM,
            'enabled' => true,
            'editUsernameAllowed' => true,
        ]);

        // Decoded as objects, as empty JSON objects must stay objects
        $userProfile = self::request('GET', '/users/profile', null, [200], false);
        $userProfile->unmanagedAttributePolicy = 'ENABLED';
        self::request('PUT', '/users/profile', $userProfile);

        self::request('POST', '/clients', [
            'clientId' => self::CLIENT_ID,
            'secret' => self::CLIENT_SECRET,
            'publicClient' => false,
            'serviceAccountsEnabled' => true,
            'standardFlowEnabled' => true,
            'directAccessGrantsEnabled' => true,
            'redirectUris' => ['*'],
            'protocolMappers' => [[
                'name' => self::USERINFO_ATTRIBUTE,
                'protocol' => 'openid-connect',
                'protocolMapper' => 'oidc-usermodel-attribute-mapper',
                'config' => [
                    'user.attribute' => self::USERINFO_ATTRIBUTE,
                    'claim.name' => self::USERINFO_ATTRIBUTE,
                    'userinfo.token.claim' => 'true',
                    'id.token.claim' => 'true',
                    'access.token.claim' => 'true',
                    'jsonType.label' => 'String',
                ],
            ]],
        ]);

        $clientUuid = self::getClientUuid(self::CLIENT_ID);
        $serviceAccountUser = self::request('GET', '/clients/' . $clientUuid . '/service-account-user');
        $realmManagementUuid = self::getClientUuid('realm-management');
        $roles = array_values(array_filter(
            self::request('GET', '/clients/' . $realmManagementUuid . '/roles'),
            static fn($role) => in_array($role['name'], ['manage-users', 'view-users', 'query-groups', 'view-realm'], true),
        ));
        self::request('POST', '/users/' . $serviceAccountUser['id'] . '/role-mappings/clients/' . $realmManagementUuid, $roles);
    }

    /**
     * @param array $user Keycloak user representation
     * @return string Keycloak user ID
     */
    public static function createUser(array $user): string
    {
        $user += ['enabled' => true, 'emailVerified' => true];
        $user['credentials'] ??= [['type' => 'password', 'value' => self::USER_PASSWORD, 'temporary' => false]];
        self::request('POST', '/users', $user);
        return self::request('GET', '/users?exact=true&username=' . rawurlencode($user['username']))[0]['id'];
    }

    /**
     * @param array $data user representation fields to change (Keycloak validates the whole user profile on update)
     */
    public static function updateUser(string $userId, array $data): void
    {
        self::request('PUT', '/users/' . $userId, array_merge(self::request('GET', '/users/' . $userId), $data));
    }

    /**
     * @return string Keycloak group ID
     */
    public static function createGroup(string $name, ?string $parentId = null): string
    {
        if ($parentId !== null) {
            return self::request('POST', '/groups/' . $parentId . '/children', ['name' => $name])['id'];
        }
        self::request('POST', '/groups', ['name' => $name]);
        foreach (self::request('GET', '/groups?search=' . rawurlencode($name)) as $group) {
            if ($group['name'] === $name) {
                return $group['id'];
            }
        }
        throw new RuntimeException('Created group ' . $name . ' not found');
    }

    public static function addUserToGroup(string $userId, string $groupId): void
    {
        self::request('PUT', '/users/' . $userId . '/groups/' . $groupId);
    }

    /**
     * Gets tokens with the "password" grant, as the module would get them on sign in
     * @return array token response
     */
    public static function getUserTokens(string $username): array
    {
        return self::http('POST', self::getUrl() . '/realms/' . self::REALM . '/protocol/openid-connect/token', [
            'grant_type' => 'password',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
            'username' => $username,
            'password' => self::USER_PASSWORD,
            'scope' => 'openid',
        ], [200], null, true);
    }

    /**
     * Admin REST API request on the test realm
     * @param string $path relative to /admin/realms/<realm>
     * @return mixed decoded JSON response
     */
    public static function request(string $method, string $path, $body = null, array $expectedCodes = [200, 201, 204], bool $associative = true)
    {
        return self::adminRequest($method, '/admin/realms/' . self::REALM . $path, $body, $expectedCodes, $associative);
    }

    private static function getClientUuid(string $clientId): string
    {
        return self::request('GET', '/clients?clientId=' . rawurlencode($clientId))[0]['id'];
    }

    private static function adminRequest(string $method, string $path, $body = null, array $expectedCodes = [200, 201, 204], bool $associative = true)
    {
        // Admin tokens of the master realm expire after 60 seconds
        if (self::$adminToken === null || time() - self::$adminTokenTime > 30) {
            self::$adminToken = self::http('POST', self::getUrl() . '/realms/master/protocol/openid-connect/token', [
                'grant_type' => 'password',
                'client_id' => 'admin-cli',
                'username' => self::ADMIN_USERNAME,
                'password' => self::ADMIN_PASSWORD,
            ], [200], null, true)['access_token'];
            self::$adminTokenTime = time();
        }

        return self::http($method, self::getUrl() . $path, $body, $expectedCodes, self::$adminToken, false, $associative);
    }

    /**
     * @param array|object|null $body JSON body, or form parameters if $isForm
     * @return mixed decoded JSON response
     */
    private static function http(string $method, string $url, $body, array $expectedCodes, ?string $token = null, bool $isForm = false, bool $associative = true)
    {
        $curl = curl_init($url);
        $headers = [];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        if ($body !== null) {
            if ($isForm) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($body));
            } else {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
                $headers[] = 'Content-Type: application/json';
            }
        }
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException($method . ' ' . $url . ' returned HTTP ' . $code . ': ' . $response);
        }
        return $response === '' || $response === false ? null : json_decode($response, $associative);
    }

    private static function isReady(): bool
    {
        $curl = curl_init(self::getUrl() . '/realms/master');
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 2]);
        curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return $code === 200;
    }

    /**
     * Runs a Docker command without a shell
     * @param string[] $arguments
     * @return array [exit code, output]
     */
    private static function docker(array $arguments): array
    {
        $process = proc_open(array_merge(['docker'], $arguments), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return [1, 'could not run docker'];
        }
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), trim($output)];
    }
}

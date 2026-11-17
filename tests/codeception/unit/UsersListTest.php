<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 */

namespace humhub\modules\authKeycloak\tests\codeception\unit;

use humhub\modules\authKeycloak\components\KeycloakApi;
use tests\codeception\_support\HumHubDbTestCase;
use Yii;

class UsersListTest extends HumHubDbTestCase
{
    /**
     * With a user federation, Keycloak may return pages having less than the maximum results before the last one
     */
    public function testShortPagesBeforeTheLastOne()
    {
        // 250 users, the first page has 1 user less (returned again in the second page)
        $users = array_map(static fn($i) => ['id' => 'user-' . $i], range(1, 250));
        $api = $this->getApi($users, 250, static fn($first, $max) => $first === 0
            ? array_slice($users, 0, $max - 1)
            : array_slice($users, $first - 1, $max + 1));

        $list = $api->getAllUsers($isComplete);

        $this->assertTrue($isComplete);
        $this->assertCount(250, $list);
        $this->assertSame($users, $list);
    }

    /**
     * With a user federation, Keycloak may fail while sending a page going beyond the number of users of the realm
     * (HTTP 200 response ending with {"error":"unknown_error","error_description":"Cannot parse the JSON"})
     */
    public function testPagesDoNotGoBeyondTheNumberOfUsers()
    {
        $users = array_map(static fn($i) => ['id' => 'user-' . $i], range(1, 250));
        $requests = [];
        $api = $this->getApi($users, 250, static function ($first, $max) use ($users, &$requests) {
            $requests[] = [$first, $max];
            return $first < 250 && $first + $max > 250
                ? ['content' => '[{"id":"user-201"},{"id":"us{"error":"unknown_error","error_description":"Cannot parse the JSON"}']
                : array_slice($users, $first, $max);
        });

        $list = $api->getAllUsers($isComplete);

        $this->assertTrue($isComplete);
        $this->assertSame($users, $list);
        $this->assertSame([[0, 100], [100, 100], [200, 50], [250, 100]], $requests);
    }

    /**
     * Users beyond the number of users of the realm (e.g. of a user federation without import) are requested separately
     */
    public function testUsersBeyondTheNumberOfUsers()
    {
        $users = array_map(static fn($i) => ['id' => 'user-' . $i], range(1, 300));
        $api = $this->getApi($users, 150, static fn($first, $max) => array_slice($users, $first, $max));

        $list = $api->getAllUsers($isComplete);

        $this->assertTrue($isComplete);
        $this->assertSame($users, $list);
    }

    public function testIncompleteList()
    {
        $users = array_map(static fn($i) => ['id' => 'user-' . $i], range(1, 150));
        $api = $this->getApi($users, 200, static fn($first, $max) => array_slice($users, $first, $max));

        $list = $api->getAllUsers($isComplete);

        $this->assertFalse($isComplete);
        $this->assertCount(150, $list);
    }

    /**
     * The Keycloak client returns ['content' => <response body>] if the response is not valid JSON (e.g. truncated or invalid UTF-8)
     */
    public function testInvalidJsonPage()
    {
        $users = array_map(static fn($i) => ['id' => 'user-' . $i], range(1, 150));
        $api = $this->getApi($users, 150, static fn($first, $max) => $first === 0
            ? array_slice($users, 0, $max)
            : ['content' => '[{"id":"user-101"},{"id":"user-1']);

        static::logInitialize();
        $list = $api->getAllUsers($isComplete);

        $this->assertNull($list);
        $this->assertFalse($isComplete);
        static::assertLogRegexCount(1, '/^Error retrieving users from Keycloak: the response is not a JSON list \(.+, 32 bytes, ending with: .*\{"id":"user-1\)$/');
    }

    /**
     * If Keycloak fails while sending the response (e.g. while querying a user federation), it appends its error
     * to the response already sent with the HTTP status 200
     */
    public function testKeycloakErrorWhileSendingThePage()
    {
        $users = array_map(static fn($i) => ['id' => 'user-' . $i], range(1, 150));
        $api = $this->getApi($users, 150, static fn($first, $max) => $first === 0
            ? array_slice($users, 0, $max)
            : ['content' => '[{"id":"user-101"},{"id":"us{"error":"unknown_error","error_description":"Cannot parse the JSON"}']);

        static::logInitialize();
        $list = $api->getAllUsers($isComplete);

        $this->assertNull($list);
        static::assertLogRegexCount(1, '/^Error retrieving users from Keycloak: Keycloak failed while sending the response \(97 bytes received, see the Keycloak server log\): \{"error":"unknown_error","error_description":"Cannot parse the JSON"\}$/');
    }

    private function getApi(array $users, int $count, callable $getUsers): KeycloakApi
    {
        Yii::$app->getModule('auth-keycloak')->usersListPageSize = 100;
        $api = new class ($count) extends KeycloakApi {
            public function __construct(private int $count)
            {
                // No connection to Keycloak
            }

            public function getUserCount(): ?int
            {
                return $this->count;
            }
        };
        $api->realm = ['realm' => 'test'];
        $api->api = new class ($getUsers) {
            public function __construct(private $getUsers)
            {
            }

            public function getUsers(array $args): array
            {
                return ($this->getUsers)($args['first'], $args['max']);
            }
        };
        return $api;
    }
}

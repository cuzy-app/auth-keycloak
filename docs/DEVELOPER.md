# Developer

## Installation using Git

For a git based installation you'll need a `composer install` on the module root in order to fetch dependencies. If the module is packaged in a zip with dependencies, change PHP version on your development server to the minimal requirement (see https://docs.humhub.org/docs/admin/requirements) for the minimal HumHub version in module.json, and then update dependencies with `composer update`

## Keycloak API

- [Keycloak Admin client documentation](https://github.com/MohammadWaleed/keycloak-admin-client)
- [Keycloak API documentation - user commands](https://www.keycloak.org/docs-api/11.0/rest-api/#_users_resource)
- [Keycloak API documentation - user representation](https://www.keycloak.org/docs-api/11.0/rest-api/#_userrepresentation)

## Tests

The Codeception tests (`tests/`) run against a real Keycloak server. Before the tests, a Keycloak Docker container (`auth-keycloak-tests`, port 8181) is started if no Keycloak is reachable, and removed after the tests. The test realm is recreated before each test with the Keycloak admin REST API (see `tests/codeception/_support/KeycloakTestServer.php`).

From the `tests` folder of the module, with a [HumHub test environment](https://docs.humhub.org/docs/develop/testing):

```bash
export HUMHUB_PATH=/path/to/humhub
php $HUMHUB_PATH/protected/vendor/bin/codecept build
php $HUMHUB_PATH/protected/vendor/bin/codecept run unit
```

Environment variables:

- `KEYCLOAK_TEST_KEEP=1`: keeps the container after the tests (faster re-runs)
- `KEYCLOAK_TEST_URL=http://localhost:8080`: uses an already running Keycloak (admin user `admin` / `admin`) instead of starting a container
- `KEYCLOAK_TESTS=0`: skips the tests requiring Keycloak (e.g. without Docker)

The PHP `bcmath` (or `gmp`) extension is required. On GitHub, the tests run with the `Codeception Tests` workflows.

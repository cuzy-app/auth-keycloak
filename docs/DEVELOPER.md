# Developer

## Installation using Git

For a git based installation you'll need a `composer install` on the module root in order to fetch dependencies.
If the module is packaged in a zip with dependencies, change PHP version on your development server to the minimal requirement (see https://docs.humhub.org/docs/admin/requirements) for the minimal Humhub version in module.json, and then update dependencies with `composer update`

## Keycloak API

- [Keycloak Admin client documentation](https://github.com/MohammadWaleed/keycloak-admin-client)
- [Keycloak API documentation - user commands](https://www.keycloak.org/docs-api/11.0/rest-api/#_users_resource)
- [Keycloak API documentation - user representation](https://www.keycloak.org/docs-api/11.0/rest-api/#_userrepresentation)
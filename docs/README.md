# Keycloak Sign-In

Using this module, users can directly log in or register with [Keycloak](https://www.keycloak.org/) credentials at this HumHub installation.

A new button "Keycloak" (which can be renamed) will appear on the login page.

## Features

- OpenID Connect
- Keycloak Back-channel logout (1)
- Possibility to add a page in account settings allowing users to change their Keycloak password
- Users' groups and email synchronization between Keycloak and HumHub in both directions (2):
    - HumHub to Keycloak sync is done in real time
    - Keycloak to HumHub sync is done once a day
    - Keycloak subgroups are not synced

(1) Allows removing user sessions automatically when signing out from Keycloak (via a websocket).

(2) E.g., when a user on HumHub becomes member of a group the module will:
1. check if a group with the same name exists on Keycloak
2. create the group on Keycloak if not exists
3. add this group to the corresponding user on Keycloak

## Requirements

- PHP 8.1 or later
- [PHP `allow_url_fopen`](https://www.php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen) must be enabled
- PHP extensions: `MBString`, `JSON` and `BCMath` or `GMP`
- Depending on the algorithms you're using, other PHP extensions may be required (e.g. OpenSSL, Sodium). Full details: https://web-token.spomky-labs.com/introduction/pre-requisite
- For users' groups and email synchronization: on Keycloak, users attributes must be writable (it can be tested by changing the email address of a user on Keycloak administration).

## Configuration

Go to module's configuration at: `Administration -> Modules -> Keycloak Auth -> Configure`. And follow the instructions.

## Pricing

This module is free, but the result of a lot of work for design and maintenance over time.

If it's useful to you, please consider [making a donation](https://www.cuzy.app/checkout/donate/) or [participating in the code](https://github.com/cuzy-app/auth-keycloak). Thanks!

## Repository

https://github.com/cuzy-app/auth-keycloak

## Publisher

[CUZY.APP](https://www.cuzy.app/)

## Licence

[GNU AGPL](https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md)

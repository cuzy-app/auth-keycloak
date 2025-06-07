Changelog
=========

1.4.4 (June 7, 2025)
--------------------
- Chg Minimal HumHub version is now 1.17
- Enh: Upgrade mohammad-waleed/keycloak-admin-client (v0.37.0 => v0.38.0)
- Enh: Replace abandoned `web-token/jwt-key-mgmt` library with `web-token/jwt-library` v3.4.8

1.4.3 (January 23, 2025)
--------------------
- Chg: Repository URL from https://github.com/cuzy-app/humhub-modules-auth-keycloak to https://github.com/cuzy-app/auth-keycloak
- Enh: Use safe methods in migrations
- Enh: Add GitHub HumHub PHP workflows (tests & CS fixer)

1.4.2 (Feb 19, 2024)
--------------------
- Enh: Added the new `requirements.php` file (https://github.com/humhub/humhub/issues/6831)
- Chg: Require PHP 8.1 or later
- Fix: Added PHP extensions requirements: `MBString`, `JSON` and `BCMath` or `GMP`

1.4.1 (Jan 18, 2024)
--------------------
- Fix: API communication error with Keycloak 23: `cURL error 25: Chunky upload is not supported by HTTP 1.0`
- Chg: Updated library `mohammad-waleed/keycloak-admin-client` from 0.34 to 0.37

1.4.0 (Jan 16, 2024)
--------------------
- Chg: Require PHP 8.0 or later
- Enh: OpenID Connect instead of Oauth 2 protocol
- Enh: Added Back channel feature (see README and module configuration)
- Fix: PDOException: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'search.update.xxxxxxxxxxxxx' for key 'queue_exclusive.PRIMARY' i

1.3.1 (May 10, 2023)
--------------------
- Fix: On module install and uninstall, check if `keycloak_id` exists or not in the `group` table in case of previous improper install or uninstall
- Chg: Removed instructions about client scopes and mappers (useless with recent Keycloak versions)
- Chg: Default username mapper changed from `preferred_username` to `sub` (for new Keycloak installations)

1.3.0 (April 6, 2023)
--------------------
- Chg: Minimum HumHub version is now 1.14.0
- Chg: Removed auto-login feature
- Enh: Sync Keycloak user's attributes (groups, email and username) to HumHub on login (in addition to syncing on HumHub change)
- Enh: Compatibility with this new feature: Invitation by link: when registering within an SSO, the email should only be requested on the service provider (https://github.com/humhub/humhub/issues/6164)
- Enh: Username and email sync to Keycloak is now done by cron job
- Fix: Updated translations

1.2.4 (February 25, 2023)
--------------------
- Fix: Don't add to group a user that is not active
- Fix: PHP 8.1 warning in the module settings if empty values

1.2.3 (February 20, 2023)
--------------------
- Fix: For some users, the corresponding user ID on Keycloak was not saved in `user_auth` table, which prevents group sync from working.

1.2.2 (January 4, 2023)
--------------------
- Chg: If the realm is not "master" and the Keycloak API is configured, you need to move the admin user from the "master" to the realm where the client is configured. See hints of the "Keycloak API admin username" field.
- Fix #7: Naming issue in translations (thanks @francoisauclair911)

1.2.1 (December 29, 2022)
--------------------
- Enh: Added error log if API connection fails
- Chg: Connection to the API is now done with the "openid" scope, which solves connection issues on some Keycloak instances
- Enh: Added $apiVerifySsl boolean attribute in the Module class: when connecting to Keycloak API, check if SSL certificate is valid
- Enh: [keycloak-admin-client library](https://github.com/MohammadWaleed/keycloak-admin-client) updated to the latest version (0.34.0)

1.2.0 (December 16, 2022)
--------------------
- Fix: Added compatibility with Keycloak version 20+
- Enh: In the module settings, updated procedure to configure Keycloak client for Keycloak version >= 20
- Fix: Account edition page crashed if the authentification to Keycloak API failed
- Enh: Added a message in the module settings to inform if the authentification to Keycloak API succeeded or not

1.1.5 (December 12, 2022)
--------------------
- Enh: Added new setting checkbox: "In admin, hide password fields in edit user form"

1.1.4 (November 26, 2022)
--------------------
- Enh: `User::auth_mode` is set to `Keycloak` after login. Avoids showing the "Change Password" tab in the user account when logged in with Keycloak
- Chg: Minimum HumHub version to 1.12

1.1.3 (October 16, 2022)
--------------------
- Fix: Don't try to sync groups if API username and password are not defined in the settings

1.1.2 (June 29, 2022)
--------------------
- Enh: Possibility to sync username from HumHub to Keycloak, and reverse
- Enh: Some texts in the module's settings

1.1.1 (May 30, 2022)
--------------------
- Fix: Removing Keycloak session optimization on HumHub logout

1.1.0 (May 5, 2022)
--------------------
- Enh: Possibility to add a page in account settings allowing users to change their Keycloak password
- Enh: If the username sent by Keycloak is the user's email, it is replaced by a username auto-generated from the first and last name (CamelCase formatted)
- Enh: If a user is invited by email to a space, he is now redirected to the space at the end of the registration process. If legal module is enabled, needs https://github.com/humhub-contrib/legal/pull/49/files
- Fix: On the registration form, if the username field is hidden (in the module's settings) but has an error (e.g. already been taken), it is still displayed.

1.0.2 (April 8, 2022)
--------------------
- Fix: Error message after account creation if invited by email to a space
- Enh: Better errors handling on Keycloak API requests

1.0.1 (March 18, 2022)
--------------------
- Fix: API synchronization is now working on old Keycloak instances

1.0.0 (March 17, 2022)
--------------------
- Enh: Added groups synchronization (see README)
- Cnh: Settings page refactoring
- Chg: Minimum HumHub version is now 1.9

If updating from a previous version:
- If, on your previous version, you have "Attribute to match user tables with `email` or `id`" defined to `email`, as this version now uses only `id` attribute, you might have some users that cannot login anymore to HumHub if their email is different on HumHub and Keycloak. You should update their email on Keycloak to match.
- If updating without the marketplace, proceed to database migration: "Administration" -> "Information" -> "Database"

0.4.2 (March 14, 2022)
--------------------
- Chg: Compatibility with Keycloak 17+ as it has removed the default `auth/` in the base URL
- Chg: `mohammad-waleed/keycloak-admin-client` library updated to version 0.30
- Fix: If the API realm wasn't "master" it was not possible to use it as it is not possible to create an admin user in another realm than master
- Enh: Better explanations in the settings

0.4.1 (August 17, 2021)
--------------------
- Fix: In the settings form, autologin checkbox was not saved

0.4 (July 25, 2021)
--------------------
- Enh #2: Admin Settings Interface (thanks @ArchBlood)

0.3 (June 2, 2021)
--------------------
- Chg: Module renamed (uninstall old module, install new one and change module name in config/common.php)

0.2.1 (May, 21, 2021)
--------------------
- Fix: if invited (to a space or via admin -> users), do not hide username field as registration if not done with SSO

0.2 (April, 8, 2021)
--------------------
- Enh: Added auto login if new user is invited to space by email
- Enh: Added `hideRegistrationUsernameField` param in Keycloak client
- Enh: Added `removeKeycloakSessionsAfterLogout` param in Keycloak client

0.1 (January, 5, 2021)
--------------------
- Enh: Initial commit

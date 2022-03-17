Changelog
=========

1.0.0 (March 17, 2022)
--------------------
- Enh: Added groups synchronization (see README)
- Cnh: Settings page refactoring
- Chn: Minimum Humhub version is now 1.9

If updating from a previous version:
 - If, on your previous version, you have "Attribute to match user tables with `email` or `id`" defined to `email`, as this version now uses only `id` attribute, you might have some users that cannot login anymore to Humhub if their email is different on Humhub and Keycloak. You should update their email on Keycloak to match.
 - If updating without the marketplace, proceed to database migration: "Administration" -> "Information" -> "Database"


0.4.2 (March 14, 2022)
--------------------
- Chn: Compatibility with Keycloak 17+ as it has removed the default `auth/` in the base URL
- Chn: `mohammad-waleed/keycloak-admin-client` library updated to version 0.30
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
- Fix: if invited (to a space or via admin -> users), do not hide username field as registration is not done with SSO


0.2 (April, 8, 2021)
--------------------
- Enh: Added auto login if new user is invited to space by email
- Enh: Added `hideRegistrationUsernameField` param in Keycloak client
- Enh: Added `removeKeycloakSessionsAfterLogout` param in Keycloak client


0.1 (January, 5, 2021)
--------------------
- Enh: Initial commit
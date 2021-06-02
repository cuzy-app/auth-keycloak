Changelog
=========

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
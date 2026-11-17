# Installation

## 1. Install the module

Install and enable the module from `Administration -> Modules`, then open its configuration page
(`Administration -> Modules -> Keycloak Sign-In -> Configure`).

The configuration page shows the exact URLs of your installation (redirect URI, back-channel logout URL) to use in the steps below.

## 2. Create the client on Keycloak

In the Keycloak administration, select the realm of your users, then `Clients -> Create client`:

- Client type: `OpenID Connect`
- Client ID: e.g. `humhub`
- `Client authentication`: On (for Keycloak < 20: `Access Type`: `confidential`)
- `Standard flow`: On
- `Valid redirect URIs`: the "Redirect URI" shown on the module configuration page,
  e.g. `https://humhub.example.com/user/auth/external?authclient=Keycloak`

Then copy the secret from the `Credentials` tab.

### Optional: back-channel logout

To sign users out of HumHub automatically when they sign out from Keycloak, in the client's `Logout settings`:

- `Backchannel logout URL`: the URL shown on the module configuration page
- `Backchannel logout session required`: On
- `Backchannel logout revoke offline sessions`: On

## 3. Configure the module

On the module configuration page, enter:

- **Base URL**: e.g. `https://sso.example.com` (or `https://sso.example.com/auth` for older Keycloak versions)
- **Realm name**
- **Client ID** and **Client secret** of the client created above
- **Attribute mapping** (optional): e.g. `username=preferred_username` to use the Keycloak username instead of the Keycloak ID (`sub`, default) as HumHub username, and the profile fields to fill from Keycloak (see [MANUAL.md](MANUAL.md#attribute-mapping))

Enable the module and save. The "Keycloak" button now appears on the login page.

## 4. Optional: access to the Keycloak API

The following features need access to the Keycloak admin API:

| Feature | Access |
|---|---|
| Fetch/Update users automatically (users sync) | read |
| Keycloak to HumHub groups sync | read |
| HumHub to Keycloak groups sync | write |
| Update username / email on Keycloak when changed on HumHub | write |
| Change Keycloak password page in account settings | write |
| Deactivate the Keycloak account when the user is deleted on HumHub | write |
| Remove the user's Keycloak sessions after logout | write |

The module can access the API in two ways. The service account is recommended for new installations.

### Option A: service account of the client (recommended)

The module gets its API tokens with the client ID and secret already entered for the sign-in. No admin user and no password are needed, and the tokens are renewed automatically (cron jobs and console commands keep working without any action).

On Keycloak, in the HumHub client:

1. `Settings` tab -> `Capability config`: enable `Service accounts roles` (`Direct access grants` can stay disabled).
2. `Service accounts roles` tab -> `Assign role` -> `Filter by clients`: assign the roles of the `realm-management` client (see [Roles](#roles) below).

In the module configuration, under "Advanced settings requiring access to the Keycloak API":

- **Keycloak API authentication**: `Service account of the client (client ID and secret)`

### Option B: admin user (username and password)

The module gets its API tokens with the username and password of a Keycloak user (`password` grant).

On Keycloak:

1. In the HumHub client, `Settings` tab -> `Capability config`: enable `Direct access grants`.
2. `Users -> Add user` in the **same realm** as the one entered in the module configuration:
   - no required user actions
   - `Credentials` tab: set a password with `Temporary`: Off
3. `Role mapping` tab -> `Assign role` -> `Filter by clients`: assign the roles of the `realm-management` client (see [Roles](#roles) below).

In the module configuration:

- **Keycloak API authentication**: `Admin user (username and password)`
- **Keycloak API admin username** and **password**

Note: enabling `Direct access grants` on the client allows any user of the realm to get tokens for this client with their username and password only, bypassing the second factor of the browser login. If this matters, prefer option A, or restrict this flow to the API user with a dedicated `Direct Grant Flow` in the client's `Advanced -> Authentication flow overrides`.

### Roles

Roles of the `realm-management` client to assign to the service account or to the admin user:

- **Read-only** (users sync and Keycloak to HumHub groups sync): `view-realm`, `view-users`, `query-groups`.
  Then enable **The API user or service account has only read permissions** in the module configuration: the settings requiring write access are disabled.
- **Read and write** (all features): additionally `manage-users`. If your realm is `master`, the `admin` realm role can be used instead.

### Test the API access

After saving the module configuration, the message "Authentication to Keycloak API succeeded!" must be displayed.
Otherwise, check the error log (`Administration -> Information -> Logging`).

From the HumHub root folder, list the Keycloak users seen by the module:

```bash
php protected/yii auth-keycloak/list-users
```

You can also test the token request with curl:

```bash
# Option A: service account
curl -s -X POST "https://sso.example.com/realms/<realm>/protocol/openid-connect/token" \
  -d grant_type=client_credentials -d client_id=<client-id> -d client_secret=<client-secret>

# Option B: admin user
curl -s -X POST "https://sso.example.com/realms/<realm>/protocol/openid-connect/token" \
  -d grant_type=password -d client_id=<client-id> -d client_secret=<client-secret> \
  -d username=<api-username> -d password=<api-password>
```

The response must contain an `access_token`. The error `Client not allowed for direct access grants` (option B) means that `Direct access grants` is disabled on the client.

## 5. Cron jobs

The users sync (hourly) and the Keycloak to HumHub groups sync (daily) run as HumHub background jobs: make sure the [HumHub cron jobs](https://docs.humhub.org/docs/admin/cron-jobs) are configured.

## Advanced module properties

These properties can be set in `protected/config/common.php`:

```php
return [
    'modules' => [
        'auth-keycloak' => [
            // Check the SSL certificate of the Keycloak API (default: true)
            'apiVerifySsl' => true,
            // Maximum duration in seconds of a request to the Keycloak API (default: 10, 0 to wait indefinitely)
            'apiTimeout' => 10,
            // Same, for requests from the console: background jobs such as the users sync, and console commands (default: 600, 0 to wait indefinitely)
            'apiSyncTimeout' => 600,
            // Number of users per request when listing all the Keycloak users or the members of a group (default: 500). With a user federation (e.g. LDAP), larger pages give a more complete list, but each request takes longer
            'usersListPageSize' => 500,
            // Maximum duration in seconds to connect to the Keycloak API (default: 5, 0 to wait indefinitely)
            'apiConnectTimeout' => 5,
            // Register the Keycloak user source (default: true, required for the users sync)
            'provideUserSource' => true,
        ],
    ],
];
```

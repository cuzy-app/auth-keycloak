# Keycloak Sign-In

Using this module, users can directly log in or register with their [Keycloak](https://www.keycloak.org/) credentials at this HumHub installation. 
A new button "Keycloak" (can be renamed) will appear on the login page.


## Features

- Auto login
- Users' groups and email synchronization between Keycloak and Humhub in both directions (1):
  - Humhub to Keycloak sync is done in real time
  - Keycloak to Humhub sync is done once a day
  - Keycloak subgroups are not synced

(1) E.g. when a user on Humhub becomes member of a group the module will:
1. check if a group with the same name exists on Keycloak
2. create the group on Keycloak if not exists
3. add this group to the corresponding user on Keycloak


## Requirements

For auto login: on Humhub, anonymous registration must be allowed
For users' groups and email synchronization: on Keycloak, users attributes must be writable (it can be tested by changing the email address of a user on Keycloak administration).


## Configuration

Go to module's configuration at: `Administration -> Modules -> Keycloak Auth -> Settings`. 
And follow the instructions.


## Repository

https://github.com/cuzy-app/humhub-modules-auth-keycloak


## Publisher

[CUZY.APP](https://www.cuzy.app/)


## Licence

[GNU AGPL](https://github.com/cuzy-app/humhub-modules-auth-keycloak/blob/master/docs/LICENCE.md)
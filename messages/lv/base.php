<?php
return array (
  '<strong>Keycloak</strong> Sign-In configuration' => '<strong>Keycloak</strong> pierakstīšanās konfigurācija',
  'Add a page in account settings allowing users to change their Keycloak password' => 'Pievienojiet lapu konta iestatījumos, kas ļauj lietotājiem mainīt Keycloak paroli',
  'Advanced settings (optional)' => 'Papildu iestatījumi (neobligāti)',
  'Advanced settings requiring an admin user for the API (optional)' => 'Papildu iestatījumi, kuru izmantošanai API ir nepieciešams administrators (neobligāti)',
  'Authentication to Keycloak API failed!' => 'Keycloak API autentifikācija neizdevās!',
  'Authentication to Keycloak API succeeded!' => 'Autentifikācija Keycloak API bija veiksmīga!',
  'Base URL' => 'Pamata URL',
  'Called {nameInEnglish} in english' => 'Angļu valodā sauc {nameInEnglish} angļu valodā',
  'Change password on {keycloakRealmDisplayName}' => 'Mainiet paroli vietnē {keycloakRealmDisplayName}',
  'Client ID' => 'Klienta ID',
  'Client secret is in the "Credentials" tab (if in the settings "Access Type" is set to "confidential")' => 'Klienta noslēpums atrodas cilnē "Akreditācijas dati" (ja iestatījumos "Piekļuves veids" ir iestatīts uz "konfidenciāli")',
  'Client secret key' => 'Klienta slepenā atslēga',
  'Confirm new password' => 'Apstipriniet jauno paroli',
  'Enable this auth client' => 'Iespējot šo autentifikācijas klientu',
  'For administrators allowed to manage users' => 'Administratoriem, kam atļauts pārvaldīt lietotājus',
  'Hide username field in registration form' => 'Slēpt lietotājvārda lauku reģistrācijas formā',
  'HumHub to Keycloak sync is done in real time. Keycloak to HumHub sync is done once a day. Keycloak subgroups are not synced.' => 'HumHub un Keycloak sinhronizācija tiek veikta reāllaikā. Keycloak sinhronizācija ar HumHub tiek veikta reizi dienā. Keycloak apakšgrupas netiek sinhronizētas.',
  'If the username sent by Keycloak is the user\'s email, it is replaced by a username auto-generated from the first and last name (CamelCase formatted)' => 'Ja Keycloak nosūtītais lietotājvārds ir lietotāja e-pasts, tas tiek aizstāts ar lietotājvārdu, kas automātiski ģenerēts no vārda un uzvārda (CamelCase formatēts)',
  'If you set a custom title, it will not be translated to the user\'s language unless you have a custom translation file in the protected/config folder. Leave blank to set default title.' => 'Ja iestatāt pielāgotu nosaukumu, tas netiks tulkots lietotāja valodā, ja vien aizsargātajā/konfigurācijas mapē nebūs pielāgota tulkošanas faila. Atstājiet tukšu, lai iestatītu noklusējuma nosaukumu.',
  'If you want to enable {BackChannelLogout} (which allows removing user sessions automatically when signing out from Keycloak), configure the client {LogoutSettings}:' => 'Ja vēlaties iespējot {BackChannelLogout} (kas ļauj automātiski noņemt lietotāju sesijas, izrakstoties no Keycloak), konfigurējiet klienta {LogoutSettings} :',
  'In admin, hide password fields in edit user form' => 'Administratorā paslēpiet paroles laukus lietotāja rediģēšanas veidlapā',
  'Keycloak API admin password' => 'Keycloak API administratora parole',
  'Keycloak API admin username' => 'Keycloak API administratora lietotājvārds',
  'Keycloak attribute to use to get username on account creation' => 'Keycloak atribūts, kas jāizmanto, lai iegūtu lietotājvārdu konta izveides laikā',
  'More informations here.' => 'Vairāk informācijas šeit.',
  'New password' => 'Jauna parole',
  'No sync' => 'Nav sinhronizācijas',
  'On Keycloak, create a client for HumHub and configure it:' => 'Programmā Keycloak izveidojiet HumHub klientu un konfigurējiet to:',
  'Password confirmation does not match.' => 'Paroles apstiprinājums neatbilst.',
  'Realm name' => 'Valdības nosaukums',
  'Remove user\'s Keycloak sessions after logout' => 'Pēc atteikšanās noņemiet lietotāja Keycloak sesijas',
  'Sync HumHub towards Keycloak' => 'Sinhronizējiet HumHub ar Keycloak',
  'Sync HumHub towards Keycloak (but no removal on Keycloak)' => 'Sinhronizēt HumHub ar Keycloak (bet nenoņemt no Keycloak)',
  'Sync Keycloak towards HumHub' => 'Sinhronizējiet Keycloak ar HumHub',
  'Sync Keycloak towards HumHub (but no removal on HumHub)' => 'Sinhronizēt Keycloak ar HumHub (bet bez noņemšanas no HumHub)',
  'Sync both ways' => 'Sinhronizējiet abos veidos',
  'Sync both ways (but no removal on HumHub)' => 'Sinhronizēt abos veidos (bet bez noņemšanas pakalpojumā HumHub)',
  'Sync both ways (but no removal on Keycloak or HumHub)' => 'Sinhronizēt abos veidos (bet nenoņemt Keycloak vai HumHub)',
  'Sync both ways (but no removal on Keycloak)' => 'Sinhronizēt abos veidos (bet nenoņemt Keycloak)',
  'Synchronize groups and their members' => 'Sinhronizējiet grupas un to dalībniekus',
  'The client id provided by Keycloak' => 'Keycloak nodrošinātais klienta ID',
  'The new password could not be saved.' => 'Jauno paroli nevarēja saglabāt.',
  'This admin user must be created in the same realm as the one entered in the {RealmName} field. If your realm is {masterRealmName}, just assign the {adminRoleName} role to this user. Otherwise, you need to add the {realmManagementClientRole} Client Role and assign all Roles. {MoreInformationHere}' => 'Šis administratora lietotājs ir jāizveido tajā pašā jomā, kurā ir ievadīts laukā {RealmName} . Ja jūsu sfēra ir {masterRealmName} , vienkārši piešķiriet šim lietotājam lomu {adminRoleName} . Pretējā gadījumā jums ir jāpievieno {realmManagementClientRole} klienta loma un jāpiešķir visas lomas. {MoreInformationHere}',
  'Title of the button' => 'Pogas nosaukums',
  'Update user\'s email on HumHub when changed on Keycloak' => 'Atjauniniet lietotāja e-pasta adresi HumHub, kad tas ir mainīts Keycloak',
  'Update user\'s email on Keycloak when changed on HumHub' => 'Atjauniniet lietotāja e-pasta adresi Keycloak, kad izmaiņas tiek veiktas HumHub',
  'Update user\'s username on HumHub when changed on Keycloak' => 'Atjauniniet lietotāja lietotājvārdu HumHub, kad tas ir mainīts Keycloak',
  'Update user\'s username on Keycloak when changed on HumHub' => 'Atjauniniet lietotāja lietotājvārdu Keycloak, kad tas ir mainīts HumHub',
  'View error log' => 'Skatīt kļūdu žurnālu',
  'Will only work if in Keycloak\'s realm settings "Email as username" is disabled and "Edit username" is enabled.' => 'Darbosies tikai tad, ja Keycloak sfēras iestatījumos ir atspējots “E-pasts kā lietotājvārds” un ir iespējota opcija “Rediģēt lietotājvārdu”.',
  'Your current password can be changed here.' => 'Šeit var mainīt savu pašreizējo paroli.',
  '`preferred_username` (to use Keycloak username), `sub` (to use Keycloak ID) or other custom Token Claim Name' => '`preferred_username` (lai izmantotu Keycloak lietotājvārdu), `sub` (lai izmantotu Keycloak ID) vai cits pielāgots Token Claim nosaukums',
  '{Credentials} tab: copy the secret key' => 'Cilne {Credentials} dati: kopējiet slepeno atslēgu',
  '{Settings} tab -> {ClientAuthenticationOn} (for Keycloak version <20: {AccessTypeValue}).' => 'Cilne {Settings} —> {ClientAuthenticationOn} (Humhub versijai <20: {AccessTypeValue} ).',
  '{Settings} tab -> {ValidRedirectURIsValue}.' => 'Cilne {Settings} -> {ValidRedirectURIsValue} .',
);

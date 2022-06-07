<?php
return array (
  '<strong>Keycloak</strong> Sign-In configuration' => '<strong>Keycloak</strong> bejelentkezési konfiguráció',
  'Add a page in account settings allowing users to change their Keycloak password' => 'Adjon hozzá egy oldalt a fiókbeállításokhoz, amely lehetővé teszi a felhasználók számára, hogy módosítsák Keycloak jelszavukat',
  'Advanced settings (optional)' => 'Speciális beállítások (opcionális)',
  'Advanced settings requiring an admin user for the API (optional)' => 'Speciális beállítások, amelyekhez adminisztrátori felhasználó szükséges az API-hoz (opcionális)',
  'Automatic login' => 'Automatikus bejelentkezés',
  'Base URL' => 'Alap URL',
  'Button {AddBuiltin} and check theses attributes:' => 'Nyomja {AddBuiltin} gombot, és ellenőrizze az alábbi attribútumokat:',
  'Called {nameInEnglish} in english' => '{nameInEnglish} English-nek hívják',
  'Change password on {keycloakRealmDisplayName}' => 'Módosítsa a jelszót a {keycloakRealmDisplayName}',
  'Client ID' => 'Ügyfélazonosító',
  'Client secret is in the "Credentials" tab (if in the settings "Access Type" is set to "confidential")' => 'Az ügyfél titkossága a "Hitelesítési adatok" lapon található (ha a beállításokban a "Hozzáférés típusa" "bizalmas" értékre van állítva)',
  'Client secret key' => 'Kliens titkos kulcsa',
  'Confirm new password' => 'Erősítsd meg az új jelszót',
  'Edit {usernameAttribute} and in {TokenClaimName}, replace {preferredUsernameAttribute} with {idAttribute}' => 'Szerkessze a {usernameAttribute} elemet, és a {TokenClaimName} cserélje ki a {preferredUsernameAttribute} {idAttribute} -re',
  'Enable this auth client' => 'Engedélyezze ezt a hitelesítési klienst',
  'Hide username field in registration form' => 'Felhasználónév mező elrejtése a regisztrációs űrlapon',
  'Humhub to Keycloak sync is done in real time. Keycloak to Humhub sync is done once a day. Keycloak subgroups are not synced.' => 'A Humhub és a Keycloak közötti szinkronizálás valós időben történik. A Keycloak és a Humhub szinkronizálása naponta egyszer történik. A Keycloak alcsoportok nincsenek szinkronizálva.',
  'If enabled, you should also enable {removeKeycloakSessionsAfterLogoutAttrLabel}, otherwise users cannot logout.' => 'Ha engedélyezve van, engedélyeznie kell a {removeKeycloakSessionsAfterLogoutAttrLabel} is, ellenkező esetben a felhasználók nem tudnak kijelentkezni.',
  'If the username sent by Keycloak is the user\'s email, it is replaced by a username auto-generated from the first and last name (CamelCase formatted)' => 'Ha a Keycloak által küldött felhasználónév a felhasználó e-mail-címe, akkor azt egy, a vezeték- és utónévből automatikusan generált felhasználónév váltja fel (CamelCase formátumban).',
  'If you set a custom title, it will not be translated to the user\'s language unless you have a custom translation file in the protected/config folder. Leave blank to set default title.' => 'Ha egyéni címet ad meg, az nem lesz lefordítva a felhasználó nyelvére, hacsak nincs egyéni fordítási fájlja a védett/konfigurációs mappában. Hagyja üresen az alapértelmezett cím beállításához.',
  'Keycloak API admin password' => 'Keycloak API rendszergazdai jelszó',
  'Keycloak API admin username' => 'Keycloak API adminisztrátori felhasználónév',
  'Keycloak attribute to use to get username on account creation' => 'Keycloak attribútum a felhasználónév lekéréséhez a fiók létrehozásakor',
  'New password' => 'Új jelszó',
  'No sync' => 'Nincs szinkronizálás',
  'On Keycloak, create a client for Humhub and configure it:' => 'A Keycloakon hozzon létre egy klienst a Humhub számára, és konfigurálja azt:',
  'Password confirmation does not match.' => 'A jelszó megerősítése nem egyezik.',
  'Possible only if {newUsersCanRegister} is allowed in Administration -> Users -> Settings.' => 'Csak akkor lehetséges, ha a {newUsersCanRegister} engedélyezve van az Adminisztráció -> Felhasználók -> Beállítások menüpontban.',
  'Realm name' => 'Birodalmi név',
  'Remove user\'s Keycloak sessions after logout' => 'Távolítsa el a felhasználó Keycloak munkameneteit a kijelentkezés után',
  'Sync Humhub towards Keycloak' => 'Szinkronizálja a Humhubot a Keycloakkal',
  'Sync Humhub towards Keycloak (but no removal on Keycloak)' => 'A Humhub szinkronizálása a Keycloakkal (de nincs eltávolítása a Keycloakon)',
  'Sync Keycloak towards Humhub' => 'A Keycloak szinkronizálása a Humhub felé',
  'Sync Keycloak towards Humhub (but no removal on Humhub)' => 'Keycloak szinkronizálása a Humhub felé (de nincs eltávolítása a Humhubról)',
  'Sync both ways' => 'Szinkronizálás mindkét irányban',
  'Sync both ways (but no removal on Humhub)' => 'Szinkronizálás mindkét irányban (de nincs eltávolítása a Humhubon)',
  'Sync both ways (but no removal on Keycloak or Humhub)' => 'Szinkronizálás mindkét irányban (de nincs eltávolítása a Keycloakon vagy a Humhubon)',
  'Sync both ways (but no removal on Keycloak)' => 'Szinkronizálás mindkét irányban (de nincs eltávolítása a Keycloakon)',
  'Synchronize groups and their members' => 'Csoportok és tagjaik szinkronizálása',
  'The client id provided by Keycloak' => 'A Keycloak által biztosított ügyfél-azonosító',
  'The new password could not be saved.' => 'Az új jelszót nem sikerült elmenteni.',
  'This admin user must be in the {master} realm and have permission to manage users of the realm belonging to the client for this Humhub' => 'Ennek az adminisztrátornak a {master} tartományban kell lennie, és jogosultsággal kell rendelkeznie a klienshez tartozó tartomány felhasználóinak kezelésére ehhez a Humhubhoz',
  'Title of the button (if autoLogin is disabled)' => 'A gomb címe (ha az automatikus bejelentkezés le van tiltva)',
  'Update user\'s email on Humhub when changed on Keycloak' => 'Frissítse a felhasználó e-mail-címét a Humhubon, ha módosítja a Keycloakon',
  'Update user\'s email on Keycloak when changed on Humhub' => 'Frissítse a felhasználó e-mail-címét a Keycloakon, ha módosítja a Humhubon',
  'Update user\'s username on Humhub when changed on Keycloak' => 'Frissítse a felhasználó felhasználónevét a Humhubon, ha módosítja a Keycloakon',
  'Update user\'s username on Keycloak when changed on Humhub' => 'Frissítse a felhasználó felhasználónevét a Keycloakon, ha megváltozott a Humhubon',
  'Will only work if in Keycloak\'s realm settings "Email as username" is disabled and "Edit username" is enabled.' => 'Csak akkor működik, ha a Keycloak tartomány beállításaiban az „E-mail mint felhasználónév” le van tiltva, és a „Felhasználónév szerkesztése” engedélyezve van.',
  'Your current password can be changed here.' => 'Jelenlegi jelszava itt módosítható.',
  '`preferred_username` (to use Keycloak username), `sub` (to use Keycloak ID) or other custom Token Claim Name' => '"preferred_username" (a Keycloak felhasználónév használatához), "sub" (a Keycloak ID használatához) vagy más egyéni Token Claim név',
  '{Credentials} tab: copy the secret key' => '{Credentials} lap: másolja ki a titkos kulcsot',
  '{Mappers} tab:' => '{Mappers} lap:',
  '{Settings} tab -> {AccessType}: choose {confidential}. Save settings.' => '{Settings} lap -> {AccessType} : válassza a {confidential} lehetőséget. Beállítások mentése.',
);

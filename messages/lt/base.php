<?php
return array (
  '<strong>Keycloak</strong> Sign-In configuration' => '<strong>„Keycloak“</strong> prisijungimo konfigūracija',
  'Add a page in account settings allowing users to change their Keycloak password' => 'Paskyros nustatymuose pridėkite puslapį, kuriame naudotojai gali pakeisti „Keycloak“ slaptažodį',
  'Advanced settings (optional)' => 'Išplėstiniai nustatymai (pasirenkama)',
  'Advanced settings requiring an admin user for the API (optional)' => 'Išplėstiniai nustatymai, kuriems reikalingas API administratorius (neprivaloma)',
  'Automatic login' => 'Automatinis prisijungimas',
  'Base URL' => 'Bazinis URL',
  'Button {AddBuiltin} and check theses attributes:' => 'Mygtukas {AddBuiltin} ir patikrinkite šiuos atributus:',
  'Called {nameInEnglish} in english' => 'Angliškai vadinamas {nameInEnglish}',
  'Change password on {keycloakRealmDisplayName}' => 'Pakeiskite slaptažodį „ {keycloakRealmDisplayName} “.',
  'Client ID' => 'Kliento ID',
  'Client secret is in the "Credentials" tab (if in the settings "Access Type" is set to "confidential")' => 'Kliento paslaptis yra skirtuke „Kredencialai“ (jei nustatymuose „Prieigos tipas“ nustatytas į „konfidencialu“)',
  'Client secret key' => 'Kliento slaptasis raktas',
  'Confirm new password' => 'Patvirtinti naują slaptažodį',
  'Edit {usernameAttribute} and in {TokenClaimName}, replace {preferredUsernameAttribute} with {idAttribute}' => 'Redaguokite {usernameAttribute} vardąAttribute ir {TokenClaimName} pakeiskite {preferredUsernameAttribute} naudotojo vardo požymį į {idAttribute}',
  'Enable this auth client' => 'Įgalinti šį autentifikavimo klientą',
  'Hide username field in registration form' => 'Slėpti vartotojo vardo laukelį registracijos formoje',
  'Humhub to Keycloak sync is done in real time. Keycloak to Humhub sync is done once a day. Keycloak subgroups are not synced.' => '„Humhub“ ir „Keycloak“ sinchronizavimas atliekamas realiuoju laiku. Keycloak to Humhub sinchronizavimas atliekamas kartą per dieną. Keycloak pogrupiai nėra sinchronizuojami.',
  'If enabled, you should also enable {removeKeycloakSessionsAfterLogoutAttrLabel}, otherwise users cannot logout.' => 'Jei įjungta, taip pat turėtumėte įgalinti {removeKeycloakSessionsAfterLogoutAttrLabel} , kitaip vartotojai negalės atsijungti.',
  'If the username sent by Keycloak is the user\'s email, it is replaced by a username auto-generated from the first and last name (CamelCase formatted)' => 'Jei „Keycloak“ atsiųstas vartotojo vardas yra vartotojo el. pašto adresas, jis pakeičiamas vartotojo vardu, automatiškai sugeneruotu iš vardo ir pavardės („CamelCase“ formato)',
  'If you set a custom title, it will not be translated to the user\'s language unless you have a custom translation file in the protected/config folder. Leave blank to set default title.' => 'Jei nustatysite pasirinktinį pavadinimą, jis nebus išverstas į vartotojo kalbą, nebent apsaugotame / konfigūracijos aplanke turėsite pasirinktinį vertimo failą. Palikite tuščią, kad nustatytumėte numatytąjį pavadinimą.',
  'Keycloak API admin password' => 'Keycloak API administratoriaus slaptažodis',
  'Keycloak API admin username' => 'Keycloak API administratoriaus vartotojo vardas',
  'Keycloak attribute to use to get username on account creation' => 'Keycloak atributas, naudojamas norint gauti vartotojo vardą kuriant paskyrą',
  'New password' => 'Naujas Slaptažodis',
  'No sync' => 'Nėra sinchronizavimo',
  'On Keycloak, create a client for Humhub and configure it:' => '„Keycloak“ sukurkite „Humhub“ klientą ir sukonfigūruokite jį:',
  'Password confirmation does not match.' => 'Slaptažodžio patvirtinimas nesutampa.',
  'Possible only if {newUsersCanRegister} is allowed in Administration -> Users -> Settings.' => 'Galima tik tuo atveju, jei {newUsersCanRegister} " yra leidžiama Administravimas -> Vartotojai -> Nustatymai.',
  'Realm name' => 'Sferos pavadinimas',
  'Remove user\'s Keycloak sessions after logout' => 'Atsijungę pašalinkite vartotojo „Keycloak“ seansus',
  'Sync Humhub towards Keycloak' => 'Sinchronizuokite „Humhub“ su „Keycloak“.',
  'Sync Humhub towards Keycloak (but no removal on Keycloak)' => 'Sinchronizuoti „Humhub“ su „Keycloak“ (bet nepašalinti iš „Keycloak“)',
  'Sync Keycloak towards Humhub' => 'Sinchronizuoti Keycloak su Humhub',
  'Sync Keycloak towards Humhub (but no removal on Humhub)' => 'Sinchronizuoti „Keycloak“ su „Humhub“ (bet nepašalinti „Humhub“)',
  'Sync both ways' => 'Sinchronizuoti abiem būdais',
  'Sync both ways (but no removal on Humhub)' => 'Sinchronizuoti abiem būdais (bet nepašalinti „Humhub“)',
  'Sync both ways (but no removal on Keycloak or Humhub)' => 'Sinchronizuoti abiem būdais (bet nepašalinti „Keycloak“ ar „Humhub“)',
  'Sync both ways (but no removal on Keycloak)' => 'Sinchronizuoti abiem būdais (bet nepašalinti „Keycloak“)',
  'Synchronize groups and their members' => 'Sinchronizuoti grupes ir jų narius',
  'The client id provided by Keycloak' => '„Keycloak“ pateiktas kliento ID',
  'The new password could not be saved.' => 'Naujo slaptažodžio išsaugoti nepavyko.',
  'This admin user must be in the {master} realm and have permission to manage users of the realm belonging to the client for this Humhub' => 'Šis administratoriaus vartotojas turi būti {master} srityje ir turėti leidimą valdyti klientui priklausančios srities naudotojus, skirtus šiam Humhub',
  'Title of the button (if autoLogin is disabled)' => 'Mygtuko pavadinimas (jei automatinis prisijungimas išjungtas)',
  'Update user\'s email on Humhub when changed on Keycloak' => 'Atnaujinkite naudotojo el. paštą „Humhub“, kai pakeisite „Keycloak“.',
  'Update user\'s email on Keycloak when changed on Humhub' => 'Atnaujinkite vartotojo el. paštą „Keycloak“, kai pakeisite „Humhub“.',
  'Update user\'s username on Humhub when changed on Keycloak' => 'Atnaujinkite vartotojo vardą „Humhub“, kai pakeisite „Keycloak“.',
  'Update user\'s username on Keycloak when changed on Humhub' => 'Atnaujinkite vartotojo vardą „Keycloak“, kai jis pakeistas „Humhub“.',
  'Will only work if in Keycloak\'s realm settings "Email as username" is disabled and "Edit username" is enabled.' => 'Veiks tik tuo atveju, jei „Keycloak“ srities nustatymuose „El. paštas kaip vartotojo vardas“ išjungtas ir „Redaguoti naudotojo vardą“ įjungta.',
  'Your current password can be changed here.' => 'Dabartinį slaptažodį galite pakeisti čia.',
  '`preferred_username` (to use Keycloak username), `sub` (to use Keycloak ID) or other custom Token Claim Name' => '„Preferred_username“ (norint naudoti „Keycloak“ naudotojo vardą), „sub“ (naudoti „Keycloak ID“) arba kitas tinkintas prieigos rakto reikalavimo pavadinimas',
  '{Credentials} tab: copy the secret key' => '{Credentials} skirtukas: nukopijuokite slaptąjį raktą',
  '{Mappers} tab:' => '{Mappers} skirtukas:',
  '{Settings} tab -> {AccessType}: choose {confidential}. Save settings.' => 'Skirtukas {Settings} -> {AccessType} : pasirinkite {confidential} . Išsaugoti nustatymus.',
);

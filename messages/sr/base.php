<?php
return array (
  '<strong>Keycloak</strong> Sign-In configuration' => '<strong>Кеицлоак</strong> конфигурација за пријаву',
  'Add a page in account settings allowing users to change their Keycloak password' => 'Додајте страницу у подешавања налога која омогућава корисницима да промене своју Кеицлоак лозинку',
  'Advanced settings (optional)' => 'Напредна подешавања (опционо)',
  'Advanced settings requiring an admin user for the API (optional)' => 'Напредна подешавања која захтевају администраторског корисника за АПИ (опционално)',
  'Authentication to Keycloak API failed!' => 'Аутентификација за Кеицлоак АПИ није успела!',
  'Authentication to Keycloak API succeeded!' => 'Аутентификација за Кеицлоак АПИ је успела!',
  'Base URL' => 'Основни УРЛ',
  'Called {nameInEnglish} in english' => 'На енглеском се зове {nameInEnglish}',
  'Change password on {keycloakRealmDisplayName}' => 'Промените лозинку на {keycloakRealmDisplayName}',
  'Client ID' => 'ИД клијента',
  'Client secret is in the "Credentials" tab (if in the settings "Access Type" is set to "confidential")' => 'Тајна клијента је на картици „Акредитиви“ (ако је у подешавањима „Тип приступа“ подешен на „поверљиво“)',
  'Client secret key' => 'Тајни кључ клијента',
  'Confirm new password' => 'Потврдите нову лозинку',
  'Enable this auth client' => 'Омогући овог клијента за аутентификацију',
  'For administrators allowed to manage users' => 'За администраторе којима је дозвољено да управљају корисницима',
  'Hide username field in registration form' => 'Сакриј поље за корисничко име у обрасцу за регистрацију',
  'HumHub to Keycloak sync is done in real time. Keycloak to HumHub sync is done once a day. Keycloak subgroups are not synced.' => 'Синхронизација ХумХуб-а са Кеицлоак-ом се обавља у реалном времену. Кеицлоак са ХумХуб синхронизацијом се обавља једном дневно. Кеицлоак подгрупе нису синхронизоване.',
  'If the username sent by Keycloak is the user\'s email, it is replaced by a username auto-generated from the first and last name (CamelCase formatted)' => 'Ако је корисничко име које шаље Кеицлоак корисникова е-пошта, замењује се корисничким именом аутоматски генерисаним од имена и презимена (у формату ЦамелЦасе)',
  'If you set a custom title, it will not be translated to the user\'s language unless you have a custom translation file in the protected/config folder. Leave blank to set default title.' => 'Ако поставите прилагођени наслов, он неће бити преведен на језик корисника осим ако немате прилагођену датотеку за превод у заштићеном/конфигурационом фолдеру. Оставите празно да поставите подразумевани наслов.',
  'If you want to enable {BackChannelLogout} (which allows removing user sessions automatically when signing out from Keycloak), configure the client {LogoutSettings}:' => 'Ако желите да омогућите {BackChannelLogout} (који омогућава аутоматско уклањање корисничких сесија приликом одјављивања са Кеицлоак-а), конфигуришите клијентске {LogoutSettings} :',
  'In admin, hide password fields in edit user form' => 'У администратору, сакријте поља за лозинку у обрасцу за уређивање корисника',
  'Keycloak API admin password' => 'Кеицлоак АПИ администраторска лозинка',
  'Keycloak API admin username' => 'Кеицлоак АПИ администраторско корисничко име',
  'Keycloak attribute to use to get username on account creation' => 'Кеицлоак атрибут који се користи за добијање корисничког имена при креирању налога',
  'More informations here.' => 'Више информација овде.',
  'New password' => 'Нова лозинка',
  'No sync' => 'Нема синхронизације',
  'On Keycloak, create a client for HumHub and configure it:' => 'На Кеицлоак-у креирајте клијента за ХумХуб и конфигуришите га:',
  'Password confirmation does not match.' => 'Потврда лозинке се не поклапа.',
  'Realm name' => 'Име царства',
  'Remove user\'s Keycloak sessions after logout' => 'Уклоните сесије корисника Кеицлоак након одјаве',
  'Sync HumHub towards Keycloak' => 'Синхронизујте ХумХуб са Кеицлоак-ом',
  'Sync HumHub towards Keycloak (but no removal on Keycloak)' => 'Синхронизујте ХумХуб са Кеицлоак-ом (али без уклањања на Кеицлоаку)',
  'Sync Keycloak towards HumHub' => 'Синц Кеицлоак са ХумХуб-ом',
  'Sync Keycloak towards HumHub (but no removal on HumHub)' => 'Синхронизујте Кеицлоак са ХумХуб-ом (али без уклањања на ХумХубу)',
  'Sync both ways' => 'Синхронизујте у оба смера',
  'Sync both ways (but no removal on HumHub)' => 'Синхронизујте у оба смера (али без уклањања на ХумХуб-у)',
  'Sync both ways (but no removal on Keycloak or HumHub)' => 'Синхронизујте у оба смера (али без уклањања на Кеицлоак-у или ХумХуб-у)',
  'Sync both ways (but no removal on Keycloak)' => 'Синхронизујте у оба смера (али без уклањања на Кеицлоак-у)',
  'Synchronize groups and their members' => 'Синхронизујте групе и њихове чланове',
  'The client id provided by Keycloak' => 'ИД клијента обезбеђује Кеицлоак',
  'The new password could not be saved.' => 'Нова лозинка није могла да се сачува.',
  'This admin user must be created in the same realm as the one entered in the {RealmName} field. If your realm is {masterRealmName}, just assign the {adminRoleName} role to this user. Otherwise, you need to add the {realmManagementClientRole} Client Role and assign all Roles. {MoreInformationHere}' => 'Овај корисник администратор мора бити креиран у истом домену као онај унет у поље {RealmName} . Ако је ваше подручје {masterRealmName} , само доделите улогу {adminRoleName} овом кориснику. У супротном, потребно је да додате клијентску улогу {realmManagementClientRole} и доделите све улоге. {MoreInformationHere}',
  'Title of the button' => 'Наслов дугмета',
  'Update user\'s email on HumHub when changed on Keycloak' => 'Ажурирајте имејл корисника на ХумХуб-у када се промени на Кеицлоак-у',
  'Update user\'s email on Keycloak when changed on HumHub' => 'Ажурирајте имејл корисника на Кеицлоак-у када се промени на ХумХуб-у',
  'Update user\'s username on HumHub when changed on Keycloak' => 'Ажурирајте корисничко име на ХумХуб-у када се промени на Кеицлоак-у',
  'Update user\'s username on Keycloak when changed on HumHub' => 'Ажурирајте корисничко име на Кеицлоак-у када се промени на ХумХуб-у',
  'View error log' => 'Погледајте дневник грешака',
  'Will only work if in Keycloak\'s realm settings "Email as username" is disabled and "Edit username" is enabled.' => 'Радиће само ако је у подешавањима домена Кеицлоак-а „Е-пошта као корисничко име“ онемогућено и „Измени корисничко име“ омогућено.',
  'Your current password can be changed here.' => 'Ваша тренутна лозинка се може променити овде.',
  '`preferred_username` (to use Keycloak username), `sub` (to use Keycloak ID) or other custom Token Claim Name' => '`преферред_усернаме` (да бисте користили Кеицлоак корисничко име), `суб` (да бисте користили Кеицлоак ИД) или други прилагођени назив Токена',
  '{Credentials} tab: copy the secret key' => 'Картица {Credentials} : копирајте тајни кључ',
  '{Settings} tab -> {ClientAuthenticationOn} (for Keycloak version <20: {AccessTypeValue}).' => 'Картица {Settings} -> {ClientAuthenticationOn} (за Кеицлоак верзију <20: {AccessTypeValue} ).',
  '{Settings} tab -> {ValidRedirectURIsValue}.' => 'Картица {Settings} -> {ValidRedirectURIsValue} .',
);

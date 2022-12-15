<?php
return array (
  '<strong>Keycloak</strong> Sign-In configuration' => '<strong>Keycloak</strong>サインイン構成',
  'Add a page in account settings allowing users to change their Keycloak password' => 'アカウント設定にページを追加して、ユーザーがKeycloakパスワードを変更できるようにします',
  'Advanced settings (optional)' => '詳細設定（オプション）',
  'Advanced settings requiring an admin user for the API (optional)' => 'APIの管理者ユーザーを必要とする詳細設定（オプション）',
  'Authentication to Keycloak API failed!' => 'Keycloak API への認証に失敗しました!',
  'Authentication to Keycloak API succeeded!' => 'Keycloak API への認証が成功しました!',
  'Automatic login' => '自動ログイン',
  'Base URL' => 'ベースURL',
  'Button {AddBuiltin} and check theses attributes:' => '{AddBuiltin}ボタンを押して、これらの属性を確認します。',
  'Called {nameInEnglish} in english' => '英語で{nameInEnglish}と呼ばれる',
  'Change password on {keycloakRealmDisplayName}' => '{keycloakRealmDisplayName}のパスワードを変更します',
  'Client ID' => 'クライアントID',
  'Client secret is in the "Credentials" tab (if in the settings "Access Type" is set to "confidential")' => 'クライアントシークレットは[資格情報]タブにあります（設定で[アクセスタイプ]が[機密]に設定されている場合）',
  'Client secret key' => 'クライアントの秘密鍵',
  'Confirm new password' => '新しいパスワードを確認',
  'Edit {usernameAttribute} and in {TokenClaimName}, replace {preferredUsernameAttribute} with {idAttribute}' => '{usernameAttribute}を編集し、TokenClaimNameで{preferredUsernameAttribute}を{TokenClaimName}に置き換え{idAttribute}',
  'Enable this auth client' => 'この認証クライアントを有効にする',
  'For administrators allowed to manage users' => 'ユーザーの管理を許可された管理者の場合',
  'Hide username field in registration form' => '登録フォームのユーザー名フィールドを非表示にする',
  'Humhub to Keycloak sync is done in real time. Keycloak to Humhub sync is done once a day. Keycloak subgroups are not synced.' => 'HumhubからKeycloakへの同期はリアルタイムで行われます。 KeycloakからHumhubへの同期は1日1回行われます。 Keycloakサブグループは同期されません。',
  'If enabled, you should also enable {removeKeycloakSessionsAfterLogoutAttrLabel}, otherwise users cannot logout.' => '有効になっている場合は、 {removeKeycloakSessionsAfterLogoutAttrLabel}も有効にする必要があります。有効にしないと、ユーザーはログアウトできません。',
  'If the username sent by Keycloak is the user\'s email, it is replaced by a username auto-generated from the first and last name (CamelCase formatted)' => 'Keycloakによって送信されたユーザー名がユーザーの電子メールである場合、それは姓名から自動生成されたユーザー名に置き換えられます（CamelCase形式）',
  'If you set a custom title, it will not be translated to the user\'s language unless you have a custom translation file in the protected/config folder. Leave blank to set default title.' => 'カスタムタイトルを設定した場合、protected / configフォルダーにカスタム翻訳ファイルがない限り、ユーザーの言語に翻訳されません。デフォルトのタイトルを設定するには、空白のままにします。',
  'In admin, hide password fields in edit user form' => '管理画面で、ユーザー編集フォームのパスワード フィールドを非表示にします',
  'Keycloak API admin password' => 'KeycloakAPI管理者パスワード',
  'Keycloak API admin username' => 'KeycloakAPI管理者ユーザー名',
  'Keycloak attribute to use to get username on account creation' => 'アカウント作成時にユーザー名を取得するために使用するKeycloak属性',
  'New password' => '新しいパスワード',
  'No sync' => '同期なし',
  'On Keycloak, create a client for Humhub and configure it:' => 'Keycloakで、Humhubのクライアントを作成し、構成します。',
  'Password confirmation does not match.' => 'パスワードの確認が一致しません。',
  'Possible only if {newUsersCanRegister} is allowed in Administration -> Users -> Settings.' => '{newUsersCanRegister}が[管理]->[ユーザー]->[設定]で許可されている場合にのみ可能です。',
  'Realm name' => 'レルム名',
  'Remove user\'s Keycloak sessions after logout' => 'ログアウト後にユーザーのKeycloakセッションを削除する',
  'Sync Humhub towards Keycloak' => 'HumhubをKeycloakに同期します',
  'Sync Humhub towards Keycloak (but no removal on Keycloak)' => 'HumhubをKeycloakに同期します（ただし、Keycloakでは削除されません）',
  'Sync Keycloak towards Humhub' => 'KeycloakをHumhubに同期します',
  'Sync Keycloak towards Humhub (but no removal on Humhub)' => 'KeycloakをHumhubに同期します（ただし、Humhubでは削除されません）',
  'Sync both ways' => '双方向で同期する',
  'Sync both ways (but no removal on Humhub)' => '両方の方法で同期します（ただし、Humhubでは削除されません）',
  'Sync both ways (but no removal on Keycloak or Humhub)' => '両方の方法で同期します（ただし、KeycloakまたはHumhubでは削除されません）',
  'Sync both ways (but no removal on Keycloak)' => '両方の方法で同期します（ただし、Keycloakでは削除されません）',
  'Synchronize groups and their members' => 'グループとそのメンバーを同期する',
  'The client id provided by Keycloak' => 'Keycloakによって提供されるクライアントID',
  'The new password could not be saved.' => '新しいパスワードを保存できませんでした。',
  'This admin user must be in the {master} realm and have permission to manage users of the realm belonging to the client for this Humhub' => 'この管理者ユーザーは{master}レルムに属し、このHumhubのクライアントに属するレルムのユーザーを管理する権限を持っている必要があります',
  'Title of the button (if autoLogin is disabled)' => 'ボタンのタイトル（autoLoginが無効になっている場合）',
  'Update user\'s email on Humhub when changed on Keycloak' => 'Keycloakで変更された場合、Humhubでユーザーのメールアドレスを更新します',
  'Update user\'s email on Keycloak when changed on Humhub' => 'Humhubで変更されたときに、Keycloakでユーザーのメールアドレスを更新する',
  'Update user\'s username on Humhub when changed on Keycloak' => 'Keycloakで変更された場合、Humhubでユーザーのユーザー名を更新します',
  'Update user\'s username on Keycloak when changed on Humhub' => 'Humhubで変更された場合、Keycloakでユーザーのユーザー名を更新します',
  'Will only work if in Keycloak\'s realm settings "Email as username" is disabled and "Edit username" is enabled.' => 'Keycloakのレルム設定で「ユーザー名としてのメール」が無効になっていて「ユーザー名の編集」が有効になっている場合にのみ機能します。',
  'Your current password can be changed here.' => '現在のパスワードはここで変更できます。',
  '`preferred_username` (to use Keycloak username), `sub` (to use Keycloak ID) or other custom Token Claim Name' => '`preferred_username`（Keycloakユーザー名を使用する場合）、` sub`（Keycloak IDを使用する場合）またはその他のカスタムトークンクレーム名',
  '{Credentials} tab: copy the secret key' => '[ {Credentials} ]タブ：秘密鍵をコピーします',
  '{Mappers} tab:' => '{Mappers} ：',
  '{Settings} tab -> {AccessType}: choose {confidential}. Save settings.' => '{Settings} ]タブ->[ {AccessType} ]：[ {confidential} ]を選択します。設定を保存する。',
);

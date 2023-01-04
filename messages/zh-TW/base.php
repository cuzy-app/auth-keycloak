<?php
return array (
  '<strong>Keycloak</strong> Sign-In configuration' => '<strong>Keycloak</strong>登录配置',
  'Add a page in account settings allowing users to change their Keycloak password' => '在帐户设置中添加一个页面，允许用户更改其 Keycloak 密码',
  'Advanced settings (optional)' => '高级设置（可选）',
  'Advanced settings requiring an admin user for the API (optional)' => 'API 需要管理员用户的高级设置（可选）',
  'Authentication to Keycloak API failed!' => '對 Keycloak API 的身份驗證失敗！',
  'Authentication to Keycloak API succeeded!' => 'Keycloak API 認證成功！',
  'Automatic login' => '自动登录',
  'Base URL' => '基本网址',
  'Button {AddMapper} (for Keycloak version <20: {AddBuiltin}) and add theses attributes:' => '按鈕{AddMapper} （對於 Humhub 版本 <20: {AddBuiltin} ）並添加這些屬性：',
  'Called {nameInEnglish} in english' => '英文叫{nameInEnglish}',
  'Change password on {keycloakRealmDisplayName}' => '在{keycloakRealmDisplayName}上更改密码',
  'Client ID' => '客户编号',
  'Client secret is in the "Credentials" tab (if in the settings "Access Type" is set to "confidential")' => '客户端密码位于“凭据”选项卡中（如果在设置中“访问类型”设置为“机密”）',
  'Client secret key' => '客户端密钥',
  'Confirm new password' => '确认新密码',
  'Edit {usernameAttribute} and in {TokenClaimName}, replace {preferredUsernameAttribute} with {idAttribute}' => '编辑{usernameAttribute}并在{TokenClaimName}中，将{preferredUsernameAttribute}替换为{idAttribute}',
  'Enable this auth client' => '启用此身份验证客户端',
  'For administrators allowed to manage users' => '對於允許管理用戶的管理員',
  'Hide username field in registration form' => '在注册表单中隐藏用户名字段',
  'Humhub to Keycloak sync is done in real time. Keycloak to Humhub sync is done once a day. Keycloak subgroups are not synced.' => 'Humhub 到 Keycloak 的同步是实时完成的。 Keycloak 到 Humhub 的同步每天进行一次。 Keycloak 子组不同步。',
  'If enabled, you should also enable {removeKeycloakSessionsAfterLogoutAttrLabel}, otherwise users cannot logout.' => '如果启用，您还应该启用{removeKeycloakSessionsAfterLogoutAttrLabel} ，否则用户无法注销。',
  'If the username sent by Keycloak is the user\'s email, it is replaced by a username auto-generated from the first and last name (CamelCase formatted)' => '如果 Keycloak 发送的用户名是用户的电子邮件，则替换为根据名字和姓氏自动生成的用户名（CamelCase 格式）',
  'If you set a custom title, it will not be translated to the user\'s language unless you have a custom translation file in the protected/config folder. Leave blank to set default title.' => '如果您设置了自定义标题，除非您在 protected/config 文件夹中有自定义翻译文件，否则它不会被翻译成用户的语言。留空以设置默认标题。',
  'In admin, hide password fields in edit user form' => '在管理員中，在編輯用戶表單中隱藏密碼字段',
  'Keycloak API admin password' => 'Keycloak API 管理员密码',
  'Keycloak API admin username' => 'Keycloak API 管理员用户名',
  'Keycloak attribute to use to get username on account creation' => '用于在创建帐户时获取用户名的 Keycloak 属性',
  'More informations here.' => '更多信息在這裡。',
  'New password' => '新密码',
  'No sync' => '不同步',
  'On Keycloak, create a client for Humhub and configure it:' => '在 Keycloak 上，为 Humhub 创建一个客户端并进行配置：',
  'Password confirmation does not match.' => '密码确认不匹配。',
  'Possible only if {newUsersCanRegister} is allowed in Administration -> Users -> Settings.' => '只有在管理 -> 用户 -> 设置中允许{newUsersCanRegister}时才可能。',
  'Realm name' => '领域名称',
  'Remove user\'s Keycloak sessions after logout' => '注销后删除用户的 Keycloak 会话',
  'Sync Humhub towards Keycloak' => '将 Humhub 同步到 Keycloak',
  'Sync Humhub towards Keycloak (but no removal on Keycloak)' => '将 Humhub 与 Keycloak 同步（但不会在 Keycloak 上移除）',
  'Sync Keycloak towards Humhub' => '将 Keycloak 同步到 Humhub',
  'Sync Keycloak towards Humhub (but no removal on Humhub)' => '将 Keycloak 同步到 Humhub（但不会在 Humhub 上移除）',
  'Sync both ways' => '双向同步',
  'Sync both ways (but no removal on Humhub)' => '双向同步（但在 Humhub 上没有删除）',
  'Sync both ways (but no removal on Keycloak or Humhub)' => '双向同步（但在 Keycloak 或 Humhub 上没有删除）',
  'Sync both ways (but no removal on Keycloak)' => '双向同步（但在 Keycloak 上没有删除）',
  'Synchronize groups and their members' => '同步组及其成员',
  'The client id provided by Keycloak' => 'Keycloak提供的客户端ID',
  'The new password could not be saved.' => '无法保存新密码。',
  'This admin user must be created in the same realm as the one entered in the {RealmName} field. If your realm is {masterRealmName}, just assign the {adminRoleName} role to this user. Otherwise, you need to add the {realmManagementClientRole} Client Role and assign all Roles. {MoreInformationHere}' => '此管理員用戶必須在與{RealmName}字段中輸入的相同領域中創建。如果您的領域是{masterRealmName} ，只需將{adminRoleName}角色分配給該用戶。否則，您需要添加{realmManagementClientRole}客戶端角色並分配所有角色。 {MoreInformationHere}',
  'Title of the button (if autoLogin is disabled)' => '按钮的标题（如果禁用自动登录）',
  'Update user\'s email on Humhub when changed on Keycloak' => '在 Keycloak 上更改时更新用户在 Humhub 上的电子邮件',
  'Update user\'s email on Keycloak when changed on Humhub' => '在 Humhub 上更改时更新用户在 Keycloak 上的电子邮件',
  'Update user\'s username on Humhub when changed on Keycloak' => '在 Keycloak 上更改时更新用户在 Humhub 上的用户名',
  'Update user\'s username on Keycloak when changed on Humhub' => '在 Humhub 上更改时更新用户在 Keycloak 上的用户名',
  'View error log' => '查看錯誤日誌',
  'Will only work if in Keycloak\'s realm settings "Email as username" is disabled and "Edit username" is enabled.' => '仅当在 Keycloak 的领域设置中禁用“作为用户名发送电子邮件”并启用“编辑用户名”时才有效。',
  'Your current password can be changed here.' => '您可以在此处更改当前密码。',
  '`preferred_username` (to use Keycloak username), `sub` (to use Keycloak ID) or other custom Token Claim Name' => '`preferred_username`（使用 Keycloak 用户名）、`sub`（使用 Keycloak ID）或其他自定义令牌声明名称',
  '{ClientScope} tab -> click on the first {scopeName} (for Keycloak version <20: {Mappers} tab):' => '{ClientScope}選項卡 -> 單擊第一個{scopeName} （對於 Humhub 版本 <20： {Mappers}選項卡）：',
  '{Credentials} tab: copy the secret key' => '{Credentials}选项卡：复制密钥',
  '{Settings} tab -> {ClientAuthenticationOn} (for Keycloak version <20: {AccessTypeValue}).' => '{Settings}選項卡 -> {ClientAuthenticationOn} （對於 Humhub 版本 <20： {AccessTypeValue} ）。',
  '{Settings} tab -> {ValidRedirectURIsValue}.' => '{Settings}選項卡 -> {ValidRedirectURIsValue} 。',
);

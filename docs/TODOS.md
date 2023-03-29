TODOS 
=====

- `authclient\Keycloak` class should extend `OpenIdConnect` instead of `OAuth2`. When done, remove `public $scope = 'openid';`. Test all different settings to check there are no issues.
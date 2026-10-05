<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\components;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Promise\RejectionException;
use Keycloak\Admin\Middleware\RefreshToken;
use Psr\Http\Message\ResponseInterface;

/**
 * The library's RefreshToken middleware gets the access token with its own HTTP client, without timeout,
 * so an unresponsive Keycloak server could block the request indefinitely.
 *
 * This middleware is added before the library's one and shares its token storage:
 * it gets the token with the API request timeouts, and the library's middleware then reuses it.
 *
 * Same as the parent method, except for the `timeout` and `connect_timeout` options
 * (check it when upgrading `mohammad-waleed/keycloak-admin-client`).
 */
class RefreshTokenWithTimeout extends RefreshToken
{
    /**
     * @inheritdoc
     */
    public function getAccessToken($credentials, $refresh, $options)
    {
        if ($refresh && empty($credentials['refresh_token'])) {
            return new RejectedPromise("cannot refresh token when the 'refresh_token' is missing");
        }

        $url = "realms/{$options['realm']}/protocol/openid-connect/token";
        $clientId = $options['client_id'] ?? 'admin-cli';
        $grantType = $refresh ? 'refresh_token' : ($options['grant_type'] ?? 'password');
        $params = [
            'client_id' => $clientId,
            'grant_type' => $grantType,
        ];

        if ($grantType === 'refresh_token') {
            $params['refresh_token'] = $credentials['refresh_token'];
        } elseif ($grantType === 'password') {
            $params['username'] = $options['username'];
            $params['password'] = $options['password'];
        } elseif ($grantType === 'client_credentials') {
            $params['client_secret'] = $options['client_secret'];
        }

        if (!empty($options['client_secret'])) {
            $params['client_secret'] = $options['client_secret'];
        }

        $httpClient = new Client([
            'base_uri' => $options['baseUri'],
            'verify' => $options['verify'] ?? true,
            'timeout' => $options['timeout'] ?? 0,
            'connect_timeout' => $options['connect_timeout'] ?? 0,
        ]);

        return $httpClient->requestAsync('POST', $url, ['form_params' => $params])->then(function (ResponseInterface $response) {
            if ($response->getStatusCode() !== 200) {
                throw new RejectionException('expected to receive http status code 200 when requesting a token');
            }

            $token = json_decode($response->getBody()->getContents(), true);
            if (!$token) {
                throw new RejectionException('token returned in the response body is not in a valid json');
            }

            return $token;
        });
    }
}

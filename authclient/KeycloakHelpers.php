<?php
/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/humhub-modules-auth-keycloak
 * @license https://github.com/cuzy-app/humhub-modules-auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\authclient;

use humhub\modules\user\models\Auth;
use humhub\modules\user\models\User;

/**
 * With PrimaryClient, the user will have the `auth_mode` field in the `user` table set to 'Keycloak'.
 * This will avoid showing the "Change Password" tab when logged in with Keycloak
 */
class KeycloakHelpers
{
    /**
     * Force saving source ID in user_auth as AuthClientHelpers::storeAuthClientForUser doesn't do it, and we need it for Keycloak API calls (to retrieve the user)
     * @param User $user
     * @param $sourceId
     * @return Auth|null
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public static function storeAndGetAuthSourceId(User $user, $sourceId)
    {
        if ($sourceId) {
            $auth = Auth::findOne(['source' => Keycloak::DEFAULT_NAME, 'source_id' => $sourceId]);

            // Make sure authClient is not double assigned
            if ($auth !== null && $auth->user_id !== $user->id) {
                $auth->delete();
                $auth = null;
            }

            if ($auth === null) {
                $auth = new Auth([
                    'user_id' => $user->id,
                    'source' => Keycloak::DEFAULT_NAME,
                    'source_id' => (string)$sourceId,
                ]);
                $auth->save();
            }
        }

        return $auth ?? null;
    }
}
<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/master/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.APP](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\models;

use humhub\modules\user\models\Auth;

/**
 * @inerhitdoc
 *
 * @property string $keycloak_sid Keycloak shared session identifier
 */
class AuthKeycloak extends Auth
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            [['keycloak_sid'], 'string', 'max' => 36],
        ]);
    }
}

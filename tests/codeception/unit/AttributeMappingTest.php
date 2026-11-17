<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 */

namespace humhub\modules\authKeycloak\tests\codeception\unit;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\source\KeycloakUserSource;
use humhub\modules\user\services\UserSourceService;
use tests\codeception\_support\HumHubDbTestCase;
use Yii;

/**
 * Attribute mapping without Keycloak: validation, parsing and normalization of Keycloak API user representations
 */
class AttributeMappingTest extends HumHubDbTestCase
{
    private const KEYCLOAK_USER = [
        'id' => 'e9f480fa-fe64-4587-b99b-139f59d6e0c2',
        'username' => 'jdoe',
        'email' => 'jdoe@example.com',
        'emailVerified' => true,
        'firstName' => 'John',
        'lastName' => 'Doe',
        'attributes' => [
            'phoneNumber' => ['+49 123'],
            'locality' => ['Berlin', 'Munich'],
        ],
    ];

    public function testValidation()
    {
        $form = new ConfigureForm();
        $form->attributeMapping = "username=preferred_username\nphone_work=phoneNumber\n\nnot_a_field=foo\nuser_id=sub\nemail=mail\nbroken line\ncity=";
        $this->assertFalse($form->validate(['attributeMapping']));
        $this->assertSame([
            '"not_a_field" is not a HumHub profile field.',
            '"user_id" is not a HumHub profile field.',
            '"email" is not a HumHub profile field.',
            'Invalid line "broken line", the expected format is humhub-profile-field=keycloak-attribute.',
            'Invalid line "city=", the expected format is humhub-profile-field=keycloak-attribute.',
        ], $form->getErrors('attributeMapping'));

        $form->attributeMapping = "username=preferred_username\n phone_work = phoneNumber \r\n";
        $this->assertTrue($form->validate(['attributeMapping']));
    }

    public function testParse()
    {
        $this->assertSame(
            ['username' => 'preferred_username', 'phone_work' => 'phoneNumber', 'city' => 'locality'],
            ConfigureForm::parseAttributeMapping(" username = preferred_username\r\nphone_work=phoneNumber\n\nuser_id=sub\ncity=locality\ninvalid"),
        );
        $this->assertSame([], ConfigureForm::parseAttributeMapping(null));
    }

    public function testDefaultMapping()
    {
        $attributes = $this->createAuthClient('')->normalizeApiUserAttributes(self::KEYCLOAK_USER);

        $this->assertSame(self::KEYCLOAK_USER['id'], $attributes['id']);
        // The Keycloak ID is the default username
        $this->assertSame(self::KEYCLOAK_USER['id'], $attributes['username']);
        $this->assertSame('jdoe@example.com', $attributes['email']);
        $this->assertSame('John', $attributes['firstname']);
        $this->assertSame('Doe', $attributes['lastname']);
        $this->assertArrayNotHasKey('phone_work', $attributes);
    }

    public function testCustomMapping()
    {
        $authClient = $this->createAuthClient("username=preferred_username\nphone_work=phoneNumber\ncity=locality\nstreet=street\nfirstname=family_name");
        $attributes = $authClient->normalizeApiUserAttributes(self::KEYCLOAK_USER);

        $this->assertSame('jdoe', $attributes['username']);
        $this->assertSame('+49 123', $attributes['phone_work']);
        // Only the first value of a multivalued attribute
        $this->assertSame('Berlin', $attributes['city']);
        // The API returns all attributes: a missing mapped attribute empties the profile field
        $this->assertSame('', $attributes['street']);
        // Standard fields can be overridden
        $this->assertSame('Doe', $attributes['firstname']);
        // id and email cannot be overridden
        $this->assertSame(self::KEYCLOAK_USER['id'], $attributes['id']);
        $this->assertSame('jdoe@example.com', $attributes['email']);
    }

    public function testMissingUsernameAttributeIsNotEmptied()
    {
        $attributes = $this->createAuthClient('username=customUsername')->normalizeApiUserAttributes(self::KEYCLOAK_USER);
        $this->assertArrayNotHasKey('username', $attributes);
    }

    public function testUnverifiedEmailIgnored()
    {
        $attributes = $this->createAuthClient('', ['trustOnlyVerifiedEmail' => true])->normalizeApiUserAttributes(['emailVerified' => false] + self::KEYCLOAK_USER);
        $this->assertArrayNotHasKey('email', $attributes);
    }

    public function testManagedAttributes()
    {
        $this->setSettings([
            'attributeMapping' => "username=preferred_username\nphone_work=phoneNumber\nfirstname=given_name",
            'syncUsers' => true,
            'updateHumhubUsernameFromBrokerUsername' => false,
        ]);
        $source = UserSourceService::getCollection()->getUserSource(KeycloakUserSource::SOURCE_ID);
        // The username mapping does not lock the username, the profile fields are locked
        $this->assertSame(['firstname', 'lastname', 'phone_work'], $source->getManagedAttributes());

        $this->setSettings(['updateHumhubUsernameFromBrokerUsername' => true]);
        $this->assertContains('username', $source->getManagedAttributes());
    }

    private function createAuthClient(string $attributeMapping, array $settings = []): Keycloak
    {
        $this->setSettings(['attributeMapping' => $attributeMapping] + $settings);
        return Yii::createObject(['class' => Keycloak::class, 'clientId' => 'humhub', 'clientSecret' => 'secret']);
    }

    private function setSettings(array $settings): void
    {
        $settings += ['baseUrl' => 'http://keycloak.test', 'realm' => 'test', 'trustOnlyVerifiedEmail' => false];
        foreach ($settings as $name => $value) {
            Yii::$app->getModule('auth-keycloak')->settings->set($name, $value);
        }
    }
}

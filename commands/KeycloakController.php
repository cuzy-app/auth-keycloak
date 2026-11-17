<?php

/**
 * Keycloak Sign-In
 * @link https://github.com/cuzy-app/auth-keycloak
 * @license https://github.com/cuzy-app/auth-keycloak/blob/main/docs/LICENCE.md
 * @author [Marc FARRE](https://marc.fun) for [CUZY.app](https://www.cuzy.app)
 */

namespace humhub\modules\authKeycloak\commands;

use humhub\modules\authKeycloak\authclient\Keycloak;
use humhub\modules\authKeycloak\components\KeycloakApi;
use humhub\modules\authKeycloak\jobs\GroupsFullSync;
use humhub\modules\authKeycloak\models\ConfigureForm;
use humhub\modules\authKeycloak\source\KeycloakUserSource;
use humhub\modules\user\models\Auth;
use humhub\modules\user\models\User;
use humhub\modules\user\services\UserSourceService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\console\widgets\Table;
use yii\helpers\Console;

/**
 * Console tools for the Keycloak users
 */
class KeycloakController extends Controller
{
    /**
     * @inheritdoc
     */
    public $defaultAction = 'sync-users';

    /**
     * Creates, updates and deactivates the HumHub users from the Keycloak users
     * (also done every hour if "Fetch/Update users automatically" is enabled)
     */
    public function actionSyncUsers()
    {
        $this->stdout("*** Keycloak users sync\n\n");

        if (($error = $this->checkApi()) !== null) {
            $this->stderr('Error: ' . $error . "\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $collection = UserSourceService::getCollection();
        if (!$collection->hasUserSource(KeycloakUserSource::SOURCE_ID)) {
            $this->stderr("Error: the Keycloak user source is disabled (`provideUserSource` module property).\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        /** @var KeycloakUserSource $source */
        $source = $collection->getUserSource(KeycloakUserSource::SOURCE_ID);
        $start = microtime(true);
        $progressStep = null;
        $results = $source->syncUsers(function (string $step, int $done, ?int $total, int $pass) use (&$progressStep) {
            $this->showProgress($progressStep, $step . $pass, $step === KeycloakUserSource::PROGRESS_LIST
                ? 'Retrieving the Keycloak users' . ($pass > 1 ? ' (pass ' . $pass . ')' : '') . ': '
                : 'Syncing the HumHub users: ', $done, $total);
        });
        if ($progressStep !== null) {
            Console::endProgress();
        }
        $this->stdout("\n");
        if ($results === null) {
            $this->stderr("Error: the sync could not run, see the error log.\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ($results as $result => $count) {
            $this->stdout(str_pad(ucfirst($result) . ':', 12) . $count . "\n");
        }

        $this->stdout("\nKeycloak users: " . $source->lastSyncKeycloakUsersCount . "\n");
        if (!$source->lastSyncIsComplete) {
            $this->stderr("Warning: the list of Keycloak users may be incomplete (see the log), no HumHub user has been deactivated for not being found on Keycloak.\n", Console::FG_YELLOW);
        }
        $counts = $source->countUsersByStatus();
        $this->stdout('HumHub users of the "' . $source->getId() . '" user source: ' . array_sum($counts)
            . ' (enabled: ' . $counts[User::STATUS_ENABLED]
            . ', deactivated: ' . $counts[User::STATUS_DEACTIVATED]
            . ', awaiting approval: ' . $counts[User::STATUS_NEED_APPROVAL]
            . ', soft deleted: ' . $counts[User::STATUS_SOFT_DELETED] . ")\n");
        $this->stdout("\nDuration: " . $this->formatDuration(microtime(true) - $start)
            . ' (retrieving the Keycloak users: ' . $this->formatDuration($source->lastSyncListDuration) . ")\n");
        $this->stdout("\nKeycloak users sync completed!\n\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Synchronizes the groups and their members (also done every day if the "Groups sync" setting is enabled)
     */
    public function actionSyncGroups()
    {
        $this->stdout("*** Keycloak groups sync\n\n");

        if (($error = $this->checkApi()) !== null) {
            $this->stderr('Error: ' . $error . "\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $start = microtime(true);
        $progressStep = null;
        $job = new GroupsFullSync();
        $job->onProgress = function (int $done, int $total) use (&$progressStep) {
            $this->showProgress($progressStep, 'members', 'Retrieving the members of the Keycloak groups: ', $done, $total);
        };
        $job->run();
        if ($progressStep !== null) {
            Console::endProgress();
        }
        if ($job->isCompleted === null) {
            $this->stderr("Error: the groups sync is disabled.\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ([
            'created' => 'Created on HumHub',
            'linked' => 'Linked to an existing HumHub group',
            'withoutMembers' => 'Not created (no members with a HumHub account)',
        ] as $result => $label) {
            $this->stdout($label . ': ' . count($job->results[$result])
                . ($job->results[$result] !== [] ? ' (' . implode(', ', $job->results[$result]) . ')' : '') . "\n");
        }
        $this->stdout("\nDuration: " . $this->formatDuration(microtime(true) - $start) . "\n");
        if (!$job->isCompleted) {
            $this->stderr("\nError: the sync was aborted, see the error log.\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $this->stdout("\nKeycloak groups sync completed!\n\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Lists the Keycloak users and their linked HumHub user
     */
    public function actionListUsers()
    {
        $this->stdout("*** Keycloak users\n\n");

        if (($error = $this->checkApi()) !== null) {
            $this->stderr('Error: ' . $error . "\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $keycloakUsers = (new KeycloakApi())->getAllUsers();
        if ($keycloakUsers === null) {
            $this->stderr("Error: the Keycloak users could not be retrieved, see the error log.\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $rows = [];
        foreach ($keycloakUsers as $keycloakUser) {
            $auth = Auth::findOne(['source' => Keycloak::DEFAULT_NAME, 'source_id' => (string)($keycloakUser['id'] ?? '')]);
            $rows[] = [
                $keycloakUser['id'] ?? '---',
                $keycloakUser['username'] ?? '---',
                $keycloakUser['email'] ?? '---',
                !empty($keycloakUser['enabled']) ? 'Yes' : 'No',
                $auth?->user?->username ?? '---',
            ];
        }

        echo Table::widget(['headers' => ['Keycloak ID', 'Username', 'Email', 'Enabled', 'HumHub user'], 'rows' => $rows]);
        $this->stdout("\n" . count($rows) . " Keycloak users.\n\n");

        return ExitCode::OK;
    }

    /**
     * Shows all the attributes of a Keycloak user and the resulting HumHub attributes (profile fields mapping)
     *
     * @param string $user HumHub username or email, or Keycloak ID, username or email
     */
    public function actionShowUser($user)
    {
        $this->stdout('*** Keycloak user details for "' . $user . "\"\n\n");

        if (($error = $this->checkApi()) !== null) {
            $this->stderr('Error: ' . $error . "\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $authClient = Yii::$app->authClientCollection->hasClient(Keycloak::DEFAULT_NAME)
            ? Yii::$app->authClientCollection->getClient(Keycloak::DEFAULT_NAME)
            : null;
        if (!$authClient instanceof Keycloak) {
            $this->stderr("Error: the Keycloak auth client is not enabled.\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $api = new KeycloakApi();
        $keycloakUser = null;
        $humhubUser = User::find()->where(['username' => $user])->orWhere(['email' => $user])->one();
        if ($humhubUser !== null && ($auth = $api->getUserAuth($humhubUser->id)) !== null) {
            $keycloakUser = $api->getUser($auth->source_id);
        }
        $keycloakUser ??= $api->findUser($user);
        // Not only UUIDs: the IDs of users of a user federation without import look like "f:<provider ID>:<user ID>"
        $keycloakUser ??= $api->getUser($user);
        if ($keycloakUser === null) {
            $this->stderr('Error: no Keycloak user found with the ID, username or email "' . $user . '"'
                . ($humhubUser !== null ? ' or linked to the HumHub user ' . $humhubUser->username . ' (ID ' . $humhubUser->id . ')' : '')
                . ". API errors, if any, are in the error log.\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $rows = [];
        foreach ($keycloakUser as $name => $value) {
            if ($name !== 'attributes') {
                $rows[] = [$name, $this->formatValue($value)];
            }
        }
        $this->stdout("Keycloak user:\n");
        echo Table::widget(['headers' => ['Name', 'Value'], 'rows' => $rows]) . "\n";

        $rows = [];
        foreach ($keycloakUser['attributes'] ?? [] as $name => $value) {
            $rows[] = [$name, $this->formatValue($value)];
        }
        $this->stdout("Keycloak user attributes (usable in the profile fields mapping):\n");
        if ($rows === []) {
            $this->stdout("No attributes.\n\n");
        } else {
            echo Table::widget(['headers' => ['Keycloak attribute', 'Value'], 'rows' => $rows]) . "\n";
        }

        $auth = Auth::findOne(['source' => Keycloak::DEFAULT_NAME, 'source_id' => (string)$keycloakUser['id']]);
        $humhubUser = $auth?->user;
        $attributes = $authClient->normalizeApiUserAttributes($keycloakUser);
        $managedAttributes = $humhubUser !== null ? (new UserSourceService($humhubUser))->getManagedAttributes() : [];
        $rows = [];
        foreach ($authClient->getNormalizeUserAttributeMap() as $field => $actualName) {
            $row = [
                $field,
                is_string($actualName) ? $actualName : '---',
                $this->formatValue($attributes[$field] ?? null),
            ];
            if ($humhubUser !== null) {
                $humhubValue = $field === 'id'
                    ? $auth->source_id
                    : ($humhubUser->hasAttribute($field) ? $humhubUser->getAttribute($field) : $humhubUser->profile?->getAttribute($field));
                $row[] = $this->formatValue($humhubValue);
                $row[] = in_array($field, $managedAttributes, true) ? 'Yes' : 'No';
            }
            $rows[] = $row;
        }
        $headers = ['HumHub', 'Keycloak', 'Keycloak value'];
        if ($humhubUser !== null) {
            array_push($headers, 'HumHub value', 'Synced');
        }
        $this->stdout('HumHub attributes' . ($humhubUser !== null
            ? ' (linked to the HumHub user ' . $humhubUser->username . ', ID ' . $humhubUser->id . ', user source "' . $humhubUser->user_source . '")'
            : ' (no linked HumHub user)') . ":\n");
        echo Table::widget(['headers' => $headers, 'rows' => $rows]) . "\n";

        $this->stdout("Values read from the Keycloak API. On sign in, the claims sent by Keycloak depend on the mappers of the client scopes.\n\n");

        return ExitCode::OK;
    }

    /**
     * @param string|null $currentStep step of the progress bar being displayed, updated
     * @param string $step a new progress bar is started when the step changes
     * @param int|null $total if null, only the number of done items is displayed
     */
    private function showProgress(?string &$currentStep, string $step, string $prefix, int $done, ?int $total): void
    {
        if ($total === null || $total <= 0) {
            $this->stdout("\r" . $prefix . $done);
            $currentStep = $step;
            return;
        }
        // With a user federation, more users than the total may be listed
        $done = min($done, $total);
        if ($step !== $currentStep) {
            if ($currentStep !== null) {
                Console::endProgress();
            }
            Console::startProgress($done, $total, $prefix);
            $currentStep = $step;
        } else {
            Console::updateProgress($done, $total, $prefix);
        }
    }

    private function formatDuration(?float $seconds): string
    {
        if ($seconds === null) {
            return '---';
        }
        return $seconds < 60
            ? round($seconds, 1) . ' s'
            : floor($seconds / 60) . ' min ' . round(fmod($seconds, 60)) . ' s';
    }

    private function formatValue($value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '---';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return array_is_list($value) && count(array_filter($value, 'is_scalar')) === count($value)
                ? implode(', ', $value)
                : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return (string)$value;
    }

    /**
     * @return string|null error message
     */
    private function checkApi(): ?string
    {
        $config = new ConfigureForm();
        if (!$config->enabled) {
            return 'the Keycloak auth client is not enabled.';
        }
        if (!$config->hasApiParams()) {
            return 'the Keycloak API user is not configured.';
        }
        if (!(new KeycloakApi())->isConnected()) {
            return 'the authentication to the Keycloak API failed, see the error log.';
        }
        return null;
    }
}

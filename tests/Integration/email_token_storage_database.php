<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use Defuse\Crypto\Key;
use TeampassClasses\EmailService\DatabaseEmailTokenStore;
use TeampassClasses\EmailService\EmailTransportException;

$database = (string) getenv('TEAMPASS_TEST_DB');
if (preg_match('/\Ateampass_test_[a-z0-9_]+\z/', $database) !== 1) {
    throw new RuntimeException('A disposable teampass_test_* database is required.');
}
$host = getenv('TEAMPASS_TEST_HOST') ?: '127.0.0.1';
$user = getenv('TEAMPASS_TEST_USER') ?: 'root';
$password = (string) getenv('TEAMPASS_TEST_PASSWORD');
$port = (int) (getenv('TEAMPASS_TEST_PORT') ?: 3306);
$prefix = 'email_tokens_' . bin2hex(random_bytes(4)) . '_';
$root = dirname(__DIR__, 2);
$keyFile = tempnam(sys_get_temp_dir(), 'teampass-email-token-key-');
define('TEAMPASS_FUNCTIONS_ONLY', true);
define('TEAMPASS_RECOVERY_MIGRATION', true);
define('TEAMPASS_SECRETS', dirname($keyFile));
define('SECUREFILE', basename($keyFile));
define('DB_PREFIX', $prefix);
require $root . '/app/vendor/autoload.php';
require $root . '/app/sources/main.functions.php';
file_put_contents($keyFile, Key::createNewRandomKey()->saveToAsciiSafeString());
DB::$host = $host;
DB::$user = $user;
DB::$password = $password;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = 'utf8mb4';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/** Fail independently of the runtime's assertion settings. */
function emailTokenExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Execute the actual migration body with an isolated connection and prefix. */
function emailTokenRunMigration(string $root, string $host, string $user, #[SensitiveParameter] string $password, string $database, int $port, string $prefix): void
{
    $db_link = new mysqli($host, $user, $password, $database, $port);
    $pre = $prefix;
    $SETTINGS = [];
    require $root . '/public/install/upgrade_run_3.2.4.php';
}

try {
    // Load only the trusted installer class, without its HTTP dispatcher or installation settings.
    $installerSource = (string) file_get_contents($root . '/public/install/install-steps/run.step5.php');
    $classStart = strpos($installerSource, 'class DatabaseInstaller');
    emailTokenExpect($classStart !== false, 'Installer class is missing');
    eval(substr($installerSource, $classStart));
    $installer = new DatabaseInstaller(['tablePrefix' => $prefix], []);
    (new ReflectionMethod(DatabaseInstaller::class, 'emails'))->invoke($installer);
    (new ReflectionMethod(DatabaseInstaller::class, 'background_tasks'))->invoke($installer);
    (new ReflectionMethod(DatabaseInstaller::class, 'background_subtasks'))->invoke($installer);
    (new ReflectionMethod(DatabaseInstaller::class, 'secure_send_email'))->invoke($installer);
    (new ReflectionMethod(DatabaseInstaller::class, 'secure_send_audit'))->invoke($installer);
    $auditTable = prefixTable('secure_send_audit');
    $freshAuditColumns = DB::query('SHOW FULL COLUMNS FROM %b', $auditTable);
    DB::query('ALTER TABLE %b DROP COLUMN attachment_count, DROP COLUMN attachment_ids, DROP COLUMN delivery_channel', $auditTable);
    DB::insert($auditTable, secureSendAuditRecord(['id' => 1, 'originator' => 42, 'send_type' => 'note'], 'revealed'));
    $historicalAudit = DB::queryFirstRow('SELECT * FROM %b', $auditTable);
    $freshSharingColumns = DB::query('SHOW COLUMNS FROM ' . prefixTable('secure_send_email'));
    DB::query('DROP TABLE ' . prefixTable('secure_send_email'));
    $freshQueueColumns = DB::query('SHOW COLUMNS FROM ' . prefixTable('background_tasks'));
    DB::query('ALTER TABLE ' . prefixTable('background_tasks') . ' DROP INDEX idx_email_due, DROP COLUMN email_attempts, DROP COLUMN next_attempt_at');
    $freshColumns = DB::query('SHOW COLUMNS FROM ' . prefixTable('email_oauth_state'));
    DB::query('DROP TABLE ' . prefixTable('email_oauth_state'));
    // The 3.2.4 script also binds OAuth2 accounts (users.oauth2_identity). Build the users table
    // from the installer's own statement, without users() and its seeding, then remove the column
    // to stand for a database that predates it.
    $usersStart = strpos($installerSource, '"CREATE TABLE IF NOT EXISTS `" . $this->inputData[\'tablePrefix\'] . "users` (');
    emailTokenExpect($usersStart !== false, 'Installer users table is missing');
    $usersEnd = strpos($installerSource, 'COLLATE=utf8mb4_unicode_ci;"', $usersStart) + strlen('COLLATE=utf8mb4_unicode_ci;"');
    DB::query(eval('return ' . str_replace('$this->inputData[\'tablePrefix\']', '$prefix', substr($installerSource, $usersStart, $usersEnd - $usersStart)) . ';'));
    $oauth2IdentityShape = static fn(): array => [
        DB::queryFirstRow('SHOW FULL COLUMNS FROM ' . prefixTable('users') . ' WHERE Field = %s', 'oauth2_identity'),
        array_map(
            static fn(array $index): array => [$index['Non_unique'], $index['Column_name']],
            DB::query('SHOW INDEX FROM ' . prefixTable('users') . ' WHERE Key_name = %s', 'oauth2_identity')
        ),
    ];
    $freshIdentityShape = $oauth2IdentityShape();
    emailTokenExpect($freshIdentityShape[0] !== null && $freshIdentityShape[1] === [['0', 'oauth2_identity']], 'Installer lacks the unique OAuth2 identity');
    DB::query('ALTER TABLE ' . prefixTable('users') . ' DROP INDEX oauth2_identity, DROP COLUMN oauth2_identity');
    DB::query('CREATE TABLE ' . prefixTable('misc') . ' (type VARCHAR(50), intitule VARCHAR(100), valeur MEDIUMTEXT,
        is_encrypted TINYINT DEFAULT 0, UNIQUE KEY settings_key (type, intitule))');
    $oldSecret = cryption('retained-synthetic-smtp-password', '', 'encrypt')['string'];
    DB::query('INSERT INTO ' . prefixTable('misc') . ' VALUES (%s, %s, %s, 1)', 'admin', 'email_auth_pwd', $oldSecret);
    DB::query('INSERT INTO ' . prefixTable('misc') . ' VALUES (%s, %s, %s, 0)', 'admin', 'email_smtp_server', 'relay.example.test');
    DB::query('INSERT INTO ' . prefixTable('misc') . ' VALUES (%s, %s, %s, 0)', 'admin', 'oauth2_client_endpoint', 'https://login.microsoftonline.com/tenant/oauth2/v2.0/authorize');
    emailTokenRunMigration($root, $host, $user, $password, $database, $port, $prefix);
    $linkSetting = static fn(): mixed => DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'oauth2_link_existing_accounts');
    emailTokenExpect($freshIdentityShape === $oauth2IdentityShape(), 'Fresh and upgrade OAuth2 identity schemas differ');
    emailTokenExpect($linkSetting() === '1', 'An Entra installation lost the conversion of existing accounts');
    DB::query('UPDATE ' . prefixTable('misc') . ' SET valeur = %s WHERE intitule = %s', '0', 'oauth2_link_existing_accounts');
    $revision = (string) DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'email_config_revision');
    emailTokenExpect(DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'secure_send_allow_email') === '0', 'Email invitations enabled without opt-in');
    DB::update(prefixTable('misc'), ['valeur' => '1'], 'intitule=%s', 'secure_send_allow_email');
    emailTokenExpect(DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'secure_send_api_enabled') === '0', 'Secure Send for API clients enabled without opt-in');
    DB::update(prefixTable('misc'), ['valeur' => '1'], 'intitule=%s', 'secure_send_api_enabled');
    emailTokenExpect(preg_match('/\A[a-f0-9]{32}\z/', $revision) === 1, 'Missing configuration revision');
    emailTokenExpect($freshColumns === DB::query('SHOW COLUMNS FROM ' . prefixTable('email_oauth_state')), 'Fresh and upgrade schemas differ');
    emailTokenExpect($freshQueueColumns === DB::query('SHOW COLUMNS FROM ' . prefixTable('background_tasks')), 'Fresh and upgrade queue schemas differ');
    emailTokenExpect($freshSharingColumns === DB::query('SHOW COLUMNS FROM ' . prefixTable('secure_send_email')), 'Fresh and upgrade sharing delivery schemas differ');
    emailTokenExpect($freshAuditColumns === DB::query('SHOW FULL COLUMNS FROM %b', $auditTable), 'Fresh and upgraded Secure Send audit schemas differ');
    $upgradedAudit = DB::queryFirstRow('SELECT * FROM %b', $auditTable);
    emailTokenExpect($upgradedAudit === $historicalAudit + ['attachment_count' => null, 'attachment_ids' => null, 'delivery_channel' => null],
        'Upgrade changed historical Secure Send audit evidence');
    DB::insert(prefixTable('secure_send_email'), ['send_id' => 1, 'task_id' => 1, 'originator' => 42,
        'created_at' => time(), 'address_hash' => str_repeat('a', 64)]);
    emailTokenRunMigration($root, $host, $user, $password, $database, $port, $prefix);
    emailTokenExpect($upgradedAudit === DB::queryFirstRow('SELECT * FROM %b', $auditTable),
        'Upgrade replay changed historical Secure Send audit evidence');
    emailTokenExpect((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_email')) === 1, 'Replay removed a sharing delivery reservation');

    $tasks = prefixTable('background_tasks');
    $finishedAt = time();
    DB::insert($tasks, ['increment_id' => 1, 'process_type' => 'secure_send_email', 'status' => 'in_progress',
        'is_in_progress' => 1, 'created_at' => $finishedAt - 1, 'arguments' => '{"sealed":"private-canary"}']);
    secureSendEmailFinishTask(1, 'accepted', ['status' => 'completed', 'finished_at' => $finishedAt,
        'is_in_progress' => -1, 'arguments' => '{"accepted":true}'], []);
    secureSendEmailFinishTask(1, 'indeterminate', ['status' => 'failed', 'finished_at' => $finishedAt + 1], []);
    emailTokenExpect(DB::queryFirstField('SELECT status FROM %b WHERE increment_id = %i', $tasks, 1) === 'completed',
        'A duplicate finalization overwrote the first outcome');
    emailTokenExpect(secureSendEmailDeleteTask(1, false, []), 'An authorized Tasks user lost terminal cleanup');
    $outcomes = DB::query('SELECT * FROM %b WHERE event = %s', $auditTable, 'email_outcome');
    emailTokenExpect(count($outcomes) === 1 && $outcomes[0]['reason'] === 'accepted'
        && (int) $outcomes[0]['occurred_at'] === $finishedAt && (int) $outcomes[0]['send_id'] === 1
        && $outcomes[0]['actor_id'] === null && $outcomes[0]['send_type'] === 'unknown'
        && !str_contains(json_encode($outcomes, JSON_THROW_ON_ERROR), 'private-canary'),
        'Terminal outcome must survive task deletion with its time and association and no email data');

    // Candidate selection alone must not prevent cancellation or allow a late worker launch.
    $subtasks = prefixTable('background_subtasks');
    $queueInvitation = static function (int $sendId, int $retryAt = 0) use ($tasks, $subtasks, $finishedAt): int {
        DB::insert($tasks, ['process_type' => 'secure_send_email', 'status' => 'queued',
            'created_at' => $finishedAt, 'arguments' => '{}', 'next_attempt_at' => $retryAt]);
        $id = (int) DB::insertId();
        DB::insert($subtasks, ['task_id' => $id, 'created_at' => $finishedAt, 'task' => '{}']);
        DB::insert(prefixTable('secure_send_email'), ['send_id' => $sendId, 'task_id' => $id, 'originator' => 42,
            'created_at' => $finishedAt, 'address_hash' => str_repeat('a', 64)]);
        return $id;
    };
    $queued = $queueInvitation(2);
    $deferred = $queueInvitation(3, $finishedAt + 600);
    $running = $queueInvitation(4);
    emailTokenExpect(!secureSendEmailDeleteTask($queued, false, []), 'A non-admin cancelled a queued invitation');
    emailTokenExpect(!secureSendEmailClaimTask($deferred, $finishedAt), 'A deferred invitation was claimed early');
    emailTokenExpect(secureSendEmailClaimTask($running, $finishedAt), 'Claiming a due invitation failed');
    emailTokenExpect(!secureSendEmailClaimTask($running, $finishedAt) && !secureSendEmailDeleteTask($running, true, []),
        'A claimed invitation was claimed again or cancelled');

    // A deletion failure after writing the audit must leave all three rows consistent.
    $trigger = $prefix . 'deny_task_delete';
    DB::query("CREATE TRIGGER %b BEFORE DELETE ON %b FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test failure'",
        $trigger, $tasks);
    try {
        secureSendEmailDeleteTask($queued, true, []);
        throw new LogicException('Cancellation ignored a failed task deletion');
    } catch (RuntimeException $error) {
        emailTokenExpect($error->getMessage() === 'Unable to delete the Secure Send email task.', 'Unexpected deletion error');
    } finally {
        DB::query('DROP TRIGGER %b', $trigger);
    }
    emailTokenExpect(DB::queryFirstField('SELECT status FROM %b WHERE increment_id = %i', $tasks, $queued) === 'queued'
        && (int) DB::queryFirstField('SELECT COUNT(*) FROM %b WHERE task_id = %i', $subtasks, $queued) === 1
        && (int) DB::queryFirstField('SELECT COUNT(*) FROM %b WHERE send_id = %i', $auditTable, 2) === 0,
        'Task, subtask or audit escaped rollback');

    foreach ([$queued, $deferred] as $cancelled) {
        emailTokenExpect(secureSendEmailDeleteTask($cancelled, true, []) && secureSendEmailDeleteTask($cancelled, true, []),
            'Queued or deferred cancellation failed or was not idempotent');
        emailTokenExpect(!secureSendEmailClaimTask($cancelled, $finishedAt + 601)
            && DB::queryFirstField('SELECT increment_id FROM %b WHERE increment_id = %i', $tasks, $cancelled) === null
            && (int) DB::queryFirstField('SELECT COUNT(*) FROM %b WHERE task_id = %i', $subtasks, $cancelled) === 0,
            'A cancelled task or its subtasks remained runnable');
    }
    secureSendEmailFinishTask($queued, 'indeterminate', ['status' => 'failed', 'finished_at' => $finishedAt], []);
    $cancelledOutcomes = DB::query('SELECT send_id, originator, reason FROM %b WHERE event = %s AND send_id > %i ORDER BY send_id',
        $auditTable, 'email_outcome', 1);
    emailTokenExpect(array_map('intval', array_column($cancelledOutcomes, 'send_id')) === [2, 3]
        && array_map('intval', array_column($cancelledOutcomes, 'originator')) === [42, 42]
        && array_column($cancelledOutcomes, 'reason') === ['not_submitted', 'not_submitted'],
        'Cancellation did not retain exactly one outcome per invitation');
    emailTokenExpect(DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'secure_send_allow_email') === '1', 'Replay changed administrator email opt-in');
    emailTokenExpect(DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'secure_send_api_enabled') === '1', 'Replay changed the Secure Send API opt-in');
    emailTokenExpect($revision === DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'email_config_revision'), 'Replay changed the revision');
    emailTokenExpect($freshIdentityShape === $oauth2IdentityShape(), 'Replay changed the OAuth2 identity schema');
    emailTokenExpect($linkSetting() === '0', 'Replay overrode the account linking choice');
    // Any provider other than Entra starts with the conversion disabled
    DB::query('DELETE FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'oauth2_link_existing_accounts');
    DB::query('UPDATE ' . prefixTable('misc') . ' SET valeur = %s WHERE intitule = %s', 'https://authentik.example.test/application/o/authorize/', 'oauth2_client_endpoint');
    emailTokenRunMigration($root, $host, $user, $password, $database, $port, $prefix);
    emailTokenExpect($linkSetting() === '0', 'A generic provider kept the conversion of existing accounts');
    // Execute the scheduler's actual candidate SQL against due/deferred tasks on MariaDB.
    DB::insert(prefixTable('background_tasks'), ['created_at' => time(), 'process_type' => 'send_email', 'arguments' => '{}', 'next_attempt_at' => time() + 7200]);
    $deferredTaskId = (int) DB::insertId();
    DB::insert(prefixTable('background_tasks'), ['created_at' => time(), 'process_type' => 'send_email', 'arguments' => '{}']);
    $dueTaskId = (int) DB::insertId();
    $handlerSource = file_get_contents($root . '/app/scripts/background_tasks___handler.php');
    $queryStart = strpos($handlerSource, "'SELECT increment_id, process_type, arguments, item_id");
    $queryEnd = strpos($handlerSource, "LIMIT %i'", $queryStart) + strlen("LIMIT %i'");
    $candidateSql = eval('return ' . substr($handlerSource, $queryStart, $queryEnd - $queryStart) . ';');
    $dueIds = array_map(static fn(array $row): int => (int) $row['increment_id'], DB::query($candidateSql, 100));
    emailTokenExpect(in_array($dueTaskId, $dueIds, true) && !in_array($deferredTaskId, $dueIds, true), 'Scheduler ran a deferred email early');
    emailTokenExpect($oldSecret === DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'email_auth_pwd'), 'Upgrade changed the SMTP secret');
    emailTokenExpect('relay.example.test' === DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'email_smtp_server'), 'Upgrade changed the relay');

    $store = new DatabaseEmailTokenStore($revision);
    $ownerA = str_repeat('a', 32);
    $ownerB = str_repeat('b', 32);
    $token = ['access_token' => 'synthetic-access-token', 'refresh_token' => 'synthetic-refresh-token', 'expires_at' => time() + 3600];
    emailTokenExpect($store->claim('cache', $ownerA, 60), 'First lease failed');
    emailTokenExpect(!$store->claim('cache', $ownerB, 60), 'Concurrent renewal was allowed');
    emailTokenExpect($store->save('cache', $ownerA, $token), 'Token write failed');
    $stored = DB::queryFirstRow('SELECT access_token, refresh_token FROM ' . prefixTable('email_oauth_state'));
    emailTokenExpect(tpIsInstanceKeyCiphertext($stored['access_token']) && tpIsInstanceKeyCiphertext($stored['refresh_token']), 'Token persisted without encryption');
    emailTokenExpect($store->load('cache') === $token, 'Token round trip failed');
    DB::query('UPDATE ' . prefixTable('email_oauth_state') . ' SET lease_until = 0');
    emailTokenExpect($store->claim('cache', $ownerB, 60), 'Expired lease could not be replaced');
    emailTokenExpect(!$store->save('cache', $ownerA, $token), 'Stale owner overwrote a token');
    $store->release('cache', $ownerA);
    emailTokenExpect(!$store->claim('cache', $ownerA, 60), 'Stale release cleared the new lease');
    $store->invalidateAccess('cache', 'stale-token');
    emailTokenExpect($store->load('cache')['access_token'] === $token['access_token'], 'Stale response invalidated a newer token');
    DB::query('UPDATE ' . prefixTable('misc') . ' SET valeur = %s WHERE intitule = %s', str_repeat('2', 32), 'email_config_revision');
    emailTokenExpect(!$store->save('cache', $ownerB, $token), 'Configuration change allowed a late write');
    try {
        $store->load('cache');
        throw new RuntimeException('Configuration change allowed a stale read');
    } catch (EmailTransportException $e) {
        emailTokenExpect($e->result->errorCode === 'configuration', 'Wrong revision failure');
    }
    // Profile activation must roll back as one unit and refuse an obsolete browser revision.
    $activeRevision = str_repeat('2', 32);
    $profile = emailTransportCandidate([
        'email_transport' => 'smtp', 'email_smtp_server' => 'relay.internal', 'email_port' => '25',
        'email_from' => 'sender@example.test', 'email_from_name' => 'R&D O\'Brien <label>',
        'email_auth_pwd' => 'synthetic-replacement', 'secret_actions' => ['email_auth_pwd' => 'replace'],
    ], [], static fn(string $secret): string => emailTransportEncrypt($secret, []));
    DB::startTransaction();
    emailTransportWriteProfile($profile, $activeRevision);
    DB::rollback();
    emailTokenExpect($activeRevision === DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'email_config_revision'), 'Rollback published a revision');
    emailTokenExpect($oldSecret === DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'email_auth_pwd'), 'Rollback changed credentials');
    DB::startTransaction();
    emailTransportWriteProfile($profile, $activeRevision);
    DB::commit();
    $replacement = DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'email_auth_pwd');
    emailTokenExpect(tpIsInstanceKeyCiphertext($replacement), 'Replacement secret was not encrypted');
    emailTokenExpect(cryption($replacement, '', 'decrypt')['string'] === 'synthetic-replacement', 'Secret replacement corrupted its value');
    emailTokenExpect((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('email_oauth_state')) === 0, 'Old authorization survived a revision change');
    DB::startTransaction();
    try {
        emailTransportWriteProfile($profile, $activeRevision);
        throw new LogicException('Stale browser configuration was accepted');
    } catch (RuntimeException $e) {
        emailTokenExpect($e->getMessage() === 'configuration', 'Wrong profile concurrency error');
    } finally {
        DB::rollback();
    }
    echo "Email token storage and profiles: install, migration, replay, encryption, ownership, rollback and concurrency checks passed.\n";
    echo "OAuth2 identity binding: install, migration, replay and linking default checks passed.\n";
    echo "Secure Send invitations: cancellation, claims, rollback and retained outcomes passed.\n";
} finally {
    foreach (['secure_send_attachments', 'secure_send_email', 'secure_send_audit', 'email_oauth_state', 'emails', 'misc', 'background_subtasks', 'background_tasks', 'users'] as $table) {
        DB::query('DROP TABLE IF EXISTS ' . prefixTable($table));
    }
    DB::disconnect();
    unlink($keyFile);
}

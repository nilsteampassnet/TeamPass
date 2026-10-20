<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      api_device_credentials_database.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

// Real storage/transactions; the JWT issuer is an explicit fixture. Never use an installation DB.
require_once __DIR__ . '/../../app/vendor/autoload.php';
require_once __DIR__ . '/../../app/sources/api_device_credentials.php';

$database = (string) getenv('TEAMPASS_TEST_DB');
if (preg_match('/^teampass_test_[a-z0-9_]+$/', $database) !== 1) {
    throw new RuntimeException('Set TEAMPASS_TEST_DB to a disposable teampass_test_* database.');
}
DB::$host = getenv('TEAMPASS_TEST_HOST') ?: '127.0.0.1';
DB::$port = (int) (getenv('TEAMPASS_TEST_PORT') ?: 3306);
DB::$user = getenv('TEAMPASS_TEST_USER') ?: 'root';
DB::$password = (string) getenv('TEAMPASS_TEST_PASSWORD');
DB::$dbName = $database;
$GLOBALS['device_test_prefix'] = getenv('TEAMPASS_TEST_PREFIX') ?: 'device_' . bin2hex(random_bytes(4)) . '_';
if (preg_match('/^device_[a-f0-9]{8}_$/', $GLOBALS['device_test_prefix']) !== 1) {
    throw new RuntimeException('Invalid fixture table prefix.');
}

/** Fixture prefix, isolated from application settings. */
function prefixTable(string $table): string
{
    return $GLOBALS['device_test_prefix'] . $table;
}

/** The real authentication helper receives fresh account data through this fixture adapter. */
function getUserCompleteData(string $login): ?array
{
    return DB::queryFirstRow('SELECT u.*, a.enabled AS api_enabled FROM ' . prefixTable('users') . ' u
        LEFT JOIN ' . prefixTable('api') . ' a ON a.user_id = u.id WHERE u.login = %s', $login);
}

/** Fail even when PHP assertions are disabled. */
function deviceCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Explicit fixture issuer: exercise the production transaction with a real source-linked session. */
function deviceFixtureIssue(array $token, array $user): array
{
    DB::insert(prefixTable('api_sessions'), ['user_id' => $user['id'], 'source_token_id' => $token['id'], 'jti' => bin2hex(random_bytes(16))]);
    return ['token' => 'synthetic-jwt', 'teampass_version' => 'test', 'teampass_version_major' => 'test', 'teampass_version_minor' => 'test'];
}

/** Run independently connected operations at the same instant. */
function deviceConcurrent(array $operations, int $tokenId, array $settings): array
{
    putenv('TEAMPASS_TEST_PREFIX=' . $GLOBALS['device_test_prefix']);
    $workers = [];
    $start = microtime(true) + 0.5;
    foreach ($operations as $operation) {
        $process = proc_open([PHP_BINARY, __FILE__, 'worker'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        deviceCheck(is_resource($process), 'Could not start credential worker');
        fwrite($pipes[0], json_encode(compact('start', 'operation', 'tokenId', 'settings'), JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        deviceCheck(proc_close($process) === 0, 'Credential worker failed: ' . $error);
        $results[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    return $results;
}

if (($argv[1] ?? '') === 'worker') {
    $input = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    while (microtime(true) < $input['start']) {
        usleep(1000);
    }
    $result = $input['operation'] === 'revoke'
        ? apiDeviceRevokeCredential(42, $input['tokenId'])
        : apiDeviceAuthenticate(42, $input['tokenId'], $input['settings'], 'deviceFixtureIssue');
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

$settings = ['device_pairing_schema' => 1, 'device_pairing_enabled' => 1, 'api' => 1, 'extension_token_all_auth_types' => 1];
$tables = ['api_sessions', 'api_tokens', 'api', 'users', 'misc', 'log_system'];
try {
    DB::query('CREATE TABLE ' . prefixTable('users') . ' (id INT PRIMARY KEY, login VARCHAR(255), auth_type VARCHAR(32), disabled INT DEFAULT 0, deleted_at INT NULL) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('api') . ' (user_id INT PRIMARY KEY, enabled INT) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('api_tokens') . ' (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT, purpose VARCHAR(32), expires_at INT NULL) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('api_sessions') . ' (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT, jti VARCHAR(32), source_token_id INT NULL, revoked_at INT NULL, KEY source_token_id(source_token_id)) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('misc') . ' (type VARCHAR(32), intitule VARCHAR(64), valeur TEXT) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('log_system') . ' (type VARCHAR(32), date INT, label VARCHAR(64), qui VARCHAR(32), field_1 TEXT) ENGINE=InnoDB');
    DB::insert(prefixTable('users'), ['id' => 42, 'login' => 'fixture', 'auth_type' => 'local']);
    DB::insert(prefixTable('api'), ['user_id' => 42, 'enabled' => 1]);
    foreach ($settings as $key => $value) {
        DB::insert(prefixTable('misc'), ['type' => 'admin', 'intitule' => $key, 'valeur' => (string) $value]);
    }
    DB::insert(prefixTable('api_tokens'), ['user_id' => 42, 'purpose' => 'external_device']);
    $id = (int) DB::insertId();
    deviceCheck(apiDeviceAuthenticate(42, $id, $settings, 'deviceFixtureIssue') !== null, 'Allowed credential refused');
    deviceCheck(apiDeviceSessionSourceAllowed(42, $id, $settings), 'Live source refused');
    DB::update(prefixTable('misc'), ['valeur' => '0'], 'intitule = %s', 'device_pairing_enabled');
    deviceCheck(apiDeviceAuthenticate(42, $id, $settings, 'deviceFixtureIssue') === null, 'Cached opt-in survived its revocation');
    deviceCheck(!apiDeviceSessionSourceAllowed(42, $id, $settings), 'Disabled feature left a JWT authorized');
    deviceCheck(apiDeviceRevokeCredential(42, $id), 'Disabled feature prevented revocation');
    DB::update(prefixTable('misc'), ['valeur' => '1'], 'intitule = %s', 'device_pairing_enabled');
    DB::insert(prefixTable('api_tokens'), ['user_id' => 42, 'purpose' => 'external_device']);
    $rollbackId = (int) DB::insertId();
    deviceCheck(apiDeviceAuthenticate(42, $rollbackId, $settings, 'deviceFixtureIssue') !== null, 'Rollback fixture could not authenticate');
    DB::query('RENAME TABLE ' . prefixTable('log_system') . ' TO ' . prefixTable('log_system_unavailable'));
    $failed = false;
    try {
        apiDeviceRevokeCredential(42, $rollbackId);
    } catch (Throwable $e) {
        $failed = true;
    } finally {
        DB::query('RENAME TABLE ' . prefixTable('log_system_unavailable') . ' TO ' . prefixTable('log_system'));
    }
    deviceCheck($failed, 'Audit failure did not reject revocation');
    deviceCheck(apiDeviceSessionSourceAllowed(42, $rollbackId, $settings), 'Audit failure deleted the credential despite rollback');
    deviceCheck((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('api_sessions') . ' WHERE source_token_id = %i AND revoked_at IS NULL', $rollbackId) === 1, 'Audit failure partially revoked the session');
    for ($round = 0; $round < 5; ++$round) {
        DB::insert(prefixTable('api_tokens'), ['user_id' => 42, 'purpose' => 'external_device']);
        $id = (int) DB::insertId();
        deviceConcurrent(['authenticate', 'authenticate', 'revoke', 'authenticate'], $id, $settings);
        deviceCheck((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('api_tokens') . ' WHERE id = %i', $id) === 0, 'Revoked credential remained active');
        deviceCheck((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('api_sessions') . ' WHERE source_token_id = %i AND revoked_at IS NULL', $id) === 0, 'Authentication published an unrevoked session after revocation');
    }
    echo "OK: source validation, fresh policy, disabled-feature revocation, audit rollback and five authentication/revocation races\n";
} finally {
    foreach ($tables as $table) {
        DB::query('DROP TABLE IF EXISTS ' . prefixTable($table));
    }
}

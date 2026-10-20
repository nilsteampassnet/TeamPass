<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 TeamPass.net
 * SPDX-License-Identifier: GPL-3.0-only
 * Distributed without any warranty. See LICENSE.md.
 */

// Real installer DDL, RSA, wrapping, service and concurrent SQL. JWT emission is a fixture adapter.
require_once __DIR__ . '/../../app/vendor/autoload.php';
require_once __DIR__ . '/../../app/config/include.php';
require_once __DIR__ . '/../../app/sources/device_pairing.functions.php';
require_once __DIR__ . '/../../app/sources/device_pairing_schema.php';

use TeampassClasses\CryptoManager\CryptoManager;

$database = (string) getenv('TEAMPASS_TEST_DB');
if (preg_match('/^teampass_test_[a-z0-9_]+$/', $database) !== 1) {
    throw new RuntimeException('Use a disposable teampass_test_* database.');
}
DB::$host = getenv('TEAMPASS_TEST_HOST') ?: '127.0.0.1';
DB::$port = (int) (getenv('TEAMPASS_TEST_PORT') ?: 3306);
DB::$user = getenv('TEAMPASS_TEST_USER') ?: 'root';
DB::$password = (string) getenv('TEAMPASS_TEST_PASSWORD');
DB::$dbName = $database;
$GLOBALS['pairing_test_prefix'] = getenv('TEAMPASS_TEST_PREFIX') ?: 'pairing_' . bin2hex(random_bytes(4)) . '_';
if (preg_match('/^pairing_[a-f0-9]{8}_$/', $GLOBALS['pairing_test_prefix']) !== 1) {
    throw new RuntimeException('Invalid fixture prefix.');
}

/** Isolate every fixture table from every other test or installation. */
function prefixTable(string $table): string
{
    return $GLOBALS['pairing_test_prefix'] . $table;
}

/** Explicit account adapter expected by the production credential transaction. */
function getUserCompleteData(string $login): ?array
{
    return DB::queryFirstRow('SELECT u.*, a.enabled AS api_enabled FROM ' . prefixTable('users') . ' u LEFT JOIN '
        . prefixTable('api') . ' a ON a.user_id = u.id WHERE u.login = %s', $login);
}

/** Fail regardless of PHP's assertion configuration. */
function pairingCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Require a protocol failure with its fixed reason. */
function pairingRefuses(callable $operation, string $reason): void
{
    try {
        $operation();
        throw new RuntimeException('A refused operation was accepted: ' . $reason);
    } catch (DevicePairingException $e) {
        pairingCheck($e->reason === $reason, 'Unexpected refusal reason');
    }
}

/** Minimal session issuer; validates the real PAT wrapping before inserting an actual session. */
function pairingFixtureAuthenticate(int $tokenId, string $pat, array $settings): ?array
{
    return apiDeviceAuthenticate(42, $tokenId, $settings, static function (array $token, array $user) use ($pat): array {
        $wrapKey = hash_hkdf('sha256', $pat, 32, 'teampass-extension-token-v1', hex2bin($token['salt']));
        $privateKey = decrypt_with_session_key($token['wrapped_private_key'], $wrapKey);
        pairingCheck(is_string($privateKey) && apiDevicePrivateKeyMatches($privateKey, $user['public_key']), 'PAT cannot unlock the current user key');
        $jti = bin2hex(random_bytes(16));
        DB::insert(prefixTable('api_sessions'), ['user_id' => 42, 'jti' => $jti, 'key_tempo' => $user['key_tempo'],
            'source_token_id' => $token['id'], 'created_at' => time(), 'expires_at' => time() + 60]);
        return ['token' => $jti, 'teampass_version' => 'fixture', 'teampass_version_major' => 'fixture', 'teampass_version_minor' => 'fixture'];
    });
}

/** Run claims, approvals or consumptions over separate real database connections. */
function pairingConcurrent(string $operation, array $inputs, array $settings, string $privateKey = ''): array
{
    putenv('TEAMPASS_TEST_PREFIX=' . $GLOBALS['pairing_test_prefix']);
    $workers = [];
    $start = microtime(true) + 0.5;
    foreach ($inputs as $input) {
        $process = proc_open([PHP_BINARY, __FILE__, 'worker'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        pairingCheck(is_resource($process), 'Cannot start pairing worker');
        fwrite($pipes[0], json_encode(compact('start', 'operation', 'input', 'settings', 'privateKey'), JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        pairingCheck(proc_close($process) === 0, 'Pairing worker failed: ' . $error);
        $results[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    return $results;
}

if (($argv[1] ?? '') === 'worker') {
    $data = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    while (microtime(true) < $data['start']) {
        usleep(1000);
    }
    try {
        $result = match ($data['operation']) {
            'claim' => devicePairingClaim($data['input'], $data['settings']),
            'approve' => devicePairingWebAction('approve', $data['input'], 42, 'fixture-binding', $data['privateKey'], $data['settings']),
            default => devicePairingDeviceAction('consume', $data['input'], $data['settings']),
        };
    } catch (DevicePairingException $e) {
        $result = ['error' => $e->reason];
    }
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

$settings = ['api' => 1, 'device_pairing_enabled' => 1, 'device_pairing_schema' => 2,
    'extension_token_all_auth_types' => 1, 'cpassman_url' => 'https://vault.example.test:8443/team'];
$userKeys = CryptoManager::generateRSAKeyPair(2048);
$deviceKeys = CryptoManager::generateRSAKeyPair(3072);
$tables = ['device_pairings', 'api_tokens', 'api_sessions', 'api_rate_limit', 'users', 'api', 'misc', 'log_system'];
try {
    // Execute the real fresh-install methods without executing the installer's HTTP entry point.
    $installerSource = (string) file_get_contents(__DIR__ . '/../../public/install/install-steps/run.step5.php');
    preg_match('/class DatabaseInstaller\s*\{[\s\S]*\z/', $installerSource, $class);
    eval($class[0]);
    $installer = new DatabaseInstaller(['tablePrefix' => $GLOBALS['pairing_test_prefix']], []);
    foreach (['api', 'api_tokens', 'api_sessions', 'api_rate_limit'] as $method) {
        (new ReflectionMethod($installer, $method))->invoke($installer);
    }
    DB::query(devicePairingSchemaSql(prefixTable('device_pairings')));
    DB::query('CREATE TABLE ' . prefixTable('users') . ' (id INT PRIMARY KEY, login VARCHAR(255), auth_type VARCHAR(32),
        disabled INT DEFAULT 0, deleted_at INT NULL, is_ready_for_usage INT DEFAULT 1, public_key TEXT,
        key_tempo VARCHAR(64), session_end BIGINT) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('misc') . ' (type VARCHAR(32), intitule VARCHAR(64), valeur TEXT, UNIQUE KEY setting(type,intitule)) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('log_system') . ' (type VARCHAR(32), date INT, label VARCHAR(64), qui VARCHAR(32), field_1 TEXT) ENGINE=InnoDB');
    DB::insert(prefixTable('users'), ['id' => 42, 'login' => 'fixture', 'auth_type' => 'local', 'public_key' => $userKeys['publickey'], 'key_tempo' => 'fixture-session-key', 'session_end' => time() + 3600]);
    DB::insert(prefixTable('api'), ['user_id' => 42, 'enabled' => 1, 'type' => 'user', 'timestamp' => (string) time()]);
    foreach ($settings as $key => $value) {
        DB::insert(prefixTable('misc'), ['type' => 'admin', 'intitule' => $key, 'valeur' => (string) $value]);
    }
    // Simulate a populated pre-feature installation and execute the exact upgrade helper twice.
    DB::query('ALTER TABLE ' . prefixTable('api_tokens') . ' DROP COLUMN purpose, DROP COLUMN pairing_id');
    DB::query('ALTER TABLE ' . prefixTable('api_sessions') . ' DROP COLUMN source_token_id');
    DB::query('DROP TABLE ' . prefixTable('device_pairings'));
    DB::insert(prefixTable('api_tokens'), ['user_id' => 42, 'token_hash' => str_repeat('f', 64),
        'wrapped_private_key' => 'synthetic-existing-wrap', 'salt' => str_repeat('a', 32), 'label' => 'legacy', 'created_at' => 1]);
    $legacyId = (int) DB::insertId();
    DB::update(prefixTable('misc'), ['valeur' => '1'], 'intitule = %s', 'device_pairing_schema');
    $migrationTables = [];
    foreach ($tables as $name) {
        $migrationTables[$name] = prefixTable($name);
    }
    devicePairingUpgradeSchema(DB::get(), $migrationTables);
    devicePairingUpgradeSchema(DB::get(), $migrationTables);
    $legacy = DB::queryFirstRow('SELECT * FROM ' . prefixTable('api_tokens') . ' WHERE id = %i', $legacyId);
    pairingCheck($legacy['purpose'] === 'legacy' && $legacy['wrapped_private_key'] === 'synthetic-existing-wrap', 'Upgrade changed a legacy credential');
    pairingCheck((string) DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'device_pairing_enabled') === '1', 'Upgrade reset explicit administrator opt-in');
    DB::delete(prefixTable('api_tokens'), 'id = %i', $legacyId);
    DB::query('ALTER TABLE ' . prefixTable('log_system') . ' ENGINE=MyISAM');
    DB::update(prefixTable('misc'), ['valeur' => '1'], 'intitule = %s', 'device_pairing_schema');
    $storageRefused = false;
    try {
        devicePairingUpgradeSchema(DB::get(), $migrationTables);
    } catch (RuntimeException $e) {
        $storageRefused = true;
    }
    pairingCheck($storageRefused && (string) DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s', 'device_pairing_schema') === '1', 'Nontransactional storage published protocol readiness');
    DB::query('ALTER TABLE ' . prefixTable('log_system') . ' ENGINE=InnoDB');
    devicePairingUpgradeSchema(DB::get(), $migrationTables);
    echo "OK: replayed upgrade preserves legacy credentials and opt-in; nontransactional audit is rejected\n";
    $create = static fn (): array => devicePairingCreate(42, 'fixture-binding', 'fixture-session-key', time() + 3600, $userKeys['privatekey'], $settings);
    $makeClaim = static function (array $created) use ($deviceKeys): array {
        $verifier = devicePairingBase64Url(random_bytes(32));
        return ['input' => ['pairing_id' => $created['pairing_id'], 'pairing_secret' => $created['qr']['pairing_secret'],
            'challenge' => devicePairingBase64Url(hash('sha256', $verifier, true)), 'public_key' => $deviceKeys['publickey'], 'device_label' => "R&D O'Brien <test>"], 'verifier' => $verifier];
    };
    $created = $create();
    $first = $makeClaim($created);
    $second = $makeClaim($created);
    $claims = pairingConcurrent('claim', [$first['input'], $second['input']], $settings);
    pairingCheck(count(array_filter($claims, static fn (array $r): bool => isset($r['claim_id']))) === 1, 'Concurrent claims replaced each other');
    $winner = isset($claims[0]['claim_id']) ? 0 : 1;
    $claimed = $claims[$winner];
    $chosen = $winner === 0 ? $first : $second;
    $proof = ['pairing_id' => $created['pairing_id'], 'claim_id' => $claimed['claim_id'], 'verifier' => $chosen['verifier']];
    $approval = ['pairing_id' => $created['pairing_id'], 'claim_id' => $claimed['claim_id'], 'key_fingerprint' => $claimed['key_fingerprint']];
    pairingRefuses(static fn () => devicePairingDeviceAction('consume', array_replace($proof, ['verifier' => str_repeat('x', 43)]), $settings), 'invalid_proof');
    pairingRefuses(static fn () => devicePairingWebAction('approve', $approval, 42, 'different-session', $userKeys['privatekey'], $settings), 'invalid_proof');
    pairingCheck(!isset(devicePairingDeviceAction('consume', $proof, $settings)['handoff']), 'Scan authorized a credential before web approval');
    $approvals = pairingConcurrent('approve', [$approval, $approval], $settings, $userKeys['privatekey']);
    pairingCheck(count(array_filter($approvals, static fn (array $r): bool => ($r['state'] ?? '') === 'approved')) === 1, 'Approval emitted twice');
    pairingCheck((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('api_tokens')) === 0, 'Approval published a usable PAT');
    $deliveries = pairingConcurrent('consume', [$proof, $proof, $proof], $settings);
    pairingCheck($deliveries[0]['handoff'] === $deliveries[1]['handoff'] && $deliveries[1]['handoff'] === $deliveries[2]['handoff'], 'Retry did not replay the same protected result');
    pairingCheck((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('api_tokens')) === 1, 'Concurrent consumption created multiple credentials');
    $pat = CryptoManager::rsaDecryptWithVersion(base64_decode(strtr($deliveries[0]['handoff']['ciphertext'], '-_', '+/')), $deviceKeys['privatekey'], 3);
    pairingCheck(preg_match('/^[a-f0-9]{64}$/', $pat) === 1, 'Device could not decrypt the PAT');
    $row = DB::queryFirstRow('SELECT * FROM ' . prefixTable('device_pairings') . ' WHERE id = %s', $created['pairing_id']);
    pairingCheck(strpos(json_encode($row), $pat) === false && strpos(json_encode($row), $userKeys['privatekey']) === false, 'Handoff table contains a plaintext secret');
    $tokenId = (int) $row['credential_id'];
    // Run the real AuthModel key validation after provisional confirmation. A failed unwrap
    // must roll the confirmation back before the caller records its failed-auth audit.
    require_once __DIR__ . '/../../app/api/Model/AuthModel.php';
    if (!defined('API_ROOT_PATH')) {
        define('API_ROOT_PATH', dirname(__DIR__, 2) . '/app/api');
    }
    $authMethod = new ReflectionMethod(AuthModel::class, 'authenticateTokenRow');
    try {
        apiDeviceAuthenticate(42, $tokenId, $settings, static function (array $token, array $user) use ($authMethod, $settings): array {
            return $authMethod->invoke(new AuthModel(), $token, $user, str_repeat('0', 64), $settings, (int) $token['id']);
        });
        throw new RuntimeException('Invalid wrapped key authenticated');
    } catch (ApiTokenKeyException $e) {
        pairingCheck($e->getMessage() === 'api_token_decrypt_failed', 'Invalid key refusal reason changed');
        DB::insert(prefixTable('log_system'), ['type' => 'failed_auth', 'date' => time(), 'label' => $e->getMessage(), 'qui' => '42']);
    }
    $afterFailure = DB::queryFirstRow('SELECT * FROM ' . prefixTable('device_pairings') . ' WHERE id = %s', $created['pairing_id']);
    pairingCheck($afterFailure['state'] === 'delivered' && $afterFailure['encrypted_token'] !== null, 'Failed auth consumed the handoff');
    pairingCheck(DB::queryFirstField('SELECT pairing_id FROM ' . prefixTable('api_tokens') . ' WHERE id = %i', $tokenId) === $created['pairing_id'], 'Failed auth confirmed the credential');
    pairingCheck((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('log_system') . ' WHERE label = %s', 'api_token_decrypt_failed') === 1, 'Failed-auth audit was rolled back');
    $one = pairingFixtureAuthenticate($tokenId, $pat, $settings);
    $two = pairingFixtureAuthenticate($tokenId, $pat, $settings);
    pairingCheck($one !== null && $two !== null && $one['token'] !== $two['token'], 'Durable PAT could not renew the session');
    $row = DB::queryFirstRow('SELECT * FROM ' . prefixTable('device_pairings') . ' WHERE id = %s', $created['pairing_id']);
    pairingCheck($row['state'] === 'completed' && $row['encrypted_token'] === null && $row['public_key'] === null, 'Successful auth left a recoverable handoff');
    apiDeviceRevokeCredential(42, $tokenId);
    pairingCheck(!apiDeviceSessionSourceAllowed(42, $tokenId, $settings), 'Revoked PAT left its JWT source authorized');
    echo "OK: real installer schema, immutable claims, explicit approval, concurrent consumption, protected retry and PAT renewal/revocation\n";

    foreach (['cancel', 'reject', 'expiry', 'logout', 'policy', 'key_change', 'delivery_timeout'] as $scenario) {
        DB::delete(prefixTable('api_rate_limit'), 'scope_key LIKE %s', 'pair:%');
        $created = $create();
        $claimInput = $makeClaim($created);
        $claim = devicePairingClaim($claimInput['input'], $settings);
        $proof = ['pairing_id' => $created['pairing_id'], 'claim_id' => $claim['claim_id'], 'verifier' => $claimInput['verifier']];
        $approval = ['pairing_id' => $created['pairing_id'], 'claim_id' => $claim['claim_id'], 'key_fingerprint' => $claim['key_fingerprint']];
        devicePairingWebAction('approve', $approval, 42, 'fixture-binding', $userKeys['privatekey'], $settings);
        if ($scenario === 'delivery_timeout') {
            devicePairingDeviceAction('consume', $proof, $settings);
            DB::update(prefixTable('device_pairings'), ['delivery_until' => time() - 1], 'id = %s', $created['pairing_id']);
        } elseif ($scenario === 'cancel') {
            devicePairingWebAction('cancel', $approval, 42, 'fixture-binding', '', $settings);
        } elseif ($scenario === 'reject') {
            // Refusal is also covered before approval, with the exact claim identity.
            DB::update(prefixTable('device_pairings'), ['state' => 'awaiting_approval'], 'id = %s', $created['pairing_id']);
            devicePairingWebAction('reject', $approval, 42, 'fixture-binding', '', $settings);
        } elseif ($scenario === 'expiry') {
            DB::update(prefixTable('device_pairings'), ['expires_at' => time() - 1], 'id = %s', $created['pairing_id']);
        } elseif ($scenario === 'logout') {
            DB::update(prefixTable('users'), ['key_tempo' => ''], 'id = %i', 42);
        } elseif ($scenario === 'policy') {
            DB::update(prefixTable('misc'), ['valeur' => '0'], 'intitule = %s', 'device_pairing_enabled');
        } else {
            DB::update(prefixTable('users'), ['public_key' => 'outdated'], 'id = %i', 42);
        }
        $result = devicePairingDeviceAction('consume', $proof, $settings);
        pairingCheck(!isset($result['handoff']), 'Terminal transaction returned a credential');
        pairingCheck((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('api_tokens')) === 0, 'Abandoned delivery left an orphan PAT');
        DB::update(prefixTable('users'), ['key_tempo' => 'fixture-session-key', 'public_key' => $userKeys['publickey']], 'id = %i', 42);
        DB::update(prefixTable('misc'), ['valeur' => '1'], 'intitule = %s', 'device_pairing_enabled');
    }
    echo "OK: cancellation, refusal, expiry, logout, disabled policy, changed keys and abandoned-delivery cleanup\n";
    DB::delete(prefixTable('api_rate_limit'), 'scope_key LIKE %s', 'pair:%');
    $created = $create();
    $request = $makeClaim($created);
    $claim = devicePairingClaim($request['input'], $settings);
    $approval = ['pairing_id' => $created['pairing_id'], 'claim_id' => $claim['claim_id'], 'key_fingerprint' => $claim['key_fingerprint']];
    $proof = ['pairing_id' => $created['pairing_id'], 'claim_id' => $claim['claim_id'], 'verifier' => $request['verifier']];
    foreach (['approve', 'consume'] as $phase) {
        DB::query('RENAME TABLE ' . prefixTable('log_system') . ' TO ' . prefixTable('log_system_unavailable'));
        $failed = false;
        try {
            $phase === 'approve'
                ? devicePairingWebAction('approve', $approval, 42, 'fixture-binding', $userKeys['privatekey'], $settings)
                : devicePairingDeviceAction('consume', $proof, $settings);
        } catch (Throwable $e) {
            $failed = true;
        } finally {
            DB::query('RENAME TABLE ' . prefixTable('log_system_unavailable') . ' TO ' . prefixTable('log_system'));
        }
        pairingCheck($failed, 'Audit failure did not roll back ' . $phase);
        pairingCheck((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('api_tokens')) === 0, 'Failed phase published a credential');
        $state = DB::queryFirstField('SELECT state FROM ' . prefixTable('device_pairings') . ' WHERE id = %s', $created['pairing_id']);
        pairingCheck($state === ($phase === 'approve' ? 'awaiting_approval' : 'approved'), 'Failed phase partially changed state');
        if ($phase === 'approve') {
            devicePairingWebAction('approve', $approval, 42, 'fixture-binding', $userKeys['privatekey'], $settings);
        }
    }
    devicePairingDeviceAction('consume', $proof, $settings);
    DB::update(prefixTable('device_pairings'), ['delivery_until' => time() - 1], 'id = %s', $created['pairing_id']);
    pairingCheck(devicePairingPurge($settings) >= 1, 'Maintenance did not expire an abandoned delivery');
    pairingCheck((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('api_tokens')) === 0, 'Maintenance retained an orphan credential');
    echo "OK: approval/consumption rollback and real maintenance cleanup\n";
} finally {
    foreach ($tables as $table) {
        DB::query('DROP TABLE IF EXISTS ' . prefixTable($table));
    }
}

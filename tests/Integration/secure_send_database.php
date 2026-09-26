<?php

declare(strict_types=1);

/**
 * Disposable MySQL/MariaDB lifecycle and concurrent redemption test.
 * Run only against a database named teampass_test_* (see quality.yml).
 * Recipient logic, Defuse and SQL are real; creation/ACL/audit adapters are explicit fixtures.
 */
require_once __DIR__ . '/../../app/vendor/autoload.php';
require_once __DIR__ . '/../Fixtures/secure_send_dependencies.php';
require_once __DIR__ . '/../../app/sources/secure_send.functions.php';

$database = (string) getenv('TEAMPASS_TEST_DB');
if (!preg_match('/^teampass_test_[a-z0-9_]+$/', $database)) {
    fwrite(STDERR, "Set TEAMPASS_TEST_DB to a disposable teampass_test_* database.\n");
    exit(1);
}
DB::$host = getenv('TEAMPASS_TEST_HOST') ?: '127.0.0.1';
DB::$port = (int) (getenv('TEAMPASS_TEST_PORT') ?: 3306);
DB::$user = getenv('TEAMPASS_TEST_USER') ?: 'root';
DB::$password = (string) getenv('TEAMPASS_TEST_PASSWORD');
DB::$dbName = $database;
$GLOBALS['secure_send_test_prefix'] = getenv('TEAMPASS_TEST_PREFIX') ?: 'otv_test_' . bin2hex(random_bytes(4)) . '_';

if (($argv[1] ?? '') === 'worker') {
    $input = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    while (microtime(true) < $input['start']) {
        usleep(1000);
    }
    echo json_encode(secureSendRedeem($input['parameters'], $input['passphrase'], $input['settings']), JSON_THROW_ON_ERROR);
    exit;
}

/** Fail with a readable diagnostic instead of relying on disabled PHP assertions. */
function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Start independent PHP processes, each with its own database connection and recipient session. */
function concurrentRedemptions(array $parameters, array $settings, int $count, string $passphrase = ''): array
{
    putenv('TEAMPASS_TEST_PREFIX=' . $GLOBALS['secure_send_test_prefix']);
    $workers = [];
    $start = microtime(true) + 1;
    for ($i = 0; $i < $count; ++$i) {
        $credentials = isset($parameters[0]) ? $parameters[$i % count($parameters)] : $parameters;
        $input = json_encode(['parameters' => $credentials, 'settings' => $settings, 'passphrase' => $passphrase, 'start' => $start], JSON_THROW_ON_ERROR);
        $process = proc_open([PHP_BINARY, __FILE__, 'worker'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start concurrent recipient');
        }
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        check(proc_close($process) === 0, 'Recipient process failed: ' . $error);
        $results[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
    return $results;
}

$settings = ['otv_is_enabled' => 1, 'secure_send_allow_notes' => 1, 'secure_send_max_views' => 5,
    'otv_expiration_period' => 7, 'cpassman_url' => 'https://vault.example.com', 'otv_subdomain' => 'https://share.example.com'];
$tables = ['otv', 'users', 'send_audit', 'items', 'automatic_del'];
try {
    // Same relevant types/defaults as the installer, including the historical string timestamps.
    DB::query('CREATE TABLE ' . prefixTable('otv') . ' (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, timestamp TEXT NOT NULL, code VARCHAR(100) NOT NULL,
        item_id INT NULL, send_type VARCHAR(10) NOT NULL DEFAULT "item", originator INT NOT NULL,
        encrypted TEXT NOT NULL, protected_key TEXT NULL, has_passphrase TINYINT NOT NULL DEFAULT 0,
        failed_attempts INT NOT NULL DEFAULT 0, views INT NOT NULL DEFAULT 0, max_views INT NULL,
        time_limit VARCHAR(100) NULL, shared_globaly INT NOT NULL DEFAULT 0) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('users') . ' (id INT PRIMARY KEY, admin INT DEFAULT 0, disabled INT DEFAULT 0, deleted_at INT NULL) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('send_audit') . ' (item_id INT, action VARCHAR(30)) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('items') . ' (
        id INT PRIMARY KEY, id_tree INT, label TEXT, login TEXT, url TEXT, description TEXT,
        inactif INT DEFAULT 0, deleted_at INT NULL) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('automatic_del') . ' (
        item_id INT PRIMARY KEY, del_enabled INT, del_type INT, del_value BIGINT) ENGINE=InnoDB');
    DB::insert(prefixTable('users'), ['id' => 42]);

    foreach ([1, 5] as $allowedViews) {
        $created = secureSendFixtureCreate(['send_type' => 'note', 'views' => $allowedViews, 'payload' => ['secret' => 'synthetic-concurrency-secret']]);
        parse_str((string) parse_url($created['url'], PHP_URL_QUERY), $query);
        $parameters = secureSendRequestParameters($query);
        $results = concurrentRedemptions($parameters, $settings, 10);
        $successes = array_filter($results, static fn (array $result): bool => $result['error'] === '');
        check(count($successes) === $allowedViews, 'Concurrent reveals exceeded or lost the view budget');
        check((int) DB::queryFirstField('SELECT views FROM ' . prefixTable('otv') . ' WHERE id = %i', $created['otv_id']) === $allowedViews, 'Stored view count differs from actual reveals');
        foreach ($results as $result) {
            check($result['error'] === '' || !isset($result['fields']), 'A refused request disclosed plaintext');
        }
        echo "OK: $allowedViews permitted views, 10 concurrent recipients\n";
    }
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('send_audit')) === 6, 'Every successful reveal must be audited once');

    $created = secureSendFixtureCreate(['send_type' => 'note', 'passphrase' => 'test-phrase', 'payload' => ['secret' => 'synthetic-secret']]);
    parse_str((string) parse_url($created['url'], PHP_URL_QUERY), $query);
    $results = concurrentRedemptions(secureSendRequestParameters($query), $settings, 10, 'wrong');
    check(count(array_filter($results, static fn (array $r): bool => $r['error'] === '')) === 0, 'Incorrect passphrase revealed a secret');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('otv') . ' WHERE id = %i', $created['otv_id']) === 0, 'Concurrent failed attempts did not revoke the link');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('send_audit')) === 6, 'Failed attempts were counted as reveals');
    echo "OK: concurrent wrong passphrases revoke without revealing or consuming a view\n";

    $settings['enable_delete_after_consultation'] = 1;
    DB::insert(prefixTable('items'), ['id' => 123, 'id_tree' => 7, 'label' => 'Fixture item',
        'login' => '', 'url' => '', 'description' => '']);
    DB::insert(prefixTable('automatic_del'), ['item_id' => 123, 'del_enabled' => 1, 'del_type' => 1, 'del_value' => 1]);
    $pool = [];
    for ($i = 0; $i < 2; ++$i) {
        $created = secureSendFixtureCreate(['views' => 5]);
        parse_str((string) parse_url($created['url'], PHP_URL_QUERY), $query);
        $pool[] = secureSendRequestParameters($query);
    }
    $results = concurrentRedemptions($pool, $settings, 10);
    check(count(array_filter($results, static fn (array $r): bool => $r['error'] === '')) === 1, 'Item deletion budget was exceeded across links');
    check((int) DB::queryFirstField('SELECT inactif FROM ' . prefixTable('items') . ' WHERE id = 123') === 1, 'Exhausted item was not deactivated');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('automatic_del')) === 0, 'Exhausted budget was not removed');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('send_audit') . ' WHERE action = %s', 'at_delete') === 1, 'Automatic deletion was not audited exactly once');
    foreach ($results as $result) {
        check($result['error'] === '' || !isset($result['fields']), 'A refused item request disclosed plaintext');
    }
    echo "OK: concurrent links share the item budget and deactivate it exactly once\n";
} finally {
    foreach ($tables as $table) {
        DB::query('DROP TABLE IF EXISTS ' . prefixTable($table));
    }
}

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
 * @file      secure_send_database.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Disposable MySQL/MariaDB lifecycle and concurrent redemption test.
 * Run only against a database named teampass_test_* (see quality.yml).
 * Storage, recipient logic, structured audit, Defuse and SQL are real.
 * ACL and legacy item-audit adapters are explicit fixtures.
 */
require_once __DIR__ . '/../../app/vendor/autoload.php';
require_once __DIR__ . '/../../app/sources/otp.functions.php';
require_once __DIR__ . '/../Fixtures/secure_send_dependencies.php';
require_once __DIR__ . '/../../app/sources/secure_send.functions.php';
require_once __DIR__ . '/../../app/sources/secure_send_storage.php';

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
    $result = match ($input['operation'] ?? 'reveal') {
        'revoke' => ['revoked' => secureSendRevokeLink((int) $input['parameters']['id'], 42, $input['settings'])],
        'purge' => ['purged' => secureSendPurgeExpiredLinks($input['settings'])],
        default => secureSendRedeem($input['parameters'], $input['passphrase'], $input['settings']),
    };
    echo json_encode($result, JSON_THROW_ON_ERROR);
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
function concurrentRedemptions(array $parameters, array $settings, int $count, string $passphrase = '', string $operation = 'reveal'): array
{
    putenv('TEAMPASS_TEST_PREFIX=' . $GLOBALS['secure_send_test_prefix']);
    $workers = [];
    $start = microtime(true) + 1;
    for ($i = 0; $i < $count; ++$i) {
        $credentials = isset($parameters[0]) ? $parameters[$i % count($parameters)] : $parameters;
        $input = json_encode(['parameters' => $credentials, 'settings' => $settings, 'passphrase' => $passphrase,
            'operation' => $operation, 'start' => $start], JSON_THROW_ON_ERROR);
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
$tables = ['otv', 'users', 'send_audit', 'items', 'automatic_del', 'cache', 'nested_tree',
    'secure_send_audit', 'secure_send_audit_unavailable'];
try {
    // Execute the exact DDL shared by fresh installation and the 3.2.3 migration twice.
    DB::query(secureSendAuditSchemaSql(prefixTable('secure_send_audit')));
    DB::query(secureSendAuditSchemaSql(prefixTable('secure_send_audit')));
    $auditIndexes = DB::query('SHOW INDEX FROM ' . prefixTable('secure_send_audit'));
    check(count(array_unique(array_column($auditIndexes, 'Key_name'))) === 4, 'Audit schema indexes differ from the reporting/history contract');
    // Same relevant types/defaults as the installer, including the historical string timestamps.
    DB::query('CREATE TABLE ' . prefixTable('otv') . ' (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, timestamp TEXT NOT NULL, code VARCHAR(100) NOT NULL,
        item_id INT NULL, send_type VARCHAR(10) NOT NULL DEFAULT "item", originator INT NOT NULL,
        encrypted TEXT NOT NULL, protected_key TEXT NULL, has_passphrase TINYINT NOT NULL DEFAULT 0,
        failed_attempts INT NOT NULL DEFAULT 0, views INT NOT NULL DEFAULT 0, max_views INT NULL,
        time_limit VARCHAR(100) NULL, shared_globaly INT NOT NULL DEFAULT 0) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('users') . ' (
        id INT PRIMARY KEY, name VARCHAR(255) NULL, lastname VARCHAR(255) NULL,
        admin INT DEFAULT 0, disabled INT DEFAULT 0, deleted_at INT NULL) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('send_audit') . ' (item_id INT, action VARCHAR(30)) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('items') . ' (
        id INT PRIMARY KEY, id_tree INT, label TEXT, login TEXT, url TEXT, description TEXT,
        inactif INT DEFAULT 0, deleted_at INT NULL) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('automatic_del') . ' (
        item_id INT PRIMARY KEY, del_enabled INT, del_type INT, del_value BIGINT) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('cache') . ' (id INT PRIMARY KEY) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('nested_tree') . ' (id INT PRIMARY KEY, nb_items_in_folder INT) ENGINE=InnoDB');
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
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_audit') . ' WHERE event = %s', 'revealed') === 6, 'Structured audit lost or duplicated a concurrent reveal');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_audit') . ' WHERE originator <> 42 OR actor_id IS NOT NULL') === 0, 'Anonymous reveals acquired a false actor');

    $created = secureSendFixtureCreate(['send_type' => 'note', 'passphrase' => 'test-phrase', 'payload' => ['secret' => 'synthetic-secret']]);
    parse_str((string) parse_url($created['url'], PHP_URL_QUERY), $query);
    $results = concurrentRedemptions(secureSendRequestParameters($query), $settings, 10, 'wrong');
    check(count(array_filter($results, static fn (array $r): bool => $r['error'] === '')) === 0, 'Incorrect passphrase revealed a secret');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('otv') . ' WHERE id = %i', $created['otv_id']) === 0, 'Concurrent failed attempts did not revoke the link');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('send_audit')) === 6, 'Failed attempts were counted as reveals');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_audit') . ' WHERE send_id = %i AND event = %s',
        $created['otv_id'], 'reveal_failed') === 5, 'Concurrent failures lost or duplicated attempts');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_audit') . ' WHERE send_id = %i AND event = %s AND failed_attempts = 5',
        $created['otv_id'], 'invalidated') === 1, 'Final invalidation evidence was not retained');
    echo "OK: concurrent wrong passphrases revoke without revealing or consuming a view\n";

    $settings['enable_delete_after_consultation'] = 1;
    DB::insert(prefixTable('items'), ['id' => 123, 'id_tree' => 7, 'label' => 'Fixture item',
        'login' => '', 'url' => '', 'description' => '']);
    DB::insert(prefixTable('automatic_del'), ['item_id' => 123, 'del_enabled' => 1, 'del_type' => 1, 'del_value' => 1]);
    DB::insert(prefixTable('cache'), ['id' => 123]);
    DB::insert(prefixTable('nested_tree'), ['id' => 7, 'nb_items_in_folder' => 1]);
    $pool = [];
    for ($i = 0; $i < 2; ++$i) {
        $created = secureSendFixtureCreate(['views' => 5]);
        parse_str((string) parse_url($created['url'], PHP_URL_QUERY), $query);
        $pool[] = secureSendRequestParameters($query);
    }
    $results = concurrentRedemptions($pool, $settings, 10);
    check(count(array_filter($results, static fn (array $r): bool => $r['error'] === '')) === 1, 'Item deletion budget was exceeded across links');
    check((int) DB::queryFirstField('SELECT inactif FROM ' . prefixTable('items') . ' WHERE id = 123') === 1, 'Exhausted item was not deactivated');
    check((int) DB::queryFirstField('SELECT deleted_at FROM ' . prefixTable('items') . ' WHERE id = 123') > 0, 'Deletion timestamp was not set');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('automatic_del') . ' WHERE item_id = 123 AND del_value = 0') === 1, 'Exhausted budget settings were not retained');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('cache')) === 0, 'Deleted item remained in the cache');
    check((int) DB::queryFirstField('SELECT nb_items_in_folder FROM ' . prefixTable('nested_tree') . ' WHERE id = 7') === 0, 'Folder counter was not decremented exactly once');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('send_audit') . ' WHERE action = %s', 'at_delete') === 1, 'Automatic deletion was not audited exactly once');
    foreach ($results as $result) {
        check($result['error'] === '' || !isset($result['fields']), 'A refused item request disclosed plaintext');
    }
    echo "OK: concurrent links share the item budget and deactivate it exactly once\n";

    // A replayed upgrade must preserve the existing journal, not erase/reseed it.
    $before = (int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_audit'));
    DB::query(secureSendAuditSchemaSql(prefixTable('secure_send_audit')));
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_audit')) === $before, 'Replayed migration erased evidence');

    $created = secureSendFixtureCreate(['send_type' => 'note', 'views' => 5]);
    $link = DB::queryFirstRow('SELECT * FROM ' . prefixTable('otv') . ' WHERE id = %i', $created['otv_id']);
    DB::delete(prefixTable('otv'), 'id = %i', $created['otv_id']);
    unset($link['id']);
    $sendId = secureSendStoreLink($link, $settings);
    $results = concurrentRedemptions(['id' => $sendId], $settings, 10, '', 'revoke');
    check(count(array_filter($results, static fn (array $r): bool => $r['revoked'])) === 1, 'Concurrent revocation did not serialize');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_audit') . ' WHERE send_id = %i AND event IN (%s, %s)',
        $sendId, 'created', 'revoked') === 2, 'Creation/revocation evidence did not survive deletion');
    echo "OK: creation and concurrent revocation retain exactly one event each\n";

    $created = secureSendFixtureCreate(['send_type' => 'note']);
    DB::update(prefixTable('otv'), ['time_limit' => time() - 1], 'id = %i', $created['otv_id']);
    concurrentRedemptions([], $settings, 10, '', 'purge');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_audit') . ' WHERE send_id = %i AND event = %s',
        $created['otv_id'], 'expired') === 1, 'Concurrent expiration cleanup duplicated evidence');
    echo "OK: concurrent cleanup records expiration once\n";

    // Invalid historical senders must not occupy the first batch forever. Execute
    // the actual SQL predicate before LIMIT with more than a full invalid batch.
    $invalidIds = [];
    $invalid = secureSendFixtureCreate(['send_type' => 'note']);
    $invalidLink = DB::queryFirstRow('SELECT * FROM ' . prefixTable('otv') . ' WHERE id = %i', $invalid['otv_id']);
    DB::delete(prefixTable('otv'), 'id = %i', $invalid['otv_id']);
    unset($invalidLink['id']);
    $invalidLink['originator'] = 0;
    $invalidLink['time_limit'] = time() - 1;
    for ($index = 0; $index < 101; ++$index) {
        DB::insert(prefixTable('otv'), $invalidLink);
        $invalidIds[] = (int) DB::insertId();
    }
    $valid = secureSendFixtureCreate(['send_type' => 'note']);
    DB::update(prefixTable('otv'), ['time_limit' => time() - 1], 'id = %i', $valid['otv_id']);
    check(secureSendPurgeExpiredLinks($settings) === 1, 'Invalid historical rows starved the auditable batch');
    check(secureSendPurgeExpiredLinks($settings) === 0, 'Invalid historical rows made repeated cleanup fail');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('otv') . ' WHERE id IN %li', $invalidIds) === 101,
        'Invalid historical rows were deleted without evidence');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_audit') . ' WHERE send_id = %i AND event = %s',
        $valid['otv_id'], 'expired') === 1, 'Valid cleanup lost or duplicated expiration evidence');
    // Remove only these synthetic rows from the disposable fixture, not production data.
    DB::query('DELETE FROM ' . prefixTable('otv') . ' WHERE id IN %li', $invalidIds);
    echo "OK: malformed historical senders do not stall atomic expiration cleanup\n";

    $created = secureSendFixtureCreate(['send_type' => 'note', 'views' => 5]);
    parse_str((string) parse_url($created['url'], PHP_URL_QUERY), $query);
    $parameters = secureSendRequestParameters($query);
    $link = DB::queryFirstRow('SELECT * FROM ' . prefixTable('otv') . ' WHERE id = %i', $created['otv_id']);
    // Fail the real SQL insert, preserving the journal in an isolated fixture table.
    DB::query('RENAME TABLE ' . prefixTable('secure_send_audit') . ' TO ' . prefixTable('secure_send_audit_unavailable'));
    check(secureSendRedeem($parameters, '', $settings) === ['error' => 'server_error'], 'Audit failure disclosed plaintext');
    check((int) DB::queryFirstField('SELECT views FROM ' . prefixTable('otv') . ' WHERE id = %i', $created['otv_id']) === 0, 'Audit failure consumed a view');
    try {
        unset($link['id']);
        secureSendStoreLink($link, $settings);
        check(false, 'Unaudited creation succeeded');
    } catch (MeekroDBException $e) {
        check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('otv') . ' WHERE code = %s', $link['code']) === 1, 'Creation audit failure left an orphan link');
    }
    try {
        secureSendRevokeLink($created['otv_id'], 42, $settings);
        check(false, 'Unaudited revocation succeeded');
    } catch (MeekroDBException $e) {
        check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('otv') . ' WHERE id = %i', $created['otv_id']) === 1, 'Revocation audit failure removed the link');
    }
    DB::query('RENAME TABLE ' . prefixTable('secure_send_audit_unavailable') . ' TO ' . prefixTable('secure_send_audit'));
    echo "OK: real audit SQL failures roll back creation, reveal and revocation\n";
} finally {
    foreach ($tables as $table) {
        DB::query('DROP TABLE IF EXISTS ' . prefixTable($table));
    }
}

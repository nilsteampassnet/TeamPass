<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * TeamPass is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See <https://www.gnu.org/licenses/> for the GNU General Public License.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

/** Run only within the disposable-database guard and cleanup of secure_send_database.php. */
function secureSendRetentionDatabaseChecks(): void
{
    global $db_link;
    DB::query('CREATE TABLE ' . prefixTable('log_system') . ' (
        id INT AUTO_INCREMENT PRIMARY KEY, type VARCHAR(20) NOT NULL, date VARCHAR(30) NOT NULL,
        label TEXT NOT NULL, qui VARCHAR(255) NOT NULL, field_1 VARCHAR(250)) ENGINE=InnoDB');
    DB::query('CREATE TABLE ' . prefixTable('misc') . ' (
        type VARCHAR(50), intitule VARCHAR(100), valeur TEXT, UNIQUE KEY (type, intitule)) ENGINE=InnoDB');
    DB::query('DELETE FROM ' . prefixTable('secure_send_audit'));
    $record = secureSendAuditRecord(['id' => 123, 'originator' => 42, 'send_type' => 'note', 'timestamp' => 1], 'created', '', 42, 1);
    DB::insert(prefixTable('secure_send_audit'), $record);

    // Execute the actual installer index helper and retention migration, twice,
    // against a table deployed before retention existed.
    DB::query('ALTER TABLE ' . prefixTable('secure_send_audit') . ' DROP INDEX idx_retention_period');
    if (!function_exists('checkIndexExist')) {
        $functions = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../public/install/tp.functions.php'));
        $start = strpos($functions, 'function checkIndexExist(');
        $end = strpos($functions, "\n}", $start) + 2;
        check($start !== false && $end > $start, 'Installer index helper missing');
        eval(substr($functions, $start, $end - $start));
    }
    $source = (string) file_get_contents(__DIR__ . '/../../public/install/upgrade_run_3.2.3.php');
    $start = strpos($source, '// Secure Send retention migration:');
    $end = strpos($source, '// End Secure Send retention migration.', $start);
    check($start !== false && $end !== false, 'Retention migration missing');
    $migration = substr($source, $start, $end - $start);
    $previousConnection = $db_link ?? null;
    $db_link = DB::getMDB()->get();
    $pre = prefixTable('');
    try {
        eval('use TeampassClasses\\ConfigManager\\ConfigManager;' . $migration);
        check(DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s',
            'secure_send_audit_retention_days') === '0', 'Migration enabled destructive retention by default');
        DB::update(prefixTable('misc'), ['valeur' => '30'], 'intitule = %s', 'secure_send_audit_retention_days');
        eval('use TeampassClasses\\ConfigManager\\ConfigManager;' . $migration);
        check(DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule = %s',
            'secure_send_audit_retention_days') === '30', 'Migration replay overwrote the policy');
        check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_audit')) === 1,
            'Migration replay removed existing evidence');
        $indexes = DB::query('SHOW INDEX FROM ' . prefixTable('secure_send_audit') . ' WHERE Key_name = %s', 'idx_retention_period');
        check(array_column($indexes, 'Column_name') === ['occurred_at', 'id'], 'Retention migration did not add the ordered index');
    } finally {
        $db_link = $previousConnection;
    }
    echo "OK: retention migration preserves evidence and policy on replay\n";

    $policy = ['secure_send_audit_retention_days' => 1, 'otv_is_enabled' => 0];
    $now = 200000;
    $cutoff = $now - 86400;
    check(secureSendPruneAuditHistory([], $now) === 0, 'Missing retention setting deleted evidence');
    check(secureSendPruneAuditHistory(['secure_send_audit_retention_days' => '0'], $now) === 0, 'Disabled retention deleted evidence');
    // A failing summary insert must roll back the DELETE on real InnoDB.
    DB::query('ALTER TABLE ' . prefixTable('log_system') . ' CHANGE field_1 invalid_field VARCHAR(250)');
    try {
        secureSendPruneAuditHistory($policy, $now);
        check(false, 'Summary failure committed a deletion');
    } catch (MeekroDBException $e) {
        check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_audit')) === 1,
            'Summary failure lost evidence');
    }
    DB::query('ALTER TABLE ' . prefixTable('log_system') . ' CHANGE invalid_field field_1 VARCHAR(250)');
    DB::query('ALTER TABLE ' . prefixTable('log_system') . ' ENGINE=MyISAM');
    try {
        secureSendPruneAuditHistory($policy, $now);
        check(false, 'Nontransactional audit summary accepted');
    } catch (RuntimeException $e) {
        check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_audit')) === 1,
            'Unsafe table engine allowed deletion');
    }
    DB::query('ALTER TABLE ' . prefixTable('log_system') . ' ENGINE=InnoDB');
    check(secureSendPruneAuditHistory($policy, $now) === 1, 'Valid retention did not delete the old event');
    $summary = json_decode((string) DB::queryFirstField('SELECT field_1 FROM ' . prefixTable('log_system')), true, 512, JSON_THROW_ON_ERROR);
    check($summary === ['retention_days' => 1, 'cutoff' => $cutoff, 'deleted_count' => 1, 'batch_limit' => 1000],
        'Retention summary differs from actual deletion');
    echo "OK: real SQL summary failures roll back deletion; unsafe engines fail closed\n";

    // Concurrent workers share no connection: bounded DELETE locks must prevent
    // counting/deleting the same event twice and preserve exact-cutoff events.
    for ($index = 0; $index < 1003; ++$index) {
        $record['occurred_at'] = 1 + $index;
        DB::insert(prefixTable('secure_send_audit'), $record);
    }
    $record['occurred_at'] = $cutoff;
    DB::insert(prefixTable('secure_send_audit'), $record);
    $record['occurred_at'] = $cutoff + 1;
    DB::insert(prefixTable('secure_send_audit'), $record);
    $results = concurrentRedemptions(['now' => $now], $policy, 2, '', 'retention');
    $counts = array_column($results, 'pruned');
    sort($counts);
    check($counts === [3, 1000], 'Concurrent retention exceeded its batch or duplicated deletion');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_audit')) === 2,
        'Retention removed events on/after the cutoff');
    check(secureSendPruneAuditHistory($policy, $now) === 0, 'Repeated retention duplicated work');
    check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('log_system')) === 3,
        'Empty runs generated evidence or concurrent batches lost summaries');
    $summaries = DB::query('SELECT field_1 FROM ' . prefixTable('log_system'));
    $deleted = array_sum(array_map(static fn (array $row): int => json_decode($row['field_1'], true, 512, JSON_THROW_ON_ERROR)['deleted_count'], $summaries));
    check($deleted === 1004, 'Summary count differs from actually deleted events');
    $statistics = secureSendBuildOperationalStatistics($cutoff, $cutoff + 1, $policy, []);
    check($statistics['available'] && $statistics['totals']['created'] === 2, 'Retention broke retained-period statistics');
    echo "OK: concurrent retention is bounded, replay-safe, cutoff-exact and compatible with statistics\n";
}

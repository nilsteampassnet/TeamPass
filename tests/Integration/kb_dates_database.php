<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License v3.0.
 * See <https://www.gnu.org/licenses/>.
 */

require_once __DIR__ . '/../../public/install/kb_dates_migration.php';

$database = (string) getenv('TEAMPASS_TEST_DB');
if (!preg_match('/^teampass_test_[a-z0-9_]+$/', $database)) {
    throw new RuntimeException('A disposable teampass_test_* database is required.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$connection = new mysqli(getenv('TEAMPASS_TEST_HOST') ?: '127.0.0.1',
    getenv('TEAMPASS_TEST_USER') ?: 'root', (string) getenv('TEAMPASS_TEST_PASSWORD'),
    $database, (int) (getenv('TEAMPASS_TEST_PORT') ?: 3306));
$prefix = 'kb_dates_' . bin2hex(random_bytes(4)) . '_';
$articleTable = $prefix . 'kb';
$miscTable = $prefix . 'misc';

/** Fail explicitly even when PHP's assertions are disabled. */
function kbDatesExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    // Execute the real fresh-install SQL expression, without booting the HTTP installer.
    $installer = (string) file_get_contents(__DIR__ . '/../../public/install/install-steps/run.step5.php');
    kbDatesExpect(preg_match('/private function kb\(\)[\s\S]*?DB::query\(\s*("[\s\S]*?")\s*\);/', $installer, $match) === 1,
        'Missing fresh-install article schema');
    $fixture = new class ($prefix) {
        public array $inputData;
        /** Supply only the table-prefix input used by the real installer expression. */
        public function __construct(string $prefix) { $this->inputData = ['tablePrefix' => $prefix]; }
    };
    $sql = (function () use ($match): string { return eval('return ' . $match[1] . ';'); })->call($fixture);
    $connection->query($sql);
    $columns = $connection->query('SHOW COLUMNS FROM `' . $articleTable . '`')->fetch_all(MYSQLI_ASSOC);
    $byName = array_column($columns, null, 'Field');
    foreach (['created_at', 'updated_at'] as $column) {
        kbDatesExpect($byName[$column]['Null'] === 'YES' && $byName[$column]['Default'] === null,
            'Fresh install must preserve unknown dates');
    }

    // Upgrade a legacy table, using a non-default prefix and retained JSON journal rows.
    $connection->query('ALTER TABLE `' . $articleTable . '` DROP COLUMN created_at, DROP COLUMN updated_at');
    $connection->query('CREATE TABLE `' . $miscTable . '` (type VARCHAR(100), intitule VARCHAR(250),
        valeur LONGTEXT, created_at BIGINT, UNIQUE KEY uk_type_intitule (type, intitule))');
    foreach ([1, 2, 3, 4, 5] as $id) {
        $connection->query('INSERT INTO `' . $articleTable . '` (id, category_id, label, description, author_id)
            VALUES (' . $id . ", 7, 'Article', 'Body', 2)");
    }
    $statement = $connection->prepare('INSERT INTO `' . $miscTable . '` VALUES (?, ?, ?, ?)');
    foreach ([[1, 'at_creation', 100, ''], [1, 'at_modification', 200, 'description'],
        [1, 'at_modification', 300, 'comment_add'], [1, 'at_modification', 400, ''],
        [2, 'at_modification', 500, 'comment_delete'], [3, 'at_modification', 250, 'attachments_upload'],
        [4, 'at_creation', 300, ''], [4, 'at_modification', 300, 'label'],
        [1, 'at_shown', 900, '']] as [$id, $action, $timestamp, $reason]) {
        $type = 'kb_log';
        $intitule = 'kb_log_' . $action . '_' . $id . '_' . $timestamp;
        $json = json_encode(['kb_id' => $id, 'action' => $action, 'date' => $timestamp, 'reason' => $reason]);
        $statement->bind_param('sssi', $type, $intitule, $json, $timestamp);
        $statement->execute();
    }
    // A view-prefixed row must not reach the decoder, even with a creation-shaped payload.
    $intitule = 'kb_log_at_shown_5_999';
    $timestamp = 999;
    $json = json_encode(['kb_id' => 5, 'action' => 'at_creation', 'date' => $timestamp]);
    $statement->execute();
    $statement->close();
    kbUpgradeArticleDates($connection, $articleTable, $miscTable);
    $rows = $connection->query('SELECT id, created_at, updated_at FROM `' . $articleTable . '` ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    kbDatesExpect((int) $rows[0]['created_at'] === 100 && (int) $rows[0]['updated_at'] === 200, 'Incorrect historical dates');
    kbDatesExpect($rows[1]['created_at'] === null && $rows[1]['updated_at'] === null, 'Comments must not invent dates');
    kbDatesExpect($rows[2]['created_at'] === null && (int) $rows[2]['updated_at'] === 250, 'Partial history must stay partial');
    kbDatesExpect((int) $rows[3]['created_at'] === 300 && (int) $rows[3]['updated_at'] === 300, 'Same-second edits must survive');
    kbDatesExpect($rows[4]['created_at'] === null && $rows[4]['updated_at'] === null,
        'View-prefixed history must be excluded before decoding');

    $connection->query('UPDATE `' . $articleTable . '` SET updated_at = 1000 WHERE id = 1');
    kbUpgradeArticleDates($connection, $articleTable, $miscTable);
    kbDatesExpect((int) $connection->query('SELECT updated_at FROM `' . $articleTable . '` WHERE id = 1')->fetch_row()[0] === 1000,
        'Migration replay overwrote a recorded edit');
    $connection->query('DELETE FROM `' . $miscTable . '`');
    kbUpgradeArticleDates($connection, $articleTable, $miscTable);
    kbDatesExpect((int) $connection->query('SELECT created_at FROM `' . $articleTable . '` WHERE id = 1')->fetch_row()[0] === 100,
        'Journal purge removed an article date');
    echo "Knowledge base dates: fresh install, migration, replay and journal purge passed.\n";
} finally {
    $connection->query('DROP TABLE IF EXISTS `' . $miscTable . '`');
    $connection->query('DROP TABLE IF EXISTS `' . $articleTable . '`');
    $connection->close();
}

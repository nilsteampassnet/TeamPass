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
 * Collation regression checks against a disposable MySQL/MariaDB database.
 *
 * @file      admin_activity_database.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require_once __DIR__ . '/../../app/vendor/sergeytsalkov/meekrodb/db.class.php';
require_once __DIR__ . '/../../app/sources/admin_activity_logic.php';

$database = (string) getenv('TEAMPASS_TEST_DB');
if (!preg_match('/^teampass_test_[a-z0-9_]+$/', $database)) {
    throw new RuntimeException('A disposable teampass_test_* database is required.');
}
DB::$host = getenv('TEAMPASS_TEST_HOST') ?: '127.0.0.1';
DB::$port = (int) (getenv('TEAMPASS_TEST_PORT') ?: 3306);
DB::$user = getenv('TEAMPASS_TEST_USER') ?: 'root';
DB::$password = (string) getenv('TEAMPASS_TEST_PASSWORD');
DB::$dbName = $database;
DB::$encoding = 'utf8mb4';
DB::query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');

$tables = ['users' => 'activity_test_users', 'items' => 'activity_test_items',
    'log_items' => 'activity_test_log_items', 'log_system' => 'activity_test_log_system', 'misc' => 'activity_test_misc'];
DB::query('CREATE TEMPORARY TABLE activity_test_users (id INT, login TEXT) COLLATE utf8mb4_unicode_ci');
DB::query('CREATE TEMPORARY TABLE activity_test_items (id INT, label TEXT) COLLATE utf8mb4_unicode_ci');
DB::query('CREATE TEMPORARY TABLE activity_test_log_items
    (increment_id INT, date VARCHAR(30), id_user INT, action TEXT, raison TEXT, id_item INT) COLLATE utf8mb4_unicode_ci');
DB::query('CREATE TEMPORARY TABLE activity_test_log_system
    (id INT, date VARCHAR(30), type VARCHAR(100), label TEXT, qui VARCHAR(255), field_1 TEXT) COLLATE utf8mb4_unicode_ci');
DB::query('CREATE TEMPORARY TABLE activity_test_misc
    (increment_id INT, created_at VARCHAR(30), type TEXT, valeur TEXT) COLLATE utf8mb4_unicode_ci');
DB::query('INSERT INTO activity_test_users VALUES (1, %s)', 'alice');
DB::query('INSERT INTO activity_test_items VALUES (1, %s)', 'Item');
DB::query('INSERT INTO activity_test_log_items VALUES (1, %s, 1, %s, %s, 1), (2, %s, 1, %s, %s, 1)',
    '990', 'at_creation', '', '990', 'at_shown', 'tp_src=api');
DB::query('INSERT INTO activity_test_log_system VALUES (1, %s, %s, %s, %s, %s), (2, %s, %s, %s, %s, %s)',
    '990', 'failed_auth', 'wrong_mfa_code', '127.0.0.1', 'unknown', '990', 'user_connection', 'connection', '1', '');
DB::query('INSERT INTO activity_test_misc VALUES (1, %s, %s, %s)', '990', 'kb_log',
    json_encode(['action' => 'at_creation', 'user_login' => 'alice', 'label' => 'Article'], JSON_THROW_ON_ERROR));

// Verify that this fixture really reproduces the old implicit-collation conflict.
$oldJoinFailed = false;
try {
    DB::query('SELECT u.id FROM activity_test_log_system l
        LEFT JOIN activity_test_users u ON l.qui = CAST(u.id AS CHAR)');
} catch (MeekroDBException $error) {
    $oldJoinFailed = str_contains($error->getMessage(), 'Illegal mix of collations');
}
if (!$oldJoinFailed) {
    throw new RuntimeException('The fixture did not reproduce the original collation failure.');
}

$categories = ['changes', 'accesses', 'failed', 'connections', 'kb'];
for ($mask = 1; $mask < 32; ++$mask) {
    $selected = array_values(array_filter($categories, static fn ($key) => ($mask & (1 << $key)) !== 0, ARRAY_FILTER_USE_KEY));
    [$sql, $values] = adminActivityQuery(adminActivityOptions(['categories' => $selected], 1000, true), $tables);
    $rows = DB::query('SELECT * FROM (' . $sql . ') activity ORDER BY timestamp DESC, source_rank DESC, event_id DESC', ...$values);
    if (count($rows) !== count($selected)) {
        throw new RuntimeException('Unexpected rows for categories: ' . implode(',', $selected));
    }
    foreach ($rows as $row) {
        $formatted = adminActivityFormat($row, static fn ($key) => $key);
        $expectedLogin = $row['source_type'] === 'failed_auth' ? 'unknown' : 'alice';
        if ($formatted['user_login'] !== $expectedLogin) {
            throw new RuntimeException('Incorrect authentication join or formatted login.');
        }
    }
}
echo "OK — all 31 activity category combinations passed with differing connection/table collations.\n";

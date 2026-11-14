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
 * @file      secure_send_upgrade_database.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/** Run the legacy OTV migration within the disposable Secure Send database harness. */
function secureSendUpgradeDatabaseChecks(): void
{
    $source = (string) file_get_contents(__DIR__ . '/../../public/install/upgrade_run_3.0.0.php');
    $start = strpos($source, '// Alter table otv');
    check($start !== false, 'Legacy OTV migration start marker missing');
    $end = strpos($source, '// Add column increment_id to restriction_to_roles table', $start);
    check($end !== false, 'Legacy OTV migration end marker missing');
    $migration = substr($source, $start, $end - $start);
    $pre = prefixTable('upgrade_');
    $table = prefixTable('upgrade_otv');
    $db_link = DB::getMDB()->get();
    $previousMode = DB::queryFirstField('SELECT @@SESSION.sql_mode');

    try {
        DB::query('SET SESSION sql_mode = %s', 'STRICT_ALL_TABLES');
        DB::query('CREATE TABLE ' . $table . ' (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, item_id INT NULL DEFAULT NULL,
            originator INT NOT NULL, send_type VARCHAR(10) NOT NULL, encrypted TEXT NOT NULL) ENGINE=InnoDB');
        foreach ([['item_id' => null, 'send_type' => 'note'], ['item_id' => 123, 'send_type' => 'item']] as $link) {
            DB::insert($table, $link + ['originator' => 42, 'encrypted' => 'synthetic-encrypted-payload']);
        }
        $before = DB::query('SELECT * FROM ' . $table . ' ORDER BY id');

        for ($attempt = 0; $attempt < 2; ++$attempt) {
            eval($migration);
            check($db_link->errno === 0, 'Legacy OTV migration failed: ' . $db_link->error);
            check(DB::query('SELECT * FROM ' . $table . ' ORDER BY id') === $before,
                'Legacy OTV migration changed existing note or item links');
            $column = DB::queryFirstRow('SHOW COLUMNS FROM ' . $table . ' WHERE Field = %s', 'item_id');
            check($column['Null'] === 'YES' && $column['Default'] === null,
                'Legacy OTV migration must keep item_id nullable for standalone notes');
        }

        // Older installations have only item links and a NOT NULL item_id.
        DB::query('DELETE FROM ' . $table . ' WHERE item_id IS NULL');
        DB::query('ALTER TABLE ' . $table . ' MODIFY item_id INT NOT NULL');
        eval($migration);
        check($db_link->errno === 0, 'Legacy item-only OTV migration failed: ' . $db_link->error);
        check((int) DB::queryFirstField('SELECT item_id FROM ' . $table) === 123,
            'Legacy item-only OTV migration changed the source item');
        DB::insert($table, ['item_id' => null, 'originator' => 42, 'send_type' => 'note',
            'encrypted' => 'synthetic-encrypted-payload']);
        check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . $table . ' WHERE item_id IS NULL') === 1,
            'Upgraded OTV schema rejected a standalone note');
    } finally {
        DB::query('DROP TABLE IF EXISTS ' . $table);
        DB::query('SET SESSION sql_mode = %s', $previousMode);
    }
    echo "OK: legacy OTV migration preserves note/item links on replay and accepts new notes\n";
}

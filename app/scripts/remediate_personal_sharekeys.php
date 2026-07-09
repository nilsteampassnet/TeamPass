<?php
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
 * Remediation for SEC-8: personal items created with the previous code received a sharekey for
 * EVERY user (the personal/owner-only intent was silently ignored). This script removes the
 * foreign sharekeys from existing personal items, keeping only the owner and the TP_USER_ID /
 * system recovery keys — i.e. it enforces invariant I1 on already-stored data.
 *
 * It is a bulk generalisation of EnsurePersonalItemHasOnlyKeysForOwner() (sources/main.functions.php),
 * but, unlike the lazy cleanup, it also processes personal items owned by an admin (decision 2026-06-15).
 *
 * Owner resolution (conservative, no deletion on doubt):
 *   - folder owner = numeric title of the personal root folder (personal_folder = 1) above the item;
 *   - cross-checked with log_items.at_creation.id_user;
 *   - any disagreement or unresolved owner => the item is reported and SKIPPED (no deletion).
 *
 * Usage (run from the TeamPass root directory):
 *   php app/scripts/remediate_personal_sharekeys.php [--dry-run|--execute] [--help]
 *
 * Options:
 *   --dry-run   Analyse and report only — NO changes (default).
 *   --execute   Actually delete the foreign sharekeys (irreversible — back up the DB first).
 *   --help      Show this help message.
 *
 * @file      remediate_personal_sharekeys.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

// CLI only
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Bootstraps config, constants (TP_USER_ID, ...), DB and helpers (prefixTable, loadClasses, ...)
require_once __DIR__ . '/../sources/main.functions.php';

// Pure, unit-tested decision logic shared with tests/Unit/PersonalSharekeysLogicTest.php
require_once __DIR__ . '/personal_sharekeys_logic.php';

loadClasses('DB');

$options = getopt('', ['dry-run', 'execute', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
TeamPass — Personal items sharekey remediation (SEC-8)
======================================================

Removes foreign sharekeys wrongly distributed to all users on personal items.
Only the owner and the TP_USER_ID / system recovery keys are kept.

Usage: php app/scripts/remediate_personal_sharekeys.php [OPTIONS]
       (run from the TeamPass root directory)

Options:
  --dry-run   Analyse and report only — NO changes (default)
  --execute   Apply the deletions (irreversible — BACK UP THE DATABASE FIRST)
  --help      Show this help message

The script never deletes anything for an item whose owner is ambiguous
(folder owner unresolved, or disagreeing with the at_creation log).

HELP;
    exit(0);
}

// Dry-run is the default; deletions happen only with --execute.
$execute = isset($options['execute']);

echo "\n=== TeamPass — Personal items sharekey remediation (SEC-8) ===\n\n";
echo $execute
    ? "EXECUTE mode: foreign sharekeys WILL be deleted. Make sure you have a DB backup.\n\n"
    : "DRY-RUN mode (default): no change will be made. Use --execute to apply.\n\n";

// System users that must always keep a sharekey on a personal item (recovery / internal accounts).
$systemUsers = [(int) TP_USER_ID, (int) API_USER_ID, (int) OTV_USER_ID, (int) SSH_USER_ID];

/**
 * Resolve the owner of a personal item from its folder hierarchy.
 *
 * Walks up the nested tree to the absolute root (parent_id = 0) of the item's tree. The personal
 * tree root carries the owner user id as its title and personal_folder = 1. Intermediate
 * sub-folders may carry an inconsistent personal_folder flag (e.g. after a copy), so the decision
 * relies on the absolute root, not on intermediate flags. Returns null when the owner cannot be
 * resolved safely (broken hierarchy, non-personal root, non-numeric or system title).
 *
 * @param int   $folderId      Item id_tree.
 * @param int[] $systemUserIds System user ids that can never be an item owner.
 *
 * @return int|null Owner user id, or null when unresolved.
 */
function resolvePersonalFolderOwner(int $folderId, array $systemUserIds): ?int
{
    $guard = 0;
    $currentId = $folderId;
    $rootNode = null;

    while ($currentId > 0 && $guard < 200) {
        $node = DB::queryFirstRow(
            'SELECT id, parent_id, personal_folder, title
             FROM ' . prefixTable('nested_tree') . '
             WHERE id = %i',
            $currentId
        );
        if ($node === null) {
            // Broken hierarchy: do not guess.
            return null;
        }
        $rootNode = $node;
        $currentId = (int) $node['parent_id'];
        ++$guard;
    }

    // Delegate the DB-free decision (root flag + numeric/non-system title) to the shared,
    // unit-tested logic.
    $ownerId = personalRootOwnerId($rootNode, $systemUserIds);
    if ($ownerId === null) {
        return null;
    }

    // The owner must be a real existing user.
    $exists = DB::queryFirstField(
        'SELECT id FROM ' . prefixTable('users') . ' WHERE id = %i',
        $ownerId
    );

    return $exists === null ? null : $ownerId;
}

/**
 * Count the foreign sharekeys that would be removed for a personal item.
 *
 * @param int      $itemId   Item id.
 * @param string[] $excluded User ids that must keep their sharekey (owner + system users).
 *
 * @return array{items:int,fields:int,files:int,logs:int,total:int}
 */
function countForeignSharekeys(int $itemId, array $excluded): array
{
    $items = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('sharekeys_items') . '
         WHERE object_id = %i AND user_id NOT IN %ls',
        $itemId,
        $excluded
    );
    $files = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('sharekeys_files') . '
         WHERE object_id IN (SELECT id FROM ' . prefixTable('files') . ' WHERE id_item = %i)
         AND user_id NOT IN %ls',
        $itemId,
        $excluded
    );
    $fields = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('sharekeys_fields') . '
         WHERE object_id IN (SELECT id FROM ' . prefixTable('categories_items') . ' WHERE item_id = %i)
         AND user_id NOT IN %ls',
        $itemId,
        $excluded
    );
    $logs = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('sharekeys_logs') . '
         WHERE object_id IN (SELECT increment_id FROM ' . prefixTable('log_items') . ' WHERE id_item = %i)
         AND user_id NOT IN %ls',
        $itemId,
        $excluded
    );

    return [
        'items'  => $items,
        'fields' => $fields,
        'files'  => $files,
        'logs'   => $logs,
        'total'  => $items + $fields + $files + $logs,
    ];
}

/**
 * Delete the foreign sharekeys for a personal item (transactional).
 *
 * @param int      $itemId   Item id.
 * @param string[] $excluded User ids that must keep their sharekey (owner + system users).
 *
 * @return bool True on success, false on rollback.
 */
function deleteForeignSharekeys(int $itemId, array $excluded): bool
{
    try {
        DB::startTransaction();

        DB::delete(
            prefixTable('sharekeys_items'),
            'object_id = %i AND user_id NOT IN %ls',
            $itemId,
            $excluded
        );
        DB::query(
            'DELETE FROM ' . prefixTable('sharekeys_files') . '
             WHERE object_id IN (SELECT id FROM ' . prefixTable('files') . ' WHERE id_item = %i)
             AND user_id NOT IN %ls',
            $itemId,
            $excluded
        );
        DB::query(
            'DELETE FROM ' . prefixTable('sharekeys_fields') . '
             WHERE object_id IN (SELECT id FROM ' . prefixTable('categories_items') . ' WHERE item_id = %i)
             AND user_id NOT IN %ls',
            $itemId,
            $excluded
        );
        DB::query(
            'DELETE FROM ' . prefixTable('sharekeys_logs') . '
             WHERE object_id IN (SELECT increment_id FROM ' . prefixTable('log_items') . ' WHERE id_item = %i)
             AND user_id NOT IN %ls',
            $itemId,
            $excluded
        );

        DB::commit();
        return true;
    } catch (Throwable $e) {
        DB::rollback();
        echo '    ERROR: rollback for item ' . $itemId . ' — ' . $e->getMessage() . "\n";
        return false;
    }
}

// ─────────────────────────────────────────────────────────────
// Main loop
// ─────────────────────────────────────────────────────────────
$personalItems = DB::query(
    'SELECT id, id_tree FROM ' . prefixTable('items') . ' WHERE perso = 1 ORDER BY id ASC'
);

$stats = [
    'total'               => count($personalItems),
    'items_with_foreign'  => 0,
    'cleaned'             => 0,
    'already_clean'       => 0,
    'skipped_unresolved'  => 0,
    'skipped_conflict'    => 0,
    'foreign_found'       => 0,
    'foreign_deleted'     => 0,
];

echo '[i] ' . $stats['total'] . " personal item(s) to analyse.\n\n";

foreach ($personalItems as $item) {
    $itemId = (int) $item['id'];

    // 1. Resolve the folder owner.
    $folderOwner = resolvePersonalFolderOwner((int) $item['id_tree'], $systemUsers);
    if ($folderOwner === null) {
        ++$stats['skipped_unresolved'];
        echo "[SKIP] item {$itemId}: owner could not be resolved from the folder hierarchy.\n";
        continue;
    }

    // 2. Cross-check with the at_creation log.
    $creator = DB::queryFirstField(
        'SELECT id_user FROM ' . prefixTable('log_items') . '
         WHERE id_item = %i AND action = %s
         ORDER BY date ASC LIMIT 1',
        $itemId,
        'at_creation'
    );
    if (personalOwnerConflictsWithCreator($folderOwner, $creator) === true) {
        ++$stats['skipped_conflict'];
        echo "[SKIP] item {$itemId}: folder owner ({$folderOwner}) disagrees with at_creation user ("
            . (int) $creator . ") — left untouched.\n";
        continue;
    }

    // 3. Compute the foreign sharekeys.
    $excluded = array_map('strval', personalSharekeyKeepList($folderOwner, $systemUsers));
    $counts = countForeignSharekeys($itemId, $excluded);

    if ($counts['total'] === 0) {
        ++$stats['already_clean'];
        continue;
    }

    ++$stats['items_with_foreign'];
    $stats['foreign_found'] += $counts['total'];
    echo "[FIX]  item {$itemId} (owner {$folderOwner}): {$counts['total']} foreign sharekey(s)"
        . " [items={$counts['items']}, fields={$counts['fields']}, files={$counts['files']}, logs={$counts['logs']}]";

    if ($execute === true) {
        if (deleteForeignSharekeys($itemId, $excluded) === true) {
            ++$stats['cleaned'];
            $stats['foreign_deleted'] += $counts['total'];
            echo " — deleted.\n";
        } else {
            echo " — FAILED.\n";
        }
    } else {
        echo " — would delete (dry-run).\n";
    }
}

// ─────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────
echo "\n=== Summary ===\n";
echo "Personal items analysed   : {$stats['total']}\n";
echo "Already clean             : {$stats['already_clean']}\n";
echo "Items with foreign keys   : {$stats['items_with_foreign']}\n";
echo "Skipped (unresolved owner): {$stats['skipped_unresolved']}\n";
echo "Skipped (owner conflict)  : {$stats['skipped_conflict']}\n";
echo "Foreign sharekeys found   : {$stats['foreign_found']}\n";
if ($execute === true) {
    echo "Items cleaned             : {$stats['cleaned']}\n";
    echo "Foreign sharekeys deleted : {$stats['foreign_deleted']}\n";
    echo "\nDone.\n";
} else {
    echo "\nDRY-RUN only — re-run with --execute to apply (back up the database first).\n";
}

exit(0);

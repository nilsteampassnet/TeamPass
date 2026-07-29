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
 * Remediation engine for personal-item sharekeys: removes the sharekeys wrongly held by users who
 * do not own the personal item, keeping only the owner and the system/recovery accounts.
 *
 * Two distinct defects put those keys in the database:
 *   - before 3.2.1.0, personal items were shared with EVERY user at creation (SEC-8);
 *   - in 3.2.1.0/3.2.1.1, user key generation redistributed TP_USER's recovery sharekeys, which
 *     since the SEC-8 fix cover every personal item, to each user being (re)keyed.
 *
 * DB-aware counterpart of personal_sharekeys_logic.php (pure decision logic). Shared by:
 *   - app/scripts/remediate_personal_sharekeys.php   (manual CLI run, supports dry-run)
 *   - public/install/upgrade_run_3.2.1.php           (automatic run at upgrade)
 *
 * Owner resolution is conservative: an item whose owner cannot be resolved from the folder
 * hierarchy, or whose folder owner disagrees with the at_creation log, is reported and SKIPPED.
 *
 * @file      personal_sharekeys_remediation.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

// Pure, unit-tested decision logic shared with tests/Unit/PersonalSharekeysLogicTest.php
require_once __DIR__ . '/personal_sharekeys_logic.php';

if (function_exists('remediatePersonalSharekeys') === false) {
    /**
     * System users that must always keep a sharekey on a personal item (recovery / internal accounts).
     *
     * @return int[]
     */
    function personalSharekeysSystemUsers(): array
    {
        return [(int) TP_USER_ID, (int) API_USER_ID, (int) OTV_USER_ID, (int) SSH_USER_ID];
    }

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
     * Each sharekeys table is keyed by its own object id (files.id, categories_items.id,
     * log_items.increment_id), never by the item id — hence the sub-queries.
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
            error_log('TEAMPASS Error - personal sharekeys remediation: rollback for item ' . $itemId . ' - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remediate every personal item of the instance.
     *
     * Candidates = items flagged personal OR stored in a personal folder. The items.perso column
     * alone is not enough: items created while the client sent folder_is_personal = 0 are stored
     * with perso = 0 in a personal folder (the flag is only repaired later, on folder listing). The
     * owner is resolved from the absolute tree root anyway, so a folder-based candidate is as safe.
     *
     * Idempotent: an already clean item costs 4 COUNT queries and no write.
     *
     * @param bool          $execute  False = analyse only (dry-run), true = apply the deletions.
     * @param callable|null $reporter Optional per-item progress callback, receives one text line.
     *
     * @return array{total:int,items_with_foreign:int,cleaned:int,already_clean:int,skipped_unresolved:int,skipped_conflict:int,foreign_found:int,foreign_deleted:int}
     */
    function remediatePersonalSharekeys(bool $execute = false, ?callable $reporter = null): array
    {
        $systemUsers = personalSharekeysSystemUsers();

        $personalItems = DB::query(
            'SELECT i.id, i.id_tree
            FROM ' . prefixTable('items') . ' AS i
            INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON (n.id = i.id_tree)
            WHERE i.perso = 1 OR n.personal_folder = 1
            ORDER BY i.id ASC'
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

        // Personal items are concentrated in a few folders: resolve each folder owner only once.
        $ownerByFolder = [];

        foreach ($personalItems as $item) {
            $itemId = (int) $item['id'];
            $folderId = (int) $item['id_tree'];

            // 1. Resolve the folder owner.
            if (array_key_exists($folderId, $ownerByFolder) === false) {
                $ownerByFolder[$folderId] = resolvePersonalFolderOwner($folderId, $systemUsers);
            }
            $folderOwner = $ownerByFolder[$folderId];
            if ($folderOwner === null) {
                ++$stats['skipped_unresolved'];
                if ($reporter !== null) {
                    $reporter("[SKIP] item {$itemId}: owner could not be resolved from the folder hierarchy.");
                }
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
                if ($reporter !== null) {
                    $reporter("[SKIP] item {$itemId}: folder owner ({$folderOwner}) disagrees with at_creation user ("
                        . (int) $creator . ') — left untouched.');
                }
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
            $line = "[FIX]  item {$itemId} (owner {$folderOwner}): {$counts['total']} foreign sharekey(s)"
                . " [items={$counts['items']}, fields={$counts['fields']}, files={$counts['files']}, logs={$counts['logs']}]";

            if ($execute === false) {
                if ($reporter !== null) {
                    $reporter($line . ' — would delete (dry-run).');
                }
                continue;
            }

            if (deleteForeignSharekeys($itemId, $excluded) === true) {
                ++$stats['cleaned'];
                $stats['foreign_deleted'] += $counts['total'];
                if ($reporter !== null) {
                    $reporter($line . ' — deleted.');
                }
            } elseif ($reporter !== null) {
                $reporter($line . ' — FAILED.');
            }
        }

        return $stats;
    }
}

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
 * @file      users_purge.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

if (function_exists('loadClasses') && !class_exists('DB')) {
    loadClasses('DB');
}

/**
 * Hard-delete a previously soft-deleted user.
 *
 * The user MUST already be soft-deleted (deleted_at > 0).
 * This function focuses on DB operations only (no UI translations, no session checks).
 *
 * @return array{error:bool, message_key:string, debug?:string}
 */
function tpPurgeDeletedUserById(int $userId): array
{
    // Ensure DB is loaded (for workers)
    if (function_exists('loadClasses') && !class_exists('DB')) {
        loadClasses('DB');
    }

    // Verify user is soft-deleted
    $user = DB::queryFirstRow(
        'SELECT id, login, deleted_at
         FROM ' . prefixTable('users') . '
         WHERE id = %i
         AND deleted_at IS NOT NULL
         AND deleted_at > 0',
        $userId
    );

    if (empty($user) === true) {
        return [
            'error' => true,
            'message_key' => 'user_not_found_or_not_deleted',
        ];
    }

    DB::startTransaction();
    try {
        // Delete private keys
        DB::delete(prefixTable('user_private_keys'), 'user_id = %i', $userId);

        // Delete user api
        DB::delete(prefixTable('api'), 'user_id = %i', $userId);

        // Delete cache
        DB::delete(prefixTable('cache'), 'author = %i', $userId);

        // Delete cache_tree
        DB::delete(prefixTable('cache_tree'), 'user_id = %i', $userId);

        // Delete tokens
        DB::delete(prefixTable('tokens'), 'user_id = %i', $userId);

        // Delete personal folder and subfolders
        $data = DB::queryFirstRow(
            'SELECT id FROM ' . prefixTable('nested_tree') . '
            WHERE title = %s AND personal_folder = %i',
            (string) $userId,
            1
        );

        if (!empty($data['id'])) {
            $tree = new \TeampassClasses\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $folders = $tree->getDescendants((int) $data['id'], true);
            foreach ($folders as $folder) {
                // delete folder
                DB::delete(prefixTable('nested_tree'), 'id = %i AND personal_folder = %i', (int) $folder->id, 1);

                // delete items & logs
                $items = DB::query(
                    'SELECT id FROM ' . prefixTable('items') . '
                    WHERE id_tree = %i AND perso = %i',
                    (int) $folder->id,
                    1
                );
                foreach ($items as $item) {
                    DB::delete(prefixTable('items'), 'id = %i', (int) $item['id']);
                    DB::delete(prefixTable('log_items'), 'id_item = %i', (int) $item['id']);
                }
            }

            // rebuild tree
            $tree = new \TeampassClasses\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $tree->rebuild();
        }

        // Delete objects keys (function exists in main.functions.php)
        deleteUserObjetsKeys($userId);

        // Delete any process related to user
        $processes = DB::query(
            'SELECT increment_id
            FROM ' . prefixTable('background_tasks') . '
            WHERE JSON_EXTRACT(arguments, "$.new_user_id") = %i',
            $userId
        );
        $processId = -1;
        foreach ($processes as $process) {
            DB::delete(prefixTable('background_subtasks'), 'task_id = %i', (int) $process['increment_id']);
            $processId = (int) $process['increment_id'];
        }
        if ($processId > -1) {
            DB::delete(prefixTable('background_tasks'), 'increment_id = %i', $processId);
        }

        // Delete Roles
        DB::delete(prefixTable('users_roles'), 'user_id = %i', $userId);

        // Delete Groups
        DB::delete(prefixTable('users_groups'), 'user_id = %i', $userId);
        DB::delete(prefixTable('users_groups_forbidden'), 'user_id = %i', $userId);

        // Delete Latest items
        DB::delete(prefixTable('users_latest_items'), 'user_id = %i', $userId);

        // Delete favorites
        DB::delete(prefixTable('users_favorites'), 'user_id = %i', $userId);

        // Finally delete user row
        DB::delete(prefixTable('users'), 'id = %i', $userId);

        DB::commit();

        return [
            'error' => false,
            'message_key' => 'user_purged_successfully',
        ];
    } catch (Exception $e) {
        DB::rollback();
        return [
            'error' => true,
            'message_key' => 'an_error_occurred',
            'debug' => $e->getMessage(),
        ];
    }
}

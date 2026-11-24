<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 * ---
 * Scope of the recycle bin (Utilities → Recycled bin), DB-free and session-free.
 *
 * Administrators see the whole recycle bin. Managers and HR managers only see, restore and purge
 * what they can see in the Items page (GHSA-4jq3-w38q-6wrq):
 *   - a deleted folder they had access to. Deleting a folder keeps its role rights, so its id
 *     stays in the accessible folders of the users who could open it;
 *   - a deleted folder of their own personal tree;
 *   - a deleted item of an accessible folder, unless its user or role restriction excludes them.
 * The items of a deleted folder are restored with it, restrictions included. A deleted folder is
 * only permanently deleted when the caller sees every subfolder and every item it holds.
 *
 * Included by app/sources/utilities.queries.php and tests/Unit/RecycleBinLogicTest.php.
 */

require_once __DIR__ . '/item_restriction_logic.php';

/**
 * Keep the deleted folders the caller may see.
 *
 * @param array<int, array<string, mixed>> $deletedFolders     Recycled folders, keyed by id, each with a parent_id
 * @param bool                             $isAdmin            Administrators see every recycled folder
 * @param array<int|string, mixed>         $accessibleFolderIds The caller's accessible folders (session)
 * @param array<int|string, mixed>         $personalFolderIds   The caller's own personal folders (session)
 * @return array<int, array<string, mixed>> The visible subset, keys preserved
 */
function recycleBinVisibleDeletedFolders(
    array $deletedFolders,
    bool $isAdmin,
    array $accessibleFolderIds,
    array $personalFolderIds
): array {
    if ($isAdmin === true) {
        return $deletedFolders;
    }

    $accessible = array_flip(array_map('intval', $accessibleFolderIds));
    $personal = array_flip(array_map('intval', $personalFolderIds));

    $visible = [];
    foreach ($deletedFolders as $folderId => $folder) {
        $folderId = (int) $folderId;
        if ($folderId <= 0) {
            continue;
        }
        if (isset($accessible[$folderId]) === true) {
            $visible[$folderId] = $folder;
            continue;
        }

        // A personal subfolder leaves the session once deleted: find the existing folder its
        // deleted ancestors hang from, and keep it if that folder is one of the caller's own.
        $seen = [$folderId => true];
        $parentId = (int) ($folder['parent_id'] ?? 0);
        while ($parentId > 0 && isset($deletedFolders[$parentId]) === true && isset($seen[$parentId]) === false) {
            $seen[$parentId] = true;
            $parentId = (int) ($deletedFolders[$parentId]['parent_id'] ?? 0);
        }
        if ($parentId > 0 && isset($deletedFolders[$parentId]) === false && isset($personal[$parentId]) === true) {
            $visible[$folderId] = $folder;
        }
    }

    return $visible;
}

/**
 * Tell whether every folder of a recycled subtree is visible to the caller. A subtree is only
 * purged whole: a hidden subfolder would be destroyed unseen, or left behind without its parent.
 *
 * @param array<int|string, mixed>         $subtree               Folder ids of the subtree, root included
 * @param array<int, array<string, mixed>> $visibleDeletedFolders Output of recycleBinVisibleDeletedFolders()
 */
function recycleBinSubtreeIsVisible(array $subtree, array $visibleDeletedFolders): bool
{
    if (count($subtree) === 0) {
        return false;
    }
    foreach ($subtree as $folderId) {
        if (isset($visibleDeletedFolders[(int) $folderId]) === false) {
            return false;
        }
    }

    return true;
}

/**
 * Build the SQL predicate limiting recycled items to the ones the caller may see.
 *
 * @param string                   $itemAlias           SQL alias of the items table
 * @param bool                     $isAdmin             Administrators see every recycled item
 * @param array<int|string, mixed> $accessibleFolderIds The caller's accessible folders (session)
 * @param int                      $userId              The caller
 * @param int[]                    $userRoleIds         Role ids held by the caller
 * @param string                   $restrictionTable    Fully-qualified restriction_to_roles table
 * @return string Parenthesized predicate without a leading AND; '(1 = 0)' fails closed
 */
function recycleBinItemScopeSql(
    string $itemAlias,
    bool $isAdmin,
    array $accessibleFolderIds,
    int $userId,
    array $userRoleIds,
    string $restrictionTable
): string {
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $itemAlias) !== 1) {
        return '(1 = 0)';
    }
    if ($isAdmin === true) {
        return '(1 = 1)';
    }

    $folderIds = [];
    foreach ($accessibleFolderIds as $folderId) {
        $folderId = (int) $folderId;
        if ($folderId > 0) {
            $folderIds[$folderId] = $folderId;
        }
    }
    if (count($folderIds) === 0) {
        return '(1 = 0)';
    }

    return '(' . $itemAlias . '.id_tree IN (' . implode(',', $folderIds) . ')'
        . ' AND ' . itemRestrictionSqlPredicate($userId, $userRoleIds, $itemAlias, $restrictionTable)
        . ')';
}

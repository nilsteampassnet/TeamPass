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
 * @file      leaver.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Leaver / Offboarding Risk view (F3 — Enterprise governance).
 *
 * When a user is disabled (or about to leave), the credentials they could
 * read are still known to them. This module answers "which SHARED
 * credentials did this account have access to?" so the admin can flag
 * them for rotation.
 *
 * The first section is pure, DB-free logic (unit-tested by
 * tests/Unit/LeaverRiskLogicTest.php). The second section holds the
 * DB-backed helpers used by users.queries.php.
 */

use TeampassClasses\NestedTree\NestedTree;

// -------------------------------------------------------------------------
// Pure logic — DB-free, unit-tested
// -------------------------------------------------------------------------

/**
 * Resolve the display status of a rotation flag for one item.
 *
 * A flag is considered resolved as soon as the item password was changed
 * AFTER the flag was raised — no write-back is needed, the state is derived
 * at read time from the item history.
 *
 * @param int|null    $flaggedAt    Timestamp the item was flagged (null/0 = never flagged)
 * @param int         $lastPwChange Timestamp of the last password change (0 = unknown)
 * @param string|null $status       Raw flag status stored in DB ('pending'|'dismissed')
 *
 * @return string One of 'not_flagged' | 'pending' | 'resolved' | 'dismissed'
 */
function leaverRiskItemDisplayStatus(?int $flaggedAt, int $lastPwChange, ?string $status): string
{
    if ($flaggedAt === null || $flaggedAt <= 0) {
        return 'not_flagged';
    }
    if ($status === 'dismissed') {
        return 'dismissed';
    }
    if ($lastPwChange > $flaggedAt) {
        return 'resolved';
    }

    return 'pending';
}

/**
 * Keep only the requested item ids that belong to the eligible set.
 *
 * The eligible set is recomputed server-side (the shared items the leaver
 * could actually read) so a tampered client cannot flag arbitrary items.
 *
 * @param array $requestedIds Item ids sent by the client
 * @param array $eligibleIds  Item ids the leaver really had access to
 *
 * @return int[] Sanitized intersection, re-indexed
 */
function leaverRiskValidateItemIds(array $requestedIds, array $eligibleIds): array
{
    $requested = array_map('intval', $requestedIds);
    $eligible = array_map('intval', $eligibleIds);

    return array_values(array_unique(array_intersect($requested, $eligible)));
}

/**
 * Build the rotation-flag rows to upsert for a set of items.
 *
 * Re-flagging an already flagged item refreshes flagged_at and resets the
 * status to 'pending' (a dismissed or resolved flag becomes active again).
 *
 * @param int[] $itemIds   Validated item ids
 * @param int   $leaverId  The disabled/leaving user id
 * @param int   $flaggedBy The admin/manager performing the action
 * @param int   $now       Current timestamp
 *
 * @return array<int, array<string, int|string>> Rows keyed for the rotation_flags table
 */
function leaverRiskBuildFlagRows(array $itemIds, int $leaverId, int $flaggedBy, int $now): array
{
    $rows = [];
    foreach ($itemIds as $itemId) {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            continue;
        }
        $rows[] = [
            'item_id' => $itemId,
            'flagged_at' => $now,
            'flagged_by' => $flaggedBy,
            'leaver_id' => $leaverId,
            'reason' => 'leaver',
            'status' => 'pending',
        ];
    }

    return $rows;
}

/**
 * Sanitize a list of folder ids coming from the client.
 *
 * @param array $ids Raw ids (strings/ints, possibly duplicated or invalid)
 *
 * @return int[] Positive unique ids, re-indexed, insertion order preserved
 */
function leaverRiskNormalizeFolderIds(array $ids): array
{
    $out = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $out[$id] = $id;
        }
    }

    return array_values($out);
}

/**
 * Merge selected folder ids with their descendant id lists.
 *
 * Pure counterpart of the "include subfolders" option: the caller resolves
 * each selected folder's descendants (NestedTree) and passes them here.
 *
 * @param array $selectedIds     Folder ids picked by the admin
 * @param array $descendantLists One list of descendant ids per selected folder
 *
 * @return int[] Unique folder scope (selected + descendants)
 */
function leaverRiskExpandFolderScope(array $selectedIds, array $descendantLists): array
{
    $scope = leaverRiskNormalizeFolderIds($selectedIds);
    foreach ($descendantLists as $list) {
        foreach (leaverRiskNormalizeFolderIds((array) $list) as $id) {
            if (in_array($id, $scope, true) === false) {
                $scope[] = $id;
            }
        }
    }

    return $scope;
}

/**
 * Decorate raw report rows with their display status and shared filter.
 *
 * Input rows carry: other_users (int), last_pw_change (int),
 * flagged_at (int|null), flag_status (string|null). Rows with no other
 * reader are dropped (the credential is not "shared" knowledge).
 *
 * @param array<int, array<string, mixed>> $rows Raw SQL rows
 *
 * @return array<int, array<string, mixed>> Shared rows with a 'display_status' key
 */
function leaverRiskDecorateReportRows(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if ((int) ($row['other_users'] ?? 0) <= 0) {
            continue;
        }
        $row['display_status'] = leaverRiskItemDisplayStatus(
            isset($row['flagged_at']) === true ? (int) $row['flagged_at'] : null,
            (int) ($row['last_pw_change'] ?? 0),
            isset($row['flag_status']) === true ? (string) $row['flag_status'] : null
        );
        $out[] = $row;
    }

    return $out;
}

// -------------------------------------------------------------------------
// DB-backed helpers — web context only
// -------------------------------------------------------------------------

/**
 * List the SHARED items a (leaving) user could read.
 *
 * "Shared" = the item is not personal, sits in a non-personal folder, and at
 * least one OTHER active human user also holds a sharekey on it. Special
 * server accounts (TP/OTV/SSH/API) never count as readers.
 *
 * @param int   $leaverId  The user whose access graph is inspected
 * @param int[] $folderIds Optional folder scope — empty array = all folders
 *
 * @return array<int, array<string, mixed>> Report rows (already decorated + filtered)
 */
function leaverRiskSharedItems(int $leaverId, array $folderIds = []): array
{
    $specialIds = [(int) TP_USER_ID, (int) OTV_USER_ID, (int) SSH_USER_ID, (int) API_USER_ID];

    // Optional folder scope — appended as the last positional placeholder
    $folderFilterSql = '';
    $args = [$leaverId, $specialIds, 'at_creation', 'at_modification', 'at_pw%', $leaverId, 0, 0, 0];
    if (count($folderIds) > 0) {
        $folderFilterSql = ' AND i.id_tree IN %li';
        $args[] = array_map('intval', $folderIds);
    }

    $rows = DB::query(
        'SELECT i.id AS item_id, i.label, i.id_tree, n.title AS folder_title,
            (SELECT COUNT(DISTINCT sk2.user_id)
                FROM ' . prefixTable('sharekeys_items') . ' AS sk2
                INNER JOIN ' . prefixTable('users') . ' AS u2 ON u2.id = sk2.user_id
                WHERE sk2.object_id = i.id
                AND sk2.user_id != %i
                AND sk2.user_id NOT IN %li
                AND u2.deleted_at IS NULL) AS other_users,
            COALESCE(l.last_relevant_date, NULLIF(CAST(i.created_at AS UNSIGNED), 0), 0) AS last_pw_change,
            rf.flagged_at, rf.status AS flag_status
        FROM ' . prefixTable('sharekeys_items') . ' AS sk
        INNER JOIN ' . prefixTable('items') . ' AS i ON i.id = sk.object_id
        INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON n.id = i.id_tree
        LEFT JOIN (
            SELECT id_item, MAX(CAST(date AS UNSIGNED)) AS last_relevant_date
            FROM ' . prefixTable('log_items') . '
            WHERE action = %s OR (action = %s AND raison LIKE %s)
            GROUP BY id_item
        ) AS l ON l.id_item = i.id
        LEFT JOIN ' . prefixTable('rotation_flags') . ' AS rf ON rf.item_id = i.id
        WHERE sk.user_id = %i
        AND i.perso = %i
        AND i.inactif = %i
        AND i.deleted_at IS NULL
        AND n.personal_folder = %i' . $folderFilterSql . '
        ORDER BY n.title ASC, i.label ASC',
        ...$args
    );

    return leaverRiskDecorateReportRows($rows);
}

/**
 * Resolve the folder scope requested by the client.
 *
 * Sanitizes the requested folder ids and, when asked, expands each one
 * with its whole subtree (NestedTree descendants).
 *
 * @param array $requestedFolderIds Folder ids sent by the client
 * @param bool  $includeChildren    Expand each selected folder with its descendants
 *
 * @return int[] Folder ids to filter on — empty array = no filter (all folders)
 */
function leaverRiskResolveFolderScope(array $requestedFolderIds, bool $includeChildren): array
{
    $selected = leaverRiskNormalizeFolderIds($requestedFolderIds);
    if (count($selected) === 0 || $includeChildren === false) {
        return $selected;
    }

    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    $descendantLists = [];
    foreach ($selected as $folderId) {
        $ids = [];
        foreach ($tree->getDescendants($folderId) as $node) {
            $ids[] = (int) $node->id;
        }
        $descendantLists[] = $ids;
    }

    return leaverRiskExpandFolderScope($selected, $descendantLists);
}

/**
 * Upsert rotation flags for a validated set of items.
 *
 * @param int[] $itemIds   Validated item ids (see leaverRiskValidateItemIds)
 * @param int   $leaverId  The disabled/leaving user id
 * @param int   $flaggedBy The admin/manager performing the action
 *
 * @return int Number of items flagged
 */
function leaverRiskFlagItems(array $itemIds, int $leaverId, int $flaggedBy): int
{
    $rows = leaverRiskBuildFlagRows($itemIds, $leaverId, $flaggedBy, time());
    foreach ($rows as $row) {
        DB::insertUpdate(prefixTable('rotation_flags'), $row);
    }

    return count($rows);
}

<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net.
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

/**
 * Folder-cache decisions shared by the web/API adapters and their unit tests.
 * This module deliberately has no database, session or clock dependency.
 */

/**
 * Normalize user/folder identifiers without accepting the synthetic root (0).
 *
 * @param array $ids Identifiers from database or session arrays
 * @return array<int> Unique positive identifiers
 */
function folderCacheNormalizeIds(array $ids): array
{
    return array_values(array_unique(array_filter(
        array_map('intval', $ids),
        static fn (int $id): bool => $id > 0
    )));
}

/**
 * Initialize an empty cache before reading the data that will populate it.
 *
 * @param int $userId Cache owner
 * @return array Empty cache row; no representation is marked as built
 */
function folderCacheEmptyRow(int $userId): array
{
    return [
        'user_id' => $userId,
        'data' => '[]',
        'visible_folders' => '[]',
        'folders' => '[]',
        'timestamp' => 0,
        'invalidated_at' => 0,
    ];
}

/**
 * Check the presentation setting used to build a cached jsTree snapshot.
 *
 * Legacy snapshots have no marker and must be rebuilt once. Keeping the marker
 * in jsTree's standard node data avoids a schema change and also detects an old
 * build that finishes after the administrator changes the setting.
 *
 * @param string $data Cached jsTree JSON
 * @param bool $treeCounters Whether folder counters are currently enabled
 * @return bool Whether the snapshot was built with the current setting
 */
function folderCacheTreeMatchesCounters(string $data, bool $treeCounters): bool
{
    $tree = json_decode($data, true);
    return is_array($tree)
        && ($tree[0]['data']['tree_counters'] ?? null) === $treeCounters;
}

/**
 * Prepare a complete or partial cache write without erasing invalidation history.
 *
 * @param string $data JSON payload
 * @param string $field Empty for a tree build, or the dropdown/API field
 * @param string $visibleFolders Dropdown JSON produced with the tree
 * @param int $startedAt Time captured before reading permissions and folder rows
 * @return array Fields to write after the database freshness guard succeeds
 */
function folderCacheWriteFields(string $data, string $field, string $visibleFolders, int $startedAt): array
{
    if (!in_array($field, ['', 'visible_folders', 'folders'], true)) {
        throw new InvalidArgumentException('Unsupported folder cache field');
    }
    $fields = ['timestamp' => $startedAt, $field === '' ? 'data' : $field => $data];
    if ($field === '' && $visibleFolders !== '') {
        $fields['visible_folders'] = $visibleFolders;
    }
    return $fields;
}

/**
 * Resolve the usable folder IDs from freshly identified session rights.
 *
 * @param array $accessible Accessible folders
 * @param array $denied Explicitly denied folders
 * @param array $foreignPersonal Other users' personal folders
 * @return array<int> Authorized existing-folder candidates
 */
function folderCacheVisibleScope(array $accessible, array $denied, array $foreignPersonal): array
{
    return array_values(array_diff(
        folderCacheNormalizeIds($accessible),
        folderCacheNormalizeIds($denied),
        folderCacheNormalizeIds($foreignPersonal)
    ));
}

/**
 * Build fallback metadata from one batch of folder rows and the refreshed scope.
 *
 * @param array $rows Database rows with id, title and parent_id
 * @param array $visibleIds Effective folder scope
 * @param array $personalIds Current user's personal descendants
 * @param array $readOnlyIds Read-only folders
 * @return array Folder metadata for item authorization
 */
function folderCacheVisibleRows(array $rows, array $visibleIds, array $personalIds, array $readOnlyIds): array
{
    $visible = array_flip(folderCacheNormalizeIds($visibleIds));
    $personal = array_flip(folderCacheNormalizeIds($personalIds));
    $readOnly = array_flip(folderCacheNormalizeIds($readOnlyIds));
    $folders = [];
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        if (!isset($visible[$id])) {
            continue;
        }
        $folders[] = [
            'id' => $id,
            'level' => 0,
            'title' => $row['title'],
            'disabled' => isset($readOnly[$id]) ? 1 : 0,
            'parent_id' => (int) $row['parent_id'],
            'perso' => isset($personal[$id]) ? 1 : 0,
            'path' => '',
            'is_visible_active' => isset($readOnly[$id]) ? 1 : 0,
        ];
    }
    return $folders;
}

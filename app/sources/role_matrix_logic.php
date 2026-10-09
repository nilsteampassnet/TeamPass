<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

require_once __DIR__ . '/folder_list_logic.php';

/**
 * Build the authorized role matrix from bulk tree and permission snapshots.
 * Personal containment IDs include legacy descendants with an unset personal flag.
 * Stored labels remain encoded; the existing exchange decoder handles display text.
 *
 * @param array<int, object> $nodes NestedTree snapshot in tree order
 * @param array $accessibleIds Session-authorized folder IDs
 * @param array $excludedIds Personal and forbidden folder IDs
 * @param array $permissions Role permission records loaded in one query
 * @return array<int, array> Authorized folder metadata, without database reads
 */
function roleMatrixBuild(array $nodes, array $accessibleIds, array $excludedIds, array $permissions): array
{
    $accessible = array_fill_keys(array_map('intval', $accessibleIds), true);
    $excluded = array_fill_keys(array_map('intval', $excludedIds), true);
    $byId = [];
    $access = [];
    foreach ($nodes as $node) {
        $byId[(int) $node->id] = $node;
    }
    foreach ($permissions as $permission) {
        $access[(int) $permission['folder_id']] = (string) $permission['type'];
    }
    $matrix = [];
    foreach ($byId as $id => $node) {
        if (!isset($accessible[$id]) || isset($excluded[$id])) {
            continue;
        }
        $path = folderListAncestorPath($byId, $id);
        $parents = [];
        $titles = [];
        foreach ($path['ids'] as $index => $parentId) {
            if (isset($accessible[$parentId]) && !isset($excluded[$parentId])) {
                $parents[] = $parentId;
                $titles[] = $path['titles'][$index];
            }
        }
        $matrix[] = [
            'id' => $id,
            'title' => (string) $node->title,
            'ident' => (int) $node->nlevel,
            'level' => (int) $node->nlevel,
            'parentId' => (int) $node->parent_id,
            'parents' => $parents,
            'path' => $titles,
            'access' => $access[$id] ?? 'none',
        ];
    }

    return $matrix;
}

/**
 * Resolve a rights submission against the current authorized matrix.
 * Reject an invalid explicit target; propagation only includes authorized descendants.
 *
 * @return array<int>|null Target IDs, or null when the explicit selection is invalid
 */
function roleMatrixTargets(array $matrix, array $selectedIds, bool $propagate): ?array
{
    $allowed = array_fill_keys(array_column($matrix, 'id'), true);
    $selected = [];
    foreach ($selectedIds as $id) {
        if (!is_scalar($id) || !ctype_digit((string) $id) || !isset($allowed[(int) $id])) {
            return null;
        }
        $selected[(int) $id] = true;
    }
    if ($selected === []) {
        return null;
    }
    $targets = [];
    foreach ($matrix as $row) {
        $included = isset($selected[$row['id']]);
        if (!$included && $propagate) {
            foreach ($row['parents'] as $parentId) {
                if (isset($selected[$parentId])) {
                    $included = true;
                    break;
                }
            }
        }
        if ($included) {
            $targets[] = (int) $row['id'];
        }
    }

    return $targets;
}

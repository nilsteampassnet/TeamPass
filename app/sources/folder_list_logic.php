<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

/**
 * Resolve a folder's ancestors from an already loaded NestedTree snapshot.
 * Missing parents and cycles stop traversal; no database reads are performed.
 *
 * @param array<int, object> $nodes Nodes indexed by their identifier
 * @return array{ids: array<int>, titles: array<string>}
 */
function folderListAncestorPath(array $nodes, int $folderId): array
{
    $ids = [];
    $titles = [];
    $visited = [$folderId => true];
    $parentId = (int) ($nodes[$folderId]->parent_id ?? 0);
    while ($parentId > 0 && isset($nodes[$parentId]) && !isset($visited[$parentId])) {
        $visited[$parentId] = true;
        $ids[] = $parentId;
        $titles[] = (string) $nodes[$parentId]->title;
        $parentId = (int) $nodes[$parentId]->parent_id;
    }

    return ['ids' => array_reverse($ids), 'titles' => array_reverse($titles)];
}

/**
 * Build one bounded parent-picker page from an authorized shared-folder snapshot.
 * Personal containment IDs include legacy descendants whose own personal flag is unset.
 *
 * @param array<int, object> $nodes Nodes indexed by their identifier
 * @param array $accessibleIds Session-authorized folders
 * @param array $excludedIds Personal, forbidden and read-only folders
 * @return array{results: array, pagination: array{more: bool}}
 */
function folderListParentPage(
    array $nodes,
    array $accessibleIds,
    array $excludedIds,
    bool $allowRoot,
    string $rootLabel,
    string $term,
    int $page,
    int $movingId = 0
): array {
    $accessible = array_fill_keys(array_map('intval', $accessibleIds), true);
    $excluded = array_fill_keys(array_map('intval', $excludedIds), true);
    $offset = (max(1, min($page, 100000)) - 1) * 30;
    $results = [];
    $matched = 0;
    $term = mb_substr(trim($term), 0, 100);
    if ($allowRoot && ($term === '' || mb_stripos($rootLabel, $term) !== false)) {
        if ($offset === 0) {
            $results[] = ['id' => '0', 'text' => $rootLabel, 'complexity' => 0, 'add_is_blocked' => 0, 'edit_is_blocked' => 0];
        }
        $matched++;
    }
    foreach ($nodes as $id => $node) {
        $id = (int) $id;
        if (!isset($accessible[$id]) || isset($excluded[$id]) || $id === $movingId) {
            continue;
        }
        $path = folderListAncestorPath($nodes, $id);
        if (in_array($movingId, $path['ids'], true)) {
            continue;
        }
        // Never include an inaccessible ancestor's name in a lookup result.
        $titles = [];
        foreach ($path['ids'] as $index => $parentId) {
            if (isset($accessible[$parentId]) && !isset($excluded[$parentId])) {
                $titles[] = $path['titles'][$index];
            }
        }
        $text = implode(' / ', array_merge($titles, [(string) $node->title]));
        if ($term !== '' && mb_stripos($text, $term) === false) {
            continue;
        }
        if ($matched++ < $offset) {
            continue;
        }
        $results[] = [
            'id' => (string) $id,
            'text' => $text,
            'add_is_blocked' => (int) $node->bloquer_creation,
            'edit_is_blocked' => (int) $node->bloquer_modification,
        ];
        if (count($results) > 30) {
            break;
        }
    }

    return ['results' => array_slice($results, 0, 30), 'pagination' => ['more' => count($results) > 30]];
}

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

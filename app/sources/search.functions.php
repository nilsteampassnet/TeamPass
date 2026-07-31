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
 * @file      search.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Faceted search — pure, DB-free logic.
 *
 * Unit-tested by tests/Unit/SearchFolderScopeTest.php and
 * tests/Unit/SearchFiltersLogicTest.php. Every function here is
 * deterministic and takes its inputs explicitly, so the authorization
 * rules can be tested without a session or a database.
 *
 * The ACL-bound queries live in search.queries.php.
 */

/**
 * Resolve the folder scope a search may read from.
 *
 * This is the single authorization primitive of the search feature. It is
 * shared by find.queries.php (legacy quick search) and search.queries.php
 * so the two can never diverge.
 *
 * Two rules, both mandatory:
 *  - a caller-supplied subtree only ever *narrows* the scope. It is
 *    intersected with the accessible folders, never substituted for them:
 *    NestedTree::getDescendants() walks the raw tree and applies no ACL, so
 *    trusting it alone would expose any folder id a client cares to send.
 *  - other users' personal folders are subtracted, mirroring
 *    expired.datatables.php.
 *
 * @param array<int|string> $accessibleFolders         Session `user-accessible_folders`.
 * @param array<int|string> $forbiddenPersonalFolders  Session `user-forbiden_personal_folders`.
 * @param array<int|string>|null $requestedSubtree     Folder ids of the requested subtree,
 *                                                     or null when no narrowing is asked for.
 *
 * @return array<int, int> Positive folder ids, unique and reindexed. Empty means "search nothing".
 */
function searchResolveFolderScope(
    array $accessibleFolders,
    array $forbiddenPersonalFolders = [],
    ?array $requestedSubtree = null
): array {
    $toIdSet = static function (array $ids): array {
        $clean = [];
        foreach ($ids as $id) {
            if (is_int($id) === false && is_string($id) === false) {
                continue;
            }
            $value = (int) $id;
            if ($value > 0) {
                $clean[$value] = $value;
            }
        }

        return $clean;
    };

    $scope = $toIdSet($accessibleFolders);
    if (count($scope) === 0) {
        return [];
    }

    // A denial always beats a grant.
    foreach ($toIdSet($forbiddenPersonalFolders) as $forbidden) {
        unset($scope[$forbidden]);
    }

    // Narrowing only: intersect, never replace.
    if ($requestedSubtree !== null) {
        $scope = array_intersect_key($scope, $toIdSet($requestedSubtree));
    }

    return array_values($scope);
}

/**
 * Build the ORDER BY clause from a server-side column map.
 *
 * No request value is ever concatenated verbatim: the column comes from
 * $columnMap and the direction is re-derived as a constant, which is the
 * form mandated after GHSA-fqg6-xvv8-w228 (see users.logs.datatable.php).
 *
 * @param array<int, string> $columnMap    Ordered SQL expressions, indexed as the client sees them.
 * @param mixed              $columnIndex  Client-supplied column index.
 * @param mixed              $direction    Client-supplied direction.
 * @param string             $default      Clause returned when the request is unusable.
 *
 * @return string An `ORDER BY ...` clause, or $default.
 */
function searchBuildOrderClause(
    array $columnMap,
    mixed $columnIndex,
    mixed $direction,
    string $default = ''
): string {
    if (is_int($columnIndex) === false && (is_string($columnIndex) === false || $columnIndex === '')) {
        return $default;
    }
    if (is_string($direction) === false) {
        return $default;
    }

    $index = (int) $columnIndex;
    if (isset($columnMap[$index]) === false) {
        return $default;
    }
    if (in_array(strtolower($direction), ['asc', 'desc'], true) === false) {
        return $default;
    }

    // Emit the re-derived constant, never the request value.
    return 'ORDER BY ' . $columnMap[$index] . ' ' . strtoupper(strtolower($direction));
}

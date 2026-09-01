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
 * shared by find.queries.php (legacy quick search), search.queries.php, and
 * palette.queries.php so the three entry points cannot diverge.
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

/**
 * Searchable text fields, mapped to their SQL column.
 *
 * Only plaintext metadata columns of the search cache. Secrets are absent by
 * construction: items.pw, items_otp.secret and encrypted custom-field values
 * can never be reached from here.
 *
 * @return array<string, string>
 */
function searchTextFieldMap(): array
{
    return [
        'label' => 'c.label',
        'login' => 'c.login',
        'url' => 'c.url',
        'tags' => 'c.tags',
        // MEDIUMTEXT: opt-in, it is the expensive one to scan.
        'description' => 'c.description',
    ];
}

/**
 * Sort columns exposed to the client, in the order the table renders them.
 *
 * @return array<int, string>
 */
function searchSortColumnMap(): array
{
    return [
        1 => 'c.label',
        2 => 'c.login',
        3 => 'c.description',
        4 => 'c.tags',
        5 => 'c.url',
        6 => 'c.folder',
    ];
}

/**
 * Health facets that can be resolved without decrypting anything.
 *
 * `reused` and `orphaned` are deliberately absent from this list: they live in
 * teampass_item_health and only exist after a deep scan. They are handled
 * separately so the UI can tell the user when the data is missing.
 *
 * @return array<int, string>
 */
function searchLiveHealthFlags(): array
{
    // Same vocabulary as the security dashboard (F1), so the labels and the
    // meaning stay identical across the two pages.
    return ['weak', 'breached', 'overdue', 'no_expiry', 'overshared'];
}

/**
 * Split a raw query string into normalised search terms.
 *
 * Multiple terms are ANDed by the caller, so "backup prod" no longer returns
 * everything containing "prod". Terms are bounded in count and length to keep
 * the LIKE scan affordable on an endpoint with no rate limiter.
 *
 * @param string $raw      Raw client input.
 * @param int    $maxTerms Hard cap on the number of terms.
 *
 * @return array<int, string> Normalised terms, possibly empty.
 */
function searchNormalizeTerms(string $raw, int $maxTerms = 5): array
{
    $terms = [];
    foreach (preg_split('/\s+/u', trim($raw)) ?: [] as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '' || mb_strlen($candidate) < 2) {
            continue;
        }
        $candidate = mb_substr($candidate, 0, 100);
        if (in_array($candidate, $terms, true) === false) {
            $terms[] = $candidate;
        }
        if (count($terms) >= $maxTerms) {
            break;
        }
    }

    return $terms;
}

/**
 * Coerce a client value into a list of positive integers.
 *
 * @param mixed $raw   Scalar, comma-separated string, or array.
 * @param int   $max   Hard cap on the number of kept values.
 *
 * @return array<int, int>
 */
function searchIntList(mixed $raw, int $max = 50): array
{
    if (is_string($raw) === true) {
        $raw = explode(',', $raw);
    }
    if (is_int($raw) === true) {
        $raw = [$raw];
    }
    if (is_array($raw) === false) {
        return [];
    }

    $out = [];
    foreach ($raw as $value) {
        if (is_int($value) === false && is_string($value) === false) {
            continue;
        }
        $int = (int) $value;
        if ($int > 0 && in_array($int, $out, true) === false) {
            $out[] = $int;
        }
        if (count($out) >= $max) {
            break;
        }
    }

    return $out;
}

/**
 * Keep only the values present in an allow-list.
 *
 * @param mixed             $raw     Scalar, comma-separated string, or array.
 * @param array<int,string> $allowed Allow-list.
 *
 * @return array<int, string>
 */
function searchWhitelist(mixed $raw, array $allowed): array
{
    if (is_string($raw) === true) {
        $raw = explode(',', $raw);
    }
    if (is_array($raw) === false) {
        return [];
    }

    $out = [];
    foreach ($raw as $value) {
        if (is_string($value) === false) {
            continue;
        }
        $value = trim($value);
        if (in_array($value, $allowed, true) === true && in_array($value, $out, true) === false) {
            $out[] = $value;
        }
    }

    return $out;
}

/**
 * Normalise the whole filter payload into a canonical, validated structure.
 *
 * Everything the client sends is either matched against an allow-list or
 * coerced to a bounded integer. Unknown keys are dropped, so a filter that
 * does not exist server-side can never reach the SQL builder.
 *
 * @param array<string, mixed> $raw Raw client payload.
 *
 * @return array<string, mixed> Canonical filter structure.
 */
function searchNormalizeFilters(array $raw): array
{
    $fields = searchWhitelist($raw['fields'] ?? null, array_keys(searchTextFieldMap()));
    if (count($fields) === 0) {
        // Sensible default: everything cheap, description excluded.
        $fields = ['label', 'login', 'url', 'tags'];
    }

    $toTimestamp = static function (mixed $value): ?int {
        if (is_int($value) === false && (is_string($value) === false || trim((string) $value) === '')) {
            return null;
        }
        $int = (int) $value;

        return $int > 0 ? $int : null;
    };

    $toBool = static fn (mixed $value): bool => $value === true || $value === 1 || $value === '1' || $value === 'true';

    $attachmentName = is_string($raw['attachment_name'] ?? null)
        ? mb_substr(trim((string) $raw['attachment_name']), 0, 100)
        : '';
    $customFieldValue = is_string($raw['custom_field_value'] ?? null)
        ? mb_substr(trim((string) $raw['custom_field_value']), 0, 100)
        : '';
    $customFieldId = searchIntList($raw['custom_field_id'] ?? null, 1);

    return [
        'terms' => searchNormalizeTerms(is_string($raw['term'] ?? null) ? (string) $raw['term'] : ''),
        'fields' => $fields,
        // Classification: 0 (unclassified) is a meaningful value, so this list
        // is validated against the scale rather than through searchIntList().
        'classification' => array_values(array_filter(
            array_map('intval', is_array($raw['classification'] ?? null) ? $raw['classification'] : []),
            static fn (int $level): bool => $level >= 0 && $level <= 4
        )),
        'health' => searchWhitelist($raw['health'] ?? null, searchLiveHealthFlags()),
        'health_scan' => searchWhitelist($raw['health_scan'] ?? null, ['reused', 'orphaned']),
        'attachment_has' => $toBool($raw['attachment_has'] ?? null),
        'attachment_extensions' => array_values(array_filter(
            array_map(
                static fn (mixed $e): string => is_string($e) ? mb_strtolower(mb_substr(trim($e), 0, 10)) : '',
                is_array($raw['attachment_extensions'] ?? null) ? $raw['attachment_extensions'] : []
            ),
            static fn (string $e): bool => $e !== '' && preg_match('/^[a-z0-9]+$/', $e) === 1
        )),
        'attachment_name' => $attachmentName,
        'created_from' => $toTimestamp($raw['created_from'] ?? null),
        'created_to' => $toTimestamp($raw['created_to'] ?? null),
        'updated_from' => $toTimestamp($raw['updated_from'] ?? null),
        'updated_to' => $toTimestamp($raw['updated_to'] ?? null),
        'rotation_status' => searchWhitelist($raw['rotation_status'] ?? null, ['pending', 'resolved']),
        'rotation_auto' => $toBool($raw['rotation_auto'] ?? null),
        'folder' => searchIntList($raw['folder'] ?? null, 1),
        'tags' => array_values(array_filter(
            array_map(
                static fn (mixed $t): string => is_string($t) ? mb_substr(trim($t), 0, 30) : '',
                is_array($raw['tags'] ?? null) ? $raw['tags'] : []
            ),
            static fn (string $t): bool => $t !== ''
        )),
        'favourites' => $toBool($raw['favourites'] ?? null),
        'recent' => $toBool($raw['recent'] ?? null),
        'author' => searchIntList($raw['author'] ?? null, 1),
        'scope_perso' => in_array($raw['scope_perso'] ?? '', ['personal', 'shared'], true)
            ? (string) $raw['scope_perso']
            : '',
        'custom_field_id' => $customFieldId,
        'custom_field_value' => $customFieldValue,
    ];
}

/**
 * Report whether any facet (i.e. anything other than free text) is active.
 *
 * Used to decide if an empty search term is acceptable: browsing by facet
 * alone is legitimate, an unfiltered full scan is not.
 *
 * @param array<string, mixed> $filters Canonical filters.
 */
function searchHasActiveFacet(array $filters): bool
{
    $listKeys = [
        'classification', 'health', 'health_scan', 'attachment_extensions',
        'rotation_status', 'folder', 'tags', 'author', 'custom_field_id',
    ];
    foreach ($listKeys as $key) {
        if (count((array) ($filters[$key] ?? [])) > 0) {
            return true;
        }
    }

    $scalarKeys = [
        'attachment_has', 'attachment_name', 'created_from', 'created_to',
        'updated_from', 'updated_to', 'rotation_auto', 'favourites', 'recent',
        'scope_perso',
    ];
    foreach ($scalarKeys as $key) {
        $value = $filters[$key] ?? null;
        if ($value !== null && $value !== '' && $value !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Build the item-level restriction predicate.
 *
 * Mirrors getItemRestrictedUsersList() (items.queries.php), the function that
 * decides whether a user may actually open an item, and its SQL twin
 * securityPostureItemAccessSql(): an item with no restriction at all is
 * visible; otherwise the user must be named directly OR hold one of the
 * allowed roles. Moving this into SQL removes two queries per rendered row
 * and, above all, lets the filtering happen before the LIMIT so the row
 * counts stop lying.
 *
 * Ids are inlined rather than bound: they are already validated integers, and
 * an inlined list cannot trip MeekroDB's "array can't be empty" exception.
 *
 * @param int              $userId    Requesting user.
 * @param array<int, int>  $roleIds   Role ids held by the user.
 * @param string           $itemAlias SQL alias of the items table.
 * @param string           $restrictionTable Fully-qualified restriction_to_roles table.
 *
 * @return string SQL predicate; '(1 = 0)' when the inputs are unusable (fail closed).
 */
function searchItemRestrictionSql(
    int $userId,
    array $roleIds,
    string $itemAlias,
    string $restrictionTable
): string {
    if ($userId <= 0 || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $itemAlias) !== 1) {
        return '(1 = 0)';
    }
    if (preg_match('/^[A-Za-z0-9_]+$/', $restrictionTable) !== 1) {
        return '(1 = 0)';
    }

    $cleanRoles = [];
    foreach ($roleIds as $roleId) {
        $roleId = (int) $roleId;
        if ($roleId > 0) {
            $cleanRoles[$roleId] = $roleId;
        }
    }

    $roleClause = '';
    if (count($cleanRoles) > 0) {
        $roleClause = ' OR EXISTS (SELECT 1 FROM ' . $restrictionTable . ' AS search_role_grant'
            . ' WHERE search_role_grant.item_id = ' . $itemAlias . '.id'
            . ' AND search_role_grant.role_id IN (' . implode(',', $cleanRoles) . '))';
    }

    return '('
        . '(COALESCE(' . $itemAlias . '.restricted_to, \'\') = \'\''
        . ' AND NOT EXISTS (SELECT 1 FROM ' . $restrictionTable . ' AS search_any_restriction'
        . ' WHERE search_any_restriction.item_id = ' . $itemAlias . '.id))'
        . ' OR CONCAT(\';\', COALESCE(' . $itemAlias . '.restricted_to, \'\'), \';\')'
        . ' LIKE \'%;' . $userId . ';%\''
        . $roleClause
        . ')';
}

/**
 * Build the WHERE body and its bound parameters for a faceted search.
 *
 * Returns MeekroDB named placeholders exclusively — named and numbered
 * placeholders cannot be mixed in one query. Array placeholders are only
 * emitted for non-empty arrays, because MeekroDB throws on an empty one.
 *
 * Expected aliases in the caller's query: `c` (cache), `i` (items) and, when
 * the classification facet is used, `dc` (LEFT JOIN on data_classification).
 *
 * @param array<string, mixed> $filters Canonical filters from searchNormalizeFilters().
 * @param array<string, mixed> $ctx     Resolved context: user_id, role_ids, folder_scope,
 *                                      tables map, weak_sql, overshared_threshold,
 *                                      visible_field_ids, now.
 *
 * @return array{sql: string, params: array<string, mixed>}
 */
function searchBuildWhere(array $filters, array $ctx): array
{
    $tables = (array) ($ctx['tables'] ?? []);
    $userId = (int) ($ctx['user_id'] ?? 0);
    $now = (int) ($ctx['now'] ?? time());
    $params = [];
    $clauses = [];

    // ---- Base scope: folders, liveness, item-level restrictions ----------
    $scope = array_values(array_unique(array_map('intval', (array) ($ctx['folder_scope'] ?? []))));
    if ($userId <= 0 || count($scope) === 0) {
        // Fail closed rather than returning an unbounded query.
        return ['sql' => '(1 = 0)', 'params' => []];
    }
    $clauses[] = 'c.id_tree IN %li_scope';
    $params['scope'] = $scope;

    // Defence in depth: the cache is pruned on delete, this catches drift.
    $clauses[] = 'i.deleted_at IS NULL';

    $clauses[] = searchItemRestrictionSql(
        $userId,
        (array) ($ctx['role_ids'] ?? []),
        'i',
        (string) ($tables['restriction_to_roles'] ?? '')
    );

    // ---- Free text -------------------------------------------------------
    $fieldMap = searchTextFieldMap();
    $terms = (array) ($filters['terms'] ?? []);
    $fields = (array) ($filters['fields'] ?? []);
    foreach (array_values($terms) as $index => $term) {
        $columns = [];
        foreach ($fields as $field) {
            if (isset($fieldMap[$field]) === true) {
                // The same named placeholder is reused across columns: MeekroDB
                // resolves named args by lookup, not by position.
                $columns[] = $fieldMap[$field] . ' LIKE %ss_term' . $index;
            }
        }
        if (count($columns) === 0) {
            continue;
        }
        $clauses[] = '(' . implode(' OR ', $columns) . ')';
        $params['term' . $index] = $term;
    }

    // ---- Classification --------------------------------------------------
    if (count((array) $filters['classification']) > 0) {
        // COALESCE: no row in data_classification means "unclassified" (0).
        $clauses[] = 'COALESCE(dc.level, 0) IN %li_classification';
        $params['classification'] = array_values((array) $filters['classification']);
    }

    // ---- Health, recomputed live (no decryption, no deep scan) -----------
    foreach ((array) $filters['health'] as $flag) {
        switch ($flag) {
            case 'weak':
                $weakSql = (string) ($ctx['weak_sql'] ?? '');
                if ($weakSql !== '') {
                    $clauses[] = $weakSql . ' = 1';
                }
                break;
            case 'breached':
                $clauses[] = 'i.hibp_status = 2';
                break;
            case 'overdue':
                // cache.timestamp already holds the last at_pw date, so the
                // expiry is computed without joining log_items.
                $clauses[] = '(c.renewal_period > 0 AND (CAST(c.timestamp AS UNSIGNED)'
                    . ' + c.renewal_period * ' . (int) ($ctx['day_seconds'] ?? 86400) . ') < ' . $now . ')';
                break;
            case 'no_expiry':
                $clauses[] = 'c.renewal_period <= 0';
                break;
            case 'overshared':
                $clauses[] = '(SELECT COUNT(*) FROM ' . (string) ($tables['sharekeys_items'] ?? '')
                    . ' AS search_sk WHERE search_sk.object_id = c.id) > '
                    . (int) ($ctx['overshared_threshold'] ?? 10);
                break;
        }
    }

    // ---- Health flags that require a prior deep scan ---------------------
    foreach ((array) $filters['health_scan'] as $flag) {
        $column = $flag === 'reused' ? 'flag_reused' : 'flag_orphaned';
        $clauses[] = 'EXISTS (SELECT 1 FROM ' . (string) ($tables['item_health'] ?? '') . ' AS search_health'
            . ' WHERE search_health.item_id = c.id AND search_health.user_id = ' . $userId
            . ' AND search_health.' . $column . ' = 1)';
    }

    // ---- Attachments -----------------------------------------------------
    $filesTable = (string) ($tables['files'] ?? '');
    $attachmentConditions = [];
    if ($filters['attachment_has'] === true) {
        $attachmentConditions[] = '1 = 1';
    }
    if (count((array) $filters['attachment_extensions']) > 0) {
        $attachmentConditions[] = 'LOWER(search_file.extension) IN %ls_attachmentext';
        $params['attachmentext'] = array_values((array) $filters['attachment_extensions']);
    }
    if ((string) $filters['attachment_name'] !== '') {
        // Attachment names are stored base64-encoded; only the file CONTENT is
        // encrypted, never this column. Two shapes coexist in the wild:
        // "b64:<base64>.<ext>" (current) and "<base64>.<ext>" (legacy, which is
        // why every reader probes with isBase64()). Both must be decoded, so the
        // prefix is stripped conditionally rather than assumed.
        // CONVERT(... USING utf8mb4) matters: FROM_BASE64 returns a BLOB, which
        // would otherwise compare case-sensitively unlike the rest of the schema.
        // FROM_BASE64 yields NULL on a genuinely plaintext name, hence the
        // trailing OR on the raw column.
        $attachmentConditions[] = '(CONVERT(FROM_BASE64(SUBSTRING_INDEX('
            . 'CASE WHEN search_file.name LIKE \'b64:%\''
            . ' THEN SUBSTRING(search_file.name, 5) ELSE search_file.name END'
            . ', \'.\', 1)) USING utf8mb4) LIKE %ss_attachmentname'
            . ' OR search_file.name LIKE %ss_attachmentname)';
        $params['attachmentname'] = (string) $filters['attachment_name'];
    }
    if (count($attachmentConditions) > 0) {
        $clauses[] = 'EXISTS (SELECT 1 FROM ' . $filesTable . ' AS search_file'
            . ' WHERE search_file.id_item = c.id AND search_file.confirmed = 1'
            . ' AND ' . implode(' AND ', $attachmentConditions) . ')';
    }

    // ---- Dates -----------------------------------------------------------
    $dateColumns = [
        'created_from' => ['i.created_at', '>='],
        'created_to' => ['i.created_at', '<='],
        'updated_from' => ['i.updated_at', '>='],
        'updated_to' => ['i.updated_at', '<='],
    ];
    foreach ($dateColumns as $key => [$column, $operator]) {
        if ($filters[$key] !== null) {
            // Stored as a varchar holding a unix timestamp.
            $clauses[] = 'CAST(' . $column . ' AS UNSIGNED) ' . $operator . ' %i_' . $key;
            $params[$key] = (int) $filters[$key];
        }
    }

    // ---- Rotation --------------------------------------------------------
    if (count((array) $filters['rotation_status']) > 0) {
        $clauses[] = 'EXISTS (SELECT 1 FROM ' . (string) ($tables['rotation_flags'] ?? '') . ' AS search_rotation'
            . ' WHERE search_rotation.item_id = c.id AND search_rotation.status IN %ls_rotationstatus)';
        $params['rotationstatus'] = array_values((array) $filters['rotation_status']);
    }
    if ($filters['rotation_auto'] === true) {
        $clauses[] = 'i.auto_update_pwd_frequency > 0';
    }

    // ---- Scope & content -------------------------------------------------
    if (count((array) $filters['tags']) > 0) {
        $clauses[] = 'EXISTS (SELECT 1 FROM ' . (string) ($tables['tags'] ?? '') . ' AS search_tag'
            . ' WHERE search_tag.item_id = c.id AND search_tag.tag IN %ls_tags)';
        $params['tags'] = array_values((array) $filters['tags']);
    }
    if ($filters['favourites'] === true) {
        $clauses[] = 'EXISTS (SELECT 1 FROM ' . (string) ($tables['users_favorites'] ?? '') . ' AS search_fav'
            . ' WHERE search_fav.item_id = c.id AND search_fav.user_id = ' . $userId . ')';
    }
    if ($filters['recent'] === true) {
        $clauses[] = 'EXISTS (SELECT 1 FROM ' . (string) ($tables['users_latest_items'] ?? '') . ' AS search_recent'
            . ' WHERE search_recent.item_id = c.id AND search_recent.user_id = ' . $userId . ')';
    }
    if (count((array) $filters['author']) > 0) {
        $clauses[] = 'c.author = %i_author';
        $params['author'] = (int) ((array) $filters['author'])[0];
    }
    if ($filters['scope_perso'] === 'personal') {
        $clauses[] = 'c.perso = 1';
    } elseif ($filters['scope_perso'] === 'shared') {
        $clauses[] = 'c.perso = 0';
    }

    // ---- Custom fields ---------------------------------------------------
    // Only unencrypted fields are reachable, and only those the user's roles
    // are allowed to see: a match is itself an oracle on the value, so the
    // role_visibility rule of the item card applies here too (issue #5176).
    $visibleFieldIds = array_values(array_unique(array_map('intval', (array) ($ctx['visible_field_ids'] ?? []))));
    if ((string) $filters['custom_field_value'] !== '' && count($visibleFieldIds) > 0) {
        $requestedField = (array) $filters['custom_field_id'];
        $targetFields = count($requestedField) > 0
            ? array_values(array_intersect($visibleFieldIds, array_map('intval', $requestedField)))
            : $visibleFieldIds;

        if (count($targetFields) === 0) {
            // Asked for a field they may not see: return nothing, do not widen.
            $clauses[] = '(1 = 0)';
        } else {
            $clauses[] = 'EXISTS (SELECT 1 FROM ' . (string) ($tables['categories_items'] ?? '') . ' AS search_cf'
                . ' WHERE search_cf.item_id = c.id'
                . ' AND search_cf.encryption_type = \'not_set\''
                . ' AND search_cf.field_id IN (' . implode(',', $targetFields) . ')'
                . ' AND search_cf.data LIKE %ss_customfieldvalue)';
            $params['customfieldvalue'] = (string) $filters['custom_field_value'];
        }
    }

    return ['sql' => implode(' AND ', $clauses), 'params' => $params];
}

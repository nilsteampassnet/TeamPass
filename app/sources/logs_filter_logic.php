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
 * Database-free filtering rules for the monitoring logs.
 *
 * @file      logs_filter_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\Language\Language;

/**
 * Resolve an item-log search column against the columns offered by the page.
 * The User column displays the login and full name, so all three fields are searched.
 *
 * @return string[]
 */
function getItemLogSearchColumns(mixed $column): array
{
    $columns = ['i.id', 'i.label', 't.title', 'u.login', 'l.action'];
    if ($column === 'u.login') {
        return ['u.login', 'u.name', 'u.lastname'];
    }
    if (is_string($column) && in_array($column, $columns, true)) {
        return [$column];
    }

    return array_merge($columns, ['u.name', 'u.lastname']);
}

/**
 * Find known item actions by their translated label or stored code.
 *
 * @return string[]
 */
function getItemLogActionSearchCodes(string $searchValue, Language $lang): array
{
    $actions = [
        'at_creation', 'at_modification', 'at_shown', 'at_export', 'at_restored', 'at_delete', 'at_copy',
        'at_moved', 'at_manual', 'at_import', 'at_access',
        'at_password_copied', 'at_password_shown', 'at_password_shown_edit_form',
    ];
    $matches = [];
    foreach ($actions as $action) {
        if (mb_stripos($action, $searchValue, 0, 'UTF-8') !== false
            || mb_stripos(html_entity_decode((string) $lang->get($action), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $searchValue, 0, 'UTF-8') !== false
        ) {
            $matches[] = $action;
        }
    }

    return $matches;
}

/**
 * Build an item-log search predicate without opening a database connection.
 * An action with no matching label/code must produce no matches, not an empty filter.
 */
function buildItemLogSearchFilter(mixed $column, string $searchValue, Language $lang): WhereClause
{
    $where = new WhereClause('AND');
    if ($searchValue !== '') {
        $search = $where->addClause('OR');
        foreach (getItemLogSearchColumns($column) as $searchColumn) {
            if ($searchColumn === 'l.action') {
                $actions = getItemLogActionSearchCodes($searchValue, $lang);
                if ($actions === []) {
                    $search->add('1 = 0');
                } else {
                    $search->add('l.action IN %ls', $actions);
                }
            } else {
                $search->add($searchColumn . ' LIKE %ss', $searchValue);
            }
        }
    }

    return $where;
}

/**
 * Parse one calendar day boundary in the server's timezone.
 *
 * A boundary is accepted only in strict Y-m-d form: the round-trip comparison rejects a lenient
 * parse ('2026-9-8'), an overflowing date ('2026-02-30') and any trailing garbage.
 *
 * @param bool $isEnd When true the timestamp returned is the exclusive start of the next day, so
 *                    the whole selected day is included whatever its length (DST transitions).
 */
function logsParseDateBoundary(mixed $value, bool $isEnd): ?int
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    try {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    } catch (ValueError $error) {
        return null;
    }
    if ($date === false || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $isEnd === true ? $date->modify('+1 day')->getTimestamp() : $date->getTimestamp();
}

/**
 * Parse a calendar date range, including the whole last day in the server's timezone.
 *
 * @return array{0: int, 1: int}|null Start inclusive, end exclusive
 */
function getLogsPurgeDateRange(mixed $start, mixed $end): ?array
{
    $startTimestamp = logsParseDateBoundary($start, false);
    $endTimestamp = logsParseDateBoundary($end, true);
    if ($startTimestamp === null || $endTimestamp === null || $startTimestamp >= $endTimestamp) {
        return null;
    }

    return [$startTimestamp, $endTimestamp];
}

/**
 * Log sources the monitoring page may request.
 *
 * One source at a time: the displayed column set depends on it, so a multi-source selection would
 * have no unambiguous table to render.
 *
 * @return array<int, string>
 */
function logsAllowedSources(): array
{
    return ['system', 'items', 'kb'];
}

/**
 * Selectable types under the 'system' source, as the interface names them.
 *
 * These are interface keys, not database values: 'admin' covers two log_system types.
 *
 * @return array<int, string>
 */
function logsAllowedSystemTypes(): array
{
    return ['connections', 'failed', 'errors', 'admin'];
}

/**
 * Expand interface type keys into the log_system.type values they cover.
 *
 * @param array<int, string> $typeKeys Interface keys, already validated.
 *
 * @return array<int, string>
 */
function logsSystemTypeDbValues(array $typeKeys): array
{
    $map = [
        'connections' => ['user_connection'],
        'failed' => ['failed_auth'],
        'errors' => ['error'],
        'admin' => ['admin_action', 'user_mngt'],
    ];

    $values = [];
    foreach ($typeKeys as $key) {
        foreach ($map[$key] ?? [] as $value) {
            if (in_array($value, $values, true) === false) {
                $values[] = $value;
            }
        }
    }

    return $values;
}

/**
 * Item log actions offered by the interface.
 *
 * @return array<int, string>
 */
function logsAllowedItemActions(): array
{
    return [
        'at_creation', 'at_modification', 'at_shown', 'at_export', 'at_restored', 'at_delete',
        'at_copy', 'at_moved', 'at_manual', 'at_import', 'at_access',
        'at_password_copied', 'at_password_shown', 'at_password_shown_edit_form',
    ];
}

/**
 * Knowledge base log actions offered by the interface.
 *
 * @return array<int, string>
 */
function logsAllowedKbActions(): array
{
    return ['at_creation', 'at_modification', 'at_delete', 'at_restored', 'at_shown'];
}

/**
 * Labels only the API writes on a failed authentication.
 *
 * The 'tp_src=api' marker alone cannot be trusted there: on the web path field_1 holds the
 * submitted login, so anyone could forge an API row by typing that string in the login form.
 *
 * @return array<int, string>
 */
function logsApiFailureLabels(): array
{
    return ['api_invalid_credentials', 'api_invalid_apikey', 'api_invalid_token', 'api_token_decrypt_failed'];
}

/**
 * SQL predicate matching an API-originated log_system row, with its bound values.
 *
 * Web is built as the exact negation of this expression, so the two channels can never overlap
 * nor leave a row unclassified. Every operand is wrapped in COALESCE: a NULL column would make
 * the comparison NULL, and the negation would then drop a row that does belong to Web.
 *
 * @param string $prefix Table alias prefix used by the query ('l.' on the read path, '' on the
 *                       purge path, which deletes from the log table alone).
 *
 * @return array{0: string, 1: array<int, mixed>}
 */
function logsSystemApiPredicate(string $prefix = 'l.'): array
{
    $field = "COALESCE(" . $prefix . "field_1, '')";
    $label = "COALESCE(" . $prefix . "label, '')";
    $type = $prefix . 'type';

    return [
        '((' . $type . ' = %s AND (' . $label . ' IN %ls OR (' . $label . ' = %s AND ' . $field . ' LIKE %ss)))'
        . ' OR (' . $type . ' <> %s AND (' . $field . ' = %s OR ' . $field . ' LIKE %ss)))',
        [
            'failed_auth', logsApiFailureLabels(), 'bruteforce_account_locked', 'tp_src=api',
            'failed_auth', 'api', 'tp_src=api',
        ],
    ];
}

/**
 * Map a stored log_system type back to the interface key that selects it.
 *
 * @return string Empty when the row belongs to no selectable type.
 */
function logsSystemTypeKey(string $dbType): string
{
    foreach (logsAllowedSystemTypes() as $key) {
        if (in_array($dbType, logsSystemTypeDbValues([$key]), true) === true) {
            return $key;
        }
    }

    return '';
}

/**
 * Decide whether a log_system row was written by the API.
 *
 * PHP twin of logsSystemApiPredicate(): the two must answer identically, otherwise the Channel
 * facet would hide rows the Source column labels as API. They share logsApiFailureLabels(), which
 * is what keeps them from drifting apart.
 */
function logsSystemRowIsApi(string $type, string $label, string $field1): bool
{
    if ($type === 'failed_auth') {
        return in_array($label, logsApiFailureLabels(), true)
            || ($label === 'bruteforce_account_locked' && strpos($field1, 'tp_src=api') !== false);
    }

    return $field1 === 'api' || strpos($field1, 'tp_src=api') !== false;
}

/**
 * Remove the internal API marker from a failed authentication's submitted login.
 *
 * Only applied to a confirmed API row: on the web path field_1 is the submitted login, so a forged
 * one containing the marker must stay visible exactly as it was typed.
 */
function logsStripApiMarker(string $field1, bool $isApi): string
{
    if ($isApi === false) {
        return $field1;
    }

    return trim(str_replace([' | tp_src=api', 'tp_src=api'], '', $field1), " |\t\n\r\0\x0B");
}

/**
 * Keep only the values present in an allow-list.
 *
 * @param mixed             $raw     Scalar, comma-separated string, or array.
 * @param array<int,string> $allowed Allow-list.
 *
 * @return array<int, string>
 */
function logsWhitelist(mixed $raw, array $allowed): array
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
 * Normalise the whole logs filter payload into a canonical, validated structure.
 *
 * Everything the client sends is either matched against an allow-list or coerced to a bounded
 * integer, and unknown keys are dropped, so a filter that does not exist server-side can never
 * reach the SQL builder. The same payload feeds the read queries and the purge, which is what
 * guarantees that what gets deleted is what the table displayed.
 *
 * @param array<string, mixed> $raw Raw client payload.
 *
 * @return array{source: string, types: array<int,string>, actions: array<int,string>, term: string,
 *               search_column: string, date_from: ?int, date_to: ?int, user_id: ?int,
 *               folder_id: ?int, scope: string, channel: string}
 */
function logsNormalizeFilters(array $raw): array
{
    $source = is_string($raw['source'] ?? null) && in_array($raw['source'], logsAllowedSources(), true)
        ? (string) $raw['source']
        : 'system';

    // An empty selection means "every type", never "no type": an empty IN () silently matches
    // nothing, which on the purge path would announce a scope and then delete none of it.
    $types = [];
    if ($source === 'system') {
        $types = logsWhitelist($raw['types'] ?? null, logsAllowedSystemTypes());
        if ($types === []) {
            $types = logsAllowedSystemTypes();
        }
    }

    $allowedActions = [];
    if ($source === 'items') {
        $allowedActions = logsAllowedItemActions();
    } elseif ($source === 'kb') {
        $allowedActions = logsAllowedKbActions();
    }

    // -1 is the historical "all users" sentinel of the purge form.
    $userId = filter_var($raw['user_id'] ?? null, FILTER_VALIDATE_INT);
    $folderId = filter_var($raw['folder_id'] ?? null, FILTER_VALIDATE_INT);
    $scope = is_string($raw['scope'] ?? null) && in_array($raw['scope'], ['personal', 'shared'], true)
        ? (string) $raw['scope']
        : '';
    $channel = is_string($raw['channel'] ?? null) && in_array($raw['channel'], ['web', 'api'], true)
        ? (string) $raw['channel']
        : '';

    return [
        'source' => $source,
        'types' => $types,
        'actions' => logsWhitelist($raw['actions'] ?? null, $allowedActions),
        'term' => is_string($raw['term'] ?? null) ? mb_substr(trim((string) $raw['term']), 0, 100) : '',
        // Validated again by getItemLogSearchColumns(), which falls back to every column.
        'search_column' => is_string($raw['search_column'] ?? null) ? (string) $raw['search_column'] : 'all',
        'date_from' => logsParseDateBoundary($raw['date_from'] ?? null, false),
        'date_to' => logsParseDateBoundary($raw['date_to'] ?? null, true),
        'user_id' => $userId === false || $userId <= 0 ? null : $userId,
        'folder_id' => $source === 'items' && $folderId !== false && $folderId > 0 ? $folderId : null,
        'scope' => $source === 'items' ? $scope : '',
        'channel' => $source === 'kb' ? '' : $channel,
    ];
}

/**
 * Columns the client must display for a source and type selection.
 *
 * Kept server-side so the column contract has a single definition: the client only toggles what
 * this returns. A column is listed as soon as one selected type feeds it, so a multi-type view
 * shows the union and the Type badge explains the empty cells.
 *
 * @param array<int, string> $typeKeys Interface type keys, meaningful for the 'system' source only.
 *
 * @return array<int, string>
 */
function logsVisibleColumns(string $source, array $typeKeys): array
{
    if ($source === 'items') {
        return ['date', 'id', 'label', 'folder', 'user', 'action', 'api', 'personal'];
    }
    if ($source === 'kb') {
        return ['date', 'label', 'user', 'action', 'details'];
    }

    $rule = logsSystemColumnRule();
    $columns = $rule['base'];
    foreach ($rule['order'] as $key) {
        if (in_array($key, $typeKeys, true) === false) {
            continue;
        }
        foreach ($rule['per_type'][$key] ?? [] as $column) {
            if (in_array($column, $columns, true) === false) {
                $columns[] = $column;
            }
        }
    }

    return $columns;
}

/**
 * The system column rule as plain data.
 *
 * The page serialises this for the client, which has to know its column set before the first ajax
 * call and therefore cannot wait for the response to carry it. Emitting the data rather than
 * letting the client hardcode the mapping keeps one definition of what feeds what.
 *
 * @return array{base: array<int,string>, per_type: array<string, array<int,string>>, order: array<int,string>}
 */
function logsSystemColumnRule(): array
{
    return [
        'base' => ['date', 'type', 'label', 'user'],
        'per_type' => [
            'connections' => ['source'],
            'failed' => ['ip', 'channel', 'actions'],
            'errors' => [],
            'admin' => ['target'],
        ],
        'order' => logsAllowedSystemTypes(),
    ];
}

/**
 * Add the channel restriction to a predicate, for either log family.
 *
 * @param string $source 'system' or 'items'.
 * @param string $prefix Table alias prefix used by the query.
 */
function logsAddChannelClause(WhereClause $where, string $source, string $channel, string $prefix = 'l.'): void
{
    if ($channel !== 'web' && $channel !== 'api') {
        return;
    }

    if ($source === 'items') {
        // The item log marker is unambiguous: only the API writes it into the reason.
        $where->add("COALESCE(" . $prefix . "raison, '') LIKE %ss", 'tp_src=api');
    } else {
        [$sql, $values] = logsSystemApiPredicate($prefix);
        $where->add($sql, ...$values);
    }

    if ($channel === 'web') {
        $where->negateLast();
    }
}

/**
 * Build the log_system read predicate from the canonical filters.
 *
 * The free-text search covers the columns the merged view displays, so what the search box
 * matches is what the administrator reads on screen.
 *
 * @param array<string, mixed> $filters Output of logsNormalizeFilters().
 */
function buildSystemLogFilter(array $filters): WhereClause
{
    // An empty selection means every type, the same rule logsNormalizeFilters() applies. Relying
    // on the caller for that turned a filter set built for another source into an empty IN (),
    // which MeekroDB rejects outright rather than matching nothing.
    $types = $filters['types'] ?? [];
    if (is_array($types) === false || $types === []) {
        $types = logsAllowedSystemTypes();
    }

    $where = new WhereClause('AND');
    $where->add('l.type IN %ls', logsSystemTypeDbValues($types));

    if ($filters['term'] !== '') {
        $search = $where->addClause('OR');
        foreach (['l.label', 'l.field_1', 'u.login', 'u.name', 'u.lastname'] as $column) {
            $search->add($column . ' LIKE %ss', $filters['term']);
        }
    }
    if ($filters['date_from'] !== null) {
        $where->add('l.date >= %i', $filters['date_from']);
    }
    if ($filters['date_to'] !== null) {
        $where->add('l.date < %i', $filters['date_to']);
    }
    if ($filters['user_id'] !== null) {
        // qui also stores IP addresses on failed authentications: compare as text, otherwise
        // MySQL coerces the column to a number and matches unrelated rows.
        $where->add('l.qui = %s', (string) $filters['user_id']);
    }
    logsAddChannelClause($where, 'system', $filters['channel']);

    return $where;
}

/**
 * Build the log_items read predicate from the canonical filters.
 *
 * Folder and personal scope are expressed on the joined aliases the read query already carries.
 *
 * @param array<string, mixed> $filters Output of logsNormalizeFilters().
 */
function buildItemLogFilter(array $filters, Language $lang): WhereClause
{
    $where = buildItemLogSearchFilter($filters['search_column'], $filters['term'], $lang);

    if ($filters['actions'] !== []) {
        $where->add('l.action IN %ls', $filters['actions']);
    }
    if ($filters['date_from'] !== null) {
        $where->add('l.date >= %i', $filters['date_from']);
    }
    if ($filters['date_to'] !== null) {
        $where->add('l.date < %i', $filters['date_to']);
    }
    if ($filters['user_id'] !== null) {
        $where->add('l.id_user = %i', $filters['user_id']);
    }
    if ($filters['folder_id'] !== null) {
        $where->add('i.id_tree = %i', $filters['folder_id']);
    }
    if ($filters['scope'] !== '') {
        $where->add('t.personal_folder = %i', $filters['scope'] === 'personal' ? 1 : 0);
    }
    logsAddChannelClause($where, 'items', $filters['channel']);

    return $where;
}

/**
 * Order facet values by their translated label.
 *
 * Sorting on the stored codes would order the interface by at_* names, which is meaningless to
 * a reader, and a plain strcmp() on the labels would push every accented entry after Z. ext-intl
 * is not a TeamPass requirement, so the Unicode root collation is used when it is available and a
 * transliterated key otherwise (ext-iconv is required).
 *
 * @param array<int, string>    $values Facet values to order.
 * @param array<string, string> $labels Translated label per value.
 *
 * @return array<int, string>
 */
function logsSortByLabel(array $values, array $labels): array
{
    $collator = class_exists('Collator') === true ? collator_create('root') : null;

    $sortKey = static function (string $value) use ($labels): string {
        $label = $labels[$value] ?? $value;
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $label);

        return mb_strtolower($ascii === false ? $label : $ascii, 'UTF-8');
    };

    usort($values, static function (string $a, string $b) use ($labels, $collator, $sortKey): int {
        if ($collator !== null) {
            $compared = $collator->compare($labels[$a] ?? $a, $labels[$b] ?? $b);
            if ($compared !== false) {
                return (int) $compared;
            }
        }

        return strcmp($sortKey($a), $sortKey($b));
    });

    return $values;
}

/**
 * Decide whether one knowledge-base log row belongs to the current filter selection.
 *
 * Knowledge-base logs are misc rows decoded in PHP, not a queryable table, so their facets are
 * applied here rather than in SQL. Keeping the decision in this module is what makes the KB source
 * answer the same question as the other two.
 *
 * @param array<string, mixed> $row     Decoded log row: date, user_id, action.
 * @param array<string, mixed> $filters Output of logsNormalizeFilters().
 */
function kbLogRowMatchesFilters(array $row, array $filters): bool
{
    $date = (int) ($row['date'] ?? 0);
    if (($filters['date_from'] ?? null) !== null && $date < $filters['date_from']) {
        return false;
    }
    // The boundary is the exclusive start of the next day, exactly like the SQL sources.
    if (($filters['date_to'] ?? null) !== null && $date >= $filters['date_to']) {
        return false;
    }
    if (($filters['user_id'] ?? null) !== null && (int) ($row['user_id'] ?? 0) !== $filters['user_id']) {
        return false;
    }
    $actions = $filters['actions'] ?? [];
    if ($actions !== [] && in_array((string) ($row['action'] ?? ''), $actions, true) === false) {
        return false;
    }

    return true;
}

/**
 * Facets that are active but that a flat single-table DELETE cannot express.
 *
 * The purge must delete exactly what the table displayed. Rather than silently ignoring a facet
 * and destroying more than the announced scope, the operation is refused and the interface names
 * what has to be cleared first.
 *
 * @param array<string, mixed> $filters Output of logsNormalizeFilters().
 *
 * @return array<int, string>
 */
function logsPurgeBlockingFacets(array $filters): array
{
    $blocking = [];
    // The free-text search of both families reaches joined tables (item label, folder title, user
    // identity); a DELETE ... WHERE on the log table alone cannot reproduce it.
    if (($filters['term'] ?? '') !== '') {
        $blocking[] = 'term';
    }
    if (($filters['folder_id'] ?? null) !== null) {
        $blocking[] = 'folder';
    }
    if (($filters['scope'] ?? '') !== '') {
        $blocking[] = 'scope';
    }

    return $blocking;
}

/**
 * Build the exact deletion scope from the canonical filter payload, without accessing a database.
 *
 * Returning null is a refusal the caller must honour: it never means "purge everything". A bounded
 * date range is mandatory, so the predicate is never empty - an empty WhereClause renders as (1)
 * and would delete the whole table.
 *
 * @param array<string, mixed>  $filters   Output of logsNormalizeFilters().
 * @param string|null           $userLogin Current login of the filtered user, required as soon as
 *                                         failed authentications are in scope.
 * @param array<string, string> $tables    Prefixed names of 'items', 'users' and 'nested_tree',
 *                                         required by the item source to reproduce the joins the
 *                                         displayed view applies. Missing ones refuse the purge.
 *
 * @return array{table: string, where: WhereClause}|null
 */
function buildLogsPurgeFilter(array $filters, ?string $userLogin = null, array $tables = []): ?array
{
    $source = (string) ($filters['source'] ?? '');
    $start = $filters['date_from'] ?? null;
    $end = $filters['date_to'] ?? null;
    $userId = $filters['user_id'] ?? null;

    // The knowledge base keeps its own purge route: its logs are misc rows, not a log table.
    if (in_array($source, ['system', 'items'], true) === false
        || $start === null || $end === null || $end <= $start
        || logsPurgeBlockingFacets($filters) !== []
    ) {
        return null;
    }

    $where = new WhereClause('AND');
    $where->add('date >= %i AND date < %i', $start, $end);

    if ($source === 'items') {
        // The item view joins items, users and nested_tree, so a log row whose item, author or
        // folder has disappeared is never displayed. A flat DELETE would still match it and
        // destroy more than the table announced, so the scope is narrowed back to what the joins
        // resolve. Those unreachable rows belong to the orphan maintenance task, not to a purge.
        $itemsTable = (string) ($tables['items'] ?? '');
        $usersTable = (string) ($tables['users'] ?? '');
        $treeTable = (string) ($tables['nested_tree'] ?? '');
        if ($itemsTable === '' || $usersTable === '' || $treeTable === '') {
            return null;
        }
        $where->add(
            'id_item IN (SELECT i.id FROM %l AS i INNER JOIN %l AS t ON (i.id_tree = t.id))',
            $itemsTable,
            $treeTable
        );
        $where->add('id_user IN (SELECT id FROM %l)', $usersTable);

        if ($filters['actions'] !== []) {
            $where->add('action IN %ls', $filters['actions']);
        }
        if ($userId !== null) {
            $where->add('id_user = %i', $userId);
        }
        logsAddChannelClause($where, 'items', (string) ($filters['channel'] ?? ''), '');

        return ['table' => 'log_items', 'where' => $where];
    }

    $types = $filters['types'] ?? [];
    if ($types === []) {
        return null;
    }
    $where->add('type IN %ls', logsSystemTypeDbValues($types));

    if ($userId !== null) {
        $failedInScope = in_array('failed', $types, true);
        $otherTypes = array_values(array_diff($types, ['failed']));

        // Failed authentications store the submitted login in field_1, never a user id. Without a
        // resolvable login the scope cannot be reproduced, so the whole purge is refused rather
        // than narrowed: the count the table announced would no longer match what is deleted.
        if ($failedInScope === true && ($userLogin === null || $userLogin === '')) {
            return null;
        }

        $userWhere = $where->addClause('OR');
        if ($otherTypes !== []) {
            // qui is also used for IP addresses: compare as text to avoid MySQL numeric coercion.
            $userWhere->add(
                '(type IN %ls AND qui = %s)',
                logsSystemTypeDbValues($otherTypes),
                (string) $userId
            );
        }
        if ($failedInScope === true) {
            // Only the current login is known: attempts made under a previous login remain.
            // Equality follows the database collation (normally utf8mb4_unicode_ci), including
            // case variants. The API appends a marker; only API-compatible labels match it.
            $userWhere->add(
                '(type = %s AND (field_1 = %s OR (field_1 = %s AND label IN %ls)))',
                'failed_auth',
                $userLogin,
                $userLogin . ' | tp_src=api',
                array_merge(logsApiFailureLabels(), ['bruteforce_account_locked'])
            );
        }
    }

    // The purge runs on the log table alone, without the users join the read query carries.
    logsAddChannelClause($where, 'system', (string) ($filters['channel'] ?? ''), '');

    return ['table' => 'log_system', 'where' => $where];
}

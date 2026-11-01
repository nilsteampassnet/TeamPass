<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

/**
 * Build a conservative SQL prefilter for metadata carrying mixed HTML encodings.
 *
 * Every entity-bearing value remains a candidate, including numeric, mixed and
 * literal entities. The caller must authorize rows in SQL and then apply the
 * decoded matcher before counting, ranking or pagination. Values stay bound;
 * columns and the placeholder prefix must come from server-side allowlists.
 *
 * @param array<string> $columns Trusted SQL column identifiers.
 * @param array<string> $terms Plain text terms, ANDed across the supplied columns.
 * @param string $prefix Trusted placeholder prefix.
 * @return array{sql: string, params: array<string, string>}
 */
function storedTextSearchCandidates(array $columns, array $terms, string $prefix = 'text'): array
{
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $prefix)) {
        throw new InvalidArgumentException('Invalid search placeholder prefix');
    }
    foreach ($columns as $column) {
        if (!preg_match('/^(?:[a-zA-Z_][a-zA-Z0-9_]*\.)?[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new InvalidArgumentException('Invalid search column');
        }
    }
    if ($terms === []) {
        return ['sql' => '(1 = 1)', 'params' => []];
    }
    if ($columns === []) {
        return ['sql' => '(1 = 0)', 'params' => []];
    }

    $clauses = [];
    $params = [$prefix . '_entity' => '%&%;%'];
    foreach (array_values($terms) as $index => $term) {
        $key = $prefix . $index;
        $params[$key] = $term;
        $alternatives = [];
        foreach ($columns as $column) {
            $alternatives[] = '(' . $column . ' LIKE %ss_' . $key
                . ' OR ' . $column . ' LIKE %s_' . $prefix . '_entity)';
        }
        $clauses[] = '(' . implode(' OR ', $alternatives) . ')';
    }

    return ['sql' => implode(' AND ', $clauses), 'params' => $params];
}

/**
 * Decode exactly one storage layer; callers still escape at their output sink.
 *
 * @param string $value Stored metadata.
 * @return string Plain display text.
 */
function storedTextSearchDecode(string $value): string
{
    return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Build a case- and accent-insensitive key from plain display text.
 *
 * Normalizer is supplied by intl or the existing Composer polyfill. Keep the
 * case-insensitive fallback for contexts where neither has been loaded.
 *
 * @param string $text Plain text, not an HTML-encoded storage value.
 * @return string Comparison key; never use it as the displayed payload.
 */
function storedTextSearchFold(string $text): string
{
    if (class_exists('Normalizer')) {
        $normalized = \Normalizer::normalize($text, \Normalizer::FORM_D);
        if ($normalized !== false) {
            $text = preg_replace('/\p{Mn}+/u', '', $normalized) ?? $text;
        }
    }
    return mb_strtolower($text, 'UTF-8');
}

/**
 * Match all terms against the displayed values of at least one metadata field.
 *
 * Normalizers are pure server callbacks for special display contracts, such as
 * rich descriptions or the login replacing a numeric personal-root title.
 *
 * @param array<string, mixed> $row
 * @param array<string> $terms Plain terms, or keys prepared by the streaming reader.
 * @param array<string> $fields Row keys, not SQL identifiers.
 * @param array<string, callable(string, array): string> $normalizers
 * @param bool $termsAreFolded Whether the reader already folded these terms once.
 * @return bool Whether every term appears in a displayed field.
 */
function storedTextSearchMatches(
    array $row,
    array $terms,
    array $fields,
    array $normalizers = [],
    bool $termsAreFolded = false
): bool {
    if ($terms === []) {
        return true;
    }
    if (!$termsAreFolded) {
        $terms = array_map('storedTextSearchFold', $terms);
    }
    $values = [];
    foreach ($fields as $field) {
        $value = (string) ($row[$field] ?? '');
        $values[] = storedTextSearchFold(isset($normalizers[$field])
            ? $normalizers[$field]($value, $row)
            : storedTextSearchDecode($value));
    }
    foreach ($terms as $term) {
        $matched = false;
        foreach ($values as $value) {
            if (mb_strpos($value, $term, 0, 'UTF-8') !== false) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            return false;
        }
    }
    return true;
}

/**
 * Project a folded sort key once per matched row, leaving its payload intact.
 *
 * @param array<string, mixed> $row Stored metadata.
 * @param string $field Displayed sort field.
 * @param callable(string, array): string|null $normalizer Optional display callback.
 * @return array<string, mixed> Row with a private comparison key.
 */
function storedTextSearchSortRow(array $row, string $field, ?callable $normalizer = null): array
{
    $value = (string) ($row[$field] ?? '');
    $row['_search_key'] = storedTextSearchFold($normalizer === null
        ? storedTextSearchDecode($value) : $normalizer($value, $row));
    return $row;
}

/**
 * Compare precomputed display keys with a deterministic object-ID tie breaker.
 *
 * @param array<string, mixed> $left Projected row.
 * @param array<string, mixed> $right Projected row.
 * @return int Negative when the left row sorts first.
 */
function storedTextSearchCompareRows(array $left, array $right): int
{
    return strcmp($left['_search_key'], $right['_search_key'])
        ?: (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
}

/**
 * Release an unbuffered MeekroDB result before a handler hydrates its selected page.
 *
 * @param MeekroDBWalk $cursor Authorized metadata cursor.
 * @return Generator<int, array<string, mixed>>
 */
function storedTextSearchRows(MeekroDBWalk $cursor): Generator
{
    try {
        while (is_array($row = $cursor->next())) {
            yield $row;
        }
    } finally {
        $cursor->free();
    }
}

/** @extends SplHeap<array<string, mixed>> */
final class StoredTextSearchHeap extends SplHeap
{
    private Closure $compareRows;

    /**
     * Keep the worst selected row at the top, ready to discard.
     *
     * @param callable(array, array): int $compareRows Endpoint ordering.
     */
    public function __construct(callable $compareRows)
    {
        $this->compareRows = Closure::fromCallable($compareRows);
    }

    /**
     * Apply the endpoint's deterministic display ordering.
     *
     * @param mixed $left Projected row.
     * @param mixed $right Projected row.
     * @return int Negative when the left row sorts first.
     */
    protected function compare(mixed $left, mixed $right): int
    {
        return ($this->compareRows)($left, $right);
    }
}

/**
 * Filter an authorized metadata stream before its count, sort and page boundaries.
 *
 * SQL must not LIMIT the candidate stream. Memory retains at most offset+limit
 * rows for a display sort, or just limit rows for a structural SQL order. An
 * optional projection lets large result pages retain only IDs and sort keys.
 *
 * @param iterable<array<string, mixed>>|MeekroDBWalk $rows Unbuffered database cursors are always freed.
 * @param array<string> $terms
 * @param array<string> $fields
 * @param int $offset Number of matched rows to skip.
 * @param int $limit Maximum page size.
 * @param callable(array, array): int|null $compare
 * @param array<string, callable(string, array): string> $normalizers
 * @param callable(array): array|null $project
 * @return array{rows: array, total: int, more: bool}
 */
function storedTextSearchPage(
    iterable|MeekroDBWalk $rows,
    array $terms,
    array $fields,
    int $offset,
    int $limit,
    ?callable $compare = null,
    array $normalizers = [],
    ?callable $project = null
): array {
    $offset = max(0, $offset);
    $limit = max(0, $limit);
    $capacity = $offset > PHP_INT_MAX - $limit ? PHP_INT_MAX : $offset + $limit;
    $heap = $compare === null ? null : new StoredTextSearchHeap($compare);
    $selected = [];
    $total = 0;
    $cursor = $rows instanceof MeekroDBWalk ? $rows : null;
    if ($cursor !== null) {
        $rows = storedTextSearchRows($cursor);
    }
    try {
        $foldedTerms = array_map('storedTextSearchFold', $terms);
        foreach ($rows as $row) {
            if (!storedTextSearchMatches($row, $foldedTerms, $fields, $normalizers, true)) {
                continue;
            }
            ++$total;
            if ($limit === 0) {
                continue;
            }
            if ($heap !== null) {
                $heap->insert($project === null ? $row : $project($row));
                if ($heap->count() > $capacity) {
                    $heap->extract();
                }
            } elseif ($total > $offset && count($selected) < $limit) {
                $selected[] = $project === null ? $row : $project($row);
            }
        }
    } finally {
        $cursor?->free();
    }
    if ($heap !== null) {
        $selected = iterator_to_array($heap, false);
        usort($selected, $compare);
        $selected = array_slice($selected, $offset, $limit);
    }
    return ['rows' => $selected, 'total' => $total, 'more' => $total > $capacity];
}

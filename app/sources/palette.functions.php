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
 * @file      palette.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Universal Search / Command Palette (F15 — Scale & polish).
 *
 * Pure, DB-free palette logic (unit-tested by
 * tests/Unit/CommandPaletteLogicTest.php). The ACL-bound queries live in
 * palette.queries.php; these functions normalise the search term and rank
 * the results, never touching any secret value.
 */

require_once __DIR__ . '/stored_text_search.php';

/**
 * Normalise a palette search term.
 *
 * Trims, bounds the length, and rejects terms that are too short to search
 * (avoids full-table LIKE scans on every keystroke).
 *
 * @param string $term        Raw client input
 * @param int    $minLength   Minimum useful length
 * @param int    $maxLength   Hard cap
 *
 * @return string Normalised term, or '' when unusable
 */
function paletteNormalizeTerm(string $term, int $minLength = 2, int $maxLength = 100): string
{
    $term = trim($term);
    if (mb_strlen($term) < $minLength) {
        return '';
    }

    return mb_substr($term, 0, $maxLength);
}

/**
 * Flatten rich text (KB descriptions) to a searchable/displayable string.
 *
 * KB descriptions are stored either as sanitized HTML or, for legacy rows, as
 * escaped markup (`&lt;p&gt;`); both must flatten to the same readable text.
 * Tags are turned into word boundaries so `<p>a</p><p>b</p>` never becomes
 * `ab`.
 *
 * @param string $html      Stored description
 * @param int    $maxLength Hard cap on the returned text
 *
 * @return string Plain text, whitespace-collapsed
 */
function paletteFlattenRichText(string $html, int $maxLength = 2000): string
{
    $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace('<', ' <', $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));

    return mb_substr($text, 0, $maxLength);
}

/**
 * Build a short excerpt centred on the term match.
 *
 * Gives the user the context of the hit instead of the first words of the
 * entry, which are rarely the reason it matched.
 *
 * @param string $text      Plain text (already flattened)
 * @param string $term      Normalised term
 * @param int    $maxLength Excerpt length
 *
 * @return string Excerpt, ellipsised on both ends when trimmed
 */
function paletteBuildExcerpt(string $text, string $term, int $maxLength = 90): string
{
    $text = trim($text);
    if ($text === '' || $maxLength < 1) {
        return '';
    }

    $pos = mb_strpos(mb_strtolower($text), mb_strtolower(trim($term)));
    // Keep a bit of run-up before the match so the hit is not glued to the left.
    $start = ($pos === false || $pos < 20) ? 0 : $pos - 20;
    $excerpt = mb_substr($text, $start, $maxLength);

    if ($start > 0) {
        $excerpt = '…' . $excerpt;
    }
    if (mb_strlen($text) > $start + $maxLength) {
        $excerpt = rtrim($excerpt) . '…';
    }

    return $excerpt;
}

/**
 * Compute relevance and alphabetic keys once per matched palette row.
 *
 * @param array<string, mixed> $row Stored metadata.
 * @param string $foldedTerm Prepared user term.
 * @param string $key Label field to rank.
 * @return array<string, mixed> Row with private relevance and sort keys.
 */
function paletteProjectRow(array $row, string $foldedTerm, string $key = 'label'): array
{
    $row = storedTextSearchSortRow($row, $key);
    $text = $row['_search_key'];
    $position = $foldedTerm === '' ? false : mb_strpos($text, $foldedTerm, 0, 'UTF-8');
    $row['_search_score'] = $text !== '' && $text === $foldedTerm ? 0
        : ($position === false ? PHP_INT_MAX : ($position === 0 ? 1 : 2 + $position));
    return $row;
}

/**
 * Compare precomputed relevance, folded display keys and stable object IDs.
 *
 * @param array<string, mixed> $left Projected row.
 * @param array<string, mixed> $right Projected row.
 * @return int Negative when the left result ranks first.
 */
function paletteCompareRows(array $left, array $right): int
{
    return $left['_search_score'] <=> $right['_search_score']
        ?: storedTextSearchCompareRows($left, $right);
}

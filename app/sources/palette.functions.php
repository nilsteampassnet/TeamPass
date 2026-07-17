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
 * Escape a term for safe use inside a LIKE pattern.
 *
 * `%` and `_` are user-typable wildcards that would otherwise widen the
 * match (LIKE injection).
 *
 * @param string $term Normalised term
 *
 * @return string Term with LIKE wildcards escaped
 */
function paletteEscapeLikeTerm(string $term): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
}

/**
 * Rank palette rows by relevance to the term.
 *
 * Order: exact match, prefix match, earliest substring position, then
 * alphabetical — so "gitlab" beats "my gitlab" for the term "git".
 * Rows must carry the searchable text under $key.
 *
 * @param array<int, array<string, mixed>> $rows Result rows
 * @param string                           $term Normalised term
 * @param string                           $key  Row key holding the display text
 *
 * @return array<int, array<string, mixed>> Rows, most relevant first
 */
function paletteRankRows(array $rows, string $term, string $key = 'label'): array
{
    $needle = mb_strtolower($term);

    $scored = array_map(static function (array $row) use ($needle, $key): array {
        $text = mb_strtolower((string) ($row[$key] ?? ''));
        $pos = $needle === '' ? false : mb_strpos($text, $needle);
        if ($text !== '' && $text === $needle) {
            $score = 0;          // exact
        } elseif ($pos === 0) {
            $score = 1;          // prefix
        } elseif ($pos !== false) {
            $score = 2 + $pos;   // substring, earlier is better
        } else {
            $score = PHP_INT_MAX; // no direct match (e.g. matched on login/url)
        }
        $row['_score'] = $score;

        return $row;
    }, $rows);

    usort($scored, static function (array $a, array $b) use ($key): int {
        if ($a['_score'] !== $b['_score']) {
            return $a['_score'] <=> $b['_score'];
        }

        return strcasecmp((string) ($a[$key] ?? ''), (string) ($b[$key] ?? ''));
    });

    return array_map(static function (array $row): array {
        unset($row['_score']);

        return $row;
    }, $scored);
}

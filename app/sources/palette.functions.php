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
 * Tell whether the term appears in at least one of the given texts.
 *
 * Used to drop rows whose SQL LIKE only matched HTML markup (searching
 * "table" must not return every entry containing a `<table>`).
 *
 * @param array<int, string> $haystacks Texts to test
 * @param string             $term      Normalised term
 *
 * @return bool True when the term is readable in one of the texts
 */
function paletteTextMatchesTerm(array $haystacks, string $term): bool
{
    $needle = mb_strtolower(trim($term));
    if ($needle === '') {
        return false;
    }

    foreach ($haystacks as $haystack) {
        if (mb_strpos(mb_strtolower((string) $haystack), $needle) !== false) {
            return true;
        }
    }

    return false;
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

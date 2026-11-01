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

/** Rank a decoded label by exact, prefix, then substring match; keep other-field hits last. */
function paletteStoredTextScore(string $stored, string $term): int
{
    $needle = mb_strtolower($term, 'UTF-8');
    $text = mb_strtolower(storedTextSearchDecode($stored), 'UTF-8');
    $position = $needle === '' ? false : mb_strpos($text, $needle, 0, 'UTF-8');
    if ($text !== '' && $text === $needle) {
        return 0;
    }
    return $position === false ? PHP_INT_MAX : ($position === 0 ? 1 : 2 + $position);
}

/** Use one decoded relevance order for the bounded stream and the legacy array helper. */
function paletteCompareRows(array $left, array $right, string $term, string $key = 'label'): int
{
    $leftText = (string) ($left[$key] ?? '');
    $rightText = (string) ($right[$key] ?? '');
    return paletteStoredTextScore($leftText, $term) <=> paletteStoredTextScore($rightText, $term)
        ?: storedTextSearchCompare($leftText, $rightText)
        ?: (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
}

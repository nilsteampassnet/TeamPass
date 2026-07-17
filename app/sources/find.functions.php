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
 * @file      find.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Pure, DB-free helpers used by the item search handlers.
 */

/**
 * Convert a stored item description into a bounded plain-text preview.
 *
 * Item descriptions may contain raw HTML (legacy records) or HTML entities
 * produced by the current rich-text save path. Decode before removing tags so
 * markup is never truncated or displayed literally in search results.
 *
 * @param string $description Stored item description
 * @param int    $maxLength   Maximum preview length in Unicode characters
 *
 * @return string Normalized plain-text preview
 */
function findBuildDescriptionPreview(string $description, int $maxLength = 200): string
{
    if ($description === '' || $maxLength <= 0) {
        return '';
    }

    $decodedDescription = html_entity_decode(
        $description,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    // Preserve visual boundaries between rich-text blocks before removing tags.
    $decodedDescription = preg_replace(
        '#<\s*(?:br|hr|/p|/div|/li|/ul|/ol|/blockquote|/h[1-6]|/tr|/td|/th|/table)\b[^>]*>#iu',
        ' ',
        $decodedDescription
    ) ?? $decodedDescription;

    $plainText = strip_tags($decodedDescription);
    $plainText = str_replace("\xC2\xA0", ' ', $plainText);
    $plainText = preg_replace('/\s+/u', ' ', $plainText) ?? $plainText;
    $plainText = trim($plainText);

    return mb_substr($plainText, 0, $maxLength);
}

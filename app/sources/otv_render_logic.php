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
 * @file      otv_render_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Pure, DB-free helpers rendering item fields on the One-Time View page.
 */

/**
 * Decode the HTML entities the item write paths store in the database.
 *
 * Item fields never reach the database as raw markup: the rich-text description is
 * escaped client-side by purifyData()/escapeHtmlString(), while label and login go
 * through FILTER_SANITIZE_FULL_SPECIAL_CHARS. Every other consumer decodes before
 * rendering (item card, search previews, exports), so the One-Time View page must
 * do the same or the recipient reads "&#039;" and "<p>" as literal text.
 *
 * The loop is bounded so a multi-encoded record cannot turn the decoding into a
 * denial of service.
 *
 * @param string $value Stored field value
 *
 * @return string Decoded value
 */
function otvDecodeStoredValue(string $value): string
{
    for ($pass = 0; $pass < 5; ++$pass) {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($decoded === $value) {
            break;
        }

        $value = $decoded;
    }

    return $value;
}

/**
 * Decode a stored field and escape it for a text-only cell of the One-Time View page.
 *
 * Decoding then escaping is the exact inverse of the storage encoding, so the recipient
 * reads what the author typed. Nothing is stripped: the value lands in a text cell and
 * htmlspecialchars() at the sink is what makes it safe.
 *
 * @param string $value Stored field value
 *
 * @return string Value safe to interpolate in HTML
 */
function otvRenderPlainField(string $value): string
{
    return htmlspecialchars(otvDecodeStoredValue($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Turn a stored item description into the markup displayed to the recipient.
 *
 * strip_tags() with an allowlist is not enough here: it keeps every attribute, and the
 * page is served unauthenticated, so a stored "onerror" or "javascript:" would execute
 * in the recipient's browser (the class of GHSA-cpgh-9h3x-r8gm). HTMLPurifier is used
 * instead, with the same configuration shape as kbSanitizeRichHtml().
 *
 * @param string $description Stored item description
 *
 * @return string Sanitized HTML, or '' when nothing displayable remains
 */
function otvSanitizeDescription(string $description): string
{
    $decoded = otvDecodeStoredValue($description);

    if (otvDescriptionHasContent($decoded) === false) {
        return '';
    }

    if (class_exists('HTMLPurifier') === false) {
        // Defensive fallback: no markup at all rather than unsanitized attributes.
        return nl2br(htmlspecialchars(strip_tags($decoded), ENT_QUOTES, 'UTF-8'));
    }

    $config = HTMLPurifier_Config::createDefault();
    $config->set('Core.Encoding', 'UTF-8');
    $config->set(
        'HTML.Allowed',
        'a[href|title],br,p,div,span,ul,ol,li,blockquote,'
        . 'strong,b,em,i,u,s,strike,sub,sup,small,font,h1,h2,h3,h4,h5,h6,pre,code,hr,'
        . 'table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan],'
        . 'img[src|alt|width|height|title]'
    );
    // Reject javascript:/vbscript:; the data: scheme only resolves to base64 images.
    $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'data' => true]);
    $config->set('Attr.AllowedFrameTargets', ['_blank']);
    $config->set('HTML.TargetBlank', true);
    $config->set('HTML.MaxImgLength', 2000);
    // No writable cache directory required (read-only vendor / Docker).
    $config->set('Cache.DefinitionImpl', null);

    $sanitized = trim((new HTMLPurifier($config))->purify($decoded));

    return otvDescriptionHasContent($sanitized) === true ? $sanitized : '';
}

/**
 * Tell whether a description still carries something worth displaying.
 *
 * @param string $html Decoded or sanitized description
 *
 * @return bool
 */
function otvDescriptionHasContent(string $html): bool
{
    if (trim(strip_tags($html)) !== '') {
        return true;
    }

    return preg_match('/<img\b[^>]*\bsrc\s*=/iu', $html) === 1
        || preg_match('/<(?:table|hr)\b/iu', $html) === 1;
}

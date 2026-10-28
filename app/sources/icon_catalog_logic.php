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
 * Catalogue of the icon picker (item and folder icons), built from the metadata Font Awesome
 * Free ships with (public/plugins/fontawesome-free/metadata/icons.json), so it always matches
 * the icons the pages can render. Kept free of any database or session access so it can be
 * unit-tested on its own (tests/Unit/IconCatalogLogicTest.php).
 *
 * icons.json weighs several MB because it carries every SVG path: only the name, the free
 * styles and the search terms of each icon are sent to the browser.
 *
 * @file      icon_catalog_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Picker style codes, in the order the picker lists them.
 */
const ICON_CATALOG_STYLES = ['solid' => 's', 'regular' => 'r', 'brands' => 'b'];

/**
 * Icons the picker offers before any search, per kind of object. Each entry is the class
 * string stored in items.fa_icon or nested_tree.fa_icon / fa_icon_selected.
 */
const ICON_CATALOG_SUGGESTED = [
    // The usual subjects of a password manager
    'item' => [
        'fa-solid fa-key', 'fa-solid fa-lock', 'fa-solid fa-user', 'fa-solid fa-users',
        'fa-solid fa-user-shield', 'fa-solid fa-shield-halved', 'fa-solid fa-fingerprint', 'fa-solid fa-id-card',
        'fa-solid fa-server', 'fa-solid fa-database', 'fa-solid fa-hard-drive', 'fa-solid fa-network-wired',
        'fa-solid fa-wifi', 'fa-solid fa-globe', 'fa-solid fa-cloud', 'fa-solid fa-envelope',
        'fa-solid fa-at', 'fa-solid fa-phone', 'fa-solid fa-mobile-screen', 'fa-solid fa-laptop',
        'fa-solid fa-desktop', 'fa-solid fa-terminal', 'fa-solid fa-code', 'fa-solid fa-microchip',
        'fa-solid fa-print', 'fa-solid fa-credit-card', 'fa-solid fa-building-columns', 'fa-solid fa-wallet',
        'fa-solid fa-cart-shopping', 'fa-solid fa-building', 'fa-solid fa-house', 'fa-solid fa-gear',
        'fa-solid fa-certificate', 'fa-solid fa-file-lines', 'fa-solid fa-video', 'fa-solid fa-calendar',
        'fa-brands fa-google', 'fa-brands fa-microsoft', 'fa-brands fa-apple', 'fa-brands fa-linux',
        'fa-brands fa-windows', 'fa-brands fa-github', 'fa-brands fa-gitlab', 'fa-brands fa-aws',
        'fa-brands fa-docker', 'fa-brands fa-linkedin', 'fa-brands fa-paypal', 'fa-brands fa-amazon',
    ],
    // Folder shapes first, then the teams, places and systems folders are usually named after
    'folder' => [
        'fa-solid fa-folder', 'fa-solid fa-folder-open', 'fa-solid fa-folder-closed', 'fa-solid fa-folder-tree',
        'fa-regular fa-folder', 'fa-regular fa-folder-open', 'fa-regular fa-folder-closed', 'fa-solid fa-box-archive',
        'fa-solid fa-briefcase', 'fa-solid fa-building', 'fa-solid fa-building-columns', 'fa-solid fa-industry',
        'fa-solid fa-store', 'fa-solid fa-house', 'fa-solid fa-users', 'fa-solid fa-user-tie',
        'fa-solid fa-user-shield', 'fa-solid fa-shield-halved', 'fa-solid fa-lock', 'fa-solid fa-key',
        'fa-solid fa-server', 'fa-solid fa-database', 'fa-solid fa-network-wired', 'fa-solid fa-sitemap',
        'fa-solid fa-cloud', 'fa-solid fa-globe', 'fa-solid fa-wifi', 'fa-solid fa-laptop',
        'fa-solid fa-code', 'fa-solid fa-terminal', 'fa-solid fa-gear', 'fa-solid fa-envelope',
        'fa-solid fa-credit-card', 'fa-solid fa-cart-shopping', 'fa-solid fa-flask', 'fa-solid fa-graduation-cap',
        'fa-solid fa-star', 'fa-solid fa-bookmark', 'fa-brands fa-microsoft', 'fa-brands fa-google',
        'fa-brands fa-aws', 'fa-brands fa-linux', 'fa-brands fa-windows', 'fa-brands fa-github',
    ],
];

/**
 * Reduce the Font Awesome metadata to the rows the picker needs.
 *
 * Each row is [name, style codes, search terms]: the style codes ("s", "r", "b") list the
 * free styles of the icon, the terms are space separated and leave out the words already in
 * the name. Icons without a free style are dropped.
 *
 * @param array<mixed> $metadata Decoded icons.json
 * @return array<int, array{0: string, 1: string, 2: string}>
 */
function iconCatalogFromMetadata(array $metadata): array
{
    $rows = [];
    foreach ($metadata as $name => $icon) {
        $name = (string) $name;
        if (is_array($icon) === false || preg_match('/^[a-z0-9-]+$/', $name) !== 1) {
            continue;
        }

        $freeStyles = is_array($icon['free'] ?? null) === true ? $icon['free'] : [];
        $styles = '';
        foreach (ICON_CATALOG_STYLES as $style => $code) {
            if (in_array($style, $freeStyles, true) === true) {
                $styles .= $code;
            }
        }
        if ($styles === '') {
            continue;
        }

        $nameWords = explode('-', $name);
        $terms = [];
        $searchTerms = is_array($icon['search']['terms'] ?? null) === true ? $icon['search']['terms'] : [];
        foreach ($searchTerms as $term) {
            $term = strtolower(trim((string) $term));
            if ($term === '' || $term === $name || in_array($term, $nameWords, true) === true) {
                continue;
            }
            $terms[$term] = true;
        }

        $rows[] = [$name, $styles, implode(' ', array_keys($terms))];
    }

    usort($rows, static function (array $a, array $b): int {
        return strcmp($a[0], $b[0]);
    });

    return $rows;
}

/**
 * Load the picker catalogue from the icons.json file Font Awesome ships with.
 *
 * @param string $metadataFile Path to icons.json
 * @return array{icons: array<int, array{0: string, 1: string, 2: string}>, suggested: array<string, array<int, string>>}|null
 *         Null when the file is missing or unreadable
 */
function iconCatalogLoad(string $metadataFile): ?array
{
    if (is_file($metadataFile) === false) {
        return null;
    }
    $content = file_get_contents($metadataFile);
    if ($content === false) {
        return null;
    }
    $metadata = json_decode($content, true);
    if (is_array($metadata) === false) {
        return null;
    }

    return [
        'icons' => iconCatalogFromMetadata($metadata),
        'suggested' => ICON_CATALOG_SUGGESTED,
    ];
}

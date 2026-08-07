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
 * @file      log_display_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Display normalization of database-backed log values, kept free of any database or session
 * access so the whole encoding contract can be unit-tested on its own — same pattern as
 * security_posture_logic.php.
 *
 * Included by both:
 *   - app/sources/main.functions.php              (production, for the log data sources)
 *   - tests/Unit/UtilitiesLogsEncodingTest.php    (unit tests, on the real function)
 */

if (function_exists('normalizeLogDisplayValue') === false) {
    /**
     * Normalize a database-backed log value for safe display.
     *
     * Older records may contain HTML entities, and some values have already been encoded more than
     * once. Decode one storage layer here, then re-escape without double encoding. The DataTables
     * renderers decode the remaining display layer and escape it again before inserting it into the
     * DOM, so legacy accents remain readable without reintroducing stored XSS.
     *
     * The escaping step is what keeps the output inert: htmlspecialchars() always escapes '<', '>',
     * '"' and "'" whatever $double_encode is worth. Disabling double encoding only leaves an
     * already-valid entity untouched, so no markup can ever survive this function.
     *
     * @param mixed $value Value read from a log-related database column
     * @return string Value with one storage entity layer removed and safely re-escaped
     */
    function normalizeLogDisplayValue(mixed $value): string
    {
        $decodedValue = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return htmlspecialchars($decodedValue, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
    }
}

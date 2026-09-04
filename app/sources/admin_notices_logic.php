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
 * Presentation logic of the admin dashboard notices card, kept free of any database,
 * settings or session access so it can be unit-tested on its own — same pattern as
 * item_revisions_logic.php.
 *
 * It is included by both:
 *   - app/sources/admin_notices.functions.php   (collection adapters + rendering)
 *   - tests/Unit/AdminNoticesLogicTest.php      (unit tests)
 *
 * Everything about severity ranking, ordering and the adaptive two-card layout lives
 * here; the adapters only read the database and the settings.
 *
 * @file      admin_notices_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Severity levels a notice can carry, from the most to the least urgent.
 *
 * 'danger'  - the instance is in a state that needs attention now.
 * 'warning' - a pending maintenance operation, nothing is broken yet.
 * 'info'    - a getting-started recommendation, dismissible by the administrator.
 */
const ADMIN_NOTICE_SEVERITIES = ['danger', 'warning', 'info'];

/**
 * Returns the weight of a severity, higher meaning more urgent.
 *
 * An unknown severity ranks below every known one so a malformed notice can never
 * drive the colour of the whole card.
 *
 * @param string $severity Severity name.
 *
 * @return int Weight, 0 when the severity is unknown.
 */
function adminNoticeSeverityRank(string $severity): int
{
    $rank = array_search($severity, ADMIN_NOTICE_SEVERITIES, true);

    return $rank === false ? 0 : count(ADMIN_NOTICE_SEVERITIES) - (int) $rank;
}

/**
 * Returns the most urgent severity found in a list of notices.
 *
 * @param array $notices List of notices.
 *
 * @return string Severity name, empty string when the list holds no known severity.
 */
function adminNoticesMaxSeverity(array $notices): string
{
    $highest = '';

    foreach ($notices as $notice) {
        $severity = (string) ($notice['severity'] ?? '');
        if (adminNoticeSeverityRank($severity) > adminNoticeSeverityRank($highest)) {
            $highest = $severity;
        }
    }

    return $highest;
}

/**
 * Orders notices, the most urgent first.
 *
 * PHP 8 sorts are stable, so notices sharing a severity keep the order in which the
 * collectors declared them.
 *
 * @param array $notices List of notices.
 *
 * @return array Ordered list.
 */
function adminNoticesSort(array $notices): array
{
    usort(
        $notices,
        static function (array $first, array $second): int {
            return adminNoticeSeverityRank((string) ($second['severity'] ?? ''))
                <=> adminNoticeSeverityRank((string) ($first['severity'] ?? ''));
        }
    );

    return $notices;
}

/**
 * Returns the AdminLTE card class matching the most urgent notice.
 *
 * @param array $notices List of notices.
 *
 * @return string Card class.
 */
function adminNoticesCardClass(array $notices): string
{
    switch (adminNoticesMaxSeverity($notices)) {
        case 'danger':
            return 'card-danger';
        case 'warning':
            return 'card-warning';
        case 'info':
            return 'card-info';
        default:
            return 'card-default';
    }
}

/**
 * Returns the badge class matching the most urgent notice.
 *
 * @param array $notices List of notices.
 *
 * @return string Badge class.
 */
function adminNoticesBadgeClass(array $notices): string
{
    switch (adminNoticesMaxSeverity($notices)) {
        case 'danger':
            return 'badge-danger';
        case 'warning':
            return 'badge-warning';
        case 'info':
            return 'badge-info';
        default:
            return 'badge-secondary';
    }
}

/**
 * Returns the text colour class used for a notice icon.
 *
 * @param string $severity Severity name.
 *
 * @return string Text colour class.
 */
function adminNoticeIconClass(string $severity): string
{
    switch ($severity) {
        case 'danger':
            return 'text-danger';
        case 'warning':
            return 'text-warning';
        case 'info':
            return 'text-info';
        default:
            return 'text-muted';
    }
}

/**
 * Resolves the Bootstrap columns of the "System health" / "Actions required" row.
 *
 * With no notice at all the second card is not rendered: an empty card is what made
 * the dashboard look broken on a fresh installation. System health then takes the
 * full width and reports the maintenance state on its own line.
 *
 * @param array $notices List of notices.
 *
 * @return array{health: string, notices: string} Column classes; 'notices' is empty
 *                                                when the card must not be rendered.
 */
function adminNoticesLayoutColumns(array $notices): array
{
    if (count($notices) === 0) {
        return ['health' => 'col-lg-12', 'notices' => ''];
    }

    return ['health' => 'col-lg-5', 'notices' => 'col-lg-7'];
}

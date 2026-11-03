<?php
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
 * ---
 * Who may export items (Settings > Options: allow_print + roles_allowed_to_print_select).
 *
 * The Export menu and page applied this rule, but the handler that builds the files did not:
 * any logged-in user could export by posting to it directly. The page, the menu and the handler
 * now share this one decision.
 *
 * DB-free and session-free so it can be unit-tested in isolation
 * (tests/Unit/ExportAccessLogicTest.php).
 *
 * @file      export_access_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

if (function_exists('exportAllowedForUser') === false) {
    /**
     * Decide whether a user may export items.
     *
     * @param array<string, mixed> $settings  Instance settings (allow_print, roles_allowed_to_print_select).
     * @param string               $userRoles Semicolon-separated role ids held by the user (session user-roles).
     * @param bool                 $isAdmin   Whether the user is a TeamPass administrator.
     *
     * @return bool True when exports are enabled and the user holds one of the allowed roles.
     */
    function exportAllowedForUser(array $settings, string $userRoles, bool $isAdmin): bool
    {
        // Administrators never read shared item content.
        if ($isAdmin === true || (int) ($settings['allow_print'] ?? 0) !== 1) {
            return false;
        }

        // Stored as a JSON-like list, e.g. ["1","3"].
        $allowedRoles = exportAccessParseRoleIds(
            str_replace(['"', '[', ']'], '', (string) ($settings['roles_allowed_to_print_select'] ?? '')),
            ','
        );
        $heldRoles = exportAccessParseRoleIds($userRoles, ';');

        return count(array_intersect($allowedRoles, $heldRoles)) > 0;
    }

    /**
     * Split a role id list into positive integers.
     *
     * @param string $list      Raw list.
     * @param string $separator List separator.
     *
     * @return int[] Role ids found in the list.
     */
    function exportAccessParseRoleIds(string $list, string $separator): array
    {
        $ids = [];
        foreach (explode($separator, $list) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk !== '' && ctype_digit($chunk) === true && (int) $chunk > 0) {
                $ids[] = (int) $chunk;
            }
        }

        return array_values(array_unique($ids));
    }
}

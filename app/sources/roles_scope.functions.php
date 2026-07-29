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
 * @file      roles_scope.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Role assignment scope.
 *
 * Pure, DB-free set logic used when saving the roles of a user (unit-tested by
 * tests/Unit/RolesScopeLogicTest.php). The user edit form replaces the whole
 * manual role set while a manager only ever sees the roles they may grant, so
 * the submitted set alone cannot be taken as the final state: applying it as-is
 * would silently revoke every role outside the caller's scope.
 *
 * The DB-facing wrappers live in main.functions.php (callerGrantableRoleIds()
 * and reconcileManualUserRoles()).
 */

/**
 * Merge a submitted role set with the roles the caller may not act on.
 *
 * Roles within the caller's grant scope are taken from the submission, every
 * other role is preserved exactly as currently stored. The result is therefore
 * always a superset of the roles the caller cannot see, whatever was submitted.
 *
 * @param array $currentRoleIds   Role ids currently stored for the user.
 * @param array $submittedRoleIds Role ids coming from the form.
 * @param array $grantableRoleIds Role ids the caller is entitled to grant.
 *
 * @return array<int> Role ids to persist, unique and re-indexed.
 */
function mergeGrantableRoleSets(
    array $currentRoleIds,
    array $submittedRoleIds,
    array $grantableRoleIds
): array {
    $grantable = rolesScopeNormalizeIds($grantableRoleIds);

    // Roles the caller may not grant keep their stored state, whatever was submitted.
    $preserved = array_diff(rolesScopeNormalizeIds($currentRoleIds), $grantable);

    // Only the submitted roles the caller is entitled to grant are honoured.
    $granted = array_intersect(rolesScopeNormalizeIds($submittedRoleIds), $grantable);

    return array_values(array_unique(array_merge($preserved, $granted)));
}

/**
 * Normalize a role id list coming from a form, a session or a GROUP_CONCAT.
 *
 * Non numeric entries (empty strings mostly, produced by exploding an empty
 * column) are dropped, everything else is cast to int so that the set
 * operations never compare '3' with 3.
 *
 * @param array $roleIds Raw role ids.
 *
 * @return array<int> Clean list of positive role ids.
 */
function rolesScopeNormalizeIds(array $roleIds): array
{
    $normalized = [];
    foreach ($roleIds as $roleId) {
        if (is_numeric($roleId) === false) {
            continue;
        }
        $value = (int) $roleId;
        if ($value > 0) {
            $normalized[] = $value;
        }
    }

    return $normalized;
}

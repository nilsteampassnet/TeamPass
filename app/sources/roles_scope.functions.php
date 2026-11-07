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
 * Role and folder assignment scope.
 *
 * Pure, DB-free set logic used when saving the roles and the allowed / forbidden
 * folders of a user (unit-tested by tests/Unit/RolesScopeLogicTest.php and
 * tests/Unit/UserFolderGrantScopeTest.php). The user edit form replaces the whole
 * set while a manager only ever sees the roles and folders they may grant, so
 * the submitted set alone cannot be taken as the final state: applying it as-is
 * would silently revoke everything outside the caller's scope, and accept ids the
 * caller had no right to hand out.
 *
 * The DB-facing wrappers live in main.functions.php (callerGrantableRoleIds(),
 * reconcileManualUserRoles(), callerGrantableFolderIds() and reconcileUserFolderSet()).
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
 * Remove the roles inherited from AD groups from a role set submitted by the user form.
 *
 * The edit form displays the AD roles alongside the manual ones, so they come back in
 * the submission. Stored as manual roles they would survive the user's removal from the
 * AD group, the AD synchronization only ever touching its own source (PR #3635).
 *
 * @param array $submittedRoleIds Role ids coming from the form.
 * @param array $adRoleIds        Role ids the user holds through AD groups.
 *
 * @return array<int> Submitted role ids that are not AD roles, re-indexed.
 */
function excludeAdRolesFromSubmittedRoles(array $submittedRoleIds, array $adRoleIds): array
{
    return array_values(
        array_diff(rolesScopeNormalizeIds($submittedRoleIds), rolesScopeNormalizeIds($adRoleIds))
    );
}

/**
 * List the folders a non-administrator may grant or deny to another user.
 *
 * Same scope as the role matrix and as the folder lists of the user form: the
 * caller's accessible folders, minus the excluded ones (personal folders, which
 * belong to their owner, and the folders denied to the caller).
 *
 * @param array $accessibleFolderIds Folder ids the caller can access (session).
 * @param array $excludedFolderIds   Folder ids never grantable by the caller.
 *
 * @return array<int> Grantable folder ids, unique and re-indexed.
 */
function grantableFolderIds(array $accessibleFolderIds, array $excludedFolderIds): array
{
    return array_values(array_unique(array_diff(
        rolesScopeNormalizeIds($accessibleFolderIds),
        rolesScopeNormalizeIds($excludedFolderIds)
    )));
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

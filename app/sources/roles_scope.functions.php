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
 * It also bounds the folders a role rights change may write to (Roles page),
 * unit-tested by tests/Unit/RoleRightsScopeTest.php.
 *
 * The DB-facing wrappers live in main.functions.php (callerGrantableRoleIds(),
 * callerRoleAssignableFolderIds() and reconcileManualUserRoles()).
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
 * List the folders whose rights the caller may change on a role.
 *
 * A folder qualifies when the caller can access it, it still exists in the tree, and it is
 * neither in a personal tree (never granted to a role) nor denied to the caller.
 *
 * @param array $accessibleIds Folder ids the caller can access (session).
 * @param array $existingIds   Folder ids currently in the tree.
 * @param array $excludedIds   Personal and denied folder ids.
 *
 * @return array<int> Folder ids the caller may assign, re-indexed.
 */
function roleRightsAssignableFolderIds(array $accessibleIds, array $existingIds, array $excludedIds): array
{
    return array_values(array_diff(
        array_intersect(rolesScopeNormalizeIds($accessibleIds), rolesScopeNormalizeIds($existingIds)),
        rolesScopeNormalizeIds($excludedIds)
    ));
}

/**
 * Resolve the folders a role rights change writes to.
 *
 * Every explicitly selected folder must be assignable, otherwise the whole submission is
 * refused: a forged id must never be silently dropped from a request that otherwise succeeds.
 * Descendants reached by propagation are kept only when they are assignable too.
 *
 * @param array $selectedIds   Folder ids submitted by the caller.
 * @param array $descendantIds Descendant ids of the selected folders (empty without propagation).
 * @param array $assignableIds Folder ids the caller may assign.
 *
 * @return array<int>|null Target folder ids, or null when the selection is empty or out of scope.
 */
function roleRightsTargetFolders(array $selectedIds, array $descendantIds, array $assignableIds): ?array
{
    $assignable = array_fill_keys(rolesScopeNormalizeIds($assignableIds), true);
    $targets = [];
    foreach ($selectedIds as $folderId) {
        if (is_int($folderId) === false && (is_string($folderId) === false || ctype_digit($folderId) === false)) {
            return null;
        }
        if (isset($assignable[(int) $folderId]) === false) {
            return null;
        }
        $targets[(int) $folderId] = true;
    }
    if ($targets === []) {
        return null;
    }
    foreach (rolesScopeNormalizeIds($descendantIds) as $folderId) {
        if (isset($assignable[$folderId]) === true) {
            $targets[$folderId] = true;
        }
    }

    return array_keys($targets);
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

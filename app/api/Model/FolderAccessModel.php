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
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @version    API
 *
 * @file      FolderAccessModel.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

/**
 * TeamPass API folder access checks.
 *
 * Personal folder exclusion convention: only personal ROOT folders
 * (personal_folder = 1 AND parent_id = 0, title = owner user ID) are used as
 * exclusion anchors. Subfolders inherit personal_folder = 1 with an arbitrary
 * title and must never be treated as roots, otherwise they exclude themselves.
 */
class FolderAccessModel
{
    /**
     * @param mixed $folderIds
     * @return array<int>
     */
    public function normalizeFolderIds($folderIds): array
    {
        if (is_string($folderIds)) {
            $folderIds = explode(',', $folderIds);
        }

        if (is_array($folderIds) === false) {
            return [];
        }

        $normalized = [];
        foreach ($folderIds as $folderId) {
            if (is_array($folderId) === true || is_object($folderId) === true) {
                continue;
            }

            $id = (int) $folderId;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param mixed $itemIds
     * @return array<int>
     */
    public function normalizeItemIds($itemIds): array
    {
        if (is_string($itemIds)) {
            $itemIds = explode(',', $itemIds);
        }

        if (is_array($itemIds) === false) {
            return [];
        }

        $normalized = [];
        foreach ($itemIds as $itemId) {
            if (is_array($itemId) === true || is_object($itemId) === true) {
                continue;
            }

            $id = (int) $itemId;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        return array_values($normalized);
    }

    /**
     * Keep shared folders and folders under the current user's personal root.
     *
     * @param array<int|string> $folderIds
     * @return array<int>
     */
    public function filterFoldersForUser(array $folderIds, int $userId): array
    {
        $folderIds = $this->normalizeFolderIds($folderIds);
        if (empty($folderIds) === true || $userId <= 0) {
            return [];
        }

        $rows = DB::query(
            'SELECT nt.id
            FROM ' . prefixTable('nested_tree') . ' AS nt
            WHERE nt.id IN %li
            AND NOT EXISTS (
                SELECT 1
                FROM ' . prefixTable('nested_tree') . ' AS other_personal
                WHERE other_personal.personal_folder = 1
                AND other_personal.parent_id = 0
                AND other_personal.title <> %s
                AND nt.nleft >= other_personal.nleft
                AND nt.nright <= other_personal.nright
            )',
            $folderIds,
            (string) $userId
        );

        $allowedFolders = [];
        foreach ($rows as $row) {
            $allowedFolders[(int) $row['id']] = true;
        }

        return array_values(
            array_filter(
                $folderIds,
                static fn (int $folderId): bool => isset($allowedFolders[$folderId]) === true
            )
        );
    }

    /**
     * Returns true when the folder is not nested inside another user's personal root.
     *
     * @param int $folderId Folder to test
     * @param int $userId   Current user ID (their personal root is excluded from the deny list)
     * @return bool
     */
    public function isFolderInsideAllowedPersonalRoot(int $folderId, int $userId): bool
    {
        if ($folderId <= 0 || $userId <= 0) {
            return false;
        }

        $allowedCount = DB::queryFirstField(
            'SELECT COUNT(*)
            FROM ' . prefixTable('nested_tree') . ' AS nt
            WHERE nt.id = %i
            AND NOT EXISTS (
                SELECT 1
                FROM ' . prefixTable('nested_tree') . ' AS other_personal
                WHERE other_personal.personal_folder = 1
                AND other_personal.parent_id = 0
                AND other_personal.title <> %s
                AND nt.nleft >= other_personal.nleft
                AND nt.nright <= other_personal.nright
            )',
            $folderId,
            (string) $userId
        );

        return (int) $allowedCount > 0;
    }

    /**
     * Returns true when the user is allowed to write into the given folder.
     *
     * @param array $userData            JWT user data (id, folders_list, user_can_create_root_folder)
     * @param int   $folderId            Folder ID to check
     * @param bool  $allowCreateRootBypass When true, root-folder creators bypass the explicit-list check
     * @return bool
     */
    public function canUseFolder(array $userData, int $folderId, bool $allowCreateRootBypass = false): bool
    {
        if ($this->isFolderInsideAllowedPersonalRoot($folderId, (int) ($userData['id'] ?? 0)) === false) {
            return false;
        }

        $userFolders = $this->normalizeFolderIds($userData['folders_list'] ?? []);
        if (in_array($folderId, $userFolders, true) === true) {
            return true;
        }

        return $allowCreateRootBypass === true
            && (int) ($userData['user_can_create_root_folder'] ?? 0) === 1;
    }

    /**
     * Returns true when the user passes the global folder-management gate.
     *
     * This mirrors the gate used by FolderManager and the API create/update/delete
     * adapters. Folder-specific access, read-only state, personal-root protection
     * and per-operation API permissions are evaluated separately.
     *
     * @param array $userData JWT user data
     * @param bool $isPersonal Whether the folder/target belongs to the personal domain
     * @param bool $enableUserCanCreateFolders Current enable_user_can_create_folders setting
     * @return bool
     */
    public function hasFolderManagementPrivilege(
        array $userData,
        bool $isPersonal,
        bool $enableUserCanCreateFolders
    ): bool
    {
        return $isPersonal === true
            || (int) ($userData['is_admin'] ?? 0) === 1
            || (int) ($userData['is_manager'] ?? 0) === 1
            || (int) ($userData['user_can_manage_all_users'] ?? 0) === 1
            || $enableUserCanCreateFolders === true
            || (int) ($userData['user_can_create_root_folder'] ?? 0) === 1;
    }

    /**
     * Build the folder-management capabilities advertised to API clients.
     *
     * These flags describe source-folder eligibility only. In particular,
     * can_move_folder does not validate a future destination; updateFolder()
     * remains responsible for destination access, read-only, cycle, domain and
     * root checks when the mutation is attempted.
     *
     * @param array $userData JWT user data, including current API CRUD permissions
     * @param bool $isPersonal Whether the folder belongs to the personal domain
     * @param bool $isPersonalRoot Whether the folder is a protected personal root
     * @param bool $isReadOnly Whether the effective folder access type is R
     * @param bool $hasEffectiveAccess Whether the source folder is effectively accessible
     * @param bool $enableUserCanCreateFolders Current enable_user_can_create_folders setting
     * @return array{
     *     can_create_subfolder: bool,
     *     can_rename_folder: bool,
     *     can_move_folder: bool,
     *     can_delete_folder: bool
     * }
     */
    public function getFolderManagementCapabilities(
        array $userData,
        bool $isPersonal,
        bool $isPersonalRoot,
        bool $isReadOnly,
        bool $hasEffectiveAccess,
        bool $enableUserCanCreateFolders
    ): array
    {
        $canManage = $hasEffectiveAccess === true
            && $isReadOnly === false
            && $this->hasFolderManagementPrivilege(
                $userData,
                $isPersonal,
                $enableUserCanCreateFolders
            );
        $canMutateSource = $canManage === true && $isPersonalRoot === false;

        return [
            'can_create_subfolder' => $canManage === true
                && (int) ($userData['allowed_to_create'] ?? 0) === 1,
            'can_rename_folder' => $canMutateSource === true
                && (int) ($userData['allowed_to_update'] ?? 0) === 1,
            'can_move_folder' => $canMutateSource === true
                && (int) ($userData['allowed_to_update'] ?? 0) === 1,
            'can_delete_folder' => $canMutateSource === true
                && (int) ($userData['allowed_to_delete'] ?? 0) === 1,
        ];
    }

    /**
     * Returns true when the user can read the given item (folder access or restricted-items list).
     *
     * @param array $userData JWT user data (id, folders_list, restricted_items_list)
     * @param int   $folderId Folder that contains the item
     * @param int   $itemId   Item ID (used to check the restricted-items fallback)
     * @return bool
     */
    public function canAccessItemInFolder(array $userData, int $folderId, int $itemId): bool
    {
        if ($this->isFolderInsideAllowedPersonalRoot($folderId, (int) ($userData['id'] ?? 0)) === false) {
            return false;
        }

        $userFolders = $this->normalizeFolderIds($userData['folders_list'] ?? []);
        if (in_array($folderId, $userFolders, true) === true) {
            return true;
        }

        $restrictedItems = $this->normalizeItemIds($userData['restricted_items_list'] ?? []);

        return in_array($itemId, $restrictedItems, true) === true;
    }

    /**
     * Resolve the effective access level of a user on a folder.
     *
     * Mirrors the web resolution exactly (getRoleBasedAccess() in items.queries.php):
     * every role type defined on the folder is folded through evaluateFolderAccesLevel(),
     * so the LEAST permissive type wins (R > NDNE > {ND, NE} > W) as documented in
     * docs/features/rights.md. Roles of both sources (manual and AD/LDAP) are taken into
     * account, like the web session does.
     *
     * A direct per-user grant (users_groups) always yields full write access and overrides
     * a role-based restriction — same priority as identUser() in main.functions.php.
     *
     * @param int $folderId Folder to evaluate
     * @param int $userId   User to evaluate
     * @return array{type: string, create: bool, edit: bool, delete: bool}
     */
    public function getFolderAccessLevelForUser(int $folderId, int $userId): array
    {
        // Direct folder grants (groupes_visibles) are always writable
        $directGrant = DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('users_groups') . ' WHERE user_id = %i AND group_id = %i',
            $userId,
            $folderId
        );
        if ((int) $directGrant > 0) {
            return ['type' => 'W', 'create' => true, 'edit' => true, 'delete' => true];
        }

        // Get user's role IDs — both manual and AD/LDAP sourced, matching core.php
        $roleRows = DB::query(
            'SELECT role_id FROM ' . prefixTable('users_roles') . ' WHERE user_id = %i',
            $userId
        );
        $roleIds = array_map(static fn($r) => (int) $r['role_id'], $roleRows);

        // No role at all: nothing restricts the folder here. Visibility is decided
        // upstream by folders_list; treat it as full write (previous behaviour).
        if (empty($roleIds) === true) {
            return ['type' => 'W', 'create' => true, 'edit' => true, 'delete' => true];
        }

        // Fetch access types for this folder from user's roles
        $accessRows = DB::query(
            'SELECT DISTINCT type FROM ' . prefixTable('roles_values') .
            ' WHERE role_id IN %li AND folder_id = %i AND type IN ("W", "ND", "NE", "NDNE", "R")',
            $roleIds,
            $folderId
        );

        if (empty($accessRows) === true) {
            // No role defines this folder: same reasoning as above
            return ['type' => 'W', 'create' => true, 'edit' => true, 'delete' => true];
        }

        // Least permissive wins — shared implementation with the web
        $resolvedType = '';
        foreach ($accessRows as $row) {
            $resolvedType = evaluateFolderAccesLevel((string) $row['type'], $resolvedType);
        }

        switch ($resolvedType) {
            case 'ND':   return ['type' => 'ND',   'create' => true,  'edit' => true,  'delete' => false];
            case 'NE':   return ['type' => 'NE',   'create' => true,  'edit' => false, 'delete' => true];
            case 'NDNE': return ['type' => 'NDNE', 'create' => true,  'edit' => false, 'delete' => false];
            case 'R':    return ['type' => 'R',    'create' => false, 'edit' => false, 'delete' => false];
            default:     return ['type' => 'W',    'create' => true,  'edit' => true,  'delete' => true];
        }
    }

    /**
     * Returns true when the given folder is read-only for the user.
     *
     * Read-only means the resolved access level is R: no item may be created, edited
     * or deleted. ND/NE/NDNE are NOT read-only — they allow creation with a restriction
     * on edit and/or delete, which callers must check via getFolderAccessLevelForUser().
     *
     * @param int $folderId
     * @param int $userId
     * @return bool
     */
    public function isFolderReadOnlyForUser(int $folderId, int $userId): bool
    {
        return $this->getFolderAccessLevelForUser($folderId, $userId)['type'] === 'R';
    }

    /**
     * Returns true when the user may edit existing items in the folder (blocks NE/NDNE/R).
     *
     * @param int $folderId
     * @param int $userId
     * @return bool
     */
    public function canEditInFolder(int $folderId, int $userId): bool
    {
        return $this->getFolderAccessLevelForUser($folderId, $userId)['edit'];
    }

    /**
     * Returns true when the user may delete items in the folder (blocks ND/NDNE/R).
     *
     * @param int $folderId
     * @param int $userId
     * @return bool
     */
    public function canDeleteInFolder(int $folderId, int $userId): bool
    {
        return $this->getFolderAccessLevelForUser($folderId, $userId)['delete'];
    }

    /**
     * Returns a SQL fragment that excludes items located inside another user's personal folder tree.
     *
     * The returned string is designed to be appended to an existing WHERE clause.
     * $itemFolderColumn must be a qualified column reference (e.g. 'i.id_tree'); it is
     * validated against a strict allowlist before being interpolated into the query.
     * Fails closed (AND 1 = 0) on invalid input.
     *
     * @param string $itemFolderColumn Qualified column holding the folder ID (e.g. 'i.id_tree')
     * @param int    $userId           Current user ID
     * @return string
     */
    public function getItemFolderSqlConstraint(string $itemFolderColumn, int $userId): string
    {
        if (preg_match('/^[A-Za-z0-9_.]+$/', $itemFolderColumn) !== 1 || $userId <= 0) {
            return ' AND 1 = 0';
        }

        return ' AND NOT EXISTS (
            SELECT 1
            FROM ' . prefixTable('nested_tree') . ' AS nt_access
            INNER JOIN ' . prefixTable('nested_tree') . ' AS other_personal
                ON other_personal.personal_folder = 1
                AND other_personal.parent_id = 0
                AND nt_access.nleft >= other_personal.nleft
                AND nt_access.nright <= other_personal.nright
            WHERE nt_access.id = ' . $itemFolderColumn . '
            AND other_personal.title <> ' . DB::escape((string) $userId) . '
        )';
    }
}

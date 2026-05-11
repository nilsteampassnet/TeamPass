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
                AND nt_access.nleft >= other_personal.nleft
                AND nt_access.nright <= other_personal.nright
            WHERE nt_access.id = ' . $itemFolderColumn . '
            AND other_personal.title <> ' . DB::escape((string) $userId) . '
        )';
    }
}

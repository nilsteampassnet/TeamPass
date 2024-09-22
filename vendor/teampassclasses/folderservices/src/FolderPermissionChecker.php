<?php
namespace TeampassClasses\FolderServices;

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
 * @file      FolderPermissionChecker.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use DB;

class FolderPermissionChecker
{
    public function isUserAllowedToCreate(int $userId, int $parentId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }
        $accessibleFolders = $this->getUserAccessibleFolders($userId);
        return in_array($parentId, $accessibleFolders);
    }

    private function getUserAccessibleFolders(int $userId): array
    {
        return DB::queryFirstColumn('SELECT folder_id FROM accessible_folders WHERE user_id = %i', $userId);
    }
}

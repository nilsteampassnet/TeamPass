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
 * @file      FolderModel.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\Language\Language;

class FolderModel
{
    public function getFoldersInfo(array $foldersId): array
    {
        // Get folders
        $rows = DB::query(
            'SELECT id, title
            FROM ' . prefixTable('nested_tree') . '
            WHERE nlevel = %i',
            1
        );

        $ret = [];

        foreach ($rows as $row) {
			$isVisible = in_array((int) $row['id'], $foldersId);
            $childrens = $this->getFoldersChildren($row['id'], $foldersId);

            if ($isVisible || count($childrens) > 0) {
                array_push(
                    $ret,
                    [
                        'id' => (int) $row['id'],
                        'title' => $row['title'],
						'isVisible' => $isVisible,
                        'childrens' => $childrens
                    ]
                );
            }
        }

        return $ret;
    }

    private function getFoldersChildren(int $parentId, array $foldersId): array
    {
        $ret = [];
        $childrens = DB::query(
            'SELECT id, title
            FROM ' . prefixTable('nested_tree') . '
            WHERE parent_id = %i',
            $parentId
        );

        if ( count($childrens) > 0) {
            foreach ($childrens as $children) {
				$isVisible = in_array((int) $children['id'], $foldersId);
                $childs = $this->getFoldersChildren($children['id'], $foldersId);

                if (in_array((int) $children['id'], $foldersId) || count($childs) > 0) {
                    array_push(
                        $ret,
                        [
                            'id' => (int) $children['id'],
                            'title' => $children['title'],
							'isVisible' => $isVisible,
                            'childrens' => $childs
                        ]
                    );
                }
            }
        }

        return $ret;
    }

    public function createFolder(
        string $title,
        int $parent_id,
        int $complexity,
        int $duration,
        int $create_auth_without,
        int $edit_auth_without,
        string $icon,
        string $icon_selected,
        string $access_rights,
        int $is_admin,
        array $foldersId,
        int $is_manager,
        int $user_can_create_root_folder,
        int $user_can_manage_all_users,
        int $user_id,
        string $user_roles
    ): array
    {
        // Validate inputs
        include_once API_ROOT_PATH . '/../sources/main.functions.php';
        $data = [
            'title' => $title,
            'parent_id' => $parent_id,
            'complexity' => $complexity,
            'duration' => $duration,
            'create_auth_without' => $create_auth_without,
            'edit_auth_without' => $edit_auth_without,
            'icon' => $icon,
            'icon_selected' => $icon_selected,
            'access_rights' => $access_rights,
            'is_admin' => $is_admin,
            'foldersId' => json_encode($foldersId),
            'is_manager' => $is_manager,
            'user_can_create_root_folder' => $user_can_create_root_folder,
            'user_can_manage_all_users' => $user_can_manage_all_users,
            'user_id' => $user_id,
            'user_roles' => $user_roles,
        ];
        
        $filters = [
            'title' => 'trim|escape',
            'parent_id' => 'cast:integer',
            'complexity' => 'cast:integer',
            'duration' => 'cast:integer',
            'create_auth_without' => 'cast:integer',
            'edit_auth_without' => 'cast:integer',
            'icon' => 'trim|escape',
            'icon_selected' => 'trim|escape',
            'access_rights' => 'trim|escape',
            'is_admin' => 'cast:integer',
            'foldersId' => 'cast:array',
            'is_manager' => 'cast:integer',
            'user_can_create_root_folder' => 'cast:integer',
            'user_can_manage_all_users' => 'cast:integer',
            'user_id' => 'cast:integer',
            'user_roles' => 'trim|escape',
        ];
        
        $inputData = dataSanitizer(
            $data,
            $filters
        );
        
        // Extract inputs
        $title = $inputData['title'];
        $parent_id = $inputData['parent_id'];
        $complexity = $inputData['complexity'];
        $duration = isset($inputData['duration']) === true ? $inputData['duration'] : 0;
        $create_auth_without = isset($inputData['create_auth_without']) === true ? $inputData['create_auth_without'] : 0;
        $edit_auth_without = isset($inputData['edit_auth_without']) === true ? $inputData['edit_auth_without'] : 0;
        $icon = $inputData['icon'];
        $icon_selected = $inputData['icon_selected'];
        $access_rights = isset($inputData['access_rights']) === true ? $inputData['access_rights'] : 'W';
        $foldersId = $inputData['foldersId'];

        // Do checks
        if (
            in_array($complexity, [TP_PW_STRENGTH_1, TP_PW_STRENGTH_2, TP_PW_STRENGTH_3, TP_PW_STRENGTH_4, TP_PW_STRENGTH_5]) === false ||
            in_array($access_rights, ['R', 'W', 'NE', 'ND', 'NDNE']) === false
        ) {
            return [
                'error' => true,
                'error_header' => 'HTTP/1.1 422 Unprocessable Entity',
                'error_message' => 'Invalid parameters'
            ];}

        // Create folder
        require_once TEAMPASS_ROOT_PATH.'/sources/folders.class.php';
        $lang = new Language();
        $folderManager = new FolderManager($lang);
        $params = [
            'title' => (string) $title,
            'parent_id' => (int) $parent_id,
            'complexity' => (int) $complexity,
            'duration' => (int) $duration,
            'create_auth_without' => (int) $create_auth_without,
            'edit_auth_without' => (int) $edit_auth_without,
            'icon' => (string) $icon,
            'icon_selected' => (string) $icon_selected,
            'access_rights' => (string) $access_rights,
            'user_is_admin' => (int) $is_admin,
            'user_accessible_folders' => (array) $foldersId,
            'user_is_manager' => (int) $is_manager,
            'user_can_create_root_folder' => (int) $user_can_create_root_folder,
            'user_can_manage_all_users' => (int) $user_can_manage_all_users,
            'user_id' => (int) $user_id,
            'user_roles' => (string) $user_roles
        ];
        $creationStatus = $folderManager->createNewFolder($params);

        return $creationStatus;
    }
}
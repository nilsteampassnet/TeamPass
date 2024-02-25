<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @version    API
 *
 * @file      folderModel.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2024 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */
require_once API_ROOT_PATH . "/Model/Database.php";
use TeampassClasses\Language\Language;

class FolderModel extends Database
{
    public function getFoldersInfo(array $foldersId): array
    {
        $rows = $this->select( "SELECT id, title FROM " . prefixTable('nested_tree') . " WHERE nlevel=1" );

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
        $childrens = $this->select('SELECT id, title FROM ' . prefixTable('nested_tree') . ' WHERE parent_id=' . $parentId);

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
        $title = filter_var($title, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $parent_id = filter_var($parent_id, FILTER_SANITIZE_NUMBER_INT);
        $complexity = filter_var($complexity, FILTER_SANITIZE_NUMBER_INT);
        $duration = isset($duration) === true ? filter_var($duration, FILTER_SANITIZE_NUMBER_INT) : 0;
        $create_auth_without = isset($create_auth_without) === true ? filter_var($create_auth_without, FILTER_SANITIZE_NUMBER_INT) : 0;
        $edit_auth_without = isset($edit_auth_without) === true ? filter_var($edit_auth_without, FILTER_SANITIZE_NUMBER_INT) : 0;
        $icon = filter_var($icon, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $icon_selected = filter_var($icon_selected, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $access_rights = isset($access_rights) === true ? filter_var($access_rights, FILTER_SANITIZE_FULL_SPECIAL_CHARS) : 'W';

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
        /*
        require_once TEAMPASS_ROOT_PATH.'/sources/folders.functions.php';
        $creationStatus = createNewFolder(
            (string) $title,
            (int) $parent_id,
            (int) $complexity,
            (int) $duration,
            (int) $create_auth_without,
            (int) $edit_auth_without,
            (string) $icon,
            (string) $icon_selected,
            (string) $access_rights,
            (int) $is_admin,
            (array) $foldersId,
            (int) $is_manager,
            (int) $user_can_create_root_folder,
            (int) $user_can_manage_all_users,
            (int) $user_id,
            (string) $user_roles
        );
        */

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
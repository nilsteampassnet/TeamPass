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
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\ConfigManager\ConfigManager;

class FolderModel
{
    /**
     * Build the model error envelope consumed by FolderController.
     *
     * @param int    $status  HTTP status code
     * @param string $message Human-readable error detail
     * @return array{error: true, error_header: string, error_message: string}
     */
    private function apiError(int $status, string $message): array
    {
        return [
            'error' => true,
            'error_header' => 'HTTP/1.1 ' . $status . ' ' . (BaseController::HTTP_REASONS[$status] ?? 'Error'),
            'error_message' => $message,
        ];
    }

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
        string $user_roles,
        bool $private = false,
        int $pf_enabled = 0,
        string $user_login = ''
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

        $folderAccessModel = new FolderAccessModel();

        // Resolve personal vs shared target and the effective parent (§3.2).
        // personal_folder is NEVER accepted from the client — always derived.
        if ($private === true) {
            if ((int) $pf_enabled !== 1) {
                return $this->apiError(403, 'Personal folders are disabled for this user');
            }
            // Resolve the caller's personal root
            $personalRoot = DB::queryFirstRow(
                'SELECT id FROM ' . prefixTable('nested_tree') . '
                WHERE title = %s AND personal_folder = 1 AND parent_id = 0',
                (string) $user_id
            );
            if ($personalRoot === null) {
                return $this->apiError(403, 'No personal folder root found for this user');
            }
            if ($parent_id <= 0) {
                // No parent given: create under the personal root
                $parent_id = (int) $personalRoot['id'];
            } else {
                // A parent was given: it must be inside the caller's own personal tree
                $parentRow = DB::queryFirstRow(
                    'SELECT personal_folder FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
                    $parent_id
                );
                if ($parentRow === null
                    || (int) $parentRow['personal_folder'] !== 1
                    || $folderAccessModel->isFolderInsideAllowedPersonalRoot($parent_id, (int) $user_id) === false
                ) {
                    return $this->apiError(422, 'The provided parent folder is not inside your personal folder');
                }
            }
            $isPersonal = 1;
        } else {
            // Parent-derived: a parent inside the caller's personal tree yields a
            // personal folder (C2 fix)
            $isPersonal = 0;
            if ($parent_id > 0) {
                $parentRow = DB::queryFirstRow(
                    'SELECT personal_folder FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
                    $parent_id
                );
                $isPersonal = ($parentRow !== null && (int) $parentRow['personal_folder'] === 1) ? 1 : 0;
            }
        }

        // Parent guards (C3) — skip for root (parent_id = 0)
        if ($parent_id > 0) {
            if ($folderAccessModel->isFolderInsideAllowedPersonalRoot($parent_id, (int) $user_id) === false) {
                return $this->apiError(403, 'Access denied: parent folder is not accessible');
            }
            if ($folderAccessModel->isFolderReadOnlyForUser($parent_id, (int) $user_id) === true) {
                return $this->apiError(403, 'Access denied: parent folder is read-only');
            }
        }

        // Validate parameters. A valid complexity (password strength) is required only
        // for shared targets; access_rights must always be valid.
        if (in_array($access_rights, ['R', 'W', 'NE', 'ND', 'NDNE'], true) === false) {
            return $this->apiError(422, 'Invalid parameters');
        }
        if ($isPersonal === 0
            && in_array($complexity, [TP_PW_STRENGTH_1, TP_PW_STRENGTH_2, TP_PW_STRENGTH_3, TP_PW_STRENGTH_4, TP_PW_STRENGTH_5], true) === false
        ) {
            return $this->apiError(422, 'Invalid parameters');
        }

        // Create folder
        require_once API_ROOT_PATH . '/../sources/folders.class.php';
        $lang = new Language();
        $folderManager = new FolderManager($lang);
        $params = [
            'title' => (string) $title,
            'parent_id' => (int) $parent_id,
            'personal_folder' => (int) $isPersonal,
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
            'user_roles' => (string) $user_roles,
        ];
        // Full lifecycle options (C1) — mirrors web add_folder so the tree is rebuilt,
        // roles_values populated, and same-role users' cache refreshed.
        $options = [
            'rebuildFolderTree' => true,
            'setFolderCategories' => false,
            'manageFolderPermissions' => true,
            'copyCustomFieldsCategories' => false,
            'refreshCacheForUsersWithSimilarRoles' => true,
        ];
        $creationStatus = $folderManager->createNewFolder($params, $options);

        // Map FolderManager result → API error envelope
        if (($creationStatus['error'] ?? false) === true) {
            if (isset($creationStatus['message']) && (string) $creationStatus['message'] !== '') {
                // Validation error (numeric title, duplicate, complexity ceiling…)
                return $this->apiError(422, (string) $creationStatus['message']);
            }
            // canCreateFolder() denied without a message → permission error
            return $this->apiError(403, 'Access denied: insufficient permissions to create a folder');
        }

        $newId = (int) $creationStatus['newId'];

        // Emit WebSocket event (C4) — excludeUserId = null: the API caller's UI did not
        // perform the change locally, so nobody is excluded.
        emitFolderEvent('created', $newId, (string) $title, (string) $user_login, (int) $parent_id, null);

        return ['error' => false, 'newId' => $newId];
    }

    /**
     * Update an existing folder (API adapter).
     *
     * Sanitizes the partial payload, performs all access/business validation
     * (FolderAccessModel + parity with the web update_folder handler), then
     * delegates the write to FolderManager::updateFolder().
     *
     * @param array $data     Request payload (id required; other fields optional)
     * @param array $userData JWT user data
     * @return array Success payload or error envelope (error_header/error_message)
     */
    public function updateFolder(array $data, array $userData): array
    {
        include_once API_ROOT_PATH . '/../sources/main.functions.php';
        require_once API_ROOT_PATH . '/../sources/folders.class.php';

        $configManager = new ConfigManager();
        $SETTINGS = $configManager->getAllSettings();

        $userId = (int) ($userData['id'] ?? 0);

        $folderId = (int) ($data['id'] ?? 0);
        if ($folderId <= 0) {
            return $this->apiError(400, 'Folder id is mandatory');
        }

        $folder = DB::queryFirstRow(
            'SELECT * FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
            $folderId
        );
        if ($folder === null) {
            return $this->apiError(404, 'Folder not found');
        }

        $folderAccessModel = new FolderAccessModel();

        // Access checks
        if ($folderAccessModel->canUseFolder($userData, $folderId) === false) {
            return $this->apiError(403, 'Access denied to this folder');
        }
        if ($folderAccessModel->isFolderReadOnlyForUser($folderId, $userId) === true) {
            return $this->apiError(403, 'Access denied: folder is read-only');
        }

        // Sanitize the provided (partial) fields
        $updatableFilters = [
            'title' => 'trim|escape',
            'parent_id' => 'cast:integer',
            'complexity' => 'cast:integer',
            'duration' => 'cast:integer',
            'create_auth_without' => 'cast:integer',
            'edit_auth_without' => 'cast:integer',
            'icon' => 'trim|escape',
            'icon_selected' => 'trim|escape',
        ];
        $rawProvided = [];
        $filters = [];
        foreach ($updatableFilters as $field => $filter) {
            if (array_key_exists($field, $data) === true) {
                $rawProvided[$field] = $data[$field];
                $filters[$field] = $filter;
            }
        }
        $provided = empty($rawProvided) === false ? dataSanitizer($rawProvided, $filters) : [];

        $titleProvided = array_key_exists('title', $provided);
        $parentProvided = array_key_exists('parent_id', $provided);
        $complexityProvided = array_key_exists('complexity', $provided);

        $newParentId = $parentProvided === true ? (int) $provided['parent_id'] : (int) $folder['parent_id'];
        $parentChanged = $newParentId !== (int) $folder['parent_id'];
        $titleChanged = $titleProvided === true && (string) $provided['title'] !== (string) $folder['title'];

        // Personal root protection — cannot be renamed or moved
        $isPersonalRoot = (int) $folder['parent_id'] === 0
            && (int) $folder['personal_folder'] === 1
            && (string) $folder['title'] === (string) $userId;
        if ($isPersonalRoot === true && ($titleChanged === true || $parentChanged === true)) {
            return $this->apiError(403, 'A personal root folder cannot be renamed or moved');
        }

        // Determine the personal/shared target
        if ($parentChanged === true) {
            if ($newParentId === $folderId) {
                return $this->apiError(422, 'A folder cannot be moved into itself');
            }
            $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            if ($newParentId !== 0) {
                // New parent must not be a descendant of the folder (the web omits
                // this check and would corrupt the tree)
                $descendants = array_map('intval', $tree->getDescendants($folderId, false, false, true));
                if (in_array($newParentId, $descendants, true) === true) {
                    return $this->apiError(422, 'A folder cannot be moved into one of its descendants');
                }
                if ($folderAccessModel->canUseFolder($userData, $newParentId, true) === false) {
                    return $this->apiError(403, 'Access denied: target parent folder is not accessible');
                }
                if ($folderAccessModel->isFolderReadOnlyForUser($newParentId, $userId) === true) {
                    return $this->apiError(403, 'Access denied: target parent folder is read-only');
                }
                $parentRow = DB::queryFirstRow(
                    'SELECT personal_folder FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
                    $newParentId
                );
                $isPersonal = ($parentRow !== null && (int) $parentRow['personal_folder'] === 1) ? 1 : 0;
            } else {
                // Move to root
                if ((int) ($userData['user_can_create_root_folder'] ?? 0) !== 1
                    && (int) ($userData['is_admin'] ?? 0) !== 1
                ) {
                    return $this->apiError(403, 'Access denied: cannot move folder to root');
                }
                $isPersonal = 0;
            }

            // Cross-domain (personal ↔ shared) moves are not supported in v1
            if ((int) $folder['personal_folder'] !== (int) $isPersonal) {
                return $this->apiError(422, 'Moving a folder between personal and shared trees is not supported');
            }
        } else {
            $isPersonal = (int) $folder['personal_folder'];
        }

        // Numeric title
        if ($titleProvided === true && is_numeric($provided['title']) === true) {
            return $this->apiError(422, 'Folder name cannot be numeric');
        }

        // Duplicate title (shared scope) on rename
        if ($titleChanged === true
            && isset($SETTINGS['duplicate_folder']) === true
            && (int) $SETTINGS['duplicate_folder'] === 0
        ) {
            DB::query(
                'SELECT id FROM ' . prefixTable('nested_tree') . '
                WHERE title = %s AND personal_folder = 0 AND id != %i',
                (string) $provided['title'],
                $folderId
            );
            if (DB::count() !== 0) {
                return $this->apiError(422, 'A folder with this name already exists');
            }
        }

        // Complexity ceiling (shared targets only, non-privileged users)
        if ($complexityProvided === true
            && $isPersonal === 0
            && (int) ($userData['is_admin'] ?? 0) !== 1
            && ((int) ($userData['is_manager'] ?? 0) !== 1
                || (int) ($userData['user_can_manage_all_users'] ?? 0) !== 1)
        ) {
            $parentComplexity = DB::queryFirstRow(
                'SELECT valeur FROM ' . prefixTable('misc') . '
                WHERE intitule = %i AND type = %s',
                $newParentId,
                'complex'
            );
            if ($parentComplexity !== null && (int) $provided['complexity'] < (int) $parentComplexity['valeur']) {
                return $this->apiError(422, 'Complexity level is lower than the parent folder requirement');
            }
        }

        // Global permission gate
        if (!(
            $isPersonal === 1
            || (int) ($userData['is_admin'] ?? 0) === 1
            || (int) ($userData['is_manager'] ?? 0) === 1
            || (int) ($userData['user_can_manage_all_users'] ?? 0) === 1
            || (isset($SETTINGS['enable_user_can_create_folders']) === true && (int) $SETTINGS['enable_user_can_create_folders'] === 1)
            || (int) ($userData['user_can_create_root_folder'] ?? 0) === 1
        )) {
            return $this->apiError(403, 'Access denied: insufficient permissions to update this folder');
        }

        // Build FolderManager params — only provided fields + the derived personal_folder
        $params = [
            'id' => $folderId,
            'personal_folder' => (int) $isPersonal,
            'parent_changed' => $parentChanged,
            'user_id' => $userId,
            'user_login' => (string) ($userData['username'] ?? ''),
            'user_roles' => (string) ($userData['roles'] ?? ''),
        ];
        if ($parentProvided === true) {
            $params['parent_id'] = $newParentId;
        }
        foreach (['title', 'complexity', 'duration', 'create_auth_without', 'edit_auth_without', 'icon', 'icon_selected'] as $field) {
            if (array_key_exists($field, $provided) === true) {
                $params[$field] = $provided[$field];
            }
        }

        $lang = new Language();
        $folderManager = new FolderManager($lang);
        $result = $folderManager->updateFolder($params);

        if ($result['error'] === true) {
            return $this->apiError(500, (string) ($result['message'] ?? 'Folder update failed'));
        }

        return [
            'error' => false,
            'message' => 'Folder updated',
            'id' => $folderId,
        ];
    }

    /**
     * Soft-delete a folder and its descendants (API adapter).
     *
     * Performs access/business validation then delegates the recycle-bin write
     * to FolderManager::deleteFolders().
     *
     * @param int   $folderId Folder to delete
     * @param array $userData JWT user data
     * @return array Success payload or error envelope (error_header/error_message)
     */
    public function deleteFolder(int $folderId, array $userData): array
    {
        include_once API_ROOT_PATH . '/../sources/main.functions.php';
        require_once API_ROOT_PATH . '/../sources/folders.class.php';

        $userId = (int) ($userData['id'] ?? 0);

        if ($folderId <= 0) {
            return $this->apiError(400, 'Folder id is mandatory and must be greater than 0');
        }

        $folder = DB::queryFirstRow(
            'SELECT * FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
            $folderId
        );
        if ($folder === null) {
            return $this->apiError(404, 'Folder not found');
        }

        $folderAccessModel = new FolderAccessModel();

        if ($folderAccessModel->canUseFolder($userData, $folderId) === false) {
            return $this->apiError(403, 'Access denied to this folder');
        }
        if ($folderAccessModel->isFolderReadOnlyForUser($folderId, $userId) === true) {
            return $this->apiError(403, 'Access denied: folder is read-only');
        }

        // Personal root protection — cannot be deleted
        if ((int) $folder['parent_id'] === 0 && (int) $folder['personal_folder'] === 1) {
            return $this->apiError(403, 'A personal root folder cannot be deleted');
        }

        $configManager = new ConfigManager();
        $SETTINGS = $configManager->getAllSettings();

        $isPersonal = (int) $folder['personal_folder'] === 1;

        // Global permission gate — same expression as the web delete handler
        if (!(
            $isPersonal === true
            || (int) ($userData['is_admin'] ?? 0) === 1
            || (int) ($userData['is_manager'] ?? 0) === 1
            || (int) ($userData['user_can_manage_all_users'] ?? 0) === 1
            || (isset($SETTINGS['enable_user_can_create_folders']) === true && (int) $SETTINGS['enable_user_can_create_folders'] === 1)
            || (int) ($userData['user_can_create_root_folder'] ?? 0) === 1
        )) {
            return $this->apiError(403, 'Access denied: insufficient permissions to delete this folder');
        }

        $lang = new Language();
        $folderManager = new FolderManager($lang);
        $result = $folderManager->deleteFolders(
            [$folderId],
            [
                'user_id' => $userId,
                'user_login' => (string) ($userData['username'] ?? ''),
                'SETTINGS' => $SETTINGS,
            ]
        );

        if ($result['error'] === true) {
            return $this->apiError(500, (string) ($result['message'] ?? 'Folder deletion failed'));
        }

        return [
            'error' => false,
            'message' => 'Folder deleted',
            'deleted_folders' => $result['deleted_folders'] ?? [],
            'deleted_items_count' => $result['deleted_items_count'] ?? 0,
        ];
    }
}
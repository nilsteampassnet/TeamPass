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
 * @file      FolderServices.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\FolderServices\FolderCreationService;
use TeampassClasses\FolderServices\FolderPermissionChecker;
use TeampassClasses\FolderServices\FolderComplexityService;
use TeampassClasses\FolderServices\FolderCacheUpdater;
use DB;

class FolderManager
{
    private $lang;
    private $settings;
    private $folderCreationService;
    private $permissionChecker;
    private $folderComplexityService;
    private $folderCacheUpdater;

    /**
     * Constructor
     */
    public function __construct($lang)
    {
        $configManager = new ConfigManager();
        $this->settings = $configManager->getAllSettings();
        $this->lang = $lang;
        $this->folderCreationService = new FolderCreationService($lang, $this->settings);
        $this->permissionChecker = new FolderPermissionChecker();
        $this->folderComplexityService = new FolderComplexityService();
        $this->folderCacheUpdater = new FolderCacheUpdater();
    }

    /**
     * Create a new folder in the system
     *
     * @param array $params Array containing folder creation parameters
     * @return array An array containing the error status and the new folder ID if successful
     */
    public function createNewFolder(array $params): array
    {
        // Validate parameters
        $validationResult = $this->folderCreationService->validateFolderParameters($params);
        if ($validationResult['error']) {
            return $this->errorResponse($validationResult['message']);
        }

        // Check if user has permissions to create the folder
        if (!$this->permissionChecker->isUserAllowedToCreate($params['user_id'], $params['parent_id'], $params['user_is_admin'])) {
            return $this->errorResponse($this->lang->get('error_folder_not_allowed_for_this_user'));
        }

        // Check complexity level compared to the parent folder
        if (!$this->folderComplexityService->checkComplexityLevel($params['parent_id'], $params['complexity'])) {
            return $this->errorResponse($this->lang->get('error_complexity_too_low'));
        }

        // Create the folder in the database
        $newFolderId = $this->folderCreationService->createFolder($params);

        // Finalize folder creation by updating cache, adding complexities, etc.
        $this->folderCreationService->finalizeCreation($newFolderId, $params);

        // Rebuild the folder tree structure
        $this->rebuildFolderTree($params['user_is_admin'], $params['title'], $params['parent_id'], $params['personal_folder'], $params['user_id'], $newFolderId);

        return ['error' => false, 'newId' => $newFolderId];
    }

    /**
     * Generates a standardized error response
     *
     * @param string $message The error message to be returned
     * @param mixed $newIdSuffix Optional additional information (e.g., ID suffix)
     * @return array The structured error response
     */
    private function errorResponse(string $message, $newIdSuffix = null): array
    {
        return [
            'error' => true,
            'message' => $message,
            'newId' => $newIdSuffix !== null ? (string) $newIdSuffix : null,
        ];
    }

    /**
     * Rebuilds the folder tree after a new folder is created, and updates session data.
     *
     * @param bool $user_is_admin Indicates if the user is an admin
     * @param string $title The title of the new folder
     * @param int $parent_id The ID of the parent folder
     * @param int $isPersonal Indicates if the folder is personal
     * @param int $user_id The ID of the user creating the folder
     * @param int $newId The ID of the newly created folder
     */
    private function rebuildFolderTree($user_is_admin, $title, $parent_id, $isPersonal, $user_id, $newId)
    {
        // Initialize the NestedTree instance for the folder structure
        $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
        $tree->rebuild();

        // Update session variables based on whether the folder is personal or accessible
        $session = SessionManager::getSession();
        $sessionKey = $isPersonal ? 'user-personal_folders' : 'user-accessible_folders';
        SessionManager::addRemoveFromSessionArray($sessionKey, [$newId], 'add');

        // If the user is not an admin, update the user's folder cache
        if (!$user_is_admin) {
            $this->updateUserFolderCache($tree, $title, $parent_id, $isPersonal, $user_id, $newId);
        }
    }

    /**
     * Updates the folder cache for a user with the new folder data.
     *
     * @param NestedTree $tree The current folder tree
     * @param string $title The title of the new folder
     * @param int $parent_id The ID of the parent folder
     * @param int $isPersonal Indicates if the folder is personal
     * @param int $user_id The ID of the user
     * @param int $newId The ID of the new folder
     */
    private function updateUserFolderCache($tree, $title, $parent_id, $isPersonal, $user_id, $newId)
    {
        // Construct the path for the new folder
        $path = '';
        $treePath = $tree->getPath(0, false);

        foreach ($treePath as $folder) {
            $path .= empty($path) ? $folder->title : '/' . $folder->title;
        }

        // Prepare the folder data to be cached
        $newFolderData = [
            "path" => $path,
            "id" => $newId,
            "level" => count($treePath),
            "title" => $title,
            "disabled" => 0,
            "parent_id" => $parent_id,
            "perso" => $isPersonal,
            "is_visible_active" => 0,
        ];

        // Retrieve the existing cache data for the user
        $cacheTree = DB::queryFirstRow('SELECT increment_id, folders, visible_folders FROM ' . prefixTable('cache_tree') . ' WHERE user_id = %i', (int)$user_id);

        if (empty($cacheTree)) {
            // Insert a new cache record if none exists
            DB::insert(prefixTable('cache_tree'), [
                'user_id' => $user_id,
                'folders' => json_encode([$newId]),
                'visible_folders' => json_encode([$newFolderData]),
                'timestamp' => time(),
                'data' => '[{}]',
            ]);
        } else {
            // Update the existing cache record with the new folder data
            $folders = json_decode($cacheTree['folders'] ?? '[]', true);
            $visibleFolders = json_decode($cacheTree['visible_folders'] ?? '[]', true);

            $folders[] = $newId;
            $visibleFolders[] = $newFolderData;

            DB::update(prefixTable('cache_tree'), [
                'folders' => json_encode($folders),
                'visible_folders' => json_encode($visibleFolders),
                'timestamp' => time(),
            ], 'increment_id = %i', (int)$cacheTree['increment_id']);
        }
    }
}

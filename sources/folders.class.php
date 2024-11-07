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
 * @file      folders.class.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\ConfigManager\ConfigManager;

class FolderManager
{
    private $lang;
    private $settings;

    /**
     * Constructor
     */
    public function __construct($lang)
    {
        $this->lang = $lang;
        $this->loadSettings();
    }

    /**
     * Load settings
     */
    private function loadSettings()
    {
        // Load config if $SETTINGS not defined
        $configManager = new ConfigManager();
        $this->settings = $configManager->getAllSettings();
    }

    /**
     * Create a new folder
     *
     * @param array $params
     * @return array
     */
    public function createNewFolder(array $params): array
    {
        // Décomposer les paramètres pour une meilleure lisibilité
        extract($params);

        if ($this->isTitleNumeric($title)) {
            return $this->errorResponse($this->lang->get('error_only_numbers_in_folder_name'));
        }

        if (!$this->isParentFolderAllowed($parent_id, $user_accessible_folders, $user_is_admin)) {
            return $this->errorResponse($this->lang->get('error_folder_not_allowed_for_this_user'));
        }

        if (!$this->checkDuplicateFolderAllowed($title) && $personal_folder == 0) {
            return $this->errorResponse($this->lang->get('error_group_exist'));
        }

        $parentFolderData = $this->getParentFolderData($parent_id);

        $parentComplexity = $this->checkComplexityLevel($parentFolderData, $complexity, $parent_id);
        if (isset($parentComplexity ['error']) && $parentComplexity['error'] === true) {
            return $this->errorResponse($this->lang->get('error_folder_complexity_lower_than_top_folder') . " [<b>{$this->settings['TP_PW_COMPLEXITY'][$parentComplexity['valeur']][1]}</b>]");
        }

        return $this->createFolder($params, array_merge($parentFolderData, $parentComplexity));
    }

    /**
     * Check if title is numeric
     *
     * @param string $title
     * @return boolean
     */
    private function isTitleNumeric($title)
    {
        return is_numeric($title);
    }

    /**
     * Check if parent folder is allowed
     *
     * @param integer $parent_id
     * @param array $user_accessible_folders
     * @param boolean $user_is_admin
     * @return boolean
     */
    private function isParentFolderAllowed($parent_id, $user_accessible_folders, $user_is_admin)
    {
        if (in_array($parent_id, $user_accessible_folders) === false
            && (int) $user_is_admin !== 1
        ) {
            return false;
        }
        return true;
    }

    /**
     * Check if duplicate folder is allowed
     *
     * @param string $title
     * @return boolean
     */
    private function checkDuplicateFolderAllowed($title)
    {
        if (
            isset($this->settings['duplicate_folder']) === true
            && (int) $this->settings['duplicate_folder'] === 0
        ) {
            DB::query(
                'SELECT *
                FROM ' . prefixTable('nested_tree') . '
                WHERE title = %s AND personal_folder = 0',
                $title
            );
            $counter = DB::count();
            if ($counter !== 0) {
                return false;
            }
            return true;
        }
        return true;
    }

    /**
     * Get parent folder data
     *
     * @param integer $parent_id
     * @return array
     */
    private function getParentFolderData($parent_id)
    {
        //check if parent folder is personal
        $data = DB::queryfirstrow(
            'SELECT personal_folder, bloquer_creation, bloquer_modification
            FROM ' . prefixTable('nested_tree') . '
            WHERE id = %i',
            $parent_id
        );

        // inherit from parent the specific settings it has
        if (DB::count() > 0) {
            $parentBloquerCreation = $data['bloquer_creation'];
            $parentBloquerModification = $data['bloquer_modification'];
        } else {
            $parentBloquerCreation = 0;
            $parentBloquerModification = 0;
        }

        return [
            'isPersonal' => null !== $data['personal_folder'] ? $data['personal_folder'] : 0,
            'parentBloquerCreation' => $parentBloquerCreation,
            'parentBloquerModification' => $parentBloquerModification,
        ];
    }

    /**
     * Check complexity level
     *
     * @param array $data
     * @param integer $complexity
     * @param integer $parent_id
     * @return array|boolean
     */
    private function checkComplexityLevel(
        $data,
        $complexity,
        $parent_id
    )
    {
        if (isset($data) === false || (int) $data['isPersonal'] === 0) {    
            // get complexity level for this folder
            $data = DB::queryfirstrow(
                'SELECT valeur
                FROM ' . prefixTable('misc') . '
                WHERE intitule = %i AND type = %s',
                $parent_id,
                'complex'
            );
            if (isset($data['valeur']) === true && intval($complexity) < intval($data['valeur'])) {
                return [
                    'error' => true,
                ];
            }
        }

        return [
            'parent_complexity' => isset($data['valeur']) === true ? $data['valeur'] : 0,
        ];
    }

    /**
     * Creates a new folder in the system, applying necessary settings, permissions, 
     * cache updates, and user role management.
     *
     * @param array $params - Parameters for folder creation (e.g., title, icon, etc.)
     * @param array $parentFolderData - Parent folder data (e.g., ID, permissions)
     * @return array - Returns an array indicating success or failure
     */
    private function createFolder($params, $parentFolderData)
    {
        extract($params);
        extract($parentFolderData);

        if ($this->canCreateFolder($isPersonal, $user_is_admin, $user_is_manager, $user_can_manage_all_users, $user_can_create_root_folder)) {
            $newId = $this->insertFolder($params, $parentFolderData);
            $this->addComplexity($newId, $complexity);
            $this->setFolderCategories($newId);
            $this->updateTimestamp();
            $this->rebuildFolderTree($user_is_admin, $title, $parent_id, $isPersonal, $user_id, $newId);
            $this->manageFolderPermissions($parent_id, $newId, $user_roles, $access_rights, $user_is_admin);
            $this->copyCustomFieldsCategories($parent_id, $newId);
            $this->refreshCacheForUsersWithSimilarRoles($user_roles);

            return ['error' => false, 'newId' => $newId];
        } else {
            return ['error' => true, 'newId' => null];
        }
    }

    /**
     * Checks if the user has the permissions to create a folder.
     */
    private function canCreateFolder($isPersonal, $user_is_admin, $user_is_manager, $user_can_manage_all_users, $user_can_create_root_folder)
    {
        return (int)$isPersonal === 1 ||
            (int)$user_is_admin === 1 ||
            ((int)$user_is_manager === 1 || (int)$user_can_manage_all_users === 1) ||
            ($this->settings['enable_user_can_create_folders'] ?? false) ||
            ((int)$user_can_create_root_folder === 1);
    }

    /**
     * Inserts a new folder into the database and returns the new folder ID.
     */
    private function insertFolder($params, $parentFolderData)
    {
        DB::insert(prefixTable('nested_tree'), [
            'parent_id' => $params['parent_id'],
            'title' => $params['title'],
            'personal_folder' => $params['personal_folder'] ?? 0,
            'renewal_period' => $params['duration'] ?? 0,
            'bloquer_creation' => $params['create_auth_without'] ?? $parentFolderData['parentBloquerCreation'],
            'bloquer_modification' => $params['edit_auth_without'] ?? $parentFolderData['parentBloquerModification'],
            'fa_icon' => empty($params['icon']) ? TP_DEFAULT_ICON : $params['icon'],
            'fa_icon_selected' => empty($params['icon_selected']) ? TP_DEFAULT_ICON_SELECTED : $params['icon_selected'],
            'categories' => '',
        ]);

        return DB::insertId();
    }

    /**
     * Adds complexity settings for the newly created folder.
     */
    private function addComplexity($folderId, $complexity)
    {
        DB::insert(prefixTable('misc'), [
            'type' => 'complex',
            'intitule' => $folderId,
            'valeur' => $complexity,
            'created_at' => time(),
        ]);
    }

    /**
     * Ensures that folder categories are set.
     */
    private function setFolderCategories($folderId)
    {
        handleFoldersCategories([$folderId]);
    }

    /**
     * Updates the last folder change timestamp.
     */
    private function updateTimestamp()
    {
        DB::update(prefixTable('misc'), [
            'valeur' => time(),
            'updated_at' => time(),
        ], 'type = %s AND intitule = %s', 'timestamp', 'last_folder_change');
    }

    /**
     * Rebuilds the folder tree and updates the cache for non-admin users.
     */
    private function rebuildFolderTree($user_is_admin, $title, $parent_id, $isPersonal, $user_id, $newId)
    {
        $session = SessionManager::getSession();
        $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
        $tree->rebuild();
        
        // Update session visible flolders
        $sess_key = $isPersonal ? 'user-personal_folders' : 'user-accessible_folders';
        SessionManager::addRemoveFromSessionArray($sess_key, [$newId], 'add');

        if ($user_is_admin === 0) {
            $this->updateUserFolderCache($tree, $title, $parent_id, $isPersonal, $user_id, $newId);
        }
    }

    /**
     * Updates the user folder cache for non-admin users.
     */
    private function updateUserFolderCache($tree, $title, $parent_id, $isPersonal, $user_id, $newId)
    {
        $path = '';
        $tree_path = $tree->getPath(0, false);
        foreach ($tree_path as $fld) {
            $path .= empty($path) ? $fld->title : '/' . $fld->title;
        }

        $new_json = [
            "path" => $path,
            "id" => $newId,
            "level" => count($tree_path),
            "title" => $title,
            "disabled" => 0,
            "parent_id" => $parent_id,
            "perso" => $isPersonal,
            "is_visible_active" => 0,
        ];

        $cache_tree = DB::queryFirstRow('SELECT increment_id, folders, visible_folders FROM ' . prefixTable('cache_tree') . ' WHERE user_id = %i', (int)$user_id);

        if (empty($cache_tree)) {
            DB::insert(prefixTable('cache_tree'), [
                'user_id' => $user_id,
                'folders' => json_encode($newId),
                'visible_folders' => json_encode($new_json),
                'timestamp' => time(),
                'data' => '[{}]',
            ]);
        } else {
            $folders = json_decode($cache_tree['folders'] ?? '[]', true);
            $visible_folders = json_decode($cache_tree['visible_folders'] ?? '[]', true);
            $folders[] = $newId;
            $visible_folders[] = $new_json;

            DB::update(prefixTable('cache_tree'), [
                'folders' => json_encode($folders),
                'visible_folders' => json_encode($visible_folders),
                'timestamp' => time(),
            ], 'increment_id = %i', (int)$cache_tree['increment_id']);
        }
    }

    /**
     * Manages folder permissions based on user roles or parent folder settings.
     */
    private function manageFolderPermissions($parent_id, $newId, $user_roles, $access_rights, $user_is_admin)
    {
        if ($this->settings['subfolder_rights_as_parent'] ?? false) {
            $rows = DB::query('SELECT role_id, type FROM ' . prefixTable('roles_values') . ' WHERE folder_id = %i', $parent_id);
            foreach ($rows as $record) {
                DB::insert(prefixTable('roles_values'), [
                    'role_id' => $record['role_id'],
                    'folder_id' => $newId,
                    'type' => $record['type'],
                ]);
            }
        } elseif ((int)$user_is_admin !== 1) {
            foreach (array_unique(explode(';', $user_roles)) as $role) {
                if (!empty($role)) {
                    DB::insert(prefixTable('roles_values'), [
                        'role_id' => $role,
                        'folder_id' => $newId,
                        'type' => $access_rights,
                    ]);
                }
            }
        }
    }

    /**
     * Copies custom field categories from the parent folder to the newly created folder.
     */
    private function copyCustomFieldsCategories($parent_id, $newId)
    {
        $rows = DB::query('SELECT id_category FROM ' . prefixTable('categories_folders') . ' WHERE id_folder = %i', $parent_id);
        foreach ($rows as $record) {
            DB::insert(prefixTable('categories_folders'), [
                'id_category' => $record['id_category'],
                'id_folder' => $newId,
            ]);
        }
    }

    /**
     * Refresh the cache for users with similar roles to the current user.
     */
    private function refreshCacheForUsersWithSimilarRoles($user_roles)
    {
        $usersWithSimilarRoles = getUsersWithRoles(explode(";", $user_roles));
        foreach ($usersWithSimilarRoles as $user) {

            // Arguments field
            $arguments = json_encode([
                'user_id' => (int) $user,
            ], JSON_HEX_QUOT | JSON_HEX_TAG);

            // Search for existing job
            $count = DB::queryFirstRow(
                'SELECT COUNT(*) AS count
                FROM ' . prefixTable('background_tasks') . '
                WHERE is_in_progress = %i AND process_type = %s AND arguments = %s',
                0,
                'user_build_cache_tree',
                $arguments
            )['count'];

            // Don't insert duplicates
            if ($count > 0)
                continue;

            // Insert new background task
            DB::insert(
                prefixTable('background_tasks'),
                array(
                    'created_at' => time(),
                    'process_type' => 'user_build_cache_tree',
                    'arguments' => $arguments,
                    'updated_at' => '',
                    'finished_at' => '',
                    'output' => '',
                )
            );
        }
    }

    /**
     * Returns an error response.
     */
    private function errorResponse($message, $newIdSuffix = "")
    {
        return [
            'error' => true,
            'message' => $message,
            'newId' => '' . $newIdSuffix,
        ];
    }
}
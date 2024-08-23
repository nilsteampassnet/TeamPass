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
        try {
            include_once TEAMPASS_ROOT_PATH.'/includes/config/tp.config.php';
            $this->settings = $GLOBALS['SETTINGS'];
        } catch (Exception $e) {
            throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
        }
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
     * Create folder
     *
     * @param array $params
     * @param array $parentFolderData
     * @return array
     */
    private function createFolder($params, $parentFolderData)
    {
        extract($params);
        extract($parentFolderData);
        
        if (
            (int) $isPersonal === 1
            || (int) $user_is_admin === 1
            || ((int) $user_is_manager === 1 || (int) $user_can_manage_all_users === 1)
            || (isset($SETTINGS['enable_user_can_create_folders']) === true
                && (int) $SETTINGS['enable_user_can_create_folders'] == 1)
            || ((int) $user_can_create_root_folder && null !== $user_can_create_root_folder && (int) $user_can_create_root_folder === 1)
        ) {
            //create folder
            DB::insert(
                prefixTable('nested_tree'),
                array(
                    'parent_id' => $parent_id,
                    'title' => $title,
                    'personal_folder' => null !== $isPersonal ? $isPersonal : 0,
                    'renewal_period' => isset($duration) === true && (int) $duration !== 0 ? $duration : 0,
                    'bloquer_creation' => isset($create_auth_without) === true && (int) $create_auth_without === 1 ? '1' : $parentBloquerCreation,
                    'bloquer_modification' => isset($edit_auth_without) === true && (int) $edit_auth_without === 1 ? '1' : $parentBloquerModification,
                    'fa_icon' => empty($icon) === true ? TP_DEFAULT_ICON : $icon,
                    'fa_icon_selected' => empty($icon_selected) === true ? TP_DEFAULT_ICON_SELECTED : $icon_selected,
                    'categories' => '',
                )
            );
            $newId = DB::insertId();
    
            //Add complexity
            DB::insert(
                prefixTable('misc'),
                array(
                    'type' => 'complex',
                    'intitule' => $newId,
                    'valeur' => $complexity,
                    'created_at' => time(),
                )
            );
    
            // ensure categories are set
            handleFoldersCategories(
                [$newId]
            );
    
            // Update timestamp
            DB::update(
                prefixTable('misc'),
                array(
                    'valeur' => time(),
                    'updated_at' => time(),
                ),
                'type = %s AND intitule = %s',
                'timestamp',
                'last_folder_change'
            );
    
            // Load tree
            $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    
            // rebuild tree
            $tree->rebuild();
    
    
            // --> build json tree if not Admin
            if ($user_is_admin === 0) {
                // Get path
                $path = '';
                $tree_path = $tree->getPath(0, false);
                foreach ($tree_path as $fld) {
                    $path .= empty($path) === true ? $fld->title : '/'.$fld->title;
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
    
                // update cache_tree
                $cache_tree = DB::queryfirstrow(
                    'SELECT increment_id, folders, visible_folders
                    FROM ' . prefixTable('cache_tree').' WHERE user_id = %i',
                    (int) $user_id
                );
                if (empty($cache_tree) === true) {
                    DB::insert(
                        prefixTable('cache_tree'),
                        array(
                            'user_id' => $user_id,
                            'folders' => json_encode($newId),
                            'visible_folders' => json_encode($new_json),
                            'timestamp' => time(),
                            'data' => '[{}]',
                        )
                    );
                } else {
                    $a_folders = is_null($cache_tree['folders']) === true ? [] : json_decode($cache_tree['folders'], true);
                    array_push($a_folders, $newId);
                    $a_visible_folders = is_null($cache_tree['visible_folders']) === true || empty($cache_tree['visible_folders']) === true ? [] : json_decode($cache_tree['visible_folders'], true);
                    array_push($a_visible_folders, $new_json);
                    DB::update(
                        prefixTable('cache_tree'),
                        array(
                            'folders' => json_encode($a_folders),
                            'visible_folders' => json_encode($a_visible_folders),
                            'timestamp' => time(),
                        ),
                        'increment_id = %i',
                        (int) $cache_tree['increment_id']
                    );
                }
            }
            // <-- end - build json tree
    
            // Create expected groups access rights based upon option selected
            if (
                isset($SETTINGS['subfolder_rights_as_parent']) === true
                && (int) $SETTINGS['subfolder_rights_as_parent'] === 1
            ) {
                //If it is a subfolder, then give access to it for all roles that allows the parent folder
                $rows = DB::query('SELECT role_id, type FROM ' . prefixTable('roles_values') . ' WHERE folder_id = %i', $parent_id);
                foreach ($rows as $record) {
                    //add access to this subfolder
                    DB::insert(
                        prefixTable('roles_values'),
                        array(
                            'role_id' => $record['role_id'],
                            'folder_id' => $newId,
                            'type' => $record['type'],
                        )
                    );
                }
            } elseif ((int) $user_is_admin !== 1) {
                // If not admin and no option enabled
                // then provide expected rights based upon user's roles
                foreach (array_unique(explode(';', $user_roles)) as $role) {
                    if (empty($role) === false) {
                        DB::insert(
                            prefixTable('roles_values'),
                            array(
                                'role_id' => $role,
                                'folder_id' => $newId,
                                'type' => $access_rights,
                            )
                        );
                    }
                }
            }
    
            // if parent folder has Custom Fields Categories then add to this child one too
            $rows = DB::query('SELECT id_category FROM ' . prefixTable('categories_folders') . ' WHERE id_folder = %i', $parent_id);
            foreach ($rows as $record) {
                //add CF Category to this subfolder
                DB::insert(
                    prefixTable('categories_folders'),
                    array(
                        'id_category' => $record['id_category'],
                        'id_folder' => $newId,
                    )
                );
            }
    
            // clear cache cache for each user that have at least one similar role as the current user
            $usersWithSimilarRoles = empty($user_roles) === false  ? getUsersWithRoles(
                explode(";", $user_roles)
            ) : [];
            foreach ($usersWithSimilarRoles as $user) {
                // delete cache tree
                DB::delete(
                    prefixTable('cache_tree'),
                    'user_id = %i',
                    $user
                );
            }
            return array(
                'error' => false,
                'newId' => $newId,
            );
    
        } else {
            return array(
                'error' => true,
                'newId' => $newId,
            );
        }
    }

    private function errorResponse($message, $newIdSuffix = "")
    {
        return [
            'error' => true,
            'message' => $message,
            'newId' => '' . $newIdSuffix,
        ];
    }
}
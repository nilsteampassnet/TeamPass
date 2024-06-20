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
 * @file      folders.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config if $SETTINGS not defined
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->userAccessPage('folders') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //


// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Prepare post variables
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);

// Ensure Complexity levels are translated
if (defined('TP_PW_COMPLEXITY') === false) {
    define(
        'TP_PW_COMPLEXITY',
        array(
            TP_PW_STRENGTH_1 => array(TP_PW_STRENGTH_1, $lang->get('complex_level1'), 'fas fa-thermometer-empty text-danger'),
            TP_PW_STRENGTH_2 => array(TP_PW_STRENGTH_2, $lang->get('complex_level2'), 'fas fa-thermometer-quarter text-warning'),
            TP_PW_STRENGTH_3 => array(TP_PW_STRENGTH_3, $lang->get('complex_level3'), 'fas fa-thermometer-half text-warning'),
            TP_PW_STRENGTH_4 => array(TP_PW_STRENGTH_4, $lang->get('complex_level4'), 'fas fa-thermometer-three-quarters text-success'),
            TP_PW_STRENGTH_5 => array(TP_PW_STRENGTH_5, $lang->get('complex_level5'), 'fas fa-thermometer-full text-success'),
        )
    );
}

if (null !== $post_type) {
    switch ($post_type) {
            /*
         * BUILD liste of folders
         */
        case 'build_matrix':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // Prepare variables
            $arrData = array();

            $treeDesc = $tree->getDescendants();

            foreach ($treeDesc as $t) {
                if (
                    in_array($t->id, $session->get('user-accessible_folders')) === true
                    && in_array($t->id, $session->get('user-personal_visible_folders')) === false
                    && $t->personal_folder == 0
                ) {
                    // get $t->parent_id
                    $data = DB::queryFirstRow('SELECT title FROM ' . prefixTable('nested_tree') . ' WHERE id = %i', $t->parent_id);
                    if ($t->nlevel == 1) {
                        $data['title'] = $lang->get('root');
                    }

                    // get rights on this folder
                    $arrayRights = array();
                    $rows = DB::query('SELECT fonction_id  FROM ' . prefixTable('rights') . ' WHERE authorized=%i AND tree_id = %i', 1, $t->id);
                    foreach ($rows as $record) {
                        array_push($arrayRights, $record['fonction_id']);
                    }

                    $arbo = $tree->getPath($t->id, false);
                    $arrayPath = array();
                    $arrayParents = array();
                    foreach ($arbo as $elem) {
                        array_push($arrayPath, $elem->title);
                        array_push($arrayParents, $elem->id);
                    }

                    // Get some elements from DB concerning this node
                    $node_data = DB::queryFirstRow(
                        'SELECT m.valeur AS valeur, n.renewal_period AS renewal_period,
                        n.bloquer_creation AS bloquer_creation, n.bloquer_modification AS bloquer_modification,
                        n.fa_icon, n.fa_icon_selected
                        FROM ' . prefixTable('misc') . ' AS m,
                        ' . prefixTable('nested_tree') . ' AS n
                        WHERE m.type=%s AND m.intitule = n.id AND m.intitule = %i',
                        'complex',
                        $t->id
                    );

                    // Preapre array of columns
                    $arrayColumns = array();

                    $arrayColumns['id'] = (int) $t->id;
                    $arrayColumns['numOfChildren'] = (int) $tree->numDescendants($t->id);
                    $arrayColumns['level'] = (int) $t->nlevel;
                    $arrayColumns['parentId'] = (int) $t->parent_id;
                    $arrayColumns['title'] = $t->title;
                    $arrayColumns['nbItems'] = (int) DB::count();
                    $arrayColumns['path'] = $arrayPath;
                    $arrayColumns['parents'] = $arrayParents;

                    if (is_null($node_data) === false && isset(TP_PW_COMPLEXITY[$node_data['valeur']][1]) === true) {
                        $arrayColumns['folderComplexity'] = array(
                            'text' => TP_PW_COMPLEXITY[$node_data['valeur']][1],
                            'value' => TP_PW_COMPLEXITY[$node_data['valeur']][0],
                            'class' => TP_PW_COMPLEXITY[$node_data['valeur']][2],
                        );
                    } else {
                        $arrayColumns['folderComplexity'] = '';
                    }

                    $arrayColumns['renewalPeriod'] = (int) is_null($node_data) === false ? $node_data['renewal_period'] : 0;

                    //col7
                    $data7 = DB::queryFirstRow(
                        'SELECT bloquer_creation,bloquer_modification
                        FROM ' . prefixTable('nested_tree') . '
                        WHERE id = %i',
                        intval($t->id)
                    );
                    $arrayColumns['add_is_blocked'] = (int) $data7['bloquer_creation'];
                    $arrayColumns['edit_is_blocked'] = (int) $data7['bloquer_modification'];
                    $arrayColumns['icon'] = (string) is_null($node_data) === false ? $node_data['fa_icon'] : '';
                    $arrayColumns['iconSelected'] = (string) is_null($node_data) === false ? $node_data['fa_icon_selected'] : '';

                    array_push($arrData, $arrayColumns);
                }
            }

            // Send the complexity levels
            $complexity = array(
                array('value' => TP_PW_STRENGTH_1, 'text' => $lang->get('complex_level1')),
                array('value' => TP_PW_STRENGTH_2, 'text' => $lang->get('complex_level2')),
                array('value' => TP_PW_STRENGTH_3, 'text' => $lang->get('complex_level3')),
                array('value' => TP_PW_STRENGTH_4, 'text' => $lang->get('complex_level4')),
                array('value' => TP_PW_STRENGTH_5, 'text' => $lang->get('complex_level5')),
            );

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'matrix' => $arrData,
                    'userIsAdmin' => $session->get('user-admin'),
                    'userCanCreateRootFolder' => (int) $session->get('user-can_create_root_folder'),
                    'fullComplexity' => $complexity,
                ),
                'encode'
            );

            break;

        // CASE where selecting/deselecting sub-folders
        case 'select_sub_folders':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // get sub folders
            $subfolders = array();

            // Get through each subfolder
            $folders = $tree->getDescendants($post_id, false);
            foreach ($folders as $folder) {
                array_push($subfolders, (int) $folder->id);
            }

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'subfolders' => json_encode($subfolders),
                ),
                'encode'
            );

            break;

            //CASE where UPDATING a new group
        case 'update_folder':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // prepare variables
            $post_title = htmlspecialchars($dataReceived['title']);
            $post_parent_id = filter_var($dataReceived['parentId'], FILTER_SANITIZE_NUMBER_INT);
            $post_complexity = filter_var($dataReceived['complexity'], FILTER_SANITIZE_NUMBER_INT);
            $post_folder_id = filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT);
            $post_renewal_period = isset($dataReceived['renewalPeriod']) === true ? filter_var($dataReceived['renewalPeriod'], FILTER_SANITIZE_NUMBER_INT) : -1;
            $post_add_restriction = isset($dataReceived['addRestriction']) === true ? filter_var($dataReceived['addRestriction'], FILTER_SANITIZE_NUMBER_INT) : -1;
            $post_edit_restriction = isset($dataReceived['editRestriction']) === true ? filter_var($dataReceived['editRestriction'], FILTER_SANITIZE_NUMBER_INT) : -1;
            $post_icon = filter_var($dataReceived['icon'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_icon_selected = filter_var($dataReceived['iconSelected'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Init
            $error = false;
            $errorMessage = '';

            // check if title is numeric
            if (is_numeric($post_title) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_only_numbers_in_folder_name'),
                    ),
                    'encode'
                );
                break;
            }

            // Get info about this folder
            $dataFolder = DB::queryfirstrow(
                'SELECT *
                FROM ' . prefixTable('nested_tree') . '
                WHERE id = %i',
                $post_folder_id
            );

            //Check if duplicate folders name are allowed
            if (
                isset($SETTINGS['duplicate_folder']) === true
                && (int) $SETTINGS['duplicate_folder'] === 0
            ) {
                if (
                    empty($dataFolder['id']) === false
                    && intval($dataReceived['id']) !== intval($dataFolder['id'])
                    && $post_title !== $dataFolder['title']
                ) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('error_group_exist'),
                        ),
                        'encode'
                    );
                    break;
                }
            }

            // Is the parent folder changed?
            if ((int) $dataFolder['parent_id'] === (int) $post_parent_id) {
                $parentChanged = false;
            } else {
                $parentChanged = true;
            }

            //check if parent folder is personal
            $dataParent = DB::queryfirstrow(
                'SELECT personal_folder, bloquer_creation, bloquer_modification
                FROM ' . prefixTable('nested_tree') . '
                WHERE id = %i',
                $post_parent_id
            );

            // inherit from parent the specific settings it has
            if (DB::count() > 0) {
                $parentBloquerCreation = $dataParent['bloquer_creation'];
                $parentBloquerModification = $dataParent['bloquer_modification'];
            } else {
                $parentBloquerCreation = 0;
                $parentBloquerModification = 0;
            }

            if (isset($dataParent['personal_folder']) === true && (int) $dataParent['personal_folder'] === 1) {
                $isPersonal = 1;
            } else {
                $isPersonal = 0;

                // check if complexity level is good
                // if manager or admin don't care
                if (
                    (int) $session->get('user-admin') !== 1
                    && ((int) $session->get('user-manager') !== 1
                        || (int) $session->get('user-can_manage_all_users') !== 1)
                ) {
                    // get complexity level for this folder
                    $data = DB::queryfirstrow(
                        'SELECT valeur
                        FROM ' . prefixTable('misc') . '
                        WHERE intitule = %i AND type = %s',
                        $post_parent_id,
                        'complex'
                    );

                    if (isset($data['valeur']) === true && (int) $post_complexity < (int) $data['valeur']) {
                        echo prepareExchangedData(
                            array(
                                'error' => true,
                                'message' => $lang->get('error_folder_complexity_lower_than_top_folder')
                                    . ' [<b>' . TP_PW_COMPLEXITY[$data['valeur']][1] . '</b>]',
                            ),
                            'encode'
                        );
                        break;
                    }
                }
            }
            
            // Prepare update parameters
            $folderParameters = array(
                'parent_id' => $post_parent_id,
                'title' => $post_title,
                'personal_folder' => $isPersonal,
                'fa_icon' => empty($post_icon) === true ? TP_DEFAULT_ICON : $post_icon,
                'fa_icon_selected' => empty($post_icon_selected) === true ? TP_DEFAULT_ICON_SELECTED : $post_icon_selected,
            );
            
            if ($post_renewal_period !== -1 && $dataFolder['renewal_period'] !== $post_renewal_period) {
                $folderParameters['renewal_period'] = $post_renewal_period;
            }
            if ($post_add_restriction !== -1 && $dataFolder['bloquer_creation'] !== $post_add_restriction) {
                $folderParameters['bloquer_creation'] = $post_add_restriction;
            }
            if ($post_edit_restriction !== -1 && $dataFolder['bloquer_modification'] !== $post_edit_restriction) {
                $folderParameters['bloquer_modification'] = $post_edit_restriction;
            }
            
            // Now update
            DB::update(
                prefixTable('nested_tree'),
                $folderParameters,
                'id=%i',
                $dataFolder['id']
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

            //Add complexity
            DB::update(
                prefixTable('misc'),
                array(
                    'valeur' => $post_complexity,
                    'updated_at' => time(),
                ),
                'intitule = %s AND type = %s',
                $dataFolder['id'],
                'complex'
            );

            // ensure categories are set
            handleFoldersCategories(
                [$dataFolder['id']]
            );

            $tree->rebuild();

            echo prepareExchangedData(
                array(
                    'error' => $error,
                    'message' => $errorMessage,
                    'info_parent_changed' => $parentChanged,
                ),
                'encode'
            );

            break;

        //CASE where ADDING a new group
        case 'add_folder':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // prepare variables
            $post_title = htmlspecialchars($dataReceived['title']);
            $post_parent_id = isset($dataReceived['parentId']) === true ? filter_var($dataReceived['parentId'], FILTER_SANITIZE_NUMBER_INT) : 0;
            $post_complexity = filter_var($dataReceived['complexity'], FILTER_SANITIZE_NUMBER_INT);
            $post_duration = isset($dataReceived['renewalPeriod']) === true ? filter_var($dataReceived['renewalPeriod'], FILTER_SANITIZE_NUMBER_INT) : 0;
            $post_create_auth_without = isset($dataReceived['renewalPeriod']) === true ? filter_var($dataReceived['addRestriction'], FILTER_SANITIZE_NUMBER_INT) : 0;
            $post_edit_auth_without = isset($dataReceived['renewalPeriod']) === true ? filter_var($dataReceived['editRestriction'], FILTER_SANITIZE_NUMBER_INT) : 0;
            $post_icon = filter_var($dataReceived['icon'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_icon_selected = filter_var($dataReceived['iconSelected'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_access_rights = isset($dataReceived['accessRight']) === true ? filter_var($dataReceived['accessRight'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : 'W';

            // Create folder
            require_once 'folders.class.php';
            $folderManager = new FolderManager($lang);
            $params = [
                'title' => (string) $post_title,
                'parent_id' => (int) $post_parent_id,
                'complexity' => (int) $post_complexity,
                'duration' => (int) $post_duration,
                'create_auth_without' => (int) $post_create_auth_without,
                'edit_auth_without' => (int) $post_edit_auth_without,
                'icon' => (string) $post_icon,
                'icon_selected' => (string) $post_icon_selected,
                'access_rights' => (string) $post_access_rights,
                'user_is_admin' => (int) $session->get('user-admin'),
                'user_accessible_folders' => (array) $session->get('user-accessible_folders'),
                'user_is_manager' => (int) $session->get('user-manager'),
                'user_can_create_root_folder' => (int) $session->get('user-can_create_root_folder'),
                'user_can_manage_all_users' => (int) $session->get('user-can_manage_all_users'),
                'user_id' => (int) $session->get('user-id'),
                'user_roles' => (string) $session->get('user-roles')
            ];
            $creationStatus = $folderManager->createNewFolder($params);

            // User created the folder
            // Add new ID to list of visible ones
            if ((int) $session->get('user-admin') === 0 && $creationStatus['error'] === false && $creationStatus['newId'] !== 0) {
                SessionManager::addRemoveFromSessionArray('user-accessible_folders', [$creationStatus['newId']], 'add');
            }

            echo prepareExchangedData(
                array(
                    'error' => $creationStatus['error'],
                    'message' => $creationStatus['error'] === true ? $lang->get('error_not_allowed_to') : $lang->get('folder_created') ,
                    'newId' => $creationStatus['newId'],
                ),
                'encode'
            );

            break;

        // CASE where DELETING multiple groups
        case 'delete_folders':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // prepare variables
            $post_folders = filter_var_array(
                $dataReceived['selectedFolders'],
                FILTER_SANITIZE_FULL_SPECIAL_CHARS
            );

            //decrypt and retreive data in JSON format
            $folderForDel = array();

            foreach ($post_folders as $folderId) {
                // Exclude a folder with id alreay in the list
                if (in_array($folderId, $folderForDel) === false) {
                    // Get through each subfolder
                    $subFolders = $tree->getDescendants($folderId, true);
                    foreach ($subFolders as $thisSubFolders) {
                        if (($thisSubFolders->parent_id > 0 || $thisSubFolders->parent_id == 0)
                            && $thisSubFolders->title !== $session->get('user-id')
                        ) {
                            //Store the deleted folder (recycled bin)
                            DB::insert(
                                prefixTable('misc'),
                                array(
                                    'type' => 'folder_deleted',
                                    'intitule' => 'f' . $thisSubFolders->id,
                                    'valeur' => $thisSubFolders->id . ', ' . $thisSubFolders->parent_id . ', ' .
                                        $thisSubFolders->title . ', ' . $thisSubFolders->nleft . ', ' . $thisSubFolders->nright . ', ' .
                                        $thisSubFolders->nlevel . ', 0, 0, 0, 0',
                                    'created_at' => time(),
                                )
                            );
                            //array for delete folder
                            $folderForDel[] = $thisSubFolders->id;

                            //delete items & logs
                            $itemsInSubFolder = DB::query('SELECT id FROM ' . prefixTable('items') . ' WHERE id_tree=%i', $thisSubFolders->id);
                            foreach ($itemsInSubFolder as $item) {
                                DB::update(
                                    prefixTable('items'),
                                    array(
                                        'inactif' => '1',
                                    ),
                                    'id = %i',
                                    $item['id']
                                );
                                
                                // log
                                logItems(
                                    $SETTINGS,
                                    (int) $item['id'],
                                    '',
                                    $session->get('user-id'),
                                    'at_delete',
                                    $session->get('user-login')
                                );

                                // delete folder from SESSION
                                if (array_search($item['id'], $session->get('user-accessible_folders')) !== false) {
                                    SessionManager::addRemoveFromSessionArray('user-accessible_folders', [$item['id']], 'remove');
                                }

                                //Update CACHE table
                                updateCacheTable('delete_value',(int) $item['id']);

                                // --> build json tree  
                                // update cache_tree
                                $cache_tree = DB::queryfirstrow(
                                    'SELECT increment_id, folders, visible_folders
                                    FROM ' . prefixTable('cache_tree').' WHERE user_id = %i',
                                    (int) $session->get('user-id')
                                );
                                if (DB::count()>0) {
                                    // remove id from folders
                                    if (empty($cache_tree['folders']) === false && is_null($cache_tree['folders']) === false) {
                                        $a_folders = json_decode($cache_tree['folders'], true);
                                        $key = array_search($item['id'], $a_folders, true);
                                        if ($key !== false) {
                                            unset($a_folders[$key]);
                                        }
                                    } else {
                                        $a_folders = [];
                                    }

                                    // remove id from visible_folders
                                    if (empty($cache_tree['visible_folders']) === false && is_null($cache_tree['visible_folders']) === false) {
                                        $a_visible_folders = json_decode($cache_tree['visible_folders'], true);
                                        foreach ($a_visible_folders as $i => $v) {
                                            if ($v['id'] == $item['id']) {
                                                unset($a_visible_folders[$i]);
                                            }
                                        }
                                    } else {
                                        $a_visible_folders = [];
                                    }

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
                                // <-- end - build json tree
                            }

                            //Actualize the variable
                            $session->set('user-nb_folders', $session->get('user-nb_folders') - 1);
                        }
                    }

                    // delete folders
                    $folderForDel = array_unique($folderForDel);
                    foreach ($folderForDel as $fol) {
                        DB::delete(prefixTable('nested_tree'), 'id = %i', $fol);
                    }
                }
            }

            //rebuild tree
            $tree->rebuild();

            // reload cache table
            include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
            updateCacheTable('reload', null);

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

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );

            break;

        case 'copy_folder':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Init post variables
            $post_source_folder_id = filter_var($dataReceived['source_folder_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_target_folder_id = filter_var($dataReceived['target_folder_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_folder_label = filter_var($dataReceived['folder_label'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            // Test if target folder is Read-only
            // If it is then stop
            if (in_array($post_target_folder_id, $session->get('user-read_only_folders')) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // Get all allowed folders
            $array_all_visible_folders = array_merge(
                $session->get('user-accessible_folders'),
                $session->get('user-read_only_folders'),
                $session->get('user-personal_visible_folders')
            );

            // get list of all folders
            $nodeDescendants = $tree->getDescendants($post_source_folder_id, true, false, false);
            $parentId = '';
            $tabNodes = [];
            foreach ($nodeDescendants as $node) {
                // step1 - copy folder

                // Can user access this subfolder?
                if (in_array($node->id, $array_all_visible_folders) === false) {
                    continue;
                }

                // get info about current node
                $nodeInfo = $tree->getNode($node->id);

                // get complexity of current node
                $nodeComplexity = DB::queryfirstrow(
                    'SELECT valeur
                    FROM ' . prefixTable('misc') . '
                    WHERE intitule = %i AND type= %s',
                    $nodeInfo->id,
                    'complex'
                );

                // prepare parent Id
                if (empty($parentId) === true) {
                    $parentId = $post_target_folder_id;
                } else {
                    $parentId = $tabNodes[$nodeInfo->parent_id];
                }

                //create folder
                DB::insert(
                    prefixTable('nested_tree'),
                    array(
                        'parent_id' => $parentId,
                        'title' => count($tabNodes) === 0 ? $post_folder_label : $nodeInfo->title,
                        'personal_folder' => $nodeInfo->personal_folder,
                        'renewal_period' => $nodeInfo->renewal_period,
                        'bloquer_creation' => $nodeInfo->bloquer_creation,
                        'bloquer_modification' => $nodeInfo->bloquer_modification,
                        'categories'  => '',
                    )
                );
                $newFolderId = DB::insertId();

                // add to correspondance matrix
                $tabNodes[$nodeInfo->id] = $newFolderId;

                //Add complexity
                DB::insert(
                    prefixTable('misc'),
                    array(
                        'type' => 'complex',
                        'intitule' => $newFolderId,
                        'valeur' => is_null($nodeComplexity['valeur']) === false ? $nodeComplexity['valeur'] : 0,
                        'created_at' => time(),
                    )
                );

                // add new folder id in SESSION
                $session->set('user-accessible_folders', array_unique(array_merge($session->get('user-accessible_folders'), [$newFolderId]), SORT_NUMERIC));
                if ((int) $nodeInfo->personal_folder === 1) {
                    SessionManager::addRemoveFromSessionArray('user-personal_folders', [$newFolderId], 'add');
                    SessionManager::addRemoveFromSessionArray('user-personal_visible_folders', [$newFolderId], 'add');
                } else {
                    SessionManager::addRemoveFromSessionArray('user-all_non_personal_folders', [$newFolderId], 'add');
                }

                // If new folder should not heritate of parent rights
                // Then use the creator ones
                if (
                    (int) $nodeInfo->personal_folder !== 1
                    && isset($SETTINGS['subfolder_rights_as_parent']) === true
                    && (int) $SETTINGS['subfolder_rights_as_parent'] === 1
                    && (int) $session->get('user-admin') === 0
                ) {
                    //add access to this new folder
                    foreach (explode(';', $session->get('user-roles')) as $role) {
                        if (empty($role) === false) {
                            DB::insert(
                                prefixTable('roles_values'),
                                array(
                                    'role_id' => $role,
                                    'folder_id' => $newFolderId,
                                    'type' => 'W',
                                )
                            );
                        }
                    }
                }

                // If it is a subfolder, then give access to it for all roles that allows the parent folder
                $rows = DB::query(
                    'SELECT role_id, type
                    FROM ' . prefixTable('roles_values') . '
                    WHERE folder_id = %i',
                    $parentId
                );
                foreach ($rows as $record) {
                    // Add access to this subfolder after checking that it is not already set
                    DB::query(
                        'SELECT *
                        FROM ' . prefixTable('roles_values') . '
                        WHERE folder_id = %i AND role_id = %i',
                        $newFolderId,
                        $record['role_id']
                    );
                    if (DB::count() === 0) {
                        DB::insert(
                            prefixTable('roles_values'),
                            array(
                                'role_id' => $record['role_id'],
                                'folder_id' => $newFolderId,
                                'type' => $record['type'],
                            )
                        );
                    }
                }

                // if parent folder has Custom Fields Categories then add to this child one too
                $rows = DB::query(
                    'SELECT id_category
                    FROM ' . prefixTable('categories_folders') . '
                    WHERE id_folder = %i',
                    $nodeInfo->id
                );
                foreach ($rows as $record) {
                    //add CF Category to this subfolder
                    DB::insert(
                        prefixTable('categories_folders'),
                        array(
                            'id_category' => $record['id_category'],
                            'id_folder' => $newFolderId,
                        )
                    );
                }

                // step2 - copy items

                $rows = DB::query(
                    'SELECT *
                    FROM ' . prefixTable('items') . '
                    WHERE id_tree = %i',
                    $nodeInfo->id
                );
                foreach ($rows as $record) {
                    // check if item is deleted
                    // if it is then don't copy it
                    $item_deleted = DB::queryFirstRow(
                        'SELECT *
                        FROM ' . prefixTable('log_items') . '
                        WHERE id_item = %i AND action = %s
                        ORDER BY date DESC
                        LIMIT 0, 1',
                        $record['id'],
                        'at_delete'
                    );
                    $dataDeleted = DB::count();

                    $item_restored = DB::queryFirstRow(
                        'SELECT *
                        FROM ' . prefixTable('log_items') . '
                        WHERE id_item = %i AND action = %s
                        ORDER BY date DESC
                        LIMIT 0, 1',
                        $record['id'],
                        'at_restored'
                    );

                    if (
                        (int) $dataDeleted !== 1
                        || (isset($item_restored['date']) === true && (int) $item_deleted['date'] < (int) $item_restored['date'])
                    ) {
                        // Get the ITEM object key for the user
                        $userKey = DB::queryFirstRow(
                            'SELECT share_key
                            FROM ' . prefixTable('sharekeys_items') . '
                            WHERE user_id = %i AND object_id = %i',
                            $session->get('user-id'),
                            $record['id']
                        );
                        if (DB::count() === 0) {
                            // ERROR - No sharekey found for this item and user
                            echo prepareExchangedData(
                                array(
                                    'error' => true,
                                    'message' => $lang->get('error_not_allowed_to'),
                                ),
                                'encode'
                            );
                            break;
                        }

                        // Decrypt / Encrypt the password
                        $cryptedStuff = doDataEncryption(
                            base64_decode(
                                doDataDecryption(
                                    $record['pw'],
                                    decryptUserObjectKey(
                                        $userKey['share_key'],
                                        $session->get('user-private_key')
                                    )
                                )
                            )
                        );

                        // Insert the new record and get the new auto_increment id
                        DB::insert(
                            prefixTable('items'),
                            array(
                                'label' => 'duplicate',
                                'id_tree' => $newFolderId,
                                'pw' => $cryptedStuff['encrypted'],
                                'pw_iv' => '',
                                'viewed_no' => 0,
                            )
                        );
                        $newItemId = DB::insertId();

                        // Create sharekeys for users of this new ITEM
                        storeUsersShareKey(
                            prefixTable('sharekeys_items'),
                            (int) $record['perso'],
                            (int) $newFolderId,
                            (int) $newItemId,
                            $cryptedStuff['objectKey'],
                        );

                        // Generate the query to update the new record with the previous values
                        $aSet = [];
                        foreach ($record as $key => $value) {
                            if (
                                $key !== 'id' && $key !== 'key' && $key !== 'id_tree'
                                && $key !== 'viewed_no' && $key !== 'pw' && $key !== 'pw_iv'
                            ) {
                                $aSet[$key] = $value;
                            }
                        }
                        DB::update(
                            prefixTable('items'),
                            $aSet,
                            'id = %i',
                            $newItemId
                        );

                        // --------------------
                        // Manage Custom Fields
                        $categories = DB::query(
                            'SELECT *
                            FROM ' . prefixTable('categories_items') . '
                            WHERE item_id = %i',
                            $record['id']
                        );
                        foreach ($categories as $field) {
                            // Create the entry for the new item

                            // Is the data encrypted
                            if ((int) $field['encryption_type'] === TP_ENCRYPTION_NAME) {
                                $cryptedStuff = doDataEncryption($field['data']);
                            }

                            // store field text
                            DB::insert(
                                prefixTable('categories_items'),
                                array(
                                    'item_id' => $newItemId,
                                    'field_id' => $field['field_id'],
                                    'data' => (int) $field['encryption_type'] === TP_ENCRYPTION_NAME ?
                                        $cryptedStuff['encrypted'] : $field['data'],
                                    'data_iv' => '',
                                    'encryption_type' => (int) $field['encryption_type'] === TP_ENCRYPTION_NAME ?
                                        TP_ENCRYPTION_NAME : 'not_set',
                                )
                            );
                            $newFieldId = DB::insertId();

                            // Create sharekeys for users
                            if ((int) $field['encryption_type'] === TP_ENCRYPTION_NAME) {
                                storeUsersShareKey(
                                    prefixTable('sharekeys_fields'),
                                    (int) $record['id'],
                                    (int) $newFolderId,
                                    (int) $newFieldId,
                                    $cryptedStuff['objectKey'],
                                );
                            }
                        }
                        // <---

                        // ------------------
                        // Manage attachments

                        // get file key
                        $files = DB::query(
                            'SELECT f.id AS id, f.file AS file, f.name AS name, f.status AS status, f.extension AS extension,
                            f.size AS size, f.type AS type, s.share_key AS share_key
                            FROM ' . prefixTable('files') . ' AS f
                            INNER JOIN ' . prefixTable('sharekeys_files') . ' AS s ON (f.id = s.object_id)
                            WHERE s.user_id = %i AND f.id_item = %i',
                            $session->get('user-id'),
                            $record['id']
                        );
                        foreach ($files as $file) {
                            // Check if file still exists
                            if (file_exists($SETTINGS['path_to_upload_folder'] . DIRECTORY_SEPARATOR . TP_FILE_PREFIX . base64_decode($file['file'])) === true) {
                                // Step1 - decrypt the file
                                $fileContent = decryptFile(
                                    $file['file'],
                                    $SETTINGS['path_to_upload_folder'],
                                    decryptUserObjectKey($file['share_key'], $session->get('user-private_key'))
                                );

                                // Step2 - create file
                                // deepcode ignore InsecureHash: Is not a password, just a random string for a file name
                                $newFileName = md5(time() . '_' . $file['id']) . '.' . $file['extension'];

                                $outstream = fopen($SETTINGS['path_to_upload_folder'] . DIRECTORY_SEPARATOR . $newFileName, 'ab');
                                if ($outstream === false) {
                                    echo prepareExchangedData(
                                        array(
                                            'error' => true,
                                            'message' => $lang->get('error_cannot_open_file'),
                                        ),
                                        'encode'
                                    );
                                    break;
                                }
                                fwrite(
                                    $outstream,
                                    base64_decode($fileContent)
                                );

                                // Step3 - encrypt the file
                                $newFile = encryptFile($newFileName, $SETTINGS['path_to_upload_folder']);

                                // Step4 - store in database
                                DB::insert(
                                    prefixTable('files'),
                                    array(
                                        'id_item' => $newItemId,
                                        'name' => $file['name'],
                                        'size' => $file['size'],
                                        'extension' => $file['extension'],
                                        'type' => $file['type'],
                                        'file' => $newFile['fileHash'],
                                        'status' => TP_ENCRYPTION_NAME,
                                        'confirmed' => 1,
                                    )
                                );
                                $newFileId = DB::insertId();

                                // Step5 - create sharekeys
                                storeUsersShareKey(
                                    prefixTable('sharekeys_files'),
                                    (int) $record['perso'],
                                    (int) $newFolderId,
                                    (int) $newFileId,
                                    $newFile['objectKey'],
                                );
                            }
                        }
                        // <---

                        // Add this duplicate in logs
                        logItems(
                            $SETTINGS,
                            (int) $newItemId,
                            $record['label'],
                            $session->get('user-id'),
                            'at_creation',
                            $session->get('user-login')
                        );
                        // Add the fact that item has been copied in logs
                        logItems(
                            $SETTINGS,
                            (int) $newItemId,
                            $record['label'],
                            $session->get('user-id'),
                            'at_copy',
                            $session->get('user-login')
                        );
                    }
                }
            }

            // rebuild tree
            $tree->rebuild();

            // reload cache table
            include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
            updateCacheTable('reload', NULL);

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

            $data = array(
                'error' => '',
            );

            // send data
            echo prepareExchangedData(
                $data,
                'encode'
            );

            break;

        // CASE where selecting/deselecting sub-folders
        case 'refresh_folders_list':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($session->get('user-read_only') === 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            $subfolders = array();

            if ((int) $session->get('user-admin') === 1 || (int) $session->get('user-manager') === 1 || (int) $session->get('user-can_create_root_folder') === 1) {
                array_push(
                    $subfolders,
                    array(
                        'id' => 0,
                        'label' => $lang->get('root'),
                        'level' => 0,
                        'path' => ''
                    )
                );
            }

            // get sub folders

            // Get through each subfolder
            $folders = $tree->getDescendants(0, false);
            
            foreach ($folders as $folder) {
                if (
                    in_array($folder->id, $session->get('user-accessible_folders')) === true
                    && in_array($folder->id, $session->get('user-personal_visible_folders')) === false
                ) {
                    // Get path
                    $text = '';
                    foreach ($tree->getPath($folder->id, false) as $fld) {
                        $text .= empty($text) === true ? '     [<i>' . $fld->title : ' > ' . $fld->title;
                    }

                    // Save array
                    array_push(
                        $subfolders,
                        array(
                            'id' => (int) $folder->id,
                            'label' => $folder->title,
                            'level' => $folder->nlevel,
                            'path' => empty($text) === true ? '' : $text . '</i>]'
                        )
                    );
                }
            }

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'subfolders' => ($subfolders),
                ),
                'encode'
            );

            break;
    }
}

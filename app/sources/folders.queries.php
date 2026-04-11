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
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

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

            // Load the full tree once (1 query) — result is indexed by node id
            $treeDesc = $tree->getDescendants();

            // Filter accessible non-personal folders
            $accessibleFolders = [];
            foreach ($treeDesc as $t) {
                if (
                    in_array($t->id, $session->get('user-accessible_folders')) === true
                    && in_array($t->id, $session->get('user-personal_visible_folders')) === false
                    && $t->personal_folder == 0
                ) {
                    $accessibleFolders[] = $t;
                }
            }

            if (!empty($accessibleFolders)) {
                // Collect IDs for bulk queries
                $folderIds = array_map(static fn($t) => (int) $t->id, $accessibleFolders);

                // Bulk load complexity values (1 query instead of N)
                $complexityMap = [];
                $complexRows = DB::query(
                    'SELECT intitule, valeur FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule IN %ls',
                    'complex',
                    $folderIds
                );
                foreach ($complexRows as $row) {
                    $complexityMap[(int) $row['intitule']] = (int) $row['valeur'];
                }

                // Bulk load active item counts per folder (1 query instead of N)
                $itemCountMap = [];
                $itemCountRows = DB::query(
                    'SELECT id_tree, COUNT(*) AS cnt FROM ' . prefixTable('items') .
                    ' WHERE inactif = %i AND id_tree IN %ls GROUP BY id_tree',
                    0,
                    $folderIds
                );
                foreach ($itemCountRows as $row) {
                    $itemCountMap[(int) $row['id_tree']] = (int) $row['cnt'];
                }

                // $treeDesc is indexed by node id — reuse as in-memory node map for path traversal
                foreach ($accessibleFolders as $t) {
                    // Build ancestor path by traversing parent_id chain (no DB query)
                    // $visited guards against circular references in corrupted data
                    $arrayPath = [];
                    $arrayParents = [];
                    $visited = [];
                    $currentId = (int) $t->parent_id;
                    while ($currentId > 0 && isset($treeDesc[$currentId]) && !isset($visited[$currentId])) {
                        $visited[$currentId] = true;
                        array_unshift($arrayPath, $treeDesc[$currentId]->title);
                        array_unshift($arrayParents, $currentId);
                        $currentId = (int) $treeDesc[$currentId]->parent_id;
                    }

                    // Compute children count from MPTT values (no DB query)
                    $numOfChildren = (int) (($t->nright - $t->nleft - 1) / 2);

                    $complexityValue = $complexityMap[(int) $t->id] ?? null;

                    // Prepare array of columns
                    $arrayColumns = [];
                    $arrayColumns['id'] = (int) $t->id;
                    $arrayColumns['numOfChildren'] = $numOfChildren;
                    $arrayColumns['level'] = (int) $t->nlevel;
                    $arrayColumns['parentId'] = (int) $t->parent_id;
                    $arrayColumns['title'] = $t->title;
                    $arrayColumns['nbItems'] = $itemCountMap[(int) $t->id] ?? 0;
                    $arrayColumns['path'] = $arrayPath;
                    $arrayColumns['parents'] = $arrayParents;

                    if ($complexityValue !== null && isset(TP_PW_COMPLEXITY[$complexityValue][1]) === true) {
                        $arrayColumns['folderComplexity'] = [
                            'text' => TP_PW_COMPLEXITY[$complexityValue][1],
                            'value' => TP_PW_COMPLEXITY[$complexityValue][0],
                            'class' => TP_PW_COMPLEXITY[$complexityValue][2],
                        ];
                    } else {
                        $arrayColumns['folderComplexity'] = '';
                    }

                    // renewal_period, bloquer_creation, bloquer_modification, fa_icon, fa_icon_selected
                    // are all loaded by getDescendants() — no extra query needed
                    $arrayColumns['renewalPeriod'] = (int) $t->renewal_period;
                    $arrayColumns['add_is_blocked'] = (int) $t->bloquer_creation;
                    $arrayColumns['edit_is_blocked'] = (int) $t->bloquer_modification;
                    $arrayColumns['icon'] = (string) ($t->fa_icon ?? '');
                    $arrayColumns['iconSelected'] = (string) ($t->fa_icon_selected ?? '');

                    $arrData[] = $arrayColumns;
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
            $data = [
                'id' => isset($dataReceived['id']) === true ? $dataReceived['id'] : -1,
                'title' => isset($dataReceived['title']) === true ? $dataReceived['title'] : '',
                'parentId' => isset($dataReceived['parentId']) === true ? $dataReceived['parentId'] : 0,
                'complexity' => isset($dataReceived['complexity']) === true ? $dataReceived['complexity'] : '',
                'duration' => isset($dataReceived['renewalPeriod']) === true ? $dataReceived['renewalPeriod'] : 0,
                'create_auth_without' => isset($dataReceived['addRestriction']) === true ? $dataReceived['addRestriction'] : 0,
                'edit_auth_without' => isset($dataReceived['editRestriction']) === true ? $dataReceived['editRestriction'] : 0,
                'icon' => isset($dataReceived['icon']) === true ? $dataReceived['icon'] : '',
                'icon_selected' => isset($dataReceived['iconSelected']) === true ? $dataReceived['iconSelected'] : '',
                'access_rights' => isset($dataReceived['accessRight']) === true ? $dataReceived['accessRight'] : 'W',
            ];            
            $filters = [
                'id' => 'cast:integer',
                'title' => 'trim|escape',
                'parentId' => 'cast:integer',
                'complexity' => 'cast:integer',
                'duration' => 'cast:integer',
                'create_auth_without' => 'cast:integer',
                'edit_auth_without' => 'cast:integer',
                'icon' => 'trim|escape',
                'icon_selected' => 'trim|escape',
                'access_rights' => 'trim|escape',
            ];            
            $inputData = dataSanitizer(
                $data,
                $filters
            );

            // Init
            $error = false;
            $errorMessage = '';

            // Prevent circular reference: a folder cannot be its own parent
            if ((int) $inputData['parentId'] === (int) $inputData['id']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // Deny edit if this specific folder is read-only for the current user (per-folder role restriction)
            if (
                (int) $session->get('user-admin') !== 1
                && is_array($session->get('user-read_only_folders'))
                && in_array($inputData['id'], $session->get('user-read_only_folders'), true)
            ) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // check if title is numeric
            if (is_numeric($inputData['title']) === true) {
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
            $dataFolder = DB::queryFirstRow(
                'SELECT *
                FROM ' . prefixTable('nested_tree') . '
                WHERE id = %i',
                $inputData['id']
            );
            
            // Check if duplicate folder names are allowed (rename case)
            if (
                isset($SETTINGS['duplicate_folder']) === true
                && (int) $SETTINGS['duplicate_folder'] === 0
                && $inputData['title'] !== $dataFolder['title']
            ) {
                DB::query(
                    'SELECT id FROM ' . prefixTable('nested_tree') . '
                    WHERE title = %s AND personal_folder = 0 AND id != %i',
                    $inputData['title'],
                    $inputData['id']
                );
                if (DB::count() !== 0) {
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

            $isPersonalRootFolder =
                (int) $dataFolder['parent_id'] === 0
                && (string) $dataFolder['title'] === (string) $session->get('user-id')
                && (int) $dataFolder['personal_folder'] === 1;

            // Is the parent folder changed?
            if ((int) $dataFolder['parent_id'] === (int) $inputData['parentId']) {
                $parentChanged = false;
            } else {
                $parentChanged = true;
            }

            if ($isPersonalRootFolder === true) {
                $inputData['parentId'] = (int) $dataFolder['parent_id'];
                $inputData['title'] = (string) $dataFolder['title'];
                $parentChanged = false;
            }

            //check if parent folder is personal
            $dataParent = DB::queryFirstRow(
                'SELECT personal_folder, bloquer_creation, bloquer_modification
                FROM ' . prefixTable('nested_tree') . '
                WHERE id = %i',
                $inputData['parentId']
            );

            // inherit from parent the specific settings it has
            if (DB::count() > 0) {
                $parentBloquerCreation = $dataParent['bloquer_creation'];
                $parentBloquerModification = $dataParent['bloquer_modification'];
            } else {
                $parentBloquerCreation = 0;
                $parentBloquerModification = 0;
            }

            if ($isPersonalRootFolder === true) {
                $isPersonal = 1;
            } elseif (isset($dataParent['personal_folder']) === true && (int) $dataParent['personal_folder'] === 1) {
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
                    $data = DB::queryFirstRow(
                        'SELECT valeur
                        FROM ' . prefixTable('misc') . '
                        WHERE intitule = %i AND type = %s',
                        $inputData['parentId'],
                        'complex'
                    );

                    if (isset($data['valeur']) === true && (int) $inputData['complexity'] < (int) $data['valeur']) {
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

            // Check if user is allowed
            if (
                !(
                    (int) $isPersonal === 1
                    || (int) $session->get('user-admin') === 1
                    || (int) $session->get('user-manager') === 1
                    || (int) $session->get('user-can_manage_all_users') === 1
                    || (isset($SETTINGS['enable_user_can_create_folders']) === true
                        && (int) $SETTINGS['enable_user_can_create_folders'] == 1)
                    || (int) $session->get('user-can_create_root_folder') === 1
                )
            ) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // Prepare update parameters
            $folderParameters = array(
                'parent_id' => $inputData['parentId'],
                'title' => $inputData['title'],
                'personal_folder' => $isPersonal,
                'fa_icon' => empty($inputData['icon']) === true ? TP_DEFAULT_ICON : $inputData['icon'],
                'fa_icon_selected' => empty($inputData['icon_selected']) === true ? TP_DEFAULT_ICON_SELECTED : $inputData['icon_selected'],
            );
            
            if ($inputData['duration'] !== -1 && $dataFolder['renewal_period'] !== $inputData['duration']) {
                $folderParameters['renewal_period'] = $inputData['duration'];
            }
            if ($inputData['create_auth_without'] !== -1 && $dataFolder['bloquer_creation'] !== $inputData['create_auth_without']) {
                $folderParameters['bloquer_creation'] = $inputData['create_auth_without'];
            }
            if ($inputData['edit_auth_without'] !== -1 && $dataFolder['bloquer_modification'] !== $inputData['edit_auth_without']) {
                $folderParameters['bloquer_modification'] = $inputData['edit_auth_without'];
            }
            
            // Now update
            DB::update(
                prefixTable('nested_tree'),
                $folderParameters,
                'id=%i',
                $dataFolder['id']
            );

            // Invalidate cache for users with access to this folder
            invalidateCacheForFolderUsers((int) $dataFolder['id']);

            // Add or update complexity row for this folder.
            // Personal root folders can exist without a misc/complex row,
            // so a plain UPDATE may silently affect 0 rows and leave the UI
            // and item edition logic with a fallback complexity of 0.
            DB::query(
                'SELECT valeur
                FROM ' . prefixTable('misc') . '
                WHERE intitule = %i AND type = %s',
                $dataFolder['id'],
                'complex'
            );
            if (DB::count() > 0) {
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => $inputData['complexity'],
                        'updated_at' => time(),
                    ),
                    'intitule = %s AND type = %s',
                    $dataFolder['id'],
                    'complex'
                );
            } else {
                DB::insert(
                    prefixTable('misc'),
                    array(
                        'type' => 'complex',
                        'intitule' => $dataFolder['id'],
                        'valeur' => $inputData['complexity'],
                        'created_at' => time(),
                    )
                );
            }

            // ensure categories are set
            handleFoldersCategories(
                [$dataFolder['id']]
            );

            $tree->rebuild();

            // Emit WebSocket event for real-time notification
            emitFolderEvent(
                'updated',
                (int) $dataFolder['id'],
                $inputData['title'] ?? $dataFolder['title'],
                $session->get('user-login') ?? '',
                (int) $inputData['parentId'],
                (int) $session->get('user-id')
            );

            // Build updated row data so the client can update the row without a full table rebuild
            // getNode() always returns an object when the id is valid (tree was just rebuilt above)
            $rowData = null;
            $updatedNode = $tree->getNode((int) $dataFolder['id']);
            $pathNodes = $tree->getPath((int) $dataFolder['id'], false);
            $arrayPath = [];
            $arrayParents = [];
            foreach ($pathNodes as $pn) {
                $arrayPath[] = $pn->title;
                $arrayParents[] = (int) $pn->id;
            }
            $nbItemsRow = DB::queryFirstRow(
                'SELECT COUNT(*) AS cnt FROM ' . prefixTable('items') . ' WHERE id_tree = %i AND inactif = %i',
                (int) $dataFolder['id'],
                0
            );
            $complexityValue = (int) $inputData['complexity'];
            $rowData = [
                'id'             => (int) $dataFolder['id'],
                'numOfChildren'  => (int) (($updatedNode->nright - $updatedNode->nleft - 1) / 2),
                'level'          => (int) $updatedNode->nlevel,
                'parentId'       => (int) $inputData['parentId'],
                'title'          => $inputData['title'],
                'nbItems'        => $nbItemsRow !== null ? (int) $nbItemsRow['cnt'] : 0,
                'path'           => $arrayPath,
                'parents'        => $arrayParents,
                'folderComplexity' => isset(TP_PW_COMPLEXITY[$complexityValue][1])
                    ? [
                        'text'  => TP_PW_COMPLEXITY[$complexityValue][1],
                        'value' => TP_PW_COMPLEXITY[$complexityValue][0],
                        'class' => TP_PW_COMPLEXITY[$complexityValue][2],
                    ]
                    : '',
                'renewalPeriod'  => isset($folderParameters['renewal_period'])
                    ? (int) $folderParameters['renewal_period']
                    : (int) $dataFolder['renewal_period'],
                'add_is_blocked' => isset($folderParameters['bloquer_creation'])
                    ? (int) $folderParameters['bloquer_creation']
                    : (int) $dataFolder['bloquer_creation'],
                'edit_is_blocked'=> isset($folderParameters['bloquer_modification'])
                    ? (int) $folderParameters['bloquer_modification']
                    : (int) $dataFolder['bloquer_modification'],
                'icon'           => $folderParameters['fa_icon'],
                'iconSelected'   => $folderParameters['fa_icon_selected'],
            ];

            echo prepareExchangedData(
                array(
                    'error'               => $error,
                    'message'             => $errorMessage,
                    'info_parent_changed' => $parentChanged,
                    'rowData'             => $rowData,
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
            $data = [
                'title' => isset($dataReceived['title']) === true ? $dataReceived['title'] : '',
                'parentId' => isset($dataReceived['parentId']) === true ? $dataReceived['parentId'] : 0,
                'complexity' => isset($dataReceived['complexity']) === true ? $dataReceived['complexity'] : '',
                'duration' => isset($dataReceived['renewalPeriod']) === true ? $dataReceived['renewalPeriod'] : 0,
                'create_auth_without' => isset($dataReceived['addRestriction']) === true ? $dataReceived['addRestriction'] : 0,
                'edit_auth_without' => isset($dataReceived['editRestriction']) === true ? $dataReceived['editRestriction'] : 0,
                'icon' => isset($dataReceived['icon']) === true ? $dataReceived['icon'] : '',
                'icon_selected' => isset($dataReceived['iconSelected']) === true ? $dataReceived['iconSelected'] : '',
                'access_rights' => isset($dataReceived['accessRight']) === true ? $dataReceived['accessRight'] : 'W',
            ];            
            $filters = [
                'title' => 'trim|escape',
                'parentId' => 'cast:integer',
                'complexity' => 'cast:integer',
                'duration' => 'cast:integer',
                'create_auth_without' => 'cast:integer',
                'edit_auth_without' => 'cast:integer',
                'icon' => 'trim|escape',
                'icon_selected' => 'trim|escape',
                'access_rights' => 'trim|escape',
            ];            
            $inputData = dataSanitizer(
                $data,
                $filters
            );

            // Check if parent folder is personal
            $dataParent = DB::queryFirstRow(
                'SELECT personal_folder
                FROM ' . prefixTable('nested_tree') . '
                WHERE id = %i',
                $inputData['parentId']
            );

            $isPersonal = (isset($dataParent['personal_folder']) === true && (int) $dataParent['personal_folder'] == 1) ? 1 : 0;

            // Create folder
            require_once 'folders.class.php';
            $folderManager = new FolderManager($lang);
            $params = [
                'title' => (string) $inputData['title'],
                'parent_id' => (int) $inputData['parentId'],
                'personal_folder' => (int) $isPersonal,
                'complexity' => (int) $inputData['complexity'],
                'duration' => (int) $inputData['duration'],
                'create_auth_without' => (int) $inputData['create_auth_without'],
                'edit_auth_without' => (int) $inputData['edit_auth_without'],
                'icon' => (string) $inputData['icon'],
                'icon_selected' => (string) $inputData['icon_selected'],
                'access_rights' => (string) $inputData['access_rights'],
                'user_is_admin' => (int) $session->get('user-admin'),
                'user_accessible_folders' => (array) $session->get('user-accessible_folders'),
                'user_is_manager' => (int) $session->get('user-manager'),
                'user_can_create_root_folder' => (int) $session->get('user-can_create_root_folder'),
                'user_can_manage_all_users' => (int) $session->get('user-can_manage_all_users'),
                'user_id' => (int) $session->get('user-id'),
                'user_roles' => (string) $session->get('user-roles')
            ];
            $options = [
                'rebuildFolderTree' => true,
                'setFolderCategories' => false,
                'manageFolderPermissions' => true,
                'copyCustomFieldsCategories' => false,
                'refreshCacheForUsersWithSimilarRoles' => true,
            ];
            $creationStatus = $folderManager->createNewFolder($params, $options);

            // User created the folder
            // Add new ID to list of visible ones
            if ((int) $session->get('user-admin') === 0 && $creationStatus['error'] === false && $creationStatus['newId'] !== 0) {
                SessionManager::addRemoveFromSessionArray('user-accessible_folders', [$creationStatus['newId']], 'add');
            }

            // Emit WebSocket event for real-time notification
            if ($creationStatus['error'] === false && $creationStatus['newId'] !== 0) {
                emitFolderEvent(
                    'created',
                    (int) $creationStatus['newId'],
                    $inputData['title'] ?? '',
                    $session->get('user-login') ?? '',
                    (int) $inputData['parentId'],
                    (int) $session->get('user-id')
                );
            }

            // Build row data so the client can insert the row without a full table rebuild
            $rowData = null;
            if ($creationStatus['error'] === false && $creationStatus['newId'] !== 0) {
                $newFolderId = (int) $creationStatus['newId'];
                // getNode() always returns an object for a freshly created folder id
                $newNode = $tree->getNode($newFolderId);
                $pathNodes = $tree->getPath($newFolderId, false);
                $arrayPath = [];
                $arrayParents = [];
                foreach ($pathNodes as $pn) {
                    $arrayPath[] = $pn->title;
                    $arrayParents[] = (int) $pn->id;
                }
                $complexityValue = (int) $inputData['complexity'];
                $rowData = [
                    'id'             => $newFolderId,
                    'numOfChildren'  => 0,
                    'level'          => (int) $newNode->nlevel,
                    'parentId'       => (int) $inputData['parentId'],
                    'title'          => $inputData['title'],
                    'nbItems'        => 0,
                    'path'           => $arrayPath,
                    'parents'        => $arrayParents,
                    'folderComplexity' => isset(TP_PW_COMPLEXITY[$complexityValue][1])
                        ? [
                            'text'  => TP_PW_COMPLEXITY[$complexityValue][1],
                            'value' => TP_PW_COMPLEXITY[$complexityValue][0],
                            'class' => TP_PW_COMPLEXITY[$complexityValue][2],
                        ]
                        : '',
                    'renewalPeriod'  => (int) ($inputData['duration'] ?? 0),
                    'add_is_blocked' => (int) ($inputData['create_auth_without'] ?? 0),
                    'edit_is_blocked'=> (int) ($inputData['edit_auth_without'] ?? 0),
                    'icon'           => empty($inputData['icon']) ? TP_DEFAULT_ICON : $inputData['icon'],
                    'iconSelected'   => empty($inputData['icon_selected']) ? TP_DEFAULT_ICON_SELECTED : $inputData['icon_selected'],
                ];
            }

            echo prepareExchangedData(
                array(
                    'error'   => $creationStatus['error'],
                    'message' => $creationStatus['error'] === true ? $lang->get('error_not_allowed_to') : $lang->get('folder_created'),
                    'newId'   => $creationStatus['newId'],
                    'rowData' => $rowData,
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

            // Ensure that the root folder is not part of the list
            if (in_array(0, $post_folders, true)) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'). " (You can't delete the root folder)",
                    ),
                    'encode'
                );
                break;
            }
            
            // Deny deletion if any of the target folders is read-only for this user (per-folder role restriction)
            $userReadOnlyFolders = $session->get('user-read_only_folders') ?? [];
            if (!empty($userReadOnlyFolders)) {
                $readOnlyViolations = array_intersect(
                    array_map('intval', $post_folders),
                    array_map('intval', $userReadOnlyFolders)
                );
                if (!empty($readOnlyViolations)) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('error_not_allowed_to'),
                        ),
                        'encode'
                    );
                    break;
                }
            }

            // Ensure that user has access to all folders
            $foldersAccessible = DB::query(
                'SELECT id
                FROM ' . prefixTable('nested_tree') . '
                WHERE id IN %li AND id IN %li',
                $post_folders,
                $session->get('user-accessible_folders')
            );
            // Extract found folder IDs
            $accessibleIds = array_column($foldersAccessible, 'id');
            // Identify those that are not existing in DB or not visible by user
            $missingFolders = array_diff($post_folders, $accessibleIds);
            if (!empty($missingFolders)) {
                // There is some issues
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to') . ' (The following folders are not accessible or do not exist: ' . implode(', ', $missingFolders) . ')',
                    ),
                    'encode'
                );
                break;
            }

            //decrypt and retreive data in JSON format
            $folderForDel = array();
            $foldersDeletedInfo = array(); // For WebSocket notifications

            // Start transaction
            DB::startTransaction();

            foreach ($post_folders as $folderId) {
                // Check if parent folder is personal
                $dataParent = DB::queryFirstRow(
                    'SELECT personal_folder
                    FROM ' . prefixTable('nested_tree') . '
                    WHERE id = %i',
                    $folderId
                );

                $isPersonal = (isset($dataParent['personal_folder']) === true && (int) $dataParent['personal_folder'] === 1) ? 1 : 0;

                // Check if user is allowed
                if (
                    !(
                        (int) $isPersonal === 1
                        || (int) $session->get('user-admin') === 1
                        || (int) $session->get('user-manager') === 1
                        || (int) $session->get('user-can_manage_all_users') === 1
                        || (isset($SETTINGS['enable_user_can_create_folders']) === true
                            && (int) $SETTINGS['enable_user_can_create_folders'] == 1)
                        || (int) $session->get('user-can_create_root_folder') === 1
                    )
                ) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('error_not_allowed_to'),
                        ),
                        'encode'
                    );

                    // Rollback transaction if error
                    DB::rollback();

                    exit;
                }

                // Exclude a folder with id already in the list
                if (in_array($folderId, $folderForDel) === false) {
                    // Get through each subfolder
                    $subFolders = $tree->getDescendants($folderId, true);
                    foreach ($subFolders as $thisSubFolders) {
                        if ($thisSubFolders->parent_id >= 0
                            && $thisSubFolders->title !== $session->get('user-id')
                        ) {
                            // Store the deleted folder (recycled bin)
                            // Use JSON format to avoid issues with commas in titles and to preserve folder properties.
                            $folderDeletedData = array(
                                'id' => (int) $thisSubFolders->id,
                                'parent_id' => (int) $thisSubFolders->parent_id,
                                'title' => (string) $thisSubFolders->title,
                                'nleft' => (int) $thisSubFolders->nleft,
                                'nright' => (int) $thisSubFolders->nright,
                                'nlevel' => (int) $thisSubFolders->nlevel,
                                'bloquer_creation' => (int) ($thisSubFolders->bloquer_creation ?? 0),
                                'bloquer_modification' => (int) ($thisSubFolders->bloquer_modification ?? 0),
                                'personal_folder' => (int) ($thisSubFolders->personal_folder ?? 0),
                                'renewal_period' => (int) ($thisSubFolders->renewal_period ?? 0),
                                'categories' => (string) ($thisSubFolders->categories ?? ''),
                                'deleted_by' => (int) $session->get('user-id'),
                                'deleted_by_login' => (string) ($session->get('user-login') ?? ''),
                            );
                            if (isset($thisSubFolders->fa_icon) === true) {
                                $folderDeletedData['fa_icon'] = (string) $thisSubFolders->fa_icon;
                            }
                            if (isset($thisSubFolders->fa_icon_selected) === true) {
                                $folderDeletedData['fa_icon_selected'] = (string) $thisSubFolders->fa_icon_selected;
                            }
                            if (isset($thisSubFolders->is_template) === true) {
                                $folderDeletedData['is_template'] = (int) $thisSubFolders->is_template;
                            }

                            DB::query(
                                'INSERT INTO %l (type, intitule, valeur, created_at)
                                 VALUES (%s, %s, %s, %i)
                                 ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), updated_at = %i',
                                prefixTable('misc'),
                                'folder_deleted',
                                'f' . (int) $thisSubFolders->id,
                                json_encode($folderDeletedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                time(),
                                time()
                            );
                            //array for delete folder
                            $folderForDel[] = $thisSubFolders->id;

                            // Store info for WebSocket notification
                            $foldersDeletedInfo[] = [
                                'folder_id' => (int) $thisSubFolders->id,
                                'title' => (string) $thisSubFolders->title,
                                'parent_id' => (int) $thisSubFolders->parent_id,
                            ];

                            //delete items & logs
                            $itemsInSubFolder = DB::query(
                                'SELECT id FROM ' . prefixTable('items') . ' 
                                WHERE id_tree=%i', 
                                $thisSubFolders->id
                            );
                            foreach ($itemsInSubFolder as $item) {
                                DB::update(
                                    prefixTable('items'),
                                    array(
                                        'inactif' => '1',
                                        'deleted_at' => time(),
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
                            }

                            //Actualize the variable
                            $session->set('user-nb_folders', $session->get('user-nb_folders') - 1);
                        }
                    }
                }
            }

            // Collect affected users BEFORE deleting folders/roles
            $folderForDel = array_unique($folderForDel);
            $affectedUserIds = [];
            if (!empty($folderForDel)) {
                $affectedUserIds = DB::queryFirstColumn(
                    'SELECT DISTINCT ur.user_id FROM ' . prefixTable('users_roles') . ' ur
                    JOIN ' . prefixTable('roles_values') . ' rv ON ur.role_id = rv.role_id
                    WHERE rv.folder_id IN %ls',
                    $folderForDel
                );
            }

            // delete folders
            foreach ($folderForDel as $fol) {
                DB::delete(prefixTable('nested_tree'), 'id = %i', $fol);
            }

            // Invalidate cache for affected users
            invalidateCacheForFolderUsers(0, $affectedUserIds);

            // Commit transaction
            DB::commit();

            //rebuild tree
            $tree->rebuild();

            // Emit WebSocket events for deleted folders
            foreach ($foldersDeletedInfo as $deletedFolder) {
                emitFolderEvent(
                    'deleted',
                    (int) $deletedFolder['folder_id'],
                    $deletedFolder['title'],
                    $session->get('user-login') ?? '',
                    (int) $deletedFolder['parent_id'],
                    (int) $session->get('user-id')
                );
            }

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
            $post_copy_subdirectories = filter_var($dataReceived['copy_subdirectories'], FILTER_SANITIZE_NUMBER_INT);
            $post_copy_items = filter_var($dataReceived['copy_items'], FILTER_SANITIZE_NUMBER_INT);

            // Test if source folder is Read-only — user cannot move a folder they can only read
            if (in_array((int) $post_source_folder_id, $session->get('user-read_only_folders')) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

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

            // Check if target parent folder is personal
            $dataParent = DB::queryFirstRow(
                'SELECT personal_folder
                FROM ' . prefixTable('nested_tree') . '
                WHERE id = %i',
                $post_target_folder_id
            );

            $isPersonal = (isset($dataParent['personal_folder']) === true && (int) $dataParent['personal_folder'] === 1) ? 1 : 0;

            // Check if user is allowed
            if (
                !(
                    (int) $isPersonal === 1
                    || (int) $session->get('user-admin') === 1
                    || (int) $session->get('user-manager') === 1
                    || (int) $session->get('user-can_manage_all_users') === 1
                    || (isset($SETTINGS['enable_user_can_create_folders']) === true
                        && (int) $SETTINGS['enable_user_can_create_folders'] == 1)
                    || (int) $session->get('user-can_create_root_folder') === 1
                )
            ) {
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
                $nodeComplexity = DB::queryFirstRow(
                    'SELECT valeur
                    FROM ' . prefixTable('misc') . '
                    WHERE intitule = %i AND type= %s',
                    $nodeInfo->id,
                    'complex'
                );

                // prepare parent Id - Always recalculate depending on the new parent ID of the node
                if (isset($tabNodes[$nodeInfo->parent_id])) {
                    // The parent has already been copied, use its new ID
                    $parentId = $tabNodes[$nodeInfo->parent_id];
                } else {
                    // The parent has not been copied (it's the first level), use the target
                    $parentId = $post_target_folder_id;
                }

                // Allocate personal folder flag
                $nodeInfo->personal_folder = $isPersonal;

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
                            DB::insertUpdate(
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
                    // Add access to this subfolder
                    DB::insertUpdate(
                        prefixTable('roles_values'),
                        array(
                            'role_id' => $record['role_id'],
                            'folder_id' => $newFolderId,
                            'type' => $record['type'],
                        )
                    );
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
                if ((int) $post_copy_items === 1) {
                    // get all items in this folder
                    // even the inactive ones
                    // because if user has access to the folder
                    // he should have access to all items in it

                    // We will use TP_USER in order to decrypt and crypt passwords
                    $userTpInfo = DB::queryFirstRow(
                        'SELECT u.pw, u.public_key, pk.private_key, u.login, u.name
                        FROM ' . prefixTable('users') . ' AS u
                        LEFT JOIN ' . prefixTable('user_private_keys') . ' AS pk ON (u.id = pk.user_id AND pk.is_current = 1)
                        WHERE u.id = %i',
                        TP_USER_ID
                    );
                    $decryptedData = cryption($userTpInfo['pw'], '', 'decrypt', $SETTINGS);
                    $userTpPwd = $decryptedData['string'] ?? '';
                    $userTpPrivateKey = decryptPrivateKey($userTpPwd, $userTpInfo['private_key']);
                    
                    $errorItems = [];
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
                            $itemUserTpKey = DB::queryFirstRow(
                                'SELECT share_key
                                FROM ' . prefixTable('sharekeys_items') . '
                                WHERE user_id = %i AND object_id = %i',
                                TP_USER_ID,
                                $record['id']
                            );
                            if (DB::count() > 0) {
                                // Decrypt / Encrypt the password
                                $cryptedStuff = doDataEncryption(
                                    base64_decode(
                                        doDataDecryption(
                                            $record['pw'],
                                            decryptUserObjectKey(
                                                $itemUserTpKey['share_key'],
                                                $userTpPrivateKey
                                            )
                                        )
                                    )
                                );                                

                                // Insert the new record and get the new auto_increment id
                                DB::insert(
                                    prefixTable('items'),
                                    array(
                                        'label' => substr($record['label'], 0, 500),
                                        'description' => empty($record['description']) === true ? '' : $record['description'],
                                        'id_tree' => $newFolderId,
                                        'pw' => $cryptedStuff['encrypted'],
                                        'pw_iv' => '',
                                        'url' => empty($record['url']) === true ? '' : substr($record['url'], 0, 500),
                                        'login' => empty($record['login']) === true ? '' : substr($record['login'], 0, 200),
                                        'viewed_no' => 0,
                                        'encryption_type' => 'teampass_aes',
                                        'item_key' => uniqidReal(50),
                                        'created_at' => time(),
                                    )
                                );
                                $newItemId = DB::insertId();

                                // Create task for the new item
                                storeTask(
                                    'item_copy',
                                    $session->get('user-id'),
                                    (int) $nodeInfo->personal_folder,
                                    (int) $newFolderId,
                                    (int) $newItemId,
                                    $cryptedStuff['objectKey'],
                                );

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

                                // Add item to cache table
                                updateCacheTable('add_value', (int) $newItemId);
                            }
                        }
                    }
                }

                // Exit loop if user does not want to copy subdirectories
                if ((int) $post_copy_subdirectories !== 1) {
                    break;
                }
            }

            // rebuild tree
            $tree->rebuild();

            // Invalidate cache for users with access to destination folder
            invalidateCacheForFolderUsers((int) $post_target_folder_id);

            $data = array(
                'error' => '',
                'errorItems' => !empty($errorItems) ? $errorItems : [],
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

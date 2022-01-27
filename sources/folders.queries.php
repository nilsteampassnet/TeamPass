<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      folders.queries.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2022 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] === false || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'folders', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit();
}

/*
 * Define Timezone
**/
if (isset($SETTINGS['timezone']) === true) {
    date_default_timezone_set($SETTINGS['timezone']);
} else {
    date_default_timezone_set('UTC');
}

require_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
require_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $_SESSION['user_language'] . '.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/SplClassLoader.php';

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Prepare post variables
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

// Ensure Complexity levels are translated
if (defined('TP_PW_COMPLEXITY') === false) {
    define(
        'TP_PW_COMPLEXITY',
        array(
            0 => array(0, langHdl('complex_level0'), 'fas fa-bolt text-danger'),
            25 => array(25, langHdl('complex_level1'), 'fas fa-thermometer-empty text-danger'),
            50 => array(50, langHdl('complex_level2'), 'fas fa-thermometer-quarter text-warning'),
            60 => array(60, langHdl('complex_level3'), 'fas fa-thermometer-half text-warning'),
            70 => array(70, langHdl('complex_level4'), 'fas fa-thermometer-three-quarters text-success'),
            80 => array(80, langHdl('complex_level5'), 'fas fa-thermometer-full text-success'),
            90 => array(90, langHdl('complex_level6'), 'far fa-gem text-success'),
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
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
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
                    in_array($t->id, $_SESSION['groupes_visibles']) === true
                    && in_array($t->id, $_SESSION['personal_visible_groups']) === false
                    && $t->personal_folder == 0
                ) {
                    // get $t->parent_id
                    $data = DB::queryFirstRow('SELECT title FROM ' . prefixTable('nested_tree') . ' WHERE id = %i', $t->parent_id);
                    if ($t->nlevel == 1) {
                        $data['title'] = langHdl('root');
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
                        n.bloquer_creation AS bloquer_creation, n.bloquer_modification AS bloquer_modification
                        FROM ' . prefixTable('misc') . ' AS m,
                        ' . prefixTable('nested_tree') . ' AS n
                        WHERE m.type=%s AND m.intitule = n.id AND m.intitule = %i',
                        'complex',
                        $t->id
                    );

                    // Preapre array of columns
                    $arrayColumns = array();

                    $arrayColumns['id'] = $t->id;
                    $arrayColumns['numOfChildren'] = $tree->numDescendants($t->id);
                    $arrayColumns['level'] = $t->nlevel;
                    $arrayColumns['parentId'] = $t->parent_id;
                    $arrayColumns['title'] = $t->title;
                    $arrayColumns['nbItems'] = DB::count();
                    $arrayColumns['path'] = $arrayPath;
                    $arrayColumns['parents'] = $arrayParents;

                    if (isset(TP_PW_COMPLEXITY[$node_data['valeur']][1]) === true) {
                        $arrayColumns['folderComplexity'] = array(
                            'text' => TP_PW_COMPLEXITY[$node_data['valeur']][1],
                            'value' => TP_PW_COMPLEXITY[$node_data['valeur']][0],
                            'class' => TP_PW_COMPLEXITY[$node_data['valeur']][2],
                        );
                    } else {
                        $arrayColumns['folderComplexity'] = '';
                    }

                    $arrayColumns['renewalPeriod'] = $node_data['renewal_period'];

                    //col7
                    $data7 = DB::queryFirstRow(
                        'SELECT bloquer_creation,bloquer_modification
                        FROM ' . prefixTable('nested_tree') . '
                        WHERE id = %i',
                        intval($t->id)
                    );
                    $arrayColumns['add_is_blocked'] = (int) $data7['bloquer_creation'];
                    $arrayColumns['edit_is_blocked'] = (int) $data7['bloquer_modification'];

                    array_push($arrData, $arrayColumns);
                }
            }

            // Send the complexity levels
            $complexity = array(
                array('value' => 0, 'text' => langHdl('complex_level0')),
                array('value' => 25, 'text' => langHdl('complex_level1')),
                array('value' => 50, 'text' => langHdl('complex_level2')),
                array('value' => 60, 'text' => langHdl('complex_level3')),
                array('value' => 70, 'text' => langHdl('complex_level4')),
                array('value' => 80, 'text' => langHdl('complex_level5')),
                array('value' => 90, 'text' => langHdl('complex_level6')),
            );

            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'message' => '',
                    'matrix' => $arrData,
                    'user_is_admin' => (int) $_SESSION['is_admin'],
                    'user_can_create_root_folder' => (int) $_SESSION['can_create_root_folder'],
                    'fullComplexity' => $complexity,
                ),
                'encode'
            );

            break;

            // CASE where selecting/deselecting sub-folders
        case 'select_sub_folders':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // get sub folders
            $subfolders = array();
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

            // Get through each subfolder
            $folders = $tree->getDescendants($post_id, false);
            foreach ($folders as $folder) {
                array_push($subfolders, (int) $folder->id);
            }

            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
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
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],$post_data, 'decode');

            // prepare variables
            $post_title = filter_var($dataReceived['title'], FILTER_SANITIZE_STRING);
            $post_parent_id = filter_var($dataReceived['parentId'], FILTER_SANITIZE_NUMBER_INT);
            $post_complexicity = filter_var($dataReceived['complexity'], FILTER_SANITIZE_NUMBER_INT);
            $post_folder_id = filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT);
            $post_renewal_period = isset($dataReceived['renewalPeriod']) === true ? filter_var($dataReceived['renewalPeriod'], FILTER_SANITIZE_STRING) : '';
            $post_add_restriction = isset($dataReceived['addRestriction']) === true ? filter_var($dataReceived['addRestriction'], FILTER_SANITIZE_NUMBER_INT) : '';
            $post_edit_restriction = isset($dataReceived['editRestriction']) === true ? filter_var($dataReceived['editRestriction'], FILTER_SANITIZE_NUMBER_INT) : '';

            // Init
            $error = false;
            $errorMessage = '';

            // check if title is numeric
            if (is_numeric($post_title) === true) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_only_numbers_in_folder_name'),
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
    $SETTINGS['cpassman_dir'],
                        array(
                            'error' => true,
                            'message' => langHdl('error_group_exist'),
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

            if ((int) $dataParent['personal_folder'] === 1) {
                $isPersonal = 1;
            } else {
                $isPersonal = 0;

                // check if complexity level is good
                // if manager or admin don't care
                if (
                    (int) $_SESSION['is_admin'] !== 1
                    && ((int) $_SESSION['user_manager'] !== 1
                        || (int) $_SESSION['user_can_manage_all_users'] !== 1)
                ) {
                    // get complexity level for this folder
                    $data = DB::queryfirstrow(
                        'SELECT valeur
                        FROM ' . prefixTable('misc') . '
                        WHERE intitule = %i AND type = %s',
                        $post_parent_id,
                        'complex'
                    );

                    if ((int) $post_complexicity < (int) $data['valeur']) {
                        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                            array(
                                'error' => true,
                                'message' => langHdl('error_folder_complexity_lower_than_top_folder')
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
            );
            if (
                $dataFolder['renewal_period'] !== $post_renewal_period
                && empty($post_renewal_period) === false
            ) {
                $folderParameters['renewal_period'] = $post_renewal_period;
            }
            if (
                $dataFolder['bloquer_creation'] !== $post_add_restriction
                && empty($post_add_restriction) === false
            ) {
                $folderParameters['bloquer_creation'] = $post_add_restriction;
            }
            if (
                $dataFolder['bloquer_modification'] !== $post_edit_restriction
                && empty($post_edit_restriction) === false
            ) {
                $folderParameters['bloquer_modification'] = $post_edit_restriction;
            }

            // Now update
            DB::update(
                prefixTable('nested_tree'),
                $folderParameters,
                'id=%i',
                $dataFolder['id']
            );

            //Add complexity
            DB::update(
                prefixTable('misc'),
                array(
                    'valeur' => $post_complexicity,
                ),
                'intitule = %s AND type = %s',
                $dataFolder['id'],
                'complex'
            );

            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $tree->rebuild();

            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
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
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],$post_data, 'decode');

            // prepare variables
            $post_title = filter_var($dataReceived['title'], FILTER_SANITIZE_STRING);
            $post_parent_id = filter_var($dataReceived['parentId'], FILTER_SANITIZE_NUMBER_INT);
            $post_complexicity = filter_var($dataReceived['complexity'], FILTER_SANITIZE_NUMBER_INT);
            $post_duration = isset($dataReceived['renewalPeriod']) === true ? filter_var($dataReceived['renewalPeriod'], FILTER_SANITIZE_NUMBER_INT) : 0;
            $post_create_auth_without = isset($dataReceived['renewalPeriod']) === true ? filter_var($dataReceived['addRestriction'], FILTER_SANITIZE_NUMBER_INT) : 0;
            $post_edit_auth_without = isset($dataReceived['renewalPeriod']) === true ? filter_var($dataReceived['editRestriction'], FILTER_SANITIZE_NUMBER_INT) : 0;

            // Init
            $error = false;
            $newId = $access_level_by_role = $errorMessage = '';

            //if ((int) $post_parent_id === 0) {
                if (isset($dataReceived['accessRight']) === true) {
                    $access_level_by_role = filter_var($dataReceived['accessRight'], FILTER_SANITIZE_STRING);
                } else {
                    if (
                        (int) $_SESSION['user_manager'] === 1
                        || (int) $_SESSION['user_can_manage_all_users'] === 1
                    ) {
                        $access_level_by_role = 'W';
                    } else {
                        $access_level_by_role = '';
                    }
                }
            //}

            // check if title is numeric
            if (is_numeric($post_title) === true) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_only_numbers_in_folder_name'),
                    ),
                    'encode'
                );
                break;
            }
            // Check if parent folder is allowed for this user
            if (
                in_array($post_parent_id, $_SESSION['groupes_visibles']) === false
                && (int) $_SESSION['is_admin'] !== 1
            ) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            //Check if duplicate folders name are allowed
            if (
                isset($SETTINGS['duplicate_folder']) === true
                && (int) $SETTINGS['duplicate_folder'] === 0
            ) {
                DB::query(
                    'SELECT *
                    FROM ' . prefixTable('nested_tree') . '
                    WHERE title = %s',
                    $post_title
                );
                $counter = DB::count();
                if ($counter !== 0) {
                    echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                        array(
                            'error' => true,
                            'message' => langHdl('error_group_exist'),
                        ),
                        'encode'
                    );
                    break;
                }
            }

            //check if parent folder is personal
            $data = DB::queryfirstrow(
                'SELECT personal_folder, bloquer_creation, bloquer_modification
                FROM ' . prefixTable('nested_tree') . '
                WHERE id = %i',
                $post_parent_id
            );

            // inherit from parent the specific settings it has
            if (DB::count() > 0) {
                $parentBloquerCreation = $data['bloquer_creation'];
                $parentBloquerModification = $data['bloquer_modification'];
            } else {
                $parentBloquerCreation = 0;
                $parentBloquerModification = 0;
            }

            if (isset($data) === true && (int) $data['personal_folder'] === 1) {
                $isPersonal = 1;
            } else {
                $isPersonal = 0;

                // check if complexity level is good
                // if manager or admin don't care
                if (
                    $_SESSION['is_admin'] != 1
                    && ((int) $_SESSION['user_manager'] !== 1
                        || (int) $_SESSION['user_can_manage_all_users'] !== 1)
                ) {
                    // get complexity level for this folder
                    $data = DB::queryfirstrow(
                        'SELECT valeur
                        FROM ' . prefixTable('misc') . '
                        WHERE intitule = %i AND type = %s',
                        $post_parent_id,
                        'complex'
                    );
                    if (intval($post_complexicity) < intval($data['valeur'])) {
                        echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                            array(
                                'error' => true,
                                'message' => langHdl('error_folder_complexity_lower_than_top_folder')
                                    . ' [<b>' . TP_PW_COMPLEXITY[$data['valeur']][1] . '</b>]',
                            ),
                            'encode'
                        );
                        break;
                    }
                }
            }

            if (
                (int) $isPersonal === 1
                || (int) $_SESSION['is_admin'] === 1
                || ((int) $_SESSION['user_manager'] === 1 || (int) $_SESSION['user_can_manage_all_users'] === 1)
                || (isset($SETTINGS['enable_user_can_create_folders']) === true
                    && (int) $SETTINGS['enable_user_can_create_folders'] == 1)
                || (isset($_SESSION['can_create_root_folder']) === true && (int) $_SESSION['can_create_root_folder'] === 1)
            ) {
                //create folder
                DB::insert(
                    prefixTable('nested_tree'),
                    array(
                        'parent_id' => $post_parent_id,
                        'title' => $post_title,
                        'personal_folder' => $isPersonal,
                        'renewal_period' => $post_duration,
                        'bloquer_creation' => isset($post_create_auth_without) === true && (int) $post_create_auth_without === 1 ? '1' : $parentBloquerCreation,
                        'bloquer_modification' => isset($post_edit_auth_without) === true && (int) $post_edit_auth_without === 1 ? '1' : $parentBloquerModification,
                    )
                );
                $newId = DB::insertId();

                //Add complexity
                DB::insert(
                    prefixTable('misc'),
                    array(
                        'type' => 'complex',
                        'intitule' => $newId,
                        'valeur' => $post_complexicity,
                    )
                );

                // add new folder id in SESSION
                array_push($_SESSION['groupes_visibles'], $newId);
                if ((int) $isPersonal === 1) {
                    array_push($_SESSION['personal_folders'], $newId);
                }

                // rebuild tree
                $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
                $tree->rebuild();

                if ((int) $isPersonal !== 1
                    //&& (int) $post_parent_id === 0
                ) {
                    //add access to this new folder
                    foreach (explode(';', $_SESSION['fonction_id']) as $role) {
                        if (empty($role) === false && empty($access_level_by_role) === false) {
                            DB::insert(
                                prefixTable('roles_values'),
                                array(
                                    'role_id' => $role,
                                    'folder_id' => $newId,
                                    'type' => $access_level_by_role,
                                )
                            );
                        }
                    }
                }

                if (
                    isset($SETTINGS['subfolder_rights_as_parent']) === true
                    && (int) $SETTINGS['subfolder_rights_as_parent'] === 1
                ) {
                    //If it is a subfolder, then give access to it for all roles that allows the parent folder
                    $rows = DB::query('SELECT role_id, type FROM ' . prefixTable('roles_values') . ' WHERE folder_id = %i', $post_parent_id);
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
                }

                // if parent folder has Custom Fields Categories then add to this child one too
                $rows = DB::query('SELECT id_category FROM ' . prefixTable('categories_folders') . ' WHERE id_folder = %i', $post_parent_id);
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
            } else {
                $error = true;
                $errorMessage = langHdl('error_not_allowed_to');
            }

            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => $error,
                    'message' => $errorMessage,
                    'newId' => $newId,
                ),
                'encode'
            );

            break;

            // CASE where DELETING multiple groups
        case 'delete_folders':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],$post_data, 'decode');

            // prepare variables
            $post_folders = filter_var_array(
                $dataReceived['selectedFolders'],
                FILTER_SANITIZE_STRING
            );

            //decrypt and retreive data in JSON format
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $folderForDel = array();

            foreach ($post_folders as $folderId) {
                // Exclude a folder with id alreay in the list
                if (in_array($folderId, $folderForDel) === false) {
                    // Get through each subfolder
                    $folders = $tree->getDescendants($folderId, true);
                    foreach ($folders as $folder) {
                        if (($folder->parent_id > 0 || $folder->parent_id == 0)
                            && $folder->title != $_SESSION['user_id']
                        ) {
                            //Store the deleted folder (recycled bin)
                            DB::insert(
                                prefixTable('misc'),
                                array(
                                    'type' => 'folder_deleted',
                                    'intitule' => 'f' . $folder->id,
                                    'valeur' => $folder->id . ', ' . $folder->parent_id . ', ' .
                                        $folder->title . ', ' . $folder->nleft . ', ' . $folder->nright . ', ' .
                                        $folder->nlevel . ', 0, 0, 0, 0',
                                )
                            );
                            //array for delete folder
                            $folderForDel[] = $folder->id;

                            //delete items & logs
                            $items = DB::query('SELECT id FROM ' . prefixTable('items') . ' WHERE id_tree=%i', $folder->id);
                            foreach ($items as $item) {
                                DB::update(
                                    prefixTable('items'),
                                    array(
                                        'inactif' => '1',
                                    ),
                                    'id = %i',
                                    $item['id']
                                );
                                //log
                                DB::insert(
                                    prefixTable('log_items'),
                                    array(
                                        'id_item' => $item['id'],
                                        'date' => time(),
                                        'id_user' => $_SESSION['user_id'],
                                        'action' => 'at_delete',
                                    )
                                );
                                // log
                                logItems(
                                    $SETTINGS,
                                    (int) $item['id'],
                                    '',
                                    $_SESSION['user_id'],
                                    'at_delete',
                                    $_SESSION['login']
                                );

                                // delete folder from SESSION
                                if (array_search($item['id'], $_SESSION['groupes_visibles']) !== false) {
                                    unset($_SESSION['groupes_visibles'][$item['id']]);
                                }

                                //Update CACHE table
                                updateCacheTable('delete_value', $SETTINGS, $item['id']);
                            }

                            //Actualize the variable
                            --$_SESSION['nb_folders'];
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
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $tree->rebuild();

            // reload cache table
            include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
            updateCacheTable('reload', $SETTINGS, null);

            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );

            break;

        case 'copy_folder':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],$post_data, 'decode');

            // Init post variables
            $post_source_folder_id = filter_var($dataReceived['source_folder_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_target_folder_id = filter_var($dataReceived['target_folder_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_folder_label = filter_var($dataReceived['folder_label'], FILTER_SANITIZE_STRING);

            //Load Tree
            $tree = new SplClassLoader('Tree\NestedTree', './includes/libraries');
            $tree->register();
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

            // Test if target folder is Read-only
            // If it is then stop
            if (in_array($post_target_folder_id, $_SESSION['read_only_folders']) === true) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // Get all allowed folders
            $array_all_visible_folders = array_merge(
                $_SESSION['groupes_visibles'],
                $_SESSION['read_only_folders'],
                $_SESSION['personal_visible_groups']
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
                        'valeur' => $nodeComplexity['valeur'],
                    )
                );

                // add new folder id in SESSION
                array_push($_SESSION['groupes_visibles'], $newFolderId);
                if ((int) $nodeInfo->personal_folder === 1) {
                    array_push($_SESSION['personal_folders'], $newFolderId);
                    array_push($_SESSION['personal_visible_groups'], $newFolderId);
                } else {
                    array_push($_SESSION['all_non_personal_folders'], $newFolderId);
                }

                // If new folder should not heritate of parent rights
                // Then use the creator ones
                if (
                    (int) $nodeInfo->personal_folder !== 1
                    && isset($SETTINGS['subfolder_rights_as_parent']) === true
                    && (int) $SETTINGS['subfolder_rights_as_parent'] === 1
                    && (int) $_SESSION['is_admin'] !== 0
                ) {
                    //add access to this new folder
                    foreach (explode(';', $_SESSION['fonction_id']) as $role) {
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
                        || (int) $item_deleted['date'] < (int) $item_restored['date']
                    ) {
                        // Get the ITEM object key for the user
                        $userKey = DB::queryFirstRow(
                            'SELECT share_key
                            FROM ' . prefixTable('sharekeys_items') . '
                            WHERE user_id = %i AND object_id = %i',
                            $_SESSION['user_id'],
                            $record['id']
                        );
                        if (DB::count() === 0) {
                            // ERROR - No sharekey found for this item and user
                            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                                array(
                                    'error' => true,
                                    'message' => langHdl('error_not_allowed_to'),
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
                                        $_SESSION['user']['private_key']
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
                            $SETTINGS
                        );

                        // Generate the query to update the new record with the previous values
                        $aSet = array();
                        foreach ($record as $key => $value) {
                            if (
                                $key !== 'id' && $key !== 'key' && $key !== 'id_tree'
                                && $key !== 'viewed_no' && $key !== 'pw' && $key !== 'pw_iv'
                            ) {
                                array_push($aSet, array($key => $value));
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
                                $cryptedStuff = doDataEncryption($field['value']);
                            }

                            // store field text
                            DB::insert(
                                prefixTable('categories_items'),
                                array(
                                    'item_id' => $newItemId,
                                    'field_id' => $field['field_id'],
                                    'data' => (int) $field['encryption_type'] === TP_ENCRYPTION_NAME ?
                                        $cryptedStuff['encrypted'] : $field['value'],
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
                                    $SETTINGS
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
                            $_SESSION['user_id'],
                            $record['id']
                        );
                        foreach ($files as $file) {
                            // Check if file still exists
                            if (file_exists($SETTINGS['path_to_upload_folder'] . DIRECTORY_SEPARATOR . TP_FILE_PREFIX . base64_decode($file['file'])) === true) {
                                // Step1 - decrypt the file
                                $fileContent = decryptFile(
                                    $file['file'],
                                    $SETTINGS['path_to_upload_folder'],
                                    decryptUserObjectKey($file['share_key'], $_SESSION['user']['private_key'])
                                );

                                // Step2 - create file
                                $newFileName = md5(time() . '_' . $file['id']) . '.' . $file['extension'];

                                $outstream = fopen($SETTINGS['path_to_upload_folder'] . DIRECTORY_SEPARATOR . $newFileName, 'ab');
                                if ($outstream === false) {
                                    echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                                        array(
                                            'error' => true,
                                            'message' => langHdl('error_cannot_open_file'),
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
                                    $SETTINGS
                                );
                            }
                        }
                        // <---

                        // Add this duplicate in logs
                        logItems(
                            $SETTINGS,
                            (int) $newItemId,
                            $record['label'],
                            $_SESSION['user_id'],
                            'at_creation',
                            $_SESSION['login']
                        );
                        // Add the fact that item has been copied in logs
                        logItems(
                            $SETTINGS,
                            (int) $newItemId,
                            $record['label'],
                            $_SESSION['user_id'],
                            'at_copy',
                            $_SESSION['login']
                        );
                    }
                }
            }

            // rebuild tree
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $tree->rebuild();

            // reload cache table
            include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
            updateCacheTable('reload', $SETTINGS, NULL);

            $data = array(
                'error' => '',
            );

            // send data
            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],$data, 'encode');

            break;

            // CASE where selecting/deselecting sub-folders
        case 'refresh_folders_list':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            $subfolders = array();

            if ((int) $_SESSION['is_admin'] === 1 || (int) $_SESSION['user_manager'] === 1 || (int) $_SESSION['can_create_root_folder'] === 1) {
                array_push(
                    $subfolders,
                    array(
                        'id' => 0,
                        'label' => langHdl('root'),
                        'level' => 0,
                        'path' => ''
                    )
                );
            }

            // get sub folders
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

            // Get through each subfolder
            $folders = $tree->getDescendants(0, false);
            foreach ($folders as $folder) {
                if (
                    in_array($folder->id, $_SESSION['groupes_visibles']) === true
                    && in_array($folder->id, $_SESSION['personal_visible_groups']) === false
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
    $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'subfolders' => ($subfolders),
                ),
                'encode'
            );

            break;
    }
}

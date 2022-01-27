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
 * @file      roles.queries.php
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
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'roles', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
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

require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Prepare post variables
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

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
            $post_role_id = filter_input(INPUT_POST, 'role_id', FILTER_SANITIZE_NUMBER_INT);
            $arrData = array();

            //Display each folder with associated rights by role
            $descendants = $tree->getDescendants();
            foreach ($descendants as $node) {
                if (in_array($node->id, $_SESSION['groupes_visibles']) === true
                    && in_array($node->id, $_SESSION['personal_visible_groups']) === false
                ) {
                    $arrNode = array();
                    $arrNode['ident'] = (int) $node->nlevel;
                    $arrNode['title'] = $node->title;
                    $arrNode['id'] = $node->id;

                    $arbo = $tree->getPath($node->id, false);
                    $parentClass = array();
                    foreach ($arbo as $elem) {
                        array_push($parentClass, $elem->title);
                    }
                    $arrNode['path'] = $parentClass;

                    // Role access
                    $role_detail = DB::queryfirstrow(
                        'SELECT *
                        FROM '.prefixTable('roles_values').'
                        WHERE folder_id = %i AND role_id = %i',
                        $node->id,
                        $post_role_id
                    );

                    if (DB::count() > 0) {
                        $arrNode['access'] = $role_detail['type'];
                    } else {
                        $arrNode['access'] = 'none';
                    }

                    array_push($arrData, $arrNode);
                }
            }

            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'message' => '',
                    'matrix' => $arrData,
                ),
                'encode'
            );

            break;

        case 'change_access_right_on_folder':
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
                $SETTINGS['cpassman_dir'],
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_selectedFolders = filter_var_array($dataReceived['selectedFolders'], FILTER_SANITIZE_NUMBER_INT);
            $post_access = filter_var($dataReceived['access'], FILTER_SANITIZE_STRING);
            $post_roleId = filter_var($dataReceived['roleId'], FILTER_SANITIZE_NUMBER_INT);
            $post_propagate = filter_var($dataReceived['propagate'], FILTER_SANITIZE_NUMBER_INT);

            // Loop on selection
            foreach ($post_selectedFolders as $folderId) {
                // delete
                DB::delete(
                    prefixTable('roles_values'),
                    'folder_id = %i AND role_id = %i',
                    $folderId,
                    $post_roleId
                );

                //Store in DB
                DB::insert(
                    prefixTable('roles_values'),
                    array(
                        'folder_id' => $folderId,
                        'role_id' => $post_roleId,
                        'type' => $post_access,
                    )
                );

                // Manage descendants
                if ((int) $post_propagate === 1) {
                    $descendants = $tree->getDescendants($folderId);
                    foreach ($descendants as $node) {
                        // delete
                        DB::delete(
                            prefixTable('roles_values'),
                            'folder_id = %i AND role_id = %i',
                            $node->id,
                            $post_roleId
                        );

                        //Store in DB
                        DB::insert(
                            prefixTable('roles_values'),
                            array(
                                'folder_id' => $node->id,
                                'role_id' => $post_roleId,
                                'type' => $post_access,
                            )
                        );
                    }
                }
            }

            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );

            break;

        case 'change_role_definition':
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
                $SETTINGS['cpassman_dir'],
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_folderId = filter_var($dataReceived['folderId'], FILTER_SANITIZE_NUMBER_INT);
            $post_complexity = filter_var($dataReceived['complexity'], FILTER_SANITIZE_NUMBER_INT);
            $post_label = filter_var($dataReceived['label'], FILTER_SANITIZE_STRING);
            $post_allowEdit = filter_var($dataReceived['allowEdit'], FILTER_SANITIZE_NUMBER_INT);
            $post_action = filter_var($dataReceived['action'], FILTER_SANITIZE_STRING);

            // Init
            $return = array(
                'error' => false,
                'message' => '',
            );

            if ($post_action === 'edit_role') {
                //Check if role already exist : No similar roles
                DB::query(
                    'SELECT *
                    FROM '.prefixTable('roles_title').'
                    WHERE id = %i',
                    $post_folderId
                );
                $counter = DB::count();

                if ($counter > 0) {
                    DB::update(
                        prefixTable('roles_title'),
                        array(
                            'title' => $post_label,
                            'complexity' => $post_complexity,
                            'allow_pw_change' => $post_allowEdit,
                        ),
                        'id = %i',
                        $post_folderId
                    );
                } else {
                    // Adding new folder not possible as it exists
                    $return['error'] = true;
                    $return['message'] = langHdl('error_role_exist');
                }
            } elseif ($post_action === 'add_role') {
                //Check if role already exist : No similar roles
                DB::query(
                    'SELECT *
                    FROM '.prefixTable('roles_title').'
                    WHERE title = %s',
                    $post_label
                );
                $counter = DB::count();

                if ($counter === 0) {
                    // Adding new role is possible as it doesn't exist
                    DB::insert(
                        prefixTable('roles_title'),
                        array(
                            'title' => $post_label,
                            'complexity' => $post_complexity,
                            'allow_pw_change' => $post_allowEdit,
                            'creator_id' => $_SESSION['user_id'],
                        )
                    );
                    $role_id = DB::insertId();
                } else {
                    // Adding new folder not possible as it exists
                    $return['error'] = true;
                    $return['message'] = langHdl('error_role_exist');
                }
            } elseif ($post_action === 'edit_folder') {
                //Check if role already exist : No similar roles
                DB::query(
                    'SELECT *
                    FROM '.prefixTable('roles_title').'
                    WHERE title = %s AND id != %i',
                    $post_label,
                    $post_folderId
                );
                $counter = DB::count();

                if ($counter === 0) {
                    // Editing the folder
                    DB::update(
                        prefixTable('roles_title'),
                        array(
                            'title' => $post_label,
                            'complexity' => $post_complexity,
                            'allow_pw_change' => $post_allowEdit,
                        ),
                        'id = %i',
                        $post_folderId
                    );
                } else {
                    // Adding new folder not possible as it exists
                    $return['error'] = true;
                    $return['message'] = langHdl('error_role_exist');
                }
            } elseif ($post_action === 'add_folder') {
                //Check if role already exist : No similar roles
                DB::query(
                    'SELECT *
                    FROM '.prefixTable('roles_title').'
                    WHERE title = %s',
                    $post_label
                );
                $counter = DB::count();

                if ($counter === 0) {
                    // Adding new folder is possible as it doesn't exist
                    DB::insert(
                        prefixTable('roles_title'),
                        array(
                            'title' => $post_label,
                            'complexity' => $post_complexity,
                            'allow_pw_change' => $post_allowEdit,
                            'creator_id' => $_SESSION['user_id'],
                        )
                    );
                    $role_id = DB::insertId();

                    if ($role_id !== 0) {
                        //Actualize the variable
                        ++$_SESSION['nb_roles'];

                        // get some data
                        $data_tmp = DB::queryfirstrow(
                            'SELECT fonction_id FROM '.prefixTable('users').' WHERE id = %s',
                            $_SESSION['user_id']
                        );

                        // add new role to user
                        $tmp = str_replace(';;', ';', $data_tmp['fonction_id']);
                        if (substr($tmp, -1) == ';') {
                            $_SESSION['fonction_id'] = str_replace(';;', ';', $data_tmp['fonction_id'].$role_id);
                        } else {
                            $_SESSION['fonction_id'] = str_replace(';;', ';', $data_tmp['fonction_id'].';'.$role_id);
                        }
                        // store in DB
                        DB::update(
                            prefixTable('users'),
                            [
                                'fonction_id' => $_SESSION['fonction_id'],
                            ],
                            'id = %i',
                            $_SESSION['user_id']
                        );
                        $_SESSION['user_roles'] = explode(';', $_SESSION['fonction_id']);

                        $return['new_role_id'] = $role_id;
                    }
                } else {
                    // Editing the folder not possible as it doesn't exist
                    $return['error'] = true;
                    $return['message'] = langHdl('role_not_exist');
                }
            } else {
                // Error
                $return['error'] = true;
                $return['message'] = langHdl('error_unknown');
            }

            // Prepare returned values
            define(
                'TP_PW_COMPLEXITY',
                [
                    0 => array(0, langHdl('complex_level0'), 'fas fa-bolt text-danger'),
                    25 => array(25, langHdl('complex_level1'), 'fas fa-thermometer-empty text-danger'),
                    50 => array(50, langHdl('complex_level2'), 'fas fa-thermometer-quarter text-warning'),
                    60 => array(60, langHdl('complex_level3'), 'fas fa-thermometer-half text-warning'),
                    70 => array(70, langHdl('complex_level4'), 'fas fa-thermometer-three-quarters text-success'),
                    80 => array(80, langHdl('complex_level5'), 'fas fa-thermometer-full text-success'),
                    90 => array(90, langHdl('complex_level6'), 'far fa-gem text-success'),
                ]
            );

            $return = array_merge(
                $return,
                [
                    'icon' => TP_PW_COMPLEXITY[$post_complexity][2],
                    'text' => TP_PW_COMPLEXITY[$post_complexity][1],
                    'value' => TP_PW_COMPLEXITY[$post_complexity][0],
                    'allow_pw_change' => $post_allowEdit,
                ]
            );

            // send data
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                $return,
                'encode'
            );
            break;

        case 'delete_role':
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

            // Prepare variables
            $post_roleId = filter_var($dataReceived['roleId'], FILTER_SANITIZE_NUMBER_INT);

            // Delete roles
            DB::delete(
                prefixTable('roles_title'),
                'id = %i',
                $post_roleId
            );
            DB::delete(
                prefixTable('roles_values'),
                'role_id = %i',
                $post_roleId
            );

            //Actualize the variable
            --$_SESSION['nb_roles'];

            // parse all users to remove this role
            $rows = DB::query(
                'SELECT id, fonction_id FROM '.prefixTable('users').'
                ORDER BY id ASC'
            );
            foreach ($rows as $record) {
                $tab = explode(';', $record['fonction_id']);
                $key = array_search($post_roleId, $tab);
                if ($key !== false) {
                    // remove the deleted role id
                    unset($tab[$key]);

                    // store new list of functions
                    DB::update(
                        prefixTable('users'),
                        [
                            'fonction_id' => rtrim(implode(';', $tab), ';'),
                        ],
                        'id = %i',
                        $record['id']
                    );
                }
            }

            // send data
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                [
                    'error' => false,
                    'message' => '',
                ],
                'encode'
            );
            break;

        case 'load_rights_for_compare':
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
            $post_role_id = filter_input(INPUT_POST, 'role_id', FILTER_SANITIZE_NUMBER_INT);
            $arrData = array();

            //Display each folder with associated rights by role
            $descendants = $tree->getDescendants();
            foreach ($descendants as $node) {
                if (in_array($node->id, $_SESSION['groupes_visibles']) === true
                    && in_array($node->id, $_SESSION['personal_visible_groups']) === false
                ) {
                    $arrNode = array();
                    $arrNode['ident'] = (int) $node->nlevel;
                    $arrNode['title'] = $node->title;
                    $arrNode['id'] = $node->id;

                    $arbo = $tree->getPath($node->id, false);
                    $parentClass = array();
                    foreach ($arbo as $elem) {
                        array_push($parentClass, $elem->title);
                    }
                    $arrNode['path'] = $parentClass;

                    // Role access
                    $role_detail = DB::queryfirstrow(
                        'SELECT *
                        FROM '.prefixTable('roles_values').'
                        WHERE folder_id = %i AND role_id = %i',
                        $node->id,
                        $post_role_id
                    );

                    if (DB::count() > 0) {
                        $arrNode['access'] = $role_detail['type'];
                    } else {
                        $arrNode['access'] = 'none';
                    }

                    array_push($arrData, $arrNode);
                }
            }

            // send data
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
            break;
    }
}

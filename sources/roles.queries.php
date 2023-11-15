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
 * @copyright 2009-2023 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */

use LdapRecord\Connection;
use LdapRecord\Container;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;


// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language(); 
session_name('teampass_session');
session_start();

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => returnIfSet($superGlobal->get('type', 'POST')),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($superGlobal->get('user_id', 'SESSION'), null),
        'user_key' => returnIfSet($superGlobal->get('key', 'SESSION'), null),
        'CPM' => returnIfSet($superGlobal->get('CPM', 'SESSION'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->userAccessPage('roles') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $superGlobal->put('code', ERR_NOT_ALLOWED, 'SESSION', 'error');
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

if (null !== $post_type) {
    switch ($post_type) {
        /*
         * BUILD liste of folders
         */
        case 'build_matrix':
            // Check KEY
            if ($post_key !== $superGlobal->get('key', 'SESSION')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
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
            if ($post_key !== $superGlobal->get('key', 'SESSION')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
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

            // Prepare variables
            $post_selectedFolders = filter_var_array($dataReceived['selectedFolders'], FILTER_SANITIZE_NUMBER_INT);
            $post_access = filter_var($dataReceived['access'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_roleId = filter_var($dataReceived['roleId'], FILTER_SANITIZE_NUMBER_INT);
            $post_propagate = filter_var($dataReceived['propagate'], FILTER_SANITIZE_NUMBER_INT);

            // Loop on selection
            foreach ($post_selectedFolders as $folderId) {
                // delete
                //db::debugmode(true);
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

            /*
            // update folders rights for users in cache_tree
            // Requested for real-time changes
            $rows = DB::query(
                'SELECT increment_id, folders
                FROM ' . prefixTable('cache_tree'),
            );

            foreach($rows as $row) {
                if ($row['folders'] === '' || $post_selectedFolders === null) {
                    continue;
                }
                // get visible folders
                $arr = json_decode($row['folders'], true);
                
                foreach($arr as $folder) {
                    if (in_array($folder, $post_selectedFolders) === true) {
                        unset($arr[$folder]);
                    }
                }
                print_r($arr);

                // update
                /*DB::update(
                    prefixTable('cache_tree'),
                    array(
                        'folders' => json_encode($arr),
                    ),
                    'increment_id = %i',
                    $row['increment_id']
                );*/
                /*
            }
            */

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );

            break;

        case 'change_role_definition':
            // Check KEY
            if ($post_key !== $superGlobal->get('key', 'SESSION')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
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
            
            // Prepare variables
            $post_folderId = filter_var($dataReceived['folderId'], FILTER_SANITIZE_NUMBER_INT);
            $post_complexity = filter_var($dataReceived['complexity'], FILTER_SANITIZE_NUMBER_INT);
            $post_label = filter_var($dataReceived['label'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_allowEdit = filter_var($dataReceived['allowEdit'], FILTER_SANITIZE_NUMBER_INT);
            $post_action = filter_var($dataReceived['action'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

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
                    $return['message'] = $lang->get('error_role_exist');
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
                    $return['new_role_id'] = DB::insertId();
                } else {
                    // Adding new folder not possible as it exists
                    $return['error'] = true;
                    $return['message'] = $lang->get('error_role_exist');
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
                    $return['message'] = $lang->get('error_role_exist');
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
                    $return['message'] = $lang->get('role_not_exist');
                }
            } else {
                // Error
                $return['error'] = true;
                $return['message'] = $lang->get('error_unknown');
            }

            // Prepare returned values
            define(
                'TP_PW_COMPLEXITY',
                [
                    TP_PW_STRENGTH_1 => array(TP_PW_STRENGTH_1, $lang->get('complex_level1'), 'fas fa-thermometer-empty text-danger'),
                    TP_PW_STRENGTH_2 => array(TP_PW_STRENGTH_2, $lang->get('complex_level2'), 'fas fa-thermometer-quarter text-warning'),
                    TP_PW_STRENGTH_3 => array(TP_PW_STRENGTH_3, $lang->get('complex_level3'), 'fas fa-thermometer-half text-warning'),
                    TP_PW_STRENGTH_4 => array(TP_PW_STRENGTH_4, $lang->get('complex_level4'), 'fas fa-thermometer-three-quarters text-success'),
                    TP_PW_STRENGTH_5 => array(TP_PW_STRENGTH_5, $lang->get('complex_level5'), 'fas fa-thermometer-full text-success'),
                ]
            );

            // ensure categories are set
            handleFoldersCategories(
                []
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
                $return,
                'encode'
            );
            break;

        case 'delete_role':
            // Check KEY
            if ($post_key !== $superGlobal->get('key', 'SESSION')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
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

            // ensure categories are set
            handleFoldersCategories(
                []
            );

            // send data
            echo prepareExchangedData(
                [
                    'error' => false,
                    'message' => '',
                ],
                'encode'
            );
            break;

        case 'load_rights_for_compare':
            // Check KEY
            if ($post_key !== $superGlobal->get('key', 'SESSION')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
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
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
            break;

        /*
        * GET LDAP LIST OF GROUPS
        */
        case 'get_list_of_groups_in_ldap':
            // Check KEY
            if ($post_key !== $superGlobal->get('key', 'SESSION')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            if (empty($SETTINGS['ldap_group_object_filter']) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('ldap_group_object_filter'),
                    ),
                    'encode'
                );
                break;
            }

            // load libraries
            require_once __DIR__.'/../vendor/autoload.php';

            // Build ldap configuration array
            $config = [
                // Mandatory Configuration Options
                'hosts'            => explode(',', $SETTINGS['ldap_hosts']),
                'base_dn'          => $SETTINGS['ldap_bdn'],
                'username'         => $SETTINGS['ldap_username'],
                'password'         => $SETTINGS['ldap_password'],
            
                // Optional Configuration Options
                'port'             => $SETTINGS['ldap_port'],
                'use_ssl'          => (int) $SETTINGS['ldap_ssl'] === 1 ? true : false,
                'use_tls'          => (int) $SETTINGS['ldap_tls'] === 1 ? true : false,
                'version'          => 3,
                'timeout'          => 5,
                'follow_referrals' => false,
            
                // Custom LDAP Options
                'options' => [
                    // See: http://php.net/ldap_set_option
                    LDAP_OPT_X_TLS_REQUIRE_CERT => (isset($SETTINGS['ldap_tls_certiface_check']) ? $SETTINGS['ldap_tls_certiface_check'] : LDAP_OPT_X_TLS_HARD),
                ]
            ];

            $connection = new Connection($config);

            // Connect to LDAP
            try {
                $connection->connect();
            
            } catch (\LdapRecord\Auth\BindException $e) {
                $error = $e->getDetailedError();

                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => "Error : ".$error->getErrorCode()." - ".$error->getErrorMessage(). "<br>".$error->getDiagnosticMessage(),
                    ),
                    'encode'
                );
                break;
            }

            // prepare groups criteria
            $pattern = '/\((.*?)\)/'; // matches anything inside parentheses
            // finds all matches and saves them to $matches array
            preg_match_all(
                $pattern,
                $SETTINGS['ldap_group_object_filter'],
                $matches
            );
            $searchCriteria = [];
            foreach($matches[0] as $match) {
                $parts = [];
                if (!str_contains($match, ',')) {
                    $tmp = explode("=", trim($match, '()'));
                    $parts = [$tmp[0], '=', $tmp[1]];
                } else {
                    $parts = explode(",", trim($match, '()'));
                }
                $searchCriteria[] = $parts;
            }
            
            $retGroups = $connection->query()->where($searchCriteria)->get();

            // check if synched with roles in Teampass
            $retAD = [];
            foreach($retGroups as $key => $group) {
                // exists in Teampass
                $role_detail = DB::queryfirstrow(
                    'SELECT a.increment_id, a.role_id, r.title
                    FROM '.prefixTable('ldap_groups_roles').' AS a
                    INNER JOIN '.prefixTable('roles_title').' AS r ON r.id = a.role_id
                    WHERE ldap_group_id = %i',
                    $group[(isset($SETTINGS['ldap_guid_attibute']) === true && empty($SETTINGS['ldap_guid_attibute']) === false ? $SETTINGS['ldap_guid_attibute']: 'gidnumber')][0]
                );
                $counter = DB::count();

                if ($counter > 0) {
                    $retGroups[$key]['teampass_role_id'] = $role_detail['role_id'];
                } else {
                    $retGroups[$key]['teampass_role_id'] = 0;
                }
                
                array_push(
                    $retAD,
                    [
                        'ad_group_id' => (int) $group[(isset($SETTINGS['ldap_guid_attibute']) === true && empty($SETTINGS['ldap_guid_attibute']) === false ? $SETTINGS['ldap_guid_attibute'] : 'gidnumber')][0],
                        'ad_group_title' => $group['cn'][0],
                        'role_id' => $counter> 0 ? (int) $role_detail['role_id'] : -1,
                        'id' => $counter > 0 ? (int) $role_detail['increment_id'] : -1,
                        'role_title' => $counter > 0 ? $role_detail['title'] : '',
                    ]
                );
            }

            
            // Get all groups in Teampass
            $teampassRoles = array();
            $rows = DB::query('SELECT id,title FROM ' . prefixTable('roles_title'));
            foreach ($rows as $record) {
                array_push(
                    $teampassRoles,
                    array(
                        'id' => $record['id'],
                        'title' => $record['title']
                    )
                );
            }

            echo (string) prepareExchangedData(
                array(
                    'error' => false,
                    'teampass_groups' => $teampassRoles,
                    'ldap_groups' => $retAD,
                ), 
                'encode'
            );

            break;

        //
        case "map_role_with_adgroup":
            // Check KEY
            if ($post_key !== $superGlobal->get('key', 'SESSION')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
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

            // Prepare variables
            $post_role_id = filter_var($dataReceived['roleId'], FILTER_SANITIZE_NUMBER_INT);
            $post_adgroup_id = filter_var($dataReceived['adGroupId'], FILTER_SANITIZE_NUMBER_INT);
            $post_adgroup_label = filter_var($dataReceived['adGroupLabel'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $data = DB::queryfirstrow(
                'SELECT *
                FROM '.prefixTable('ldap_groups_roles').'
                WHERE ldap_group_id = %i',
                $post_adgroup_id
            );
            $counter = DB::count();

            if ($counter === 0) {
                // Adding new folder is possible as it doesn't exist
                DB::insert(
                    prefixTable('ldap_groups_roles'),
                    array(
                        'role_id' => $post_role_id,
                        'ldap_group_id' => $post_adgroup_id,
                        'ldap_group_label' => $post_adgroup_label,
                    )
                );
                $new_id = DB::insertId();
            } else {
                if ((int) $post_role_id === -1) {
                    // delete
                    DB::delete(
                        prefixTable('ldap_groups_roles'),
                        'increment_id = %i',
                        $data['increment_id']
                    );
                    $new_id = -1;
                } else {
                    // update
                    DB::update(
                        prefixTable('ldap_groups_roles'),
                        array(
                            'role_id' => $post_role_id,
                        ),
                        'increment_id = %i',
                        $data['increment_id']
                    );
                    $new_id = '';
                }
            }

            echo (string) prepareExchangedData(
                array(
                    'error' => false,
                    'newId' => $new_id,
                ), 
                'encode'
            );

            break;
    }
}


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
 * @file      roles.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use LdapRecord\Connection;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\LdapExtra\LdapExtra;
use TeampassClasses\LdapExtra\OpenLdapExtra;
use TeampassClasses\LdapExtra\ActiveDirectoryExtra;

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
    $checkUserAccess->userAccessPage('roles') === false ||
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
            $post_role_id = filter_input(INPUT_POST, 'role_id', FILTER_SANITIZE_NUMBER_INT);
            $arrData = array();

            //Display each folder with associated rights by role
            $descendants = $tree->getDescendants();
            foreach ($descendants as $node) {
                if (in_array($node->id, $session->get('user-accessible_folders')) === true
                    && in_array($node->id, $session->get('user-personal_visible_folders')) === false
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

                //Store in DB if "access" is not empty (no access to folder)
                if (!empty($post_access)) {
                    DB::insert(
                        prefixTable('roles_values'),
                        array(
                            'folder_id' => $folderId,
                            'role_id' => $post_roleId,
                            'type' => $post_access,
                        )
                    );
                }

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

                        //Store in DB if "access" is not empty (no access to folder)        
                        if (!empty($post_access)) {
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
                            'creator_id' => $session->get('user-id'),
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
                            'creator_id' => $session->get('user-id'),
                        )
                    );
                    $role_id = DB::insertId();

                    if ($role_id !== 0) {
                        //Actualize the variable
                        $session->set('user-nb_roles', $session->get('user-nb_roles') + 1);

                        // get some data
                        $data_tmp = DB::queryfirstrow(
                            'SELECT fonction_id FROM '.prefixTable('users').' WHERE id = %s',
                            $session->get('user-id')
                        );

                        // add new role to user
                        $tmp = $data_tmp['fonction_id'] . (substr($data_tmp['fonction_id'], -1) == ';' ? $role_id : ';' . $role_id);
                        $session->set('user-roles', str_replace(';;', ';', $tmp));

                        // store in DB
                        DB::update(
                            prefixTable('users'),
                            [
                                'fonction_id' => $session->get('user-roles'),
                            ],
                            'id = %i',
                            $session->get('user-id')
                        );
                        $session->set('user-roles_array', explode(';', $session->get('user-roles')));

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
            $session->set('user-nb_roles', $session->get('user-nb_roles') - 1);

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
            $post_role_id = filter_input(INPUT_POST, 'role_id', FILTER_SANITIZE_NUMBER_INT);
            $arrData = array();

            //Display each folder with associated rights by role
            $descendants = $tree->getDescendants();
            foreach ($descendants as $node) {
                if (in_array($node->id, $session->get('user-accessible_folders')) === true
                    && in_array($node->id, $session->get('user-personal_visible_folders')) === false
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
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }


            // Initialisation de la connexion LDAP et des paramètres
            $connection = null;
            $ldapExtra = null;
            $retAD = [];

            try {
                switch ($SETTINGS['ldap_type']) {
                    case 'ActiveDirectory':
                        $ldapExtra = new LdapExtra($SETTINGS);
                        $ldapConnection = $ldapExtra->establishLdapConnection();

                        // Create an instance of OpenLdapExtra and configure it
                        $openLdapExtra = new ActiveDirectoryExtra();
                        $groupsData = $openLdapExtra->getADGroups($ldapConnection, $SETTINGS);
                        break;
                    case 'OpenLDAP':
                        // Establish connection for OpenLDAP
                        $ldapExtra = new LdapExtra($SETTINGS);
                        $ldapConnection = $ldapExtra->establishLdapConnection();

                        // Create an instance of OpenLdapExtra and configure it
                        $openLdapExtra = new OpenLdapExtra();
                        $groupsData = $openLdapExtra->getADGroups($ldapConnection, $SETTINGS);
                        break;
                    default:
                        throw new Exception("Unsupported LDAP type: " . $SETTINGS['ldap_type']);
                }
            } catch (Exception $e) {
                if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                    error_log('TEAMPASS Error - ldap - '.$e->getMessage());
                }
                // deepcode ignore ServerLeak: No important data is sent and it is encrypted before sending
                echo prepareExchangedData(array(
                    'error' => true,
                    'message' => 'An error occurred.',
                ), 'encode');
                exit;
            }
            
            // Check the type of LDAP and perform actions based on that
            if ($groupsData['error']) {
                // Handle error
            } else {
                // Handle successful retrieval of groups
                // exists in Teampass
                foreach($groupsData['userGroups'] as $key => $group) {
                    $role_detail = DB::queryfirstrow(
                        'SELECT a.increment_id as increment_id, a.role_id as role_id, r.title as title
                        FROM '.prefixTable('ldap_groups_roles').' AS a
                        INNER JOIN '.prefixTable('roles_title').' AS r ON r.id = a.role_id
                        WHERE a.ldap_group_id = %s',
                        $key
                    );
                    $counter = DB::count();
                    
                    array_push(
                        $retAD,
                        [
                            'ad_group_id' => $key,
                            'ad_group_title' => $group['ad_group_title'],
                            'role_id' => ($counter > 0) ? (int) $role_detail['role_id'] : $group['role_id'],
                            'id' => ($counter > 0) ? (int) $role_detail['increment_id'] : $group['id'],
                            'role_title' => ($counter > 0) ? $role_detail['title'] : $group['role_title'],
                        ]
                    );
                }
            }
            
            // Get all groups in Teampass
            $teampassRoles = array();
            $rows = DB::query('SELECT id,title FROM ' . prefixTable('roles_title'));
            foreach ($rows as $record) {
                array_push(
                    $teampassRoles,
                    array(
                        'id' => (int) $record['id'],
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
            if ($post_key !== $session->get('key')) {
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
            $post_adgroup_id = filter_var($dataReceived['adGroupId'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_adgroup_label = filter_var($dataReceived['adGroupLabel'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $data = DB::queryfirstrow(
                'SELECT *
                FROM '.prefixTable('ldap_groups_roles').'
                WHERE ldap_group_id = %s',
                $post_adgroup_id
            );
            
            if ($data) {
                // exists in Teampass
                // update or delete
                if ((int) $post_role_id === -1) {
                    // delete
                    DB::delete(
                        prefixTable('ldap_groups_roles'),
                        'increment_id = %i',
                        $data['increment_id']
                    );
                    $new_id = -1;
                } else {
                    if (isset($data['increment_id']) === true) {
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
            } else {
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


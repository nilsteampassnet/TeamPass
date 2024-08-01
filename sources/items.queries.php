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
 * @file      items.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use voku\helper\AntiXSS;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use OTPHP\TOTP;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config if $SETTINGS not defined
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => null !== $request->request->get('type') ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key', 'SESSION'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->userAccessPage('items') === false ||
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
error_reporting(E_ERROR);
set_time_limit(0);

// --------------------------------- //


/*
 * Define Timezone
*/
if (isset($SETTINGS['timezone']) === true) {
    date_default_timezone_set($SETTINGS['timezone']);
} else {
    date_default_timezone_set('UTC');
}

require_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $session->get('user-language') . '.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');


// Prepare nestedTree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Load AntiXSS
$antiXss = new AntiXSS();

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

// Prepare POST variables
$data = [
    'type' => $request->request->filter('type', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'data' => $request->request->filter('data', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'key' => $request->request->filter('key', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'label' => $request->request->filter('label', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'status' => $request->request->filter('status', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'cat' => $request->request->filter('cat', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'receipt' => $request->request->filter('receipt', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'itemId' => $request->request->filter('item_id', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'folderId' => $request->request->filter('folder_id', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'id' => $request->request->filter('id', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'destination' => $request->request->filter('destination', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'source' => $request->request->filter('source', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'userId' => $request->request->filter('user_id', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'getType' => $request->query->get('type', ''),
    'getTerm' => $request->query->get('term', ''),
    'option' => $request->request->filter('option', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'fileSuffix' => $request->request->filter('file_suffix', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'context' => $request->request->filter('context', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'notifyType' => $request->request->filter('notify_type', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'timestamp' => $request->request->filter('timestamp', '', FILTER_SANITIZE_SPECIAL_CHARS),
    'itemKey' => $request->request->filter('item_key', '', FILTER_SANITIZE_SPECIAL_CHARS),
];

$filters = [
    'type' => 'trim|escape',
    'data' => 'trim|escape',
    'key' => 'trim|escape',
    'label' => 'trim|escape',
    'status' => 'trim|escape',
    'cat' => 'trim|escape',
    'receipt' => 'trim|escape',
    'itemId' => 'cast:integer',
    'folderId' => 'cast:integer',
    'id' => 'cast:integer',
    'destination' => 'cast:integer',
    'source' => 'cast:integer',
    'userId' => 'cast:integer',
    'getType' => 'trim|escape',
    'getTerm' => 'trim|escape',
    'option' => 'trim|escape',
    'fileSuffix' => 'trim|escape',
    'context' => 'trim|escape',
    'notifyType' => 'trim|escape',
    'timestamp' => 'cast:integer',
    'itemKey' => 'trim|escape',
];

$inputData = dataSanitizer(
    $data,
    $filters
);

// Do asked action
switch ($inputData['type']) {
    /*
    * CASE
    * creating a new ITEM
    */
    case 'new_item':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        if ($session->get('user-read_only') === 1) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        // init
        $returnValues = array();
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        if (is_array($dataReceived) === true && count($dataReceived) > 0) {
            // Prepare variables
            $post_anyone_can_modify = filter_var($dataReceived['anyone_can_modify'], FILTER_SANITIZE_NUMBER_INT);
            $post_complexity_level = filter_var($dataReceived['complexity_level'], FILTER_SANITIZE_NUMBER_INT);
            $post_description = $antiXss->xss_clean($dataReceived['description']);
            $post_diffusion_list = filter_var_array(
                $dataReceived['diffusion_list'],
                FILTER_SANITIZE_FULL_SPECIAL_CHARS
            );
            $post_diffusion_list_names = filter_var_array(
                $dataReceived['diffusion_list_names'],
                FILTER_SANITIZE_FULL_SPECIAL_CHARS
            );
            $post_email = filter_var(htmlspecialchars_decode($dataReceived['email']), FILTER_SANITIZE_EMAIL);
            $post_fields = filter_var_array(
                $dataReceived['fields'],
                FILTER_SANITIZE_FULL_SPECIAL_CHARS
            );
            $inputData['folderId'] = filter_var($dataReceived['folder'], FILTER_SANITIZE_NUMBER_INT);
            $post_folder_is_personal = filter_var($dataReceived['folder_is_personal'], FILTER_SANITIZE_NUMBER_INT);
            $inputData['label'] = filter_var($dataReceived['label'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_login = filter_var($dataReceived['login'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $post_password = htmlspecialchars_decode($dataReceived['pw']);
            $post_restricted_to = filter_var(
                $dataReceived['restricted_to'],
                FILTER_SANITIZE_FULL_SPECIAL_CHARS
            );
            $post_restricted_to = $post_restricted_to !== false ? json_decode($post_restricted_to) : '';
            $post_restricted_to_roles = filter_var(
                $dataReceived['restricted_to_roles'],
                FILTER_SANITIZE_FULL_SPECIAL_CHARS
            );
            $post_restricted_to_roles = $post_restricted_to_roles !== false ? json_decode($post_restricted_to_roles) : '';
            $post_tags = htmlspecialchars_decode($dataReceived['tags']);
            $post_template_id = filter_var($dataReceived['template_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_url = filter_var(htmlspecialchars_decode($dataReceived['url']), FILTER_SANITIZE_URL);
            $post_uploaded_file_id = filter_var($dataReceived['uploaded_file_id'], FILTER_SANITIZE_NUMBER_INT);
            $inputData['userId'] = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_to_be_deleted_after_date = isset($dataReceived['to_be_deleted_after_date']) === true ? filter_var($dataReceived['to_be_deleted_after_date'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';
            $post_to_be_deleted_after_x_views = filter_var($dataReceived['to_be_deleted_after_x_views'], FILTER_SANITIZE_NUMBER_INT);
            $post_fa_icon = isset($dataReceived['fa_icon']) === true ? filter_var($dataReceived['fa_icon'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';

            //-> DO A SET OF CHECKS
            // Perform a check in case of Read-Only user creating an item in his PF
            if ($session->get('user-read_only') === 1
                && (in_array($inputData['folderId'], $session->get('user-personal_folders')) === false
                || $post_folder_is_personal !== 1)
            ) {
                echo (string) prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to_access_this_folder'),
                    ),
                    'encode'
                );
                break;
            }

            // Is author authorized to create in this folder
            if (count($session->get('user-list_folders_limited')) > 0) {
                if (in_array($inputData['folderId'], array_keys($session->get('user-list_folders_limited'))) === false
                    && in_array($inputData['folderId'], $session->get('user-accessible_folders')) === false
                    && in_array($inputData['folderId'], $session->get('user-personal_folders')) === false
                ) {
                    echo (string) prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('error_not_allowed_to_access_this_folder'),
                        ),
                        'encode'
                    );
                    break;
                }
            } else {
                if (in_array($inputData['folderId'], $session->get('user-accessible_folders')) === false) {
                    echo (string) prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('error_not_allowed_to_access_this_folder'),
                        ),
                        'encode'
                    );
                    break;
                }
            }

            // perform a check in case of Read-Only user creating an item in his PF
            if (
                $session->get('user-read_only') === 1
                && in_array($inputData['folderId'], $session->get('user-personal_folders')) === false
            ) {
                echo (string) prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to_access_this_folder'),
                    ),
                    'encode'
                );
                break;
            }

            // is pwd empty?
            if (
                empty($post_password) === true
                && $session->has('user-create_item_without_password') && null !== $session->get('user-create_item_without_password')
                && (int) $session->get('user-create_item_without_password') !== 1
            ) {
                echo (string) prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('password_cannot_be_empty'),
                    ),
                    'encode'
                );
                break;
            }

            // Check length
            $strlen_post_password = strlen($post_password);
            if ($strlen_post_password > $SETTINGS['pwd_maximum_length']) {
                echo (string) prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('password_too_long'),
                    ),
                    'encode'
                );
                break;
            }

            // Need info in DB
            // About special settings
            $dataFolderSettings = DB::queryFirstRow(
                'SELECT bloquer_creation, bloquer_modification, personal_folder
                FROM ' . prefixTable('nested_tree') . ' 
                WHERE id = %i',
                $inputData['folderId']
            );
            $itemInfos = [];
            $itemInfos['personal_folder'] = $dataFolderSettings['personal_folder'];
            if ((int) $itemInfos['personal_folder'] === 1) {
                $itemInfos['no_complex_check_on_modification'] = 1;
                $itemInfos['no_complex_check_on_creation'] = 1;
            } else {
                $itemInfos['no_complex_check_on_modification'] = (int) $dataFolderSettings['bloquer_modification'];
                $itemInfos['no_complex_check_on_creation'] = (int) $dataFolderSettings['bloquer_creation'];
            }

            // Get folder complexity
            $folderComplexity = DB::queryfirstrow(
                'SELECT valeur
                FROM ' . prefixTable('misc') . '
                WHERE type = %s AND intitule = %i',
                'complex',
                $inputData['folderId']
            );
            $itemInfos['requested_folder_complexity'] = $folderComplexity !== null ? (int) $folderComplexity['valeur'] : 0;

            // Check COMPLEXITY
            if ($post_complexity_level < $itemInfos['requested_folder_complexity'] && $itemInfos['no_complex_check_on_creation'] === 0) {
                echo (string) prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_security_level_not_reached'),
                    ),
                    'encode'
                );
                break;
            }

            // ./ END

            // check if element doesn't already exist
            $itemExists = 0;
            $newID = '';
            $data = DB::queryfirstrow(
                'SELECT * FROM ' . prefixTable('items') . '
                WHERE label = %s AND inactif = %i',
                $inputData['label'],
                0
            );
            $counter = DB::count();
            if ($counter > 0) {
                $itemExists = 1;
            } else {
                $itemExists = 0;
            }

            // Manage case where item is personal.
            // In this case, duplication is allowed
            if (
                isset($SETTINGS['duplicate_item']) === true
                && (int) $SETTINGS['duplicate_item'] === 0
                && (int) $post_folder_is_personal === 1
                && isset($post_folder_is_personal) === true
            ) {
                $itemExists = 0;
            }

            if ((isset($SETTINGS['duplicate_item']) === true
                    && (int) $SETTINGS['duplicate_item'] === 0
                    && (int) $itemExists === 0)
                || (isset($SETTINGS['duplicate_item']) === true
                    && (int) $SETTINGS['duplicate_item'] === 1)
            ) {
                // Handle case where pw is empty
                // if not allowed then warn user
                if (($session->has('user-create_item_without_password') && $session->has('user-create_item_without_password') && null !== $session->get('user-create_item_without_password')
                        && (int) $session->get('user-create_item_without_password') !== 1) ||
                    empty($post_password) === false ||
                    (int) $post_folder_is_personal === 1
                ) {
                    // NEW ENCRYPTION
                    $cryptedStuff = doDataEncryption($post_password);
                } else {
                    $cryptedStuff['encrypted'] = '';
                    $cryptedStuff['objectKey'] = '';
                }

                $post_password = $cryptedStuff['encrypted'];
                $post_password_key = $cryptedStuff['objectKey'];
                $itemFilesForTasks = [];
                $itemFieldsForTasks = [];
                
                // ADD item
                DB::insert(
                    prefixTable('items'),
                    array(
                        'label' => $inputData['label'],
                        'description' => $post_description,
                        'pw' => $post_password,
                        'pw_iv' => '',
                        'pw_len' => $strlen_post_password,
                        'email' => $post_email,
                        'url' => $post_url,
                        'id_tree' => $inputData['folderId'],
                        'login' => $post_login,
                        'inactif' => 0,
                        'restricted_to' => empty($post_restricted_to) === true ?
                            '' : (is_array($post_restricted_to) === true ? implode(';', $post_restricted_to) : $post_restricted_to),
                        'perso' => (isset($post_folder_is_personal) === true && (int) $post_folder_is_personal === 1) ?
                            1 : 0,
                        'anyone_can_modify' => (isset($post_anyone_can_modify) === true
                            && $post_anyone_can_modify === 'on') ? 1 : 0,
                        'complexity_level' => $post_complexity_level,
                        'encryption_type' => 'teampass_aes',
                        'fa_icon' => $post_fa_icon,
                        'item_key' => uniqidReal(50),
                        'created_at' => time(),
                    )
                );
                $newID = DB::insertId();

                // Create sharekeys for the user itself
                storeUsersShareKey(
                    prefixTable('sharekeys_items'),
                    (int) $post_folder_is_personal,
                    (int) $inputData['folderId'],
                    (int) $newID,
                    $cryptedStuff['objectKey'],
                    true,   // only for the item creator
                    false,  // no delete all
                );

                // update fields
                if (
                    isset($SETTINGS['item_extra_fields']) === true
                    && (int) $SETTINGS['item_extra_fields'] === 1
                ) {
                    foreach ($post_fields as $field) {
                        if (empty($field['value']) === false) {
                            // should we encrypt the data
                            $dataTmp = DB::queryFirstRow(
                                'SELECT encrypted_data
                                FROM ' . prefixTable('categories') . '
                                WHERE id = %i',
                                $field['id']
                            );

                            // Should we encrypt the data
                            if ((int) $dataTmp['encrypted_data'] === 1) {
                                // Create sharekeys for users
                                $cryptedStuff = doDataEncryption($field['value']);

                                // Store value
                                DB::insert(
                                    prefixTable('categories_items'),
                                    array(
                                        'item_id' => $newID,
                                        'field_id' => $field['id'],
                                        'data' => $cryptedStuff['encrypted'],
                                        'data_iv' => '',
                                        'encryption_type' => TP_ENCRYPTION_NAME,
                                    )
                                );
                                $newObjectId = DB::insertId();
            
                                // Create sharekeys for user
                                storeUsersShareKey(
                                    prefixTable('sharekeys_fields'),
                                    (int) $post_folder_is_personal,
                                    (int) $inputData['folderId'],
                                    (int) $newObjectId,
                                    $cryptedStuff['objectKey'],
                                    true,   // only for the item creator
                                    false,  // no delete all
                                );

                                array_push(
                                    $itemFieldsForTasks,
                                    [
                                        'object_id' => $newObjectId,
                                        'object_key' => $cryptedStuff['objectKey'],
                                    ]
                                );
                                
                            } else {
                                // update value
                                DB::insert(
                                    prefixTable('categories_items'),
                                    array(
                                        'item_id' => $newID,
                                        'field_id' => $field['id'],
                                        'data' => $field['value'],
                                        'data_iv' => '',
                                        'encryption_type' => 'not_set',
                                    )
                                );
                            }
                        }
                    }
                }

                // If template enable, is there a main one selected?
                if (
                    isset($SETTINGS['item_creation_templates']) === true
                    && (int) $SETTINGS['item_creation_templates'] === 1
                    && isset($post_template_id) === true
                    && empty($post_template_id) === false
                ) {
                    DB::queryFirstRow(
                        'SELECT *
                        FROM ' . prefixTable('templates') . '
                        WHERE item_id = %i',
                        $newID
                    );
                    if (DB::count() === 0) {
                        // store field text
                        DB::insert(
                            prefixTable('templates'),
                            array(
                                'item_id' => $newID,
                                'category_id' => $post_template_id,
                            )
                        );
                    } else {
                        // Delete if empty
                        if (empty($post_template_id) === true) {
                            DB::delete(
                                prefixTable('templates'),
                                'item_id = %i',
                                $newID
                            );
                        } else {
                            // Update value
                            DB::update(
                                prefixTable('templates'),
                                array(
                                    'category_id' => $post_template_id,
                                ),
                                'item_id = %i',
                                $newID
                            );
                        }
                    }
                }

                // If automatic deletion asked
                if (
                    isset($SETTINGS['enable_delete_after_consultation']) === true
                    && (int) $SETTINGS['enable_delete_after_consultation'] === 1
                    && is_null($post_to_be_deleted_after_x_views) === false
                    && is_null($post_to_be_deleted_after_date) === false
                ) {
                    if (
                        empty($post_to_be_deleted_after_date) === false
                        || $post_to_be_deleted_after_x_views > 0
                    ) {
                        // Automatic deletion to be added
                        DB::insert(
                            prefixTable('automatic_del'),
                            array(
                                'item_id' => $newID,
                                'del_enabled' => 1,
                                'del_type' => $post_to_be_deleted_after_x_views > 0 ? 1 : 2, //1 = numeric : 2 = date
                                'del_value' => $post_to_be_deleted_after_x_views > 0 ? $post_to_be_deleted_after_x_views : dateToStamp($post_to_be_deleted_after_date, $SETTINGS['date_format']),
                            )
                        );
                    }
                }

                // Get readable list of restriction
                $listOfRestricted = $oldRestrictionList = '';
                if (
                    is_array($post_restricted_to) === true
                    && count($post_restricted_to) > 0
                    && isset($SETTINGS['restricted_to']) === true
                    && (int) $SETTINGS['restricted_to'] === 1
                ) {
                    foreach ($post_restricted_to as $userRest) {
                        if (empty($userRest) === false) {
                            $dataTmp = DB::queryfirstrow('SELECT login FROM ' . prefixTable('users') . ' WHERE id= %i', $userRest);
                            if (empty($listOfRestricted)) {
                                $listOfRestricted = $dataTmp['login'];
                            } else {
                                $listOfRestricted .= ';' . $dataTmp['login'];
                            }
                        }
                    }
                }
                if (
                    $post_restricted_to !== null
                    && $data !== null
                    && $data['restricted_to'] !== $post_restricted_to
                    && (int) $SETTINGS['restricted_to'] === 1
                ) {
                    if (empty($data['restricted_to']) === false) {
                        foreach (explode(';', $data['restricted_to']) as $userRest) {
                            if (empty($userRest) === false) {
                                $dataTmp = DB::queryfirstrow('SELECT login FROM ' . prefixTable('users') . ' WHERE id= ' . $userRest);
                                if (empty($oldRestrictionList) === true) {
                                    $oldRestrictionList = $dataTmp['login'];
                                } else {
                                    $oldRestrictionList .= ';' . $dataTmp['login'];
                                }
                            }
                        }
                    }
                }
                // Manage retriction_to_roles
                if (
                    is_array($post_restricted_to_roles) === true
                    && count($post_restricted_to_roles) > 0
                    && isset($SETTINGS['restricted_to_roles']) === true
                    && (int) $SETTINGS['restricted_to_roles'] === 1
                ) {
                    // add roles for item
                    if (
                        is_array($post_restricted_to_roles) === true
                        && count($post_restricted_to_roles) > 0
                    ) {
                        foreach ($post_restricted_to_roles as $role) {
                            if (count($role) > 1) {
                                $role = $role[1];
                            } else {
                                $role = $role[0];
                            }
                            DB::insert(
                                prefixTable('restriction_to_roles'),
                                array(
                                    'role_id' => $role,
                                    'item_id' => $inputData['itemId'],
                                )
                            );
                        }
                    }
                }

                // log
                logItems(
                    $SETTINGS,
                    (int) $newID,
                    $inputData['label'],
                    $session->get('user-id'),
                    'at_creation',
                    $session->get('user-login')
                );

                // Add tags
                $tags = explode(' ', $post_tags);
                foreach ($tags as $tag) {
                    if (empty($tag) === false) {
                        DB::insert(
                            prefixTable('tags'),
                            array(
                                'item_id' => $newID,
                                'tag' => strtolower($tag),
                            )
                        );
                    }
                }

                // Check if any files have been added
                if (empty($post_uploaded_file_id) === false) {
                    $rows = DB::query(
                        'SELECT id
                        FROM ' . prefixTable('files') . '
                        WHERE id_item = %s',
                        $post_uploaded_file_id
                    );
                    foreach ($rows as $record) {
                        // update item_id in files table
                        DB::update(
                            prefixTable('files'),
                            array(
                                'id_item' => $newID,
                                'confirmed' => 1,
                            ),
                            'id=%i',
                            $record['id']
                        );
                    }
                }

                // Create new task for the new item
                // If it is not a personnal one
                if ((int) $post_folder_is_personal === 0) {
                    storeTask(
                        'new_item',
                        $session->get('user-id'),
                        0,
                        (int) $inputData['folderId'],
                        (int) $newID,
                        $post_password_key,
                        $itemFieldsForTasks,
                        $itemFilesForTasks,
                    );
                }

                // Announce by email?
                if (empty($post_diffusion_list) === false) {
                    // get links url
                    if (empty($SETTINGS['email_server_url'])) {
                        $SETTINGS['email_server_url'] = $SETTINGS['cpassman_url'];
                    }

                    // Get path
                    $path = geItemReadablePath(
                        (int) $inputData['folderId'],
                        $label,
                        $SETTINGS
                    );

                    // send email
                    if (is_array($post_diffusion_list) === true && count($post_diffusion_list) > 0) {
                        $cpt = 0;
                        foreach ($post_diffusion_list as $emailAddress) {
                            if (empty($emailAddress) === false) {
                                prepareSendingEmail(
                                    $lang->get('email_subject_item_updated'),
                                    str_replace(
                                        array('#label', '#link'),
                                            array($path, $SETTINGS['email_server_url'] . '/index.php?page=items&group=' . $inputData['folderId'] . '&id=' . $newID . $txt['email_body3']),
                                            $lang->get('new_item_email_body')
                                    ),
                                    $emailAddress,
                                    $post_diffusion_list_names[$cpt]
                                );
                            }
                            $cpt++;
                        }
                    }
                }
            } elseif (
                isset($SETTINGS['duplicate_item']) === true
                && (int) $SETTINGS['duplicate_item'] === 0
                && (int) $itemExists === 1
            ) {
                // Encrypt data to return
                echo (string) prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_item_exists'),
                    ),
                    'encode'
                );
                break;
            }

            // Add item to CACHE table if new item has been created
            if (isset($newID) === true) {
                updateCacheTable('add_value', (int) $newID);
            }

            $arrData = array(
                'error' => false,
                'item_id' => $newID,
            );
        } else {
            // an error appears on JSON format
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('json_error_format'),
                ),
                'encode'
            );
        }

        // Encrypt data to return
        echo (string) prepareExchangedData(
            $arrData,
            'encode'
        );
        break;

        /*
    * CASE
    * update an ITEM
    */
    case 'update_item':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        if ($session->get('user-read_only') === 1) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        // init
        $returnValues = array();
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        // Error if not expected data
        if (is_array($dataReceived) === false || count($dataReceived) === 0) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('json_error_format'),
                ),
                'encode'
            );
            break;
        }

        // Prepare variables
        $itemInfos = array();
        $inputData['label'] = isset($dataReceived['label']) && is_string($dataReceived['label']) ? filter_var($dataReceived['label'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';
        $post_url = isset($dataReceived['url'])=== true ? filter_var(htmlspecialchars_decode($dataReceived['url']), FILTER_SANITIZE_URL) : '';
        $post_password = $original_pw = isset($dataReceived['pw']) && is_string($dataReceived['pw']) ? htmlspecialchars_decode($dataReceived['pw']) : '';
        $post_login = isset($dataReceived['login']) && is_string($dataReceived['login']) ? filter_var(htmlspecialchars_decode($dataReceived['login']), FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';
        $post_tags = isset($dataReceived['tags'])=== true ? htmlspecialchars_decode($dataReceived['tags']) : '';
        $post_email = isset($dataReceived['email'])=== true ? filter_var(htmlspecialchars_decode($dataReceived['email']), FILTER_SANITIZE_EMAIL) : '';
        $post_template_id = (int) filter_var($dataReceived['template_id'], FILTER_SANITIZE_NUMBER_INT);
        $inputData['itemId'] = (int) filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT);
        $post_anyone_can_modify = (int) filter_var($dataReceived['anyone_can_modify'], FILTER_SANITIZE_NUMBER_INT);
        $post_complexity_level = (int) filter_var($dataReceived['complexity_level'], FILTER_SANITIZE_NUMBER_INT);
        $inputData['folderId'] = (int) filter_var($dataReceived['folder'], FILTER_SANITIZE_NUMBER_INT);
        $post_folder_is_personal = (int) filter_var($dataReceived['folder_is_personal'], FILTER_SANITIZE_NUMBER_INT);
        $post_restricted_to = filter_var_array(
            $dataReceived['restricted_to'],
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );
        $post_restricted_to_roles = filter_var_array(
            $dataReceived['restricted_to_roles'],
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );
        $post_diffusion_list = filter_var_array(
            $dataReceived['diffusion_list'],
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );
        $post_diffusion_list_names = filter_var_array(
            $dataReceived['diffusion_list_names'],
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );
        //$post_diffusion_list_names = $post_diffusion_list_names !== false ? json_decode($post_diffusion_list_names) : '';
        $post_to_be_deleted_after_x_views = filter_var(
            $dataReceived['to_be_deleted_after_x_views'],
            FILTER_SANITIZE_NUMBER_INT
        );
        $post_to_be_deleted_after_date = isset($dataReceived['to_be_deleted_after_date']) === true ? filter_var(
                $dataReceived['to_be_deleted_after_date'],
                FILTER_SANITIZE_FULL_SPECIAL_CHARS
            ) :
            '';
        $post_fields = (filter_var_array(
            $dataReceived['fields'],
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        ));
        $post_description = $antiXss->xss_clean($dataReceived['description']);
        $post_fa_icon = isset($dataReceived['fa_icon']) === true ? filter_var(($dataReceived['fa_icon']), FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';
        $post_otp_is_enabled = (int) filter_var($dataReceived['otp_is_enabled'], FILTER_SANITIZE_NUMBER_INT);
        $post_otp_phone_number = (int) filter_var($dataReceived['otp_phone_number'], FILTER_SANITIZE_NUMBER_INT);
        $post_otp_secret = isset($dataReceived['otp_secret']) === true ? filter_var(($dataReceived['otp_secret']), FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';

        //-> DO A SET OF CHECKS
        // Perform a check in case of Read-Only user creating an item in his PF
        if (
            $session->get('user-read_only') === 1
            && (in_array($inputData['folderId'], $session->get('user-personal_folders')) === false
                || $post_folder_is_personal !== 1)
        ) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to_access_this_folder'),
                ),
                'encode'
            );
            break;
        }

        $dataCheck = validateDataFields(prefixTable('items'), $dataReceived);
        if ($dataCheck['state'] !== true) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_data_not_valid').' - '.$lang->get('field').' '.strtoupper($dataCheck['field']).' '.$lang->get('exceeds_maximum_length_of').' '.$dataCheck['maxLength'].' ('.$dataCheck['currentLength'].')',
                ),
                'encode'
            );
            break;
        }

        // Check PWD EMPTY
        if (
            empty($pw) === true
            && $session->has('user-create_item_without_password') && $session->has('user-create_item_without_password') && null !== $session->get('user-create_item_without_password')
            && (int) $session->get('user-create_item_without_password') !== 1
        ) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_pw'),
                ),
                'encode'
            );
            break;
        }

        // Need info in DB
        // About special settings
        $dataFolderSettings = DB::queryFirstRow(
            'SELECT bloquer_creation, bloquer_modification, personal_folder, title
            FROM ' . prefixTable('nested_tree') . ' 
            WHERE id = %i',
            $inputData['folderId']
        );
        $itemInfos['personal_folder'] = (int) $dataFolderSettings['personal_folder'];
        if ((int) $itemInfos['personal_folder'] === 1) {
            $itemInfos['no_complex_check_on_modification'] = 1;
            $itemInfos['no_complex_check_on_creation'] = 1;
        } else {
            $itemInfos['no_complex_check_on_modification'] = (int) $dataFolderSettings['bloquer_modification'];
            $itemInfos['no_complex_check_on_creation'] = (int) $dataFolderSettings['bloquer_creation'];
        }

        // Get folder complexity
        $folderComplexity = DB::queryfirstrow(
            'SELECT valeur
            FROM ' . prefixTable('misc') . '
            WHERE type = %s AND intitule = %i',
            'complex',
            $inputData['folderId']
        );
        $itemInfos['requested_folder_complexity'] = is_null($folderComplexity) === false ? (int) $folderComplexity['valeur'] : 0;
        // Check COMPLEXITY
        if ($post_complexity_level < $itemInfos['requested_folder_complexity'] && $itemInfos['no_complex_check_on_modification'] === 0) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_security_level_not_reached'),
                ),
                'encode'
            );
            break;
        }

        // Check password length
        $strlen_post_password = strlen($post_password);
        if ($strlen_post_password > $SETTINGS['pwd_maximum_length']) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_pw_too_long'),
                ),
                'encode'
            );
            break;
        }

        // ./ END

        // Init
        $arrayOfChanges = array();
        $encryptionTaskIsRequested = false;
        $itemFilesForTasks = [];
        $itemFieldsForTasks = [];
        $tasksToBePerformed = [];
        $encrypted_password = '';
        $encrypted_password_key = '';

        // Get all informations for this item
        $dataItem = DB::queryfirstrow(
            'SELECT *
            FROM ' . prefixTable('items') . ' as i
            INNER JOIN ' . prefixTable('log_items') . ' as l ON (l.id_item = i.id)
            WHERE i.id=%i AND l.action = %s',
            $inputData['itemId'],
            'at_creation'
        );

        // Does the user has the sharekey
        //db::debugmode(true);
        DB::query(
            'SELECT *
            FROM ' . prefixTable('sharekeys_items') . '
            WHERE object_id = %i AND user_id = %s',
            $inputData['itemId'],
            $session->get('user-id')
        );
        if (DB::count() === 0) {
            if (LOG_TO_SERVER === true) error_log('TEAMPASS | user '.$session->get('user-id').' has no sharekey for item '.$inputData['itemId']);
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        // check that actual user can access this item
        $restrictionActive = true;
        $restrictedTo = is_null($dataItem['restricted_to']) === false ? array_filter(explode(';', $dataItem['restricted_to'])) : [];
        if (in_array($session->get('user-id'), $restrictedTo) === true) {
            $restrictionActive = false;
        }
        if (empty($dataItem['restricted_to']) === true) {
            $restrictionActive = false;
        }

        $session__list_restricted_folders_for_items = $session->get('system-list_restricted_folders_for_items') ?? [];
        if ((in_array($dataItem['id_tree'], $session->get('user-accessible_folders')) === true
                && ((int) $dataItem['perso'] === 0
                    || ((int) $dataItem['perso'] === 1
                        //&& (int) $session->get('user-id') === (int) $dataItem['id_user']))
                    ))
                && $restrictionActive === false)
            || (isset($SETTINGS['anyone_can_modify']) === true
                && (int) $SETTINGS['anyone_can_modify'] === 1
                && (int) $dataItem['anyone_can_modify'] === 1
                && (in_array($dataItem['id_tree'], $session->get('user-accessible_folders')) === true
                    || (int) $session->get('user-admin') === 1)
                && $restrictionActive === false)
            || (null !== $inputData['folderId']
                && count($session__list_restricted_folders_for_items) > 0
                && in_array($inputData['id'], $session__list_restricted_folders_for_items[$inputData['folderId']]) === true
                && $restrictionActive === false)
        ) {
            // Get existing values
            $data = DB::queryfirstrow(
                'SELECT i.id as id, i.label as label, i.description as description, i.pw as pw, i.url as url, i.id_tree as id_tree, i.perso as perso, i.login as login, 
                i.inactif as inactif, i.restricted_to as restricted_to, i.anyone_can_modify as anyone_can_modify, i.email as email, i.notification as notification,
                u.login as user_login, u.email as user_email
                FROM ' . prefixTable('items') . ' as i
                INNER JOIN ' . prefixTable('log_items') . ' as l ON (i.id=l.id_item)
                INNER JOIN ' . prefixTable('users') . ' as u ON (u.id=l.id_user)
                WHERE i.id=%i',
                $inputData['itemId']
            );

            // Should we log a password change?
            $userKey = DB::queryFirstRow(
                'SELECT share_key
                FROM ' . prefixTable('sharekeys_items') . '
                WHERE user_id = %i AND object_id = %i',
                $session->get('user-id'),
                $inputData['itemId']
            );
            if (DB::count() === 0 || empty($data['pw']) === true) {
                // No share key found
                $pw = '';
            } else {
                $pw = base64_decode(doDataDecryption(
                    $data['pw'],
                    decryptUserObjectKey(
                        $userKey['share_key'],
                        $session->get('user-private_key')
                    )
                ));
            }

            if ($post_password !== $pw) {
                // Encrypt previous pw
                $previousValue = cryption(
                    $pw,
                    '',
                    'encrypt'
                );

                // log the change of PW
                logItems(
                    $SETTINGS,
                    (int) $inputData['itemId'],
                    $inputData['label'],
                    $session->get('user-id'),
                    'at_modification',
                    $session->get('user-login'),
                    'at_pw',
                    TP_ENCRYPTION_NAME,
                    NULL,
                    isset($previousValue['string']) === true ? $previousValue['string'] : '',
                );
            }

            // encrypt PW on if it has changed, or if it is empty
            if ((($session->has('user-create_item_without_password') && $session->has('user-create_item_without_password') && null !== $session->get('user-create_item_without_password')
                    && (int) $session->get('user-create_item_without_password') !== 1)
                || empty($post_password) === false)
                && $post_password !== $pw
            ) {
                //-----
                // NEW ENCRYPTION
                $cryptedStuff = doDataEncryption($post_password);
                $encrypted_password = $cryptedStuff['encrypted'];
                $encrypted_password_key = $cryptedStuff['objectKey'];

                // Create sharekeys for users
                storeUsersShareKey(
                    prefixTable('sharekeys_items'),
                    (int) $post_folder_is_personal,
                    (int) $inputData['folderId'],
                    (int) $inputData['itemId'],
                    $encrypted_password_key,
                    true,   // only for the item creator
                    true,   // delete all
                );

                // Create a task to create sharekeys for users
                if (WIP=== true) error_log('createTaskForItem - new password for this item - '.$post_password ." -- ". $pw);
                $tasksToBePerformed = ['item_password'];
                /*createTaskForItem(
                    'item_update_create_keys',
                    'item_password',
                    (int) $inputData['itemId'],
                    (int) $session->get('user-id'),
                    $cryptedStuff['objectKey'],
                    (int) $inputData['itemId'],
                );*/
                $encryptionTaskIsRequested = true;
            } else {
                $encrypted_password = $data['pw'];
            }

            // ---Manage tags
            // Get list of tags
            $itemTags = DB::queryFirstColumn(
                'SELECT tag
                FROM ' . prefixTable('tags') . '
                WHERE item_id = %i',
                $inputData['itemId']
            );

            // deleting existing tags for this item
            DB::delete(
                prefixTable('tags'),
                'item_id = %i',
                $inputData['itemId']
            );

            // Add new tags
            $postArrayTags = [];
            if (empty($post_tags) === false) {
                $postArrayTags = explode(' ', $post_tags);
                foreach ($postArrayTags as $tag) {
                    if (empty($tag) === false) {
                    // save in DB
                        DB::insert(
                            prefixTable('tags'),
                            array(
                                'item_id' => $inputData['itemId'],
                                'tag' => strtolower($tag),
                            )
                        );
                    }
                }
            }

            // Store LOG
            if (count(array_diff($postArrayTags, $itemTags)) > 0) {
                // Store updates performed
                array_push(
                    $arrayOfChanges,
                    'tags'
                );

                // update LOG
                logItems(
                    $SETTINGS,
                    (int) $inputData['itemId'],
                    $inputData['label'],
                    $session->get('user-id'),
                    'at_modification',
                    $session->get('user-login'),
                    'at_tag : ' . implode(' ', $itemTags) . ' => ' . $post_tags
                );
            }

            // update item
            DB::update(
                prefixTable('items'),
                array(
                    'label' => $inputData['label'],
                    'description' => $post_description,
                    'pw' => $encrypted_password,
                    'pw_len' => $strlen_post_password,
                    'email' => $post_email,
                    'login' => $post_login,
                    'url' => $post_url,
                    'id_tree' => $inputData['folderId'],
                    'restricted_to' => empty($post_restricted_to) === true || count($post_restricted_to) === 0 ? '' : implode(';', $post_restricted_to),
                    'anyone_can_modify' => (int) $post_anyone_can_modify,
                    'complexity_level' => (int) $post_complexity_level,
                    'encryption_type' => TP_ENCRYPTION_NAME,
                    'perso' => in_array($inputData['folderId'], $session->get('user-personal_folders')) === true ? 1 : 0,
                    'fa_icon' => $post_fa_icon,
                    'updated_at' => time(),
                ),
                'id=%i',
                $inputData['itemId']
            );

            // update fields
            if (
                isset($SETTINGS['item_extra_fields']) === true
                && (int) $SETTINGS['item_extra_fields'] === 1
                && empty($post_fields) === false
            ) {                
                foreach ($post_fields as $field) {
                    if (empty($field['value']) === false) {
                        $dataTmpCat = DB::queryFirstRow(
                            'SELECT c.id AS id, c.title AS title, i.data AS data, i.data_iv AS data_iv,
                            i.encryption_type AS encryption_type, c.encrypted_data AS encrypted_data,
                            c.masked AS masked, i.id AS field_item_id
                            FROM ' . prefixTable('categories_items') . ' AS i
                            INNER JOIN ' . prefixTable('categories') . ' AS c ON (i.field_id=c.id)
                            WHERE i.field_id = %i AND i.item_id = %i',
                            $field['id'],
                            $inputData['itemId']
                        );
                        $cryptedStuff = [];
                        $encryptedFieldIsChanged = false;

                        // store Field text in DB
                        if (DB::count() === 0) {
                            // The data for this field doesn't exist
                            // It has to be added

                            // Perform new query
                            $dataTmpCat = DB::queryFirstRow(
                                'SELECT id, title, encrypted_data, masked
                                FROM ' . prefixTable('categories') . '
                                WHERE id = %i',
                                $field['id']
                            );

                            // store field text
                            DB::insert(
                                prefixTable('categories_items'),
                                array(
                                    'item_id' => $inputData['itemId'],
                                    'field_id' => $field['id'],
                                    'data' => $field['value'],
                                    'data_iv' => '',
                                    'encryption_type' => 'not_set',
                                )
                            );

                            $newId = DB::insertId();
                            $dataTmpCat['field_item_id'] = $newId;

                            // Should we encrypt the data
                            if ((int) $dataTmpCat['encrypted_data'] === 1) {
                                $cryptedStuff = doDataEncryption($field['value']);

                                // Create sharekeys for users
                                storeUsersShareKey(
                                    prefixTable('sharekeys_fields'),
                                    (int) $post_folder_is_personal,
                                    (int) $inputData['folderId'],
                                    (int) $newId,
                                    $cryptedStuff['objectKey'],
                                    true,   // only for the item creator
                                    true,   // delete all
                                );

                                // update value
                                DB::update(
                                    prefixTable('categories_items'),
                                    array(
                                        'data' => $cryptedStuff['encrypted'],
                                        'data_iv' => '',
                                        'encryption_type' => TP_ENCRYPTION_NAME,
                                    ),
                                    'id = %i',
                                    $newId
                                );

                                array_push(
                                    $tasksToBePerformed,
                                    'item_field'
                                );
                                $encryptedFieldIsChanged = true;
                            } else {
                                // update value
                                DB::update(
                                    prefixTable('categories_items'),
                                    array(
                                        'data' => $field['value'],
                                        'data_iv' => '',
                                        'encryption_type' => 'not_set',
                                    ),
                                    'id = %i',
                                    $newId
                                );
                            }

                            // Store updates performed
                            array_push(
                                $arrayOfChanges,
                                $dataTmpCat['title']
                            );

                            // update LOG
                            logItems(
                                $SETTINGS,
                                (int) $inputData['itemId'],
                                $inputData['label'],
                                $session->get('user-id'),
                                'at_modification',
                                $session->get('user-login'),
                                'at_field : ' . $dataTmpCat['title'] . ' : ' . $field['value']
                            );
                        } else {
                            // Case where the field already exists
                            // compare the old and new value
                            if ($dataTmpCat['encryption_type'] !== 'not_set') {
                                // Get user sharekey for this field
                                $userKey = DB::queryFirstRow(
                                    'SELECT share_key
                                    FROM ' . prefixTable('sharekeys_fields') . '
                                    WHERE user_id = %i AND object_id = %i',
                                    $session->get('user-id'),
                                    $dataTmpCat['field_item_id']
                                );

                                // Decrypt the current value
                                if (DB::count() > 0) {
                                    $oldVal = base64_decode(doDataDecryption(
                                        $dataTmpCat['data'],
                                        decryptUserObjectKey(
                                            $userKey['share_key'],
                                            $session->get('user-private_key')
                                        )
                                    ));
                                } else {
                                    $oldVal = '';
                                }
                            } else {
                                $oldVal = $dataTmpCat['data'];
                            }

                            // Compare both values to see if any change was done
                            if ($field['value'] !== $oldVal) {
                                // The strings are different
                                $encrypt = [];
                                
                                // Should we encrypt the data
                                if ((int) $dataTmpCat['encrypted_data'] === 1) {
                                    $cryptedStuff = doDataEncryption($field['value']);
                                    $encrypt['string'] = $cryptedStuff['encrypted'];
                                    $encrypt['type'] = TP_ENCRYPTION_NAME;

                                    // Create sharekeys for users
                                    storeUsersShareKey(
                                        prefixTable('sharekeys_fields'),
                                        (int) $post_folder_is_personal,
                                        (int) $inputData['folderId'],
                                        (int) $dataTmpCat['field_item_id'],
                                        $cryptedStuff['objectKey'],
                                        true,   // only for the item creator
                                        true,   // delete all
                                    );

                                    array_push(
                                        $tasksToBePerformed,
                                        'item_field'
                                    );
                                    $encryptedFieldIsChanged = true;
                                } else {
                                    $encrypt['string'] = $field['value'];
                                    $encrypt['type'] = 'not_set';
                                }

                                // update value
                                DB::update(
                                    prefixTable('categories_items'),
                                    array(
                                        'data' => $encrypt['string'],
                                        'data_iv' => '',
                                        'encryption_type' => $encrypt['type'],
                                    ),
                                    'item_id = %i AND field_id = %i',
                                    $inputData['itemId'],
                                    $field['id']
                                );

                                // Store updates performed
                                array_push(
                                    $arrayOfChanges,
                                    $dataTmpCat['title']
                                );

                                // update LOG
                                logItems(
                                    $SETTINGS,
                                    (int) $inputData['itemId'],
                                    $inputData['label'],
                                    $session->get('user-id'),
                                    'at_modification',
                                    $session->get('user-login'),
                                    'at_field : ' . $dataTmpCat['title'] . ' => ' . $oldVal
                                );
                            }
                        }

                        // Create a task to create sharekeys for this field for users
                        // If this field is encrypted
                        if ((int) $dataTmpCat['encrypted_data'] === 1 && $encryptedFieldIsChanged === true) {
                            array_push(
                                $itemFieldsForTasks,
                                [
                                    'object_id' => $dataTmpCat['field_item_id'],
                                    'object_key' => $cryptedStuff['objectKey'],
                                ]
                            );
                            $encryptionTaskIsRequested = true;
                        }
                    } else {
                        // Case where field new value is empty
                        // then delete field
                        if (empty($field_data[1]) === true) {
                            DB::delete(
                                prefixTable('categories_items'),
                                'item_id = %i AND field_id = %s',
                                $inputData['itemId'],
                                $field['id']
                            );
                        }
                    }
                }
            }

            // create a task for all fields updated
            if ($encryptionTaskIsRequested === true) {
                if (WIP === true) error_log('createTaskForItem - '.print_r($tasksToBePerformed, true));
                createTaskForItem(
                    'item_update_create_keys',
                    $tasksToBePerformed,
                    (int) $inputData['itemId'],
                    (int) $session->get('user-id'),
                    $encrypted_password_key,
                    (int) $inputData['itemId'],
                    $itemFieldsForTasks,
                    []
                );
            }

            // If template enable, is there a main one selected?
            if (
                isset($SETTINGS['item_creation_templates']) === true
                && (int) $SETTINGS['item_creation_templates'] === 1
                && isset($post_template_id) === true
            ) {
                DB::queryFirstRow(
                    'SELECT *
                    FROM ' . prefixTable('templates') . '
                    WHERE item_id = %i',
                    $inputData['itemId']
                );
                if (DB::count() === 0 && empty($post_template_id) === false) {
                    // store field text
                    DB::insert(
                        prefixTable('templates'),
                        array(
                            'item_id' => $inputData['itemId'],
                            'category_id' => $post_template_id,
                        )
                    );
                } else {
                    // Delete if empty
                    if (empty($post_template_id) === true) {
                        DB::delete(
                            prefixTable('templates'),
                            'item_id = %i',
                            $inputData['itemId']
                        );
                    } else {
                        // Update value
                        DB::update(
                            prefixTable('templates'),
                            array(
                                'category_id' => $post_template_id,
                            ),
                            'item_id = %i',
                            $inputData['itemId']
                        );
                    }
                }
            }

            // Update automatic deletion - Only by the creator of the Item
            if (
                isset($SETTINGS['enable_delete_after_consultation']) === true
                && (int) $SETTINGS['enable_delete_after_consultation'] === 1
            ) {
                // check if elem exists in Table. If not add it or update it.
                DB::query(
                    'SELECT *
                    FROM ' . prefixTable('automatic_del') . '
                    WHERE item_id = %i',
                    $inputData['itemId']
                );

                if (DB::count() === 0) {
                    // No automatic deletion for this item
                    if (
                        empty($post_to_be_deleted_after_date) === false
                        || (int) $post_to_be_deleted_after_x_views > 0
                    ) {
                        // Automatic deletion to be added
                        DB::insert(
                            prefixTable('automatic_del'),
                            array(
                                'item_id' => $inputData['itemId'],
                                'del_enabled' => 1,
                                'del_type' => empty($post_to_be_deleted_after_x_views) === false ?
                                    1 : 2, //1 = numeric : 2 = date
                                'del_value' => empty($post_to_be_deleted_after_x_views) === false ?
                                    (int) $post_to_be_deleted_after_x_views : dateToStamp($post_to_be_deleted_after_date, $SETTINGS['date_format']),
                            )
                        );

                        // Store updates performed
                        array_push(
                            $arrayOfChanges,
                            $lang->get('automatic_deletion_engaged') . ': ' . $lang->get('enabled')
                        );

                        // update LOG
                        logItems(
                            $SETTINGS,
                            (int) $inputData['itemId'],
                            $inputData['label'],
                            $session->get('user-id'),
                            'at_modification',
                            $session->get('user-login'),
                            'at_automatic_del : enabled'
                        );
                    }
                } else {
                    // Automatic deletion exists for this item
                    if (
                        empty($post_to_be_deleted_after_date) === false
                        || (int) $post_to_be_deleted_after_x_views > 0
                    ) {
                        // Update automatic deletion
                        DB::update(
                            prefixTable('automatic_del'),
                            array(
                                'del_type' => empty($post_to_be_deleted_after_x_views) === false ?
                                    1 : 2, //1 = numeric : 2 = date
                                'del_value' => empty($post_to_be_deleted_after_x_views) === false ?
                                    $post_to_be_deleted_after_x_views : dateToStamp($post_to_be_deleted_after_date, $SETTINGS['date_format']),
                            ),
                            'item_id = %i',
                            $inputData['itemId']
                        );
                    } else {
                        // delete automatic deleteion for this item
                        DB::delete(
                            prefixTable('automatic_del'),
                            'item_id = %i',
                            $inputData['itemId']
                        );

                        // Store updates performed
                        array_push(
                            $arrayOfChanges,
                            $lang->get('automatic_deletion_engaged') . ': ' . $lang->get('disabled')
                        );

                        // update LOG
                        logItems(
                            $SETTINGS,
                            (int) $inputData['itemId'],
                            $inputData['label'],
                            $session->get('user-id'),
                            'at_modification',
                            $session->get('user-login'),
                            'at_automatic_del : disabled'
                        );
                    }
                }
            }

            // get readable list of restriction
            $listOfRestricted = $oldRestrictionList = '';
            $arrayOfUsersRestriction = array();
            $arrayOfUsersIdRestriction = array();
            $diffUsersRestiction = array();
            $diffRolesRestiction = array();
            if (
                is_array($post_restricted_to) === true
                && count($post_restricted_to) > 0
                && isset($SETTINGS['restricted_to']) === true
                && (int) $SETTINGS['restricted_to'] === 1
            ) {
                foreach ($post_restricted_to as $userId) {
                    if (empty($userId) === false) {
                        $dataTmp = DB::queryfirstrow(
                            'SELECT id, name, lastname
                            FROM ' . prefixTable('users') . '
                            WHERE id= %i',
                            $userId
                        );

                        // Add to array
                        array_push(
                            $arrayOfUsersRestriction,
                            $dataTmp['name'] . ' ' . $dataTmp['lastname']
                        );
                        array_push(
                            $arrayOfUsersIdRestriction,
                            $dataTmp['id']
                        );
                    }
                }
            }
            if ((int) $SETTINGS['restricted_to'] === 1) {
                $diffUsersRestiction = array_diff(
                    empty($data['restricted_to']) === false ?
                        explode(';', $data['restricted_to']) : array(),
                    $arrayOfUsersIdRestriction
                );
            }

            // Manage retriction_to_roles
            if (
                is_array($post_restricted_to_roles) === true
                && count($post_restricted_to_roles) > 0
                && isset($SETTINGS['restricted_to_roles']) === true
                && (int) $SETTINGS['restricted_to_roles'] === 1
            ) {
                // Init
                $arrayOfRestrictionRolesOld = array();
                $arrayOfRestrictionRoles = array();

                // get values before deleting them
                $rows = DB::query(
                    'SELECT t.title, t.id AS id
                    FROM ' . prefixTable('roles_title') . ' as t
                    INNER JOIN ' . prefixTable('restriction_to_roles') . ' as r ON (t.id=r.role_id)
                    WHERE r.item_id = %i
                    ORDER BY t.title ASC',
                    $inputData['itemId']
                );
                foreach ($rows as $record) {
                    // Add to array
                    array_push(
                        $arrayOfRestrictionRolesOld,
                        $record['title']
                    );
                }
                // delete previous values
                DB::delete(
                    prefixTable('restriction_to_roles'),
                    'item_id = %i',
                    $inputData['itemId']
                );

                // add roles for item
                if (
                    is_array($post_restricted_to_roles) === true
                    && count($post_restricted_to_roles) > 0
                ) {
                    foreach ($post_restricted_to_roles as $role) {
                        DB::insert(
                            prefixTable('restriction_to_roles'),
                            array(
                                'role_id' => $role,
                                'item_id' => $inputData['itemId'],
                            )
                        );
                        $dataTmp = DB::queryfirstrow(
                            'SELECT title
                            FROM ' . prefixTable('roles_title') . '
                            WHERE id = %i',
                            $role
                        );

                        // Add to array
                        array_push(
                            $arrayOfRestrictionRoles,
                            $dataTmp['title']
                        );
                    }

                    if ((int) $SETTINGS['restricted_to'] === 1) {
                        $diffRolesRestiction = array_diff(
                            $arrayOfRestrictionRoles,
                            $arrayOfRestrictionRolesOld
                        );
                    }
                }
            }
            // Update CACHE table
            updateCacheTable('update_value', (int) $inputData['itemId']);


            // Manage OTP status
            // Get current status
            $otpStatus = DB::queryFirstRow(
                'SELECT enabled as otp_is_enabled
                FROM ' . prefixTable('items_otp') . '
                WHERE item_id = %i',
                $inputData['itemId']
            );

            // Check if status has changed
            if (DB::count() > 0 && (int) $otpStatus['otp_is_enabled'] !== (int) $post_otp_is_enabled) {
                // Update status
                DB::update(
                    prefixTable('items_otp'),
                    array(
                        'enabled' => (int) $post_otp_is_enabled,
                    ),
                    'item_id = %i',
                    $inputData['itemId']
                );

                // Store updates performed
                array_push(
                    $arrayOfChanges,
                    $lang->get('otp_status')
                );

                // update LOG
                logItems(
                    $SETTINGS,
                    (int) $inputData['itemId'],
                    $inputData['label'],
                    $session->get('user-id'),
                    'at_modification',
                    $session->get('user-login'),
                    'at_otp_status:' . ((int) $post_otp_is_enabled === 0 ? 'disabled' : 'enabled')
                );
            } elseif (DB::count() === 0 && empty($post_otp_secret) === false) {
                // Create the entry in items_otp table
                // OTP doesn't exist then create it

                // Encrypt secret
                $encryptedSecret = cryption(
                    $post_otp_secret,
                    '',
                    'encrypt'
                );
                
                // insert in table
                DB::insert(
                    prefixTable('items_otp'),
                    array(
                        'item_id' => $inputData['itemId'],
                        'secret' => $encryptedSecret['string'],
                        'phone_number' => $post_otp_phone_number,
                        'timestamp' => time(),
                        'enabled' => 1,
                    )
                );
            }

            //---- Log all modifications done ----

            // RESTRICTIONS
            if (count($diffRolesRestiction) > 0 || count($diffUsersRestiction) > 0) {
                // Store updates performed
                array_push(
                    $arrayOfChanges,
                    $lang->get('at_restriction')
                );

                // Log
                logItems(
                    $SETTINGS,
                    (int) $inputData['itemId'],
                    $inputData['label'],
                    $session->get('user-id'),
                    'at_modification',
                    $session->get('user-login'),
                    'at_restriction : ' . (count($diffUsersRestiction) > 0 ?
                        implode(', ', $arrayOfUsersRestriction) . (count($diffRolesRestiction) > 0 ? ', ' : '') : '') . (count($diffRolesRestiction) > 0 ? implode(', ', $arrayOfRestrictionRoles) : '')
                );
            }

            // LABEL
            if ($data['label'] !== $inputData['label']) {
                // Store updates performed
                array_push(
                    $arrayOfChanges,
                    $lang->get('at_label')
                );

                // Log
                logItems(
                    $SETTINGS,
                    (int) $inputData['itemId'],
                    $inputData['label'],
                    $session->get('user-id'),
                    'at_modification',
                    $session->get('user-login'),
                    'at_label : ' . $data['label'] . ' => ' . $inputData['label']
                );
            }
            // LOGIN
            if ($data['login'] !== $post_login) {
                // Store updates performed
                array_push(
                    $arrayOfChanges,
                    $lang->get('at_login')
                );

                // Log
                logItems(
                    $SETTINGS,
                    (int) $inputData['itemId'],
                    $inputData['label'],
                    $session->get('user-id'),
                    'at_modification',
                    $session->get('user-login'),
                    'at_login : ' . $data['login'] . ' => ' . $post_login
                );
            }
            // EMAIL
            if ($post_email !== null && $data['email'] !== null && strcmp($data['email'], $post_email) !== 0) {
                // Store updates performed
                array_push(
                    $arrayOfChanges,
                    $lang->get('at_email')
                );

                // Log
                logItems(
                    $SETTINGS,
                    (int) $inputData['itemId'],
                    $inputData['label'],
                    $session->get('user-id'),
                    'at_modification',
                    $session->get('user-login'),
                    'at_email : ' . $data['email'] . ' => ' . $post_email
                );
            }
            // URL
            if ($data['url'] !== $post_url && $post_url !== 'http://') {
                // Store updates performed
                array_push(
                    $arrayOfChanges,
                    $lang->get('at_url')
                );

                // Log
                logItems(
                    $SETTINGS,
                    (int) $inputData['itemId'],
                    $inputData['label'],
                    $session->get('user-id'),
                    'at_modification',
                    $session->get('user-login'),
                    'at_url : ' . $data['url'] . ' => ' . $post_url
                );
            }
            // DESCRIPTION
            // deepcode ignore InsecureHash: md5 is used just to perform a string encrypted comparison
            if (strcmp(md5(strip_tags($data['description'])), md5(strip_tags($post_description))) !== 0) {
                // Store updates performed
                array_push(
                    $arrayOfChanges,
                    $lang->get('at_description')
                );

                // Log
                logItems(
                    $SETTINGS,
                    (int) $inputData['itemId'],
                    $inputData['label'],
                    $session->get('user-id'),
                    'at_modification',
                    $session->get('user-login'),
                    'at_description'
                );
            }
            // FOLDER
            if ((int) $data['id_tree'] !== (int) $inputData['folderId']) {
                // Get name of folders
                $dataTmp = DB::query('SELECT title FROM ' . prefixTable('nested_tree') . ' WHERE id IN %li', array($data['id_tree'], $inputData['folderId']));

                // Store updates performed
                array_push(
                    $arrayOfChanges,
                    $lang->get('at_category')
                );

                // Log
                logItems(
                    $SETTINGS,
                    (int) $inputData['itemId'],
                    $inputData['label'],
                    $session->get('user-id'),
                    'at_modification',
                    $session->get('user-login'),
                    'at_category : ' . $dataTmp[0]['title'] . ' => ' . $dataTmp[1]['title']
                );
            }
            // ANYONE_CAN_MODIFY
            if ((int) $post_anyone_can_modify !== (int) $data['anyone_can_modify']) {
                // Store updates performed
                array_push(
                    $arrayOfChanges,
                    $lang->get('at_anyoneconmodify') . ': ' . ((int) $post_anyone_can_modify === 0 ? $lang->get('disabled') : $lang->get('enabled'))
                );

                // Log
                logItems(
                    $SETTINGS,
                    (int) $inputData['itemId'],
                    $inputData['label'],
                    $session->get('user-id'),
                    'at_modification',
                    $session->get('user-login'),
                    'at_anyoneconmodify : ' . ((int) $post_anyone_can_modify === 0 ? 'disabled' : 'enabled')
                );
            }

            // Reload new values
            $dataItem = DB::queryfirstrow(
                'SELECT *
                FROM ' . prefixTable('items') . ' as i
                INNER JOIN ' . prefixTable('log_items') . ' as l ON (l.id_item = i.id)
                WHERE i.id = %i AND l.action = %s',
                $inputData['itemId'],
                'at_creation'
            );
            // Reload History
            $history = '';
            $rows = DB::query(
                'SELECT l.date as date, l.action as action, l.raison as raison, u.login as login
                FROM ' . prefixTable('log_items') . ' as l
                LEFT JOIN ' . prefixTable('users') . ' as u ON (l.id_user=u.id)
                WHERE l.action <> %s AND id_item=%s',
                'at_shown',
                $inputData['itemId']
            );
            foreach ($rows as $record) {
                if ($record['raison'] === NULL) continue;
                $reason = explode(':', $record['raison']);
                if (count($reason) > 0) {
                    $sentence = date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['date']) . ' - '
                        . $record['login'] . ' - ' . $lang->get($record['action']) . ' - '
                        . (empty($record['raison']) === false ? (count($reason) > 1 ? $lang->get(trim($reason[0])) . ' : ' . $reason[1]
                            : $lang->get(trim($reason[0]))) : '');
                    if (empty($history)) {
                        $history = $sentence;
                    } else {
                        $history .= '<br />' . $sentence;
                    }
                }
            }

            // generate 2d key
            $session->set('user-key_tmp', bin2hex(GenerateCryptKey(16, false, true, true, false, true, $SETTINGS)));

            // Send email
            if (is_array($post_diffusion_list) === true && count($post_diffusion_list) > 0) {
                $cpt = 0;
                foreach ($post_diffusion_list as $emailAddress) {
                    if (empty($emailAddress) === false) {
                        prepareSendingEmail(
                            $lang->get('email_subject_item_updated'),
                            str_replace(
                                array('#item_label#', '#item_category#', '#item_id#', '#url#', '#name#', '#lastname#', '#folder_name#'),
                                array($inputData['label'], $inputData['folderId'], $inputData['itemId'], $SETTINGS['cpassman_url'], $session->get('user-name'), $session->get('user-lastname'), $dataFolderSettings['title']),
                                $lang->get('email_body_item_updated')
                            ),
                            $emailAddress,
                            $post_diffusion_list_names[$cpt]
                        );
                        $cpt++;
                    }
                }
            }

            // Remove the edition lock if no  encryption steps are needed
            if ($encryptionTaskIsRequested === false) {
                error_log('Remove the edition lock if no  encryption steps are needed');
                DB::delete(
                    prefixTable('items_edition'), 
                    'item_id = %i AND user_id = %i', 
                    $inputData['itemId'],
                    $session->get('user-id')
                );
            }

            // Notifiy changes to the users
            notifyChangesToSubscribers($inputData['itemId'], $inputData['label'], $arrayOfChanges, $SETTINGS);

            // Prepare some stuff to return
            $arrData = array(
                'error' => false,
                'message' => '',
            );
        } else {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to_edit_item'),
                ),
                'encode'
            );
            break;
        }
        
        // return data
        echo (string) prepareExchangedData(
            $arrData,
            'encode'
        );
        break;

        /*
        * CASE
        * Copy an Item
    */
    case 'copy_item':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        if ($session->get('user-read_only') === 1) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        // Prepare POST variables
        $post_new_label = (string) filter_var($dataReceived['new_label'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $post_source_id = (int) filter_var($dataReceived['source_id'], FILTER_SANITIZE_NUMBER_INT);
        $post_dest_id = (int) filter_var($dataReceived['dest_id'], FILTER_SANITIZE_NUMBER_INT);
        $inputData['itemId'] = (int) filter_var($dataReceived['item_id'], FILTER_SANITIZE_NUMBER_INT);

        // perform a check in case of Read-Only user creating an item in his PF
        if (
            (int) $session->get('user-read_only') === 1
            && (in_array($post_source_id, $session->get('user-personal_folders')) === false
                || in_array($post_dest_id, $session->get('user-personal_folders')) === false)
        ) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        // Init
        $returnValues = '';
        $pw = '';
        $is_perso = 0;
        $itemDataArray = array(
            'pwd' => '',
            'fields' => [],
            'files' => [],
        );

        if (
            empty($inputData['itemId']) === false
            && empty($post_dest_id) === false
        ) {
            // load the original record into an array
            $originalRecord = DB::queryfirstrow(
                'SELECT * FROM ' . prefixTable('items') . '
                WHERE id = %i',
                $inputData['itemId']
            );

            // Check if the folder where this item is accessible to the user
            if (in_array($originalRecord['id_tree'], $session->get('user-accessible_folders')) === false) {
                echo (string) prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // Load the destination folder record into an array
            $dataDestination = DB::queryfirstrow(
                'SELECT personal_folder FROM ' . prefixTable('nested_tree') . '
                WHERE id = %i',
                $post_dest_id
            );

            // Get the ITEM object key for the user
            $userKey = DB::queryFirstRow(
                'SELECT share_key
                FROM ' . prefixTable('sharekeys_items') . '
                WHERE user_id = %i AND object_id = %i',
                $session->get('user-id'),
                $inputData['itemId']
            );
            if (DB::count() === 0) {
                // ERROR - No sharekey found for this item and user
                echo (string) prepareExchangedData(
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
                        $originalRecord['pw'],
                        decryptUserObjectKey(
                            $userKey['share_key'],
                            $session->get('user-private_key')
                        )
                    )
                )
            );
            // reaffect pw
            $originalRecord['pw'] = $cryptedStuff['encrypted'];

            // store pwd object key
            $itemDataArray['pwd'] = $cryptedStuff['objectKey'];

            // generate the query to update the new record with the previous values
            $aSet = array();
            $aSet['created_at'] = time();
            foreach ($originalRecord as $key => $value) {
                if ($key === 'id_tree') {
                    $aSet['id_tree'] = $post_dest_id;
                } elseif ($key === 'label') {
                    $aSet[$key] = $post_new_label;
                } elseif ($key === 'viewed_no') {
                    $aSet['viewed_no'] = '0';
                } elseif ($key === 'pw' && empty($pw) === false) {
                    $aSet['pw'] = $originalRecord['pw'];
                    $aSet['pw_iv'] = '';
                } elseif ($key === 'perso') {
                    $aSet['perso'] = $is_perso;
                } elseif ($key !== 'id' && $key !== 'key') {
                    $aSet[$key] = $value;
                }
            }

            // insert the new record and get the new auto_increment id
            DB::insert(
                prefixTable('items'),
                $aSet
            );
            $newItemId = DB::insertId();

            // Create sharekeys for users of this new ITEM
            storeUsersShareKey(
                prefixTable('sharekeys_items'),
                (int) $dataDestination['personal_folder'],
                (int) $post_dest_id,
                (int) $newItemId,
                $itemDataArray['pwd'],
                true,
                false,
            );

            // --------------------
            // Manage Custom Fields
            $rows = DB::query(
                'SELECT ci.id AS id, ci.data AS data, ci.field_id AS field_id, c.encrypted_data AS encrypted_data
                FROM ' . prefixTable('categories_items') . ' AS ci
                INNER JOIN ' . prefixTable('categories') . ' AS c ON (c.id = ci.field_id)
                WHERE ci.item_id = %i',
                $inputData['itemId']
            );
            foreach ($rows as $field) {
                // Create the entry for the new item

                // Is the data encrypted
                if ((int) $field['encrypted_data'] === 1) {
                    // Get user key
                    $userKey = DB::queryFirstRow(
                        'SELECT share_key
                        FROM ' . prefixTable('sharekeys_fields') . '
                        WHERE user_id = %i AND object_id = %i',
                        $session->get('user-id'),
                        $field['id']
                    );
                    // Then decrypt original field value and encrypt with new key
                    $cryptedStuff = doDataEncryption(
                        base64_decode(
                            doDataDecryption(
                                $field['data'],
                                decryptUserObjectKey(
                                    $userKey['share_key'],
                                    $session->get('user-private_key')
                                )
                            )
                        )
                    );
                    // reaffect pw
                    $field['data'] = $cryptedStuff['encrypted'];
                }

                // store field text
                DB::insert(
                    prefixTable('categories_items'),
                    array(
                        'item_id' => $newItemId,
                        'field_id' => $field['field_id'],
                        'data' => (int) $field['encrypted_data'] === 1 ?
                            $cryptedStuff['encrypted'] : $field['data'],
                        'data_iv' => '',
                        'encryption_type' => (int) $field['encrypted_data'] === 1 ?
                            TP_ENCRYPTION_NAME : 'not_set',
                    )
                );
                $newFieldId = DB::insertId();

                // Create sharekeys for current user
                if ((int) $field['encrypted_data'] === 1) {
                    // Create sharekeys for user
                    storeUsersShareKey(
                        prefixTable('sharekeys_fields'),
                        (int) $dataDestination['personal_folder'],
                        (int) $post_dest_id,
                        (int) $newFieldId,
                        $cryptedStuff['objectKey'],
                        true,
                        false,
                    );

                    // Build list of fields
                    array_push(
                        $itemDataArray['fields'],
                        array(
                            'object_id' => $newFieldId,
                            'object_key' => $cryptedStuff['objectKey'],
                        )
                    );
                }
            }
            // <---

            // ------------------
            // Manage attachments

            // get file key
            $rows = DB::query(
                'SELECT f.id AS id, f.file AS file, f.name AS name, f.status AS status, f.extension AS extension,
                f.size AS size, f.type AS type, s.share_key AS share_key
                FROM ' . prefixTable('files') . ' AS f
                INNER JOIN ' . prefixTable('sharekeys_files') . ' AS s ON (f.id = s.object_id)
                WHERE s.user_id = %i AND f.id_item = %i',
                $session->get('user-id'),
                $inputData['itemId']
            );
            foreach ($rows as $record) {
                // Check if file still exists
                if (file_exists($SETTINGS['path_to_upload_folder'] . DIRECTORY_SEPARATOR . TP_FILE_PREFIX . base64_decode($record['file'])) === true) {
                    // Step1 - decrypt the file
                    // deepcode ignore PT: path is sanitized inside decryptFile()
                    $fileContent = decryptFile(
                        $record['file'],
                        $SETTINGS['path_to_upload_folder'],
                        decryptUserObjectKey($record['share_key'], $session->get('user-private_key'))
                    );

                    // Step2 - create file
                    // deepcode ignore InsecureHash: md5 is used jonly for file name in order to get a hashed value in database
                    $newFileName = md5(time() . '_' . $record['id']) . '.' . $record['extension'];
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
                            'name' => $record['name'],
                            'size' => $record['size'],
                            'extension' => $record['extension'],
                            'type' => $record['type'],
                            'file' => $newFile['fileHash'],
                            'status' => TP_ENCRYPTION_NAME,
                            'confirmed' => 1,
                        )
                    );
                    $newFileId = DB::insertId();

                    // Step5 - create sharekeys
                    // Build list of fields
                    array_push(
                        $itemDataArray['files'],
                        array(
                            'object_id' => $newFileId,
                            'object_key' => $newFile['objectKey'],
                        )
                    );

                    storeUsersShareKey(
                        prefixTable('sharekeys_files'),
                        (int) $dataDestination['personal_folder'],
                        (int) $post_dest_id,
                        (int) $newFileId,
                        $newFile['objectKey'],
                        true
                    );
                }
            }
            // <---

            // Create new task for the new item
            // If it is not a personnal one
            if ((int) $dataDestination['personal_folder'] !== 1) {
                //error_log('item_copy' . print_r($itemDataArray, true));
                storeTask(
                    'item_copy',
                    $session->get('user-id'),
                    0,
                    (int) $post_dest_id,
                    (int) $newItemId,
                    $itemDataArray['pwd'],
                    $itemDataArray['fields'],
                    $itemDataArray['files'],
                );
            }

            // -------------------------
            // Add specific restrictions
            $rows = DB::query('SELECT * FROM ' . prefixTable('restriction_to_roles') . ' WHERE item_id = %i', $inputData['itemId']);
            foreach ($rows as $record) {
                DB::insert(
                    prefixTable('restriction_to_roles'),
                    array(
                        'item_id' => $newItemId,
                        'role_id' => $record['role_id'],
                    )
                );
            }

            // Add Tags
            $rows = DB::query('SELECT * FROM ' . prefixTable('tags') . ' WHERE item_id = %i', $inputData['itemId']);
            foreach ($rows as $record) {
                DB::insert(
                    prefixTable('tags'),
                    array(
                        'item_id' => $newItemId,
                        'tag' => $record['tag'],
                    )
                );
            }

            // Add this duplicate in logs
            logItems(
                $SETTINGS,
                (int) $newItemId,
                $originalRecord['label'],
                $session->get('user-id'),
                'at_creation',
                $session->get('user-login')
            );
            // Add the fact that item has been copied in logs
            logItems(
                $SETTINGS,
                (int) $newItemId,
                $originalRecord['label'],
                $session->get('user-id'),
                'at_copy',
                $session->get('user-login')
            );
            // reload cache table
            include_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
            updateCacheTable('reload', null);

            echo (string) prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'new_id' => $newItemId
                ),
                'encode'
            );
        } else {
            // no item
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_missing_id'),
                ),
                'encode'
            );
        }
        break;

        /*
        * CASE
        * Display informations of selected item
    */
    case 'show_details_item':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // Step #1
        $session->set('system-show_step2', false);

        // Decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        // Init post variables
        $inputData['id'] = filter_var(($dataReceived['id']), FILTER_SANITIZE_NUMBER_INT);
        $inputData['folderId'] = filter_var(($dataReceived['folder_id']), FILTER_SANITIZE_NUMBER_INT);
        $post_expired_item = filter_var(($dataReceived['expired_item']), FILTER_SANITIZE_NUMBER_INT);
        $post_restricted = filter_var(($dataReceived['restricted']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $post_folder_access_level = isset($dataReceived['folder_access_level']) === true ?
            filter_var(($dataReceived['folder_access_level']), FILTER_SANITIZE_FULL_SPECIAL_CHARS)
            : '';
        $post_item_rights = filter_var($dataReceived['rights'], FILTER_SANITIZE_NUMBER_INT);

        $arrData = array();
        // return ID
        $arrData['id'] = (int) $inputData['id'];
        $arrData['id_user'] = API_USER_ID;
        $arrData['author'] = 'API';

        // Check if item is deleted
        // taking into account that item can be restored.
        // so if restoration timestamp is higher than the deletion one
        // then we can show it
        $item_deleted = DB::queryFirstRow(
            'SELECT *
            FROM ' . prefixTable('log_items') . '
            WHERE id_item = %i AND action = %s
            ORDER BY date DESC
            LIMIT 0, 1',
            $inputData['id'],
            'at_delete'
        );
        $dataDeleted = DB::count();

        $item_restored = DB::queryFirstRow(
            'SELECT *
            FROM ' . prefixTable('log_items') . '
            WHERE id_item = %i AND action = %s
            ORDER BY date DESC
            LIMIT 0, 1',
            $inputData['id'],
            'at_restored'
        );

        if ($dataDeleted !== 0 && intval($item_deleted['date']) > intval($item_restored['date'])) {
            // This item is deleted => exit
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('not_allowed_to_see_pw'),
                    'show_detail_option' => 2,
                ),
                'encode'
            );
            break;
        }

        // Get all informations for this item
        $dataItem = DB::queryfirstrow(
            'SELECT *
            FROM ' . prefixTable('items') . ' as i
            INNER JOIN ' . prefixTable('log_items') . ' as l ON (l.id_item = i.id)
            WHERE i.id = %i AND l.action = %s',
            $inputData['id'],
            'at_creation'
        );

        // Notification
        DB::queryfirstrow(
            'SELECT *
            FROM ' . prefixTable('notification') . '
            WHERE item_id = %i AND user_id = %i',
            $inputData['id'],
            $session->get('user-id')
        );
        if (DB::count() > 0) {
            $arrData['notification_status'] = true;
        } else {
            $arrData['notification_status'] = false;
        }

        // Get all USERS infos
        $listeRestriction = is_null($dataItem['restricted_to']) === false ? array_filter(explode(';', $dataItem['restricted_to'])) : [];
        $session->set('system-emails_list_for_notif', '');

        /*$user_in_restricted_list_of_item = false;
        $rows = DB::query(
            'SELECT id, login, email, admin, name, lastname
            FROM ' . prefixTable('users') .'
            WHERE id in %ls',
            replace(';', ',', $dataItem['restricted_to'])
        );
        $listeRestriction = [];
        foreach ($rows as $user) {
            // Get auhtor
            if ($user['id'] === $dataItem['id_user']) {
                $arrData['author'] = $user['login'];
                $arrData['author_email'] = $user['email'];
                $arrData['id_user'] = (int) $dataItem['id_user'];
            }

            // Get restriction list for users
            if (in_array($user['id'], $listRest) === true) {
                array_push($listeRestriction, $user['id']);
                if ($session->get('user-id') === $user['id']) {
                    $user_in_restricted_list_of_item = true;
                }
            }
        }*/
        $user_in_restricted_list_of_item = in_array($session->get('user-id'), $listeRestriction) === true ? true : false;

        // manage case of API user
        if ($dataItem['id_user'] === API_USER_ID) {
            $arrData['author'] = 'API [' . $dataItem['description'] . ']';
            $arrData['id_user'] = API_USER_ID;
            $arrData['author_email'] = '';
            $arrData['notification_status'] = false;
        }

        // Get all tags for this item
        $tags = array();
        $rows = DB::query(
            'SELECT tag
            FROM ' . prefixTable('tags') . '
            WHERE item_id = %i',
            $inputData['id']
        );
        foreach ($rows as $record) {
            array_push($tags, $record['tag']);
        }

        // TODO -> improve this check
        // check that actual user can access this item
        $restrictionActive = true;
        $restrictedTo = is_null($dataItem['restricted_to']) === false ? array_filter(explode(';', $dataItem['restricted_to'])) : [];
        if (
            in_array($session->get('user-id'), $restrictedTo) === true
            || ((int) $session->get('user-manager') === 1 && (int) $SETTINGS['manager_edit'] === 1)
        ) {
            $restrictionActive = false;
        }
        if (empty($dataItem['restricted_to']) === true) {
            $restrictionActive = false;
        }

        // Check if user has a role that is accepted
        $rows_tmp = DB::query(
            'SELECT role_id
            FROM ' . prefixTable('restriction_to_roles') . '
            WHERE item_id=%i',
            $inputData['id']
        );
        foreach ($rows_tmp as $rec_tmp) {
            if (in_array($rec_tmp['role_id'], explode(';', $session->get('user-roles')))) {
                $restrictionActive = false;
            }
        }

        // Uncrypt PW
        // Get the object key for the user
        $userKey = DB::queryFirstRow(
            'SELECT share_key
            FROM ' . prefixTable('sharekeys_items') . '
            WHERE user_id = %i AND object_id = %i',
            $session->get('user-id'),
            $inputData['id']
        );
        if (DB::count() === 0 || empty($dataItem['pw']) === true) {
            // No share key found
            $pwIsEmptyNormally = false;
            // Is this a personal and defuse password?
            if ((int) $dataItem['perso'] === 1 && substr($dataItem['pw'], 0, 3) === 'def') {
                // Yes, then ask for decryption with old personal salt key
                echo (string) prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error'),
                        'show_detail_option' => 2,
                        'error_type' => 'private_items_to_encrypt',
                    ),
                    'encode'
                );
                break;
            } else {
                $pw = '';
            }
        } else {
            $pwIsEmptyNormal == true;
            //error_log('userKey: ' . print_r($userKey, true));
            $decryptedObject = decryptUserObjectKey($userKey['share_key'], $session->get('user-private_key'));
            // if null then we have an error.
            // suspecting bad password
            if (empty($decryptedObject) === false) {
                $pw = doDataDecryption(
                    $dataItem['pw'],
                    $decryptedObject
                );
                $arrData['pwd_encryption_error'] = false;
                $arrData['pwd_encryption_error_message'] = '';
            } else {
                $pw = '';
                $arrData['pwd_encryption_error'] = 'inconsistent_password';
                $arrData['pwd_encryption_error_message'] = $lang->get('error_new_ldap_password_detected');
            }
        }

        // check user is admin
        $session__list_restricted_folders_for_items = $session->get('system-list_restricted_folders_for_items') ?? [];
        if (
            (int) $session->get('user-admin') === 1
            && (int) $dataItem['perso'] !== 1
        ) {
            $arrData['show_details'] = 0;
            // ---
            // ---
        } elseif ((
                (in_array($dataItem['id_tree'], $session->get('user-accessible_folders')) === true || (int) $session->get('user-admin') === 1)
                && ((int) $dataItem['perso'] === 0 || ((int) $dataItem['perso'] === 1 && in_array($dataItem['id_tree'], $session->get('user-personal_folders')) === true))
                && $restrictionActive === false)
            || (isset($SETTINGS['anyone_can_modify']) && (int) $SETTINGS['anyone_can_modify'] === 1
                && (int) $dataItem['anyone_can_modify'] === 1
                && (in_array($dataItem['id_tree'], $session->get('user-accessible_folders')) || (int) $session->get('user-admin') === 1)
                && $restrictionActive === false)
            || (null !== $inputData['folderId']
                && isset($session__list_restricted_folders_for_items[$inputData['folderId']])
                && in_array($inputData['id'], $session__list_restricted_folders_for_items[$inputData['folderId']])
                && (int) $post_restricted === 1
                && $user_in_restricted_list_of_item === true)
            || (isset($SETTINGS['restricted_to_roles']) && (int) $SETTINGS['restricted_to_roles'] === 1
                && $restrictionActive === false)
        ) {
            // Check if actual USER can see this ITEM
            // Allow show details
            $arrData['show_details'] = 1;

            // Regarding user's roles, what type of modification is allowed?
            /*$rows = DB::query(
                'SELECT r.type
                FROM '.prefixTable('roles_values').' AS r
                WHERE r.folder_id = %i AND r.role_id IN %ls',
                $dataItem['id_tree'],
                $session->get('user-accessible_folders')
            );
            foreach ($rows as $record) {
                // TODO
            }*/

            // Display menu icon for deleting if user is allowed
            if (
                (int) $dataItem['id_user'] === (int) $session->get('user-id')
                || (int) $session->get('user-admin') === 1
                || ((int) $session->get('user-manager') === 1 && (int) $SETTINGS['manager_edit'] === 1)
                || (int) $dataItem['anyone_can_modify'] === 1
                || in_array($dataItem['id_tree'], $session->get('system-list_folders_editable_by_role')) === true
                || in_array($session->get('user-id'), $restrictedTo) === true
                //|| count($restrictedTo) === 0
                || (int) $post_folder_access_level === 30
                || (int) $post_item_rights >= 40
            ) {
                $arrData['user_can_modify'] = 1;
                $user_is_allowed_to_modify = true;
            } else {
                $arrData['user_can_modify'] = 0;
                $user_is_allowed_to_modify = false;
            }

            // Get restriction list for roles
            $listRestrictionRoles = array();
            if (isset($SETTINGS['restricted_to_roles']) && (int) $SETTINGS['restricted_to_roles'] === 1) {
                // Add restriction if item is restricted to roles
                $rows = DB::query(
                    'SELECT t.title, t.id
                    FROM ' . prefixTable('roles_title') . ' AS t
                    INNER JOIN ' . prefixTable('restriction_to_roles') . ' AS r ON (t.id=r.role_id)
                    WHERE r.item_id = %i
                    ORDER BY t.title ASC',
                    $inputData['id']
                );
                foreach ($rows as $record) {
                    if (!in_array($record['title'], $listRestrictionRoles)) {
                        array_push($listRestrictionRoles, $record['id']);
                    }
                }
            }
            // Check if any KB is linked to this item
            if (isset($SETTINGS['enable_kb']) && (int) $SETTINGS['enable_kb'] === 1) {
                $tmp = array();
                $rows = DB::query(
                    'SELECT k.label, k.id
                    FROM ' . prefixTable('kb_items') . ' as i
                    INNER JOIN ' . prefixTable('kb') . ' as k ON (i.kb_id=k.id)
                    WHERE i.item_id = %i
                    ORDER BY k.label ASC',
                    $inputData['id']
                );
                foreach ($rows as $record) {
                    array_push(
                        $tmp,
                        array(
                            'id' => $record['id'],
                            'label' => $record['label'],
                        )
                    );
                }
                $arrData['links_to_kbs'] = $tmp;
            }
            // Prepare DIalogBox data
            if ((int) $post_expired_item === 0) {
                $arrData['show_detail_option'] = 0;
            } elseif ($user_is_allowed_to_modify === true && (int) $post_expired_item === 1) {
                $arrData['show_detail_option'] = 1;
            } else {
                $arrData['show_detail_option'] = 2;
            }

            $arrData['label'] = $dataItem['label'] === '' ? '' : $dataItem['label'];
            $arrData['pw'] = $pw;
            $arrData['pw_decrypt_info'] = empty($pw) === true && $pwIsEmptyNormal === false ? 'error_no_sharekey_yet' : '';
            $arrData['email'] = empty($dataItem['email']) === true || $dataItem['email'] === null ? '' : $dataItem['email'];
            $arrData['url'] = empty($dataItem['url']) === true ? '' : $dataItem['url'];
            $arrData['folder'] = $dataItem['id_tree'];
            $arrData['description'] = $dataItem['description'];
            $arrData['login'] = $dataItem['login'];
            $arrData['id_restricted_to'] = $listeRestriction;
            $arrData['id_restricted_to_roles'] = $listRestrictionRoles;
            $arrData['tags'] = $tags;
            $arrData['folder'] = (int) $dataItem['id_tree'];
            $arrData['fa_icon'] = $dataItem['fa_icon'];
            $arrData['item_key'] = $dataItem['item_key'];

            if (
                isset($SETTINGS['enable_server_password_change'])
                && (int) $SETTINGS['enable_server_password_change'] === 1
            ) {
                $arrData['auto_update_pwd_frequency'] = $dataItem['auto_update_pwd_frequency'];
            } else {
                $arrData['auto_update_pwd_frequency'] = '0';
            }

            $arrData['anyone_can_modify'] = (int) $dataItem['anyone_can_modify'];

            // Add the fact that item has been viewed in logs
            if (isset($SETTINGS['log_accessed']) && (int) $SETTINGS['log_accessed'] === 1) {
                logItems(
                    $SETTINGS,
                    (int) $inputData['id'],
                    $dataItem['label'],
                    (int) $session->get('user-id'),
                    'at_shown',
                    $session->get('user-login')
                );
            }

            // statistics
            DB::update(
                prefixTable('items'),
                array(
                    'viewed_no' => $dataItem['viewed_no'] + 1,
                    'updated_at' => time(),
                ),
                'id = %i',
                $inputData['id']
            );
            $arrData['viewed_no'] = $dataItem['viewed_no'] + 1;

            // get fields
            $fieldsTmp = array();
            $arrCatList = $template_id = '';
            if (isset($SETTINGS['item_extra_fields']) && (int) $SETTINGS['item_extra_fields'] === 1) {
                // get list of associated Categories
                $arrCatList = array();
                $rows_tmp = DB::query(
                    'SELECT id_category
                    FROM ' . prefixTable('categories_folders') . '
                    WHERE id_folder=%i',
                    $inputData['folderId']
                );
                
                if (DB::count() > 0) {
                    foreach ($rows_tmp as $row) {
                        array_push($arrCatList, (int) $row['id_category']);
                    }

                    // get fields for this Item
                    $rows_tmp = DB::query(
                        'SELECT i.id AS id, i.field_id AS field_id, i.data AS data, i.item_id AS item_id,
                        i.encryption_type AS encryption_type, c.encrypted_data AS encrypted_data, c.parent_id AS parent_id,
                        c.type as field_type, c.masked AS field_masked, c.role_visibility AS role_visibility
                        FROM ' . prefixTable('categories_items') . ' AS i
                        INNER JOIN ' . prefixTable('categories') . ' AS c ON (i.field_id=c.id)
                        WHERE i.item_id=%i AND c.parent_id IN %ls',
                        $inputData['id'],
                        $arrCatList
                    );
                    foreach ($rows_tmp as $row) {
                        // Uncrypt data
                        // Get the object key for the user
                        //db::debugmode(true);
                        $userKey = DB::queryFirstRow(
                            'SELECT share_key
                            FROM ' . prefixTable('sharekeys_fields') . '
                            WHERE user_id = %i AND object_id = %i',
                            $session->get('user-id'),
                            $row['id']
                        );
                        //db::debugmode(false);
                        $fieldText = [];
                        if (DB::count() === 0 && (int) $row['encrypted_data'] === 1) {
                            // Data should be encrypted but no key yet
                            // System is currently creating the keys
                            $fieldText = [
                                'string' => '',
                                'encrypted' => false,
                                'error' => 'error_no_sharekey_yet',
                            ];
                        } else if (DB::count() === 0 && (int) $row['encrypted_data'] === 0) {
                            // Data is not encrypted in DB
                            $fieldText = [
                                'string' => $row['data'],//#3945 - isBase64($row['data']) === true ? base64_decode($row['data']) : 
                                'encrypted' => false,
                                'error' => false,
                            ];
                        } else {
                            // Data is encrypted in DB and we have a key
                            $fieldText = [
                                'string' => doDataDecryption(
                                    $row['data'],
                                    decryptUserObjectKey(
                                        $userKey['share_key'],
                                        $session->get('user-private_key')
                                    )
                                ),
                                'encrypted' => true,
                                'error' => '',
                            ];
                        }

                        // Manage textarea string
                        /*if ($row['field_type'] === 'textarea') {
                            $fieldText = $fieldText;
                        }*/

                        // build returned list of Fields text
                        array_push(
                            $fieldsTmp,
                            array(
                                'id' => (int) $row['field_id'],
                                'value' => $fieldText['string'],
                                'encrypted' => (int) $fieldText['encrypted'],
                                'parent_id' => (int) $row['parent_id'],
                                'type' => $row['field_type'],
                                'masked' => (int) $row['field_masked'],
                                'error' => (string) $fieldText['error'],
                            )
                        );
                    }
                }
            }

            // Now get the selected template (if exists)
            if (isset($SETTINGS['item_creation_templates']) && (int) $SETTINGS['item_creation_templates'] === 1) {
                $rows_tmp = DB::queryfirstrow(
                    'SELECT category_id
                    FROM ' . prefixTable('templates') . '
                    WHERE item_id = %i',
                    $inputData['id']
                );
                if (DB::count() > 0) {
                    $template_id = $rows_tmp['category_id'];
                }
            }
            //}
            $arrData['fields'] = $fieldsTmp;
            $arrData['categories'] = $arrCatList;
            $arrData['template_id'] = (int) $template_id;
            $arrData['to_be_deleted'] = '';

            // Evaluate if item is ready for all users
            $rows_tmp = DB::queryfirstrow(
                'SELECT finished_at
                FROM ' . prefixTable('background_tasks') . '
                WHERE item_id = %i',
                $inputData['id']
            );
            $arrData['item_ready'] = DB::count() === 0 ? true : (DB::count() > 0 && empty($rows_tmp['finished_at']) === true ? false : true);

            // Manage user restriction
            if (null !== $post_restricted) {
                $arrData['restricted'] = $post_restricted;
            } else {
                $arrData['restricted'] = '';
            }
            // Decrement the number before being deleted
            if (isset($SETTINGS['enable_delete_after_consultation']) && (int) $SETTINGS['enable_delete_after_consultation'] === 1) {
                // Is the Item to be deleted?
                $dataDelete = DB::queryfirstrow(
                    'SELECT * 
                    FROM ' . prefixTable('automatic_del') . '
                    WHERE item_id = %i',
                    $inputData['id']
                );
                if (DB::count() > 0) {
                    $arrData['to_be_deleted'] = $dataDelete['del_value'];
                    $arrData['to_be_deleted_type'] = (int) $dataDelete['del_type'];
                }

                // Now delete if required
                if ($dataDelete !== null && ((int) $dataDelete['del_enabled'] === 1
                    || intval($arrData['id_user']) !== intval($session->get('user-id'))))
                {
                    if ((int) $dataDelete['del_type'] === 1 && $dataDelete['del_value'] >= 1) {
                        // decrease counter
                        DB::update(
                            prefixTable('automatic_del'),
                            array(
                                'del_value' => $dataDelete['del_value'] - 1,
                            ),
                            'item_id = %i',
                            $inputData['id']
                        );
                        // store value
                        $arrData['to_be_deleted'] = $dataDelete['del_value'] - 1;
                    } elseif (
                        (int) $dataDelete['del_type'] === 1
                        && $dataDelete['del_value'] <= 1
                        || (int) $dataDelete['del_type'] === 2
                        && $dataDelete['del_value'] < time()
                    ) {
                        $arrData['show_details'] = 0;
                        // delete item
                        DB::delete(prefixTable('automatic_del'), 'item_id = %i', $inputData['id']);
                        // make inactive object
                        DB::update(
                            prefixTable('items'),
                            array(
                                'inactif' => 1,
                                'deleted_at' => time(),
                            ),
                            'id = %i',
                            $inputData['id']
                        );

                        // log
                        logItems(
                            $SETTINGS,
                            (int) $inputData['id'],
                            $dataItem['label'],
                            (int) $session->get('user-id'),
                            'at_delete',
                            $session->get('user-login'),
                            'at_automatically_deleted'
                        );

                        // Update cache table
                        updateCacheTable('delete_value', (int) $inputData['id']);

                        $arrData['show_detail_option'] = 1;
                        $arrData['to_be_deleted'] = 0;
                    } elseif ($dataDelete['del_type'] === '2') {
                        $arrData['to_be_deleted'] = date($SETTINGS['date_format'], (int) $dataDelete['del_value']);
                    }
                } else {
                    $arrData['to_be_deleted'] = '';
                }
            } else {
                $arrData['to_be_deleted'] = $lang->get('no');
            }
            // ---
            // ---
        } else {
            $arrData['show_details'] = 0;
            // get readable list of restriction
            $listOfRestricted = '';
            if (empty($dataItem['restricted_to']) === false) {
                foreach (explode(';', $dataItem['restricted_to']) as $userRest) {
                    if (empty($userRest) === false) {
                        $dataTmp = DB::queryfirstrow('SELECT login FROM ' . prefixTable('users') . ' WHERE id= ' . $userRest);
                        if (empty($listOfRestricted)) {
                            $listOfRestricted = $dataTmp['login'];
                        } else {
                            $listOfRestricted .= ';' . $dataTmp['login'];
                        }
                    }
                }
            }
            $arrData['restricted_to'] = $listOfRestricted;
            $arrData['notification_list'] = '';
            $arrData['notification_status'] = '';
        }

        // Set a timestamp
        $arrData['timestamp'] = time();

        // Set temporary session variable to allow step2
        $session->set('system-show_step2', true);

        // Error
        $arrData['error'] = '';

        // Encrypt data to return
        echo (string) prepareExchangedData(
            $arrData, 
            'encode'
        );
        break;

        /*
        * CASE
        * Display History of the selected Item
    */
    case 'showDetailsStep2':
        // Is this query expected (must be run after a step1 and not standalone)
        if ($session->get('system-show_step2') !== true) {
            // Check KEY and rights
            if ($inputData['key'] !== $session->get('key')) {
                echo (string) prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }
            if ($session->get('user-read_only') === 1) {
                echo (string) prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }
        }

        // prepare return array
        $returnArray = [
            'show_details' => 0,
            'attachments' => [],
            'favourite' => 0,
            'otp_for_item_enabled' => 0,
            'otp_phone_number' => '',
            'otp_secret' => '',
            'users_list' => [],
            'roles_list' => [],
            'has_change_proposal' => 0,
            'setting_restricted_to_roles' => 0,
            'otv_links' => 0,
        ];

        // Load item data
        $dataItem = DB::queryFirstRow(
            'SELECT i.*, n.title AS folder_title, o.enabled AS otp_for_item_enabled, o.phone_number AS otp_phone_number, o.secret AS otp_secret
            FROM ' . prefixTable('items') . ' AS i
            INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON (i.id_tree = n.id)
            INNER JOIN ' . prefixTable('items_otp') . ' AS o ON (o.item_id = i.id)
            WHERE i.id = %i',
            $inputData['id']
        );

        // check that actual user can access this item
        $restrictionActive = true;
        $restrictedTo = is_null($dataItem['restricted_to']) === false ? array_filter(explode(';', $dataItem['restricted_to'])) : [];
        if (
            in_array($session->get('user-id'), $restrictedTo)
            || (((int) $session->get('user-manager') === 1 || (int) $session->get('user-can_manage_all_users') === 1)
                && (int) $SETTINGS['manager_edit'] === 1)
        ) {
            $restrictionActive = false;
        }
        if (empty($dataItem['restricted_to'])) {
            $restrictionActive = false;
        }

        // Check if user has a role that is accepted
        $rows_tmp = DB::query(
            'SELECT role_id
            FROM ' . prefixTable('restriction_to_roles') . '
            WHERE item_id=%i',
            $inputData['id']
        );
        foreach ($rows_tmp as $rec_tmp) {
            if (in_array($rec_tmp['role_id'], explode(';', $session->get('user-roles')))) {
                $restrictionActive = false;
            }
        }

        // check user is admin
        $session__list_restricted_folders_for_items = $session->get('system-list_restricted_folders_for_items') ?? [];
        if (
            (int) $session->get('user-admin') === 1
            && (int) $dataItem['perso'] === 0
        ) {
            $returnArray['show_details'] = 0;
            echo (string) prepareExchangedData(
                $returnArray,
                'encode'
            );
        // Get all expected data about this ITEM
        } else {
            // generate 2d key
            $session->set('user-key_tmp', bin2hex(GenerateCryptKey(16, false, true, true, false, true, $SETTINGS)));

            // Prepare files listing
            $attachments = [];
            
            // launch query
            $rows = DB::query(
                'SELECT id, name, file, extension, size
                FROM ' . prefixTable('files') . '
                WHERE id_item = %i AND confirmed = 1',
                $inputData['id']
            );
            foreach ($rows as $record) {
                $filename = basename($record['name'], '.' . $record['extension']);
                $filename = isBase64($filename) === true ? base64_decode($filename) : $filename;

                array_push(
                    $attachments,
                    array(
                        'icon' => fileFormatImage(strtolower($record['extension'])),
                        'filename' => $filename,
                        'extension' => $record['extension'],
                        'size' => formatSizeUnits((int) $record['size']),
                        'is_image' => in_array(strtolower($record['extension']), TP_IMAGE_FILE_EXT) === true ? 1 : 0,
                        'id' => $record['id'],
                        'key' => $session->get('user-key_tmp'),
                        'internalFilename' => basename($record['name'], '.' . $record['extension']),
                    )
                );
            }
            $returnArray['attachments'] = $attachments;

            // disable add bookmark if alread bookmarked
            $returnArray['favourite'] = in_array($inputData['id'], $session->get('user-favorites')) === true ? 1 : 0;
            
            // get OTP enabled for item
            $returnArray['otp_for_item_enabled'] = (int) $dataItem['otp_for_item_enabled'];
            $returnArray['otp_phone_number'] = (string) $dataItem['otp_phone_number'];
            if (empty($dataItem['otp_secret']) === false) {
                $secret = cryption(
                    $dataItem['otp_secret'],
                    '',
                    'decrypt'
                )['string'];
            } else {
                $secret = '';
            }
            $returnArray['otp_secret'] = (string) $secret;

            // Add this item to the latests list
            if ($session->has('user-latest_items') && $session->has('user-latest_items') && null !== $session->get('user-latest_items') && isset($SETTINGS['max_latest_items']) && 
                in_array($dataItem['id'], $session->get('user-latest_items')) === false
            ) {
                if (count($session->get('user-latest_items')) >= $SETTINGS['max_latest_items']) {
                    // delete last items
                    SessionManager::specificOpsOnSessionArray('user-latest_items', 'pop');
                }
                SessionManager::specificOpsOnSessionArray('user-latest_items', 'unshift', $dataItem['id']);
                // update DB
                DB::update(
                    prefixTable('users'),
                    array(
                        'latest_items' => implode(';', $session->get('user-latest_items')),
                    ),
                    'id=' . $session->get('user-id')
                );
            }

            // get list of roles
            $listOptionsForUsers = array();
            $listOptionsForRoles = array();
            $rows = DB::query(
                'SELECT r.role_id AS role_id, t.title AS title
                FROM ' . prefixTable('roles_values') . ' AS r
                INNER JOIN ' . prefixTable('roles_title') . ' AS t ON (r.role_id = t.id)
                WHERE r.folder_id = %i',
                $dataItem['id_tree']
            );
            foreach ($rows as $record) {
                array_push(
                    $listOptionsForRoles,
                    array(
                        'id' => (int) $record['role_id'],
                        'title' => $record['title'],
                    )
                );
                $rows2 = DB::query(
                    'SELECT id, login, fonction_id, email, name, lastname
                    FROM ' . prefixTable('users') . '
                    WHERE fonction_id LIKE %s',
                    '%' . $record['role_id'] . '%'
                );
                foreach ($rows2 as $record2) {
                    foreach (explode(';', $record2['fonction_id']) as $role) {
                        if (
                            array_search($record2['id'], array_column($listOptionsForUsers, 'id')) === false
                            && $role === $record['role_id']
                        ) {
                            array_push(
                                $listOptionsForUsers,
                                array(
                                    'id' => (int) $record2['id'],
                                    'login' => $record2['login'],
                                    'name' => $record2['name'] . ' ' . $record2['lastname'],
                                    'email' => $record2['email'],
                                )
                            );
                        }
                    }
                }
            }

            $returnArray['users_list'] = $listOptionsForUsers;
            $returnArray['roles_list'] = $listOptionsForRoles;

            // send notification if enabled
            if (isset($SETTINGS['enable_email_notification_on_item_shown']) === true && (int) $SETTINGS['enable_email_notification_on_item_shown'] === 1) {
                // Get path
                $arbo = $tree->getPath($dataItem['id_tree'], true);
                $path = '';
                foreach ($arbo as $elem) {
                    if (empty($path) === true) {
                        $path = htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES) . ' ';
                    } else {
                        $path .= '&#8594; ' . htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES);
                    }
                }
                // Build text to show user
                if (empty($path) === true) {
                    $path = addslashes($dataItem['label']);
                } else {
                    $path = addslashes($dataItem['label']) . ' (' . $path . ')';
                }

                // Add Admins to notification list if expected
                $reveivers = [];
                $rows = DB::query(
                    'SELECT email
                    FROM ' . prefixTable('users').'
                    WHERE admin = %i',
                    1
                );
                foreach ($rows as $user) {
                    array_push($reveivers, $user['email']);
                }

                // prepare sending email
                prepareSendingEmail(
                    $lang->get('email_on_open_notification_subject'),
                    str_replace(
                        array('#tp_user#', '#tp_item#', '#tp_link#'),
                        array(
                            addslashes($session->get('user-login')),
                            $path,
                            $SETTINGS['cpassman_url'] . '/index.php?page=items&group=' . $dataItem['id_tree'] . '&id=' . $dataItem['id'],
                        ),
                        $lang->get('email_on_open_notification_mail')
                    ),
                    implode(",", $reveivers),
                    ""
                );
            }

            // has this item a change proposal
            DB::query('SELECT * FROM ' . prefixTable('items_change') . ' WHERE item_id = %i', $inputData['id']);
            $returnArray['has_change_proposal'] = DB::count();

            // Setting
            $returnArray['setting_restricted_to_roles'] = isset($SETTINGS['restricted_to_roles']) === true
                && (int) $SETTINGS['restricted_to_roles'] === 1 ? 1 : 0;

            // get OTV links
            if (isset($SETTINGS['otv_is_enabled']) === true && (int) $SETTINGS['otv_is_enabled'] === 1) {
                DB::query(
                    'SELECT *
                    FROM ' . prefixTable('otv') . '
                    WHERE item_id = %i
                    AND time_limit > %i',
                    $inputData['id'],
                    time()
                );
                $returnArray['otv_links'] = (int) DB::count();
            }

            $session->set('system-show_step2', false);
            
            // deepcode ignore ServerLeak: Data is encrypted before being sent
            echo (string) prepareExchangedData(
                $returnArray,
                'encode'
            );
        }
        break;

        /*
        * CASE
        * Delete an item
    */
    case 'delete_item':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        if ($session->get('user-read_only') === 1) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );
        
        // Prepare POST variables
        $data = [
            'itemId' => isset($dataReceived['item_id']) === true ? $dataReceived['item_id'] : '',
            'folderId' => isset($dataReceived['folder_id']) === true ? $dataReceived['folder_id'] : '',
            'accessLevel' => isset($dataReceived['access_level']) === true ? $dataReceived['access_level'] : '',
            'itemKey' => isset($dataReceived['item_key']) === true ? $dataReceived['item_key'] : '',
        ];
        
        $filters = [
            'itemId' => 'cast:integer',
            'folderId' => 'cast:integer',
            'accessLevel' => 'cast:integer',
            'itemKey' => 'trim|escape',
        ];
        
        $inputData = dataSanitizer(
            $data,
            $filters,
            $SETTINGS['cpassman_dir']
        );
        
        if (empty($inputData['itemId']) === true && (empty($inputData['itemKey']) === true || is_null($inputData['itemKey']) === true)) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('nothing_to_do'),
                ),
                'encode'
            );
            break;
        }

        // Check that user can access this item
        $granted = accessToItemIsGranted($inputData['itemId'], $SETTINGS);
        if ($granted !== true) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $granted,
                ),
                'encode'
            );
            break;
        }

        // Load item data
        $data = DB::queryFirstRow(
            'SELECT id_tree, id, label
            FROM ' . prefixTable('items') . '
            WHERE id = %i OR item_key = %s',
            $inputData['itemId'],
            $inputData['itemKey']
        );
        if (empty($inputData['itemId']) === true) {
            $inputData['itemId'] = $data['id'];
        }
        $inputData['label'] = $data['label'];

        // delete item consists in disabling it
        DB::update(
            prefixTable('items'),
            array(
                'inactif' => '1',
                'deleted_at' => time(),
            ),
            'id = %i OR item_key = %s',
            $inputData['itemId'],
            $inputData['itemKey']
        );

        // log
        logItems(
            $SETTINGS,
            (int) $inputData['itemId'],
            $inputData['label'],
            $session->get('user-id'),
            'at_delete',
            $session->get('user-login')
        );
        // Update CACHE table
        updateCacheTable('delete_value', (int) $inputData['itemId']);

        echo (string) prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
            ),
            'encode'
        );
        break;

        
    /*
    * CASE
    * Display OTP of the selected Item
    */
    case 'show_opt_code':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        if ($session->get('user-read_only') === 1) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        // Load item data
        $dataItem = DB::queryFirstRow(
            'SELECT secret, enabled
            FROM ' . prefixTable('items_otp') . '
            WHERE item_id = %i',
            $inputData['id']
        );

        if (DB::count() > 0) {
            // OTP exists then display it
            $secret = cryption(
                $dataItem['secret'],
                '',
                'decrypt'
            )['string'];
        }
        
        // Generate OTP code
        if (empty($secret) === false) {
            try {
                $otp = TOTP::createFromSecret($secret);
                $otpCode = $otp->now();
                $otpExpiresIn = $otp->expiresIn();
            } catch (RuntimeException $e) {
                $error = true;
                $otpCode = '';
                $otpExpiresIn = '';
                $message = $e->getMessage();
            }
        } else {
            $otpCode = '';
            $otpExpiresIn = '';
        }
        
        // deepcode ignore ServerLeak: Data is encrypted before being sent
        echo (string) prepareExchangedData(
            array(
                'error' => isset($error) === true ? $error : false,
                'message' => isset($message) === true ? $message : '',
                'otp_code' => $otpCode,
                'otp_expires_in' => $otpExpiresIn,
                'otp_enabled' => $dataItem['enabled'],
            ),
            'encode'
        );
        break;

        /*
    * CASE
    * Update a Group
    */
    case 'update_folder':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        if ($session->get('user-read_only') === 1) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        // Prepare variables
        $title = filter_var(htmlspecialchars_decode($dataReceived['title'], ENT_QUOTES), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $inputData['folderId'] = filter_var(htmlspecialchars_decode($dataReceived['folder']), FILTER_SANITIZE_NUMBER_INT);

        // Check if user is allowed to access this folder
        if (!in_array($inputData['folderId'], $session->get('user-accessible_folders'))) {
            echo '[{"error" : "' . $lang->get('error_not_allowed_to') . '"}]';
            break;
        }

        // Check if title doesn't contains html codes
        if (preg_match_all('|<[^>]+>(.*)</[^>]+>|U', $title, $out)) {
            echo '[ { "error" : "' . $lang->get('error_html_codes') . '" } ]';
            break;
        }
        // check that title is not numeric
        if (is_numeric($title) === true) {
            echo '[{"error" : "ERR_TITLE_ONLY_WITH_NUMBERS"}]';
            break;
        }

        // Check if duplicate folders name are allowed
        if (isset($SETTINGS['duplicate_folder']) && $SETTINGS['duplicate_folder'] === '0') {
            $data = DB::queryFirstRow('SELECT id, title FROM ' . prefixTable('nested_tree') . ' WHERE title = %s', $title);
            if (empty($data['id']) === false && $dataReceived['folder'] !== $data['id']) {
                echo '[ { "error" : "' . $lang->get('error_group_exist') . '" } ]';
                break;
            }
        }

        // query on folder
        $data = DB::queryfirstrow(
            'SELECT parent_id, personal_folder
            FROM ' . prefixTable('nested_tree') . '
            WHERE id = %i',
            $inputData['folderId']
        );

        // check if complexity level is good
        // if manager or admin don't care
        if ($session->get('user-admin') !== 1 && $session->get('user-manager') !== 1 && $data['personal_folder'] === '0') {
            $data = DB::queryfirstrow(
                'SELECT valeur
                FROM ' . prefixTable('misc') . '
                WHERE intitule = %i AND type = %s',
                $data['parent_id'],
                'complex'
            );
            if (intval($dataReceived['complexity']) < intval($data['valeur'])) {
                echo '[ { "error" : "' . $lang->get('error_folder_complexity_lower_than_top_folder') . ' [<b>' . TP_PW_COMPLEXITY[$data['valeur']][1] . '</b>]"} ]';
                break;
            }
        }

        // update Folders table
        $tmp = DB::queryFirstRow(
            'SELECT title, parent_id, personal_folder FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
            $dataReceived['folder']
        );
        if ($tmp['parent_id'] !== 0 || $tmp['title'] !== $session->get('user-id') || $tmp['personal_folder'] !== 1) {
            DB::update(
                prefixTable('nested_tree'),
                array(
                    'title' => $title,
                ),
                'id=%s',
                $inputData['folderId']
            );
            // update complixity value
            DB::update(
                prefixTable('misc'),
                array(
                    'valeur' => $dataReceived['complexity'],
                    'updated_at' => time(),
                ),
                'intitule = %s AND type = %s',
                $inputData['folderId'],
                'complex'
            );
            // rebuild fuild tree folder
            $tree->rebuild();
        }
        // send data
        echo '[{"error" : ""}]';
        break;

        /*
    * CASE
    * Move a Group including sub-folders
    */
    case 'move_folder':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        if ($session->get('user-read_only') === 1) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }
        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );
        $post_source_folder_id = filter_var(htmlspecialchars_decode($dataReceived['source_folder_id']), FILTER_SANITIZE_NUMBER_INT);
        $post_target_folder_id = filter_var(htmlspecialchars_decode($dataReceived['target_folder_id']), FILTER_SANITIZE_NUMBER_INT);

        // Check that user can access this folder
        if ((in_array($post_source_folder_id, $session->get('user-accessible_folders')) === false ||
                in_array($post_target_folder_id, $session->get('user-accessible_folders')) === false) && ($post_target_folder_id === '0' &&
                isset($SETTINGS['can_create_root_folder']) === true && (int) $SETTINGS['can_create_root_folder'] === 1)
        ) {
            $returnValues = '[{"error" : "' . $lang->get('error_not_allowed_to') . '"}]';
            echo $returnValues;
            break;
        }

        $tmp_source = DB::queryFirstRow(
            'SELECT title, parent_id, personal_folder
            FROM ' . prefixTable('nested_tree') . '
            WHERE id = %i',
            $post_source_folder_id
        );

        $tmp_target = DB::queryFirstRow(
            'SELECT title, parent_id, personal_folder
            FROM ' . prefixTable('nested_tree') . '
            WHERE id = %i',
            $post_target_folder_id
        );

        // check if target is not a child of source
        if ($tree->isChildOf($post_target_folder_id, $post_source_folder_id) === true) {
            $returnValues = '[{"error" : "' . $lang->get('error_not_allowed_to') . '"}]';
            echo $returnValues;
            break;
        }

        // check if source or target folder is PF. If Yes, then cancel operation
        if ((int) $tmp_source['personal_folder'] === 1 || (int) $tmp_target['personal_folder'] === 1) {
            $returnValues = '[{"error" : "' . $lang->get('error_not_allowed_to') . '"}]';
            echo $returnValues;
            break;
        }

        // check if source or target folder is PF. If Yes, then cancel operation
        if ($tmp_source['title'] === $session->get('user-id') || $tmp_target['title'] === $session->get('user-id')) {
            $returnValues = '[{"error" : "' . $lang->get('error_not_allowed_to') . '"}]';
            echo $returnValues;
            break;
        }

        // moving SOURCE folder
        DB::update(
            prefixTable('nested_tree'),
            array(
                'parent_id' => $post_target_folder_id,
            ),
            'id=%s',
            $post_source_folder_id
        );
        $tree->rebuild();

        // send data
        echo '[{"error" : ""}]';
        break;

        /*
    * CASE
    * Store hierarchic position of Group
    */
    case 'save_position':
        DB::update(
            prefixTable('nested_tree'),
            array(
                'parent_id' => $inputData['destination'],
            ),
            'id = %i',
            $inputData['source']
        );
        $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
        $tree->rebuild();
        break;

        /*
    * CASE
    * List items of a group
    */
    case 'do_items_list_in_folder':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to')." BOOH 1",
                ),
                'encode'
            );
            break;
        }

        if (count($session->get('user-roles_array')) === 0) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to')." BOOH 2",
                ),
                'encode'
            );
            break;
        }

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        if (is_array($dataReceived) === true && array_key_exists('id', $dataReceived) === false) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_unknown'),
                ),
                'encode'
            );
            break;
        }

        // Prepare POST variables
        $inputData['id'] = filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT);
        $post_restricted = filter_var($dataReceived['restricted'], FILTER_SANITIZE_NUMBER_INT);
        $post_start = filter_var($dataReceived['start'], FILTER_SANITIZE_NUMBER_INT);
        $post_nb_items_to_display_once = filter_var($dataReceived['nb_items_to_display_once'], FILTER_SANITIZE_NUMBER_INT);

        $arr_arbo = [];
        $folderIsPf = in_array($inputData['id'], $session->get('user-personal_folders')) === true ? true : false;
        $showError = 0;
        $itemsIDList = $rights = $returnedData = $uniqueLoadData = $html_json = array();
        // Build query limits
        if (empty($post_start) === true) {
            $start = 0;
        } else {
            $start = $post_start;
        }

        // to do only on 1st iteration
        if ((int) $start === 0) {
            // Prepare tree
            $arbo = $tree->getPath($inputData['id'], true);
            foreach ($arbo as $elem) {
                // Personnal folder
                if ((int) $elem->title === (int) $session->get('user-id') && (int) $elem->nlevel === 1) {
                    $elem->title = $session->get('user-login');
                }
                // Store path elements
                array_push(
                    $arr_arbo,
                    array(
                        'id' => $elem->id,
                        'title' => htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES),
                        'visible' => in_array($elem->id, $session->get('user-accessible_folders')) ? 1 : 0,
                    )
                );
            }
            $uniqueLoadData['path'] = $arr_arbo;

            // store last folder accessed in cookie
            $arr_cookie_options = array (
                'expires' => time() + TP_ONE_DAY_SECONDS * 5,
                'path' => '/', 
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax' // None || Lax  || Strict
            );
            // deepcode ignore WebCookieSecureDisabledByDefault: defined in $arr_cookie_options, deepcode ignore WebCookieHttpOnlyDisabledByDefault: defined in $arr_cookie_options
            setcookie('jstree_select', $inputData['id'], $arr_cookie_options);

            // CHeck if roles have 'allow_pw_change' set to true
            $forceItemEditPrivilege = false;
            foreach ($session->get('user-roles_array') as $role) {
                $roleQ = DB::queryfirstrow(
                    'SELECT allow_pw_change
                    FROM ' . prefixTable('roles_title') . '
                    WHERE id = %i',
                    $role
                );
                if ((int) $roleQ['allow_pw_change'] === 1) {
                    $forceItemEditPrivilege = true;
                    break;
                }
            }

            // is this folder a personal one
            $folder_is_personal = in_array($inputData['id'], $session->get('user-personal_folders'));
            $uniqueLoadData['folder_is_personal'] = $folder_is_personal;

            $folder_is_in_personal = in_array(
                $inputData['id'],
                array_merge(
                    $session->get('user-personal_visible_folders'),
                    $session->get('user-personal_folders')
                )
            );
            $uniqueLoadData['folder_is_in_personal'] = $folder_is_in_personal;


            // check role access on this folder (get the most restrictive) (2.1.23)
            if ((int) $folder_is_personal === 0) {
                $accessLevel = 20;
                $arrTmp = [];
                
                foreach ($session->get('user-roles_array') as $role) {
                    $access = DB::queryFirstRow(
                        'SELECT type FROM ' . prefixTable('roles_values') . ' WHERE role_id = %i AND folder_id = %i',
                        $role,
                        $inputData['id']
                    );
                    if (DB::count()>0) {
                        if ($access['type'] === 'R') {
                            array_push($arrTmp, 10);
                        } elseif ($access['type'] === 'W') {
                            array_push($arrTmp, 30);
                        } elseif (
                            $access['type'] === 'ND'
                            || ($forceItemEditPrivilege === true && $access['type'] === 'NDNE')
                        ) {
                            array_push($arrTmp, 20);
                        } elseif ($access['type'] === 'NE') {
                            array_push($arrTmp, 10);
                        } elseif ($access['type'] === 'NDNE') {
                            array_push($arrTmp, 15);
                        } else {
                            // Ensure to give access Right if allowed folder
                            if (in_array($inputData['id'], $session->get('user-accessible_folders')) === true) {
                                array_push($arrTmp, 30);
                            } else {
                                array_push($arrTmp, 0);
                            }
                        }
                    }
                }
                // 3.0.0.0 - changed  MIN to MAX
                $accessLevel = count($arrTmp) > 0 ? max($arrTmp) : $accessLevel;
            } else {
                $accessLevel = 30;
            }
            $uniqueLoadData['accessLevel'] = $accessLevel;
            $uniqueLoadData['showError'] = $showError;

            // check if items exist
            $where = new WhereClause('and');
            $session__user_list_folders_limited = $session->get('user-list_folders_limited');
            if (null !== $post_restricted && (int) $post_restricted === 1 && empty($session__user_list_folders_limited[$inputData['id']]) === false) {
                $counter = count($session__user_list_folders_limited[$inputData['id']]);
                $uniqueLoadData['counter'] = $counter;
                // check if this folder is visible
            } elseif (!in_array(
                $inputData['id'],
                array_merge(
                    $session->get('user-accessible_folders'),
                    array_keys($session->get('system-list_restricted_folders_for_items')),
                    array_keys($session->get('user-list_folders_limited'))
                )
            )) {
                echo (string) prepareExchangedData(
                    array(
                        'error' => 'not_authorized',
                        'arborescence' => $arr_arbo,
                    ),
                    'encode'
                );
                break;
            } else {
                DB::query(
                    'SELECT *
                    FROM ' . prefixTable('items') . '
                    WHERE inactif = %i',
                    0
                );
                $counter = DB::count();
                $uniqueLoadData['counter'] = $counter;
            }

            // Get folder complexity
            $folderComplexity = DB::queryFirstRow(
                'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %i',
                'complex',
                $inputData['id']
            );
            $folderComplexity = $folderComplexity !== null ? (int) $folderComplexity['valeur'] : 0;
            $uniqueLoadData['folderComplexity'] = $folderComplexity;

            // Has this folder some categories to be displayed?
            $categoriesStructure = array();
            if (isset($SETTINGS['item_extra_fields']) && (int) $SETTINGS['item_extra_fields'] === 1) {
                $folderRow = DB::query(
                    'SELECT id_category
                    FROM ' . prefixTable('categories_folders') . '
                    WHERE id_folder = %i',
                    $inputData['id']
                );
                foreach ($folderRow as $category) {
                    array_push(
                        $categoriesStructure,
                        $category['id_category']
                    );
                }
            }
            $uniqueLoadData['categoriesStructure'] = $categoriesStructure;

            /*$categoriesStructure = array();
            if (isset($SETTINGS['item_extra_fields']) && (int) $SETTINGS['item_extra_fields'] === 1) {
                $folderRow = DB::query(
                    'SELECT f.id_category, c.title AS title
                    FROM '.prefixTable('categories_folders').' AS f
                    INNER JOIN '.prefixTable('categories').' AS c ON (c.id = f.id_category)
                    WHERE f.id_folder = %i',
                    $inputData['id']
                );
                foreach ($folderRow as $category) {
                    $arrFields = array();
                    // Get each category definition with fields
                    $categoryRow = DB::query(
                        "SELECT *
                        FROM ".prefixTable("categories")."
                        WHERE parent_id=%i
                        ORDER BY `order` ASC",
                        $category['id_category']
                    );

                    if (DB::count() > 0) {
                        foreach ($categoryRow as $field) {
                            // Is this Field visibile by user?
                            if ($field['role_visibility'] === 'all'
                                || count(
                                    array_intersect(
                                        explode(';', $session->get('user-roles')),
                                        explode(',', $field['role_visibility'])
                                    )
                                ) > 0
                            ) {
                                array_push(
                                    $arrFields,
                                    array(
                                        $field['id'],
                                        $field['title'],
                                        $field['encrypted_data'],
                                        $field['type'],
                                        $field['masked'],
                                        $field['is_mandatory']
                                    )
                                );
                            }
                        }
                    }

                    // store the categories
                    array_push(
                        $categoriesStructure,
                        array(
                            $category['id_category'],
                            $category['title'],
                            $arrFields
                        )
                    );
                }
            }
            $uniqueLoadData['categoriesStructure'] = $categoriesStructure;
            */

            if ($session->has('system-list_folders_editable_by_role') && $session->has('system-list_folders_editable_by_role') && null !== $session->get('system-list_folders_editable_by_role')) {
                $list_folders_editable_by_role = in_array($inputData['id'], $session->get('system-list_folders_editable_by_role'));
            } else {
                $list_folders_editable_by_role = '';
            }
            $uniqueLoadData['list_folders_editable_by_role'] = $list_folders_editable_by_role;
        } else {
            $uniqueLoadData = json_decode(
                filter_var($dataReceived['uniqueLoadData'], FILTER_UNSAFE_RAW),
                true
            );

            // initialize main variables
            $showError = $uniqueLoadData['showError'];
            $accessLevel = $uniqueLoadData['accessLevel'];
            $counter = $uniqueLoadData['counter'];
            $counter_full = $uniqueLoadData['counter_full'];
            $categoriesStructure = $uniqueLoadData['categoriesStructure'];
            $folderComplexity = $uniqueLoadData['folderComplexity'];
            $folder_is_personal = $uniqueLoadData['folder_is_personal'];
            $folder_is_in_personal = $uniqueLoadData['folder_is_in_personal'];
            //$list_folders_editable_by_role = $uniqueLoadData['list_folders_editable_by_role'];
        }
        
        // prepare query WHere conditions
        $where = new WhereClause('and');
        $session__user_list_folders_limited = $session->get('user-list_folders_limited');
        if (null !== $post_restricted && (int) $post_restricted === 1 && empty($session__user_list_folders_limited[$inputData['id']]) === false) {
            $where->add('i.id IN %ls', $session__user_list_folders_limited[$inputData['id']]);
        } else {
            $where->add('i.id_tree=%i', $inputData['id']);
        }

        // build the HTML for this set of Items
        if ($counter > 0 && empty($showError)) {
            // init variables
            $expired_item = false;
            $limited_to_items = '';

            // List all ITEMS
            if ($folderIsPf === false) {
                $where->add('i.inactif=%i', 0);
                $where->add('l.date=%l', '(SELECT date FROM ' . prefixTable('log_items') . " WHERE action IN ('at_creation', 'at_modification') AND id_item=i.id ORDER BY date DESC LIMIT 1)");
                if (empty($limited_to_items) === false) {
                    $where->add('i.id IN %ls', explode(',', $limited_to_items));
                }

                $query_limit = ' LIMIT ' .
                    $start . ',' .
                    $post_nb_items_to_display_once;
                //db::debugmode(true);
                $rows = DB::query(
                    'SELECT i.id AS id, i.item_key AS item_key, MIN(i.restricted_to) AS restricted_to, MIN(i.perso) AS perso,
                    MIN(i.label) AS label, MIN(i.description) AS description, MIN(i.pw) AS pw, MIN(i.login) AS login,
                    MIN(i.anyone_can_modify) AS anyone_can_modify, l.date AS date, i.id_tree AS tree_id, i.fa_icon AS fa_icon,
                    MIN(n.renewal_period) AS renewal_period,
                    MIN(l.action) AS log_action,
                    l.id_user AS log_user,
                    i.url AS link,
                    i.email AS email
                    FROM ' . prefixTable('items') . ' AS i
                    INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON (i.id_tree = n.id)
                    INNER JOIN ' . prefixTable('log_items') . ' AS l ON (i.id = l.id_item)
                    WHERE %l
                    GROUP BY i.id, l.date, l.id_user, l.action
                    ORDER BY i.label ASC, l.date DESC' . $query_limit,
                    $where
                );
                //db::debugmode(false);
            } else {
                $post_nb_items_to_display_once = 'max';
                $where->add('i.inactif=%i', 0);

                $rows = DB::query(
                    'SELECT i.id AS id, i.item_key AS item_key, MIN(i.restricted_to) AS restricted_to, MIN(i.perso) AS perso,
                    MIN(i.label) AS label, MIN(i.description) AS description, MIN(i.pw) AS pw, MIN(i.login) AS login,
                    MIN(i.anyone_can_modify) AS anyone_can_modify,l.date AS date, i.id_tree AS tree_id, i.fa_icon AS fa_icon,
                    MIN(n.renewal_period) AS renewal_period,
                    MIN(l.action) AS log_action,
                    l.id_user AS log_user,
                    i.url AS link,
                    i.email AS email
                    FROM ' . prefixTable('items') . ' AS i
                    INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON (i.id_tree = n.id)
                    INNER JOIN ' . prefixTable('log_items') . ' AS l ON (i.id = l.id_item)
                    WHERE %l
                    GROUP BY i.id, l.date, l.id_user, l.action
                    ORDER BY i.label ASC, l.date DESC',
                    $where
                );
            }

            $idManaged = '';

            foreach ($rows as $record) {
                // exclude all results except the first one returned by query
                if (empty($idManaged) === true || $idManaged !== $record['id']) {
                    // Fix a bug on Personal Item creation - field `perso` must be set to `1`
                    if ((int) $record['perso'] !== 1 && (int) $folder_is_personal === 1) {
                        DB::update(
                            prefixTable('items'),
                            array(
                                'perso' => 1,
                                'updated_at' => time(),
                            ),
                            'id=%i',
                            $record['id']
                        );
                        $record['perso'] = 1;
                    }

                    // Does this item has restriction to groups of users?
                    $item_is_restricted_to_role = false;
                    DB::queryfirstrow(
                        'SELECT role_id
                        FROM ' . prefixTable('restriction_to_roles') . '
                        WHERE item_id = %i',
                        $record['id']
                    );
                    if (DB::count() > 0) {
                        $item_is_restricted_to_role = true;
                    }

                    // Has this item a restriction to Groups of Users
                    $user_is_included_in_role = false;
                    DB::query(
                        'SELECT role_id
                        FROM ' . prefixTable('restriction_to_roles') . '
                        WHERE item_id = %i AND role_id IN %ls',
                        $record['id'],
                        $session->get('user-roles_array')
                    );
                    if (DB::count() > 0) {
                        $user_is_included_in_role = true;
                    }

                    // Is user in restricted list of users
                    if (empty($record['restricted_to']) === false) {
                        if (
                            in_array($session->get('user-id'), explode(';', $record['restricted_to'])) === true
                            || (((int) $session->get('user-manager') === 1 || (int) $session->get('user-can_manage_all_users') === 1)
                                && (int) $SETTINGS['manager_edit'] === 1)
                        ) {
                            $user_is_in_restricted_list = true;
                        } else {
                            $user_is_in_restricted_list = false;
                        }
                    } else {
                        $user_is_in_restricted_list = false;
                    }

                    // Get Expiration date
                    $expired_item = 0;
                    if (
                        (int) $SETTINGS['activate_expiration'] === 1
                        && $record['renewal_period'] > 0
                        && ($record['date'] + ($record['renewal_period'] * TP_ONE_MONTH_SECONDS)) < time()
                    ) {
                        $expired_item = 1;
                    }
                    // Init
                    $html_json[$record['id']]['expired'] = (int) $expired_item;
                    $html_json[$record['id']]['item_id'] = (int) $record['id'];
                    $html_json[$record['id']]['item_key'] = (string) $record['item_key'];
                    $html_json[$record['id']]['tree_id'] = (int) $record['tree_id'];
                    $html_json[$record['id']]['label'] = strip_tags($record['label']);
                    if (isset($SETTINGS['show_description']) === true && (int) $SETTINGS['show_description'] === 1 && is_null($record['description']) === false && empty($record['description']) === false) {
                        $html_json[$record['id']]['desc'] = mb_substr(preg_replace('#<[^>]+>#', ' ', $record['description']), 0, 200);
                    } else {
                        $html_json[$record['id']]['desc'] = '';
                    }
                    $html_json[$record['id']]['login'] = $record['login'];
                    $html_json[$record['id']]['anyone_can_modify'] = (int) $record['anyone_can_modify'];
                    $html_json[$record['id']]['is_result_of_search'] = 0;
                    $html_json[$record['id']]['is_favourited'] = in_array($record['id'], $session->get('user-favorites')) === true ? 1 : 0;
                    $html_json[$record['id']]['link'] = $record['link'];
                    $html_json[$record['id']]['email'] = $record['email'] ?? '';
                    $html_json[$record['id']]['fa_icon'] = $record['fa_icon'];

                    // Possible values:
                    // 0 -> no access to item
                    // 10 -> appears in list but no view
                    // 20 -> can view without edit (no copy) or move
                    // 30 -> can view without edit (no copy) but can move
                    // 40 -> can edit but not move
                    // 50 -> can edit and move
                    $itemIsPersonal = false;

                    // Let's identify the rights belonging to this ITEM
                    if (
                        (int) $record['perso'] === 1
                        && $record['log_action'] === 'at_creation'
                        && $record['log_user'] === $session->get('user-id')
                        && (int) $folder_is_in_personal === 1
                        && (int) $folder_is_personal === 1
                    ) {
                        // Case 1 - Is this item personal and user its owner?
                        // If yes then allow
                        // If no then continue
                        $itemIsPersonal = true;
                        $right = 70;
                        // ---
                        // ----- END CASE 1 -----
                    } elseif ((($session->has('user-manager') && (int) $session->get('user-manager') && $session->has('user-manager') && (int) $session->get('user-manager') && null !== $session->get('user-manager') && (int) $session->get('user-manager') === 1)
                            || ($session->has('user-can_manage_all_users') && (int) $session->get('user-can_manage_all_users') && $session->has('user-can_manage_all_users') && (int) $session->get('user-can_manage_all_users') && null !== $session->get('user-can_manage_all_users') && (int) $session->get('user-can_manage_all_users') === 1))
                        && (isset($SETTINGS['manager_edit']) === true && (int) $SETTINGS['manager_edit'] === 1)
                        && (int) $record['perso'] !== 1
                        && $user_is_in_restricted_list === true
                    ) {
                        // Case 2 - Is user manager and option "manager_edit" set to true?
                        // Allow all rights
                        $right = 70;
                        // ---
                        // ----- END CASE 2 -----
                    } elseif (
                        (int) $record['anyone_can_modify'] === 1
                        && (int) $record['perso'] !== 1
                        && (int) $session->get('user-read_only') === 0
                    ) {
                        // Case 3 - Has this item the setting "anyone can modify" set to true?
                        // Allow all rights
                        $right = 70;
                        // ---
                        // ----- END CASE 3 -----
                    } elseif (
                        $user_is_in_restricted_list === true
                        && (int) $record['perso'] !== 1
                        && (int) $session->get('user-read_only') === 0
                    ) {
                        // Case 4 - Is this item limited to Users? Is current user in this list?
                        // Allow all rights
                        $right = 70;
                        // ---
                        // ----- END CASE 4 -----
                    } elseif (
                        $user_is_included_in_role === true
                        && (int) $record['perso'] !== 1
                        && (int) $session->get('user-read_only') === 0
                    ) {
                        // Case 5 - Is this item limited to group of users? Is current user in one of those groups?
                        // Allow all rights
                        $right = 60;
                        // ---
                        // ----- END CASE 5 -----
                    } elseif (
                        (int) $record['perso'] !== 1
                        && (int) $session->get('user-read_only') === 1
                    ) {
                        // Case 6 - Is user readonly?
                        // Allow limited rights
                        $right = 10;
                        // ---
                        // ----- END CASE 6 -----
                    } elseif (
                        (int) $record['perso'] !== 1
                        && (int) $session->get('user-read_only') === 1
                    ) {
                        // Case 7 - Is user readonly?
                        // Allow limited rights
                        $right = 10;
                        // ---
                        // ----- END CASE 7 -----
                    } elseif (
                        (int) $record['perso'] !== 1
                        && (int) $session->get('user-read_only') === 1
                    ) {
                        // Case 8 - Is user allowed to access?
                        // Allow rights
                        $right = 10;
                        // ---
                        // ----- END CASE 8 -----
                    } elseif (($user_is_included_in_role === false && $item_is_restricted_to_role === true)
                        && (int) $record['perso'] !== 1
                        && (int) $session->get('user-read_only') === 0
                    ) {
                        // Case 9 - Is this item limited to Users or Groups? Is current user in this list?
                        // If no then Allow none
                        $right = 10;
                        // ---
                        // ----- END CASE 9 -----
                    } else {
                        // Define the access based upon setting on folder
                        // 0 -> no access to item
                        // 10 -> appears in list but no view
                        // 20 -> can view without edit (no copy) or move or delete
                        // 30 -> can view without edit (no copy) or delete but can move
                        // 40 -> can edit but not move and not delete
                        // 50 -> can edit and delete but not move
                        // 60 -> can edit and move but not delete
                        // 70 -> can edit and move
                        if ((int) $accessLevel === 0) {
                            $right = 0;
                        } elseif ((10 <= (int) $accessLevel) && ((int) $accessLevel < 20)) {
                            $right = 20;
                        } elseif ((20 <= (int) $accessLevel) && ((int) $accessLevel < 30)) {
                            $right = 60;
                        } elseif ((int) $accessLevel === 30) {
                            $right = 70;
                        } else {
                            $right = 10;
                        }
                    }

                    // Now finalize the data to send back
                    $html_json[$record['id']]['rights'] = $right;
                    $html_json[$record['id']]['perso'] = 'fa-tag mi-red';
                    $html_json[$record['id']]['sk'] = $itemIsPersonal === true ? 1 : 0;
                    $html_json[$record['id']]['display'] = $right > 0 ? 1 : 0;
                    $html_json[$record['id']]['open_edit'] = in_array($right, array(40, 50, 60, 70)) === true ? 1 : 0;
                    $html_json[$record['id']]['canMove'] = in_array($right, array(30, 60, 70)) === true ? 1 : 0;

                    //*************** */

                    // Build array with items
                    array_push(
                        $itemsIDList,
                        array(
                            'id' => (int) $record['id'],
                            //'display' => $displayItem,
                            'edit' => $html_json[$record['id']]['open_edit'],
                        )
                    );
                }
                $idManaged = $record['id'];
            }

            $rights = recupDroitCreationSansComplexite($inputData['id']);
        }

        // DELETE - 2.1.19 - AND (l.action = 'at_creation' OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%'))
        // count
        if ((int) $start === 0) {
            DB::query(
                'SELECT i.id
                FROM ' . prefixTable('items') . ' as i
                INNER JOIN ' . prefixTable('nested_tree') . ' as n ON (i.id_tree = n.id)
                INNER JOIN ' . prefixTable('log_items') . ' as l ON (i.id = l.id_item)
                WHERE %l
                ORDER BY i.label ASC, l.date DESC',
                $where
            );
            $counter_full = DB::count();
            $uniqueLoadData['counter_full'] = $counter_full;
        }

        // Check list to be continued status
        if ($post_nb_items_to_display_once !== 'max' && ($post_nb_items_to_display_once + $start) < $counter_full) {
            $listToBeContinued = 'yes';
        } else {
            $listToBeContinued = 'end';
        }

        // Prepare returned values
        $returnValues = array(
            'html_json' => $html_json,
            //'folder_requests_psk' => $findPfGroup,
            'arborescence' => $arr_arbo,
            'array_items' => $itemsIDList,
            'error' => $showError,
            //'saltkey_is_required' => $folderIsPf === true ? 1 : 0,
            'show_clipboard_small_icons' => isset($SETTINGS['copy_to_clipboard_small_icons']) && (int) $SETTINGS['copy_to_clipboard_small_icons'] === 1 ? 1 : 0,
            'next_start' => intval($post_nb_items_to_display_once) + intval($start),
            'list_to_be_continued' => $listToBeContinued,
            'items_count' => $counter,
            'counter_full' => $counter_full,
            'folder_complexity' => (int) $folderComplexity,
            'categoriesStructure' => $categoriesStructure,
            'access_level' => $accessLevel,
            'IsPersonalFolder' => $folderIsPf === true ? 1 : 0,
            'uniqueLoadData' => json_encode($uniqueLoadData),
        );
        // Check if $rights is not null
        if (count($rights) > 0) {
            $returnValues = array_merge($returnValues, $rights);
        }

        // Encrypt data to return
        echo (string) prepareExchangedData(
            $returnValues,
            'encode'
        );

        break;

    case 'show_item_password':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // Run query
        $dataItem = DB::queryfirstrow(
            'SELECT i.pw AS pw, s.share_key AS share_key
            FROM ' . prefixTable('items') . ' AS i
            INNER JOIN ' . prefixTable('sharekeys_items') . ' AS s ON (s.object_id = i.id)
            WHERE user_id = %i AND i.item_key = %s',
            $session->get('user-id'),
            $inputData['itemKey']
        );
        
        // Uncrypt PW
        if (DB::count() === 0) {
            // No share key found
            $pw = '';
        } else {
            $pw = doDataDecryption(
                $dataItem['pw'],
                decryptUserObjectKey(
                    $dataItem['share_key'],
                    $session->get('user-private_key')
                )
            );

            $log = 'Used user ID: '.$session->get('user-id')."\n";
            $log .= 'Used user Private key: '.$session->get('user-private_key')."\n";
            $log .= '$currentUserKey: '.$dataItem['share_key']."\n";
            $log .= 'itemKey: '.decryptUserObjectKey(
                $dataItem['share_key'],
                $session->get('user-private_key')
            )."\n\n";
            file_put_contents('/var/www/html/tplog.log', $log, FILE_APPEND | LOCK_EX);
        }

        $returnValues = array(
            'error' => false,
            'password' => $pw,
            'password_error' => '',
        );

        // Encrypt data to return
        echo (string) prepareExchangedData(
            $returnValues,
            'encode'
        );
        break;

        /*
    * CASE
    * Get complexity level of a group
    */
    case 'get_complixity_level':
        // get some info about ITEM
        if (null !== $inputData['itemId'] && empty($inputData['itemId']) === false) {
            // get if existing edition lock
            $dataItemEditionLocks = DB::query(
                'SELECT timestamp, user_id
                FROM ' . prefixTable('items_edition') . '
                WHERE item_id = %i 
                ORDER BY increment_id DESC',
                $inputData['itemId']
            );
            
            if (WIP === true) error_log('Existing edition locks: '.DB::count());

            // Check if item has no edition lock
            if ((int) DB::count() > 0 ) {
                // get last edition lock
                $dataLastItemEditionLock = $dataItemEditionLocks[0];

                // Calculate the edition grace delay
                if (isset($SETTINGS['delay_item_edition']) && $SETTINGS['delay_item_edition'] > 0 && empty($dataTmp['timestamp']) === false) {
                    $delay = $SETTINGS['delay_item_edition'];
                } else {
                    $delay = EDITION_LOCK_PERIOD; // One day delay
                }
                if (WIP === true) error_log('delay: ' . $delay);

                // We remove old edition locks if delay is expired meaning more than 1 day long
                if (round(abs(time() - $dataTmp['timestamp']),0) > $delay) {
                    // Case where time is expired
                    // In this case, delete edition lock and possible ongoing processes
                    // and continue editing this time
                    // We coonsidere if the most recent item is still locked then all other locks can be removed
                    if (WIP === true)  error_log('Delay is expired, removing old locks');
                    foreach ($dataItemEditionLocks as $itemEditionLock) {
                        // delete lock
                        DB::delete(
                            prefixTable('items_edition'), 
                            'item_id = %i', 
                            $inputData['itemId']
                        );

                        // Get process Id
                        $processDetail = DB::queryFirstRow(
                            'SELECT increment_id
                            FROM ' . prefixTable('background_tasks') . '
                            WHERE item_id = %i AND finished_at = ""',
                            $inputData['itemId']
                        );

                        // Delete related Task Process
                        if (DB::count() > 0) {
                            deleteProcessAndRelatedTasks((int) $processDetail['increment_id']);
                        }
                    }
                }

                // check if user is currently the one that is editing
                if ((int) $dataItemEditionLock['user_id'] === (int) $session->get('user-id')) {
                    // CASE where no encryption process is pending
                    // get if existing process ongoing for this item
                    $dataItemProcessOngoing = DB::queryFirstRow(
                        'SELECT JSON_EXTRACT(arguments, "$.all_users_except_id") AS all_users_except_id
                        FROM ' . prefixTable('background_tasks') . '
                        WHERE item_id = %i AND finished_at = ""
                        ORDER BY increment_id DESC',
                        $inputData['itemId']
                    );

                    if ((int) DB::count() === 0) {
                        // Delete the existing edition lock and let the user edit
                        DB::delete(
                            prefixTable('items_edition'), 
                            'item_id = %i AND user_id = %i', 
                            $inputData['itemId'],
                            $session->get('user-id')
                        );
                    } else {
                        // Case where encryption process is pending
                        // Then no edition is possible
                        $returnValues = array(
                            'error' => true,
                            'message' => $lang->get('error_no_edition_possible_locked'),
                        );
                        echo (string) prepareExchangedData(
                            $returnValues,
                            'encode'
                        );
                        break;
                    }
                } elseif (round(abs(time() - $dataTmp['timestamp']),0) <= $delay) {
                    // Case where edition lock is already taken by another user
                    // Then no edition is possible
                    $returnValues = array(
                        'error' => true,
                        'message' => $lang->get('error_no_edition_possible_locked'),
                    );
                    echo (string) prepareExchangedData(
                        $returnValues,
                        'encode'
                    );
                    break;
                }
            }

            // create edition lock on this item for this user
            DB::insert(
                prefixTable('items_edition'),
                array(
                    'timestamp' => time(),
                    'item_id' => $inputData['itemId'],
                    'user_id' => (int) $session->get('user-id'),
                )
            );
        }

        // do query on this folder
        $data_this_folder = DB::queryFirstRow(
            'SELECT id, personal_folder, title
            FROM ' . prefixTable('nested_tree') . '
            WHERE id = %s',
            $inputData['folderId']
        );

        // check if user can perform this action
        if (
            null !== $inputData['context']
            && empty($inputData['context']) === false
        ) {
            if (
                $inputData['context'] === 'create_folder'
                || $inputData['context'] === 'edit_folder'
                || $inputData['context'] === 'delete_folder'
                || $inputData['context'] === 'copy_folder'
            ) {
                if (
                    (int) $session->get('user-admin') !== 1
                    && ((int) $session->get('user-manager') !== 1)
                    && (isset($SETTINGS['enable_user_can_create_folders'])
                        && (int) $SETTINGS['enable_user_can_create_folders'] !== 1)
                    && ((int) $data_this_folder['personal_folder'] !== 1 && $data_this_folder['title'] !== $session->get('user-id'))   // take into consideration if this is a personal folder
                ) {
                    $returnValues = array(
                        'error' => true,
                        'message' => $lang->get('error_not_allowed_to'),
                    );
                    echo (string) prepareExchangedData(
                        $returnValues,
                        'encode'
                    );
                    break;
                }
            }
        }

        // Get required Complexity for this Folder
        $visibilite = '';
        $data = DB::queryFirstRow(
            'SELECT m.valeur, n.personal_folder
            FROM ' . prefixTable('misc') . ' AS m
            INNER JOIN ' . prefixTable('nested_tree') . ' AS n ON (m.intitule = n.id)
            WHERE type=%s AND intitule = %s',
            'complex',
            $inputData['folderId']
        );

        if (isset($data['valeur']) === true && (empty($data['valeur']) === false || $data['valeur'] === '0')) {
            $complexity = TP_PW_COMPLEXITY[$data['valeur']][1];
            $folder_is_personal = (int) $data['personal_folder'];

            // Prepare Item actual visibility (what Users/Roles can see it)
            $rows = DB::query(
                'SELECT t.title
                FROM ' . prefixTable('roles_values') . ' as v
                INNER JOIN ' . prefixTable('roles_title') . ' as t ON (v.role_id = t.id)
                WHERE v.folder_id = %i
                GROUP BY title',
                $inputData['folderId']
            );
            foreach ($rows as $record) {
                if (empty($visibilite)) {
                    $visibilite = $record['title'];
                } else {
                    $visibilite .= ' - ' . $record['title'];
                }
            }
        } else {
            $complexity = $lang->get('not_defined');

            // if not defined, then previous query failed and personal_folder is null
            // do new query to know if current folder is pf
            $data_pf = DB::queryFirstRow(
                'SELECT personal_folder
                FROM ' . prefixTable('nested_tree') . '
                WHERE id = %s',
                $inputData['folderId']
            );
            
            $folder_is_personal = $data_pf !== null ? (int) $data_pf['personal_folder'] : 0;
            
            $visibilite = $session->get('user-name') . ' ' . $session->get('user-lastname') . ' (' . $session->get('user-login') . ')';
        }

        recupDroitCreationSansComplexite($inputData['folderId']);

        // get list of roles
        $listOptionsForUsers = array();
        $listOptionsForRoles = array();
        $rows = DB::query(
            'SELECT r.role_id AS role_id, t.title AS title
            FROM ' . prefixTable('roles_values') . ' AS r
            INNER JOIN ' . prefixTable('roles_title') . ' AS t ON (r.role_id = t.id)
            WHERE r.folder_id = %i',
            $inputData['folderId']
        );
        foreach ($rows as $record) {
            array_push(
                $listOptionsForRoles,
                array(
                    'id' => $record['role_id'],
                    'title' => $record['title'],
                )
            );
            $rows2 = DB::query(
                'SELECT id, login, fonction_id, email, name, lastname
                FROM ' . prefixTable('users') . '
                WHERE admin = 0 AND fonction_id is not null'
            );
            foreach ($rows2 as $record2) {
                foreach (explode(';', $record2['fonction_id']) as $role) {
                    if (
                        array_search($record2['id'], array_column($listOptionsForUsers, 'id')) === false
                        && $role === $record['role_id']
                    ) {
                        array_push(
                            $listOptionsForUsers,
                            array(
                                'id' => $record2['id'],
                                'login' => $record2['login'],
                                'name' => $record2['name'] . ' ' . $record2['lastname'],
                                'email' => $record2['email'],
                            )
                        );
                    }
                }
            }
        }
        
        // Get access level for this folder
        $accessLevel = 20;
        if ($folder_is_personal === 0) {
            $arrTmp = [];
            foreach ($session->get('user-roles_array') as $role) {
                //db::debugmode(true);
                $access = DB::queryFirstRow(
                    'SELECT type
                    FROM ' . prefixTable('roles_values') . '
                    WHERE role_id = %i AND folder_id = %i',
                    $role,
                    $inputData['folderId']
                );
                //db::debugmode(false);
                if (DB::count()>0) {
                    if ($access['type'] === 'R') {
                        array_push($arrTmp, 10);
                    } elseif ($access['type'] === 'W') {
                        array_push($arrTmp, 30);
                    } elseif ($access['type'] === 'ND') {
                        array_push($arrTmp, 20);
                    } elseif ($access['type'] === 'NE') {
                        array_push($arrTmp, 10);
                    } elseif ($access['type'] === 'NDNE') {
                        array_push($arrTmp, 15);
                    } else {
                        // Ensure to give access Right if allowed folder
                        if (in_array($inputData['id'], $session->get('user-accessible_folders')) === true) {
                            array_push($arrTmp, 30);
                        } else {
                            array_push($arrTmp, 0);
                        }
                    }
                }
            }
            // 3.0.0.0 - changed  MIN to MAX
            $accessLevel = count($arrTmp) > 0 ? max($arrTmp) : $accessLevel;
        } elseif ($folder_is_personal === 1) {
            $accessLevel = 30;
        }

        $returnValues = array(
            'folderId' => (int) $inputData['folderId'],
            'error' => false,
            'val' => $data !== null ? (int) $data['valeur'] : 0,
            'visibility' => $visibilite,
            'complexity' => $complexity,
            'personal' => $folder_is_personal,
            'usersList' => $listOptionsForUsers,
            'rolesList' => $listOptionsForRoles,
            'setting_restricted_to_roles' => isset($SETTINGS['restricted_to_roles']) === true
                && (int) $SETTINGS['restricted_to_roles'] === 1 ? 1 : 0,
            'itemAccessRight' => isset($accessLevel) === true ? $accessLevel : '',
        );
        echo (string) prepareExchangedData(
            $returnValues,
            'encode'
        );
        break;

    case 'handle_item_edition_lock':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );
        $itemId = filter_var($dataReceived['item_id'], FILTER_SANITIZE_NUMBER_INT);
        $action = filter_var($dataReceived['action'], FILTER_SANITIZE_SPECIAL_CHARS);

        if ($action === 'release_lock') {
            DB::delete(
                prefixTable('items_edition'), 
                'item_id = %i AND user_id = %i', 
                $itemId,
                $session->get('user-id')
            );
        }
        
        break;

        /*
    * CASE
    * DELETE attached file from an item
    */
    case 'delete_attached_file':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );
        $fileId = filter_var($dataReceived['file_id'], FILTER_SANITIZE_NUMBER_INT);

        // Get some info before deleting
        $data = DB::queryFirstRow(
            'SELECT name, id_item, file
            FROM ' . prefixTable('files') . '
            WHERE id = %i',
            $fileId
        );

        // Load item data
        $data_item = DB::queryFirstRow(
            'SELECT id_tree
            FROM ' . prefixTable('items') . '
            WHERE id = %i',
            $data['id_item']
        );

        // Check that user can access this folder
        if (in_array($data_item['id_tree'], $session->get('user-accessible_folders')) === false) {
            echo (string) prepareExchangedData(
                array('error' => 'ERR_FOLDER_NOT_ALLOWED'),
                'encode'
            );
            break;
        }

        if (empty($data['id_item']) === false) {
            // Delete from FILES table
            DB::delete(
                prefixTable('files'),
                'id = %i',
                $fileId
            );

            // Update the log
            logItems(
                $SETTINGS,
                (int) $data['id_item'],
                $data['name'],
                $session->get('user-id'),
                'at_modification',
                $session->get('user-login'),
                'at_del_file : ' . $data['name']
            );

            // DElete sharekeys
            DB::delete(
                prefixTable('sharekeys_files'),
                'object_id = %i',
                $fileId
            );

            // Delete file from server
            $fileToDelete = $SETTINGS['path_to_upload_folder'] . '/' . TP_FILE_PREFIX . base64_decode($data['file']);
            $fileToDelete = realpath($fileToDelete);
            if ($fileToDelete && strpos($fileToDelete, $SETTINGS['path_to_upload_folder']) === 0) {
                fileDelete($fileToDelete, $SETTINGS);
            }
        }

        echo (string) prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
            ),
            'encode'
        );
        break;

        /*
    * FUNCTION
    * Launch an action when clicking on a quick icon
    * $action = 0 => Make not favorite
    * $action = 1 => Make favorite
    */
    case 'action_on_quick_icon':
        // Check KEY and rights
        if (
            $inputData['key'] !== $session->get('key')
            || $session->get('user-read_only') === 1 || !isset($SETTINGS['pwd_maximum_length'])
        ) {
            // error
            exit;
        }

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );
        $inputData['action'] = (int) filter_var($dataReceived['action'], FILTER_SANITIZE_NUMBER_INT);
        $inputData['itemId'] = (int) filter_var($dataReceived['item_id'], FILTER_SANITIZE_NUMBER_INT);

        if ((int) $inputData['action'] === 0) {
            // Add new favourite
            SessionManager::addRemoveFromSessionArray('user-favorites', [$inputData['itemId']], 'add');
            DB::update(
                prefixTable('users'),
                array(
                    'favourites' => implode(';', $session->get('user-favorites')),
                ),
                'id = %i',
                $session->get('user-id')
            );
            // Update SESSION with this new favourite
            $data = DB::queryfirstrow(
                'SELECT label,id_tree
                FROM ' . prefixTable('items') . '
                WHERE id = %i',
                $inputData['itemId']
            );
            SessionManager::addRemoveFromSessionAssociativeArray(
                'user-favorites_tab',
                [
                    $inputData['itemId'] => [
                        'label' => $data['label'],
                        'url' => 'index.php?page=items&amp;group=' . $data['id_tree'] . '&amp;id=' . $inputData['itemId'],
                    ],
                ],
                'add'
            );
        } elseif ((int) $inputData['action'] === 1) {
            // delete from session
            SessionManager::addRemoveFromSessionArray('user-favorites', [$inputData['itemId']], 'remove');

            // delete from DB
            DB::update(
                prefixTable('users'),
                array(
                    'favourites' => implode(';', $session->get('user-favorites')),
                ),
                'id = %i',
                $session->get('user-id')
            );
            // refresh session fav list
            if ($session->has('user-favorites_tab') && $session->has('user-favorites_tab') && null !== $session->get('user-favorites_tab')) {
                $user_favorites_tab = $session->get('user-favorites_tab');
                foreach ($user_favorites_tab as $key => $value) {
                    if ($key === $inputData['id']) {
                        SessionManager::addRemoveFromSessionAssociativeArray('user-favorites_tab', [$key], 'remove');
                        break;
                    }
                }
            }
        }
        break;

        /*
    * CASE
    * Move an ITEM
    */
    case 'move_item':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        if ($session->get('user-read_only') === 1 || isset($SETTINGS['pwd_maximum_length']) === false) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );
        $inputData['folderId'] = (int) filter_var($dataReceived['folder_id'], FILTER_SANITIZE_NUMBER_INT);
        $inputData['itemId'] = (int) filter_var($dataReceived['item_id'], FILTER_SANITIZE_NUMBER_INT);

        // get data about item
        $dataSource = DB::queryfirstrow(
            'SELECT i.pw, f.personal_folder,i.id_tree, f.title,i.label
            FROM ' . prefixTable('items') . ' as i
            INNER JOIN ' . prefixTable('nested_tree') . ' as f ON (i.id_tree=f.id)
            WHERE i.id=%i',
            $inputData['itemId']
        );

        // get data about new folder
        $dataDestination = DB::queryfirstrow(
            'SELECT personal_folder, title
            FROM ' . prefixTable('nested_tree') . '
            WHERE id = %i',
            $inputData['folderId']
        );

        // Check that user can access this folder
        if (
            in_array($dataSource['id_tree'], $session->get('user-accessible_folders')) === false
            || in_array($inputData['folderId'], $session->get('user-accessible_folders')) === false
            //|| (int) $dataSource['personal_folder'] === (int) $dataDestination['personal_folder']
        ) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        // Manage possible cases
        if ((int) $dataSource['personal_folder'] === 0 && (int) $dataDestination['personal_folder'] === 0) {
            // Previous is non personal folder and new too
            // Just update is needed. Item key is the same
            DB::update(
                prefixTable('items'),
                array(
                    'id_tree' => $inputData['folderId'],
                    'updated_at' => time(),
                ),
                'id=%i',
                $inputData['itemId']
            );
            // ---
            // ---
        } elseif ((int) $dataSource['personal_folder'] === 0 && (int) $dataDestination['personal_folder'] === 1) {
            // Source is public and destination is personal
            // Decrypt and remove all sharekeys (items, fields, files)
            // Encrypt only for the user

            // Remove all item sharekeys items
            DB::delete(
                prefixTable('sharekeys_items'),
                'object_id = %i AND user_id != %i',
                $inputData['itemId'],
                $session->get('user-id')
            );

            // Remove all item sharekeys fields
            // Get fields for this Item
            $rows = DB::query(
                'SELECT id
                FROM ' . prefixTable('categories_items') . '
                WHERE item_id = %i',
                $inputData['itemId']
            );
            foreach ($rows as $field) {
                DB::delete(
                    prefixTable('sharekeys_fields'),
                    'object_id = %i AND user_id != %i',
                    $field['id'],
                    $session->get('user-id')
                );
            }

            // Remove all item sharekeys files
            // Get FILES for this Item
            $rows = DB::query(
                'SELECT id
                FROM ' . prefixTable('files') . '
                WHERE id_item = %i',
                $inputData['itemId']
            );
            foreach ($rows as $attachment) {
                DB::delete(
                    prefixTable('sharekeys_files'),
                    'object_id = %i AND user_id != %i',
                    $attachment['id'],
                    $session->get('user-id')
                );
            }

            // update pw
            DB::update(
                prefixTable('items'),
                array(
                    'id_tree' => $inputData['folderId'],
                    'perso' => 1,
                    'updated_at' => time(),
                ),
                'id=%i',
                $inputData['itemId']
            );
            // ---
            // ---
        } elseif ((int) $dataSource['personal_folder'] === 1 && (int) $dataDestination['personal_folder'] === 1) {
            // If previous is personal folder and new is personal folder too => no key exist on item
            // just update is needed. Item key is the same
            DB::update(
                prefixTable('items'),
                array(
                    'id_tree' => $inputData['folderId'],
                    'updated_at' => time(),
                ),
                'id=%i',
                $inputData['itemId']
            );
            // ---
            // ---
        } elseif ((int) $dataSource['personal_folder'] === 1 && (int) $dataDestination['personal_folder'] === 0) {
            // If previous is personal folder and new is not personal folder => no key exist on item => add new
            // Create keys for all users

            // Get the ITEM object key for the user
            $userKey = DB::queryFirstRow(
                'SELECT share_key
                FROM ' . prefixTable('sharekeys_items') . '
                WHERE user_id = %i AND object_id = %i',
                $session->get('user-id'),
                $inputData['itemId']
            );
            if (DB::count() > 0) {
                $objectKey = decryptUserObjectKey($userKey['share_key'], $session->get('user-private_key'));

                // This is a public object
                $users = DB::query(
                    'SELECT id, public_key
                    FROM ' . prefixTable('users') . '
                    WHERE id NOT IN ("' . OTV_USER_ID . '","' . SSH_USER_ID . '","' . API_USER_ID . '","' . $session->get('user-id') . '")
                    AND public_key != ""'
                );
                foreach ($users as $user) {
                    // Insert in DB the new object key for this item by user
                    DB::insert(
                        prefixTable('sharekeys_items'),
                        array(
                            'object_id' => $inputData['itemId'],
                            'user_id' => (int) $user['id'],
                            'share_key' => encryptUserObjectKey($objectKey, $user['public_key']),
                        )
                    );
                }
            }

            // Get the FIELDS object key for the user
            // Get fields for this Item
            $rows = DB::query(
                'SELECT id
                FROM ' . prefixTable('categories_items') . '
                WHERE item_id = %i',
                $inputData['itemId']
            );
            foreach ($rows as $field) {
                $userKey = DB::queryFirstRow(
                    'SELECT share_key
                    FROM ' . prefixTable('sharekeys_fields') . '
                    WHERE user_id = %i AND object_id = %i',
                    $session->get('user-id'),
                    $field['id']
                );
                if (DB::count() > 0) {
                    $objectKey = decryptUserObjectKey($userKey['share_key'], $session->get('user-private_key'));

                    // This is a public object
                    $users = DB::query(
                        'SELECT id, public_key
                        FROM ' . prefixTable('users') . '
                        WHERE id NOT IN ("' . OTV_USER_ID . '","' . SSH_USER_ID . '","' . API_USER_ID . '","' . $session->get('user-id') . '")
                        AND public_key != ""'
                    );
                    foreach ($users as $user) {
                        // Insert in DB the new object key for this item by user
                        DB::insert(
                            prefixTable('sharekeys_fields'),
                            array(
                                'object_id' => $field['id'],
                                'user_id' => (int) $user['id'],
                                'share_key' => encryptUserObjectKey($objectKey, $user['public_key']),
                            )
                        );
                    }
                }
            }

            // Get the FILE object key for the user
            // Get FILES for this Item
            $rows = DB::query(
                'SELECT id
                FROM ' . prefixTable('files') . '
                WHERE id_item = %i',
                $inputData['itemId']
            );
            foreach ($rows as $attachment) {
                $userKey = DB::queryFirstRow(
                    'SELECT share_key
                    FROM ' . prefixTable('sharekeys_files') . '
                    WHERE user_id = %i AND object_id = %i',
                    $session->get('user-id'),
                    $attachment['id']
                );
                if (DB::count() > 0) {
                    $objectKey = decryptUserObjectKey($userKey['share_key'], $session->get('user-private_key'));

                    // This is a public object
                    $users = DB::query(
                        'SELECT id, public_key
                        FROM ' . prefixTable('users') . '
                        WHERE id NOT IN ("' . OTV_USER_ID . '","' . SSH_USER_ID . '","' . API_USER_ID . '","' . $session->get('user-id') . '")
                        AND public_key != ""'
                    );
                    foreach ($users as $user) {
                        // Insert in DB the new object key for this item by user
                        DB::insert(
                            prefixTable('sharekeys_files'),
                            array(
                                'object_id' => $attachment['id'],
                                'user_id' => (int) $user['id'],
                                'share_key' => encryptUserObjectKey($objectKey, $user['public_key']),
                            )
                        );
                    }
                }
            }

            // update item
            DB::update(
                prefixTable('items'),
                array(
                    'id_tree' => $inputData['folderId'],
                    'perso' => 0,
                    'updated_at' => time(),
                ),
                'id=%i',
                $inputData['itemId']
            );
        }

        // Log item moved
        logItems(
            $SETTINGS,
            (int) $inputData['itemId'],
            $dataSource['label'],
            $session->get('user-id'),
            'at_modification',
            $session->get('user-login'),
            'at_moved : ' . $dataSource['title'] . ' -> ' . $dataDestination['title']
        );

        // Update cache table
        updateCacheTable('update_value', (int) $inputData['itemId']);

        $returnValues = array(
            'error' => '',
            'message' => '',
            'from_folder' => $dataSource['id_tree'],
            'to_folder' => $inputData['folderId'],
        );
        echo (string) prepareExchangedData(
            $returnValues,
            'encode'
        );
        break;

        /*
    * CASE
    * MASSIVE Move an ITEM
    */
    case 'mass_move_items':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        if ($session->get('user-read_only') === 1 || isset($SETTINGS['pwd_maximum_length']) === false) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );
        $inputData['folderId'] = filter_var($dataReceived['folder_id'], FILTER_SANITIZE_NUMBER_INT);
        $post_item_ids = filter_var($dataReceived['item_ids'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // loop on items to move
        foreach (explode(';', $post_item_ids) as $item_id) {
            if (empty($item_id) === false) {
                // get data about item
                $dataSource = DB::queryfirstrow(
                    'SELECT i.pw, f.personal_folder,i.id_tree, f.title,i.label
                    FROM ' . prefixTable('items') . ' as i
                    INNER JOIN ' . prefixTable('nested_tree') . ' as f ON (i.id_tree=f.id)
                    WHERE i.id=%i',
                    $item_id
                );

                // Check that user can access this folder
                if (
                    in_array($dataSource['id_tree'], $session->get('user-accessible_folders')) === false
                    || in_array($inputData['folderId'], $session->get('user-accessible_folders')) === false
                ) {
                    echo (string) prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('error_not_allowed_to'),
                        ),
                        'encode'
                    );
                    exit;
                }

                // get data about new folder
                $dataDestination = DB::queryfirstrow(
                    'SELECT personal_folder, title FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
                    $inputData['folderId']
                );

                // previous is non personal folder and new too
                if (
                    (int) $dataSource['personal_folder'] === 0
                    && (int) $dataDestination['personal_folder'] === 0
                ) {
                    // just update is needed. Item key is the same
                    DB::update(
                        prefixTable('items'),
                        array(
                            'id_tree' => $inputData['folderId'],
                            'updated_at' => time(),
                        ),
                        'id = %i',
                        $item_id
                    );
                    // ---
                    // ---
                    // ---
                } elseif (
                    (int) $dataSource['personal_folder'] === 0
                    && (int) $dataDestination['personal_folder'] === 1
                ) {
                    // Source is public and destination is personal
                    // Decrypt and remove all sharekeys (items, fields, files)
                    // Encrypt only for the user

                    // Remove all item sharekeys items
                    DB::delete(
                        prefixTable('sharekeys_items'),
                        'object_id = %i AND user_id != %i',
                        $item_id,
                        $session->get('user-id')
                    );

                    // Remove all item sharekeys fields
                    // Get fields for this Item
                    $rows = DB::query(
                        'SELECT id
                        FROM ' . prefixTable('categories_items') . '
                        WHERE item_id = %i',
                        $item_id
                    );
                    foreach ($rows as $field) {
                        DB::delete(
                            prefixTable('sharekeys_fields'),
                            'object_id = %i AND user_id != %i',
                            $field['id'],
                            $session->get('user-id')
                        );
                    }

                    // Remove all item sharekeys files
                    // Get FILES for this Item
                    $rows = DB::query(
                        'SELECT id
                        FROM ' . prefixTable('files') . '
                        WHERE id_item = %i',
                        $item_id
                    );
                    foreach ($rows as $attachment) {
                        DB::delete(
                            prefixTable('sharekeys_files'),
                            'object_id = %i AND user_id != %i',
                            $attachment['id'],
                            $session->get('user-id')
                        );
                    }

                    // update pw
                    DB::update(
                        prefixTable('items'),
                        array(
                            'id_tree' => $inputData['folderId'],
                            'perso' => 1,
                            'updated_at' => time(),
                        ),
                        'id = %i',
                        $item_id
                    );
                    // ---
                    // ---
                    // ---
                } elseif (
                    (int) $dataSource['personal_folder'] === 1
                    && (int) $dataDestination['personal_folder'] === 1
                ) {
                    // If previous is personal folder and new is personal folder too => no key exist on item
                    // just update is needed. Item key is the same
                    DB::update(
                        prefixTable('items'),
                        array(
                            'id_tree' => $inputData['folderId'],
                            'updated_at' => time(),
                        ),
                        'id = %i',
                        $item_id
                    );
                    // ---
                    // ---
                    // ---
                } elseif (
                    (int) $dataSource['personal_folder'] === 1
                    && (int) $dataDestination['personal_folder'] === 0
                ) {
                    // If previous is personal folder and new is not personal folder => no key exist on item => add new
                    // Create keys for all users

                    // Get the ITEM object key for the user
                    $userKey = DB::queryFirstRow(
                        'SELECT share_key
                        FROM ' . prefixTable('sharekeys_items') . '
                        WHERE user_id = %i AND object_id = %i',
                        $session->get('user-id'),
                        $item_id
                    );
                    if (DB::count() > 0) {
                        $objectKey = decryptUserObjectKey($userKey['share_key'], $session->get('user-private_key'));

                        // This is a public object
                        $users = DB::query(
                            'SELECT id, public_key
                            FROM ' . prefixTable('users') . '
                            WHERE id NOT IN ("' . OTV_USER_ID . '","' . SSH_USER_ID . '","' . API_USER_ID . '","' . $session->get('user-id') . '")
                            AND public_key != ""'
                        );
                        foreach ($users as $user) {
                            // Insert in DB the new object key for this item by user
                            DB::insert(
                                prefixTable('sharekeys_items'),
                                array(
                                    'object_id' => $item_id,
                                    'user_id' => (int) $user['id'],
                                    'share_key' => encryptUserObjectKey($objectKey, $user['public_key']),
                                )
                            );
                        }
                    }

                    // Get the FIELDS object key for the user
                    // Get fields for this Item
                    $rows = DB::query(
                        'SELECT id
                        FROM ' . prefixTable('categories_items') . '
                        WHERE item_id = %i',
                        $item_id
                    );
                    foreach ($rows as $field) {
                        $userKey = DB::queryFirstRow(
                            'SELECT share_key
                            FROM ' . prefixTable('sharekeys_fields') . '
                            WHERE user_id = %i AND object_id = %i',
                            $session->get('user-id'),
                            $field['id']
                        );
                        if (DB::count() > 0) {
                            $objectKey = decryptUserObjectKey($userKey['share_key'], $session->get('user-private_key'));

                            // This is a public object
                            $users = DB::query(
                                'SELECT id, public_key
                                FROM ' . prefixTable('users') . '
                                WHERE id NOT IN ("' . OTV_USER_ID . '","' . SSH_USER_ID . '","' . API_USER_ID . '","' . $session->get('user-id') . '")
                                AND public_key != ""'
                            );
                            foreach ($users as $user) {
                                // Insert in DB the new object key for this item by user
                                DB::insert(
                                    prefixTable('sharekeys_fields'),
                                    array(
                                        'object_id' => $field['id'],
                                        'user_id' => (int) $user['id'],
                                        'share_key' => encryptUserObjectKey($objectKey, $user['public_key']),
                                    )
                                );
                            }
                        }
                    }

                    // Get the FILE object key for the user
                    // Get FILES for this Item
                    $rows = DB::query(
                        'SELECT id
                        FROM ' . prefixTable('files') . '
                        WHERE id_item = %i',
                        $item_id
                    );
                    foreach ($rows as $attachment) {
                        $userKey = DB::queryFirstRow(
                            'SELECT share_key
                            FROM ' . prefixTable('sharekeys_files') . '
                            WHERE user_id = %i AND object_id = %i',
                            $session->get('user-id'),
                            $attachment['id']
                        );
                        if (DB::count() > 0) {
                            $objectKey = decryptUserObjectKey($userKey['share_key'], $session->get('user-private_key'));

                            // This is a public object
                            $users = DB::query(
                                'SELECT id, public_key
                                FROM ' . prefixTable('users') . '
                                WHERE id NOT IN ("' . OTV_USER_ID . '","' . SSH_USER_ID . '","' . API_USER_ID . '","' . $session->get('user-id') . '")
                                AND public_key != ""'
                            );
                            foreach ($users as $user) {
                                // Insert in DB the new object key for this item by user
                                DB::insert(
                                    prefixTable('sharekeys_files'),
                                    array(
                                        'object_id' => $attachment['id'],
                                        'user_id' => (int) $user['id'],
                                        'share_key' => encryptUserObjectKey($objectKey, $user['public_key']),
                                    )
                                );
                            }
                        }
                    }

                    // update item
                    DB::update(
                        prefixTable('items'),
                        array(
                            'id_tree' => $inputData['folderId'],
                            'perso' => 0,
                            'updated_at' => time(),
                        ),
                        'id=%i',
                        $item_id
                    );
                }
                // Log item moved
                logItems(
                    $SETTINGS,
                    (int) $item_id,
                    $dataSource['label'],
                    $session->get('user-id'),
                    'at_modification',
                    $session->get('user-login'),
                    'at_moved : ' . $dataSource['title'] . ' -> ' . $dataDestination['title']
                );
            }
        }

        // reload cache table
        require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
        updateCacheTable('reload', null);

        echo (string) prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
            ),
            'encode'
        );
        break;

        /*
        * CASE
        * MASSIVE Delete an item
    */
    case 'mass_delete_items':
        // Check KEY and rights
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        if ($session->get('user-read_only') === 1) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );
        $post_item_ids = filter_var($dataReceived['item_ids'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // perform a check in case of Read-Only user creating an item in his PF
        if ($session->get('user-read_only') === 1) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_not_allowed_to'),
                ),
                'encode'
            );
            break;
        }

        // loop on items to move
        foreach (explode(';', $post_item_ids) as $item_id) {
            if (empty($item_id) === false) {
                // get info
                $dataSource = DB::queryfirstrow(
                    'SELECT label, id_tree
                    FROM ' . prefixTable('items') . '
                    WHERE id=%i',
                    $item_id
                );

                // Check that user can access this folder
                if (
                    in_array($dataSource['id_tree'], $session->get('user-accessible_folders')) === false
                ) {
                    echo (string) prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => $lang->get('error_not_allowed_to'),
                        ),
                        'encode'
                    );
                    break;
                }

                // delete item consists in disabling it
                DB::update(
                    prefixTable('items'),
                    array(
                        'inactif' => '1',
                        'deleted_at' => time(),
                    ),
                    'id = %i',
                    $item_id
                );

                // log
                logItems(
                    $SETTINGS,
                    (int) $item_id,
                    $dataSource['label'],
                    $session->get('user-id'),
                    'at_delete',
                    $session->get('user-login')
                );

                // Update CACHE table
                updateCacheTable('delete_value', (int) $item_id);
            }
        }

        echo (string) prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
            ),
            'encode'
        );
        break;

        /*
        * CASE
        * Send email
    */
    case 'send_email':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        if ($session->get('user-read_only') === 1) {
            echo (string) prepareExchangedData(
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
            $inputData['data'],
            'decode'
        );

        // Prepare variables
        $inputData['id'] = filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT);
        $inputData['receipt'] = filter_var($dataReceived['receipt'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $inputData['cat'] = filter_var($dataReceived['cat'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $post_content = $request->request->has('name') ? explode(',', $request->request->filter('content', '', FILTER_SANITIZE_FULL_SPECIAL_CHARS)) : '';

        // get links url
        if (empty($SETTINGS['email_server_url']) === true) {
            $SETTINGS['email_server_url'] = $SETTINGS['cpassman_url'];
        }
        if ($inputData['cat'] === 'request_access_to_author') {
            // Variables
            $dataAuthor = DB::queryfirstrow('SELECT email,login FROM ' . prefixTable('users') . ' WHERE id = ' . $post_content[1]);
            $dataItem = DB::queryfirstrow('SELECT label, id_tree FROM ' . prefixTable('items') . ' WHERE id = ' . $post_content[0]);

            // Get path
            $path = geItemReadablePath(
                (int) $dataItem['id_tree'],
                $dataItem['label'],
                $SETTINGS
            );

            // Prepare email
            prepareSendingEmail(
                $lang->get('email_request_access_subject'),
                str_replace(
                    array('#tp_item_author#', '#tp_user#', '#tp_item#'),
                    array(' ' . addslashes($dataAuthor['login']), addslashes($session->get('user-login')), $path),
                    $lang->get('email_request_access_mail')
                ),
                $dataAuthor['email'],
                ""
            );
        } elseif ($inputData['cat'] === 'share_this_item') {
            $dataItem = DB::queryfirstrow(
                'SELECT label,id_tree
                FROM ' . prefixTable('items') . '
                WHERE id= %i',
                $inputData['id']
            );

            // Get path
            $path = geItemReadablePath(
                (int) $dataItem['id_tree'],
                $dataItem['label'],
                $SETTINGS
            );

            // Prepare email
            prepareSendingEmail(
                $lang->get('email_share_item_subject'),
                str_replace(
                    array(
                        '#tp_link#',
                        '#tp_user#',
                        '#tp_item#',
                    ),
                    array(
                        empty($SETTINGS['email_server_url']) === false ?
                            $SETTINGS['email_server_url'] . '/index.php?page=items&group=' . $dataItem['id_tree'] . '&id=' . $inputData['id'] : $SETTINGS['cpassman_url'] . '/index.php?page=items&group=' . $dataItem['id_tree'] . '&id=' . $inputData['id'],
                        addslashes($session->get('user-login')),
                        addslashes($path),
                    ),
                    $lang->get('email_share_item_mail')
                ),
                $inputData['receipt'],
                ""
            );
        }

        echo (string) prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
            ),
            'encode'
        );
        break;

    /*
    * CASE
    * manage notification of an Item
    */
    /*
    case 'notify_a_user':
        if ($inputData['key'] !== $session->get('key')) {
            echo '[{"error" : "something_wrong"}]';
            break;
        }
        if ($inputData['notifyType'] === 'on_show') {
            // Check if values already exist
            $data = DB::queryfirstrow(
                'SELECT notification FROM ' . prefixTable('items') . ' WHERE id = %i',
                $inputData['itemId']
            );
            $notifiedUsers = explode(';', $data['notification']);
            // User is not in actual notification list
            if ($inputData['status'] === 'true' && !in_array($inputData['userId'], $notifiedUsers)) {
                // User is not in actual notification list and wants to be notified
                DB::update(
                    prefixTable('items'),
                    array(
                        'notification' => empty($data['notification']) ?
                            $inputData['userId'] . ';'
                            : $data['notification'] . $inputData['userId'] ,
                    ),
                    'id=%i',
                    $inputData['itemId']
                );
                echo '[{"error" : "", "new_status":"true"}]';
                break;
            }
            if ($inputData['status'] === 'false' && in_array($inputData['userId'], $notifiedUsers)) {
                // TODO : delete user from array and store in DB
                // User is in actual notification list and doesn't want to be notified
                DB::update(
                    prefixTable('items'),
                    array(
                        'notification' => empty($data['notification']) ?
                        $inputData['userId']
                            : $data['notification'] . ';' . $inputData['userId'],
                    ),
                    'id=%i',
                    $inputData['itemId']
                );
            }
        }
        break;
    */

        /*
    * CASE
    * Item History Log - add new entry
    */
    case 'history_entry_add':
        if ($inputData['key'] !== $session->get('key')) {
            $data = array('error' => 'key_is_wrong');
            echo (string) prepareExchangedData(
                $data,
                'encode'
            );
            break;
        }

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        $item_id = filter_var($dataReceived['item_id'], FILTER_SANITIZE_NUMBER_INT);
        $label = filter_var($dataReceived['label'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $date = filter_var($dataReceived['date'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $time = filter_var($dataReceived['time'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $session__user_list_folders_limited = $session->get('user-list_folders_limited');

        // Get all informations for this item
        $dataItem = DB::queryfirstrow(
            'SELECT *
            FROM ' . prefixTable('items') . ' as i
            INNER JOIN ' . prefixTable('log_items') . ' as l ON (l.id_item = i.id)
            WHERE i.id=%i AND l.action = %s',
            $item_id,
            'at_creation'
        );
        // check that actual user can access this item
        $restrictionActive = true;
        $restrictedTo = is_null($dataItem['restricted_to']) === false ? array_filter(explode(';', $dataItem['restricted_to'])) : [];
        if (in_array($session->get('user-id'), $restrictedTo)) {
            $restrictionActive = false;
        }
        if (empty($dataItem['restricted_to'])) {
            $restrictionActive = false;
        }

        if (((in_array($dataItem['id_tree'], $session->get('user-accessible_folders'))) && ((int) $dataItem['perso'] === 0 || ((int) $dataItem['perso'] === 1 && $dataItem['id_user'] === $session->get('user-id'))) && $restrictionActive === false)
            || (isset($SETTINGS['anyone_can_modify']) && (int) $SETTINGS['anyone_can_modify'] === 1 && (int) $dataItem['anyone_can_modify'] === 1 && (in_array($dataItem['id_tree'], $session->get('user-accessible_folders')) || (int) $session->get('user-admin') === 1) && $restrictionActive === false)
            || (is_array($session__user_list_folders_limited[$inputData['folderId']]) === true && in_array($inputData['id'], $session__user_list_folders_limited[$inputData['folderId']]) === true)
        ) {
            // Query
            logItems(
                $SETTINGS,
                (int) $item_id,
                $dataItem['label'],
                $session->get('user-id'),
                'at_manual',
                $session->get('user-login'),
                htmlspecialchars_decode($label, ENT_QUOTES),
                null,
                (string) dateToStamp($date.' '.$time, $SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'])
            );
            // Prepare new line
            $data = DB::queryfirstrow(
                'SELECT * FROM ' . prefixTable('log_items') . ' WHERE id_item = %i ORDER BY date DESC',
                $item_id
            );
            $historic = date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $data['date']) . ' - ' . $session->get('user-login') . ' - ' . $lang->get($data['action']) . ' - ' . $data['raison'];
            // send back
            $data = array(
                'error' => '',
                'new_line' => '<br>' . addslashes($historic),
            );
            echo (string) prepareExchangedData(
                $data,
                'encode'
            );
        } else {
            $data = array('error' => 'something_wrong');
            echo (string) prepareExchangedData(
                $data,
                'encode'
            );
            break;
        }
        break;

        /*
    * CASE
    * Free Item for Edition
    */
    case 'free_item_for_edition':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo '[ { "error" : "key_not_conform" } ]';
            break;
        }
        // Do
        DB::delete(
            prefixTable('items_edition'),
            'item_id = %i',
            $inputData['id']
        );
        break;

        /*
    * CASE
    * Check if Item has been changed since loaded
    */
    /*
    case 'is_item_changed':
        $data = DB::queryFirstRow(
            'SELECT date FROM ' . prefixTable('log_items') . ' WHERE action = %s AND id_item = %i ORDER BY date DESC',
            'at_modification',
            $inputData['itemId']
        );
        // Check if it's in a personal folder. If yes, then force complexity overhead.
        if ((int) $data['date'] > (int) $inputData['timestamp']) {
            echo '{ "modified" : "1" }';
        } else {
            echo '{ "modified" : "0" }';
        }
        break;
        */

        /*
    * CASE
    * Check if Item has been changed since loaded
    */
    case 'generate_OTV_url':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo '[ { "error" : "key_not_conform" } ]';
            break;
        }

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        // delete all existing old otv codes
        $rows = DB::query('SELECT id FROM ' . prefixTable('otv') . ' WHERE time_limit < ' . time());
        foreach ($rows as $record) {
            DB::delete(prefixTable('otv'), 'id=%i', $record['id']);
        }

        // generate session
        $otv_code = GenerateCryptKey(32, false, true, true, false, true, $SETTINGS);
        $otv_key = GenerateCryptKey(32, false, true, true, false, true, $SETTINGS);

        // Generate Defuse key
        $otv_user_code_encrypted = defuse_generate_personal_key($otv_key);

        // check if psk is correct.
        $otv_key_encoded = defuse_validate_personal_key(
            $otv_key,
            $otv_user_code_encrypted
        );

        // Decrypt the pwd
        // Should we log a password change?
        $itemQ = DB::queryFirstRow(
            'SELECT s.share_key, i.pw
            FROM ' . prefixTable('items') . ' AS i
            INNER JOIN ' . prefixTable('sharekeys_items') . ' AS s ON (i.id = s.object_id)
            WHERE s.user_id = %i AND s.object_id = %i',
            $session->get('user-id'),
            $dataReceived['id']
        );
        if (DB::count() === 0 || empty($itemQ['pw']) === true) {
            // No share key found
            $pw = '';
        } else {
            $pw = base64_decode(doDataDecryption(
                $itemQ['pw'],
                decryptUserObjectKey(
                    $itemQ['share_key'],
                    $session->get('user-private_key')
                )
            ));
        }

        // Encrypt it with DEFUSE using the generated code as key
        // This is required as the OTV is used by someone without any Teampass account
        $passwd = cryption(
            $pw,
            $otv_key_encoded,
            'encrypt',
            $SETTINGS
        );
        $timestampReference = time();

        DB::insert(
            prefixTable('otv'),
            array(
                'id' => null,
                'item_id' => $dataReceived['id'],
                'timestamp' => $timestampReference,
                'originator' => intval($session->get('user-id')),
                'code' => $otv_code,
                'encrypted' => $passwd['string'],
                'time_limit' => (int) $dataReceived['days'] * (int) TP_ONE_DAY_SECONDS + time(),
                'max_views' => (int) $dataReceived['views'],
                'shared_globaly' => 0,
            )
        );
        $newID = DB::insertId();

        // Prepare URL content
        $otv_session = array(
            'otv' => true,
            'code' => $otv_code,
            'key' => $otv_key_encoded,
            'stamp' => $timestampReference,
        );

        if (isset($SETTINGS['otv_expiration_period']) === false) {
            $SETTINGS['otv_expiration_period'] = 7;
        }
        $url = $SETTINGS['cpassman_url'] . '/index.php?' . http_build_query($otv_session);

        echo json_encode(
            array(
                'error' => '',
                'url' => $url,
                'otv_id' => $newID,
            )
        );
        break;

    /*
    * CASE
    * Check if Item has been changed since loaded
    */
    case 'update_OTV_url':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo '[ { "error" : "key_not_conform" } ]';
            break;
        }

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        // get parameters from original link
        $url = $dataReceived['original_link'];
        $parts = parse_url($url);
        if(isset($parts['query'])){
            parse_str($parts['query'], $orignal_link_parameters);
        } else {
            $orignal_link_parameters = array();
        }

        // update database
        DB::update(
            prefixTable('otv'),
            array(
                'time_limit' => (int) $dataReceived['days'] * (int) TP_ONE_DAY_SECONDS + time(),
                'max_views' => (int) $dataReceived['views'],
                'shared_globaly' => (int) $dataReceived['shared_globaly'] === 1 ? 1 : 0,
            ),
            'id = %i',
            $dataReceived['otv_id']
        );

        // Prepare URL content
        $otv_session = [
            'otv' => true,
            'code' => $orignal_link_parameters['code'],
            'key' => $orignal_link_parameters['key'],
            'stamp' => $orignal_link_parameters['stamp'],
        ];

        if ((int) $dataReceived['shared_globaly'] === 1 && isset($SETTINGS['otv_subdomain']) === true && empty($SETTINGS['otv_subdomain']) === false) {
            // Inject subdomain in URL by convering www. to subdomain.
            $domain_scheme = parse_url($SETTINGS['cpassman_url'], PHP_URL_SCHEME);
            $domain_host = parse_url($SETTINGS['cpassman_url'], PHP_URL_HOST);
            if (str_contains($domain_host, 'www.') === true) {
                $domain_host = (string) $SETTINGS['otv_subdomain'] . '.' . substr($domain_host, 4);
            } else {
                $domain_host = (string) $SETTINGS['otv_subdomain'] . '.' . $domain_host;
            }
            $url = $domain_scheme.'://'.$domain_host . '/index.php?'.http_build_query($otv_session);
        } else {
            $url = $SETTINGS['cpassman_url'] . '/index.php?'.http_build_query($otv_session);
        }

        echo (string) prepareExchangedData(
            array(
                'error' => false,
                'new_url' => $url,
            ),
            'encode'
        );
        break;


        /*
    * CASE
    * Free Item for Edition
    */
    case 'image_preview_preparation':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // get file info
        $file_info = DB::queryfirstrow(
            'SELECT f.id AS id, f.file AS file, f.name AS name, f.status AS status,
            f.extension AS extension, f.type AS type,
            s.share_key AS share_key
            FROM ' . prefixTable('files') . ' AS f
            INNER JOIN ' . prefixTable('sharekeys_files') . ' AS s ON (f.id = s.object_id)
            WHERE s.user_id = %i AND s.object_id = %i',
            $session->get('user-id'),
            $inputData['id']
        );

        // Check if user has this sharekey
        if (empty($file_info['share_key']) === true) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('no_sharekey_found'),
                ),
                'encode'
            );
            break;
        }

        //$fileName = basename($file_info['name'], '.'.$file_info['extension']);

        // prepare image info
        $post_title = basename($file_info['name'], '.' . $file_info['extension']);
        $post_title = isBase64($post_title) === true ? base64_decode($post_title) : $post_title;
        
        // Get image content
        // deepcode ignore PT: File and path are secured directly inside the function decryptFile()
        $fileContent = decryptFile(
            $file_info['file'],
            $SETTINGS['path_to_upload_folder'],
            decryptUserObjectKey($file_info['share_key'], $session->get('user-private_key'))
        );

        // Encrypt data to return
        echo (string) prepareExchangedData(
            array(
                'error' => false,
                'filename' => $post_title . '.' . $file_info['extension'],
                'file_type' => $file_info['type'],
                'file_content' => $fileContent,
            ),
            'encode'
        );
        break;

        /*
    * CASE
    * Free Item for Edition
    */
    /*
    case 'delete_file':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo '[ { "error" : "key_not_conform" } ]';
            break;
        }

        // get file info
        $result = DB::queryfirstrow(
            'SELECT file FROM ' . prefixTable('files') . ' WHERE id=%i',
            intval(substr(filter_input(INPUT_POST, 'uri', FILTER_SANITIZE_FULL_SPECIAL_CHARS), 1))
        );

        fileDelete($SETTINGS['path_to_upload_folder'] . '/' . $result['file'] . $inputData['fileSuffix'], $SETTINGS);

        break;
        */

        /*
    * CASE
    * Get list of users that have access to the folder
    */
    case 'check_for_title_duplicate':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo '[ { "error" : "key_not_conform" } ]';
            break;
        }
        $duplicate = 0;

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );
        // Prepare variables
        $label = htmlspecialchars_decode($dataReceived['label']);
        $idFolder = $dataReceived['idFolder'];

        // don't check if Personal Folder
        $data = DB::queryFirstRow('SELECT title FROM ' . prefixTable('nested_tree') . ' WHERE id = %i', $idFolder);
        if ($data['title'] === $session->get('user-id')) {
            // send data
            echo '[{"duplicate" : "' . $duplicate . '" , error" : ""}]';
        } else {
            if ($inputData['option'] === 'same_folder') {
                // case unique folder
                DB::query(
                    'SELECT label
                    FROM ' . prefixTable('items') . '
                    WHERE id_tree = %i AND label = %s',
                    $idFolder,
                    $label
                );
            } else {
                // case complete database

                //get list of personal folders
                $arrayPf = array();
                if (empty($row['id']) === false) {
                    $rows = DB::query(
                        'SELECT id FROM ' . prefixTable('nested_tree') . ' WHERE personal_folder = %i',
                        '1'
                    );
                    foreach ($rows as $record) {
                        if (!in_array($record['id'], $arrayPf)) {
                            array_push($arrayPf, $record['id']);
                        }
                    }
                }

                // build WHERE condition
                $where = new WhereClause('and');
                $where->add('id_tree = %i', $idFolder);
                $where->add('label = %s', $label);
                if (empty($arrayPf) === false) {
                    $where->add('id_tree NOT IN (' . implode(',', $arrayPf) . ')');
                }

                DB::query(
                    'SELECT label
                    FROM ' . prefixTable('items') . '
                    WHERE %l',
                    $where
                );
            }

            // count results
            if (DB::count() > 0) {
                $duplicate = 1;
            }

            // send data
            echo '[{"duplicate" : "' . $duplicate . '" , "error" : ""}]';
        }
        break;

        /*
    * CASE
    * Get list of users that have access to the folder
    */
    case 'refresh_visible_folders':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        $arr_data = [];
        $arrayFolders = [];

        // decrypt and retreive data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        // Will we show the root folder?
        if ($session->has('user-can_create_root_folder') && (int) $session->get('user-can_create_root_folder') && $session->has('user-can_create_root_folder') && (int) $session->get('user-can_create_root_folder') && null !== $session->get('user-can_create_root_folder') && (int) $session->get('user-can_create_root_folder') === 1
        ) {
            $arr_data['can_create_root_folder'] = 1;
        } else {
            $arr_data['can_create_root_folder'] = 0;
        }

        // do we have a cache to be used?
        if (isset($dataReceived['force_refresh_cache']) === true && $dataReceived['force_refresh_cache'] === false) {
            $goCachedFolders = loadFoldersListByCache('visible_folders', 'folders');
            if ($goCachedFolders['state'] === true) {
                $arr_data['folders'] = json_decode($goCachedFolders['data'], true);
                // send data
                echo (string) prepareExchangedData(
                    [
                        'error' => 'false',
                        'html_json' => ($arr_data),
                        'extra' => isset($goCachedFolders['extra']) ? $goCachedFolders['extra'] : '',
                    ],
                    'encode'
                );
                break;
            }
        }
        // Build list of visible folders
        if (
            (int) $session->get('user-admin') === 1
        ) {
            $session->set('user-accessible_folders', $session->get('user-personal_visible_folders'));
        }

        if (null !== $session->get('user-list_folders_limited') && count($session->get('user-list_folders_limited')) > 0) {
            $listFoldersLimitedKeys = array_keys($session->get('user-list_folders_limited'));
        } else {
            $listFoldersLimitedKeys = array();
        }
        // list of items accessible but not in an allowed folder
        if (
            null !== $session->get('system-list_restricted_folders_for_items') &&
            count($session->get('system-list_restricted_folders_for_items')) > 0
        ) {
            $listRestrictedFoldersForItemsKeys = array_keys($session->get('system-list_restricted_folders_for_items'));
        } else {
            $listRestrictedFoldersForItemsKeys = array();
        }
        
        //Build tree
        $tree->rebuild();
        $folders = $tree->getDescendants();
        foreach ($folders as $folder) {
            // Be sure that user can only see folders he/she is allowed to
            if (
                in_array($folder->id, $session->get('user-forbiden_personal_folders')) === false
                || in_array($folder->id, $session->get('user-accessible_folders')) === true
                || in_array($folder->id, $listFoldersLimitedKeys) === true
                || in_array($folder->id, $listRestrictedFoldersForItemsKeys) === true
            ) {
                // Init
                $displayThisNode = false;

                // Check if any allowed folder is part of the descendants of this node
                $nodeDescendants = $tree->getDescendantsFromTreeArray($folders, $folder->id);
                foreach ($nodeDescendants as $node) {
                    // manage tree counters
                    if (
                        in_array($node, array_merge($session->get('user-accessible_folders'), $session->get('system-list_restricted_folders_for_items'))) === true
                        || (is_array($listFoldersLimitedKeys) === true && in_array($node, $listFoldersLimitedKeys) === true)
                        || (is_array($listRestrictedFoldersForItemsKeys) === true && in_array($node, $listRestrictedFoldersForItemsKeys) === true)
                    ) {
                        $displayThisNode = true;
                        //break;
                    }
                }

                if ($displayThisNode === true) {
                    // ALL FOLDERS
                    // Build path
                    $arbo = $tree->getPath($folder->id, false);
                    $path = '';
                    foreach ($arbo as $elem) {
                        $path = (empty($path) ? '' : $path . ' / ') . htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES);
                    }

                    // Build array
                    array_push($arrayFolders, [
                        'id' => (int) $folder->id,
                        'level' => (int) $folder->nlevel,
                        'title' => ((int) $folder->title === (int) $session->get('user-id') && (int) $folder->nlevel === 1) ? $session->get('user-login') : $folder->title,
                        'disabled' => (
                            in_array($folder->id, $session->get('user-accessible_folders')) === false
                            || in_array($folder->id, $session->get('user-read_only_folders')) === true
                        ) ? 1 : 0,
                        'parent_id' => (int) $folder->parent_id,
                        'perso' => (int) $folder->personal_folder,
                        'path' => htmlspecialchars($path),
                        'is_visible_active' => (null !== $session->get('user-read_only_folders') && in_array($folder->id, $session->get('user-read_only_folders'))) ? 1 : 0,
                    ]);
                }
            }
        }
        if (empty($arrayFolders) === false) {
            // store array to return
            $arr_data['folders'] = $arrayFolders;

            // update session
            $session->set('user-folders_list', $arr_data['folders']);

            // update cache
            cacheTreeUserHandler(
                (int) $session->get('user-id'),
                json_encode($arr_data['folders']),
                $SETTINGS,
                'visible_folders',
            );
        }

        // send data
        echo (string) prepareExchangedData(
            [
                'error' => 'false',
                'html_json' => $arr_data,
            ],
            'encode'
        );

        break;

        /*
    * CASE
    * Get list of users that have access to the folder
    */
    case 'refresh_folders_other_info':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        $ret = [];
        $foldersArray = json_decode($inputData['data'], true);
        if (is_array($foldersArray) === true && $inputData['data'] !== '[null]') {
            $rows = DB::query(
                'SELECT id, categories
                FROM ' . prefixTable('nested_tree') . '
                WHERE id IN (%l)',
                implode(',', $foldersArray)
            );
            foreach ($rows as $record) {
                if (empty($record['categories']) === false) {
                    array_push(
                        $ret,
                        array($record['id'] => json_decode($record['categories'], true))
                    );
                }
            }
        }

        // send data
        echo (string) prepareExchangedData(
            [
                'error' => '',
                'result' => $ret,
            ],
            'encode'
        );

        break;

        /*
    * CASE
    * Load item history
    */
    case 'load_item_history':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array('error' => 'ERR_KEY_NOT_CORRECT'),
                'encode'
            );
            break;
        }
        
        // get item info
        $dataItem = DB::queryFirstRow(
            'SELECT *
            FROM ' . prefixTable('items') . '
            WHERE id=%i',
            $inputData['itemId']
        );

        // get item history
        $history = [];
        $previous_passwords = [];
        $rows = DB::query(
            'SELECT l.date as date, l.action as action, l.raison as raison,
                u.login as login, u.avatar_thumb as avatar_thumb, u.name as name, u.lastname as lastname,
                l.old_value as old_value
            FROM ' . prefixTable('log_items') . ' as l
            INNER JOIN ' . prefixTable('users') . ' as u ON (l.id_user=u.id)
            WHERE id_item=%i AND l.action NOT IN (%l)
            ORDER BY date DESC',
            $inputData['itemId'],
            '"at_shown","at_password_copied", "at_shown", "at_password_shown"'
        );
        foreach ($rows as $record) {
            if (empty($record['raison']) === true) {
                $reason[0] = '';
            } else {
                $reason = array_map('trim', explode(':', $record['raison']));
            }
            
            // imported via API
            if (empty($record['login']) === true) {
                $record['login'] = $lang->get('imported_via_api') . ' [' . $record['raison'] . ']';
            }
            
            // Prepare avatar
            if (isset($record['avatar_thumb']) && empty($record['avatar_thumb']) === false) {
                if (file_exists($SETTINGS['cpassman_dir'] . '/includes/avatars/' . $record['avatar_thumb'])) {
                    $avatar = $SETTINGS['cpassman_url'] . '/includes/avatars/' . $record['avatar_thumb'];
                } else {
                    $avatar = $SETTINGS['cpassman_url'] . '/includes/images/photo.jpg';
                }
            } else {
                $avatar = $SETTINGS['cpassman_url'] . '/includes/images/photo.jpg';
            }

            // Prepare action
            $action = '';
            $detail = '';
            if ($reason[0] === 'at_pw') {
                $action = $lang->get($reason[0]);
                
                // get previous password
                if (empty($record['old_value']) === false) {
                    $previous_pwd = cryption(
                        $record['old_value'],
                        '',
                        'decrypt'
                    );
                    array_push(
                        $previous_passwords, 
                        [
                            'password' => htmlentities($previous_pwd['string']),
                            'date' => date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['date']),
                        ]
                    );
                }
            } elseif ($record['action'] === 'at_manual') {
                $detail = $reason[0];
                $action = $lang->get($record['action']);
            } elseif ($reason[0] === 'at_description') {
                $action = $lang->get('description_has_changed');
            } elseif (empty($record['raison']) === false && $reason[0] !== 'at_creation') {
                $action = $lang->get($reason[0]);
                if ($reason[0] === 'at_moved') {
                    $tmp = explode(' -> ', $reason[1]);
                    $detail = $lang->get('from') . ' <span class="font-weight-light">' . $tmp[0] . '</span> ' . $lang->get('to') . ' <span class="font-weight-light">' . $tmp[1] . ' </span>';
                } elseif ($reason[0] === 'at_field') {
                    $tmp = explode(' => ', $reason[1]);
                    if (count($tmp) > 1) {
                        $detail = '<b>' . trim($tmp[0]) . '</b> | ' . $lang->get('previous_value') .
                            ': <span class="font-weight-light">' . trim($tmp[1]) . '</span>';
                    } else {
                        $detail = trim($reason[1]);
                    }
                } elseif (in_array($reason[0], array('at_restriction', 'at_email', 'at_login', 'at_label', 'at_url', 'at_tag')) === true) {
                    $tmp = explode(' => ', $reason[1]);
                    $detail = empty(trim($tmp[0])) === true ?
                        $lang->get('no_previous_value') : $lang->get('previous_value') . ': <span class="font-weight-light">' . $tmp[0] . ' </span>';
                } elseif ($reason[0] === 'at_automatic_del') {
                    $detail = $lang->get($reason[1]);
                } elseif ($reason[0] === 'at_anyoneconmodify' || $reason[0] === 'at_otp_status') {
                    $detail = $lang->get($reason[1]);
                } elseif ($reason[0] === 'at_add_file' || $reason[0] === 'at_del_file') {
                    $tmp = explode(':', $reason[1]);
                    $tmp = explode('.', $tmp[0]);
                    $detail = isBase64($tmp[0]) === true ?
                        base64_decode($tmp[0]) . '.' . $tmp[1] : $tmp[0];
                } elseif ($reason[0] === 'at_import') {
                    $detail = '';
                } elseif (in_array($reason[0], array('csv', 'pdf')) === true) {
                    $detail = $reason[0];
                    $action = $lang->get('exported_to_file');
                } else {
                    $detail = $reason[0];
                }
            } else {
                $detail = $lang->get($record['action']);
                $action = '';
            }

            array_push(
                $history,
                array(
                    'avatar' => $avatar,
                    'login' => $record['login'],
                    'name' => $record['name'] . ' ' . $record['lastname'],
                    'date' => date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['date']),
                    'action' => $action,
                    'detail' => $detail,
                )
            );
        }

        // order previous passwords by date
        $key_values = array_column($previous_passwords, 'date'); 
        array_multisort($key_values, /** @scrutinizer ignore-type */SORT_DESC, $previous_passwords);

        // send data
        // deepcode ignore ServerLeak: Data is encrypted before being sent
        echo (string) prepareExchangedData(
            [
                'error' => '',
                'history' => $history,
                'previous_passwords' => $previous_passwords,
            ],
            'encode'
        );

        break;

    case 'suggest_item_change':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => 'key_not_conform',
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        // decrypt and retrieve data in JSON format
        $data_received = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        // prepare variables
        $label = htmlspecialchars_decode($data_received['label'], ENT_QUOTES);
        $pwd = htmlspecialchars_decode($data_received['password']);
        $login = htmlspecialchars_decode($data_received['login'], ENT_QUOTES);
        $email = htmlspecialchars_decode($data_received['email']);
        $url = htmlspecialchars_decode($data_received['url']);
        $folder = htmlspecialchars_decode($data_received['folder_id']);
        $comment = htmlspecialchars_decode($data_received['comment']);
        $item_id = htmlspecialchars_decode($data_received['item_id']);

        if (empty($pwd)) {
            $cryptedStuff['encrypted'] = '';
            $cryptedStuff['objectKey'] = '';
        } else {
            $cryptedStuff = doDataEncryption($pwd);
        }

        // query
        DB::insert(
            prefixTable('items_change'),
            array(
                'item_id' => $item_id,
                'label' => $label,
                'pw' => $encrypt['string'],
                'login' => $login,
                'email' => $email,
                'url' => $url,
                'description' => '',
                'comment' => $comment,
                'folder_id' => $folder,
                'user_id' => (int) $session->get('user-id'),
                'timestamp' => time(),
            )
        );
        $newID = DB::insertId();

        // Create sharekeys for users
        storeUsersShareKey(
            prefixTable('sharekeys_items'),
            0,
            (int) $folder,
            (int) $newID,
            $cryptedStuff['objectKey'],
        );

        // get some info to add to the notification email
        $resp_user = DB::queryfirstrow(
            'SELECT login FROM ' . prefixTable('users') . ' WHERE id = %i',
            $session->get('user-id')
        );
        $resp_folder = DB::queryfirstrow(
            'SELECT title FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
            $folder
        );

        // notify Managers
        $rows = DB::query(
            'SELECT email
            FROM ' . prefixTable('users') . '
            WHERE `gestionnaire` = %i AND `email` IS NOT NULL',
            1
        );
        foreach ($rows as $record) {
            sendEmail(
                $lang->get('suggestion_notify_subject'),
                str_replace(array('#tp_label#', '#tp_user#', '#tp_folder#'), array(addslashes($label), addslashes($resp_user['login']), addslashes($resp_folder['title'])), $lang->get('suggestion_notify_body')),
                $record['email'],
                $SETTINGS
            );
        }

        echo (string) prepareExchangedData(
            array(
                'error' => '',
            ),
            'encode'
        );
        break;

    case 'build_list_of_users':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo '[ { "error" : "key_not_conform" } ]';
            break;
        }

        // Get list of users
        $usersList = array();
        $usersString = '';
        $rows = DB::query('SELECT id,login,email FROM ' . prefixTable('users') . ' ORDER BY login ASC');
        foreach ($rows as $record) {
            $usersList[$record['login']] = array(
                'id' => $record['id'],
                'login' => $record['login'],
                'email' => $record['email'],
            );
            $usersString .= $record['id'] . '#' . $record['login'] . ';';
        }

        $data = array(
            'error' => '',
            'list' => $usersString,
        );

        // send data
        echo (string) prepareExchangedData(
            $data,
            'encode'
        );
        break;

    case 'send_request_access':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => 'key_not_conform',
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        // decrypt and retrieve data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        // prepare variables
        //$post_email_body = filter_var($dataReceived['email'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $inputData['itemId'] = (int) filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT);

        // Send email
        $dataItem = DB::queryfirstrow(
            'SELECT label, id_tree
            FROM ' . prefixTable('items') . '
            WHERE id = %i',
            $inputData['itemId']
        );

        // Do log
        logItems(
            $SETTINGS,
            (int) $inputData['itemId'],
            $dataItem['label'],
            $session->get('user-id'),
            'at_access',
            $session->get('user-login')
        );

        // Return
        echo (string) prepareExchangedData(
            array(
                'error' => false,
                'message' => '',
            ),
            'encode'
        );

        break;

        /*
    * CASE
    * save_notification_status
    */
    case 'save_notification_status':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => 'key_not_conform',
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        // decrypt and retrieve data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        // prepare variables
        $post_notification_status = (int) filter_var($dataReceived['notification_status'], FILTER_SANITIZE_NUMBER_INT);
        $inputData['itemId'] = (int) filter_var($dataReceived['item_id'], FILTER_SANITIZE_NUMBER_INT);

        DB::query(
            'SELECT *
            FROM ' . prefixTable('notification') . '
            WHERE item_id = %i AND user_id = %i',
            $inputData['itemId'],
            $session->get('user-id')
        );
        if (DB::count() > 0) {
            // Notification is set for this user on this item
            if ((int) $post_notification_status === 0) {
                // Remove the notification
                DB::delete(
                    prefixTable('notification'),
                    'item_id = %i AND user_id = %i',
                    $inputData['itemId'],
                    $session->get('user-id')
                );
            }
        } else {
            // Notification is not set on this item
            if ((int) $post_notification_status === 1) {
                // Add the notification
                DB::insert(
                    prefixTable('notification'),
                    array(
                        'item_id' => $inputData['itemId'],
                        'user_id' => (int) $session->get('user-id'),
                    )
                );
            }
        }

        $data = array(
            'error' => false,
            'message' => '',
        );

        // send data
        echo (string) prepareExchangedData(
            $data,
            'encode'
        );

        break;

        /*
    * CASE
    * delete_uploaded_files_but_not_saved
    */
    case 'delete_uploaded_files_but_not_saved':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => 'key_not_conform',
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        // decrypt and retrieve data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        // prepare variables
        $inputData['itemId'] = (int) filter_var($dataReceived['item_id'], FILTER_SANITIZE_NUMBER_INT);

        // Delete non confirmed files for this item
        // And related logs
        $rows = DB::query(
            'SELECT id, file AS filename
            FROM ' . prefixTable('files') . '
            WHERE id_item = %i AND confirmed = %i',
            $inputData['itemId'],
            0
        );
        foreach ($rows as $file) {
            // Delete file in DB
            DB::delete(
                prefixTable('files'),
                'id = %i',
                $file['id']
            );

            // Delete file on server
            unlink($SETTINGS['path_to_upload_folder'] . '/' . TP_FILE_PREFIX . base64_decode($file['filename']));

            // Delete related logs
            $logFile = DB::query(
                'SELECT increment_id, raison
                FROM ' . prefixTable('log_items') . '
                WHERE id_item = %i AND id_user = %i AND action = %s AND raison LIKE "at_add_file :%"',
                $inputData['itemId'],
                $session->get('user-id'),
                'at_modification'
            );
            foreach ($logFile as $log) {
                $tmp = explode(':', $log['raison']);
                if (count($tmp) === 3 && (int) $tmp[2] === (int) $file['id']) {
                    DB::delete(
                        prefixTable('log_items'),
                        'increment_id = %i',
                        $log['increment_id']
                    );
                }
            }
        }

        $data = array(
            'error' => false,
            'message' => '',
        );

        // send data
        echo (string) prepareExchangedData(
            $data,
            'encode'
        );

        break;

        /*
    * CASE
    * confirm_attachments
    */
    case 'confirm_attachments':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => 'key_not_conform',
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }
        // decrypt and retrieve data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );

        // prepare variables
        $inputData['itemId'] = (int) filter_var($dataReceived['item_id'], FILTER_SANITIZE_NUMBER_INT);

        // Confirm attachments
        $rows = DB::query(
            'SELECT id, file AS filename
            FROM ' . prefixTable('files') . '
            WHERE id_item = %i AND confirmed = %i',
            $inputData['itemId'],
            0
        );
        foreach ($rows as $file) {
            DB::update(
                prefixTable('files'),
                array(
                    'confirmed' => 1,
                ),
                'id_item = %i',
                $inputData['itemId']
            );
        }

        $data = array(
            'error' => false,
            'message' => '',
        );

        // send data
        echo (string) prepareExchangedData(
            $data,
            'encode'
        );

        break;

    /*
    * CASE
    * check_current_access_rights
    */
    case 'check_current_access_rights':
        // Check KEY
        if ($inputData['key'] !== $session->get('key')) {
            echo (string) prepareExchangedData(
                array(
                    'error' => 'key_not_conform',
                    'message' => $lang->get('key_is_not_correct'),
                ),
                'encode'
            );
            break;
        }

        // Init
        $editionLock = false;

        // decrypt and retrieve data in JSON format
        $dataReceived = prepareExchangedData(
            $inputData['data'],
            'decode'
        );
        
        // prepare variables
        $inputData['userId'] = (int) filter_var($dataReceived['userId'], FILTER_SANITIZE_NUMBER_INT);
        $inputData['itemId'] = (int) filter_var($dataReceived['itemId'], FILTER_SANITIZE_NUMBER_INT);
        $inputData['treeId'] = (int) filter_var($dataReceived['treeId'], FILTER_SANITIZE_NUMBER_INT);
        
        // Locked Item (if already locked), go back and warn
        $dataTmp = DB::queryFirstRow(
            'SELECT timestamp, user_id 
            FROM ' . prefixTable('items_edition') . ' 
            WHERE item_id = %i',
            $inputData['itemId']
        );

        // if token already exists for this item then no edition is possible
        if (DB::count() > 0) {
            // Get if current user is the one who locked the item
            $userLockedItemQueryResults = DB::queryFirstRow(
                'SELECT user_id 
                FROM ' . prefixTable('items_edition') . ' 
                WHERE item_id = %i AND user_id = %i',
                $inputData['itemId'],
                $session->get('user-id')
            );
            $userLockedItem = (DB::count() > 0) ? true : false;

            // Get if existing process ongoing for this item
            $dataItemProcessOngoing = DB::queryFirstRow(
                'SELECT JSON_EXTRACT(p.arguments, "$.all_users_except_id") AS all_users_except_id
                FROM ' . prefixTable('background_tasks') . ' AS p
                INNER JOIN ' . prefixTable('items_edition') . ' AS i ON (i.item_id = p.item_id)
                WHERE p.item_id = %i AND p.finished_at = ""
                ORDER BY p.increment_id DESC',
                $inputData['itemId']
            );

            // Get delay period
            if (isset($SETTINGS['delay_item_edition']) && $SETTINGS['delay_item_edition'] > 0 && empty($dataTmp['timestamp']) === false) {
                $delay = $SETTINGS['delay_item_edition']*60;
            } else {
                $delay = EDITION_LOCK_PERIOD; // One day delay
            }
            
            if ((int) DB::count() === 0) {
                // CASE where no encryption process is pending
                if ($session->get('user-id') === $dataTmp['user_id'] || $userLockedItem === true || round(abs(time() - $dataTmp['timestamp']), 0) > $delay) {
                    // CASE where user is the one who locked the item
                    // Delete the existing edition lock and let the user edit
                    DB::delete(
                        prefixTable('items_edition'), 
                        'item_id = %i AND user_id = %i', 
                        $inputData['itemId'],
                        $session->get('user-id')
                    );
                } else {
                    // CASE where user is not the one who locked the item
                    $editionLock = true;
                }
            } else {
                // Case where encryption process is pending
                // Block any user to edit the item
                $editionLock = true;
            }
        }

        $data = DB::queryFirstRow(
            'SELECT visible_folders
            FROM ' . prefixTable('cache_tree') . ' WHERE user_id = %i',
            $inputData['userId']
        );
        // Check if tree ID is in visible folders.
        $arr = json_decode($data['visible_folders'], true);
        $ids = is_null($arr) === true ? [] : array_column($arr, 'id');

        // Is folder in Read Only list for this user?
        if (in_array($inputData['treeId'], $session->get('user-read_only_folders')) === true) {
            $data = array(
                'error' => false,
                'access' => true,
                'edit' => false,
                'delete' => false,
                'edition_locked' => false,
            );

            // send data
            echo (string) prepareExchangedData(
                $data,
                'encode'
            );
            break;
        }


        // Check rights of this role on this folder
        // Is there no edit or no delete defined
        $edit = $delete = null;
        $data = DB::queryFirstColumn(
            'SELECT type
            FROM ' . prefixTable('roles_values') . '
            WHERE role_id IN %ls AND folder_id = %i',
            array_column($session->get('system-array_roles'), 'id'),
            $inputData['treeId'],
        );       
        foreach ($data as $access) {
            if ($access === 'ND') {
                $delete = $delete === true ? true : false;
            } elseif ($access === 'NE') {
                $edit = $edit === true ? true : false;
            } elseif ($access === 'NDNE') {
                $edit = $edit === true ? true : false;
                $delete = $delete === true ? true : false;
            } elseif ($access === 'R') {
                $edit = $edit === true ? true : false;
                $delete = $delete === true ? true : false;
            } elseif ($access === 'W') {
                $edit = true;
            }
        }
        if (LOG_TO_SERVER === true) error_log('TEAMPASS - Folder: '.$inputData['treeId'].' - User: '.$inputData['userId'].' - access: ' . $access . ' - edit: ' . $edit . ' - delete: ' . $delete);

        $data = array(
            'error' => false,
            'access' => isset($inputData['treeId']) === true && in_array($inputData['treeId'], $ids) === true ? true : false,
            'edit' => $edit,
            'delete' => $delete,
            'edition_locked' => $editionLock,
            'debug' => 'read_only_folders',
        );

        // send data
        echo (string) prepareExchangedData(
            $data,
            'encode'
        );

        break;
}

// Build the QUERY in case of GET
if (isset($inputData['getType'])) {
    switch ($inputData['getType']) {
        /*
        * CASE
        * Autocomplet for TAGS
        */
        case 'autocomplete_tags':
            // Get a list off all existing TAGS
            $listOfTags = '';
            $rows = DB::query('SELECT tag FROM ' . prefixTable('tags') . ' WHERE tag LIKE %ss GROUP BY tag', $inputData['getTerm']);
            foreach ($rows as $record) {
                if (empty($listOfTags)) {
                    $listOfTags = '"' . $record['tag'] . '"';
                } else {
                    $listOfTags .= ', "' . $record['tag'] . '"';
                }
            }
            echo '[' . $listOfTags . ']';
            break;
    }
}

/**
 * Identify if this group authorize creation of item without the complexit level reached
 *
 * @param int $groupe ID for group
 *
 * @return array list of roles
*/
function recupDroitCreationSansComplexite($groupe)
{
    $data = DB::queryFirstRow(
        'SELECT bloquer_creation, bloquer_modification, personal_folder
        FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
        $groupe
    );
    // Check if it's in a personal folder. If yes, then force complexity overhead.
    if ($data !== null && (int) $data['personal_folder'] === 1) {
        return array(
            'bloquer_modification_complexite' => 1,
            'bloquer_creation_complexite' => 1,
        );
    }

    return array(
        'bloquer_modification_complexite' => $data !== null ? (int) $data['bloquer_modification'] : 0,
        'bloquer_creation_complexite' => $data !== null ? (int) $data['bloquer_creation'] : 0,
    );
}

/**
 * Permits to identify what icon to display depending on file extension.
 *
 * @param string $ext extension
 *
 * @return string
 */
function fileFormatImage($ext)
{
    if (in_array($ext, TP_OFFICE_FILE_EXT)) {
        $image = 'fas fa-file-word';
    } elseif ($ext === 'pdf') {
        $image = 'fas fa-file-pdf';
    } elseif (in_array($ext, TP_IMAGE_FILE_EXT)) {
        $image = 'fas fa-file-image';
    } elseif ($ext === 'txt') {
        $image = 'fas fa-file-alt';
    } else {
        $image = 'fas fa-file';
    }

    return $image;
}

/**
 * Returns a cleaned up password.
 *
 * @param string $pwd String for pwd
 *
 * @return string
 */
function passwordReplacement($pwd)
{
    $pwPatterns = array('/ETCOMMERCIAL/', '/SIGNEPLUS/');
    $pwRemplacements = array('&', '+');

    return preg_replace($pwPatterns, $pwRemplacements, $pwd);
}

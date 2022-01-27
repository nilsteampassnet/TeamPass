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
 * @file      fields.queries.php
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
if (isset($_SESSION['CPM']) === false
    || $_SESSION['CPM'] != 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id'])
    || isset($_SESSION['key']) === false || empty($_SESSION['key'])
) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
if (isset($SETTINGS['cpassman_dir']) === false || empty($SETTINGS['cpassman_dir'])) {
    if (file_exists('../includes/config/tp.config.php')) {
        include_once '../includes/config/tp.config.php';
    } elseif (file_exists('./includes/config/tp.config.php')) {
        include_once './includes/config/tp.config.php';
    } elseif (file_exists('../../includes/config/tp.config.php')) {
        include_once '../../includes/config/tp.config.php';
    } else {
        throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    }
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], 'options', $SETTINGS)) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/tp.config.php';

header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

// connect to the server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
//$post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

if (null !== $post_type) {
    switch ($post_type) {
        // LOADING THE TABLE
        case 'loadFieldsList':
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
            } elseif ($_SESSION['is_admin'] === false) {
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

            //$categoriesSelect = '';
            $arrCategories = $arrFields = array();
            $rows = DB::query(
                'SELECT *
                FROM '.prefixTable('categories').'
                WHERE level = %i
                ORDER BY '.prefixTable('categories').'.order ASC',
                0
            );
            foreach ($rows as $record) {
                // get associated folders
                //$foldersList = '';
                //$foldersNumList = '';
                $arrayFolders = array();
                $arrayRoles = array();

                $rowsF = DB::query(
                    'SELECT t.title AS title, c.id_folder as id_folder
                    FROM '.prefixTable('categories_folders').' AS c
                    INNER JOIN '.prefixTable('nested_tree').' AS t ON (c.id_folder = t.id)
                    WHERE c.id_category = %i',
                    $record['id']
                );
                foreach ($rowsF as $recordF) {
                    array_push(
                        $arrayFolders,
                        array(
                            'title' => $recordF['title'],
                            'id' => $recordF['id_folder'],
                        )
                    );
                    /*
                    if (empty($foldersList)) {
                        $foldersList = $recordF['title'];
                        $foldersNumList = $recordF['id_folder'];
                    } else {
                        $foldersList .= ' | '.$recordF['title'];
                        $foldersNumList .= ';'.$recordF['id_folder'];
                    }
                    */
                }

                // store
                array_push(
                    $arrCategories,
                    array(
                        'category' => true,
                        'id' => (int) $record['id'],
                        'title' => $record['title'],
                        'order' => (int) $record['order'],
                        'folders' => $arrayFolders,
                        //$foldersNumList,
                    )
                );
                $rows = DB::query(
                    'SELECT *
                    FROM '.prefixTable('categories').'
                    WHERE parent_id = %i
                    ORDER BY '.prefixTable('categories').'.order ASC',
                    $record['id']
                );
                if (count($rows) > 0) {
                    foreach ($rows as $field) {
                        $arrayRoles = array();
                        // Get lsit of Roles
                        if ($field['role_visibility'] === 'all') {
                            array_push(
                                $arrayRoles,
                                array(
                                    'id' => 'all',
                                    'title' => langHdl('every_roles'),
                                )
                            );
                        } else {
                            //echo $field['role_visibility'];
                            foreach (explode(',', $field['role_visibility']) as $role) {
                                if (empty($role) === false && $role !== null) {
                                    $data = DB::queryFirstRow(
                                        'SELECT title
                                        FROM '.prefixTable('roles_title').'
                                        WHERE id = %i',
                                        $role
                                    );
                                    array_push(
                                        $arrayRoles,
                                        array(
                                            'id' => $role,
                                            'title' => $data['title'],
                                        )
                                    );
                                }
                            }
                        }
                        // Store for exchange
                        array_push(
                            $arrCategories,
                            array(
                                'category' => false,
                                'id' => (int) $field['id'],
                                'title' => $field['title'],
                                'order' => (int) $field['order'],
                                'encrypted' => (int) $field['encrypted_data'],
                                'type' => $field['type'],
                                'masked' => (int) $field['masked'],
                                'roles' => $arrayRoles,
                                //'role' => $field['role_visibility'],
                                'mandatory' => (int) $field['is_mandatory'],
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
                    'array' => $arrCategories,
                ),
                'encode'
            );
            break;

        // LOADING THE TABLE
        case 'add_new_category':
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
            } elseif ($_SESSION['is_admin'] === false) {
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

            // Decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],$post_data, 'decode');

            // Prepare variables
            $post_label = filter_var($dataReceived['label'], FILTER_SANITIZE_STRING);
            $post_position = filter_var($dataReceived['position'], FILTER_SANITIZE_STRING);
            $post_folders = filter_var_array($dataReceived['folders'], FILTER_SANITIZE_STRING);

            // Store in DB
            DB::insert(
                prefixTable('categories'),
                [
                    'parent_id' => 0,
                    'title' => $post_label,
                    'level' => 0,
                    'order' => 1,
                ]
            );
            $newCategoryId = DB::insertId();

            // Order the new item
            DB::update(
                prefixTable('categories'),
                [
                    'order' => calculateOrder($newCategoryId, $post_position),
                ],
                'id = %i',
                $newCategoryId
            );

            // Store the folders
            foreach ($post_folders as $folder) {
                //add CF Category to this subfolder
                DB::insert(
                    prefixTable('categories_folders'),
                    [
                        'id_category' => $newCategoryId,
                        'id_folder' => $folder,
                    ]
                );
            }

            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                [
                    'error' => false,
                    'message' => '',
                ],
                'encode'
            );
            break;

        // LOADING THE TABLE
        case 'edit_category':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    [
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ],
                    'encode'
                );
                break;
            } elseif ($_SESSION['is_admin'] === false) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    [
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ],
                    'encode'
                );
                break;
            }

            // Decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],$post_data, 'decode');

            // Prepare variables
            $post_label = filter_var($dataReceived['label'], FILTER_SANITIZE_STRING);
            $post_position = filter_var($dataReceived['position'], FILTER_SANITIZE_STRING);
            $post_folders = filter_var_array($dataReceived['folders'], FILTER_SANITIZE_STRING);
            $post_categoryId = filter_var($dataReceived['categoryId'], FILTER_SANITIZE_NUMBER_INT);

            // Update category
            DB::update(
                prefixTable('categories'),
                [
                    'title' => $post_label,
                    'order' => calculateOrder($post_categoryId, $post_position),
                ],
                'id = %i',
                $post_categoryId
            );

            // Delete all folders
            DB::delete(
                prefixTable('categories_folders'),
                'id_category = %i',
                $post_categoryId
            );

            // Store the folders
            foreach ($post_folders as $folder) {
                //add Category to this subfolder
                DB::insert(
                    prefixTable('categories_folders'),
                    [
                        'id_category' => $post_categoryId,
                        'id_folder' => $folder,
                    ]
                );
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

        // LOADING THE TABLE
        case 'delete':
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
            } elseif ($_SESSION['is_admin'] === false) {
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

            // Decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],$post_data, 'decode');

            // Prepare variables
            $post_idToRemove = filter_var($dataReceived['idToRemove'], FILTER_SANITIZE_NUMBER_INT);
            $post_action = filter_var($dataReceived['action'], FILTER_SANITIZE_STRING);

            if ($post_action === 'category') {
                // DELETING A CATEGORY
                // Delete ID
                DB::delete(
                    prefixTable('categories'),
                    'id = %i',
                    $post_idToRemove
                );

                // Remove data from fields
                $rows = DB::query(
                    'SELECT id
                    FROM '.prefixTable('categories').'
                    WHERE parent_id = %i',
                    $post_idToRemove
                );
                foreach ($rows as $record) {
                    DB::delete(
                        prefixTable('categories_items'),
                        'field_id = %i',
                        $record['id']
                    );
                }

                // Remove all fields of this category
                DB::delete(
                    prefixTable('categories'),
                    'parent_id = %i',
                    $post_idToRemove
                );

                // Remove all folders belonging to this category
                DB::delete(
                    prefixTable('categories_folders'),
                    'id_category = %i',
                    $post_idToRemove
                );
            } else {
                // DELETING A FIELD
                // Delete ID
                DB::delete(
                    prefixTable('categories'),
                    'id = %i',
                    $post_idToRemove
                );

                // Delete all data
                DB::delete(
                    prefixTable('categories_items'),
                    'field_id = %i',
                    $post_idToRemove
                );
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

        // EDIT FIELD
        case 'edit_field':
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
            } elseif ($_SESSION['is_admin'] === false) {
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

            // Decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],$post_data, 'decode');

            // Prepare variables
            $post_label = filter_var($dataReceived['label'], FILTER_SANITIZE_STRING);
            $post_order = filter_var($dataReceived['order'], FILTER_SANITIZE_STRING);
            $post_categoryId = filter_var($dataReceived['categoryId'], FILTER_SANITIZE_NUMBER_INT);
            $post_type = filter_var($dataReceived['type'], FILTER_SANITIZE_STRING);
            $post_mandatory = filter_var($dataReceived['mandatory'], FILTER_SANITIZE_STRING);
            $post_masked = filter_var($dataReceived['masked'], FILTER_SANITIZE_STRING);
            $post_encrypted = filter_var($dataReceived['encrypted'], FILTER_SANITIZE_STRING);
            $post_roles = filter_var_array($dataReceived['roles'], FILTER_SANITIZE_STRING);
            $post_fieldId = isset($dataReceived['fieldId']) === false ? '' :
                filter_var($dataReceived['fieldId'], FILTER_SANITIZE_NUMBER_INT);

            if (empty($post_fieldId) === false) {
                // UPDATE FIELD

                // Perform update
                DB::update(
                    prefixTable('categories'),
                    array(
                        'title' => $post_label,
                        'parent_id' => $post_categoryId,
                        'type' => $post_type,
                        'encrypted_data' => $post_encrypted,
                        'is_mandatory' => $post_mandatory,
                        'masked' => $post_masked,
                        'role_visibility' => is_null($post_roles) === true || count($post_roles) ===0 ? '' : implode(',', $post_roles),
                        'order' => calculateOrder($post_fieldId, $post_order),
                    ),
                    'id = %i',
                    $post_fieldId
                );

                // encrypt/decrypt existing data
                $rowsF = DB::query(
                    'SELECT i.id, i.data, i.data_iv, i.encryption_type
                    FROM '.$pre.'categories_items AS i
                    INNER JOIN '.prefixTable('categories').' AS c ON (i.field_id = c.id)
                    WHERE c.id = %i',
                    $post_fieldId
                );
                foreach ($rowsF as $recordF) {
                    $encryption_type = '';
                    // decrypt/encrypt
                    if ($post_encrypted === '0' && $recordF['encryption_type'] === 'defuse') {
                        $encrypt = cryption(
                            $recordF['data'],
                            '',
                            'decrypt',
                            $SETTINGS
                        );
                        $encryption_type = 'none';
                    } elseif ($recordF['encryption_type'] === 'none' || $recordF['encryption_type'] === '') {
                        $encrypt = cryption(
                            $recordF['data'],
                            '',
                            'encrypt',
                            $SETTINGS
                        );
                        $encryption_type = 'defuse';
                    }

                    // store in DB
                    if ($encryption_type !== '') {
                        DB::update(
                            prefixTable('categories_items'),
                            [
                                'data' => $encrypt['string'],
                                'data_iv' => '',
                                'encryption_type' => $encryption_type,
                            ],
                            'id = %i',
                            $recordF['id']
                        );
                    }
                }
            } else {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                    [
                        'error' => true,
                        'message' => langHdl('error_could_not_update_the_field'),
                    ],
                    'encode'
                );
                break;
            }

            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                [
                    'error' => false,
                    'message' => '',
                ],
                'encode'
            );
            break;

        // ADD NEW FIELD
        case 'add_new_field':
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
            } elseif ($_SESSION['is_admin'] === false) {
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

            // Decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],$post_data, 'decode');

            // Prepare variables
            $post_label = filter_var($dataReceived['label'], FILTER_SANITIZE_STRING);
            $post_order = filter_var($dataReceived['order'], FILTER_SANITIZE_STRING);
            $post_categoryId = filter_var($dataReceived['categoryId'], FILTER_SANITIZE_NUMBER_INT);
            $post_type = filter_var($dataReceived['type'], FILTER_SANITIZE_STRING);
            $post_mandatory = filter_var($dataReceived['mandatory'], FILTER_SANITIZE_STRING);
            $post_masked = filter_var($dataReceived['masked'], FILTER_SANITIZE_STRING);
            $post_encrypted = filter_var($dataReceived['encrypted'], FILTER_SANITIZE_STRING);
            $post_roles = filter_var_array($dataReceived['roles'], FILTER_SANITIZE_STRING);

            // NEW FIELD
            DB::insert(
                prefixTable('categories'),
                array(
                    'parent_id' => $post_categoryId,
                    'title' => $post_label,
                    'type' => $post_type,
                    'masked' => $post_masked,
                    'encrypted_data' => $post_encrypted,
                    'is_mandatory' => $post_mandatory,
                    'role_visibility' => implode(',', $post_roles),
                    'level' => 1,
                    'order' => 1,
                )
            );
            $newFieldId = DB::insertId();

            // Order the new item
            DB::update(
                prefixTable('categories'),
                [
                    'order' => calculateOrder($newFieldId, $post_order),
                ],
                'id = %i',
                $newFieldId
            );

            echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                [
                    'error' => false,
                    'message' => '',
                ],
                'encode'
            );
            break;
    }
}

function calculateOrder($id, $position)
{
    if ($position === 'top') {
        // Set this new category to the top
        $orderNewCategory = 1;
        $newOrder = 2;
    } elseif ($position === 'bottom') {
        // Set this new category to the bottom

        // Get number of categories
        DB::query(
            'SELECT id
            FROM '.prefixTable('categories').'
            WHERE level = %i',
            0
        );

        return DB::count() + 1;
    } else {
        // Get position of selected folder
        $data = DB::queryFirstRow(
            'SELECT c.order AS position
            FROM '.prefixTable('categories').' AS c
            WHERE id = %i',
            (int) $position
        );

        if (DB::count() > 0) {
            $orderNewCategory = (int) $data['position'] - 1;

            // Manage case of top
            if ((int) $orderNewCategory === 0) {
                $orderNewCategory = 1;
                $newOrder = 2;
            } else {
                $newOrder = 1;
            }
        } else {
            $orderNewCategory = 1;
            $newOrder = 1;
        }
    }

    // Update all orders
    $rows = DB::query(
        'SELECT id, c.order AS position
        FROM '.prefixTable('categories').' AS c
        WHERE level = %i
        ORDER BY c.order ASC, c.title ASC',
        0
    );
    foreach ($rows as $record) {
        if ($record['id'] !== $id) {
            DB::update(
                prefixTable('categories'),
                [
                    'order' => $newOrder,
                ],
                'id = %i',
                $record['id']
            );
        }
        ++$newOrder;
    }

    // update for the new item
    return (int) $orderNewCategory;
}

/*
if (null !== $post_type) {
    switch ($post_type) {
        case "addNewCategory":
            // store key
            DB::insert(
                prefixTable("categories"),
                array(
                    'parent_id' => 0,
                    'title' => $post_title,
                    'level' => 0,
                    'order' => 1
                )
            );
            echo '[{"error" : "", "id" : "'.DB::insertId().'"}]';
            break;

        case "deleteCategory":
            DB::delete(prefixTable("categories"), "id = %i", $post_id);
            DB::delete(prefixTable("categories_folders"), "id_category = %i", $post_id);
            echo '[{"error" : ""}]';
            break;

        case "addNewField":
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                $post_data,
                "decode"
            );

            $post_title = filter_var($dataReceived['title'], FILTER_SANITIZE_STRING);

            // store key
            if (empty($post_title) === false) {
                DB::insert(
                    prefixTable("categories"),
                    array(
                        'parent_id' => filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT),
                        'title' => filter_var($dataReceived['title'], FILTER_SANITIZE_STRING),
                        'type' => filter_var($dataReceived['type'], FILTER_SANITIZE_STRING),
                        'masked' => filter_var($dataReceived['masked'], FILTER_SANITIZE_STRING),
                        'encrypted_data' => filter_var($dataReceived['encrypted'], FILTER_SANITIZE_STRING),
                        'is_mandatory' => filter_var($dataReceived['is_mandatory'], FILTER_SANITIZE_STRING),
                        'role_visibility' => filter_var($dataReceived['field_visibility'], FILTER_SANITIZE_STRING),
                        'level' => 1,
                        'order' => filter_var($dataReceived['order'], FILTER_SANITIZE_NUMBER_INT)
                    )
                );
                echo '[{"error" : "", "id" : "'.DB::insertId().'"}]';
            }
            break;

        case "saveOrder":
            // update order
            if (empty($post_type) === false) {
                foreach (explode(';', $post_data) as $data) {
                    $elem = explode(':', $data);
                    DB::update(
                        prefixTable("categories"),
                        array(
                            'order' => $elem[1]
                            ),
                        "id=%i",
                        $elem[0]
                    );
                }
                echo '[{"error" : ""}]';
            }
            break;

        case "update_category_and_field":
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
    $SETTINGS['cpassman_dir'],
                $post_data,
                "decode"
            );

            if (filter_var(($dataReceived['field_is_category']), FILTER_SANITIZE_NUMBER_INT) === 1) {
                $array = array(
                    'title' => filter_var($dataReceived['title'], FILTER_SANITIZE_STRING)
                );
            } else {
                $array = array(
                    'title' => filter_var($dataReceived['title'], FILTER_SANITIZE_STRING),
                    'parent_id' => filter_var($dataReceived['category'], FILTER_SANITIZE_NUMBER_INT),
                    'type' => filter_var($dataReceived['type'], FILTER_SANITIZE_STRING),
                    'encrypted_data' => filter_var($dataReceived['encrypted'], FILTER_SANITIZE_STRING),
                    'is_mandatory' => filter_var($dataReceived['is_mandatory'], FILTER_SANITIZE_STRING),
                    'masked' => filter_var($dataReceived['masked'], FILTER_SANITIZE_STRING),
                    'role_visibility' => filter_var($dataReceived['roles'], FILTER_SANITIZE_STRING),
                    'order' => filter_var($dataReceived['order'], FILTER_SANITIZE_NUMBER_INT)
                );
            }

            // Perform update
            DB::update(
                prefixTable("categories"),
                $array,
                "id=%i",
                filter_var(($dataReceived['id']), FILTER_SANITIZE_NUMBER_INT)
            );
            echo '[{"error" : ""}]';

            break;

        case "loadFieldsList":
            $categoriesSelect = "";
            $arrCategories = $arrFields = array();
            $rows = DB::query(
                "SELECT *
                FROM ".$pre."categories
                WHERE level = %i
                ORDER BY ".$pre."categories.order ASC",
                0
            );
            foreach ($rows as $record) {
                // get associated folders
                $foldersList = $foldersNumList = "";
                $rowsF = DB::query(
                    "SELECT t.title AS title, c.id_folder as id_folder
                    FROM ".$pre."categories_folders AS c
                    INNER JOIN ".prefixTable("nested_tree")." AS t ON (c.id_folder = t.id)
                    WHERE c.id_category = %i",
                    $record['id']
                );
                foreach ($rowsF as $recordF) {
                    if (empty($foldersList)) {
                        $foldersList = $recordF['title'];
                        $foldersNumList = $recordF['id_folder'];
                    } else {
                        $foldersList .= " | ".$recordF['title'];
                        $foldersNumList .= ";".$recordF['id_folder'];
                    }
                }

                // store
                array_push(
                    $arrCategories,
                    array(
                        '1',
                        $record['id'],
                        $record['title'],
                        $record['order'],
                        $foldersList,
                        $foldersNumList
                    )
                );
                $rows = DB::query(
                    "SELECT *
                    FROM ".prefixTable("categories")."
                    WHERE parent_id = %i
                    ORDER BY ".$pre."categories.order ASC",
                    $record['id']
                );
                if (count($rows) > 0) {
                    foreach ($rows as $field) {
                        // Get lsit of Roles
                        if ($field['role_visibility'] === 'all') {
                            $roleVisibility = $LANG['every_roles'];
                        } else {
                            $roleVisibility = '';
                            foreach (explode(',', $field['role_visibility']) as $role) {
                                $data = DB::queryFirstRow(
                                    "SELECT title
                                    FROM ".$pre."roles_title
                                    WHERE id = %i",
                                    $role
                                );
                                if (empty($roleVisibility) === true) {
                                    $roleVisibility = $data['title'];
                                } else {
                                    $roleVisibility .= ', '.$data['title'];
                                }
                            }
                        }
                        // Store for exchange
                        array_push(
                            $arrCategories,
                            array(
                                '2',
                                $field['id'],
                                $field['title'],
                                $field['order'],
                                $field['encrypted_data'],
                                "",
                                $field['type'],
                                $field['masked'],
                                addslashes($roleVisibility),
                                $field['role_visibility'],
                                $field['is_mandatory']
                            )
                        );
                    }
                }
            }
            echo json_encode($arrCategories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            break;

        case "categoryInFolders":
            // Prepare POST variables
            $post_foldersIds = filter_input(INPUT_POST, 'foldersIds', FILTER_SANITIZE_STRING);
            $post_id = $post_id;

            // update order
            if (empty($post_foldersIds) === false) {
                // delete all existing inputs
                DB::delete(
                    $pre."categories_folders",
                    "id_category = %i",
                    $post_id
                );
                // create new list
                $list = "";
                foreach (explode(';', $post_foldersIds) as $folder) {
                    DB::insert(
                        prefixTable("categories_folders"),
                        array(
                            'id_category' => $post_id,
                            'id_folder' => $folder
                            )
                    );

                    // prepare a list
                    $row = DB::queryfirstrow("SELECT title FROM ".prefixTable("nested_tree")." WHERE id=%i", $folder);
                    if (empty($list)) {
                        $list = $row['title'];
                    } else {
                        $list .= " | ".$row['title'];
                    }
                }
                echo '[{"list" : "'.$list.'"}]';
            }
            break;

        case "dataIsEncryptedInDB":
            // Prepare POST variables
            $post_encrypt = filter_input(INPUT_POST, 'encrypt', FILTER_SANITIZE_STRING);

            // store key
            DB::update(
                prefixTable("categories"),
                array(
                    'encrypted_data' => $post_encrypt
                    ),
                "id = %i",
                $post_id
            );

            // encrypt/decrypt existing data
            $rowsF = DB::query(
                "SELECT i.id, i.data, i.data_iv, i.encryption_type
                FROM ".$pre."categories_items AS i
                INNER JOIN ".prefixTable("categories")." AS c ON (i.field_id = c.id)
                WHERE c.id = %i",
                $post_id
            );
            foreach ($rowsF as $recordF) {
                $encryption_type = "";
                // decrypt/encrypt
                if ($post_encrypt === "0" && $recordF['encryption_type'] === "defuse") {
                    $encrypt = cryption(
                        $recordF['data'],
                        "",
                        "decrypt"
                    );
                    $encryption_type = "none";
                } elseif ($recordF['encryption_type'] === "none" || $recordF['encryption_type'] === "") {
                    $encrypt = cryption(
                        $recordF['data'],
                        "",
                        "encrypt"
                    );
                    $encryption_type = "defuse";
                }

                // store in DB
                if ($encryption_type !== "") {
                    DB::update(
                        prefixTable("categories_items"),
                        array(
                            'data' => $encrypt['string'],
                            'data_iv' => "",
                            'encryption_type' => $encryption_type
                            ),
                        "id = %i",
                        $recordF['id']
                    );
                }
            }

            echo '[{"error" : ""}]';
            break;

        case "refreshCategoriesHTML":
            //Build tree of Categories
            $categoriesSelect = "";
            $arrCategories = array();
            $rows = DB::query(
                "SELECT * FROM ".prefixTable("categories")."
                WHERE level = %i
                ORDER BY ".$pre."categories.order ASC",
                '0'
            );
            foreach ($rows as $record) {
                array_push(
                    $arrCategories,
                    array(
                        $record['id'],
                        $record['title'],
                        $record['order']
                    )
                );
            }
            $arrReturn = array(
                'html' => '',
                'no_category' => false
            );
            $html = '';

            if (isset($arrCategories) && count($arrCategories) > 0) {
                // build table
                foreach ($arrCategories as $category) {
                    // get associated Folders
                    $foldersList = $foldersNumList = "";
                    $rows = DB::query(
                        "SELECT t.title AS title, c.id_folder as id_folder
                        FROM ".prefixTable("categories_folders")." AS c
                        INNER JOIN ".prefixTable("nested_tree")." AS t ON (c.id_folder = t.id)
                        WHERE c.id_category = %i",
                        $category[0]
                    );
                    foreach ($rows as $record) {
                        if (empty($foldersList)) {
                            $foldersList = $record['title'];
                            $foldersNumList = $record['id_folder'];
                        } else {
                            $foldersList .= " | ".$record['title'];
                            $foldersNumList .= ";".$record['id_folder'];
                        }
                    }
                    // display each cat and fields
                    $html .= '
<tr id="t_cat_'.$category[0].'">
    <td colspan="2">
        <input type="text" id="catOrd_'.$category[0].'" size="1" class="category_order" value="'.$category[2].'" />&nbsp;
        <span class="fa-stack tip" title="'.$LANG['field_add_in_category'].'" onclick="fieldAdd('.$category[0].')" style="cursor:pointer;">
            <i class="fa fa-square fa-stack-2x"></i>
            <i class="fa fa-plus fa-stack-1x fa-inverse"></i>
        </span>
        &nbsp;
        <input type="radio" name="sel_item" id="item_'.$category[0].'_cat" />
        <label for="item_'.$category[0].'_cat" id="item_'.$category[0].'" style="font-weight:bold;">'.$category[1].'</label>
    </td>
    <td>
        <span class="fa-stack tip" title="'.$LANG['category_in_folders'].'" onclick="catInFolders('.$category[0].')" style="cursor:pointer;">
            <i class="fa fa-square fa-stack-2x"></i>
            <i class="fa fa-edit fa-stack-1x fa-inverse"></i>
        </span>
        &nbsp;
        '.$LANG['category_in_folders_title'].':
        <span style="font-family:italic; margin-left:10px;" id="catFolders_'.$category[0].'">'.$foldersList.'</span>
        <input type="hidden" id="catFoldersList_'.$category[0].'" value="'.$foldersNumList.'" />
    </td>
</tr>';
                    $rows = DB::query(
                        "SELECT * FROM ".prefixTable("categories")."
                        WHERE parent_id = %i
                        ORDER BY ".$pre."categories.order ASC",
                        $category[0]
                    );
                    $counter = DB::count();
                    if ($counter > 0) {
                        foreach ($rows as $field) {
                            $html .= '
<tr id="t_field_'.$field['id'].'">
    <td width="60px"></td>
    <td colspan="2">
        <input type="text" id="catOrd_'.$field['id'].'" size="1" class="category_order" value="'.$field['order'].'" />&nbsp;
        <input type="radio" name="sel_item" id="item_'.$field['id'].'_cat" />
        <label for="item_'.$field['id'].'_cat" id="item_'.$field['id'].'">'.($field['title']).'</label>
        <span id="encryt_data_'.$field['id'].'" style="margin-left:4px; cursor:pointer;">'.(isset($field['encrypted_data']) && $field['encrypted_data'] === "1") ? '<i class="fa fa-key tip" title="'.$LANG['encrypted_data'].'" onclick="changeEncrypMode(\''.$field['id'].'\', \'1\')"></i>' : '<span class="fa-stack" title="'.$LANG['not_encrypted_data'].'" onclick="changeEncrypMode(\''.$field['id'].'\', \'0\')"><i class="fa fa-key fa-stack-1x"></i><i class="fa fa-ban fa-stack-1x fa-lg" style="color:red;"></i></span>'.'
        </span>';
                            if (isset($field['type'])) {
                                if ($field['type'] === "text") {
                                    $html .= '
        <span style="margin-left:4px;"><i class="fa fa-paragraph tip" title="'.$LANG['data_is_text'].'"></i></span>';
                                } elseif ($field['type'] === "masked") {
                                    $html .= '
        <span style="margin-left:4px;"><i class="fa fa-eye-slash tip" title="'.$LANG['data_is_masked'].'"></i></span>';
                                }
                            }
                            $html .= '
    </td>
    <td></td>
</tr>';
                        }
                    }
                }
            } else {
                $arrReturn['no_category'] === true;
                $html = addslashes($LANG['no_category_defined']);
            }

            $arrReturn['html'] === $html;

            echo json_encode($arrReturn, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            break;
    }
}
*/

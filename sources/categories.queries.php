<?php
/**
 * @file          categories.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2018 Nils Laumaillé
 * @licensing     GNU GPL-3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once 'SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key'])
) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    require_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    require_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_settings")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

include $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset==utf-8");
require_once 'main.functions.php';
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

//Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$pass = defuse_return_decrypted($pass);
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = true;
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// Prepare POST variables
$post_title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
$post_field_title = filter_input(INPUT_POST, 'field_title', FILTER_SANITIZE_STRING);
$post_field_type = filter_input(INPUT_POST, 'field_type', FILTER_SANITIZE_STRING);
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

if (null !== $post_type) {
    switch ($post_type) {
        case "addNewCategory":
            // store key
            DB::insert(
                prefix_table("categories"),
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
            DB::delete(prefix_table("categories"), "id = %i", $post_id);
            DB::delete(prefix_table("categories_folders"), "id_category = %i", $post_id);
            echo '[{"error" : ""}]';
            break;

        case "addNewField":
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                "decode"
            );

            $post_title = filter_var($dataReceived['title'], FILTER_SANITIZE_STRING);

            // store key
            if (empty($post_title) === false) {
                DB::insert(
                    prefix_table("categories"),
                    array(
                        'parent_id' => filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT),
                        'title' => filter_var($dataReceived['title'], FILTER_SANITIZE_STRING),
                        'type' => filter_var($dataReceived['type'], FILTER_SANITIZE_STRING),
                        'masked' => filter_var($dataReceived['masked'], FILTER_SANITIZE_STRING),
                        'encrypted_data' => filter_var($dataReceived['encrypted'], FILTER_SANITIZE_STRING),
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
                        prefix_table("categories"),
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
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
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
                    'masked' => filter_var($dataReceived['masked'], FILTER_SANITIZE_STRING),
                    'role_visibility' => filter_var($dataReceived['roles'], FILTER_SANITIZE_STRING),
                    'order' => filter_var($dataReceived['order'], FILTER_SANITIZE_NUMBER_INT)
                );
            }

            // Perform update
            DB::update(
                prefix_table("categories"),
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
                    INNER JOIN ".prefix_table("nested_tree")." AS t ON (c.id_folder = t.id)
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
                    FROM ".prefix_table("categories")."
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
                            foreach(explode(',', $field['role_visibility']) as $role) {
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
                                $field['role_visibility']
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
                        prefix_table("categories_folders"),
                        array(
                            'id_category' => $post_id,
                            'id_folder' => $folder
                            )
                    );

                    // prepare a list
                    $row = DB::queryfirstrow("SELECT title FROM ".prefix_table("nested_tree")." WHERE id=%i", $folder);
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
                prefix_table("categories"),
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
                INNER JOIN ".prefix_table("categories")." AS c ON (i.field_id = c.id)
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
                        prefix_table("categories_items"),
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
                "SELECT * FROM ".prefix_table("categories")."
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
                        FROM ".prefix_table("categories_folders")." AS c
                        INNER JOIN ".prefix_table("nested_tree")." AS t ON (c.id_folder = t.id)
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
                        "SELECT * FROM ".prefix_table("categories")."
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
        <span id="encryt_data_'.$field['id'].'" style="margin-left:4px; cursor:pointer;">'. (isset($field['encrypted_data']) && $field['encrypted_data'] === "1") ? '<i class="fa fa-key tip" title="'.$LANG['encrypted_data'].'" onclick="changeEncrypMode(\''.$field['id'].'\', \'1\')"></i>' : '<span class="fa-stack" title="'.$LANG['not_encrypted_data'].'" onclick="changeEncrypMode(\''.$field['id'].'\', \'0\')"><i class="fa fa-key fa-stack-1x"></i><i class="fa fa-ban fa-stack-1x fa-lg" style="color:red;"></i></span>'. '
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

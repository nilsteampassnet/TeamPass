<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      upgrade_operations.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2023 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */

use EZimuel\PHPSecureSession;
use TeampassClasses\SuperGlobal\SuperGlobal;
use PasswordLib\PasswordLib;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
session_name('teampass_session');
session_start();
error_reporting(E_ERROR | E_PARSE);
set_time_limit(600);
$_SESSION['CPM'] = 1;

//include librairies
require_once __DIR__.'/../includes/language/english.php';
require_once __DIR__.'/../includes/config/include.php';
require_once __DIR__.'/../includes/config/settings.php';
require_once __DIR__.'/tp.functions.php';
require_once __DIR__.'/libs/aesctr.php';
require_once __DIR__.'/../includes/config/tp.config.php';

// Prepare POST variables
$post_nb = filter_input(INPUT_POST, 'nb', FILTER_SANITIZE_NUMBER_INT);
$post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);

// Load libraries
$superGlobal = new SuperGlobal();

// Some init
$_SESSION['settings']['loaded'] = '';
$finish = true;

// Get the encrypted password
define('DB_PASSWD_CLEAR', defuse_return_decrypted(DB_PASSWD));

// DataBase
// Test DB connexion
$pass = DB_PASSWD_CLEAR;
$server = DB_HOST;
$pre = DB_PREFIX;
$database = DB_NAME;
$port = DB_PORT;
$user = DB_USER;

if (mysqli_connect(
    $server,
    $user,
    $pass,
    $database,
    $port
)) {
    $db_link = mysqli_connect(
        $server,
        $user,
        $pass,
        $database,
        $port
    );
} else {
    $res = 'Impossible to get connected to server. Error is: ' . addslashes(mysqli_connect_error());
    echo '[{"finish":"1", "msg":"", "error":"Impossible to get connected to server. Error is: ' . addslashes(mysqli_connect_error()) . '!"}]';
    mysqli_close($db_link);
    exit();
}

// Get POST with operation to perform
$post_operation = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (isset($post_operation) === true && empty($post_operation) === false) {
    if ($post_operation === '20230604_1') {
        // ---->
        // OPERATION - 20230604_1 - generate key for item_key

        // Get items to treat
        $rows = mysqli_query(
            $db_link,
            "SELECT id FROM ".$pre."items
            WHERE item_key = '-1'
            ORDER BY id
            LIMIT ".$post_nb.";"
        );
        // Handle error on query
        if (!$rows) {
            echo '[{"finish":"1" , "error":"'.mysqli_error($db_link).'"}]';
            exit();
        }

        // Get total of items to treat
        $total = mysqli_num_rows($rows);

        // Loop on items and update for requested ones
        if ((int) $total > 0) {
            while ($row = mysqli_fetch_array($rows, MYSQLI_ASSOC)) {
                // Gererate a key and update
                mysqli_query(
                    $db_link,
                    "UPDATE `".$pre."items`
                    SET `item_key` = '".uniqidReal(50)."'
                    WHERE `id` = ".$row['id'].";"
                );
                if (mysqli_error($db_link)) {
                    echo '[{"finish":"1", "next":"", "error":"MySQL Error! '.addslashes(mysqli_error($db_link)).'"}]';
                    exit();
                }
            }
        }

        // Manage end of operation
        if ($total === 0) {
            $finish = 1;
        } else {
            $finish = 0;
        }
        // ----<
    } elseif ($post_operation === 'populateItemsTable_CreatedAt') {
        $finish = populateItemsTable_CreatedAt($pre, $post_nb);
    } elseif ($post_operation === 'populateItemsTable_UpdatedAt') {
        $finish = populateItemsTable_UpdatedAt($pre);
    } elseif ($post_operation === 'populateItemsTable_DeletedAt') {
        $finish = populateItemsTable_DeletedAt($pre);
    } elseif ($post_operation === '20231017_1') {
            // ---->
            // OPERATION - 20231017_1 - remove all existing keys
            // if item is personal and user is not owner

            installPurgeUnnecessaryKeys(true, 0,$pre);
    }
}

// Close connection
mysqli_close($db_link);


// Return back
echo '[{"finish":"'.$finish.'" , "next":"", "error":"", "total":"'.$total.'"}]';



function populateItemsTable_CreatedAt($pre, $post_nb)
{
    global $db_link;
    // loop on items - created_at
    $items = mysqli_query(
        $db_link,
        "select i.id as id, ls.date as datetime
        from `" . $pre . "items` as i
        inner join `" . $pre . "log_items` as ls on ls.id_item = i.id
        WHERE ls.action = 'at_creation' AND i.created_at IS NULL
        LIMIT " . $post_nb.";"
    );
    while ($item = mysqli_fetch_assoc($items)) {
        if (empty((string) $item['datetime']) === false && is_null($item['datetime']) === false) {
            // update created_at field
            mysqli_query(
                $db_link,
                "UPDATE `" . $pre . "items` SET created_at = '".$item['datetime']."' WHERE id = ".$item['id']
            );
        }
    }

    // Is it finished?
    $remainingItems = mysqli_num_rows(
        mysqli_query(
            $db_link,
            "SELECT * FROM `" . $pre . "items` WHERE created_at IS NULL"
        )
    );
    return $remainingItems > 0 ? 0 : 1;
}

function populateItemsTable_UpdatedAt($pre)
{
    global $db_link;
    // loop on items - updated_at
    $items = mysqli_query(
        $db_link,
        "select i.id as id, (select date from " . $pre . "log_items where action = 'at_modification' and id_item=id order by date DESC limit 1) as datetime 
        from `" . $pre . "items` as i;"
    );
    while ($item = mysqli_fetch_assoc($items)) {
        if (is_null($item['datetime']) === false) {
            // update updated_at field
            mysqli_query(
                $db_link,
                "UPDATE `" . $pre . "items` SET updated_at = '".$item['datetime']."' WHERE id = ".$item['id']
            );
        }
    }

    return 1;
}

function populateItemsTable_DeletedAt($pre)
{
    global $db_link;
    // loop on items - deleted_at
    $items = mysqli_query(
        $db_link,
        "select i.id as id, (select date from " . $pre . "log_items where action = 'at_deleted' and id_item=id order by date DESC limit 1) as datetime
        from `" . $pre . "items` as i;"
    );
    while ($item = mysqli_fetch_assoc($items)) {
        if (is_null($item['datetime']) === false) {
            // update updated_at field
            mysqli_query(
                $db_link,
                "UPDATE `" . $pre . "items` SET deleted_at = '".$item['datetime']."' WHERE id = ".$item['id']
            );
        }
    }

    return 1;
}


/**
 * Delete unnecessary keys for personal items
 *
 * @param boolean $allUsers
 * @param integer $user_id
 * @return void
 */
function installPurgeUnnecessaryKeys(bool $allUsers = true, int $user_id=0, string $pre)
{
    global $db_link;
    if ($allUsers === true) {
        $users = mysqli_query(
            $db_link,
            'SELECT id
            FROM ' . $pre . 'users
            WHERE id NOT IN ('.OTV_USER_ID.', '.TP_USER_ID.', '.SSH_USER_ID.', '.API_USER_ID.')
            ORDER BY login ASC'
        );
        while ($user = mysqli_fetch_assoc($users)) {
            installPurgeUnnecessaryKeysForUser((int) $user['id'], $pre);
        }
    } else {
        installPurgeUnnecessaryKeysForUser((int) $user_id, $pre);
    }
}

/**
 * Delete unnecessary keys for personal items
 *
 * @param integer $user_id
 * @return void
 */
function installPurgeUnnecessaryKeysForUser(int $user_id=0, string $pre)
{
    global $db_link;
    if ($user_id === 0) {
        return;
    }

    $result = mysqli_query(
        $db_link,
        'SELECT id
        FROM ' . $pre . 'items AS i
        INNER JOIN ' . $pre . 'log_items AS li ON li.id_item = i.id
        WHERE i.perso = 1 AND li.action = "at_creation" AND li.id_user IN ('.TP_USER_ID.', '.$user_id.')',
    );
    $rowcount = mysqli_num_rows($result);
    if ($rowcount > 0) {
        // Build list of items id
        $pfItemsList = [];
        while ($row = mysqli_fetch_assoc($result)) {
            array_push($pfItemsList, $row['id']);
        }

        /*$personalItem = mysqli_fetch_column($result, 0);*/
        $pfItemsList = implode(',', $pfItemsList);
        // Item keys
        mysqli_query(
            $db_link,
            'DELETE FROM ' . $pre . 'sharekeys_items
            WHERE object_id IN ('.$pfItemsList.') AND user_id NOT IN ('.TP_USER_ID.', '.$user_id.')'
        );
        // Files keys
        mysqli_query(
            $db_link,
            'DELETE FROM ' . $pre . 'sharekeys_files
            WHERE object_id IN ('.$pfItemsList.') AND user_id NOT IN ('.TP_USER_ID.', '.$user_id.')'
        );
        // Fields keys
        mysqli_query(
            $db_link,
            'DELETE FROM ' . $pre . 'sharekeys_fields
            WHERE object_id IN ('.$pfItemsList.') AND user_id NOT IN ('.TP_USER_ID.', '.$user_id.')'
        );
        // Logs keys
        mysqli_query(
            $db_link,
            'DELETE FROM ' . $pre . 'sharekeys_logs
            WHERE object_id IN ('.$pfItemsList.') AND user_id NOT IN ('.TP_USER_ID.', '.$user_id.')'
        );
    }
}


/**
 * Permits to refresh the categories of folders
 *
 * @param array $folderIds
 * @return void
 */
function installHandleFoldersCategories(
    array $folderIds, $pre
)
{
    global $db_link;
    $filename = '../includes/config/settings.php';
    include_once '../sources/main.functions.php';
    $pass = defuse_return_decrypted(DB_PASSWD);
    $server = DB_HOST;
    $pre = DB_PREFIX;
    $database = DB_NAME;
    $port = intval(DB_PORT);
    $user = DB_USER;
    $arr_data = array();
    $mysqli = new mysqli($server, $user, $pass, $database, $port);

    // force full list of folders
    if (count($folderIds) === 0) {
        $result = $mysqli->query('SELECT id
            FROM ' . $pre . 'nested_tree
            WHERE personal_folder = 0',
        );
        $rowcount = $result->num_rows;
        if ($rowcount > 0) {
            while ($row = $result->fetch_assoc()) {
                array_push($folderIds, $row['id']);
            }
        }
    }

    // Get complexity
    defineComplexity();

    // update
    foreach ($folderIds as $folder) {
        // Do we have Categories
        // get list of associated Categories
        $arrCatList = array();
        $result = $mysqli->query('SELECT c.id, c.title, c.level, c.type, c.masked, c.order, c.encrypted_data, c.role_visibility, c.is_mandatory,
            f.id_category AS category_id
            FROM ' . $pre . 'categories_folders AS f
            INNER JOIN ' . $pre . 'categories AS c ON (f.id_category = c.parent_id)
            WHERE id_folder = '.$folder
        );
        $rowcount = $result->num_rows;
        if ($rowcount > 0) {
            while ($row = $result->fetch_assoc()) {
                $arrCatList[$row['id']] = array(
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'level' => $row['level'],
                    'type' => $row['type'],
                    'masked' => $row['masked'],
                    'order' => $row['order'],
                    'encrypted_data' => $row['encrypted_data'],
                    'role_visibility' => $row['role_visibility'],
                    'is_mandatory' => $row['is_mandatory'],
                    'category_id' => $row['category_id'],
                );
            }
        }
        $arr_data['categories'] = $arrCatList;

        // Now get complexity
        $valTemp = '';
        $result = $mysqli->query('SELECT valeur
            FROM ' . $pre . 'misc
            WHERE type = "complex" AND intitule = '.$folder
        );
        $rowcount = $result->num_rows;
        if ($rowcount > 0) {
            while ($row = $result->fetch_assoc()) {
                $valTemp = array(
                    'value' => $row['valeur'],
                    'text' => TP_PW_COMPLEXITY[$row['valeur']][1],
                );
            }
        }
        $arr_data['complexity'] = $valTemp;

        // Now get Roles
        $valTemp = '';
        $result = $mysqli->query('SELECT t.title
            FROM ' . $pre . 'roles_values as v
            INNER JOIN ' . $pre . 'roles_title as t ON (v.role_id = t.id)
            WHERE v.intitule = '.$folder.'
            GROUP BY title'
        );
        $rowcount = $result->num_rows;
        if ($rowcount > 0) {
            while ($row = $result->fetch_assoc()) {
                $valTemp .= (empty($valTemp) === true ? '' : ' - ') . $row['title'];
            }
        }
        $arr_data['visibilityRoles'] = $valTemp;

        // now save in DB
        mysqli_query(
            $db_link,
            'UPDATE categories = "'.json_encode($arr_data).'"
            FROM ' . $pre . 'nested_tree
            WHERE id = '.$folder
        );
    }
    
    mysqli_free_result($result); 
    mysqli_close($mysqli);
}
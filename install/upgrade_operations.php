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

set_time_limit(600);


require_once __DIR__.'/../sources/SecureHandler.php';
session_name('teampass_session');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['CPM'] = 1;

//include librairies
require_once __DIR__.'/../includes/language/english.php';
require_once __DIR__.'/../includes/config/include.php';
require_once __DIR__.'/../includes/config/settings.php';
require_once __DIR__.'/../sources/main.functions.php';
require_once __DIR__.'/../includes/libraries/Tree/NestedTree/NestedTree.php';
require_once __DIR__.'/tp.functions.php';
require_once __DIR__.'/libs/aesctr.php';
require_once __DIR__.'/../includes/config/tp.config.php';

// Prepare POST variables
$post_nb = filter_input(INPUT_POST, 'nb', FILTER_SANITIZE_NUMBER_INT);
$post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);

// Load libraries
require_once __DIR__.'/../includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

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

// Load libraries
require_once __DIR__.'/../includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

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

            purgeUnnecessaryKeys(true);
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
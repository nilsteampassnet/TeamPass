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


require_once '../sources/SecureHandler.php';
session_name('teampass_session');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['CPM'] = 1;

//include librairies
require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
require_once '../includes/config/settings.php';
require_once '../sources/main.functions.php';
require_once '../includes/libraries/Tree/NestedTree/NestedTree.php';
require_once 'tp.functions.php';
require_once 'libs/aesctr.php';
require_once '../includes/config/tp.config.php';

// Prepare POST variables
$post_nb = filter_input(INPUT_POST, 'nb', FILTER_SANITIZE_NUMBER_INT);
$post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);

// Load libraries
require_once '../includes/libraries/protect/SuperGlobal/SuperGlobal.php';
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
require_once '../includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

// Get POST with operation to perform
$post_operation = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (isset($post_operation) === true && empty($post_operation) === false) {
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
}

// Close connection
mysqli_close($db_link);


// Return back
echo '[{"finish":"'.$finish.'" , "next":"", "error":"", "total":"'.$total.'"}]';
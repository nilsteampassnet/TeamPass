<?php
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
 * @file      upgrade_operations.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use EZimuel\PHPSecureSession;
use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language();
error_reporting(E_ERROR | E_PARSE);
set_time_limit(600);
$_SESSION['CPM'] = 1;

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

//include librairies
require_once __DIR__.'/../includes/language/english.php';
require_once __DIR__.'/../includes/config/include.php';
require_once __DIR__.'/../includes/config/settings.php';
require_once __DIR__.'/tp.functions.php';
require_once __DIR__.'/libs/aesctr.php';

// Prepare POST variables
$post_nb = filter_input(INPUT_POST, 'nb', FILTER_SANITIZE_NUMBER_INT);
$post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);

// Load libraries
$superGlobal = new SuperGlobal();
$lang = new Language(); 

// Some init
$_SESSION['settings']['loaded'] = '';
$finish = true;

// Get the encrypted password
define('DB_PASSWD_CLEAR', defuse_return_decrypted(DB_PASSWD));

// DataBase
// Test DB connexion
$pass = (string) DB_PASSWD_CLEAR;
$server = (string) DB_HOST;
$pre = (string) DB_PREFIX;
$database = (string) DB_NAME;
$port = (int) DB_PORT;
$user = (string) DB_USER;

$db_link = mysqli_connect(
    $server,
    $user,
    $pass,
    $database,
    $port
);
if ($db_link) {
    $db_link->set_charset(DB_ENCODING);
} else {
    echo '[{"finish":"1", "msg":"", "error":"Impossible to get connected to server. Error is: ' . addslashes(mysqli_connect_error()) . '!"}]';
    exit();
}

// Get POST with operation to perform
$post_operation = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (isset($post_operation) === true && empty($post_operation) === false && strpos($post_operation, 'step') === false) {
    $total = 0;
    /**
     * TAG - 20230604_1
     */
    if ($post_operation === '20230604_1') {
        // ---->
        // OPERATION - 20230604_1 - generate key for item_key

        // Start transaction to avoid autocommit
        mysqli_begin_transaction($db_link, MYSQLI_TRANS_START_READ_WRITE);

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
            mysqli_commit($db_link);
            mysqli_close($db_link);
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
                    mysqli_commit($db_link);
                    mysqli_close($db_link);
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
        $finish = populateItemsTable_CreatedAt($pre);
    } elseif ($post_operation === 'populateItemsTable_UpdatedAt') {
        $finish = populateItemsTable_UpdatedAt($pre);
    } elseif ($post_operation === 'populateItemsTable_DeletedAt') {
        $finish = populateItemsTable_DeletedAt($pre);
    } elseif ($post_operation === '20231017_1') {
            // ---->
            // OPERATION - 20231017_1 - remove all existing keys
            // if item is personal and user is not owner

            installPurgeUnnecessaryKeys(true, 0,$pre);
    } elseif ($post_operation === 'Transparent_recovery_migration') {
        // OPERATION for users transparent recovery
        $finish = transparentRecovery($pre);
    } elseif ($post_operation === 'clean_duplicate_sharekeys') {
        $tables = ['sharekeys_items', 'sharekeys_fields', 'sharekeys_files', 'sharekeys_suggestions', 'sharekeys_logs'];
        foreach ($tables as $table) {
            cleanDuplicateSharekeys($pre.$table);
        }
    }
    // Return back
    echo '[{"finish":"'.$finish.'" , "next":"", "error":"", "total":"'.$total.'"}]';
    // Commit transaction.
    mysqli_commit($db_link);
}

/**
 * 3.1.5.5
 * Clean duplicate sharekeys from database
 * Keeps only the most recent entry for each object_id/user_id pair
 * 
 * @param string $tableName Table name (without prefix)
 * @return int Number of duplicates removed
 */
function cleanDuplicateSharekeys(string $tableName): int
{
    global $db_link;
    
    // Find duplicates
    $result = mysqli_query(
        $db_link,
        "SELECT object_id, user_id, COUNT(*) as count
        FROM `" . $tableName . "`
        GROUP BY object_id, user_id
        HAVING count > 1"
    );
    
    if (!$result) {
        return 1;
    }
    
    while ($duplicate = mysqli_fetch_assoc($result)) {
        // Keep only the most recent entry (highest increment_id)
        $deleteQuery = "DELETE FROM `" . $tableName . "`
            WHERE object_id = " . (int) $duplicate['object_id'] . "
            AND user_id = " . (int) $duplicate['user_id'] . "
            AND increment_id NOT IN (
                SELECT * FROM (
                    SELECT MAX(increment_id)
                    FROM `" . $tableName . "`
                    WHERE object_id = " . (int) $duplicate['object_id'] . "
                    AND user_id = " . (int) $duplicate['user_id'] . "
                ) as temp
            )";
        
        if (mysqli_query($db_link, $deleteQuery)) {
            // Do nothing and continue
        } else {
            error_log('TEAMPASS Error - cleanDuplicateSharekeys delete failed: ' . mysqli_error($db_link));
        }
    }
    
    mysqli_free_result($result);
    return 1;
}

/**
 * 3.1.5 - Transparent Key Recovery
 * Generate user_derivation_seed for existing users (will be finalized on first login)
 * Process in batches of 50 users to avoid timeout
 */
function transparentRecovery(string $pre)
{
    global $db_link;
    $batch_size = 50;
    $offset = 0;

    // Check if transparent_key_recovery_migration log entry exists
    $result = mysqli_query(
        $db_link,
        "SELECT * FROM `" . $pre . "log_system`
        WHERE label = 'transparent_key_recovery_migration' AND qui = 'system'"
    );

    if (mysqli_num_rows($result) === 0) {
        while (true) {
            $result = mysqli_query(
                $db_link,
                "SELECT id FROM `" . $pre . "users`
                WHERE user_derivation_seed IS NULL
                AND disabled = 0
                AND private_key IS NOT NULL
                AND private_key != 'none'
                LIMIT " . $offset . ", " . $batch_size
            );

            if (mysqli_num_rows($result) === 0) {
                break;
            }

            while ($row = mysqli_fetch_assoc($result)) {
                // Generate unique seed for each user
                $user_seed = bin2hex(openssl_random_pseudo_bytes(32));

                mysqli_query(
                    $db_link,
                    "UPDATE `" . $pre . "users`
                    SET user_derivation_seed = '" . $user_seed . "',
                        last_pw_change = " . time() . "
                    WHERE id = " . intval($row['id'])
                );
            }

            $offset += $batch_size;
        }

        // Add log entry for migration completion
        mysqli_query(
            $db_link,
            "INSERT INTO `" . $pre . "log_system`
            (`date`, `label`, `qui`, `action`)
            VALUES
                ('" . time() . "', 'transparent_key_recovery_migration', '1', 'Transparent key recovery migration completed')"
        );
    }
    return 1;
}

/**
 * 
 */
function populateItemsTable_CreatedAt($pre)
{
    global $db_link;

    // loop on items - created_at
    $items = mysqli_query(
        $db_link,
        "select i.id as id, ls.date as datetime
        from `" . $pre . "items` as i
        inner join `" . $pre . "log_items` as ls on ls.id_item = i.id
        WHERE ls.action = 'at_creation' AND i.created_at IS NULL;"
    );

    // Empty lists
    $updateCases = [];
    $ids = [];

    // Generate lists of items to update
    while ($item = mysqli_fetch_assoc($items)) {
        if (empty((string) $item['datetime']) === false && is_null($item['datetime']) === false) {
            $ids[] = $item['id'];
            $updateCases[] = "WHEN id = " . $item['id'] . " THEN '" . $item['datetime'] . "'";
        }
    }

    // Update table in unique query
    if (!empty($ids)) {
        $idsList = implode(',', $ids);
        $updateQuery = "
            UPDATE `" . $pre . "items`
            SET created_at = CASE " . implode(' ', $updateCases) . " END
            WHERE id IN (" . $idsList . ")
        ";
        mysqli_query($db_link, $updateQuery);
    }

    // All items are processed.
    return 1;
}

function populateItemsTable_UpdatedAt($pre)
{
    global $db_link;
    // Start transaction to avoid autocommit
    mysqli_begin_transaction($db_link, MYSQLI_TRANS_START_READ_WRITE);

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

    // Commit transaction.
    mysqli_commit($db_link);

    return 1;
}

function populateItemsTable_DeletedAt($pre)
{
    global $db_link;
    // Start transaction to avoid autocommit
    mysqli_begin_transaction($db_link, MYSQLI_TRANS_START_READ_WRITE);

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

    // Commit transaction.
    mysqli_commit($db_link);

    return 1;
}


/**
 * Delete unnecessary keys for personal items
 *
 * @param boolean $allUsers
 * @param integer $user_id
 * @return void
 */
function installPurgeUnnecessaryKeys(bool $allUsers, int $user_id, string $pre)
{
    global $db_link;

    // Do init
    $allUsers = $allUsers === true ? true : false;
    if (is_null($user_id)) {
        $user_id = 0;
    }

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
function installPurgeUnnecessaryKeysForUser(int $user_id, string $pre)
{
    global $db_link;

    if (is_null($user_id)) {
        $user_id = 0;
    }

    // Start transaction to avoid autocommit
    mysqli_begin_transaction($db_link, MYSQLI_TRANS_START_READ_WRITE);

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

    // Commit transaction.
    mysqli_commit($db_link);
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
    $mysqli2 = new mysqli($server, $user, $pass, $database, $port);

    // force full list of folders
    if (count($folderIds) === 0) {
        $result = $mysqli2->query('SELECT id
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
        $result = $mysqli2->query('SELECT c.id, c.title, c.level, c.type, c.masked, c.order, c.encrypted_data, c.role_visibility, c.is_mandatory,
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
        $result = $mysqli2->query('SELECT valeur
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
        $result = $mysqli2->query('SELECT t.title
            FROM ' . $pre . 'roles_values as v
            INNER JOIN ' . $pre . 'roles_title as t ON (v.role_id = t.id)
            WHERE v.folder_id = '.$folder.'
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
        $mysqli2->query("UPDATE " . $pre . "nested_tree SET categories = '".json_encode($arr_data)."' WHERE id = ".$folder);
    }
    
    mysqli_close($mysqli2);
}

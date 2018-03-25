<?php
/**
 * @file          upgrade_run_final.php
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

/*
** Is always performed at the end of the update process
*/
require_once('../sources/SecureHandler.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";
$_SESSION['CPM'] = 1;

require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
if (!file_exists("../includes/config/settings.php")) {
    echo 'document.getElementById("res_step1_error").innerHTML = "";';
    echo 'document.getElementById("res_step1_error").innerHTML = '.
        '"File settings.php does not exist in folder includes/! '.
        'If it is an upgrade, it should be there, otherwise select install!";';
    echo 'document.getElementById("loader").style.display = "none";';
    exit;
}

require_once '../includes/config/settings.php';
require_once '../sources/main.functions.php';
require_once '../sources/SplClassLoader.php';

$finish = 0;
$next = ""; // init on 1st task to be done

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);


// Test DB connexion
$pass = defuse_return_decrypted($pass);
if (mysqli_connect(
    $server,
    $user,
    $pass,
    $database,
    $port
)
) {
    $db_link = mysqli_connect(
        $server,
        $user,
        $pass,
        $database,
        $port
    );
} else {
    $res = "Impossible to get connected to server. Error is: ".addslashes(mysqli_connect_error());
    echo '[{"finish":"1", "next":"", "error":"Impossible to get connected to server. Error is: '.addslashes(mysqli_connect_error()).'!"}]';
    mysqli_close($db_link);
    exit();
}

//Update CACHE table -> this will be the last task during update
if ($post_type === "reload_cache_table" || empty($post_type)) {
    //Load Tree
    $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

    // truncate table
    mysqli_query($db_link, "TRUNCATE TABLE ".$pre."cache");

    // reload table
    $rows = mysqli_query(
        $db_link,
        "SELECT *
        FROM ".$pre."items as i
        INNER JOIN ".$pre."log_items as l ON (l.id_item = i.id)
        AND l.action = 'at_creation'
        AND i.inactif = 0"
    );
    foreach ($rows as $record) {
        // Get all TAGS
        $tags = "";
        $itemTags = mysqli_query(
            $db_link,
            "SELECT tag FROM ".$pre."tags WHERE item_id=".intval($record['id'])
        );
        $itemTags = mysqli_fetch_array($itemTags);
        foreach ($itemTags as $itemTag) {
            if (!empty($itemTag['tag'])) {
                $tags .= $itemTag['tag']." ";
            }
        }
        // Get renewal period
        $resNT = mysqli_query(
            $db_link,
            "SELECT renewal_period FROM ".$pre."nested_tree WHERE id=".intval($record['id_tree'])
        );
        $resNT = mysqli_fetch_array($resNT);

        // form id_tree to full foldername
        $folder = "";
        $arbo = $tree->getPath($record['id_tree'], true);
        foreach ($arbo as $elem) {
            if ($elem->title == $_SESSION['user_id'] && $elem->nlevel == 1) {
                $elem->title = $_SESSION['login'];
            }
            if (empty($folder)) {
                $folder = stripslashes($elem->title);
            } else {
                $folder .= " » ".stripslashes($elem->title);
            }
        }

        // temp data
        if (!isset($record['login'])) {
            $record['login'] = "";
        }
        if (!isset($resNT['renewal_period'])) {
            $resNT['renewal_period'] = "0";
        }

        // store data
        $res = mysqli_query(
            $db_link,
            "INSERT INTO `".$pre."cache`
            (`id`, `label`, `description`, `tags`, `id_tree`, `perso`, `restricted_to`, `login`, `folder`, `author`, `renewal_period`, `timestamp`) VALUES (
            '".$record['id']."',
            '".addslashes($record['label'])."',
            '".addslashes($record['description'])."',
            '".$tags."',
            '".$record['id_tree']."',
            '".$record['perso']."',
            '".$record['restricted_to']."',
            '".$record['login']."',
            '".$folder."',
            '".$record['id_user']."',
            '".$resNT['renewal_period']."',
            '".$record['date']."'
            )"
        );
        if (mysqli_error($db_link)) {
            echo $res;
        }
    }

    $finish = 1;
}



// 2.1.27
if ($SETTINGS_EXT['version'] === "2.1.27") {
    $tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `".$pre."misc` WHERE type = 'admin' AND intitule = 'migration_to_2127'"));
    if (intval($tmp) === 0) {
        $mysqli_result = mysqli_query(
            $db_link,
            "INSERT INTO `".$pre."misc`
            (`increment_id`, `type`, `intitule`, `valeur`)
            VALUES (NULL, 'admin', 'migration_to_2127', 'done')"
        );
    } else {
        mysqli_query(
            $db_link,
            "UPDATE ".$pre."misc
            SET `valeur` = 'done'
            WHERE type = 'admin' AND intitule = 'migration_to_2127'"
        );
    }

    $tmp = mysqli_num_rows(mysqli_query($db_link, "SELECT * FROM `".$pre."misc` WHERE type = 'admin' AND intitule = 'files_with_defuse'"));
    if (intval($tmp) === 0) {
        $mysqli_result = mysqli_query(
            $db_link,
            "INSERT INTO `".$pre."misc`
            (`increment_id`, `type`, `intitule`, `valeur`)
            VALUES (NULL, 'admin', 'files_with_defuse', 'done')"
        );
    } else {
        mysqli_query(
            $db_link,
            "UPDATE ".$pre."misc
            SET `valeur` = 'done'
            WHERE type = 'admin' AND intitule = 'files_with_defuse'"
        );
    }
}


// alter ITEMS table by adding default value to encryption_type field
mysqli_query($db_link, "ALTER TABLE `".$pre."items` CHANGE encryption_type encryption_type varchar(20) NOT NULL DEFAULT 'defuse'");



/*
* UPDATE CONFIG file
*/
$tp_config_file = "../includes/config/tp.config.php";
if (file_exists($tp_config_file)) {
    if (!copy($tp_config_file, $tp_config_file.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
        echo '[{"error" : "includes/config/tp.config.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.", "result":"", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
        return false;
    } else {
        unlink($tp_config_file);
    }
}
$file_handler = fopen($tp_config_file, 'w');
$config_text = "";
$any_settings = false;

$result = mysqli_query($db_link, "SELECT * FROM `".$pre."misc` WHERE `type` = 'admin'");
while ($row = mysqli_fetch_assoc($result)) {
    // append new setting in config file
    $config_text .= "
    '".$row['intitule']."' => '".$row['valeur']."',";
    if ($any_settings === false) {
        $any_settings = true;
    }
}
mysqli_free_result($result);

// write to config file
if ($any_settings === true) {
    $result = fwrite(
        $file_handler,
        utf8_encode(
            "<?php
global \$SETTINGS;
\$SETTINGS = array (" . $config_text . "
    );"
        )
    );
}
fclose($file_handler);


// FINISHED
echo '[{"finish":"'.$finish.'" , "next":"'.$next.'", "error":""}]';

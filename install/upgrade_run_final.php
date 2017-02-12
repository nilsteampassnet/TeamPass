<?php
/**
 * @file          upgrade_run_final.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
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

//Update CACHE table -> this will be the last task during update
if ($_POST['type'] == "reload_cache_table" || empty($_POST['type'])) {

    //Load Tree
    $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');


    $dbTmp = mysqli_connect(
        $_SESSION['server'],
        $_SESSION['user'],
        $_SESSION['pass'],
        $_SESSION['database'],
        $_SESSION['port']
    );

    // truncate table
    mysqli_query($dbTmp, "TRUNCATE TABLE ".$_SESSION['pre']."cache");

    // reload table
    $rows = mysqli_query($dbTmp,
        "SELECT *
        FROM ".$_SESSION['pre']."items as i
        INNER JOIN ".$_SESSION['pre']."log_items as l ON (l.id_item = i.id)
        AND l.action = 'at_creation'
        AND i.inactif = 0"
    );
    foreach ($rows as $record) {
        // Get all TAGS
        $tags = "";
        $itemTags = mysqli_query($dbTmp,
            "SELECT tag FROM ".$_SESSION['pre']."tags WHERE item_id=".intval($record['id'])
        );
        $itemTags = mysqli_fetch_array($itemTags);
        foreach ($itemTags as $itemTag) {
            if (!empty($itemTag['tag'])) {
                $tags .= $itemTag['tag']." ";
            }
        }
        // Get renewal period
        $resNT = mysqli_query(
            $dbTmp,
            "SELECT renewal_period FROM ".$_SESSION['pre']."nested_tree WHERE id=".intval($record['id_tree'])
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
        if (!isset($record['login'])) $record['login'] = "";
        if (!isset($resNT['renewal_period'])) $resNT['renewal_period'] = "0";

        // store data
        $res = mysqli_query($dbTmp,
            "INSERT INTO `".$_SESSION['pre']."cache`
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
        if (mysqli_error($dbTmp)) {
            echo $res;
        }
    }

    $finish = 1;
}

// alter ITEMS table by adding default value to encryption_type field
mysqli_query($dbTmp, "ALTER TABLE `".$_SESSION['pre']."items` CHANGE encryption_type encryption_type varchar(20) NOT NULL DEFAULT 'defuse'");

echo '[{"finish":"'.$finish.'" , "next":"'.$next.'", "error":""}]';
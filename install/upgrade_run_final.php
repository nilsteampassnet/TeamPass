<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      upgrade_run_final.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2019 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


require_once '../sources/SecureHandler.php';
session_name('teampass_session');
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

require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
require_once '../includes/config/settings.php';
require_once '../sources/main.functions.php';
require_once '../includes/libraries/Tree/NestedTree/NestedTree.php';
require_once 'tp.functions.php';
require_once 'libs/aesctr.php';
require_once '../includes/config/tp.config.php';
require_once '../sources/SplClassLoader.php';

$finish = 0;
$next = ""; // init on 1st task to be done

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);


// Test DB connexion
$pass = defuse_return_decrypted(DB_PASSWD);
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
)
) {
    $db_link = mysqli_connect(
        $server,
        $user,
        $pass,
        $database,
        $port
    );
	$db_link->set_charset(DB_ENCODING);
} else {
    $res = "Impossible to get connected to server. Error is: ".addslashes(mysqli_connect_error());
    echo '[{"finish":"1", "next":"", "error":"Impossible to get connected to server. Error is: '.addslashes(mysqli_connect_error()).'!"}]';
    mysqli_close($db_link);
    exit();
}



// 2.1.27
if (TP_VERSION === "2.1.27") {
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

        
    // alter ITEMS table by adding default value to encryption_type field
    mysqli_query($db_link, "ALTER TABLE `".$pre."items` CHANGE encryption_type encryption_type varchar(20) NOT NULL DEFAULT 'defuse'");

    //
    //
    //
} else if (TP_VERSION === "3.0.0") {
    // Update some values in database
	mysqli_query(
		$db_link,
		"UPDATE ".$pre."misc
		SET `valeur` = '".TP_VERSION_FULL."'
		WHERE type = 'admin' AND intitule = 'teampass_version'"
	);
	
	mysqli_query(
		$db_link,
		"DELETE FROM ".$pre."misc
		WHERE type = 'admin' AND intitule = 'migration_to_2127'"
	);
	
	mysqli_query(
		$db_link,
		"DELETE FROM ".$pre."misc
		WHERE type = 'admin' AND intitule = 'cpassman_version'"
	);
}





/*
* UPDATE CONFIG file
*/
$tp_config_file = "../includes/config/tp.config.php";
if (file_exists($tp_config_file)) {
    if (!copy($tp_config_file, $tp_config_file.'.'.date("Y_m_d", mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y'))))) {
        echo '[{"error" : "includes/config/tp.config.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.", "result":"", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
        return false;
    } else {
        unlink($tp_config_file);
    }
}
// regenerate
$data = array();
$data[0] = "<?php\n";
$data[1] = "global \$SETTINGS;\n";
$data[2] = "\$SETTINGS = array (\n";
$result = mysqli_query($db_link, "SELECT * FROM `".$pre."misc` WHERE `type` = 'admin'");
while ($row = mysqli_fetch_assoc($result)) {
	array_push($data, "    '" . $row['intitule'] . "' => '" . addslashes($row['valeur']) . "',\n");
}
array_push($data, ");\n");
$data = array_unique($data);

mysqli_free_result($result);
// write to config file
file_put_contents($tp_config_file, implode('', isset($data) ? $data : array()));

// FINISHED
echo '[{"finish":"1" , "next":"'.$next.'", "error":""}]';

<?php
/**
 * @file          script.backup.php
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

require dirname(__FILE__) .'/../includes/config/settings.php';
require_once dirname(__FILE__) .'/../includes/config/tp.config.php';
header("Content-type: text/html; charset=utf-8");

$_SESSION['CPM'] = 1;

// connect to DB
require_once $SETTINGS['cpassman_dir'] .'/sources/SplClassLoader.php';
require_once $SETTINGS['cpassman_dir'] .'/includes/libraries/Database/Meekrodb/db.class.php';

// Load libraries
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

$pass = defuse_return_decrypted($pass);
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$error_handler = true;
$link = mysqli_connect($server, $user, $pass, $database, $port);

// Check provided key
if (isset($_GET['key']) === false || empty($_GET['key']) === true) {
    // Try if launched through shell
    $opts = getopt('k:');
    if (isset($opts['k']) === false || empty($opts['k']) === true) {
        echo '[{"error":"no_key_provided"}]';
        return false;
    }
}

// Allocate correct key
if (isset($_GET['key']) === true) {
    $provided_key = $_GET['key'];
} else if (isset($opts['k']) === true) {
    $provided_key = $opts['k'];
}

//get backups infos
$rows = DB::query("SELECT * FROM ".$pre."misc WHERE type = 'admin'");
foreach ($rows as $record) {
    $settings[$record['intitule']] = $record['valeur'];
}

// Check if key is conform
if (empty($settings['bck_script_passkey']) === false) {
    $currentKey = cryption(
        $settings['bck_script_passkey'],
        "",
        "decrypt"
    )['string'];
    if ($currentKey !== $provided_key) {
        echo '[{"error":"not_allowed"}]';
        return false;
    }
}

// Now can we start backup?
if (!empty($settings['bck_script_filename']) && !empty($settings['bck_script_path'])) {
    //get all of the tables
    $tables = array();
    $result = mysqli_query($link, 'SHOW TABLES');
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }
    $return = "";

    //cycle through each table and format the data
    foreach ($tables as $table) {
        $result = mysqli_query($link, 'SELECT * FROM '.$table);
        $num_fields = mysqli_num_fields($result);

        $return .= 'DROP TABLE '.$table.';';
        $row2 = mysqli_fetch_row(mysqli_query($link, 'SHOW CREATE TABLE '.$table));
        $return .= "\n\n".$row2[1].";\n\n";

        for ($i = 0; $i < $num_fields; $i++) {
            while ($row = mysqli_fetch_row($result)) {
                $return .= 'INSERT INTO '.$table.' VALUES(';
                for ($j = 0; $j < $num_fields; $j++) {
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = preg_replace('/\n/', '/\\n/', $row[$j]);
                    if (isset($row[$j])) {
                        $return .= '"'.$row[$j].'"';
                    } else {
                        $return .= '""';
                    }
                    if ($j < ($num_fields - 1)) {
                        $return .= ',';
                    }
                }
                $return .= ");\n";
            }
        }
        $return .= "\n\n\n";
    }

    //save the file
    $bck_filename = $settings['bck_script_filename'].'-'.time();
    $handle = fopen($settings['bck_script_path'].'/'.$bck_filename.'.sql', 'w+');
    fwrite($handle, $return);
    fclose($handle);

    // Encrypt file is required
    if (empty($settings['bck_script_key']) === false) {
        // Encrypt the file
        prepareFileWithDefuse(
            'encrypt',
            $settings['bck_script_path'].'/'.$bck_filename.'.sql',
            $settings['bck_script_path'].'/'.$bck_filename.'.encrypted.sql',
            $settings['bck_script_key']
        );

        // Do clean
        unlink($settings['bck_script_path'].'/'.$bck_filename.'.sql');
        rename(
            $settings['bck_script_path'].'/'.$bck_filename.'.encrypted.sql',
            $settings['bck_script_path'].'/'.$bck_filename.'.sql'
        );
    }

    // store this file in DB
    $tmp = mysqli_num_rows(mysqli_query($link, "SELECT * FROM `".$pre."misc` WHERE type = 'backup' AND intitule = 'filename'"));
    if ($tmp === 0) {
        $mysqli_result = mysqli_query(
            $link,
            "INSERT INTO `".$pre."misc` (`type`, `intitule`, `valeur`) VALUES ('backup', 'filename', '".$bck_filename."')"
        );
    } else {
        $mysqli_result = mysqli_query(
            $link,
            "UPDATE `".$pre."misc` SET `valeur` = '".$bck_filename."' WHERE type = 'backup' AND intitule = 'filename'"
        );
    }

    echo '[{"error":"" , "status":"success"}]';
}

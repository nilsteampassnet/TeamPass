<?php
/**
 * @file          script.backup.php
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

include '../includes/config/settings.php';
header("Content-type: text/html; charset=utf-8");

// connect to DB
require_once '../sources/SplClassLoader.php';

require_once '../includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$error_handler = 'db_error_handler';
$link= mysqli_connect($server, $user, $pass, $database, $port);

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

//get backups infos
$rows = DB::query("SELECT * FROM ".$pre."misc WHERE type = 'admin'");
foreach ($rows as $record) {
    $settings[$record['intitule']] = $record['valeur'];
}
if (!empty($settings['bck_script_filename']) && !empty($settings['bck_script_path'])) {
    //get all of the tables
    $tables = array();
    $result = mysqli_query($link,'SHOW TABLES');
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }
    $return = "";

    //cycle through each table and format the data
    foreach ($tables as $table) {
        $result = mysqli_query($link,'SELECT * FROM '.$table);
        $num_fields = mysqli_num_fields($result);

        $return.= 'DROP TABLE '.$table.';';
        $row2 = mysqli_fetch_row(mysqli_query($link,'SHOW CREATE TABLE '.$table));
        $return.= "\n\n".$row2[1].";\n\n";

        for ($i = 0; $i < $num_fields; $i++) {
            while ($row = mysqli_fetch_row($result)) {
                $return.= 'INSERT INTO '.$table.' VALUES(';
                for ($j=0; $j<$num_fields; $j++) {
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = preg_replace('/\n/', '/\\n/', $row[$j]);
                    if (isset($row[$j])) {
                        $return.= '"'.$row[$j].'"' ;
                    } else {
                        $return.= '""';
                    }
                    if ($j<($num_fields-1)) {
                        $return.= ',';
                    }
                }
                $return.= ");\n";
            }
        }
        $return.="\n\n\n";
    }

    if (!empty($settings['bck_script_key'])) {
        $return = Encryption\Crypt\aesctr::encrypt($return, $settings['bck_script_key'], 256);
    }

    //save the file
    $handle = fopen($settings['bck_script_path'].'/'.$settings['bck_script_filename'].'-'.time().'.sql', 'w+');
    fwrite($handle, $return);
    fclose($handle);
}

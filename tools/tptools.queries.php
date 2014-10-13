<?php
/**
 * @file          items.queries.php
 * @author        Nils Laumaillé
 * @version       0.1
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

session_start();
$_SESSION['CPM'] = 1;

include '../includes/settings.php';
require_once '../includes/include.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
include '../sources/main.functions.php';

// Connect to mysql server
require_once '../includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$error_handler = 'db_error_handler';
DB::$encoding = $encoding;
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);


//Class loader
require_once '../sources/SplClassLoader.php';

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

switch ($_POST['type']) {
    /*
    * CASE
    * creating a new ITEM
    */
    case "tool_1":
        if (!isset($_POST['action'])) {
            $rowColor = false;
            $ret = "";

            $rows = DB::query(
                "SELECT i.id as id, i.pw as pw, k.rand_key as rand_key
                FROM ".$pre."items as i
                LEFT JOIN ".$pre."keys as k ON (k.id = i.id)
                GROUP BY i.id
                LIMIT ".$_POST['index'].", 10"
            );
            foreach ($rows as $record) {
                $pw = decrypt($record['pw']);
                if (strlen($pw) > 1) {
                    $pw = substr($pw, strlen($record['rand_key']));
                    if ($_SESSION['prefix_length'] >= $pw) $reduced_pw ="";
                    else $reduced_pw = substr($pw, $_SESSION['prefix_length']);
                    $pw = str_replace(array('"', "\'"), array("&quot;", "&escapesq;"), $pw);
                    $reduced_pw = str_replace(array('"', "\'"), array("&quot;", "&escapesq;"), $reduced_pw);
                    if ($rowColor == true) {
                        $ret .= "<tr class='alt'><td><input type='checkbox' id='".$record['id']."'></td><td id='old_".$record['id']."'>".$pw."</td><td> -> </td><td id='new_".$record['id']."'>".$reduced_pw."</td><td id='res_".$record['id']."'></td></tr>";
                        $rowColor = false;
                    } else {
                        $ret .= "<tr><td><input type='checkbox' id='".$record['id']."'></td><td id='old_".$record['id']."'>".$pw."</td><td> -> </td><td id='new_".$record['id']."'>".$reduced_pw."</td><td id='res_".$record['id']."'></td></tr>";
                        $rowColor = true;
                    }
                }
            }
            echo '[{"error":"", "result":"'.$ret.'", "index":"'.$_POST['index'].'"}]';
        }
        elseif (isset($_POST['action']) && $_POST['action'] == "tool_clean_1" && $_POST['prefix_len'] != "") {

            $data = DB::queryfirstrow(
                'SELECT i.pw AS pw, k.rand_key AS rand_key
                FROM `'.$pre.'items` as i
                LEFT JOIN '.$pre.'keys as k ON (k.id = i.id)
                WHERE i.id = %i',
                $_POST['id']
            );
            $pw = decrypt($data['pw']);
            $pw = substr($pw, strlen($data['rand_key']));
            $pw = $data['rand_key'].substr($pw, $_POST['prefix_len']);

            DB::update(
                $pre."items",
                array(
                    'pw' => encrypt($pw)
                ),
                "id = %i",
                $_POST['id']
            );

            echo '[{"error":"", "result":"'.$_POST['id'].'"}]';
        }
        break;
}

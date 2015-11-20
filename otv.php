<?php
/**
 * @file          otv.php
 * @author        Nils Laumaillé
 * @version       2.1.24
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sources/sessions.php');
@session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

$html = "";
if (
    filter_var($_GET['code'], FILTER_SANITIZE_STRING) != false
    && filter_var($_GET['stamp'], FILTER_VALIDATE_INT) != false
) {
    //Include files
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

    // connect to DB
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = 'db_error_handler';
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    // check session validity
    $data = DB::queryfirstrow(
        "SELECT id, timestamp, code, item_id FROM ".prefix_table("otv")."
        WHERE code = %i",
        intval($_GET['code'])
    );
    if (
        $data['timestamp'] == $_GET['stamp']
    ) {
        // otv is too old
        if ($data['timestamp'] < (time() - $_SESSION['settings']['otv_expiration_period'] * 86400) ) {
            $html = "Link is too old!";
        } else {
            $dataItem = DB::queryfirstrow(
                "SELECT *
                FROM ".prefix_table("items")." as i
                INNER JOIN ".prefix_table("log_items")." as l ON (l.id_item = i.id)
                WHERE i.id = %i AND l.action = %s",
                intval($data['item_id']),
                'at_creation'
            );

            // get data
            $pw = cryption($dataItem['pw'], SALT, $dataItem['pw_iv'], "decrypt");

            $label = $dataItem['label'];
            $email = $dataItem['email'];
            $url = $dataItem['url'];
            $description = preg_replace('/(?<!\\r)\\n+(?!\\r)/', '', strip_tags($dataItem['description'], $k['allowedTags']));
            $login = str_replace('"', '&quot;', $dataItem['login']);

            // display data
            $html = "<div style='margin:30px;'>".
            	"<div style='font-size:20px;font-weight:bold;'>Welcome to One-Time item view page.</div>".
            	"<div style='font-style:italic;'>Here are the details of the Item that has been shared to you</div>".
            	"<div style='margin-top:10px;'><table>".
				"<tr><td>Label:</td><td>" . $label . "</td</tr>".
            	"<tr><td>Password:</td><td>" . $pw . "</td</tr>".
            	"<tr><td>Description:</td><td>" . $description . "</td</tr>".
            	"<tr><td>login:</td><td>" . $login . "</td</tr>".
            	"<tr><td>URL:</td><td>" . $url ."</td</tr>".
            	"</table></div>".
            	"<div style='margin-top:30px;'>Copy carefully the data you need. This page is only visible once.</div>".
            	"</div>";

        	// delete entry
        	//DB::delete(prefix_table("otv"), "id = %i", intval($_GET['otv_id']));
			
			// display
			echo $html;
        }
    } else {
        echo "Not a valid page!";
    }
} else {
	echo "No valid OTV inputs!";
}
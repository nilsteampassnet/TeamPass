<?php
/**
 * @file          otv.php
 * @author        Nils Laumaillé
 * @version       2.1.20
 * @copyright     (c) 2009-2014 Nils Laumaillé
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
    filter_var($_GET['code'], FILTER_SANITIZE_STRING) != ""
    && filter_var($_GET['item_id'], FILTER_SANITIZE_STRING) >= 0
    && filter_var($_GET['stamp'], FILTER_SANITIZE_STRING) >= 0
    && filter_var($_GET['otv_id'], FILTER_SANITIZE_STRING) >= 0
) {
    //Include files
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

    // connect to DB
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$error_handler = 'db_error_handler';

    // Include main functions used by TeamPass
    require_once 'sources/main.functions.php';

    // check session validity
    $data = DB::queryfirstrow(
        "SELECT timestamp, code, item_id FROM ".$pre."otv
        WHERE id = %i",
        intval($_GET['otv_id'])
    );
    if (
        $data['timestamp'] == $_GET['stamp']
        && $data['code'] == $_GET['code']
        && $data['item_id'] == $_GET['item_id']
    ) {
        // otv is too old
        if ($data['timestamp'] < (time() - $k['otv_expiration_period']) ) {
            $html = "Link is too old!";
        } else {
            $dataItem = DB::queryfirstrow(
                "SELECT *
                FROM ".$pre."items as i
                INNER JOIN ".$pre."log_items as l ON (l.id_item = i.id)
                WHERE i.id = %i AND l.action = %s",
                intval($_GET['item_id']),
                'at_creation'
            );

            // get data
            $pw = decrypt($dataItem['pw']);

        	// get key for original pw
        	$originalKey = DB::queryfirstrow(
                "SELECT rand_key FROM `".$pre."keys`
                WHERE `table` = %s AND `id` = %i",
                'items',
                intval($_GET['item_id'])
            );
        	// unsalt previous pw
        	$pw = substr($pw, strlen($originalKey['rand_key']));


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
        	DB::delete($pre."otv", "id = %i", intval($_GET['otv_id']));
        }
    } else {
        $html = "Not a valid page2!";
    }
} else {
    $html = "Not a valid page!";
}

echo $html;
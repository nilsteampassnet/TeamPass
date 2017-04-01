<?php
/**
 * @file          otv.php
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

require_once('sources/SecureHandler.php');
@session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

$html = "";
if (
    filter_var($_GET['code'], FILTER_SANITIZE_STRING) !== false
    && filter_var($_GET['stamp'], FILTER_VALIDATE_INT) !== false
) {
    //Include files
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
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

    if (!isset($_SESSION['settings']['otv_is_enabled']) || $_SESSION['settings']['otv_is_enabled'] === "0") {
        echo '<div style="padding:10px; margin:90px 30px 30px 30px; text-align:center;" class="ui-widget-content ui-state-error ui-corner-all"><i class="fa fa-warning fa-2x"></i>&nbsp;One-Time-View is not allowed!</div>';
    }

    // check session validity
    $data = DB::queryfirstrow(
        "SELECT id, timestamp, code, item_id FROM ".prefix_table("otv")."
        WHERE code = %s",
        $_GET['code']
    );
    if (
        $data['timestamp'] == intval($_GET['stamp'])
    ) {
        // otv is too old
        if ($data['timestamp'] < ( time() - ($_SESSION['settings']['otv_expiration_period'] * 86400))) {
            $html = "Link is too old!";
        } else {
            // get from DB
            $dataItem = DB::queryfirstrow(
                "SELECT *
                FROM ".prefix_table("items")." as i
                INNER JOIN ".prefix_table("log_items")." as l ON (l.id_item = i.id)
                WHERE i.id = %i AND l.action = %s",
                intval($data['item_id']),
                'at_creation'
            );

            // is Item still valid regarding number of times being seen
            // Decrement the number before being deleted
            $dataDelete = DB::queryfirstrow(
                "SELECT * FROM ".prefix_table("automatic_del")." WHERE item_id=%i",
                $data['item_id']
            );
            if (isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1) {
                if ($dataDelete['del_enabled'] == 1) {
                    if ($dataDelete['del_type'] == 1 && $dataDelete['del_value'] >= 1) {
                        // decrease counter
                        DB::update(
                            $pre."automatic_del",
                            array(
                                'del_value' => $dataDelete['del_value'] - 1
                               ),
                            "item_id = %i",
                            $data['item_id']
                        );
                    } elseif (
                        $dataDelete['del_type'] == 1 && $dataDelete['del_value'] <= 1
                        || $dataDelete['del_type'] == 2 && $dataDelete['del_value'] < time()
                    ) {
                        // delete item
                        DB::delete($pre."automatic_del", "item_id = %i", $data['item_id']);
                        // make inactive object
                        DB::update(
                            prefix_table("items"),
                            array(
                                'inactif' => '1',
                               ),
                            "id = %i",
                            $data['item_id']
                        );
                        // log
                        logItems($data['item_id'], $dataItem['label'], OTV_USER_ID, 'at_delete', 'otv', 'at_automatically_deleted');

                        echo '<div style="padding:10px; margin:90px 30px 30px 30px; text-align:center;" class="ui-widget-content ui-state-error ui-corner-all"><i class="fa fa-warning fa-2x"></i>&nbsp;'.addslashes(
                            $LANG['not_allowed_to_see_pw_is_expired']).'</div>';
                        return false;
                    }
                }
            }

            // get data
            $pw = cryption($dataItem['pw'], "", "decrypt");
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
                "<tr><td>Label:</td><td>" . $label . "</td></tr>".
                "<tr><td>Password:</td><td>" . htmlspecialchars($pw['string']) . "</td></tr>".
                "<tr><td>Description:</td><td>" . $description . "</td></tr>".
                "<tr><td>login:</td><td>" . $login . "</td></tr>".
                "<tr><td>URL:</td><td>" . $url ."</td></tr>".
                "</table></div>".
                "<div style='margin-top:30px;'>Copy carefully the data you need. This page is only visible once.</div>".
                "</div>";

            // log
            logItems($data['item_id'], $dataItem['label'], OTV_USER_ID, 'at_shown', 'otv');

            // delete entry
            DB::delete(prefix_table("otv"), "id = %i", $data['id']);

            // display
            echo $html;
        }
    } else {
        echo '<div style="padding:10px; margin:90px 30px 30px 30px; text-align:center;" class="ui-widget-content ui-state-error ui-corner-all"><i class="fa fa-warning fa-2x"></i>&nbsp;Not a valid page!</div>';
    }
} else {
    echo '<div style="padding:10px; margin:90px 30px 30px 30px; text-align:center;" class="ui-widget-content ui-state-error ui-corner-all"><i class="fa fa-warning fa-2x"></i>&nbsp;No valid OTV inputs!</div>';
}
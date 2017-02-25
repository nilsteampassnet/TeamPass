<?php
/**
 * @file          views_database.php
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
session_start();
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key']))
{
    die('Hacking attempt...');
}

/* do checks */
include $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_views")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset=utf-8");
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

//Load file
require_once 'views_database.load.php';

//TAB 5 - DATABASE
echo '
    <div id="tabs-5">
        <div id="radio_database">
            <input type="radio" id="radio10" name="radio_db" onclick="manage_div_display(\'tab5_1\'); loadTable(\'t_items_edited\');" /><label for="radio10">'.$LANG['db_items_edited'].'</label>
            <input type="radio" id="radio11" name="radio_db" onclick="manage_div_display(\'tab5_2\'); loadTable(\'t_users_logged\');" /><label for="radio11">'.$LANG['db_users_logged'].'</label>
        </div>
        <div id="tab5_1" style="display:none;margin-top:30px;">
            <div style="margin:10px auto 25px auto;min-height:250px;" id="items_edited_page">
                <table id="t_items_edited" cellspacing="0" cellpadding="5" width="100%">
                    <thead><tr>
                        <th style="width-max:38px;"></th>
                        <th style="width:25%;">'.$LANG['item_edition_start_hour'].'</th>
                        <th style="width:30%;">'.$LANG['user'].'</th>
                        <th style="width:35%;">'.$LANG['label'].'</th>
                    </tr></thead>
                    <tbody>
                        <tr><td></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="tab5_2" style="display:none;margin-top:30px;">
            <div style="font-style:italic;">
                <input type="button" class="button" id="but_disconnect_all_users" value="'.htmlentities(strip_tags($LANG['disconnect_all_users']), ENT_QUOTES).'"><br />
                '.$LANG['info_list_of_connected_users_approximation'].'
            </div>
            <div style="margin:10px auto 25px auto;min-height:250px;" id="t_users_logged_page">
                <table id="t_users_logged" cellspacing="0" cellpadding="5" width="100%">
                    <thead><tr>
                        <th style="width-max:38px;"></th>
                        <th style="width:40%;">'.$LANG['user'].'</th>
                        <th style="width:20%;">'.$LANG['role'].'</th>
                        <th style="width:20%;">'.$LANG['login_time'].'</th>
                    </tr></thead>
                    <tbody>
                        <tr><td></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>';
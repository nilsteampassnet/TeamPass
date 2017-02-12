<?php
/**
 * @file          views_logs.php
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
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_views")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
header("Content-type: text/html; charset=utf-8");
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

//Load file
require_once 'views_logs.load.php';

//TAB 6 - LOGS
echo '
    <input type="hidden" id="type_log_displayed" />
    <div id="tabs-6">
        <div id="radio_log">
            <input type="radio" id="radio60" name="radio" onclick="manage_div_display(\'tab6_0\'); loadTable(\'t_connections\');" /><label for="radio60">'.$LANG['connections'].'</label>
            <input type="radio" id="radio61" name="radio" onclick="manage_div_display(\'tab6_1\'); loadTable(\'t_errors\');" /><label for="radio61">'.$LANG['errors'].'</label>
            <input type="radio" id="radio63" name="radio" onclick="manage_div_display(\'tab6_3\'); loadTable(\'t_copy\');" /><label for="radio63">'.$LANG['at_copy'].'</label>
            <input type="radio" id="radio64" name="radio" onclick="manage_div_display(\'tab6_4\'); loadTable(\'t_admin\');" /><label for="radio64">'.$LANG['admin'].'</label>
            <input type="radio" id="radio65" name="radio" onclick="manage_div_display(\'tab6_5\'); loadTable(\'t_items\');" /><label for="radio65">'.$LANG['items'].'</label>
            <input type="radio" id="radio66" name="radio" onclick="manage_div_display(\'tab6_6\'); loadTable(\'t_failed_auth\');" /><label for="radio66">'.$LANG['failed_logins'].'</label>
        </div>
        <div id="tab6_0" style="display:none;margin-top:30px;">
            <div style="margin:10px auto 25px auto;min-height:250px;" id="t_connections_page">
                <table id="t_connections" cellspacing="0" cellpadding="5" width="100%">
                    <thead><tr>
                        <th style="width-max:38px;">'.$LANG['date'].'</th>
                        <th style="width:25%;">'.$LANG['action'].'</th>
                        <th style="width:30%;">'.$LANG['user'].'</th>
                    </tr></thead>
                    <tbody>
                        <tr><td></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="tab6_1" style="display:none;margin-top:30px;">
            <div style="margin:10px auto 25px auto;min-height:250px;" id="t_errors_page">
                <table id="t_errors" cellspacing="0" cellpadding="5" width="100%">
                    <thead><tr>
                        <th style="width-max:38px;">'.$LANG['date'].'</th>
                        <th style="width:60%;">'.$LANG['label'].'</th>
                        <th style="width:20%;">'.$LANG['user'].'</th>
                    </tr></thead>
                    <tbody>
                        <tr><td></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="tab6_3" style="display:none;margin-top:30px;">
            <div style="margin:10px auto 25px auto;min-height:250px;" id="t_copy_page">
                <table id="t_copy" cellspacing="0" cellpadding="5" width="100%">
                    <thead><tr>
                        <th style="width-max:38px;">'.$LANG['date'].'</th>
                        <th style="width:40%;">'.$LANG['label'].'</th>
                        <th style="width:20%;">'.$LANG['user'].'</th>
                    </tr></thead>
                    <tbody>
                        <tr><td></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="tab6_4" style="display:none;margin-top:30px;">
            <div style="margin:10px auto 25px auto;min-height:250px;" id="t_admin_page">
                <table id="t_admin" cellspacing="0" cellpadding="5" width="100%">
                    <thead><tr>
                        <th style="width:30%;">'.$LANG['date'].'</th>
                        <th style="width:30%;">'.$LANG['user'].'</th>
                        <th style="width:40%;">'.$LANG['action'].'</th>
                    </tr></thead>
                    <tbody>
                        <tr><td></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="tab6_5" style="display:none;margin-top:30px;">
            <div style="margin:10px auto 25px auto;min-height:250px;" id="t_items_page">
                <table id="t_items" cellspacing="0" cellpadding="5" width="100%">
                    <thead><tr>
                        <th style="width:10%;">'.$LANG['date'].'</th>
                        <th style="width:40%;">'.$LANG['label'].'</th>
                        <th style="width:20%;">'.$LANG['user'].'</th>
                        <th style="width:20%;">'.$LANG['action'].'</th>
                        <th style="width:10%;">'.$LANG['at_personnel'].'</th>
                    </tr></thead>
                    <tbody>
                        <tr><td></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="tab6_6" style="display:none;margin-top:30px;">
            <div style="margin:10px auto 25px auto;min-height:250px;" id="t_failed_auth_page">
                <table id="t_failed_auth" cellspacing="0" cellpadding="5" width="100%">
                    <thead><tr>
                        <th style="width:10%;">'.$LANG['date'].'</th>
                        <th style="width:40%;">'.$LANG['label'].'</th>
                        <th style="width:20%;">'.$LANG['user'].'</th>
                    </tr></thead>
                    <tbody>
                        <tr><td></td></tr>
                    </tbody>
                </table>
            </div>
        </div>' ,
        isset($_SESSION['user_admin']) && $_SESSION['user_admin'] == 1 ? '
        <div id="div_log_purge" style="margin-top:30px;padding:10px;display:none;" class="ui-state-highlight ">
            <label for="purgeFrom">'.$LANG['purge_log'].'</label>
            <input type="text" id="purgeFrom" name="purgeFrom" />
            <label for="purgeTo">'.$LANG['to'].'</label>
            <input type="text" id="purgeTo" name="purgeTo" />
            <input type="button" id="butPurge" value="'.$LANG['purge_now'].'" />
            <span id="purge_result"></span>
        </div>' : '', '
    </div>';
<?php
/**
 * @file          admin.php
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

if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key']))
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_main")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

// get current statistics items
$statistics_items = array();
if (isset($_SESSION['settings']['send_statistics_items'])) {
    $statistics_items = array_filter(explode(";", $_SESSION['settings']['send_statistics_items']));
}

echo '
<input type="hidden" id="setting_send_stats" value="',isset($_SESSION['settings']['send_stats']) ? $_SESSION['settings']['send_stats'] : '0','" />
<div class="title ui-widget-content ui-corner-all">'.$LANG['thku'].'</div>

<div style="margin:auto; line-height:20px; padding:10px;" id="tabs">
    <ul>
        <li><a href="#tabs-2">'.$LANG['communication_means'].'</a></li>
        <li><a href="#tabs-1">'.$LANG['sending_anonymous_statistics'].'</a></li>
        <li><a href="#tabs-3">'.$LANG['changelog'].'</a></li>
        <li><a href="#tabs-4">'.$LANG['admin_info'].'</a></li>
    </ul>


    <div id="tabs-1">
        <div>
            <span class="fa fa-area-chart fa-2x"></span>&nbsp;<label style="font-size:16px;">'.$LANG['considering_sending_anonymous_statistics'].'</label>
        </div>
        <div class="ui-state-default ui-corner-all" style="padding:5px; margin:15px 0 10px 0;"><span class="fa fa-info-circle fa-lg"></span>&nbsp;'.$LANG['sending_anonymous_statistics_details'].'</div>
        <div style="margin:5px 0 5px 0;">'.$LANG['anonymous_statistics_definition'].':</div>
        <div style="margin-left:10px;">
            <table border="0">
                <thead>
                    <tr>
                    <th>'.$LANG['characteristic'].'</th>
                    <th>'.$LANG['usage_example'].'</th>
                    <th>'.$LANG['current_value'].'</th>
                    </tr>
                </thead>
                <tbody>
                <tr style="border-bottom:1px;">
                    <td width="350px">
                    <input type="checkbox" id="stat_country" style="margin-right:15px;" ',in_array("stat_country", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",' class="stat_option"><label for="stat_country"><b>'.$LANG['country'].'</b></label>
                    </td>
                    <td>
                    <i>'.$LANG['country_statistics'].'</i>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_country" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_users" style="margin-right:15px;" ',in_array("stat_users", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option" class="stat_option"><label for="stat_users"><b>'.$LANG['users'].'</b></label>
                    </td>
                    <td>
                    <i>'.$LANG['users_statistics'].'</i>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_users" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_items" style="margin-right:15px;" ',in_array("stat_items", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_items"><b>'.$LANG['items_all'].'</b></label>
                    </td>
                    <td>
                    <i>'.$LANG['items_statistics'].'</i>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_items" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_items_shared" style="margin-right:15px;" ',in_array("stat_items_shared", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_items_shared"><b>'.$LANG['items_shared'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_items_shared" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_folders" style="margin-right:15px;" ',in_array("stat_folders", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_folders"><b>'.$LANG['folders_all'].'</b></label>
                    </td>
                    <td>
                    <i>'.$LANG['folders_statistics'].'</i>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_folders" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_folders_shared" style="margin-right:15px;" ',in_array("stat_folders_shared", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_folders_shared"><b>'.$LANG['folders_shared'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_folders_shared" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_admins" style="margin-right:15px;" ',in_array("stat_admins", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_admins"><b>'.$LANG['administrators_number'].'</b></label>
                    </td>
                    <td>
                    <i>'.$LANG['administrators_number_statistics'].'</i>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_admin" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_managers" style="margin-right:15px;" ',in_array("stat_managers", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_managers"><b>'.$LANG['managers_number'].'</b></label>
                    </td>
                    <td>
                    <i>'.$LANG['managers_number_statistics'].'</i>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_manager" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_ro" style="margin-right:15px;" ',in_array("stat_ro", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_ro"><b>'.$LANG['readonly_number'].'</b></label>
                    </td>
                    <td>
                    <i>'.$LANG['readonly_number_statistics'].'</i>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_ro" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_mysqlversion" style="margin-right:15px;" ',in_array("stat_mysqlversion", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_mysqlversion"><b>'.$LANG['mysql_version'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_mysql" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_phpversion" style="margin-right:15px;" ',in_array("stat_phpversion", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_phpversion"><b>'.$LANG['php_version'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_php" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_teampassversion" style="margin-right:15px;" ',in_array("stat_teampassversion", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_teampassversion"><b>'.$LANG['teampass_version'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_teampassv" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_languages" style="margin-right:15px;" ',in_array("stat_languages", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_languages"><b>'.$LANG['languages_used'].'</b></label>
                    </td>
                    <td>
                    <i>'.$LANG['languages_statistics'].'</i>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_languages" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_kb" style="margin-right:15px;" ',in_array("stat_kb", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_kb"><b>'.$LANG['kb_option_enabled'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_kb" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_suggestion" style="margin-right:15px;" ',in_array("stat_suggestion", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_suggestion"><b>'.$LANG['suggestion_option_enabled'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_suggestion" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_customfields" style="margin-right:15px;" ',in_array("stat_customfields", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_customfields"><b>'.$LANG['customfields_option_enabled'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_customfields" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_api" style="margin-right:15px;" ',in_array("stat_api", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_api"><b>'.$LANG['api_option_enabled'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_api" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_2fa" style="margin-right:15px;" ',in_array("stat_2fa", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_2fa"><b>'.$LANG['2fa_option_enabled'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_2fa" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_agses" style="margin-right:15px;" ',in_array("stat_agses", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_agses"><b>'.$LANG['agses_option_enabled'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_agses" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_duo" style="margin-right:15px;" ',in_array("stat_duo", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_duo"><b>'.$LANG['duo_option_enabled'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_duo" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_ldap" style="margin-right:15px;" ',in_array("stat_ldap", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_ldap"><b>'.$LANG['ldap_option_enabled'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_ldap" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_syslog" style="margin-right:15px;" ',in_array("stat_syslog", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_syslog"><b>'.$LANG['syslog_option_enabled'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_syslog" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_stricthttps" style="margin-right:15px;" ',in_array("stat_stricthttps", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_stricthttps"><b>'.$LANG['stricthttps_option_enabled'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_https" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_fav" style="margin-right:15px;" ',in_array("stat_fav", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_fav"><b>'.$LANG['favourites_option_enabled'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_fav" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td>
                    <input type="checkbox" id="stat_pf" style="margin-right:15px;" ',in_array("stat_pf", $statistics_items) || count($statistics_items) === 0 ? "checked" : "",'  class="stat_option"><label for="stat_pf"><b>'.$LANG['personalfolders_option_enabled'].'</b></label>
                    </td>
                    <td>
                    </td>
                    <td>
                        <div class="spin_wait" id="value_pf" style="text-align:center;"><span class="fa fa-cog fa-spin "></span></div>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                    <input type="checkbox" id="cb_select_all" style="margin:10px 15px 0 4px;"><label for="cb_select_all"><b>'.$LANG['select_all'].'</b></label>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <div style="text-align:center; margin-top:20px;">
            <table border="0">
                <tr>
                <td>'.$LANG['settings_send_stats'].'&nbsp;</td>
                <td width="200px"><div class="toggle toggle-modern" id="send_stats" data-toggle-on="', isset($_SESSION['settings']['send_stats']) && $_SESSION['settings']['send_stats'] === "1" ? 'true' : 'false', '"></div><input type="hidden" id="send_stats_input" name="send_stats_input" value="', isset($_SESSION['settings']['send_stats']) && $_SESSION['settings']['send_stats'] === "1" ? '1' : '0', '" /></td>
                </tr>
            </table>
        </div>

        <div style="text-align:center; margin-top:20px; font-size:16px;">
        <input type="button" id="but_save_send_stat" style="width:300px;" value="'.$LANG['save_statistics_choice'].'" />
        </div>
    </div>


    <div id="tabs-2" style="font-size:15px;">

        <div>
            <span class="fa fa-globe fa-lg"></span>&nbsp;&nbsp;<a target="_blank" href="http://www.teampass.net">'.$LANG['website_canal'].'</a>
        </div>
        <div style="margin-top:30px;">
            <span class="fa fa-book fa-lg"></span>&nbsp;&nbsp;'.$LANG['documentation_canal'].'&nbsp;<a target="_blank" href="https://teampass.readthedocs.org" style="font-weight:bold;font-style:italic;">ReadTheDoc</a>
        </div>
        <div style="margin-top:13px;">
            <span class="fa fa-bug fa-lg"></span>&nbsp;&nbsp;'.$LANG['bug_canal'].'&nbsp;<a target="_blank" href="https://github.com/nilsteampassnet/TeamPass/issues" style="font-weight:bold;font-style:italic;">Github</a>
        </div>
        <div style="margin-top:13px;">
        <span class="fa fa-lightbulb-o fa-lg"></span>&nbsp;&nbsp;'.$LANG['feature_request_canal'].'&nbsp;<a target="_blank" href="http://teampass.userecho.com/" style="font-weight:bold;font-style:italic;">UserEcho</a>
        </div>


        <div style="margin-top:30px;">
        <span class="fa fa-beer fa-lg"></span>&nbsp;&nbsp;'.$LANG['consider_a_donation'].'&nbsp;<span class="fa fa-smile-o"></span>&nbsp;<a target="_blank" href="http://teampass.net/donation" style="font-weight:bold;font-style:italic;">'.$LANG['more_information'].'</a>
        </div>
    </div>
    <div id="tabs-3">';
        // Display the readme file
        $Fnm = "changelog.md";
        if (file_exists($Fnm)) {
            $tab = file($Fnm);
            echo '
                <h3>'.$LANG['changelog'].'</h3>';
            $show = false;
            $cnt = 0;
            while (list($cle,$val) = each($tab)) {
                if ($cnt < 30) {
                    echo $val."<br />";
                    $cnt ++;
                } elseif ($cnt == 30) {
                    echo '...<br /><br /><b><a href="changelog.md" target="_blank"><span class="fa fa-book"></span>&nbsp;'.$LANG['readme_open'].'</a></b>';
                    break;
                }
            }
        }
echo '
    </div>
    <div id="tabs-4">
    <div id="CPM_infos" style="">'.$LANG['admin_info_loading'].'&nbsp;<span class="fa fa-cog fa-spin"></span></div>
    </div>
</div>';

// javascript
echo '
<script type="text/javascript">
//<![CDATA[
$(function() {
    $("#tabs").tabs();
});
//]]>
</script>';
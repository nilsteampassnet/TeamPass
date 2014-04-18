<?php
/**
 * @file          admin.php
 * @author        Nils Laumaillé
 * @version       2.1.19
 * @copyright     (c) 2009-2014 Nils Laumaillé
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
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "users.php")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include 'error.php';
    exit();
}

echo '
    <div class="title ui-widget-content ui-corner-all">'.$txt['admin'].'</div>
    <div style="width:900px;margin-left:50px; line-height:25px;height:100%;overflow:auto;">';

    // Div for tool info
    echo '
        <div id="CPM_infos" style="float:left;margin-top:10px;margin-left:15px;width:500px;">'.$txt['admin_info_loading'].'&nbsp;<img src="includes/images/ajax-loader.gif" alt="" /></div>';

     //div for information
     echo '
        <div style="float:right;width:300px;padding:10px;" class="ui-state-highlight ui-corner-all">
            <h3>Some instructions</h3>
            <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                Access to <a target="_blank" href="http://www.teampass.net" style="font-weight:bold;font-style:italic;">TeamPass website</a><br />
            <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                For any kind of Help and Support, please use the <a target="_blank" href="http://www.teampass.net/forum" style="font-weight:bold;font-style:italic;">Forum</a><br />
            <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>
                You discovered a Bug or you have an improvement Proposal, please use the <a target="_blank" href="https://github.com/nilsteampassnet/TeamPass/issues" style="font-weight:bold;font-style:italic;">Github channel</a>. <i>If you are not sure, always use the Forum before to obtain a confirmation. This will prevent having to much open tickets at Github</i>.<br />
            <div style="text-align:center;margin-top:10px;">
                '.$txt['thku'].'
            </div>
        </div>';

    // Display the readme file
    $Fnm = "changelog.md";
if (file_exists($Fnm)) {
    $tab = file($Fnm);
    echo '
    <div style="float:left;width:900px;height:150px;overflow:auto;">
    <div style="float:left;" class="readme">
        <h3>'.$txt['changelog'].'</h3>';
    $show = false;
    $cnt = 0;
    while (list($cle,$val) = each($tab)) {
        if ($cnt < 30) {
            echo $val."<br />";
            $cnt ++;
        } elseif ($cnt == 30) {
            echo '...<br /><br /><b><a href="changelog.md" target="_blank">'.$txt['readme_open'].'</a></b>';
            break;
        }
    }
    echo '
    </div></div>';
}
echo '
    </div>';

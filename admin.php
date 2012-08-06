<?php
/**
 * @file 		admin.php
 * @author		Nils Laumaillé
 * @version 	2.1.8
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
	die('Hacking attempt...');

echo '
    <div class="title ui-widget-content ui-corner-all">'.$txt['admin'].'</div>
    <div style="width:900px;margin-left:50px; line-height:25px;height:100%;overflow:auto;">';

    // Div for tool info
    echo '
        <div id="CPM_infos" style="float:left;margin-top:10px;margin-left:15px;width:500px;">'.$txt['admin_info_loading'].'&nbsp;<img src="includes/images/ajax-loader.gif" alt="" /></div>';

     //div for information
     echo '
        <div style="float:right;width:300px;padding:10px;" class="ui-state-highlight ui-corner-all">
            <span class="ui-icon ui-icon-wrench" style="float: left; margin-right: .3em;">&nbsp;</span>'.$txt['bugs_page'].'
            <div style="text-align:center;margin-top:10px;">
                '.$txt['thku'].'
            </div>
        </div>';

    // Display the readme file
    $Fnm = "readme.txt";
    if (file_exists($Fnm)) {
        $tab = file($Fnm);
        echo '
        <div style="float:left;width:900px;height:150px;overflow:auto;">
        <div style="float:left;" class="readme">
            <h3>'.$txt['changelog'].'</h3>';
        $show = false;
        $cnt = 0;
        while(list($cle,$val) = each($tab)) {
            if ( $show == true && $cnt < 30 ){
                echo $val."<br />";
                $cnt ++;
            }
            else if ( $cnt == 30 ){
                echo '...<br /><br /><b><a href="readme.txt" target="_blank">'.$txt['readme_open'].'</a></b>';
                break;
            }
            if ( substr_count($val,"CHANGELOG") == 1 && $show == false ) $show = true;
        }
        echo '
        </div></div>';
    }
    echo '
    </div>';

?>
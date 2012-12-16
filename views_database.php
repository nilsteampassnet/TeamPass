<?php
/**
 * @file           views_database.php
 * @author        Nils Laumaillé
 * @version         2.1.13
 * @copyright     (c) 2009-2012 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link            http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

session_start();

if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
header("Content-type: text/html; charset=utf-8");
include $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

//Load file
require_once 'views_database.load.php';

//TAB 5 - DATABASE
echo '
    <div id="tabs-5">
        <div id="radio_database">
            <input type="radio" id="radio10" name="radio_db" onclick="manage_div_display(\'tab5_1\')" /><label for="radio10">'.$txt['db_items_edited'].'</label>
        </div>
        <div id="tab5_1" style="display:none;margin-top:30px;">
            <div style="margin:10px auto 25px auto;min-height:250px;" id="items_edited_page">
            <table id="t_items_edited" cellspacing="0" cellpadding="5" width="100%">
                <thead><tr>
                    <th style="width-max:38px;"></th>
                    <th style="width:15%;">'.$txt['item_edition_start_hour'].'</th>
                    <th style="width:20%;">'.$txt['login'].'</th>
                    <th style="width:25%;">'.$txt['label'].'</th>
                </tr></thead>
                <tbody>
                    <tr><td></td></tr>
                </tbody>
            </table>
        </div>
    </div>';
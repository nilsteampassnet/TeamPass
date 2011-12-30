<?php
/**
 * @file 		find.php
 * @author		Nils Laumaillé
 * @version 	2.1
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

//Load file
require_once ("find.load.php");

//load the full Items tree
require_once ("sources/NestedTree.class.php");
$tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

//Show the Items in a table view
echo '
    <div class="title ui-widget-content ui-corner-all">'.$txt['find'].'</div>
<div style="margin:10px auto 25px auto;min-height:250px;" id="find_page">
<table id="t_items" cellspacing="0" cellpadding="5" width="100%">
    <thead><tr>
        <th style="width-max:34px;"></th>
        <th style="width:15%;">'.$txt['label'].'</th>
		<th style="width:20%;">'.$txt['login'].'</th>
        <th style="width:25%;">'.$txt['description'].'</th>
        <th style="width:15%;">'.$txt['tags'].'</th>
        <th style="width:20%;">'.$txt['group'].'</th>
    </tr></thead>
    <tbody>
    	<tr><td></td></tr>
    </tbody>
</table>
</div>';
?>
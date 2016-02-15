<?php
/**
 * @file          kb.php
 * @author        Nils Laumaillé
 * @version       2.1.25
 * @copyright     (c) 2009-2015 Nils Laumaillé
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
        !isset($_SESSION['key']) || empty($_SESSION['key'])
        || !isset($_SESSION['settings']['enable_kb'])
        || $_SESSION['settings']['enable_kb'] != 1)
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], curPage())) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

//load language
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'_kb.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

//build list of categories
$tab_users = array();
$rows = DB::query(
    "SELECT id, login FROM ".prefix_table("users")." ORDER BY login ASC"
);
$counter = DB::count();
if ($counter>0) {
    foreach ($rows as $reccord) {
        $tab_users[$reccord['login']] = array(
            'id'=>$reccord['id'],
            'login'=>$reccord['login']
        );
    }
}

echo '
<div class="title ui-widget-content ui-corner-all">
    '.$LANG['kb'].'&nbsp;&nbsp;&nbsp;
    <button title="'.$LANG['new_kb'].'" onclick="OpenDialog(\'kb_form\')" class="button">
        <img src="includes/images/direction_plus.png" alt="" />
    </button>
    <span style="float:right;margin-right:5px;"><img src="includes/images/question-white.png" style="cursor:pointer" title="'.$LANG['show_help'].'" onclick="OpenDialog(\'help_on_users\')" /></span>
</div>';

//Show the KB in a table view
echo '
<div style="margin:10px auto 25px auto;min-height:250px;" id="kb_page">
<table id="t_kb" cellspacing="0" cellpadding="5" width="100%">
    <thead><tr>
        <th style="width:50px;"></th>
        <th style="width:25%;">'.$LANG['category'].'</th>
        <th style="width:50%;">'.$LANG['label'].'</th>
        <th style="width:15%;">'.$LANG['author'].'</th>
    </tr></thead>
    <tbody>
        <tr><td></td></tr>
    </tbody>
</table>
</div>';

//Hidden things
echo '
<input type="hidden" id="kb_id" value="" />';

/* DIV FOR ADDING A KN */
echo '
<div id="kb_form" style="display:none;">
    <label for="kb_label" class="label">'.$LANG['label'].'</label>
    <input type="text" id="kb_label" class="input text ui-widget-content ui-corner-all" />
    <br />

    <div style="width:100%;">
        <div style="float:left;width:50%;">
            <label for="kb_category" class="label">'.$LANG['category'].'</label>
            <input name="kb_category" id="kb_category" class="kb_text ui-widget-content ui-corner-all" width="300px;" value="">
        </div>
        <div style="float:right;width:50%;">
            <label for="" class="">'.$LANG['kb_anyone_can_modify'].' : </label>
            <span class="div_radio">
                <input type="radio" id="modify_kb_yes" name="modify_kb" value="1" checked="checked" /><label for="modify_kb_yes">'.$LANG['yes'].'</label>
                <input type="radio" id="modify_kb_no" name="modify_kb" value="0" /><label for="modify_kb_no">'.$LANG['no'].'</label>
            </span>
        </div>
    </div>

    <div style="float:left;width:100%;">
        <label for="kb_description" class="label">'.$LANG['description'].'</label>
        <textarea rows="5" name="kb_description" id="kb_description" class="input"></textarea>
    </div>

    <div style="float:left;width:100%;margin-top:15px;">
        <label for="kb_associated_to" class="label">'.$LANG['associate_kb_to_items'].'</label>
        <select id="kb_associated_to" class="multiselect" multiple="multiple" name="kb_associated_to[]" style="width: 860px; height: 150px;">';
            //get list of available items
            $items_id_list = array();
            $rows = DB::query(
                "SELECT i.id as id, i.restricted_to as restricted_to, i.perso as perso, i.label as label, i.description as description, i.pw as pw, i.login as login, i.anyone_can_modify as anyone_can_modify,
                    l.date as date,
                    n.renewal_period as renewal_period
                FROM ".prefix_table("items")." as i
                INNER JOIN ".prefix_table("nested_tree")." as n ON (i.id_tree = n.id)
                INNER JOIN ".prefix_table("log_items")." as l ON (i.id = l.id_item)
                WHERE i.inactif = %i
                AND (l.action = %s OR (l.action = %s AND l.raison LIKE %s))
                ORDER BY i.label ASC, l.date DESC",
                '0',
                'at_creation',
                'at_modification',
                'at_pw :%'
            );
foreach ($rows as $reccord) {
    if (!in_array($reccord['id'], $items_id_list) && !empty($reccord['label'])) {
        echo '
        <option value="'.$reccord['id'].'">'.$reccord['label'].'</option>';
        array_push($items_id_list, $reccord['id']);
    }
}
        echo '
        </select>
    </div>
</div>';

//DELETE DIALOG
echo '
<div id="div_kb_delete" style="display:none;">
    <p><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 20px 0;">&nbsp;</span>'.$LANG['confirm_deletion'].'</p>
</div>';

//Hidden things
echo '
<input type="hidden" id="kb_id" value="" />';

//Call javascript stuff
require_once 'kb.load.php';

//If redirection is done to a speoific KB then open it
if (isset($_GET['id']) && !empty($_GET['id'])) {
    echo '
        <script language="javascript" type="text/javascript">
        <!--
        openKB('.$_GET['id'].');
        -->
        </script>';
}

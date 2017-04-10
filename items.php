<?php
/**
 *
 * @file          items.php
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
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], curPage())) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

if ($_SESSION['user_admin'] == 1 && (isset($k['admin_full_right'])
    && $k['admin_full_right'] == true) || !isset($k['admin_full_right'])) {
    $_SESSION['groupes_visibles'] = $_SESSION['personal_visible_groups'];
    $_SESSION['groupes_visibles_list'] = implode(',', $_SESSION['groupes_visibles']);
}

// Get list of users
$usersList = array();
$usersString = "";
$rows = DB::query("SELECT id,login,email FROM ".$pre."users ORDER BY login ASC");
foreach ($rows as $record) {
    $usersList[$record['login']] = array(
        "id" => $record['id'],
        "login" => $record['login'],
        "email" => $record['email'],
       );
    $usersString .= $record['id']."#".$record['login'].";";
}
// Get list of roles
$arrRoles = array();
$listRoles = "";
$rows = DB::query("SELECT id,title FROM ".$pre."roles_title ORDER BY title ASC");
foreach ($rows as $reccord) {
    $arrRoles[$reccord['title']] = array(
        'id' => $reccord['id'],
        'title' => $reccord['title']
       );
    if (empty($listRoles)) {
        $listRoles = $reccord['id'].'#'.$reccord['title'];
    } else {
        $listRoles .= ';'.$reccord['id'].'#'.$reccord['title'];
    }
}

// Hidden things
echo '
<input type="hidden" name="hid_cat" id="hid_cat" value="', isset($_GET['group']) ? filter_var($_GET['group'], FILTER_SANITIZE_NUMBER_INT) : "", '" />
<input type="hidden" id="complexite_groupe" value="" />
<input type="hidden" name="selected_items" id="selected_items" value="" />
<input type="hidden" id="bloquer_creation_complexite" value="" />
<input type="hidden" id="bloquer_modification_complexite" value="" />
<input type="hidden" id="error_detected" value="" />
<input type="hidden" name="random_id" id="random_id" value="" />
<input type="hidden" id="edit_wysiwyg_displayed" value="" />
<input type="hidden" id="richtext_on" value="1" />
<input type="hidden" id="query_next_start" value="0" />
<input type="hidden" id="display_categories" value="0" />
<input type="hidden" id="nb_items_to_display_once" value="', isset($_SESSION['settings']['nb_items_by_query']) ? htmlspecialchars($_SESSION['settings']['nb_items_by_query']) : 'auto', '" />
<input type="hidden" id="user_is_read_only" value="', isset($_SESSION['user_read_only']) && $_SESSION['user_read_only'] == 1 ? '1' : '', '" />
<input type="hidden" id="request_ongoing" value="" />
<input type="hidden" id="request_lastItem" value="" />
<input type="hidden" id="item_editable" value="" />
<input type="hidden" id="timestamp_item_displayed" value="" />
<input type="hidden" id="pf_selected" value="" />
<input type="hidden" id="user_ongoing_action" value="" />
<input type="hidden" id="input_liste_utilisateurs" value="'.$usersString.'" />
<input type="hidden" id="input_list_roles" value="'.htmlentities($listRoles).'" />
<input type="hidden" id="path_fontsize" value="" />
<input type="hidden" id="access_level" value="" />
<input type="hidden" id="empty_clipboard" value="" />
<input type="hidden" id="selected_folder_is_personal" value="" />
<input type="hidden" id="personal_visible_groups_list" value="', isset($_SESSION['personal_visible_groups_list']) ? $_SESSION['personal_visible_groups_list'] : "", '" />
<input type="hidden" id="create_item_without_password" value="', isset($_SESSION['settings']['create_item_without_password']) ? $_SESSION['settings']['create_item_without_password'] : "0", '" />';
// Hidden objects for Item search
if (isset($_GET['group']) && isset($_GET['id'])) {
    echo '
    <input type="hidden" name="open_folder" id="open_folder" value="'.filter_var($_GET['group'], FILTER_SANITIZE_NUMBER_INT).'" />
    <input type="hidden" name="open_id" id="open_id" value="'.filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT).'" />
    <input type="hidden" name="recherche_group_pf" id="recherche_group_pf" value="', in_array(filter_var($_GET['group'], FILTER_SANITIZE_NUMBER_INT), $_SESSION['personal_visible_groups']) ? '1' : '0', '" />
    <input type="hidden" name="open_item_by_get" id="open_item_by_get" value="true" />';
} elseif (isset($_GET['group']) && !isset($_GET['id'])) {
    echo '<input type="hidden" name="open_folder" id="open_folder" value="'.filter_var($_GET['group'], FILTER_SANITIZE_NUMBER_INT).'" />';
    echo '<input type="hidden" name="open_id" id="open_id" value="" />';
    echo '<input type="hidden" name="recherche_group_pf" id="recherche_group_pf" value="', in_array(filter_var($_GET['group'], FILTER_SANITIZE_NUMBER_INT), $_SESSION['personal_visible_groups']) ? '1' : '0', '" />';
    echo '<input type="hidden" name="open_item_by_get" id="open_item_by_get" value="" />';
} else {
    echo '<input type="hidden" name="open_folder" id="open_folder" value="" />';
    echo '<input type="hidden" name="open_id" id="open_id" value="" />';
    echo '<input type="hidden" name="recherche_group_pf" id="recherche_group_pf" value="" />';
    echo '<input type="hidden" name="open_item_by_get" id="open_item_by_get" value="" />';
}
// Is personal SK available
echo '
<input type="hidden" name="personal_sk_set" id="personal_sk_set" value="', isset($_SESSION['user_settings']['session_psk']) && !empty($_SESSION['user_settings']['session_psk']) ? '1':'0', '" />
<input type="hidden" id="personal_upgrade_needed" value="', isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1 && $_SESSION['user_admin'] != 1 && isset($_SESSION['user_upgrade_needed']) && $_SESSION['user_upgrade_needed'] == 1 ? '1':'0', '" />';
// define what group todisplay in Tree
if (isset($_COOKIE['jstree_select']) && !empty($_COOKIE['jstree_select'])) {
    $firstGroup = str_replace("#li_", "", $_COOKIE['jstree_select']);
} else {
    $firstGroup = "";
}

echo '
<input type="hidden" name="jstree_group_selected" id="jstree_group_selected" value="'.htmlspecialchars($firstGroup).'" />
<input type="hidden" id="item_user_token" value="" />
<input type="hidden" id="items_listing_should_stop" value="" />
<input type="hidden" id="new_listing_characteristics" value="" />';

echo '
<div id="div_items">';
// MAIN ITEMS TREE
echo '
    <div class="items_tree">
        <div id="quick_menu" style="float:left; margin-right: 5px;">
            <ul class="quick_menu">
                <li><i class="fa fa-bars"></i>
                    <ul class="menu_250">
                        <li id="jstree_open"><i class="fa fa-expand fa-fw"></i>&nbsp; '.$LANG['expand'].'</li>
                        <li id="jstree_close"><i class="fa fa-compress fa-fw"></i>&nbsp; '.$LANG['collapse'].'</li>
                        <li onclick="refreshTree()"><i class="fa fa-refresh fa-fw"></i>&nbsp; '.$LANG['refresh'].'</li>
                        <li onclick="open_add_group_div()"><i class="fa fa-plus fa-fw"></i>&nbsp; '.$LANG['item_menu_add_rep'].'</li>
                        <li onclick="open_edit_group_div()"><i class="fa fa-pencil fa-fw"></i>&nbsp; '.$LANG['item_menu_edi_rep'].'</li>
                        <li onclick="open_move_group_div()"><i class="fa fa-arrows fa-fw"></i>&nbsp; '.$LANG['item_menu_mov_rep'].'</li>
                        <li onclick="open_del_group_div()"><i class="fa fa-eraser fa-fw"></i>&nbsp; '.$LANG['item_menu_del_rep'].'</li>
                        <li onclick="$(\'#div_copy_folder\').dialog(\'open\');"><i class="fa fa-copy fa-fw"></i>&nbsp; '.$LANG['copy_folder'].'</li>
                        ', isset($_SESSION['settings']['allow_import']) && $_SESSION['settings']['allow_import'] == 1 && $_SESSION['user_admin'] != 1 ? '<li onclick="loadImportDialog()"><i class="fa fa-cloud-upload fa-fw"></i>&nbsp; '.$LANG['import_csv_menu_title'].'</li>' : '' ,
                        (isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] == 1 && $_SESSION['user_admin'] != 1 && $_SESSION['temporary']['user_can_printout'] == true) ? '<li onclick="loadExportDialog()"><i class="fa fa-cloud-download fa-fw"></i>&nbsp; '.$LANG['print_out_menu_title'].'</li>' : '' ,
                        (isset($_SESSION['settings']['settings_offline_mode']) && $_SESSION['settings']['settings_offline_mode'] == 1 && $_SESSION['user_admin'] != 1) ? '<li onclick="loadOfflineDialog()"><i class="fa fa-laptop fa-fw"></i>&nbsp; '.$LANG['offline_menu_title'].'</li>' : '' , '
                    </ul>
                </li>
            </ul>
        </div>
        <div style="margin:3px 0px 10px 18px;font-weight:bold;">
            '.$LANG['items_browser_title'].'
            <input type="text" name="jstree_search" id="jstree_search" class="text ui-widget-content ui-corner-all search_tree" value="'.htmlentities(strip_tags($LANG['item_menu_find']), ENT_QUOTES).'" />
        </div>
        <div id="sidebar" class="sidebar">
            <div id="jstree" style="overflow:auto;"></div>
        </div>
    </div>';
// Zone top right - items list
echo '
    <div id="items_content">
        <div id="items_center">
            <div id="items_path" class="ui-corner-all">
                <div class="quick_menu1" style="float:left; margin-right: 5px;">
                    <ul class="quick_menu">
                        <li><i class="fa fa-bars"></i>
                            <ul class="menu_250">
                                <li id="menu_button_add_item" onclick="open_add_item_div()"><i class="fa fa-plus fa-fw"></i>&nbsp; '.$LANG['item_menu_add_elem'].'</li>
                                <li id="menu_button_edit_item" onclick="open_edit_item_div(', isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1 ? 1 : 0 , ')"><i class="fa fa-pencil fa-fw"></i>&nbsp; '.$LANG['item_menu_edi_elem'].'</li>
                                <li id="menu_button_del_item" onclick="open_del_item_div()"><i class="fa fa-eraser fa-fw"></i>&nbsp; '.$LANG['item_menu_del_elem'].'</li>
                                <li id="menu_button_copy_item" onclick="open_copy_item_to_folder_div()"><i class="fa fa-copy fa-fw"></i>&nbsp; '.$LANG['item_menu_copy_elem'].'</li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <div style="margin-top: 3px;">
                    <div id="txt1"  style="float:left;">
                        <span id="items_path_var"></span>
                    </div>

                    <div class="input-group margin-bottom-sm" style="float:right; margin-top:-1px;">
                        <span class="input-group-addon"><i class="fa fa-binoculars fa-fw"></i></span>
                        <input class="form-control text ui-widget-content" type="text" onkeypress="javascript:if (event.keyCode == 13) globalItemsSearch();" id="search_item" />
                    </div>

                    <i id="items_list_loader" style="display:none;float:right;margin-right:5px;" class="fa fa-cog fa-spin mi-red"></i>&nbsp;
                </div>
            </div>
            <!--<div id="items_list_loader" style="display:none; float:right;margin:-26px 10px 0 0; z-index:1000;"><img src="includes/images/76.gif" alt="loading" /></div>-->
            <div id="items_list"></div>
        </div>';
// Zone ITEM DETAIL
echo '
        <div id="item_details_ok">
            <input type="hidden" id="id_categorie" value="" />
            <input type="hidden" id="id_item" value="" />
            <input type="hidden" id="hid_anyone_can_modify" value="" />
            <div style="height:220px;overflow-y:auto;" id="item_details_scroll">';

echo'
                <div id="item_details_expired" style="display:none;background-color:white; margin:5px;">
                    <div class="ui-state-error ui-corner-all" style="padding:2px;">
                        <i class="fa fa-warning"></i>&nbsp;<b>'.$LANG['pw_is_expired_-_update_it'].'</b>
                    </div>
                </div>
                <table width="100%">';
// Line for LABEL
echo '
                <tr>
                    <td valign="top" class="td_title" colspan="2">
                        <div class="quick_menu2" style="float:left; margin-right: 5px;">
                            <ul class="quick_menu ui-menu">
                                <li><i class="fa fa-bars"></i>
                                    <ul class="menu_250">
                                        <li id="menu_button_copy_pw" class="copy_clipboard"><i class="fa fa-lock fa-fw"></i>&nbsp; '.$LANG['pw_copy_clipboard'].'</li>
                                        <li id="menu_button_copy_login" class="copy_clipboard"><i class="fa fa-user fa-fw"></i>&nbsp; '.$LANG['login_copy'].'</li>
                                        <li id="menu_button_show_pw" onclick="ShowPassword()"><i class="fa fa-eye fa-fw"></i>&nbsp; '.$LANG['mask_pw'].'</li>
                                        <li id="menu_button_copy_link" class="copy_clipboard"><i class="fa fa-link fa-fw"></i>&nbsp; '.$LANG['url_copy'].'</li>
                                        <li id="menu_button_history" onclick="OpenDialog(\'div_item_history\', \'false\')"><i class="fa fa-history fa-fw"></i>&nbsp; '.$LANG['history'].'</li>
                                        <li id="menu_button_share" onclick="OpenDialog(\'div_item_share\', \'false\')"><i class="fa fa-share fa-fw"></i>&nbsp; '.$LANG['share'].'</li>',
                                        (isset($_SESSION['settings']['otv_is_enabled']) && $_SESSION['settings']['otv_is_enabled'] == 1) ? '<li id="menu_button_otv" onclick="prepareOneTimeView()"><i class="fa fa-users fa-fw"></i>&nbsp; '.$LANG['one_time_item_view'].'</li>' : '', '
                                        ', isset($_SESSION['settings']['enable_email_notification_on_item_shown']) && $_SESSION['settings']['enable_email_notification_on_item_shown'] == 1 ? '
                                        <li id="menu_button_notify"><i class="fa fa-volume-up fa-fw"></i>&nbsp; '.$LANG['notify_me_on_change'].'</li>' : '', '
                                        ', isset($_SESSION['settings']['enable_server_password_change']) && $_SESSION['settings']['enable_server_password_change'] == 1 && isset($_SESSION['user_read_only']) && $_SESSION['user_read_only'] !== "1"? '
                                        <li onclick="serverAutoChangePwd()"><i class="fa fa-server fa-fw"></i>&nbsp; '.$LANG['update_server_password'].'</li>' : '', '
                                        ', isset($_SESSION['settings']['enable_suggestion']) && $_SESSION['settings']['enable_suggestion'] == 1 ? '
                                        <li onclick="OpenDialog(\'div_suggest_change\', \'false\')"><i class="fa fa-random fa-fw"></i>&nbsp; '.$LANG['suggest_password_change'].'</li>' : '', '
                                    </ul>
                                </li>
                            </ul>
                        </div>
                        <div id="id_label" style="display:inline; margin:4px 0px 0px 120px; "></div>
                        <input type="hidden" id="hid_label" value="', isset($dataItem) ? htmlspecialchars($dataItem['label']) : '', '" />
                        <div style="float:right; font-family:arial; margin-right:5px;" id="item_viewed_x_times"></div>

                        <!-- INFO -->
                        <div class="" style="float:right;margin-right:5px;" id="item_extra_info" title=""></div>
                        <!-- INFO END -->

                    </td>
                </tr>';
// Line for DESCRIPTION
echo '
                <tr>
                    <td valign="top" class="td_title" width="180px">&nbsp;<i class="fa fa-angle-right"></i>&nbsp;'.$LANG['description'].' :</td>
                    <td>
                        <div id="id_desc" style="font-style:italic;display:inline;"></div><input type="hidden" id="hid_desc" value="', isset($dataItem) ? htmlspecialchars($dataItem['description']) : '', '" />
                    </td>
                </tr>';
// Line for PW
echo '
                <tr>
                    <td valign="top" class="td_title">&nbsp;<i class="fa fa-angle-right"></i>&nbsp;'.$LANG['pw'].' :<i id="button_quick_pw_copy" class="fa fa-paste fa-border fa-sm tip" style="cursor:pointer;display:none;float:right;margin-right:2px;" title="'.$LANG['item_menu_copy_pw'].'"></i></td>
                    <td>
                        &nbsp;
                        <div id="id_pw" style="float:left; cursor:pointer; width:300px;"></div>
                        <input type="hidden" id="hid_pw" value="" />
                        <input type="hidden" id="pw_shown" value="0" />
                    </td>
                </tr>';
// Line for LOGIN
echo '
                <tr>
                    <td valign="top" class="td_title">&nbsp;<i class="fa fa-angle-right"></i>&nbsp;'.$LANG['index_login'].' :<i id="button_quick_login_copy" class="fa fa-paste fa-border fa-sm tip" style="cursor:pointer;display:none;float:right;margin-right:2px;" title="'.$LANG['item_menu_copy_login'].'"></i></td>
                    <td>
                        <div id="id_login" style="float:left;"></div>
                        <input type="hidden" id="hid_login" value="" />
                    </td>
                </tr>';
// Line for EMAIL
echo '
                <tr>
                    <td valign="top" class="td_title">&nbsp;<i class="fa fa-angle-right"></i>&nbsp;'.$LANG['email'].' :</td>
                    <td>
                        <div id="id_email" style="display:inline;"></div><input type="hidden" id="hid_email" value="" />
                    </td>
                </tr>';
// Line for URL
echo '
                <tr>
                    <td valign="top" class="td_title">&nbsp;<i class="fa fa-angle-right"></i>&nbsp;'.$LANG['url'].' :</td>
                    <td>
                        <div id="id_url" style="display:inline;"></div><input type="hidden" id="hid_url" value="" />
                    </td>
                </tr>';
// Line for FILES
echo '
                <tr>
                    <td valign="top" class="td_title">&nbsp;<i class="fa fa-angle-right"></i>&nbsp;'.$LANG['files_&_images'].' :</td>
                    <td>
                        <div id="id_files" style="display:inline;font-size:11px;"></div><input type="hidden" id="hid_files" />
                        <div id="dialog_files" style="display: none;">

                        </div>
                    </td>
                </tr>';
// Line for RESTRICTED TO
echo '
                <tr>
                    <td valign="top" class="td_title">&nbsp;<i class="fa fa-angle-right"></i>&nbsp;'.$LANG['restricted_to'].' :</td>
                    <td>
                        <div id="id_restricted_to" style="display:inline;"></div><input type="hidden" id="hid_restricted_to" /><input type="hidden" id="hid_restricted_to_roles" />
                    </td>
                </tr>';
// Line for TAGS
echo '
                <tr>
                    <td valign="top" class="td_title">&nbsp;<i class="fa fa-angle-right"></i>&nbsp;'.$LANG['tags'].' :</td>
                    <td>
                        <div id="id_tags" style="display:inline;"></div><input type="hidden" id="hid_tags" />
                    </td>
                </tr>';
// Line for KBs
if (isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1) {
    echo '
                    <tr>
                        <td valign="top" class="td_title">&nbsp;<i class="fa fa-angle-right"></i>&nbsp;'.$LANG['kbs'].' :</td>
                        <td>
                            <div id="id_kbs" style="display:inline;"></div><input type="hidden" id="hid_kbs" />
                        </td>
                    </tr>';
}
// lines for FIELDS
if (isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1) {
    foreach ($_SESSION['item_fields'] as $elem) {
        $itemCatName = $elem[0];
    echo '
                    <tr class="tr_fields itemCatName_'.$itemCatName.'">
                        <td valign="top" class="td_title">&nbsp;<i class="fa fa-angle-right"></i>&nbsp;'.$elem[1].' :</td>
                        <td></td>
                    </tr>';
        foreach ($elem[2] as $field) {
                    echo '
                    <tr class="tr_fields itemCatName_'.$itemCatName.'">
                        <td valign="top" class="td_title">&nbsp;&nbsp;<i class="fa fa-caret-right"></i>&nbsp;<i>'.$field[1].'</i> :</td>
                        <td>
                            <div id="id_field_'.$field[0].'" style="display:inline;" class="fields_div"></div><input type="hidden" id="hid_field_'.htmlspecialchars($field[0]).'" class="fields" />
                        </td>
                    </tr>';
        }
    }
}
echo '
                </table>
            </div>
        </div>';
// # NOT ALLOWED
echo '
        <div id="item_details_nok" style="display:none; width:400px; margin:20px auto 20px auto;">
            <div class="ui-state-highlight ui-corner-all" style="padding:10px;">
                <i class="fa fa-warning fa-2x mi-red"></i>&nbsp;<b>'.$LANG['not_allowed_to_see_pw'].'</b>
                <span id="item_details_nok_restriction_list"></span>
            </div>
        </div>';
// DATA EXPIRED
echo '
        <div id="item_details_expired_full" style="display:none; width:400px; margin:20px auto 20px auto;">
            <div class="ui-state-error ui-corner-all" style="padding:10px;">
                <i class="fa fa-warning fa-2x mi-red"></i>&nbsp;<b>'.$LANG['pw_is_expired_-_update_it'].'</b>
            </div>
        </div>';
// # NOT ALLOWED
echo '
        <div id="item_details_no_personal_saltkey" style="display:none; width:400px; margin:20px auto 20px auto; height:180px;">
            <div class="ui-state-highlight ui-corner-all" style="padding:10px;">
                <i class="fa fa-warning fa-2x mi-red"></i>&nbsp;<b>'.$LANG['home_personal_saltkey_info'].'</b>
            </div>
        </div>';

echo '
    </div>';

echo '
</div>';


/********************************
* NEW Item Form
*/
echo '
<div id="div_formulaire_saisi" style="display:none;">
    <form method="post" name="new_item" action="">
        <div id="afficher_visibilite" style="text-align:center;margin-bottom:6px;height:20px;"></div>
        <div id="display_title" style="text-align:center;margin-bottom:6px;font-size:17px;font-weight:bold;height:25px;"></div>
        <div id="new_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>

        <div id="item_tabs">
        <ul>
            <li><a href="#tabs-01">'.$LANG['definition'].'</a></li>
            <li><a href="#tabs-02">'.$LANG['index_password'].' &amp; '.$LANG['visibility'].'</a></li>
            <li><a href="#tabs-03">'.$LANG['files_&_images'].'</a></li>
            ', isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1 ?
            '<li id="form_tab_fields"><a href="#tabs-04">'.$LANG['more'].'</a></li>' :
            '', '
        </ul>
        <div id="tabs-01">';
// Line for LABEL
echo '
            <label for="" class="label_cpm">'.$LANG['label'].' : </label>
            <input type="text" name="label" id="label" onchange="checkTitleDuplicate(this.value, \'', isset($_SESSION['settings']['item_duplicate_in_same_folder']) && $_SESSION['settings']['item_duplicate_in_same_folder'] == 1 ? 0 : 1, '\', \'', isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1 ? 0 : 1, '\', \'display_title\')" class="input_text text ui-widget-content ui-corner-all" />';
// Line for DESCRIPTION
echo '
            <label for="" class="label_cpm">'.$LANG['description'].' : </label>
            <span id="desc_span">
                <textarea rows="5" cols="60" name="desc" id="desc" class="input_text"></textarea>
            </span>
            <br />';
// Line for FOLDERS
echo '
            <label for="" class="">'.$LANG['group'].' : </label>
            <select name="categorie" id="categorie" onchange="RecupComplexite(this.value,0)" style="width:250px; padding:3px;" class="ui-widget-content"><option style="display: none;"></option></select>';
// Line for LOGIN
echo '
            <label for="" class="label_cpm" style="margin-top:10px;">'.$LANG['login'].' : </label>
            <input type="text" name="item_login" id="item_login" class="input_text text ui-widget-content ui-corner-all" />';
// Line for EMAIL
echo '
            <label for="" class="label_cpm">'.$LANG['email'].' : </label>
            <input type="text" name="email" id="email" class="input_text text ui-widget-content ui-corner-all" />';
// Line for URL
echo '
            <label for="" class="label_cpm">'.$LANG['url'].' : </label>
            <input type="text" name="url" id="url" class="input_text text ui-widget-content ui-corner-all" />
        </div>';
// Tabs Items N?2
echo '
        <div id="tabs-02">';
// Line for folder complexity
echo'
            <div style="margin-bottom:10px;" id="expected_complexity">
                <label for="" class="form_label_180">'.$LANG['complex_asked'].'</label>
                <span id="complex_attendue" style="color:#D04806; margin-left:40px;"></span>
            </div>';
// Line for PW
echo '
            <label class="label_cpm">'.$LANG['used_pw'].' :<span id="prout"></span>
                <span id="visible_pw" style="display:none;margin-left:10px;font-weight:bold;"></span>
                <span id="pw_wait" style="display:none;margin-left:10px;"><span class="fa fa-cog fa-spin fa-1x"></span></span>
            </label>
            <input type="password" id="pw1" class="input_text text ui-widget-content ui-corner-all" />
            <input type="hidden" id="mypassword_complex" />
            <label for="" class="label_cpm">'.$LANG['index_change_pw_confirmation'].' :</label>
            <input type="password" name="pw2" id="pw2" class="input_text text ui-widget-content ui-corner-all" />

            <div style="font-size:9px; text-align:center; width:100%;">
                <span id="custom_pw">
                    <input type="checkbox" id="pw_numerics" /><label for="pw_numerics">123</label>
                    <input type="checkbox" id="pw_maj" /><label for="pw_maj">ABC</label>
                    <input type="checkbox" id="pw_symbols" /><label for="pw_symbols">@#&amp;</label>
                    <input type="checkbox" id="pw_secure" checked="checked" /><label for="pw_secure">'.$LANG['secure'].'</label>
                    &nbsp;<label for="pw_size">'.$LANG['size'].' : </label>
                    &nbsp;<input type="text" size="2" id="pw_size" value="8" style="font-size:10px;" />
                </span>

                <span class="fa-stack fa-lg tip" title="'.$LANG['pw_generate'].'" onclick="pwGenerate(\'\')" style="cursor:pointer;">
                    <i class="fa fa-square fa-stack-2x"></i>
                    <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                </span>&nbsp;
                <span class="fa-stack fa-lg tip" title="'.$LANG['copy'].'" onclick="pwCopy(\'\')" style="cursor:pointer;">
                    <i class="fa fa-square fa-stack-2x"></i>
                    <i class="fa fa-copy fa-stack-1x fa-inverse"></i>
                </span>&nbsp;
                <span class="fa-stack fa-lg tip" title="'.$LANG['mask_pw'].'" onclick="showPwd()" style="cursor:pointer;">
                    <i class="fa fa-square fa-stack-2x"></i>
                    <i class="fa fa-eye fa-stack-1x fa-inverse"></i>
                </span>
            </div>
            <div style="width:100%;">
                <div id="pw_strength" style="margin:5px 0 5px 120px;"></div>
            </div>';

// Line for RESTRICTED TO
if (isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1) {
    echo '
            <label for="" class="label_cpm">'.$LANG['restricted_to'].' : </label>
            <select name="restricted_to_list" id="restricted_to_list" multiple="multiple" style="width:100%;" class="ui-widget-content"></select>
            <input type="hidden" name="restricted_to" id="restricted_to" />
            <input type="hidden" size="50" name="restricted_to_roles" id="restricted_to_roles" />
            <div style="line-height:10px;">&nbsp;</div>';
}
// Line for TAGS
echo '
            <label for="" class="label_cpm">'.$LANG['tags'].' : </label>
            <input type="text" name="item_tags" id="item_tags" class="input_text text ui-widget-content ui-corner-all" />';
// Line for Item modification
echo '
            <div style="width:100%;margin:0px 0px 6px 0px;', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 ? '':'display:none;', '">
                <input type="checkbox" name="anyone_can_modify" id="anyone_can_modify"',
                    isset($_SESSION['settings']['anyone_can_modify_bydefault'])
                    && $_SESSION['settings']['anyone_can_modify_bydefault'] == 1 ?
                    ' checked="checked"' : '', ' />
                <label for="anyone_can_modify">'.$LANG['anyone_can_modify'].'</label>
            </div>';
// Line for Item automatically deleted
echo '
            <div style="width:100%;margin:0px 0px 6px 0px;', isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1 ? '':'display:none;', '">
                <input type="checkbox" name="enable_delete_after_consultation" id="enable_delete_after_consultation" />
                <label for="enable_delete_after_consultation">'.$LANG['enable_delete_after_consultation'].'</label>
                <input type="text" value="1" size="1" id="times_before_deletion" />&nbsp;'.$LANG['times'].'&nbsp;
                '.$LANG['automatic_del_after_date_text'].'&nbsp;<input type="text" value="" class="datepicker" readonly="readonly" size="10" id="deletion_after_date" onchange="$(\'#times_before_deletion\').val(\'\')" />
            </div>';
// Line for EMAIL
echo '
            <div>
                <div style="line-height:10px;">&nbsp;</div>
                <label for="" class="label_cpm">'.$LANG['email_announce'].' : </label>
                <select id="annonce_liste_destinataires" multiple="multiple" style="width:100%">';
                foreach ($usersList as $user) {
                    echo '<option value="'.$user['email'].'">'.$user['login'].'</option>';
                }
                echo '
                </select>
            </div>';

echo '

        </div>';
// Tabs EDIT N?3
echo '
        <div id="tabs-03">
            <div id="item_upload">
                <div id="item_upload_list"></div><br />
                <div id="item_upload_wait" class="ui-state-focus ui-corner-all" style="display:none;padding:2px;margin:5px 0 5px 0;">'.$LANG['please_wait'].'...</div>
                <a id="item_attach_pickfiles" href="#" class="button">'.$LANG['select'].'</a>
                <a id="item_attach_uploadfiles" href="#" class="button">'.$LANG['start_upload'].'</a>
                <input type="hidden" id="files_number" value="0" />
            </div>
        </div>';
// Tabs N°4
if (isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1) {
echo '
        <div id="tabs-04">
            <div id="item_more">';
                // load all categories and fields
                foreach ($_SESSION['item_fields'] as $elem) {
                    $itemCatName = $elem[0];
                    echo '
                    <div id="newItemCatName_'.$itemCatName.'" class="newItemCat">
                        <div style="font-weight:bold;font-size:12px;">
                            <span class="fa fa-folder-open mi-grey-1">&nbsp;</span>'.$elem[1].'
                        </div>';
                    foreach ($elem[2] as $field) {
                        echo '
                        <div style="margin:2px 0 2px 15px;">
                            <span class="fa fa-tag mi-grey-1">&nbsp;</span>
                            <label class="cpm_label">'.$field[1].'</span>
                            <input type="text" id="field_'.$field[0].'_'.$field[2].'" class="item_field input_text text ui-widget-content ui-corner-all" size="40">
                        </div>';
                    }
                    echo '
                    </div>';
                }
            echo '
            </div>
        </div>';
}
echo '
    </div>';
echo '
    </form>
    <div style="display:none; padding:5px; margin-top:5px; text-align:center;" id="div_formulaire_saisi_info" class="ui-state-default ui-corner-all"></div>
</div>';

/***************************
* Edit Item Form
*/
echo '
<div id="div_formulaire_edition_item" style="display:none;">
    <form method="post" name="form_edit" action="">
    <div id="edit_afficher_visibilite" style="text-align:center;margin-bottom:6px;height:25px;"></div>
    <div id="edit_display_title" style="text-align:center;margin-bottom:6px;font-size:17px;font-weight:bold;height:25px;"></div>
    <div id="edit_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>';
// Prepare TABS
echo '
    <div id="item_edit_tabs">
        <ul>
            <li><a href="#tabs-1">'.$LANG['definition'].'</a></li>
            <li><a href="#tabs-2">'.$LANG['index_password'].' &amp; '.$LANG['visibility'].'</a></li>
            <li><a href="#tabs-3">'.$LANG['files_&_images'].'</a></li>
            ', isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1 ?
            '<li id="form_edit_tab_fields"><a href="#tabs-4">'.$LANG['more'].'</a></li>' :
            '', '
        </ul>
        <div id="tabs-1">
            <label for="" class="cpm_label">'.$LANG['label'].' : </label>
            <input type="text" size="60" id="edit_label" onchange="checkTitleDuplicate(this.value, \'', isset($_SESSION['settings']['item_duplicate_in_same_folder']) && $_SESSION['settings']['item_duplicate_in_same_folder'] == 1 ? 0 : 1, '\', \'', isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1 ? 0 : 1, '\', \'edit_display_title\')" class="input_text text ui-widget-content ui-corner-all" />

            <label for="" class="cpm_label">'.$LANG['description'].'&nbsp;<span class="fa fa-eraser" style="cursor:pointer;" onclick="clear_html_tags()"></span>&nbsp;</label>
            <span id="edit_desc_span">
                <textarea rows="5" cols="70" id="edit_desc" name="edit_desc" class="input_text"></textarea>
            </span>';
// Line for FOLDER
echo '
            <div style="margin:10px 0px 10px 0px;">
            <label for="" class="">'.$LANG['group'].' : </label>
            <select id="edit_categorie" onchange="RecupComplexite(this.value,1)" style="width:100%;"><option style="display: none;"></option></select>
            </div>';
// Line for LOGIN
echo '
            <label for="" class="cpm_label">'.$LANG['login'].' : </label>
            <input type="text" id="edit_item_login" class="input_text text ui-widget-content ui-corner-all" />

            <label for="" class="cpm_label">'.$LANG['email'].' : </label>
            <input type="text" id="edit_email" class="input_text text ui-widget-content ui-corner-all" />

            <label for="" class="cpm_label">'.$LANG['url'].' : </label>
            <input type="text" id="edit_url" class="input_text text ui-widget-content ui-corner-all" />
        </div>';
// TABS edit n?2
echo '
        <div id="tabs-2">';
// Line for folder complexity
echo'
            <div style="margin-bottom:10px;" id="edit_expected_complexity">
                <label for="" class="cpm_label">'.$LANG['complex_asked'].'</label>
                <span id="edit_complex_attendue" style="color:#D04806;"></span>
            </div>';

echo '
            <div style="line-height:20px;">
                <label for="" class="label_cpm">'.$LANG['used_pw'].' :
                    <span id="edit_visible_pw" style="display:none;margin-left:10px;font-weight:bold;"></span>
                    <span id="edit_pw_wait" style="display:none;margin-left:10px;"><span class="fa fa-cog fa-spin fa-1x"></span></span>
                </label>
                <input type="password" id="edit_pw1" class="input_text text ui-widget-content ui-corner-all" style="width:390px;" />
                <span class="fa fa-clipboard tip" style="cursor:pointer;" id="edit_past_pwds"></span>
                <input type="hidden" id="edit_mypassword_complex" />

                <label for="" class="cpm_label">'.$LANG['confirm'].' : </label>
                <input type="password" id="edit_pw2" class="input_text text ui-widget-content ui-corner-all" style="width:390px;" />
            </div>
            <div style="font-size:9px; text-align:center; width:100%;">
                <span id="edit_custom_pw">
                    <input type="checkbox" id="edit_pw_numerics" /><label for="edit_pw_numerics">123</label>
                    <input type="checkbox" id="edit_pw_maj" /><label for="edit_pw_maj">ABC</label>
                    <input type="checkbox" id="edit_pw_symbols" /><label for="edit_pw_symbols">@#&amp;</label>
                    <input type="checkbox" id="edit_pw_secure" checked="checked" /><label for="edit_pw_secure">'.$LANG['secure'].'</label>
                    &nbsp;<label for="edit_pw_size">'.$LANG['size'].' : </label>
                    &nbsp;<input type="text" size="2" id="edit_pw_size" value="8" style="font-size:10px;" />
                </span>

                <span class="fa-stack fa-lg tip" title="'.$LANG['pw_generate'].'" onclick="pwGenerate(\'edit\')" style="cursor:pointer;">
                    <i class="fa fa-square fa-stack-2x"></i>
                    <i class="fa fa-cogs fa-stack-1x fa-inverse"></i>
                </span>&nbsp;
                <span class="fa-stack fa-lg tip" title="'.$LANG['copy'].'" onclick="pwCopy(\'edit\')" style="cursor:pointer;">
                    <i class="fa fa-square fa-stack-2x"></i>
                    <i class="fa fa-copy fa-stack-1x fa-inverse"></i>
                </span>&nbsp;
                <span class="fa-stack fa-lg tip" title="'.$LANG['mask_pw'].'" onclick="ShowPasswords_EditForm()" style="cursor:pointer;">
                    <i class="fa fa-square fa-stack-2x"></i>
                    <i class="fa fa-eye fa-stack-1x fa-inverse"></i>
                </span>
            </div>
            <div style="width:100%;">
                <div id="edit_pw_strength" style="margin:5px 0 5px 120px;"></div>
            </div>';

if (isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1) {
    echo '
            <div id="div_editRestricted">
                <label for="" class="label_cpm">'.$LANG['restricted_to'].' : </label>
                <select name="edit_restricted_to_list" id="edit_restricted_to_list" multiple="multiple" style="width:100%"></select>
                <input type="hidden" size="50" name="edit_restricted_to" id="edit_restricted_to" />
                <input type="hidden" size="50" name="edit_restricted_to_roles" id="edit_restricted_to_roles" />
                <div style="line-height:10px;">&nbsp;</div>
            </div>';
}

echo '
            <label for="" class="cpm_label">'.$LANG['tags'].' : </label>
            <input type="text" size="50" name="edit_tags" id="edit_tags" class="input_text text ui-widget-content ui-corner-all" />';
// Line for Item modification
echo '
            <div style="width:100%;margin:0px 0px 6px 0px;', isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 ? '':'display:none;', '">
                <input type="checkbox" name="edit_anyone_can_modify" id="edit_anyone_can_modify"',
                    isset($_SESSION['settings']['anyone_can_modify_bydefault'])
                    && $_SESSION['settings']['anyone_can_modify_bydefault'] == 1 ?
                    ' checked="checked"' : '', ' />
                <label for="edit_anyone_can_modify">'.$LANG['anyone_can_modify'].'</label>
            </div>';
// Line for Item automatically deleted
echo '
            <div id="edit_to_be_deleted" style="width:100%;margin:0px 0px 6px 0px;', isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1 ? '':'display:none;', '">
                <input type="checkbox" name="edit_enable_delete_after_consultation" id="edit_enable_delete_after_consultation" />
                <label for="edit_enable_delete_after_consultation">'.$LANG['enable_delete_after_consultation'].'</label>
                <input type="text" value="" size="1" id="edit_times_before_deletion" onchange="$(\'#edit_deletion_after_date\').val(\'\')" />&nbsp;'.$LANG['times'].'&nbsp;
                '.$LANG['automatic_del_after_date_text'].'&nbsp;<input type="text" value="" class="datepicker" readonly="readonly" size="10" id="edit_deletion_after_date" onchange="$(\'#edit_times_before_deletion\').val(\'\')" />
            </div>';

echo '
            <div id="div_anounce_change_by_email">
                <div style="line-height:10px;">&nbsp;</div>
                <label for="" class="label_cpm">'.$LANG['email_announce'].' : </label>
                <select id="edit_annonce_liste_destinataires" multiple="multiple" style="width:100%">';
                foreach ($usersList as $user) {
                    echo '<option value="'.$user['email'].'">'.$user['login'].'</option>';
                }
                echo '
                </select>
            </div>';

echo '
        </div>';
// Tab EDIT N°3
echo '
        <div id="tabs-3">
            <div style="font-weight:bold;font-size:12px;">
                <span class="fa fa-folder-open mi-grey-1">&nbsp;</span>'.$LANG['uploaded_files'].'
            </div>
            <div id="item_edit_list_files" style="margin-left:25px;"></div>
            <div style="margin-top:10px;font-weight:bold;font-size:12px;">
                <span class="fa fa-folder-open mi-grey-1">&nbsp;</span>'.$LANG['upload_files'].'
            </div>
            <div id="item_edit_upload">
                <div id="item_edit_upload_list"></div><br />
                <div id="item_edit_upload_wait" class="ui-state-focus ui-corner-all" style="display:none;padding:2px;margin:5px 0 5px 0;">'.$LANG['please_wait'].'...</div>
                <a id="item_edit_attach_pickfiles" href="#" class="button">'.$LANG['select'].'</a>
                <a id="item_edit_attach_uploadfiles" href="#sd" class="button">'.$LANG['start_upload'].'</a>
                <input type="hidden" id="edit_files_number" value="0" />
            </div>
        </div>';
// Tabs EDIT N°4 -> Categories
if (isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1) {
echo '
        <div id="tabs-4">
            <div id="edit_item_more">';
                // load all categories and fields
                foreach ($_SESSION['item_fields'] as $elem) {
                    echo '
                    <div class="editItemCat" id="editItemCatName_'.$elem[0].'">
                        <div style="font-weight:bold;font-size:12px;">
                            <span class="fa fa-folder-open mi-grey-1">&nbsp;</span>'.$elem[1].'
                        </div>';
                    foreach ($elem[2] as $field) {
                        echo '
                        <div style="margin:2px 0 2px 15px;">
                            <span class="fa fa-tag mi-grey-1">&nbsp;</span>
                            <label class="cpm_label">'.$field[1].'</label>
                            <input type="text" id="edit_field_'.$field[0].'" class="edit_item_field input_text text ui-widget-content ui-corner-all" size="40">
                        </div>';
                    }
                    echo '
                    </div>';
                }
            echo '
            </div>
        </div>
    </div>';
}
echo '
    <div style="display:none; padding:5px;" id="div_formulaire_edition_item_info" class="ui-state-default ui-corner-all"></div>
    </div>
    </form>
</div>';

/*
* ADD NEW FOLDER form
*/
echo '
<div id="div_ajout_rep" style="display:none;">
    <div id="new_rep_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <table>
        <tr>
            <td>'.$LANG['label'].' : </td>
            <td><input type="text" id="new_rep_titre" style="width:242px; padding:3px;" class="ui-widget-content" /></td>
        </tr>
        <tr>
            <td>'.$LANG['sub_group_of'].' : </td>
            <td><select id="new_rep_groupe" style="width:250px; padding:3px;" class="ui-widget-content">
                ', (isset($_SESSION['settings']['can_create_root_folder']) && $_SESSION['settings']['can_create_root_folder'] == 1 || $_SESSION['user_manager'] === "1") ? '<option value="0">'.$LANG['root'].'</option>' : '', '
            </select></td>
        </tr>
        <tr>
            <td>'.$LANG['complex_asked'].' : </td>
            <td><select id="new_rep_complexite" style="width:250px; padding:3px;" class="ui-widget-content">';
foreach ($_SESSION['settings']['pwComplexity'] as $complex) {
    echo '<option value="'.$complex[0].'">'.$complex[1].'</option>';
}
echo '
            </select>
            </td>
        </tr>';
echo '
    </table>
    <div id="add_folder_loader" style="display:none;text-align:center;margin-top:20px;">
        <i class="fa fa-cog fa-spin"></i>&nbsp;'.$LANG['please_wait'].'...
    </div>
</div>';
// Formulaire EDITER REPERTORIE
echo '
<div id="div_editer_rep" style="display:none;">
    <div id="edit_rep_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <table>
        <tr>
            <td>'.$LANG['new_label'].' : </td>
            <td><input type="text" id="edit_folder_title" style="width:242px; padding:3px;" class="ui-widget-content" /></td>
        </tr>
        <tr>
            <td>'.$LANG['group_select'].' : </td>
            <td><select id="edit_folder_folder" style="width:250px; padding:3px;" class="ui-widget-content"></select></td>
        </tr>
        <tr>
            <td>'.$LANG['complex_asked'].' : </td>
            <td><select id="edit_folder_complexity" style="width:250px; padding:3px;" class="ui-widget-content">
                <option value="">---</option>';
foreach ($_SESSION['settings']['pwComplexity'] as $complex) {
    echo '<option value="'.$complex[0].'">'.$complex[1].'</option>';
}
echo '
            </select>
            </td>
        </tr>
    </table>
    <div id="edit_folder_loader" style="display:none;text-align:center;margin-top:20px;">
        <i class="fa fa-cog fa-spin"></i>&nbsp;'.$LANG['please_wait'].'...
    </div>
</div>';
// Formulaire MOVE FOLDER
echo '
<div id="div_move_folder" style="display:none;">
    <div id="move_rep_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <div style="text-align:center;margin-top:20px;">
        <p>'.$LANG['folder_will_be_moved_below'].'</p>
        <div>
        <select id="move_folder_id" style="width:250px; padding:3px;" class="ui-widget-content">
        </select>
        </div>
    </div>
    <div id="move_folder_loader" style="display:none;text-align:center;margin-top:20px;">
        <i class="fa fa-cog fa-spin"></i>&nbsp;'.$LANG['please_wait'].'...
    </div>
</div>';
// Formulaire COPY FOLDER
echo '
<div id="div_copy_folder" style="display:none;">
    <div id="div_copy_folder_info" class="ui-widget-content ui-state-highlight ui-corner-all" style="padding:5px;"><span class="fa fa-info-circle fa-2x"></span>&nbsp;'.$LANG['copy_folder_info'].'</div>

    <div style="margin:10px 0 0 0;">
        <label style="float:left; width:150px;">'.$LANG['copy_folder_source'].'</label>
        <select id="copy_folder_source_id" style="width:300px; padding:3px;" class="ui-widget-content"></select>
    </div>
    <div style="margin:10px 0 0 0;">
        <label style="float:left; width:150px;">'.$LANG['copy_folder_destination'].'</label>
        <select id="copy_folder_destination_id" style="width:300px; padding:3px;" class="ui-widget-content"></select>
    </div>

    <div id="div_copy_folder_msg" style="text-align:center;padding:5px;display:none; margin-top:10px; font-size:14px;" class="ui-corner-all"></div>
</div>';
// Formulaire SUPPRIMER REPERTORIE
echo '
<div id="div_supprimer_rep" style="display:none;">
    <table>
        <tr>
            <td>'.$LANG['group_select'].' : </td>
            <td><select id="delete_rep_groupe" style="width:250px; padding:3px;" class="ui-widget-content">
            </select></td>
        </tr>
        <tr>
        <td colspan="2">
            <div id="delete_rep_groupe_validate_div" class="ui-state-default ui-corner-all" style="padding:5px; margin-top:10px;">
                <input type="checkbox" id="delete_rep_groupe_validate"><label for="delete_rep_groupe_validate">'.$LANG['confirm_delete_group'].'</label>
            </div>
        </td>
        </tr>
    </table>
    <div id="del_rep_show_error" style="text-align:center;padding:5px;display:none;margin-top:10px;" class="ui-state-error ui-corner-all"></div>

    <div id="del_folder_loader" style="display:none;text-align:center;margin-top:15px;">
        <i class="fa fa-cog fa-spin"></i>&nbsp;'.$LANG['please_wait'].'...
    </div>
</div>';
// SUPPRIMER UN ELEMENT
echo '
<div id="div_del_item" style="display:none;">
        <h2 id="div_del_item_selection"></h2>
        <div style="text-align:center;padding:8px;" class="ui-state-error ui-corner-all">
            <span class="fa fa-warning fa-2x"></span>&nbsp;'.$LANG['confirm_deletion'].'
        </div>
</div>';
// DIALOG INFORM USER THAT LINK IS COPIED
echo '
<div id="div_item_copied" style="display:none;">
    <div style="text-align:center;padding:8px;" class="ui-state-focus ui-corner-all">
        <span class="fa fa-info fa-2x"></span>&nbsp;'.$LANG['link_is_copied'].'
    </div>
    <div id="div_display_link"></div>
</div>';
// DIALOG TO WHAT FOLDER COPYING ITEM
echo '
<div id="div_copy_item_to_folder" style="display:none;">
    <div id="copy_item_to_folder_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <h2 id="div_copy_item_to_folder_item"></h2>
    <div style="text-align:center;">
        <div>'.$LANG['item_copy_to_folder'].'</div>
        <div style="margin:10px;">
            <select id="copy_in_folder" style="width:300px;">
                ', (isset($_SESSION['can_create_root_folder']) && $_SESSION['can_create_root_folder'] == 1) ? '<option value="0">'.$LANG['root'].'</option>' : '', '' .
            '</select>
        </div>
    </div>
    <div style="height:20px;text-align:center;margin:2px;" id="copy_item_info" class=""></div>
</div>';
// DIALOG FOR HISTORY OF ITEM
echo '
<div id="div_item_history" style="display:none;">
    <div id="item_history_log"></div>
    ', (isset($_SESSION['settings']['insert_manual_entry_item_history']) && $_SESSION['settings']['insert_manual_entry_item_history'] == 1) ?
'<div id="new_history_entry_form" style="display:none; margin-top:10px;"><hr>
        <div id="div_add_history_entry">
            <div id="item_history_log_error"></div>
            '.$LANG['label'].'&nbsp;<input type="text" id="add_history_entry_label" size="40" />&nbsp;
            <span class="button" style="margin-top:6px;" onclick="manage_history_entry(\'add_entry\',\'\')">'.$LANG['add_history_entry'].'</div>
        </div>
    </div>'
:'', '
</div>';
// DIALOG FOR ITEM SHARE
echo '
<div id="div_item_share" style="display:none;">
    <div id="div_item_share_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>
    <div style="">'.$LANG['item_share_text'].'</div>
    <input type="text" id="item_share_email" class="ui-corner-all" style="width:100%;" />
    <div id="div_item_share_status" style="text-align:center;margin-top:15px;display:none; padding:5px;" class="ui-corner-all">
        <i class="fa fa-cog fa-spin fa-2x"></i>&nbsp;<b>'.$LANG['please_wait'].'</b>
    </div>
</div>';
// DIALOG FOR ITEM IS UPDATED
echo '
<div id="div_item_updated" style="display:none;">
    <div style="">'.$LANG['item_updated_text'].'</div>
</div><br />';

// DIALOG FOR SUGGESTING PWD CHANGE
echo '
<div id="div_suggest_change" style="display:none;">
    <div style="padding:5px; text-align:center;" class="ui-corner-all ui-state-default"><i class="fa fa-info-circle fa-lg"></i>&nbsp;'.$LANG['suggest_password_change_intro'].'</div>
    <div style=" margin-top:10px;" id="div_suggest_change_html"></div>
    <div id="div_suggest_change_wait" style="margin-top:10; padding:5px; display:none;" class="ui-state-focus ui-corner-all"></div>
</div><br />';

// Off line mode
if (isset($_SESSION['settings']['settings_offline_mode']) && $_SESSION['settings']['settings_offline_mode'] == 1) {
    echo '
    <div id="dialog_offline_mode" style="display:none;">
        <div id="div_offline_mode">
            <i class="fa fa-cog fa-spin fa-2x"></i>
        </div>
    </div>';
}

// Export items to file
if (isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] == 1 && $_SESSION['temporary']['user_can_printout'] == true) {
    echo '
    <div id="dialog_export_file" style="display:none;">
        <div id="div_export_file">
            <i class="fa fa-cog fa-spin fa-2x"></i>
        </div>
    </div>';
}

// Import items
if (isset($_SESSION['settings']['allow_import']) && $_SESSION['settings']['allow_import'] == 1 && $_SESSION['user_admin'] != 1) {
    echo '
    <div id="dialog_import_file" style="display:none;">
        <div id="div_import_file">
            <i class="fa fa-cog fa-spin fa-2x"></i>
        </div>
    </div>';
}

// USERS passwords upgrade
if (isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1
    && $_SESSION['user_admin'] != 1 && isset($_SESSION['user_upgrade_needed']) && $_SESSION['user_upgrade_needed'] == 1
) {
    echo '
    <div id="dialog_upgrade_personal_passwords" style="display:none;">
        <div style="text-align:center;">
            <div>'.$LANG['pf_change_encryption'].'</div>
            <div id="dialog_upgrade_personal_passwords_status" style="margin:15px 0 15px 0; font-weight:bold;">', isset($_SESSION['user_settings']['session_psk']) ? $LANG['pf_sk_set'] : $LANG['pf_sk_not_set'], '</div>
        </div>
    </div>';
}

// SSH dialogbox
echo '
<div id="dialog_ssh" style="display:none;padding:4px;">
    <div id="div_ssh">
        <i class="fa fa-cog fa-spin fa-2x"></i>&nbsp;<b>'.$LANG['please_wait'].'</b>
    </div>
</div>';

require_once 'items.load.php';

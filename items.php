<?php
/**
 *
 * @file      items.php
 * @author    Nils Laumaillé
 * @version     2.1.27
 * @copyright   (c) 2009-2017 Nils Laumaillé
 * @licensing   GNU AFFERO GPL 3.0
 * @link      http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once 'sources/SecureHandler.php';
@session_start();

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

require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset==utf-8");

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
<input type="hidden" name="hid_cat" id="hid_cat" value="', isset($_GET['group']) ? htmlspecialchars($_GET['group']) : "", '" />
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
  <input type="hidden" name="open_folder" id="open_folder" value="'.htmlspecialchars($_GET['group']).'" />
  <input type="hidden" name="open_id" id="open_id" value="'.htmlspecialchars($_GET['id']).'" />
  <input type="hidden" name="recherche_group_pf" id="recherche_group_pf" value="', in_array(htmlspecialchars($_GET['group']), $_SESSION['personal_visible_groups']) ? '1' : '0', '" />
  <input type="hidden" name="open_item_by_get" id="open_item_by_get" value="true" />';
} elseif (isset($_GET['group']) && !isset($_GET['id'])) {
  echo '<input type="hidden" name="open_folder" id="open_folder" value="'.htmlspecialchars($_GET['group']).'" />';
  echo '<input type="hidden" name="open_id" id="open_id" value="" />';
  echo '<input type="hidden" name="recherche_group_pf" id="recherche_group_pf" value="', in_array(htmlspecialchars($_GET['group']), $_SESSION['personal_visible_groups']) ? '1' : '0', '" />';
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
<input type="hidden" id="new_listing_characteristics" value="" />
<input type="hidden" id="current_page_number" value="1" />';

echo '
<div class="row">
  <div class="col col-md-4 text-left">
    <div style="width:100%; height:40px;">
      <div style="float:left;">
        <div class="btn-group" role="group">
          <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <span class="fa fa-bars fa-1x"></span>
          </button>
          <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
            <a class="dropdown-item" href="#" id="jstree_open"><i class="fa fa-expand fa-fw"></i>&nbsp; '.$LANG['expand'].'</a>
            <a class="dropdown-item" href="#" id="jstree_close"><i class="fa fa-compress fa-fw"></i>&nbsp; '.$LANG['collapse'].'</a>
            <a class="dropdown-item" href="#" onclick="refreshTree()"><i class="fa fa-refresh fa-fw"></i>&nbsp; '.$LANG['refresh'].'</a>
            <a class="dropdown-item" href="#" onclick="open_add_group_div()"><i class="fa fa-plus fa-fw"></i>&nbsp; '.$LANG['item_menu_add_rep'].'</a>
            <a class="dropdown-item" href="#" onclick="open_edit_group_div()"><i class="fa fa-pencil fa-fw"></i>&nbsp; '.$LANG['item_menu_edi_rep'].'</a>
            <a class="dropdown-item" href="#" onclick="open_move_group_div()"><i class="fa fa-arrows fa-fw"></i>&nbsp; '.$LANG['item_menu_mov_rep'].'</a>
            <a class="dropdown-item" href="#" onclick="open_del_group_div()"><i class="fa fa-eraser fa-fw"></i>&nbsp; '.$LANG['item_menu_del_rep'].'</a>
            <a class="dropdown-item" href="#" onclick="$(\'#div_copy_folder\').dialog(\'open\');"><i class="fa fa-copy fa-fw"></i>&nbsp; '.$LANG['copy_folder'].'</a>
            ', isset($_SESSION['settings']['allow_import']) && $_SESSION['settings']['allow_import'] == 1 && $_SESSION['user_admin'] != 1 ? '
            <a class="dropdown-item" href="#" onclick="loadImportDialog()"><i class="fa fa-cloud-upload fa-fw"></i>&nbsp; '.$LANG['import_csv_menu_title'].'</a>' : '' ,
            (isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] == 1 && $_SESSION['user_admin'] != 1 && $_SESSION['temporary']['user_can_printout'] == true) ? '
            <a class="dropdown-item" href="#" onclick="loadExportDialog()"><i class="fa fa-cloud-download fa-fw"></i>&nbsp; '.$LANG['print_out_menu_title'].'</a>' : '' ,
            (isset($_SESSION['settings']['settings_offline_mode']) && $_SESSION['settings']['settings_offline_mode'] == 1 && $_SESSION['user_admin'] != 1) ? '
            <a class="dropdown-item" href="#" onclick="loadOfflineDialog()"><i class="fa fa-laptop fa-fw"></i>&nbsp; '.$LANG['offline_menu_title'].'</a>' : '' , '
          </div>
        </div>
      </div>
      <div style="float:right; width:150px;">
        <div class="input-group input-group-sm">
          <span class="input-group-addon"><i class="fa fa-binoculars fa-fw"></i></span>
          <input id="jstree_search" class="form-control search_tree" type="text" onkeypress="javascript:if (event.keyCode == 13) globalItemsSearch();" id="search_item" />
        </div>
      </div>
    </div>

    <div id="sidebar" class="sidebar">
      <div id="jstree" style="overflow:auto;"></div>
    </div>
  </div>
  <div class="col-md-8 text-left" id="item_right_side">
    <div id="items_list_div">
      <div style="width:100%;">

        <div style="float:left;">
          <a class="btn btn-default" href="#" role="button" id="menu_button_add_item" onclick="open_add_item_div()">
            <span class="fa fa-plus fa-fw"></span>&nbsp; '.$LANG['item_menu_add_elem'].'
          </a>
        </div>

        <div class="btn-group hidden" role="group" id="button-items-multi-selection" style="float:left;">
          &nbsp;
          <button id="btnGroupDrop2" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <span class="fa fa-bars fa-1x"></span>
          </button>
          <div class="dropdown-menu" aria-labelledby="btnGroupDrop2">
            <a class="dropdown-item" href="#" role="button" id="menu_button_move_items" onclick="">
              <span class="fa fa-arrows"></span>&nbsp; '.$LANG['move_items'].'
            </a>
            <a class="dropdown-item" href="#" role="button" id="menu_button_delete_items" onclick="">
              <span class="fa fa-trash"></span>&nbsp; '.$LANG['delete_items'].'
            </a>
          </div>
        </div>


        <div style="float:right;">
          <nav aria-label="Items Page navigation" id="items_pages_navigation"></nav>
        </div>
      </div>

      <div id="area_items_list" style="float:left; width:100%;">
        <div id="items_path_var" class="space-top"></div>

        <div id="items_list1" class="pointer" style="overflow-y:auto;"></div>
      </div>
    </div>';

// Zone ITEM DETAIL
echo '
    <div id="item_detail_div" style="display:none;">';

// hidden item fields
echo '
      <input type="hidden" class="item_field_data" id="id_categorie" value="" />
      <input type="hidden" class="item_field_data" id="id_item" value="" />
      <input type="hidden" class="item_field_data" id="hid_anyone_can_modify" value="" />
      <input type="hidden" class="item_field_data" id="hid_label" value="', isset($dataItem) ? htmlspecialchars($dataItem['label']) : '', '" />
      <input type="hidden" class="item_field_data" id="hid_tags" />
      <input type="hidden" class="item_field_data" id="hid_desc" value="', isset($dataItem) ? htmlspecialchars($dataItem['description']) : '', '" />
      <input type="hidden" class="item_field_data" id="hid_pw" value="" />
      <input type="hidden" class="item_field_data" id="pw_shown" value="0" />
      <input type="hidden" class="item_field_data" id="hid_login" value="" />
      <input type="hidden" class="item_field_data" id="hid_email" value="" />
      <input type="hidden" class="item_field_data" id="hid_url" value="" />
      <input type="hidden" class="item_field_data" id="hid_files" />
      <input type="hidden" class="item_field_data" id="hid_restricted_to" />
      <input type="hidden" class="item_field_data" id="hid_restricted_to_roles" />
      <input type="hidden" class="item_field_data" id="hid_kbs" />
      <input type="hidden" class="item_field_data" id="next_item" value="" />
      <input type="hidden" class="item_field_data" id="previous_item" value="" />';


echo '
      <div style="width:100%;">
        <div class="btn-toolbar" role="toolbar" aria-label="Items buttons bar">
          <div class="btn-group mr-1" role="group" aria-label="Back">
            <button type="button" class="btn btn-secondary" onclick="showItemElement(\'items_list_div\')">
              <span class="fa fa-reply fa-1x"></span>
            </button>
          </div>
          <div class="btn-group mr-2" role="group" aria-label="Item actions">
            <button type="button" class="btn btn-secondary" onclick="showItemElement(\'item_form_div\')" title="'.$LANG['item_menu_edi_elem'].'" data-placement="bottom">
              <span class="fa fa-pencil fa-1x"></span>
            </button>
            <button type="button" class="btn btn-secondary" onclick="open_del_item_div()" title="'.$LANG['item_menu_edi_elem'].'" data-placement="bottom">
              <span class="fa fa-eraser fa-1x"></span>
            </button>
            <button type="button" class="btn btn-secondary" onclick="open_copy_item_to_folder_div()" title="'.$LANG['item_menu_copy_elem'].'" data-placement="bottom">
              <span class="fa fa-copy fa-1x"></span>
            </button>
          </div>
          <div class="btn-group mr-3" role="group" aria-label="Item actions dropdown">
            <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <span class="fa fa-bars fa-1x"></span>
            </button>
            <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
              <a class="dropdown-item" href="#" id="menu_button_copy_pw" class="copy_clipboard"><i class="fa fa-lock fa-fw"></i>&nbsp; '.$LANG['pw_copy_clipboard'].'</a>
              <a class="dropdown-item" href="#" id="menu_button_copy_login" class="copy_clipboard"><i class="fa fa-user fa-fw"></i>&nbsp; '.$LANG['login_copy'].'</a>
              <a class="dropdown-item" href="#" id="menu_button_show_pw" onclick="ShowPassword()"><i class="fa fa-eye fa-fw"></i>&nbsp; '.$LANG['mask_pw'].'</a>
              <a class="dropdown-item" href="#" id="menu_button_copy_link" class="copy_clipboard"><i class="fa fa-link fa-fw"></i>&nbsp; '.$LANG['url_copy'].'</a>
              <a class="dropdown-item" href="#" id="menu_button_history" onclick="OpenDialog(\'div_item_history\', \'false\')"><i class="fa fa-history fa-fw"></i>&nbsp; '.$LANG['history'].'</a>
              <a class="dropdown-item" href="#" id="menu_button_share" onclick="OpenDialog(\'div_item_share\', \'false\')"><i class="fa fa-share fa-fw"></i>&nbsp; '.$LANG['share'].'</a>',
              (isset($_SESSION['settings']['otv_is_enabled']) && $_SESSION['settings']['otv_is_enabled'] == 1) ? '<a class="dropdown-item" href="#" id="menu_button_otv" onclick="prepareOneTimeView()"><i class="fa fa-users fa-fw"></i>&nbsp; '.$LANG['one_time_item_view'].'</a>' : '', '
              ', isset($_SESSION['settings']['enable_email_notification_on_item_shown']) && $_SESSION['settings']['enable_email_notification_on_item_shown'] == 1 ? '
              <a class="dropdown-item" href="#" id="menu_button_notify"><i class="fa fa-volume-up fa-fw"></i>&nbsp; '.$LANG['notify_me_on_change'].'</a>' : '', '
              ', isset($_SESSION['settings']['enable_server_password_change']) && $_SESSION['settings']['enable_server_password_change'] == 1 && isset($_SESSION['user_read_only']) && $_SESSION['user_read_only'] !== "1"? '
              <a class="dropdown-item" href="#" onclick="serverAutoChangePwd()"><i class="fa fa-server fa-fw"></i>&nbsp; '.$LANG['update_server_password'].'</a>' : '', '
              ', isset($_SESSION['settings']['enable_suggestion']) && $_SESSION['settings']['enable_suggestion'] == 1 ? '
              <a class="dropdown-item" href="#" onclick="OpenDialog(\'div_suggest_change\', \'false\')"><i class="fa fa-random fa-fw"></i>&nbsp; '.$LANG['suggest_password_change'].'</a>' : '', '
            </div>
          </div>
          <div class="btn-group mr-4" role="group" aria-label="Item navigation">
            <button type="button" class="btn btn-secondary" onclick="quickShowItem(\'previous_item\')" id="but_previous_item">
              <span class="fa fa-arrow-left fa-1x"></span>
            </button>
            <button type="button" class="btn btn-secondary" onclick="quickShowItem(\'next_item\')" id="but_next_item">
              <span class="fa fa-arrow-right fa-1x"></span>
            </button>
          </div>
        </div>
      </div>';

// Line for LABEL
echo '
      <div class="tp-row-opacity opacity-40"></div>
      <div class="tp-row-text">
        <p class="overflow-ellipsis item_field" id="id_label"></p>
      </div>';

echo'
      <div id="item_details_expired" style="display:none;background-color:white; margin-top: 40px;">
        <div class="ui-state-error ui-corner-all" style="padding:2px;">
          <i class="fa fa-warning"></i>&nbsp;<b>'.$LANG['pw_is_expired_-_update_it'].'</b>
        </div>
      </div>';

// line for badges
echo '
    <div style="width:100%; margin-top:44px;" class="space-bottom space-top">
      <div style="float:left; width:50%;">
        <button type="button" class="btn btn-primary btn-sm tip" id="button_quick_pw_copy" title="'.$LANG['item_menu_copy_pw'].'" data-toggle="tooltip">
          <span class="fa fa-hashtag fa-sm"></span>
        </button>
        <button type="button" class="btn btn-primary btn-sm tip" id="button_quick_login_copy" title="'.$LANG['item_menu_copy_login'].'" data-toggle="tooltip">
          <span class="fa fa-user fa-sm"></span>
        </button>
      </div>
      <div style="margin-right:10px; float:right;">
        <h6>
          <span class="label label-primary item_field" id="item_viewed_x_times"></span>&nbsp;
          <div class="" style="float:right;margin-right:5px;" id="item_extra_info"></div>
          <div id="id_label2" style="display:inline; margin:4px 0px 0px 120px; "></div>
        </h6>
      </div>
    </div>';

echo '
      <table width="100%" class="table-pres">';

// Line for DESCRIPTION
echo '
        <tr>
          <td valign="top" class="td_title" width="180px">&nbsp;<i class="width-20 fa fa-book"></i>&nbsp;'.$LANG['description'].' :</td>
          <td>
            <div id="id_desc" style="font-style:italic;display:inline;" class="item_field"></div>
          </td>
        </tr>';
// Line for PW
echo '
        <tr>
          <td valign="top" class="td_title">&nbsp;<i class="width-20 fa fa-hashtag"></i>&nbsp;'.$LANG['pw'].' :

          </td>
          <td>
            <button type="button" class="btn btn-primary btn-sm show-password">
              <span class="fa fa-eye fa-sm tip" title="'.$LANG['show_password'].'" data-toggle="tooltip"></span>
            </button>
            &nbsp;&nbsp;
            <span id="id_pw" style="display:inline;cursor:pointer;" class="item_field"></span>
          </td>
        </tr>';
// Line for LOGIN
echo '
        <tr>
          <td valign="top" class="td_title">&nbsp;<i class="width-20 fa fa-user"></i>&nbsp;'.$LANG['index_login'].' :</td>
          <td>
            <span id="id_login" style="display:inline;" class="item_field"></span>
          </td>
        </tr>';
// Line for EMAIL
echo '
        <tr>
          <td valign="top" class="td_title">&nbsp;<i class="width-20 fa fa-envelope"></i>&nbsp;'.$LANG['email'].' :</td>
          <td>
            <span id="id_email" style="display:inline;" class="item_field"></span>
          </td>
        </tr>';
// Line for URL
echo '
        <tr>
          <td valign="top" class="td_title">&nbsp;<i class="width-20 fa fa-globe"></i>&nbsp;'.$LANG['url'].' :</td>
          <td>
            <div id="id_url" style="display:inline;" class="item_field"></div>
          </td>
        </tr>';
// Line for FILES
echo '
        <tr>
          <td valign="top" class="td_title">&nbsp;<i class="width-20 fa fa-file"></i>&nbsp;'.$LANG['files_&_images'].' :</td>
          <td>
            <div id="id_files" style="display:inline;font-size:11px;" class="item_field"></div>
            <div id="dialog_files" style="display: none;"></div>
          </td>
        </tr>';
// Line for RESTRICTED TO
echo '
        <tr>
          <td valign="top" class="td_title">&nbsp;<i class="width-20 fa fa-user-secret"></i>&nbsp;'.$LANG['restricted_to'].' :</td>
          <td>
            <div id="id_restricted_to" style="display:inline;" class="item_field"></div>
          </td>
        </tr>';
// Line for KBs
if (isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1) {
  echo '
        <tr>
          <td valign="top" class="td_title">&nbsp;<i class="width-20 fa fa-map-signs"></i>&nbsp;'.$LANG['kbs'].' :</td>
          <td>
            <div id="id_kbs" style="display:inline;" class="item_field"></div>
          </td>
        </tr>';
}
// lines for FIELDS
if (isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1) {
foreach ($_SESSION['item_fields'] as $elem) {
$itemCatName = $elem[0];
echo '
        <tr class="tr_fields itemCatName_'.$itemCatName.'">
          <td valign="top" class="td_title">&nbsp;<i class="width-20 fa fa-folder-open"></i>&nbsp;'.$elem[1].' :</td>
          <td></td>
        </tr>';
foreach ($elem[2] as $field) {
      echo '
        <tr class="tr_fields itemCatName_'.$itemCatName.'">
          <td valign="top" class="td_title"><i class="fa fa-caret-right" style="margin-left:30px;"></i>&nbsp;<i>'.$field[1].'</i> :</td>
          <td>
            <div id="id_field_'.$field[0].'" style="display:inline;" class="fields_div"></div><input type="hidden" id="hid_field_'.htmlspecialchars($field[0]).'" class="fields" />
          </td>
        </tr>';
    }
  }
}
echo '
      </table>';

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

// tags
echo '
      <div class="item-tag-bottom">
        <div id="id_tags" class="item_field"></div>
      </div>';

echo '
    </div>';

    /************* ITEM FORM ************************/
echo '
    <div id="item_form_div" style="display:none;">
      <div class="btn-toolbar" role="toolbar" aria-label="Item edit buttons bar">
        <div class="btn-group mr-1" role="group" aria-label="Back">
          <button type="button" class="btn btn-default" onclick="showItemElement(\'item_detail_div\')">
            <span class="fa fa-reply fa-1x"></span>
          </button>
        </div>
        <div class="btn-group mr-2" role="group" aria-label="Back">
          <button type="button" class="btn btn-default" onclick="saveItemDefinition()">
            <span class="fa fa-floppy-o fa-1x"></span>&nbsp;'.$LANG['save_button'].'
          </button>
        </div>
      </div>';

// only for item edition
echo '
      <div id="item_form_top_info_edit" class="hidden">
        <input type="hidden" id="item-definition-conform" value="1" />
        <div id="edit_afficher_visibilite" style="text-align:center;margin-bottom:6px;height:25px;"></div>
        <!--<div id="edit_display_title" style="text-align:center;margin-bottom:6px;font-size:17px;font-weight:bold;height:25px;"></div>-->
        <div class="tp-top2 opacity-40"></div>
        <div class="tp-top2-text">
          <p class="overflow-ellipsis item_field" id="edit_display_title"></p>
        </div>
      </div>';

echo '
      <div class="card" style="margin-top:40px;">
        <div class="card-header">
          <ul class="nav nav-tabs card-header-tabs pull-xs-left" role="tablist" id="edit_item_navigator">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="tab" href="#item-definition" role="tab">'.$LANG['definition'].'</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="tab" href="#item-passwd" role="tab">'.$LANG['index_password'].' &amp; '.$LANG['visibility'].'</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="tab" href="#item-files" role="tab">'.$LANG['files_&_images'].'</a>
            </li>
            ', isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1 ?
            '<li class="nav-item">
              <a class="nav-link" data-toggle="tab" href="#item-extra" role="tab">'.$LANG['more'].'</a>
            </li>' :
            '', '
          </ul>
        </div>';

echo '
        <div class="tab-content">
          <div class="card-block tab-pane active" id="item-definition" role="tabpanel">
            <form>

              <div class="form-group row">
                <label for="item-label-input" class="col-2 col-form-label">'.$LANG['label'].'</label>
                <div class="col-10">
                  <input class="form-control form-control-sm item-input-form" type="text" value="" id="item-label-input" onchange="checkTitleDuplicate(this.value, \'', isset($_SESSION['settings']['item_duplicate_in_same_folder']) && $_SESSION['settings']['item_duplicate_in_same_folder'] == 1 ? 0 : 1, '\', \'', isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1 ? 0 : 1, '\', \'edit_display_title\')">
                </div>
              </div>

              <div class="form-group row">
                <label for="item-description-input" class="col-2 col-form-label">'.$LANG['description'].'&nbsp;<span class="fa fa-eraser" style="cursor:pointer;" onclick="clear_html_tags()"></span></label>
                <div class="col-10">
                  <textarea class="form-control item-input-form" rows="5" cols="70" id="item-description-input"></textarea>
                </div>
              </div>

              <div class="form-group row">
                <label for="item-folder-input" class="col-2 col-form-label">'.$LANG['group'].'</label>
                <div class="col-10">
                  <select class="form-control form-control-sm full-width" id="item-folder-input" style="width: 100%" onchange="RecupComplexite(this.value,1)"></select>
                </div>
              </div>

              <div class="form-group row">
                <label for="item-login-input" class="col-2 col-form-label">'.$LANG['login'].'</label>
                <div class="col-10">
                  <input class="form-control form-control-sm item-input-form" type="text" value="" id="item-login-input">
                </div>
              </div>

              <div class="form-group row">
                <label for="item-email-input" class="col-2 col-form-label">'.$LANG['email'].'</label>
                <div class="col-10">
                  <input class="form-control form-control-sm item-input-form form-control-warning email-check" type="email" value="" id="item-email-input">
                </div>
              </div>

              <div class="form-group row">
                <label for="item-url-input" class="col-2 col-form-label">'.$LANG['url'].'</label>
                <div class="col-10">
                  <input class="form-control form-control-sm form-control-warning item-input-form" type="url" value="" id="item-url-input">
                </div>
              </div>

            </form>
          </div>

          <div class="card-block tab-pane" id="item-passwd" role="tabpanel">
            <form>
              <div class="card">
                <h5 class="card-header">'.$LANG['index_password'].'</h5>
                <div class="card-block">
                  <h6 class="card-title">'.$LANG['complex_asked'].'&nbsp;<span id="edit_complex_attendue" style="color:#D04806;"></span></h6>
                  <p class="card-text">
                    <div class="form-group row">
                      <label for="item-pwd-input" class="col-2 col-form-label">'.$LANG['used_pw'].'&nbsp;<span id="edit_pw_wait" style="display:none;margin-left:10px;"><span class="fa fa-cog fa-spin fa-1x"></span></span></label>
                      <div class="col-8">
                        <input class="form-control form-control-sm form-control-warning item-input-form" type="password" value="" id="item-pwd-input">
                        <p id="edit_visible_pw" class="form-text text-muted">
                          <span style="display:none;font-weight:bold;"></span>
                        </p>
                      </div>
                      <div class="col-2">
                        <span class="fa fa-clipboard tip" style="cursor:pointer;" id="edit_past_pwds"></span>
                      </div>
                      <input type="hidden" id="item-pwdcomplexity-input" />
                    </div>

                    <div class="form-group row">
                      <label for="item-pwdconfirm-input" class="col-2 col-form-label">'.$LANG['confirm'].'</label>
                      <div class="col-8">
                        <input class="form-control form-control-sm form-control-warning item-input-form" type="password" value="" id="item-pwdconfirm-input">
                      </div>
                    </div>

                    <div class="form-group row text-center">
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
                      <div style="width:100%;">
                        <div id="edit_pw_strength" style="margin:5px 0 5px 120px;"></div>
                      </div>
                    </div>
                  </p>
                </div>
              </div>

              <div class="card">
                <h5 class="card-header">'.$LANG['visibility'].'</h5>
                <div class="card-block">';
if (isset($_SESSION['settings']['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1) {
  echo '
                  <div class="form-group row" id="div_editRestricted">
                    <label for="edit_restricted_to_list" class="col-2 col-form-label">'.$LANG['restricted_to'].' : </label>
                    <div class="col-10">
                      <select id="edit_restricted_to_list" multiple="multiple" style="width:100%"></select>
                      <input type="hidden" size="50" name="edit_restricted_to" id="edit_restricted_to" />
                      <input type="hidden" size="50" name="edit_restricted_to_roles" id="edit_restricted_to_roles" />
                    </div>
                  </div>';
}

echo '
                  <div class="form-group row">
                    <label for="edit_restricted_to_list" class="col-2 col-form-label">'.$LANG['restricted_to'].' : </label>
                    <div class="col-10">

                    </div>
                  </div>

                  <div class="form-group row">
                    <label for="edit_restricted_to_list" class="col-2 col-form-label">'.$LANG['restricted_to'].' : </label>
                    <div class="col-10">

                    </div>
                  </div>

                  <div class="form-group row">
                    <label for="edit_restricted_to_list" class="col-2 col-form-label">'.$LANG['restricted_to'].' : </label>
                    <div class="col-10">

                    </div>
                  </div>

                  <div class="form-group row">
                    <label for="edit_restricted_to_list" class="col-2 col-form-label">'.$LANG['restricted_to'].' : </label>
                    <div class="col-10">

                    </div>
                  </div>

                  <div class="form-group row">
                    <label for="edit_restricted_to_list" class="col-2 col-form-label">'.$LANG['restricted_to'].' : </label>
                    <div class="col-10">

                    </div>
                  </div>
                </div>
              </div>';



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
              <input type="checkbox" name="edit_annonce" id="edit_annonce" onchange="toggleDiv(\'edit_annonce_liste\')" />
              <label for="edit_annonce">'.$LANG['email_announce'].'</label>
              <div style="display:none; border:1px solid #808080; margin-left:30px; margin-top:3px;padding:5px;" id="edit_annonce_liste">
                <h3>'.$LANG['email_select'].'</h3>
                <select id="edit_annonce_liste_destinataires" multiple="multiple" size="10">';
        foreach ($usersList as $user) {
          echo '<option value="'.$user['email'].'">'.$user['login'].'</option>';
        }
        echo '
                </select>
              </div>
            </form>
          </div>

          <div class="tab-pane" id="item-files" role="tabpanel">
            <form class="space-top">

            </form>
          </div>

          <div class="tab-pane" id="item-extra" role="tabpanel">
            <form class="space-top">

            </form>
          </div>
        </div>
      </div>';


echo '
    </div>';

echo '
  </div>
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
      <input type="checkbox" name="edit_annonce" id="edit_annonce" onchange="toggleDiv(\'edit_annonce_liste\')" />
      <label for="edit_annonce">'.$LANG['email_announce'].'</label>
      <div style="display:none; border:1px solid #808080; margin-left:30px; margin-top:3px;padding:5px;" id="edit_annonce_liste">
        <h3>'.$LANG['email_select'].'</h3>
        <select id="edit_annonce_liste_destinataires" multiple="multiple" size="10">';
foreach ($usersList as $user) {
  echo '<option value="'.$user['email'].'">'.$user['login'].'</option>';
}
echo '
        </select>
      </div>
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
      <input type="checkbox" name="annonce" id="annonce" onchange="toggleDiv(\'annonce_liste\')" />
      <label for="annonce">'.$LANG['email_announce'].'</label>
      <div style="display:none; border:1px solid #808080; margin-left:30px; margin-top:6px;padding:5px;" id="annonce_liste">
        <h3>'.$LANG['email_select'].'</h3>
        <select id="annonce_liste_destinataires" multiple="multiple" size="10">';
foreach ($usersList as $user) {
  echo '<option value="'.$user['email'].'">'.$user['login'].'</option>';
}
echo '
        </select>
      </div>
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
        ', (isset($_SESSION['settings']['can_create_root_folder']) && $_SESSION['settings']['can_create_root_folder'] == 1) ? '<option value="0">'.$LANG['root'].'</option>' : '', '
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

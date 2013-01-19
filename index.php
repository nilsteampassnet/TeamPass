<?php
session_start();
@openlog("TeamPass", LOG_PID | LOG_PERROR, LOG_LOCAL0);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<?php
/**
 *
 * @file          index.php
 * @author        Nils Laumaillé
 * @version       2.1.13
 * @copyright     (c) 2009-2012 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

$_SESSION['CPM'] = 1;
session_id();
// Test if settings.file exists, if not then install
if (!file_exists('includes/settings.php')) {
    echo '
    <script language="javascript" type="text/javascript">
    <!--
    document.location.replace("install/install.php");
    clearInterval(timer);
    -->
    </script>';
}

if (!isset($_SESSION['settings']['cpassman_dir']) || $_SESSION['settings']['cpassman_dir'] == "") {
    $_SESSION['settings']['cpassman_dir'] = ".";
}

// Include files
require_once $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
require_once $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// connect to the server
$db = new SplClassLoader('Database\Core', './includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

//load main functions needed
require_once 'sources/main.functions.php';

/* DEFINE WHAT LANGUAGE TO USE */
if (!isset($_SESSION['user_id']) && !isset($_POST['language'])) {
    //get default language
    $data_language = $db->fetchRow("SELECT valeur FROM ".$pre."misc WHERE type = 'admin' AND intitule = 'default_language'");
    if (empty($data_language[0])) {
        $_SESSION['user_language'] = "english";
        $_SESSION['user_language_flag'] = "us.png";
    } else {
        $_SESSION['user_language'] = $data_language[0];
        $_SESSION['user_language_flag'] = "us.png";
    }
} elseif (isset($_SESSION['settings']['default_language']) && !isset($_SESSION['user_language'])) {
    $_SESSION['user_language'] = $_SESSION['settings']['default_language'];
} elseif (isset($_POST['language'])) {
    $_SESSION['user_language'] = filter_var($_POST['language'], FILTER_SANITIZE_STRING);
} elseif (!isset($_SESSION['user_language']) || empty($_SESSION['user_language'])) {
    if (isset($_POST['language'])) {
        $_SESSION['user_language'] = filter_var($_POST['language'], FILTER_SANITIZE_STRING);
    } elseif (isset($_SESSION['settings']['default_language'])) {
        $_SESSION['user_language'] = $_SESSION['settings']['default_language'];
    }
}
// Load user languages files
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
if (isset($_GET['page']) && $_GET['page'] == "kb") {
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'_kb.php';
}
// Load CORE
require_once $_SESSION['settings']['cpassman_dir'].'/sources/core.php';
// Load links, css and javascripts
@require_once $_SESSION['settings']['cpassman_dir'].'/load.php';
?>

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
        <title>Collaborative Passwords Manager</title>
<?php
echo $htmlHeaders;
?>
    </head>

    <body onload="countdown()">
    <?php

/* HEADER */
echo '
    <div id="top">
        <div id="logo"><img src="includes/images/canevas/logo.png" alt="" /></div>';
// Display menu
if (isset($_SESSION['login'])) {
    echo '
        <div id="menu_top">
            <div style="font-size:12px; margin-left:65px; margin-top:-5px; width:100%; color:white;">
                <img src="includes/images/user-black.png" /> <b>'.$_SESSION['login'].'</b> ['.$_SESSION['user_privilege'].']<img src="includes/images/alarm-clock.png" style="margin-left:30px;" /> '.$txt['index_expiration_in'].' <div style="display:inline;" id="countdown"></div>
            </div>
            <div style="margin-left:65px; margin-top:3px;width:100%;" id="main_menu">
                <button title="'.$txt['home'].'" onclick="MenuAction(\'\');">
                    <img src="includes/images/home.png" alt="" />
                </button>';
    if ($_SESSION['user_admin'] == 0 || $k['admin_full_right'] == 0) {
        echo '
                <button style="margin-left:10px;" title="'.$txt['pw'].'" onclick="MenuAction(\'items\');"', (isset($_SESSION['nb_folders']) && $_SESSION['nb_folders'] == 0) || (isset($_SESSION['nb_roles']) && $_SESSION['nb_roles'] == 0) ? ' disabled="disabled"' : '', '>
                    <img src="includes/images/menu_key.png" alt="" />
                </button>
                <button title="'.$txt['find'].'" onclick="MenuAction(\'find\');"', (isset($_SESSION['nb_folders']) && $_SESSION['nb_folders'] == 0) || (isset($_SESSION['nb_roles']) && $_SESSION['nb_roles'] == 0) ? ' disabled="disabled"' : '', '>
                    <img src="includes/images/binocular.png" alt="" />
                </button>
                <button title="'.$txt['last_items_icon_title'].'" onclick="OpenDiv(\'div_last_items\')">
                    <img src="includes/images/tag_blue.png" alt="" />
                </button>';
    }
    // Favourites menu
    if (isset($_SESSION['settings']['enable_favourites']) && $_SESSION['settings']['enable_favourites'] == 1 && $_SESSION['user_admin'] == 0) {
        echo '
                <button title="'.$txt['my_favourites'].'" onclick="MenuAction(\'favourites\');">
                    <img src="includes/images/favourite.png" alt="" />
                </button>';
    }
    // KB menu
    if (isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1) {
        echo '
                    <button style="margin-left:10px;" title="'.$txt['kb_menu'].'" onclick="MenuAction(\'kb\');">
                        <img src="includes/images/direction.png" alt="" />
                    </button>';
    }
    // Admin menu
    if ($_SESSION['user_admin'] == 1) {
        echo '
                <button style="margin-left:10px;" title="'.$txt['admin_main'].'" onclick="MenuAction(\'manage_main\');">
                    <img src="includes/images/menu_informations.png" alt="" />
                </button>
                <button title="'.$txt['admin_settings'].'" onclick="MenuAction(\'manage_settings\');">
                    <img src="includes/images/menu_settings.png" alt="" />
                </button>';
    }

    if ($_SESSION['user_admin'] == 1 || $_SESSION['user_manager'] == 1) {
        echo '
                <button title="'.$txt['admin_groups'].'" onclick="MenuAction(\'manage_folders\');">
                    <img src="includes/images/menu_groups.png" alt="" />
                </button>
                <button title="'.$txt['admin_functions'].'" onclick="MenuAction(\'manage_roles\');">
                    <img src="includes/images/menu_functions.png" alt="" />
                </button>
                <button title="'.$txt['admin_users'].'" onclick="MenuAction(\'manage_users\');">
                    <img src="includes/images/menu_user.png" alt="" />
                </button>
                <button title="'.$txt['admin_views'].'" onclick="MenuAction(\'manage_views\');">
                    <img src="includes/images/menu_views.png" alt="" />
                </button>';
    }
    // 1 hour
    echo '
                <button style="margin-left:10px;" title="'.$txt['index_add_one_hour'].'" onclick="IncreaseSessionTime();">
                    <img src="includes/images/clock__plus.png" alt="" />
                </button>';
    // Disconnect menu
    echo '
                <button title="'.$txt['disconnect'].'" onclick="MenuAction(\'deconnexion\');">
                    <img src="includes/images/door-open.png" alt="" />
                </button>
            </div>
        </div>';
}
// Display language menu
echo '
        <div style="float:right;">
            <dl id="flags" class="dropdown">
                <dt><img src="includes/images/flags/'.$_SESSION['user_language_flag'].'" alt="" /></dt>
                <dd>
                    <ul>
                    '.$languages_dropmenu.'
                    </ul>
                </dd>
            </dl>
        </div>
    </div>';

/* LAST SEEN */
echo '
    <div style="display:none;" id="div_last_items" class="ui-state-active ui-corner-all">
        '.$txt['last_items_title'].":&nbsp;";
if (isset($_SESSION['latest_items_tab'])) {
    foreach ($_SESSION['latest_items_tab'] as $item) {
        if (!empty($item)) {
            echo '
                    <span class="last_seen_item" onclick="javascript:$(\'#menu_action\').val(\'action\');window.location.href = \''.$item['url'].'\'"><img src="includes/images/tag-small.png" alt="" /><span id="last_items_'.$item['id'].'">'.stripslashes($item['label']).'</span></span>';
        }
    }
} else {
    echo $txt['no_last_items'];
}
echo '
    </div>';

/* MAIN PAGE */
echo '
    <form name="temp_form" method="post" action="">
        <input type="text" style="display:none;" id="temps_restant" value="', isset($_SESSION['fin_session']) ? $_SESSION['fin_session'] : '', '" />
        <input type="hidden" name="language" id="language" value="" />
        <input type="hidden" name="user_pw_complexity" id="user_pw_complexity" value="'.@$_SESSION['user_pw_complexity'].'" />
    </form>';

/* INSERT ITEM BUTTONS IN MENU BAR */
if (isset($_SESSION['autoriser']) && $_SESSION['autoriser'] == true && isset($_GET['page']) && $_GET['page'] == "items") {
    echo '
        <div style="" class="ui-corner-right" id="div_right_menu">
            <button title="'.$txt['item_menu_refresh'].'" id="menu_button_refresh_page" style="margin-bottom:5px;" onclick="javascript:document.new_item.submit()">
                <img src="includes/images/refresh.png" alt="" />
            </button>
            <br />',
    (
        (isset($_SESSION['user_admin']) && $_SESSION['user_admin'] == 1) ||
        (isset($_SESSION['user_manager']) && $_SESSION['user_manager'] == 1) ||
        (isset($_SESSION['settings']['enable_user_can_create_folders']) && $_SESSION['settings']['enable_user_can_create_folders'] == 1)
      ) ? '
            <button title="'.$txt['item_menu_add_rep'].'" id="menu_button_add_group" onclick="open_add_group_div()">
                <img src="includes/images/folder__plus.png" alt="" />
            </button>
            <br />
            <button title="'.$txt['item_menu_edi_rep'].'" id="menu_button_edit_group" onclick="open_edit_group_div()">
                <img src="includes/images/folder__pencil.png" alt="" />
            </button>
            <br />
            <button title="'.$txt['item_menu_del_rep'].'" id="menu_button_del_group" style="margin-bottom:5px;" onclick="open_del_group_div()">
                <img src="includes/images/folder__minus.png" alt="" />
            </button>
            <br />' : '', '
            <button title="'.$txt['item_menu_add_elem'].'" id="menu_button_add_item" onclick="open_add_item_div()"><img src="includes/images/key__plus.png" alt="" /></button>
            <br />
            <button title="'.$txt['item_menu_edi_elem'].'" id="menu_button_edit_item" onclick="open_edit_item_div(', isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1 ? 1 : 0 , ')"><img src="includes/images/key__pencil.png" alt="" /></button>
            <br />
            <button title="'.$txt['item_menu_del_elem'].'" id="menu_button_del_item" onclick="open_del_item_div()"><img src="includes/images/key__minus.png" alt="" /></button>
            <br />
            <button title="'.$txt['item_menu_copy_elem'].'" id="menu_button_copy_item" onclick="open_copy_item_to_folder_div()" style="margin-bottom:5px;"><img src="includes/images/key_copy.png" alt="" /></button>
            <br />
            <button title="'.$txt['pw_copy_clipboard'].'" id="menu_button_copy_pw" class="copy_clipboard"><img src="includes/images/ui-text-field-password.png" id="div_copy_pw" alt="" /></button>
            <br />
            <button title="'.$txt['login_copy'].'" style="margin-bottom:5px;" id="menu_button_copy_login" class="copy_clipboard"><img src="includes/images/ui-text-field.png" id="div_copy_login" alt="" /></button>
            <br />
            <button title="'.$txt['mask_pw'].'" style="margin-bottom:5px;" id="menu_button_show_pw" onclick="ShowPassword()"><img src="includes/images/eye.png" alt="" /></button>
            <br />
            <button title="'.$txt['link_copy'].'" style="margin-bottom:5px;" id="menu_button_copy_link" class="copy_clipboard"><img src="includes/images/target.png" id="div_copy_link" alt="" /></button>
            <br />
            <button title="'.$txt['history'].'" id="menu_button_history" class="" onclick="OpenDialog(\'div_item_history\', \'false\')"><img src="includes/images/report.png" id="div_history" alt="" /></button>
            <br />
            <button title="'.$txt['share'].'" id="menu_button_share" class="" onclick="OpenDialog(\'div_item_share\', \'false\')"><img src="includes/images/share.png" id="div_share" alt="" /></button>';
    if (isset($_SESSION['settings']['enable_email_notification_on_item_shown']) && $_SESSION['settings']['enable_email_notification_on_item_shown'] == 1) {
        echo '
            <br />
            <button style="margin-bottom:5px;" id="menu_button_notify" class=""><img src="includes/images/alarm-clock.png" id="div_notify" alt="" /></button>';
    }
    echo '
        </div>';
}

echo '
    <div id="', isset($_GET['page']) && $_GET['page'] == "items" ? "main_simple" : "main", '">';
// MESSAGE BOX
echo '
        <div style="" class="div_center">
            <div id="message_box" style="display:none;width:200px;min-height:25px;background-color:#FFC0C0;border:2px solid #FF0000;padding:5px;text-align:center;"></div>
        </div>';
// Main page
if (isset($_SESSION['autoriser']) && $_SESSION['autoriser'] == true) {
    // Show menu
    echo '
        <form method="post" name="main_form" action="">
            <input type="hidden" name="menu_action" id="menu_action" value="" />
            <input type="hidden" name="changer_pw" id="changer_pw" value="" />
            <input type="hidden" name="form_user_id" id="form_user_id" value="', isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '', '" />
            <input type="hidden" name="is_admin" id="is_admin" value="', isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : '', '" />
            <input type="hidden" name="personal_saltkey_set" id="personal_saltkey_set" value="', isset($_SESSION['my_sk']) ? true : false, '" />
        </form>';
}
// ---------
// Display a help to admin
$errorAdmin = "";
// error nb folders
if (isset($_SESSION['nb_folders']) && $_SESSION['nb_folders'] == 0) {
    $errorAdmin = '<span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$txt['error_no_folders'].'<br />';
}
// error nb roles
if (isset($_SESSION['nb_roles']) && $_SESSION['nb_roles'] == 0) {
    if (empty($errorAdmin)) {
        $errorAdmin = '<span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$txt['error_no_roles'];
    } else {
        $errorAdmin .= '<br /><span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$txt['error_no_roles'];
    }
}
// error Salt key
if (isset($_SESSION['error']['salt']) && $_SESSION['error']['salt'] == 1) {
    if (empty($errorAdmin)) {
        $errorAdmin = '<span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$txt['error_salt'];
    } else {
        $errorAdmin .= '<br /><span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$txt['error_salt'];
    }
}

if (isset($_SESSION['validite_pw']) && $_SESSION['validite_pw']) {
    // error cpassman dir
    if (isset($_SESSION['settings']['cpassman_dir']) && empty($_SESSION['settings']['cpassman_dir']) || !isset($_SESSION['settings']['cpassman_dir'])) {
        if (empty($errorAdmin)) {
            $errorAdmin = '<span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$txt['error_cpassman_dir'];
        } else {
            $errorAdmin .= '<br /><span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$txt['error_cpassman_dir'];
        }
    }
    // error cpassman url
    if (isset($_SESSION['validite_pw']) && (isset($_SESSION['settings']['cpassman_url']) && empty($_SESSION['settings']['cpassman_url']) || !isset($_SESSION['settings']['cpassman_url']))) {
        if (empty($errorAdmin)) {
            $errorAdmin = '<span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$txt['error_cpassman_url'];
        } else {
            $errorAdmin .= '<br /><span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$txt['error_cpassman_url'];
        }
    }
}
// Display help
if (!empty($errorAdmin)) {
    echo '
            <div style="margin:10px;padding:10px;" class="ui-state-error ui-corner-all">
            '.$errorAdmin.'
            </div>';
}
// -----------
// Display system errors
if (isset($_SESSION['error']['salt']) && $_SESSION['error']['salt'] == 1) {
    echo '
        <div style="margin:10px;padding:10px;" class="ui-state-error ui-corner-all">
            ', (isset($_SESSION['error']['salt']) && $_SESSION['error']['salt'] == true) ? '<span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;">&nbsp;</span>'.$txt['error_salt'].'' : '', '
        </div>';
}
// Display Maintenance mode information
if (isset($_SESSION['settings']['maintenance_mode']) && $_SESSION['settings']['maintenance_mode'] == 1 && isset($_SESSION['user_admin']) && $_SESSION['user_admin'] == 1) {
    echo '
        <div style="text-align:center;margin-bottom:5px;padding:10px;" class="ui-state-highlight ui-corner-all">
            <b>'.$txt['index_maintenance_mode_admin'].'</b>
        </div>';
}
// Display UPDATE NEEDED information
if (isset($_SESSION['settings']['update_needed']) && $_SESSION['settings']['update_needed'] == true && isset($_SESSION['user_admin']) && $_SESSION['user_admin'] == 1 && ((isset($_SESSION['hide_maintenance']) && $_SESSION['hide_maintenance'] == 0) || !isset($_SESSION['hide_maintenance']))) {
    echo '
        <div style="text-align:center;margin-bottom:5px;padding:10px;" class="ui-state-highlight ui-corner-all" id="div_maintenance">
            <b>'.$txt['update_needed_mode_admin'].'</b><span style="float:right;cursor:pointer;"><img src="includes/images/cross.png" onclick="toggleDiv(\'div_maintenance\')" /></span>
        </div>';
}

// Display pages
if (isset($_SESSION['validite_pw']) && $_SESSION['validite_pw'] == true && !empty($_GET['page'])) {
    if (!extension_loaded('mcrypt')) {
        $_SESSION['error'] = "1003";
        include 'error.php';
    } elseif ($_GET['page'] == "items") {
        // SHow page with Items
        include 'items.php';
    } elseif ($_GET['page'] == "find") {
        // Show page for items findind
        include 'find.php';
    } elseif ($_GET['page'] == "favourites") {
        // Show page for user favourites
        include 'favorites.php';
    } elseif ($_GET['page'] == "kb") {
        // Show page KB
        if (isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1) {
            include 'kb.php';
        } else {
            $_SESSION['error'] = "1000"; //not allowed page
            include 'error.php';
        }
    } elseif (in_array($_GET['page'], array_keys($mngPages))) {
        // Define if user is allowed to see management pages
        if ($_SESSION['user_admin'] == 1) {
            include($mngPages[$_GET['page']]);
        } elseif ($_SESSION['user_manager'] == 1) {
            if (($_GET['page'] != "manage_main" &&  $_GET['page'] != "manage_settings")) {
                include($mngPages[$_GET['page']]);
            } else {
                $_SESSION['error'] = "1000"; //not allowed page
                include 'error.php';
            }
        } else {
            $_SESSION['error'] = "1000"; //not allowed page
            include 'error.php';
        }
    } else {
        $_SESSION['error'] = "1001"; //page don't exists
        include 'error.php';
    }
}
// case where user not logged and can't access a direct link
elseif ((!isset($_SESSION['validite_pw']) || empty($_SESSION['validite_pw'])) && !empty($_GET['page'])) {
    $_SESSION['error'] = "1002";
    $_SESSION['initial_url'] = substr($_SERVER["REQUEST_URI"], strpos($_SERVER["REQUEST_URI"], "index.php?"));
    include 'error.php';
}
// Case where user has asked new PW
elseif (empty($_SESSION['user_id']) && isset($_GET['action']) && $_GET['action'] == "password_recovery") {
    echo '
        <div style="width:400px;margin:50px auto 50px auto;padding:25px;" class="ui-state-highlight ui-corner-all">
            <div style="text-align:center;font-weight:bold;margin-bottom:20px;">
                '.$txt['pw_recovery_asked'].'
            </div>
            <div id="generate_new_pw_error" style="color:red;display:none;text-align:center;margin:5px;"></div>
            <div style="margin-bottom:3px;">
                '.$txt['pw_recovery_info'].'
            </div>
            <div style="margin:15px; text-align:center;">
                <input type="button" id="but_generate_new_password" onclick="GenerateNewPassword(\''.$_GET['key'].'\',\''.$_GET['login'].'\')" style="padding:3px;cursor:pointer;" class="ui-state-default ui-corner-all" value="'.$txt['pw_recovery_button'].'" />
                <br /><br />
                <img id="ajax_loader_send_mail" style="display:none;" src="includes/images/ajax-loader.gif" alt="" />
            </div>
        </div>';
}
// When user identified
elseif (!empty($_SESSION['user_id']) && isset($_SESSION['user_id'])) {
    // PAGE BY DEFAULT
    include 'home.php';
    // When user is not identified
} else {
    // Automatic redirection
    if (strpos($_SERVER["REQUEST_URI"], "?") > 0) {
        $nextUrl = substr($_SERVER["REQUEST_URI"], strpos($_SERVER["REQUEST_URI"], "?"));
    } else {
        $nextUrl = "";
    }
    // MAINTENANCE MODE
    if (isset($_SESSION['settings']['maintenance_mode']) && $_SESSION['settings']['maintenance_mode'] == 1) {
        echo '
            <div style="text-align:center;margin-top:30px;margin-bottom:20px;padding:10px;" class="ui-state-error ui-corner-all">
                <b>'.$txt['index_maintenance_mode'].'</b>
            </div>';
    } else {
        // SESSION FINISHED => RECONNECTION ASKED
        echo '
                <div style="text-align:center;margin-top:30px;margin-bottom:20px;padding:10px;" class="ui-state-error ui-corner-all">
                    <b>'.$txt['index_session_expired'].'</b>
                </div>';
    }
    // CONNECTION FORM
    echo '
            <form method="post" name="form_identify" action="">
                <div style="width:300px; margin-left:auto; margin-right:auto;margin-bottom:50px;padding:25px;" class="ui-state-highlight ui-corner-all">
                    <div style="text-align:center;font-weight:bold;margin-bottom:20px;">',
    isset($_SESSION['settings']['custom_logo']) && !empty($_SESSION['settings']['custom_logo']) ? '<img src="'.$_SESSION['settings']['custom_logo'].'" alt="" style="margin-bottom:40px;" />' : '', '<br />
                        '.$txt['index_get_identified'].'
                        &nbsp;<img id="ajax_loader_connexion" style="display:none;" src="includes/images/ajax-loader.gif" alt="" />
                    </div>
                    <div id="erreur_connexion" style="color:red;display:none;text-align:center;margin:5px;">'.$txt['index_bas_pw'].'</div>';

    echo '
                    <div style="margin-bottom:3px;">
                        <label for="login" class="form_label">', isset($_SESSION['settings']['custom_login_text']) && !empty($_SESSION['settings']['custom_login_text']) ? $_SESSION['settings']['custom_login_text'] : $txt['index_login'], '</label>
                        <input type="text" size="10" id="login" name="login" class="input_text text ui-widget-content ui-corner-all" />
                    </div>
                    <div id="connect_pw" style="margin-bottom:3px;">
                        <label for="pw" class="form_label">'.$txt['index_password'].'</label>
                        <input type="password" size="10" id="pw" name="pw" onkeypress="if (event.keyCode == 13) identifyUser(\''.$nextUrl.'\')" class="input_text text ui-widget-content ui-corner-all" />
                    </div>';

    //2-Factors authentication is asked
    if (isset($_SESSION['settings']['2factors_authentication']) && $_SESSION['settings']['2factors_authentication'] == 1) {
        //Display QR
        echo '
                    <div id="connect_2factors_code" style="margin-bottom:3px;">
                        <div style="text-align:center;" id="2factors_qr_code">'.$txt['2factors_image_text'].'<br /><img class=\'google_qrcode\' src=\''.$qrCode.'\' /></div>
                        <label for="2factors_code" class="">'.$txt['2factors_confirm_text'].'</label>
                        <input type="text" size="10" id="2factors_code" name="2factors_code" class="input_text text ui-widget-content ui-corner-all" onkeypress="if (event.keyCode == 13) identifyUser(\''.$nextUrl.'\')" />
                    </div>';
    }

    echo '
                    <div style="margin-bottom:3px;">
                        <label for="duree_session" class="">'.$txt['index_session_duration'].'&nbsp;('.$txt['minutes'].') </label>
                        <input type="text" size="4" id="duree_session" name="duree_session" value="60" onkeypress="if (event.keyCode == 13) identifyUser(\''.$nextUrl.'\')" class="input_text text ui-widget-content ui-corner-all numeric_only" />
                    </div>

                    <div style="text-align:center;margin-top:5px;font-size:10pt;">
                        <span onclick="OpenDialogBox(\'div_forgot_pw\')" style="padding:3px;cursor:pointer;">'.$txt['forgot_my_pw'].'</span>
                    </div>

                    <div style="text-align:center;margin-top:15px;">
                        <input type="button" id="but_identify_user" onclick="identifyUser(\''.$nextUrl.'\')" style="padding:3px;cursor:pointer;" class="ui-state-default ui-corner-all" value="'.$txt['index_identify_button'].'" />
                    </div>
                </div>
            </form>
            <script type="text/javascript">
                $("#login").focus();
            </script>';
    // DIV for forgotten password
    echo '
            <div id="div_forgot_pw" style="display:none;">
                <div style="margin:5px auto 5px auto;" id="div_forgot_pw_alert"></div>
                <div style="margin:5px auto 5px auto;">'.$txt['forgot_my_pw_text'].'</div>
                <label for="forgot_pw_email">'.$txt['email'].'</label>
                <input type="text" size="40" name="forgot_pw_email" id="forgot_pw_email" />
                <br />
                <label for="forgot_pw_login">'.$txt['login'].'</label>
                <input type="text" size="20" name="forgot_pw_login" id="forgot_pw_login" />
                <div id="div_forgot_pw_status" style="text-align:center;margin-top:15px;display:none;" class="ui-corner-all"><img src="includes/images/76.gif" /></div>
            </div>';
}
echo '
    </div>';
// FOOTER
/* DON'T MODIFY THE FOOTER ... MANY THANKS TO YOU */
echo '
    <div id="footer">
        <div style="float:left;width:32%;">
            <a href="http://www.teampass.net/about/" target="_blank" style="color:#F0F0F0;">'.$k['tool_name'].'&nbsp;'.$k['version'].'&nbsp;&copy;&nbsp;copyright 2009-2012</a>
        </div>
        <div style="float:left;width:32%;text-align:center;">
            ', (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) ? $_SESSION['nb_users_online']."&nbsp;".$txt['users_online'] : "", '
        </div>
        <div style="float:right;margin-top:5px;text-align:right;">
        </div>
    </div>';
// PAGE LOADING
echo '
    <div id="div_loading" style="display:none;">
        <div style="padding:5px; z-index:9999999;" class="ui-widget-content ui-state-focus ui-corner-all">
            <img src="includes/images/76.gif" alt="" />
        </div>
    </div>';
// Alert BOX
echo '
    <div id="div_dialog_message" style="display:none;">
        <div id="div_dialog_message_text"></div>
    </div>';
// ENDING SESSION WARNING
echo '
    <div id="div_fin_session" style="display:none;">
        <div style="padding:10px;text-align:center;">
            <img src="includes/images/alarm-clock.png" alt="" />&nbsp;<b>'.$txt['index_session_ending'].'</b>
        </div>
    </div>';
// WARNING FOR QUERY ERROR
echo '
    <div id="div_mysql_error" style="display:none;">
        <div style="padding:10px;text-align:center;" id="mysql_error_warning"></div>
    </div>';
// Close DB connection
$db->close();

closelog();

?>
    </body>
</html>

<?php
/**
 *
 * @file          index.php
 * @author        Nils Laumaillé
 * @version       2.1.24
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

// Before we start processing, we should abort no install is present
if (!file_exists('includes/settings.php')) {
    // This should never happen, but in case it does
    // this means if headers are sent, redirect will fallback to JS
    if (!headers_sent()) {
        echo '<script language="javascript" type="text/javascript">document.location.replace("install/install.php");</script>';
    } else {
        header('Location: install/install.php');
    }
    // Now either way, we should stop processing further
    exit();
}

require_once('sources/sessions.php');
session_start();//session_unset();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<?php

$_SESSION['CPM'] = 1;
session_id();
if (!isset($_SESSION['settings']['cpassman_dir']) || $_SESSION['settings']['cpassman_dir'] == "") {
    $_SESSION['settings']['cpassman_dir'] = ".";
}

// Include files
require_once $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
require_once $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// connect to the server
require_once './includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

//load main functions needed
require_once 'sources/main.functions.php';
// Load CORE
require_once $_SESSION['settings']['cpassman_dir'].'/sources/core.php';

/* DEFINE WHAT LANGUAGE TO USE */
if (!isset($_SESSION['user_id']) && isset($_GET['language'])) {
    // case of user has change language in the login page
    $dataLanguage = DB::queryFirstRow(
        "SELECT flag, name
        FROM ".prefix_table("languages")."
        WHERE name = %s",
        $_GET['language']
    );
    $_SESSION['user_language'] = $dataLanguage['name'];
    $_SESSION['user_language_flag'] = $dataLanguage['flag'];
} elseif (!isset($_SESSION['user_id']) && !isset($_POST['language']) && !isset($_SESSION['user_language'])) {
    //get default language
    $dataLanguage = DB::queryFirstRow(
        "SELECT m.valeur AS valeur, l.flag AS flag
        FROM ".prefix_table("misc")." AS m
        INNER JOIN ".prefix_table("languages")." AS l ON (m.valeur = l.name)
        WHERE m.type=%s_type AND m.intitule=%s_intitule",
        array(
            'type' => "admin",
            'intitule' => "default_language"
        )
    );
    if (empty($dataLanguage['valeur'])) {
        $_SESSION['user_language'] = "english";
        $_SESSION['user_language_flag'] = "us.png";
    } else {
        $_SESSION['user_language'] = $dataLanguage['valeur'];
        $_SESSION['user_language_flag'] = $dataLanguage['flag'];
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
if (in_array($_SESSION['user_language'], $languagesList)) {
    require_once @$_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
    if (isset($_GET['page']) && $_GET['page'] == "kb") {
        require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'_kb.php';
    }
} else {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
}

// Load links, css and javascripts
@require_once $_SESSION['settings']['cpassman_dir'].'/load.php';
?>

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<title>Teampass</title>
<script type="text/javascript">
            if (window.location.href.indexOf("page=") == -1 && window.location.href.indexOf("otv=") == -1) {
                if (window.location.href.indexOf("session_over=true") == -1) {
                    location.replace("<?php echo $_SESSION['settings']['cpassman_url'];?>/index.php?page=items");
                } else {
                    location.replace("<?php echo $_SESSION['settings']['cpassman_url'];?>/index.php?page=items&session_over=true");
                }
            }
        </script>
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
    // welcome message
    echo '
        <div style="float:right; margin:-10px 50px 0 0; color:#FFF;">'.$LANG['index_welcome'].'&nbsp;<b>'.$_SESSION['name'].'&nbsp;'.$_SESSION['lastname'].'&nbsp;['.$_SESSION['login'].']</b>&nbsp;-&nbsp;', $_SESSION['user_admin'] == 1 ? $LANG['god'] : ($_SESSION['user_manager'] == 1 ? $LANG['gestionnaire'] : ($_SESSION['user_read_only'] == 1 ? $LANG['read_only_account'] : $LANG['user'])), '&nbsp;'.strtolower($LANG['index_login']).'</div>';
    
    echo '
        <div id="menu_top">
            <div style="margin-left:20px; margin-top:2px;width:660px;" id="main_menu">';
    if ($_SESSION['user_admin'] == 0 || $k['admin_full_right'] == 0) {
        echo '
                <button style="margin-left:10px;" title="'.$LANG['pw'].'" onclick="MenuAction(\'items\');"',
                (isset($_SESSION['nb_folders']) && $_SESSION['nb_folders'] == 0)
                || (isset($_SESSION['nb_roles']) && $_SESSION['nb_roles'] == 0) ? ' disabled="disabled"' : '',
                '>
                    <img src="includes/images/menu_key.png" alt="" />
                </button>
                <button title="'.$LANG['find'].'" onclick="MenuAction(\'find\');"',
                (isset($_SESSION['nb_folders']) && $_SESSION['nb_folders'] == 0)
                || (isset($_SESSION['nb_roles']) && $_SESSION['nb_roles'] == 0) ? ' disabled="disabled"' : '',
                '>
                    <img src="includes/images/binocular.png" alt="" />
                </button>';
    }
    
    // Favourites menu
    if (
        isset($_SESSION['settings']['enable_favourites'])
        && $_SESSION['settings']['enable_favourites'] == 1
        && 
        ($_SESSION['user_admin'] == 0 || ($_SESSION['user_admin'] == 1 && $k['admin_full_right'] == false))
    ) {
        echo '
                <button title="'.$LANG['my_favourites'].'" onclick="MenuAction(\'favourites\');">
                    <img src="includes/images/favourite.png" alt="" />
                </button>';
    }
    // KB menu
    if (isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1) {
        echo '
                    <button style="margin-left:10px;" title="'.$LANG['kb_menu'].'" onclick="MenuAction(\'kb\');">
                        <img src="includes/images/direction.png" alt="" />
                    </button>';
    }
    // SUGGESTION menu
    if (
        isset($_SESSION['settings']['enable_suggestion']) && $_SESSION['settings']['enable_suggestion'] == 1
        && ($_SESSION['user_read_only'] == 1 || $_SESSION['user_admin'] == 1 || $_SESSION['user_manager'] == 1)
    ) {
        echo '
                    <button style="margin-right:10px;" title="'.$LANG['suggestion_menu'].'" id="menu_button_suggestion" onclick="MenuAction(\'suggestion\');">
                        <img src="includes/images/envelope.png" alt="" />
                    </button>';
    }
    // Admin menu
    if ($_SESSION['user_admin'] == 1) {
        echo '
                <button style="margin-left:10px;" title="'.$LANG['admin_main'].'" onclick="MenuAction(\'manage_main\');">
                    <img src="includes/images/menu_informations.png" alt="" />
                </button>
                <button title="'.$LANG['admin_settings'].'" onclick="MenuAction(\'manage_settings\');">
                    <img src="includes/images/menu_settings.png" alt="" />
                </button>';
    }

    if ($_SESSION['user_admin'] == 1 || $_SESSION['user_manager'] == 1) {
        echo '
                <button title="'.$LANG['admin_groups'].'" onclick="MenuAction(\'manage_folders\');">
                    <img src="includes/images/menu_groups.png" alt="" />
                </button>
                <button title="'.$LANG['admin_functions'].'" onclick="MenuAction(\'manage_roles\');">
                    <img src="includes/images/menu_functions.png" alt="" />
                </button>
                <button title="'.$LANG['admin_users'].'" onclick="MenuAction(\'manage_users\');">
                    <img src="includes/images/menu_user.png" alt="" />
                </button>
                <button title="'.$LANG['admin_views'].'" onclick="MenuAction(\'manage_views\');">
                    <img src="includes/images/menu_views.png" alt="" />
                </button>';
    }

    echo '
                <div style="float:right;">
                    <ul class="menu" style="">
                        <li class="" style="padding:4px;width:40px; text-align:center;"><i class="fa fa-dashboard fa-fw"></i>&nbsp;
                            <ul class="menu_200" style="text-align:left;">',
                                ($_SESSION['user_admin'] == 1 && $k['admin_full_right'] == true) ? '' :
                                isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1 ? '
                                <li onclick="$(\'#div_set_personal_saltkey\').dialog(\'open\')">
                                    <i class="fa fa-key fa-fw"></i> &nbsp;'.$LANG['home_personal_saltkey_button'].'
                                </li>' : '', '
                                <li onclick="IncreaseSessionTime(\''.$LANG['alert_message_done'].'\', \''.$LANG['please_wait'].'\')">
                                    <i class="fa fa-clock-o fa-fw"></i> &nbsp;'.$LANG['index_add_one_hour'].'
                                </li>
                                <li onclick="loadProfileDialog()">
                                    <i class="fa fa-user fa-fw"></i> &nbsp;'.$LANG['my_profile'].'
                                </li>
                                <li onclick="MenuAction(\'deconnexion\')">
                                    <i class="fa fa-sign-out fa-fw"></i> &nbsp;'.$LANG['disconnect'].'
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>';

    if ($_SESSION['user_admin'] != 1 || ($_SESSION['user_admin'] == 1 && $k['admin_full_right'] == false)) {
        echo '
                <div style="float:right; margin-right:10px;">
                    <ul class="menu" id="menu_last_seen_items">
                        <li class="" style="padding:4px;width:40px; text-align:center;"><i class="fa fa-map fa-fw"></i>&nbsp;&nbsp;
                            <ul class="menu_200" id="last_seen_items_list" style="text-align:left;">
                                <li>'.$LANG['please_wait'].'</li>
                            </ul>
                        </li>
                    </ul>
                </div>';
    }
    echo '
            </div>';
    
    echo '
        </div>';
}
// Display language menu
if (!isset($_GET['otv'])) {
    echo '
        <div style="float:right;">
            <dl id="flags" class="dropdown">
                <dt><img src="includes/images/flags/'.$_SESSION['user_language_flag'].'" alt="" /></dt>
                <dd>
                    <ul>
                    '.$languagesDropmenu.'
                    </ul>
                </dd>
            </dl>
        </div>';
}
    
echo '
    </div>';

    echo '
<div id="main_info_box" style="display:none; z-index:99999; position:absolute; width:400px; height:40px;" class="ui-widget ui-state-active ui-color">
    <div id="main_info_box_text" style="text-align:center;margin-top:10px;"></div>
</div>';

/* MAIN PAGE */
echo '
        <input type="text" style="display:none;" id="temps_restant" value="', isset($_SESSION['fin_session']) ? $_SESSION['fin_session'] : '', '" />
        <input type="hidden" name="language" id="language" value="" />
        <input type="hidden" name="user_pw_complexity" id="user_pw_complexity" value="'.@$_SESSION['user_pw_complexity'].'" />
        <input type="hidden" name="user_session" id="user_session" value=""/>
        <input type="hidden" name="encryptClientServer" id="encryptClientServer" value="', isset($_SESSION['settings']['encryptClientServer']) ? $_SESSION['settings']['encryptClientServer'] : '1', '" />
        <input type="hidden" name="please_login" id="please_login" value="" />
        <input type="hidden" name="action_on_going" id="action_on_going" value="" />
        <input type="hidden" id="duo_sig_response" value="'.@$_POST['sig_response'].'">';

echo '
    ';

echo '
    <div id="', (isset($_GET['page']) && $_GET['page'] == "items" && isset($_SESSION['user_id'])) ? "main_simple" : "main", '">';
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
    $errorAdmin = '<span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['error_no_folders'].'<br />';
}
// error nb roles
if (isset($_SESSION['nb_roles']) && $_SESSION['nb_roles'] == 0) {
    if (empty($errorAdmin)) {
        $errorAdmin = '<span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['error_no_roles'];
    } else {
        $errorAdmin .= '<br /><span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['error_no_roles'];
    }
}
// error Salt key
if (isset($_SESSION['error']['salt']) && $_SESSION['error']['salt'] == 1) {
    if (empty($errorAdmin)) {
        $errorAdmin = '<span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['error_salt'];
    } else {
        $errorAdmin .= '<br /><span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['error_salt'];
    }
}

if (isset($_SESSION['validite_pw']) && $_SESSION['validite_pw']) {
    // error cpassman dir
    if (isset($_SESSION['settings']['cpassman_dir']) && empty($_SESSION['settings']['cpassman_dir']) || !isset($_SESSION['settings']['cpassman_dir'])) {
        if (empty($errorAdmin)) {
            $errorAdmin = '<span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['error_cpassman_dir'];
        } else {
            $errorAdmin .= '<br /><span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['error_cpassman_dir'];
        }
    }
    // error cpassman url
    if (isset($_SESSION['validite_pw']) && (isset($_SESSION['settings']['cpassman_url']) && empty($_SESSION['settings']['cpassman_url']) || !isset($_SESSION['settings']['cpassman_url']))) {
        if (empty($errorAdmin)) {
            $errorAdmin = '<span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['error_cpassman_url'];
        } else {
            $errorAdmin .= '<br /><span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['error_cpassman_url'];
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
// Display Maintenance mode information
if (
    isset($_SESSION['settings']['maintenance_mode']) && $_SESSION['settings']['maintenance_mode'] == 1
        && isset($_SESSION['user_admin']) && $_SESSION['user_admin'] == 1
    ) {
    echo '
        <div style="text-align:center;margin-bottom:5px;padding:10px;" class="ui-state-highlight ui-corner-all">
            <b>'.$LANG['index_maintenance_mode_admin'].'</b>
        </div>';
}
// Display UPDATE NEEDED information
if (
    isset($_SESSION['settings']['update_needed']) && $_SESSION['settings']['update_needed'] == true
        && isset($_SESSION['user_admin']) && $_SESSION['user_admin'] == 1
        && ((isset($_SESSION['hide_maintenance']) && $_SESSION['hide_maintenance'] == 0)
        || !isset($_SESSION['hide_maintenance']))
    ) {
    echo '
        <div style="text-align:center;margin-bottom:5px;padding:10px;"
            class="ui-state-highlight ui-corner-all" id="div_maintenance">
            <b>'.$LANG['update_needed_mode_admin'].'</b>
            <span style="float:right;cursor:pointer;">
                <img src="includes/images/cross.png" onclick="toggleDiv(\'div_maintenance\')" />
            </span>
        </div>';
}

// display an item in the context of OTV link
    if ((!isset($_SESSION['validite_pw']) || empty($_SESSION['validite_pw']) || empty($_SESSION['user_id'])) && isset($_GET['otv']) && $_GET['otv'] == "true") {
        // case where one-shot viewer
        if (
            isset($_GET['code']) && !empty($_GET['code'])
            && isset($_GET['stamp']) && !empty($_GET['stamp'])
        ) {
            include 'otv.php';
        } else {
            $_SESSION['error']['code'] = ERR_VALID_SESSION;
            $_SESSION['initial_url'] = substr($_SERVER["REQUEST_URI"], strpos($_SERVER["REQUEST_URI"], "index.php?"));
            include $_SESSION['settings']['cpassman_dir'].'/error.php';
        }
    } 
// ask the user to change his password
    else if ((!isset($_SESSION['validite_pw']) || $_SESSION['validite_pw'] == false) && !empty($_SESSION['user_id'])) {
        //Check if password is valid
        echo '
        <div style="margin:auto;padding:4px;width:300px;"  class="ui-state-focus ui-corner-all">
            <h3>'.$LANG['index_change_pw'].'</h3>
            <div style="height:20px;text-align:center;margin:2px;display:none;" id="change_pwd_error" class=""></div>
            <div style="text-align:center;margin:5px;padding:3px;" id="change_pwd_complexPw" class="ui-widget ui-state-active ui-corner-all">'.
            $LANG['complex_asked'].' : '.$_SESSION['settings']['pwComplexity'][$_SESSION['user_pw_complexity']][1].
            '</div>
            <div id="pw_strength" style="margin:0 0 10px 30px;"></div>
            <table>
                <tr>
                    <td>'.$LANG['index_new_pw'].' :</td><td><input type="password" size="15" name="new_pw" id="new_pw"/></td>
                </tr>
                <tr><td>'.$LANG['index_change_pw_confirmation'].' :</td><td><input type="password" size="15" name="new_pw2" id="new_pw2" onkeypress="if (event.keyCode == 13) ChangeMyPass();" /></td></tr>
            </table>
            <input type="hidden" id="pw_strength_value" />
            <input type="button" onClick="ChangeMyPass()" onkeypress="if (event.keyCode == 13) ChangeMyPass();" class="ui-state-default ui-corner-all" style="padding:4px;width:150px;margin:10px 0 0 80px;" value="'.$LANG['index_change_pw_button'].'" />
        </div>
        <script type="text/javascript">
            $("#new_pw").focus();
        </script>';
    }
// Display pages
    elseif (isset($_SESSION['validite_pw']) && $_SESSION['validite_pw'] == true && !empty($_GET['page']) && !empty($_SESSION['user_id'])) {
        if (!extension_loaded('mcrypt')) {
            $_SESSION['error']['code'] = ERR_NO_MCRYPT;
            include $_SESSION['settings']['cpassman_dir'].'/error.php';
        } elseif (isset($_SESSION['initial_url']) && !empty($_SESSION['initial_url'])) {
            include $_SESSION['initial_url'];
        } elseif ($_GET['page'] == "items") {
            // SHow page with Items
            if (
                ($_SESSION['user_admin'] != 1)
                ||
                ($_SESSION['user_admin'] == 1 && $k['admin_full_right'] == false)
            ) {
                include 'items.php';
            } else {
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $_SESSION['settings']['cpassman_dir'].'/error.php';
            }
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
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $_SESSION['settings']['cpassman_dir'].'/error.php';
            }
        } elseif ($_GET['page'] == "suggestion") {
            // Show page KB
            if (isset($_SESSION['settings']['enable_suggestion']) && $_SESSION['settings']['enable_suggestion'] == 1) {
                include 'suggestion.php';
            } else {
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $_SESSION['settings']['cpassman_dir'].'/error.php';
            }
        } elseif (in_array($_GET['page'], array_keys($mngPages))) {
            // Define if user is allowed to see management pages
            if ($_SESSION['user_admin'] == 1) {
                include($mngPages[$_GET['page']]);
            } elseif ($_SESSION['user_manager'] == 1) {
                if (($_GET['page'] != "manage_main" &&  $_GET['page'] != "manage_settings")) {
                    include($mngPages[$_GET['page']]);
                } else {
                    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                    include $_SESSION['settings']['cpassman_dir'].'/error.php';
                }
            } else {
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $_SESSION['settings']['cpassman_dir'].'/error.php';
            }
        } else {
            $_SESSION['error']['code'] = ERR_NOT_EXIST; //page doesn't exist
            include $_SESSION['settings']['cpassman_dir'].'/error.php';
        }
    }
// case of password recovery
    elseif (empty($_SESSION['user_id']) && isset($_GET['action']) && $_GET['action'] == "password_recovery") {
        // Case where user has asked new PW
        echo '
            <div style="width:400px;margin:50px auto 50px auto;padding:25px;" class="ui-state-highlight ui-corner-all">
                <div style="text-align:center;font-weight:bold;margin-bottom:20px;">
                    '.$LANG['pw_recovery_asked'].'
                </div>
                <div id="generate_new_pw_error" style="color:red;display:none;text-align:center;margin:5px;"></div>
                <div style="margin-bottom:3px;">
                    '.$LANG['pw_recovery_info'].'
                </div>
                <div style="margin:15px; text-align:center;">
                    <input type="button" id="but_generate_new_password" onclick="GenerateNewPassword(\''.htmlspecialchars($_GET['key'], ENT_QUOTES).'\',\''.htmlspecialchars($_GET['login'], ENT_QUOTES).'\')" style="padding:3px;cursor:pointer;" class="ui-state-default ui-corner-all" value="'.$LANG['pw_recovery_button'].'" />
                    <br /><br />
                    <img id="ajax_loader_send_mail" style="display:none;" src="includes/images/ajax-loader.gif" alt="" />
                </div>
            </div>';
    } elseif (!empty($_SESSION['user_id']) && isset($_SESSION['user_id'])) {
        // Page doesn't exist
        $_SESSION['error']['code'] = ERR_NOT_EXIST;
        include $_SESSION['settings']['cpassman_dir'].'/error.php';
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
                <div style="text-align:center;margin-top:30px;margin-bottom:20px;padding:10px;"
                    class="ui-state-error ui-corner-all">
                    <b>'.$LANG['index_maintenance_mode'].'</b>
                </div>';
        } else if (isset($_GET['session_over']) && $_GET['session_over'] == "true") {
            // SESSION FINISHED => RECONNECTION ASKED
            echo '
                    <div style="text-align:center;margin-top:30px;margin-bottom:20px;padding:10px;"
                        class="ui-state-error ui-corner-all">
                        <b>'.$LANG['index_session_expired'].'</b>
                    </div>';
        }

        // case where user not logged and can't access a direct link
        if (!empty($_GET['page'])) {
            $_SESSION['initial_url'] = substr($_SERVER["REQUEST_URI"], strpos($_SERVER["REQUEST_URI"], "index.php?"));
        } else {
            $_SESSION['initial_url'] = "";
        }

        // CONNECTION FORM
        echo '
                <form method="post" name="form_identify" id="form_identify" action="">
                    <div style="width:300px;margin:10px auto 10px auto;padding:25px;" class="ui-state-highlight ui-corner-all">
                        <div style="text-align:center;font-weight:bold;margin-bottom:20px;">',
        isset($_SESSION['settings']['custom_logo']) && !empty($_SESSION['settings']['custom_logo']) ? '<img src="'.$_SESSION['settings']['custom_logo'].'" alt="" style="margin-bottom:40px;" />' : '', '<br />
                            '.$LANG['index_get_identified'].'
                            &nbsp;<img id="ajax_loader_connexion" style="display:none;" src="includes/images/ajax-loader.gif" alt="" />
                        </div>
                        <div id="connection_error" style="display:none;text-align:center;margin:5px; padding:3px;" class="ui-state-error ui-corner-all">&nbsp;<i class="fa fa-warning"></i>&nbsp;'.$LANG['index_bas_pw'].'</div>';
        echo '
                        <div style="margin-bottom:3px;">
                            <label for="login" class="form_label">', isset($_SESSION['settings']['custom_login_text']) && !empty($_SESSION['settings']['custom_login_text']) ? $_SESSION['settings']['custom_login_text'] : $LANG['index_login'], '</label>
                            <input type="text" size="10" id="login" name="login" class="input_text text ui-widget-content ui-corner-all" />
                            <img id="login_check_wait" src="includes/images/ajax-loader.gif" alt="" style="display:none;" />
                        </div>
                        <div id="connect_pw" style="margin-bottom:3px;">
                            <label for="pw" class="form_label">'.$LANG['index_password'].'</label>
                            <input type="password" size="10" id="pw" name="pw" onkeypress="if (event.keyCode == 13) launchIdentify(\'', isset($_SESSION['settings']['duo']) && $_SESSION['settings']['duo'] == 1 ? 1 : '', '\', \''.$nextUrl.'\', \'', isset($_SESSION['settings']['2factors_authentication']) && $_SESSION['settings']['2factors_authentication'] == 1 ? 1 : '', '\')" class="input_text text ui-widget-content ui-corner-all" />
                        </div>';

        // Personal salt key
        if (isset($_SESSION['settings']['psk_authentication']) && $_SESSION['settings']['psk_authentication'] == 1) {
            echo '
                        <div id="connect_psk" style="margin-bottom:3px;">
                            <label for="personal_psk" class="form_label">'.$LANG['home_personal_saltkey'].'</label>
                            <input type="password" size="10" id="psk" name="psk" onkeypress="if (event.keyCode == 13) launchIdentify(\'', isset($_SESSION['settings']['duo']) && $_SESSION['settings']['duo'] == 1 ? 1 : '', '\', \''.$nextUrl.'\', \'', isset($_SESSION['settings']['psk_authentication']) && $_SESSION['settings']['psk_authentication'] == 1 ? 1 : '', '\')" class="input_text text ui-widget-content ui-corner-all" />
                        </div>
                        <div id="connect_psk_confirm" style="margin-bottom:3px; display:none;">
                            <label for="psk_confirm" class="form_label">'.$LANG['home_personal_saltkey_confirm'].'</label>
                            <input type="password" size="10" id="psk_confirm" name="psk_confirm" onkeypress="if (event.keyCode == 13) launchIdentify(\'', isset($_SESSION['settings']['duo']) && $_SESSION['settings']['duo'] == 1 ? 1 : '', '\', \''.$nextUrl.'\', \'', isset($_SESSION['settings']['psk_authentication']) && $_SESSION['settings']['psk_authentication'] == 1 ? 1 : '', '\')" class="input_text text ui-widget-content ui-corner-all" />
                        </div>';
        }

        // Google Authenticator code
        if (isset($_SESSION['settings']['2factors_authentication']) && $_SESSION['settings']['2factors_authentication'] == 1) {
            echo '
                        <div id="ga_code_div" style="margin-bottom:10px;">
                            '.$LANG['ga_identification_code'].'
                            <input type="text" size="4" id="ga_code" name="ga_code" style="margin:0px;" class="input_text text ui-widget-content ui-corner-all numeric_only" onkeypress="if (event.keyCode == 13) launchIdentify(\'', isset($_SESSION['settings']['duo']) && $_SESSION['settings']['duo'] == 1 ? 1 : '', '\', \''.$nextUrl.'\')" />
                            <div id="div_ga_url" class="ui-widget ui-state-focus ui-corner-all" style="margin-top:3px;">
                                '.$LANG['user_ga_code_sent_by_email'].'
                            </div>
                            <div style="text-align:center; font-size:9pt; font-style:italic; margin-bottom:10px;">
                                <span onclick="getGASynchronization()" style="padding:3px;cursor:pointer;">'.$LANG['ga_not_yet_synchronized'].'</span>
                            </div>
                        </div>';
        }
        echo '
                        <div style="margin-bottom:3px;">
                            <label for="duree_session" class="">'.$LANG['index_session_duration'].'&nbsp;('.$LANG['minutes'].') </label>
                            <input type="text" size="4" id="duree_session" name="duree_session" value="', isset($_SESSION['settings']['default_session_expiration_time']) ? $_SESSION['settings']['default_session_expiration_time'] : "60" ,'" onkeypress="if (event.keyCode == 13) launchIdentify(\'', isset($_SESSION['settings']['duo']) && $_SESSION['settings']['duo'] == 1 ? 1 : '', '\', \''.$nextUrl.'\')" class="input_text text ui-widget-content ui-corner-all numeric_only" />
                        </div>

                        <div style="text-align:center;margin-top:5px;font-size:10pt;">
                            <span onclick="OpenDialogBox(\'div_forgot_pw\')" style="padding:3px;cursor:pointer;">'.$LANG['forgot_my_pw'].'</span>
                        </div>
                        <div style="text-align:center;margin-top:15px;">
                            <input type="button" id="but_identify_user" onclick="launchIdentify(\'', isset($_SESSION['settings']['duo']) && $_SESSION['settings']['duo'] == 1 ? 1 : '', '\', \''.$nextUrl.'\', \'', isset($_SESSION['settings']['psk_authentication']) && $_SESSION['settings']['psk_authentication'] == 1 ? 1 : '', '\')" style="padding:3px;cursor:pointer;" class="ui-state-default ui-corner-all" value="'.$LANG['index_identify_button'].'" />
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
                    <div style="margin:5px auto 5px auto;">'.$LANG['forgot_my_pw_text'].'</div>
                    <label for="forgot_pw_email">'.$LANG['email'].'</label>
                    <input type="text" size="40" name="forgot_pw_email" id="forgot_pw_email" />
                    <br />
                    <label for="forgot_pw_login">'.$LANG['login'].'</label>
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
            <a href="http://teampass.net/about" target="_blank" style="color:#F0F0F0;">'.$k['tool_name'].'&nbsp;'.$k['version'].'&nbsp;<i class="fa fa-copyright"></i>&nbsp;'.$k['copyright'].'</a>
        </div>
        <div style="float:left;width:32%;text-align:center;">
            ', (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) ? '<i class="fa fa-users"></i>&nbsp;'.$_SESSION['nb_users_online'].'&nbsp;'.$LANG['users_online'].'&nbsp;|&nbsp;<i class="fa fa-hourglass-end"></i>&nbsp;'.$LANG['index_expiration_in'].'&nbsp;<div style="display:inline;" id="countdown"></div>' : '', '
        </div>
        <div style="float:right;text-align:right;">
            <i class="fa fa-clock-o"></i>&nbsp;'. $LANG['server_time']." : ".@date($_SESSION['settings']['date_format'], $_SERVER['REQUEST_TIME'])." - ".@date($_SESSION['settings']['time_format'], $_SERVER['REQUEST_TIME']) .'
        </div>
    </div>';
// PAGE LOADING
echo '
    <div id="div_loading" style="display:none;">
        <div style="padding:5px; z-index:9999999;" class="ui-widget-content ui-state-focus ui-corner-all">
            <i class="fa fa-cog fa-spin fa-2x"></i>
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
            <img src="includes/images/alarm-clock.png" alt="" />&nbsp;<b>'.$LANG['index_session_ending'].'</b>
        </div>
    </div>';
// WARNING FOR QUERY ERROR
echo '
    <div id="div_mysql_error" style="display:none;">
        <div style="padding:10px;text-align:center;" id="mysql_error_warning"></div>
    </div>';


//Personnal SALTKEY
if (
    isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1
    //&& (!isset($_SESSION['settings']['psk_authentication']) || $_SESSION['settings']['psk_authentication'] == 0)
) {
    echo '
        <div id="div_set_personal_saltkey" style="display:none;padding:4px;">
            <i class="fa fa-key"></i> <b>'.$LANG['home_personal_saltkey'].'</b>
            <input type="password" name="input_personal_saltkey" id="input_personal_saltkey" style="width:200px;padding:5px;margin-left:30px;" class="text ui-widget-content ui-corner-all text_without_symbols tip" value="', isset($_SESSION['my_sk']) ? $_SESSION['my_sk'] : '', '" title="<i class=\'fa fa-bullhorn\'></i>&nbsp;'.$LANG['text_without_symbols'].'" />
            <span id="set_personal_saltkey_last_letter" style="font-weight:bold;font-size:20px;"></span>
            <div style="display:none;margin-top:5px;text-align:center;padding:4px;" id="set_personal_saltkey_warning" class="ui-widget-content ui-state-error ui-corner-all"></div>
        </div>';
}

// user profile
    echo '
    <div id="dialog_user_profil" style="display:none;padding:4px;">
        <div id="div_user_profil">
            <i class="fa fa-cog fa-spin fa-2x"></i>&nbsp;<b>'.$LANG['please_wait'].'</b>
        </div>
    </div>';

    // DUO box
    echo '
    <div id="dialog_duo" style="display:none;padding:4px;">
        <div id="div_duo"></div>
        '.$LANG['duo_loading_iframe'].'
        <form method="POST" id="duo_form">
            <input type="hidden" id="duo_login" name="duo_login" value="'.@$_POST['duo_login'].'" />
            <input type="hidden" id="duo_data" name="duo_data" value=\''.@$_POST['duo_data'].'\' />
        </form>
    </div>';


closelog();

?>
    </body>
</html>
<script>
    NProgress.start();
</script>
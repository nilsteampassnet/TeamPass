<?php
/**
 *
 * @file          index.php
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

header("X-XSS-Protection: 1; mode=block");
header("X-Frame-Option: SameOrigin");

// **PREVENTING SESSION HIJACKING**
// Prevents javascript XSS attacks aimed to steal the session ID
ini_set('session.cookie_httponly', 1);

// **PREVENTING SESSION FIXATION**
// Session ID cannot be passed through URLs
ini_set('session.use_only_cookies', 1);

// Uses a secure connection (HTTPS) if possible
ini_set('session.cookie_secure', 0);

// Before we start processing, we should abort no install is present
if (!file_exists('includes/config/settings.php')) {
    // This should never happen, but in case it does
    // this means if headers are sent, redirect will fallback to JS
    if (headers_sent()) {
        echo '<script language="javascript" type="text/javascript">document.location.replace("install/install.php");</script>';
    } else {
        header('Location: install/install.php');
    }
    // Now either way, we should stop processing further
    exit();
}

// initialise CSRFGuard library
require_once('./includes/libraries/csrfp/libs/csrf/csrfprotector.php');
csrfProtector::init();
//session_destroy();
session_id();

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    require_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    require_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Include files
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();


// initialize session
$_SESSION['CPM'] = 1;
if (isset($SETTINGS['cpassman_dir']) === false || $SETTINGS['cpassman_dir'] === "") {
    $SETTINGS['cpassman_dir'] = ".";
    $SETTINGS['cpassman_url'] = $superGlobal->get("REQUEST_URI", "SERVER");
}

// Include files
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';


// Open MYSQL database connection
require_once './includes/libraries/Database/Meekrodb/db.class.php';
$pass = defuse_return_decrypted($pass);
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = true;
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);


// Load Core library
require_once $SETTINGS['cpassman_dir'].'/sources/core.php';


// Prepare POST variables
$post_language =        filter_input(INPUT_POST, 'language', FILTER_SANITIZE_STRING);
$post_sig_response =    filter_input(INPUT_POST, 'sig_response', FILTER_SANITIZE_STRING);
$post_duo_login =       filter_input(INPUT_POST, 'duo_login', FILTER_SANITIZE_STRING);
$post_duo_data =        filter_input(INPUT_POST, 'duo_data', FILTER_SANITIZE_STRING);

// Prepare superGlobal variables
$session_user_language =        $superGlobal->get("user_language", "SESSION");
$session_user_id =              $superGlobal->get("user_id", "SESSION");
$session_user_flag =            $superGlobal->get("user_language_flag", "SESSION");
$session_user_admin =           $superGlobal->get("user_admin", "SESSION");
$session_user_avatar_thumb =    $superGlobal->get("user_avatar_thumb", "SESSION");
$session_name =                 $superGlobal->get("name", "SESSION");
$session_lastname =             $superGlobal->get("lastname", "SESSION");
$session_user_manager =         $superGlobal->get("user_manager", "SESSION");
$session_user_read_only =       $superGlobal->get("user_read_only", "SESSION");
$session_is_admin =             $superGlobal->get("is_admin", "SESSION");
$session_login =                $superGlobal->get("login", "SESSION");
$session_validite_pw =          $superGlobal->get("validite_pw", "SESSION");
$session_nb_folders =           $superGlobal->get("nb_folders", "SESSION");
$session_nb_roles =             $superGlobal->get("nb_roles", "SESSION");
$session_autoriser =            $superGlobal->get("autoriser", "SESSION");
$session_hide_maintenance =     $superGlobal->get("hide_maintenance", "SESSION");
$session_initial_url =          $superGlobal->get("initial_url", "SESSION");
$server_request_uri =           $superGlobal->get("REQUEST_URI", "SERVER");


/* DEFINE WHAT LANGUAGE TO USE */
if (isset($_GET['language']) === true) {
    // case of user has change language in the login page
    $dataLanguage = DB::queryFirstRow(
        "SELECT flag, name
        FROM ".prefix_table("languages")."
        WHERE name = %s",
        filter_var($_GET['language'], FILTER_SANITIZE_STRING)
    );
    $superGlobal->put("user_language", $dataLanguage['name'], "SESSION");
    $superGlobal->put("user_language_flag", $dataLanguage['flag'], "SESSION");
} elseif ($session_user_id === null && null === $post_language && $session_user_language === null) {
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
        $superGlobal->put("user_language", "english", "SESSION");
        $superGlobal->put("user_language_flag", "us.png", "SESSION");
        $session_user_language = "english";
    } else {
        $superGlobal->put("user_language", $dataLanguage['valeur'], "SESSION");
        $superGlobal->put("user_language_flag", $dataLanguage['flag'], "SESSION");
        $session_user_language = $dataLanguage['valeur'];
    }
} elseif (isset($SETTINGS['default_language']) === true && $session_user_language === null) {
    $superGlobal->put("user_language", $SETTINGS['default_language'], "SESSION");
    $session_user_language = $SETTINGS['default_language'];
} elseif (null !== $post_language) {
    $superGlobal->put("user_language", $post_language, "SESSION");
    $session_user_language = $post_language;
} elseif ($session_user_language === null || empty($session_user_language) === true) {
    if (null !== $post_language) {
        $superGlobal->put("user_language", $post_language, "SESSION");
        $session_user_language = $post_language;
    } elseif ($session_user_language !== null) {
        $superGlobal->put("user_language", $SETTINGS['default_language'], "SESSION");
        $session_user_language = $SETTINGS['default_language'];
    }
} elseif ($session_user_language === '0') {
    $superGlobal->put("user_language", $SETTINGS['default_language'], "SESSION");
    $session_user_language = $SETTINGS['default_language'];
}

if (isset($SETTINGS['cpassman_dir']) === false || $SETTINGS['cpassman_dir'] === "") {
    $SETTINGS['cpassman_dir'] = ".";
    $SETTINGS['cpassman_url'] = (string) $server_request_uri;
}

// Load user languages files
if (in_array($session_user_language, $languagesList) === true) {
    require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$session_user_language.'.php';
} else {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
}

// load 2FA Google
if (isset($SETTINGS['google_authentication']) === true && $SETTINGS['google_authentication'] === "1") {
    include_once($SETTINGS['cpassman_dir']."/includes/libraries/Authentication/TwoFactorAuth/TwoFactorAuth.php");
}

// Load links, css and javascripts
if (isset($_SESSION['CPM']) === true && isset($SETTINGS['cpassman_dir']) === true) {
    require_once $SETTINGS['cpassman_dir'].'/load.php';
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<title>Teampass</title>
<script type="text/javascript">
    //<![CDATA[
    if (window.location.href.indexOf("page=") == -1 && (window.location.href.indexOf("otv=") == -1 && window.location.href.indexOf("action=") == -1)) {
        if (window.location.href.indexOf("session_over=true") == -1) {
            //location.replace("./index.php?page=items");
        } else {
            location.replace("./logout.php");
        }
    }
    //]]>
</script>
<?php

// load HEADERS
if (isset($_SESSION['CPM'])) {
    echo $htmlHeaders;
}
?>
    </head>

<body>
    <?php

/* HEADER */
    echo '
    <div id="top">
        <div id="logo"><img src="includes/images/canevas/logo.png" alt="" /></div>';
    // Display menu
    if (empty($session_login) === false) {
        // welcome message
        echo '
        <div style="float:right; margin:-10px 5px 0 0; color:#FFF;">'
            .$LANG['index_welcome'].'&nbsp;<b>'.$session_name.'&nbsp;'.$session_lastname
            .'&nbsp;['.$session_login.']</b>&nbsp;-&nbsp;'
            , $session_user_admin === '1' ? $LANG['god'] :
                ($session_user_manager === '1' ? $LANG['gestionnaire'] :
                    ($session_user_read_only === '1' ? $LANG['read_only_account'] : $LANG['user'])
                ), '&nbsp;'.strtolower($LANG['index_login']).'</div>';

        echo '
        <div id="menu_top">
            <div style="margin-left:20px; margin-top:2px;width:710px;" id="main_menu">';
        if ($session_user_admin === '0' || $SETTINGS_EXT['admin_full_right'] == 0) {
            echo '
                <a class="btn btn-default" href="#"',
                ($session_nb_folders !== null && intval($session_nb_folders) === 0)
                || ($session_nb_roles !== null && intval($session_nb_roles) === 0) ? '' : ' onclick="MenuAction(\'items\')"',
                '>
                    <i class="fa fa-key fa-2x tip" title="'.$LANG['pw'].'"></i>
                </a>

                <a class="btn btn-default" href="#"',
                ($session_nb_folders !== null && intval($session_nb_folders) === 0)
                || ($session_nb_roles !== null && intval($session_nb_roles) === 0) ? '' : ' onclick="MenuAction(\'find\')"',
                '>
                    <i class="fa fa-binoculars fa-2x tip" title="'.$LANG['find'].'"></i>
                </a>';
        }

        // Favourites menu
        if (isset($SETTINGS['enable_favourites'])
            && $SETTINGS['enable_favourites'] == 1
            &&
            ($session_user_admin === '0' || ($session_user_admin === '1' && $SETTINGS_EXT['admin_full_right'] === false))
        ) {
            echo '
                    <a class="btn btn-default" href="#" onclick="MenuAction(\'favourites\')">
                        <i class="fa fa-star fa-2x tip" title="'.$LANG['my_favourites'].'"></i>
                    </a>';
        }
        // KB menu
        if (isset($SETTINGS['enable_kb']) && $SETTINGS['enable_kb'] == 1) {
            echo '
                    <a class="btn btn-default" href="#" onclick="MenuAction(\'kb\')">
                        <i class="fa fa-map-signs fa-2x tip" title="'.$LANG['kb_menu'].'"></i>
                    </a>';
        }
        echo '
        <span id="menu_suggestion_position">';
        // SUGGESTION menu
        if (isset($SETTINGS['enable_suggestion']) && $SETTINGS['enable_suggestion'] === '1'
            && ($session_user_read_only === '1' || $session_user_admin === '1' || $session_user_manager === '1')
        ) {
            echo '
                <a class="btn btn-default" href="#" onclick="MenuAction(\'suggestion\')">
                    <i class="fa fa-lightbulb-o fa-2x tip" id="menu_icon_suggestions" title="'.$LANG['suggestion_menu'].'"></i>
                </a>';
        }
        echo '
        </span>';
        // Admin menu
        if ($session_user_admin === '1') {
            echo '
                    &nbsp;
                    <a class="btn btn-default" href="#" onclick="MenuAction(\'manage_main\')">
                        <i class="fa fa-info fa-2x tip" title="'.$LANG['admin_main'].'"></i>
                    </a>
                    <a class="btn btn-default" href="#" onclick="MenuAction(\'manage_settings\')">
                        <i class="fa fa-wrench fa-2x tip" title="'.$LANG['admin_settings'].'"></i>
                    </a>';
        }

        if ($session_user_admin === '1' || $session_user_manager === '1') {
            echo '
                &nbsp;
                <a class="btn btn-default" href="#" onclick="MenuAction(\'manage_folders\')">
                    <i class="fa fa-folder-open fa-2x tip" title="'.$LANG['admin_groups'].'"></i>
                </a>
                <a class="btn btn-default" href="#" onclick="MenuAction(\'manage_roles\')">
                    <i class="fa fa-graduation-cap fa-2x tip" title="'.$LANG['admin_functions'].'"></i>
                </a>
                <a class="btn btn-default" href="#" onclick="MenuAction(\'manage_users\')">
                    <i class="fa fa-users fa-2x tip" title="'.$LANG['admin_users'].'"></i>
                </a>
                <a class="btn btn-default" href="#" onclick="MenuAction(\'manage_views\')">
                    <i class="fa fa-cubes fa-2x tip" title="'.$LANG['admin_views'].'"></i>
                </a>';
        }

        echo '
                <div style="float:right;">
                    <ul class="menu" style="">
                        <li class="" style="padding:4px;width:40px; text-align:center;"><i class="fa fa-dashboard fa-fw"></i>&nbsp;
                            <ul class="menu_200" style="text-align:left;">',
                                ($session_user_admin === '1' && $SETTINGS_EXT['admin_full_right'] === true) ? '' : isset($SETTINGS['enable_pf_feature']) === true && $SETTINGS['enable_pf_feature'] == 1 ? '
                                <li onclick="$(\'#div_set_personal_saltkey\').dialog(\'open\')">
                                    <i class="fa fa-key fa-fw"></i> &nbsp;'.$LANG['home_personal_saltkey_button'].'
                                </li>' : '', '
                                <li onclick="$(\'#div_increase_session_time\').dialog(\'open\')">
                                    <i class="fa fa-clock-o fa-fw"></i> &nbsp;'.$LANG['index_add_one_hour'].'
                                </li>
                                <li onclick="loadProfileDialog()">
                                    <i class="fa fa-user fa-fw"></i> &nbsp;'.$LANG['my_profile'].'
                                </li>
                                <li onclick="MenuAction(\'deconnexion\', \''.$session_user_id.'\')">
                                    <i class="fa fa-sign-out fa-fw"></i> &nbsp;'.$LANG['disconnect'].'
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>';

        if ($session_user_admin !== '1' || ($session_user_admin === '1' && $SETTINGS_EXT['admin_full_right'] === false)) {
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

        // show avatar
        if ($session_user_avatar_thumb !== null && empty($session_user_avatar_thumb) === false) {
            if (file_exists('includes/avatars/'.$session_user_avatar_thumb)) {
                $avatar = $SETTINGS['cpassman_url'].'/includes/avatars/'.$session_user_avatar_thumb;
            } else {
                $avatar = $SETTINGS['cpassman_url'].'/includes/images/photo.jpg';
            }
        } else {
            $avatar = $SETTINGS['cpassman_url'].'/includes/images/photo.jpg';
        }
        echo '
                <div style="float:right; margin-right:10px;">
                    <img src="'.$avatar.'" style="border-radius:10px; height:28px; cursor:pointer;" onclick="loadProfileDialog()" alt="photo" id="user_avatar_thumb" />
                </div>';

        echo '
            </div>';

        echo '
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
        <input type="hidden" id="temps_restant" value="', isset($_SESSION['fin_session']) ? $_SESSION['fin_session'] : '', '" />
        <input type="hidden" name="language" id="language" value="" />
        <input type="hidden" name="user_pw_complexity" id="user_pw_complexity" value="', isset($_SESSION['user_pw_complexity']) ? $_SESSION['user_pw_complexity'] : '', '" />
        <input type="hidden" name="user_session" id="user_session" value=""/>
        <input type="hidden" name="encryptClientServer" id="encryptClientServer" value="', isset($SETTINGS['encryptClientServer']) ? $SETTINGS['encryptClientServer'] : '1', '" />
        <input type="hidden" name="please_login" id="please_login" value="" />
        <input type="hidden" name="disabled_action_on_going" id="disabled_action_on_going" value="" />
        <input type="hidden" id="duo_sig_response" value="', null !== $post_sig_response ? intval($post_sig_response) : '', '" />';

// SENDING STATISTICS?
    if (isset($SETTINGS['send_stats']) && $SETTINGS['send_stats'] === "1"
        && (!isset($_SESSION['temporary']['send_stats_done']) || $_SESSION['temporary']['send_stats_done'] !== "1")
    ) {
        echo '
            <input type="hidden" name="send_statistics" id="send_statistics" value="1" />';
    } else {
        echo '
        <input type="hidden" name="send_statistics" id="send_statistics" value="0" />';
    }

    echo '
    <div id="', (isset($_GET['page']) && filter_var($_GET['page'], FILTER_SANITIZE_STRING) === "items" && $session_user_id !== null) ? "main_simple" : "main", '">';
// MESSAGE BOX
    echo '
            <div style="" class="div_center">
                <div id="message_box" style="display:none;width:200px;padding:5px;text-align:center; z-index:999999;" class="ui-widget-content ui-state-error ui-corner-all"></div>
            </div>';
    // Main page
    if ($session_autoriser !== null && $session_autoriser === true) {
        // Show menu
        echo '
            <form method="post" name="main_form" action="">
                <input type="hidden" name="menu_action" id="menu_action" value="" />
                <input type="hidden" name="changer_pw" id="changer_pw" value="" />
                <input type="hidden" name="form_user_id" id="form_user_id" value="', $session_user_id !== null ? $session_user_id : '', '" />
                <input type="hidden" name="is_admin" id="is_admin" value="', $session_is_admin !== null ? $session_is_admin : '', '" />
                <input type="hidden" name="personal_saltkey_set" id="personal_saltkey_set" value="', isset($_SESSION['user_settings']['clear_psk']) ? true : false, '" />
            </form>';
    }
// ---------
// Display a help to admin
    $errorAdmin = "";

// error nb folders
    if ($session_nb_folders !== null && intval($session_nb_folders) === 0) {
        $errorAdmin = '<span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['error_no_folders'].'<br />';
    }
// error nb roles
    if ($session_nb_roles !== null && intval($session_nb_roles) === 0) {
        if (empty($errorAdmin)) {
            $errorAdmin = '<span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['error_no_roles'];
        } else {
            $errorAdmin .= '<br /><span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['error_no_roles'];
        }
    }

    if ($session_validite_pw !== null && empty($session_validite_pw) === false) {
        // error cpassman dir
        if (isset($SETTINGS['cpassman_dir']) && empty($SETTINGS['cpassman_dir']) || !isset($SETTINGS['cpassman_dir'])) {
            if (empty($errorAdmin)) {
                $errorAdmin = '<span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['error_cpassman_dir'];
            } else {
                $errorAdmin .= '<br /><span class="ui-icon ui-icon-lightbulb" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['error_cpassman_dir'];
            }
        }
        // error cpassman url
        if ($session_validite_pw !== null && (isset($SETTINGS['cpassman_url']) && empty($SETTINGS['cpassman_url']) || !isset($SETTINGS['cpassman_url']))) {
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
    if (isset($SETTINGS['maintenance_mode']) === true && $SETTINGS['maintenance_mode'] === '1'
            && $session_user_admin !== null && $session_user_admin === '1'
        ) {
        echo '
            <div style="text-align:center;margin-bottom:5px;padding:10px;" class="ui-state-highlight ui-corner-all">
                <b>'.$LANG['index_maintenance_mode_admin'].'</b>
            </div>';
    }
// Display UPDATE NEEDED information
    if (isset($SETTINGS['update_needed']) && $SETTINGS['update_needed'] === true
            && $session_user_admin !== null && $session_user_admin === '1'
            && (($session_hide_maintenance !== null && $session_hide_maintenance === '0')
            || $session_hide_maintenance === null)
        ) {
        echo '
            <div style="text-align:center;margin-bottom:5px;padding:10px;"
                class="ui-state-highlight ui-corner-all" id="div_maintenance">
                <b>'.$LANG['update_needed_mode_admin'].'</b>
                <span style="float:right;cursor:pointer;">
                    <span class="fa fa-close mi-red" onclick="toggleDiv(\'div_maintenance\')"></span>
                </span>
            </div>';
    }

// display an item in the context of OTV link
    if (($session_validite_pw === null || empty($session_validite_pw) === true || empty($session_user_id) === true) &&
        isset($_GET['otv']) && filter_var($_GET['otv'], FILTER_SANITIZE_STRING) === 'true'
    ) {
        // case where one-shot viewer
        if (isset($_GET['code']) && !empty($_GET['code'])
            && isset($_GET['stamp']) && !empty($_GET['stamp'])
        ) {
            include 'otv.php';
        } else {
            $_SESSION['error']['code'] = ERR_VALID_SESSION;
            $superGlobal->put(
                "initial_url",
                filter_var(
                    substr($server_request_uri, strpos($server_request_uri, "index.php?")),
                    FILTER_SANITIZE_URL
                ),
                "SESSION"
            );
            include $SETTINGS['cpassman_dir'].'/error.php';
        }
    // Ask the user to change his password
    } elseif (($session_validite_pw === null || $session_validite_pw === false)
        && empty($session_user_id) === false
    ) {
        //Check if password is valid
        echo '
        <div style="margin:auto; padding:20px; width:500px;" class="ui-state-focus ui-corner-all">
            <h3>'.$LANG['index_change_pw'].'</h3>
            <div style="height:20px;text-align:center;margin:2px;display:none;" id="change_pwd_error" class=""></div>
            <div style="text-align:center;margin:5px;padding:3px;" id="change_pwd_complexPw" class="ui-widget ui-state-active ui-corner-all">'.
            $LANG['complex_asked'].' : '.$SETTINGS_EXT['pwComplexity'][$_SESSION['user_pw_complexity']][1].
            '</div>
            <div id="pw_strength" style="margin:0 0 10px 140px;"></div>
            <table>
                <tr>
                    <td>'.$LANG['index_new_pw'].' :</td><td><input type="password" size="15" name="new_pw" id="new_pw"/></td>
                </tr>
                <tr><td>'.$LANG['index_change_pw_confirmation'].' :</td><td><input type="password" size="15" name="new_pw2" id="new_pw2" onkeypress="if (event.keyCode == 13) ChangeMyPass();" /></td></tr>
            </table>
            <input type="hidden" id="pw_strength_value" />
            <div style="width:420px; text-align:center; margin:15px 0 10px 0;">
                <input type="button" onClick="ChangeMyPass()" onkeypress="if (event.keyCode == 13) ChangeMyPass();" class="ui-state-default ui-corner-all" style="padding:4px;width:150px;margin:10px 0 0 80px;" value="'.$LANG['index_change_pw_button'].'" />
            </div>
        </div>
        <script type="text/javascript">
            $("#new_pw").focus();
        </script>';
    // Display pages
    } elseif ($session_validite_pw !== null
        && $session_validite_pw === true
        && empty($_GET['page']) === false
        && empty($session_user_id) === false
    ) {
        if (!extension_loaded('mcrypt')) {
            $_SESSION['error']['code'] = ERR_NO_MCRYPT;
            include $SETTINGS['cpassman_dir'].'/error.php';
        } elseif ($session_initial_url !== null && empty($session_initial_url) === false) {
            include $session_initial_url;
        } elseif ($_GET['page'] == "items") {
            // SHow page with Items
            if (($session_user_admin !== '1')
                ||
                ($session_user_admin === '1' && $SETTINGS_EXT['admin_full_right'] === false)
            ) {
                include 'items.php';
            } else {
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $SETTINGS['cpassman_dir'].'/error.php';
            }
        } elseif ($_GET['page'] == "find") {
            // Show page for items findind
            include 'find.php';
        } elseif ($_GET['page'] == "favourites") {
            // Show page for user favourites
            include 'favorites.php';
        } elseif ($_GET['page'] == "kb") {
            // Show page KB
            if (isset($SETTINGS['enable_kb']) && $SETTINGS['enable_kb'] == 1) {
                include 'kb.php';
            } else {
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $SETTINGS['cpassman_dir'].'/error.php';
            }
        } elseif ($_GET['page'] == "suggestion") {
            // Show page KB
            if (isset($SETTINGS['enable_suggestion']) && $SETTINGS['enable_suggestion'] == 1) {
                include 'suggestion.php';
            } else {
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $SETTINGS['cpassman_dir'].'/error.php';
            }
        } elseif (in_array($_GET['page'], array_keys($mngPages))) {
            // Define if user is allowed to see management pages
            if ($session_user_admin === '1') {
                include($mngPages[$_GET['page']]);
            } elseif ($session_user_manager === '1') {
                if (($_GET['page'] != "manage_main" && $_GET['page'] != "manage_settings")) {
                    include($mngPages[$_GET['page']]);
                } else {
                    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                    include $SETTINGS['cpassman_dir'].'/error.php';
                }
            } else {
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $SETTINGS['cpassman_dir'].'/error.php';
            }
        } else {
            $_SESSION['error']['code'] = ERR_NOT_EXIST; //page doesn't exist
            include $SETTINGS['cpassman_dir'].'/error.php';
        }
    // Case of password recovery
    } elseif (isset($_GET['action']) && $_GET['action'] === "password_recovery") {
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
                    <div id="ajax_loader_send_mail" style="display:none; margin: 20px;"><span class="fa fa-cog fa-spin fa-2x"></span></div>
                </div>
                <div style="margin-top:30px; text-align:center;">
                    <a href="index.php" class="tip" title="'.$LANG['home'].'"><span class="fa fa-home fa-lg"></span></a>
                </div>
            </div>';
    } elseif (empty($session_user_id) === false && $session_user_id !== null) {
        // Page doesn't exist
        $_SESSION['error']['code'] = ERR_NOT_EXIST;
        include $SETTINGS['cpassman_dir'].'/error.php';
        // When user is not identified
    } else {
        // Automatic redirection
        if (strpos($server_request_uri, "?") > 0) {
            $nextUrl = filter_var(substr($server_request_uri, strpos($server_request_uri, "?")), FILTER_SANITIZE_URL);
        } else {
            $nextUrl = "";
        }
        // MAINTENANCE MODE
        if (isset($SETTINGS['maintenance_mode']) === true && $SETTINGS['maintenance_mode'] === '1') {
            echo '
                <div style="text-align:center;margin-top:30px;margin-bottom:20px;padding:10px;"
                    class="ui-state-error ui-corner-all">
                    <b>'.addslashes($LANG['index_maintenance_mode']).'</b>
                </div>';
        } elseif (isset($_GET['session_over']) && $_GET['session_over'] === 'true') {
            // SESSION FINISHED => RECONNECTION ASKED
            echo '
                    <div style="text-align:center;margin-top:30px;margin-bottom:20px;padding:10px;"
                        class="ui-state-error ui-corner-all">
                        <b>'.addslashes($LANG['index_session_expired']).'</b>
                    </div>';
        }

        // case where user not logged and can't access a direct link
        if (empty($_GET['page']) === false) {
            $superGlobal->put(
                "initial_url",
                filter_var(
                    substr($server_request_uri, strpos($server_request_uri, "index.php?")),
                    FILTER_SANITIZE_URL
                ),
                "SESSION"
            );
            // REDIRECTION PAGE ERREUR
            echo '
            <script language="javascript" type="text/javascript">
            <!--
                sessionStorage.clear();
                window.location.href = "index.php";
            -->
            </script>';
            exit;
        } else {
            $superGlobal->put("initial_url", '', "SESSION");
        }

        // CONNECTION FORM
        echo '
                <form method="post" name="form_identify" id="form_identify" action="">
                    <div style="width:480px;margin:10px auto 10px auto;padding:25px;" class="ui-state-highlight ui-corner-all">
                        <div style="text-align:center;font-weight:bold;margin-bottom:20px;">',
        isset($SETTINGS['custom_logo']) && !empty($SETTINGS['custom_logo']) ? '<img src="'.(string) $SETTINGS['custom_logo'].'" alt="" style="margin-bottom:40px;" />' : '', '<br />
                            '.$LANG['index_get_identified'].'
                            <span id="ajax_loader_connexion" style="display:none;margin-left:10px;"><span class="fa fa-cog fa-spin fa-1x"></span></span>
                        </div>
                        <div id="connection_error" style="display:none;text-align:center;margin:5px; padding:3px;" class="ui-state-error ui-corner-all">&nbsp;<i class="fa fa-warning"></i>&nbsp;'.$LANG['index_bas_pw'].'</div>';
        echo '
                        <div style="margin-bottom:3px;">
                            <label for="login" class="form_label">', isset($SETTINGS['custom_login_text']) && !empty($SETTINGS['custom_login_text']) ? (string) $SETTINGS['custom_login_text'] : $LANG['index_login'], '</label>
                            <input type="text" size="10" id="login" name="login" class="input_text text ui-widget-content ui-corner-all" />
                            <span id="login_check_wait" style="display:none; float:right;"><i class="fa fa-cog fa-spin fa-1x"></i></span>
                        </div>';

        // AGSES
        if (isset($SETTINGS['agses_authentication_enabled']) && $SETTINGS['agses_authentication_enabled'] == 1) {
            echo '
                        <div id="agses_cardid_div" style="text-align:center; display:none; padding:5px; width:454px; margin-bottom:5px;" class="ui-state-active ui-corner-all">
                            '.$LANG['user_profile_agses_card_id'].': &nbsp;
                            <input type="text" size="12" id="agses_cardid">
                        </div>
                        <div id="agses_flickercode_div" style="text-align:center; display:none;">
                            <canvas id="axs_canvas"></canvas>
                        </div>';
        }

                        echo '
                        <div id="connect_pw" style="margin-bottom:3px;">
                            <label for="pw" class="form_label" id="user_pwd">'.$LANG['index_password'].'</label>
                            <input type="password" size="10" id="pw" name="pw" onkeypress="if (event.keyCode == 13) launchIdentify(\'', isset($SETTINGS['duo']) && $SETTINGS['duo'] === "1" ? 1 : '', '\', \''.$nextUrl.'\', \'', isset($SETTINGS['google_authentication']) && $SETTINGS['google_authentication'] === "1" ? 1 : '', '\')" class="input_text text ui-widget-content ui-corner-all" />
                        </div>';

        // Personal salt key
        if (isset($SETTINGS['psk_authentication']) && $SETTINGS['psk_authentication'] === "1") {
            echo '
                        <div id="connect_psk" style="margin-bottom:3px;">
                            <label for="personal_psk" class="form_label">'.$LANG['home_personal_saltkey'].'</label>
                            <input type="password" size="10" id="psk" name="psk" onkeypress="if (event.keyCode == 13) launchIdentify(\'', isset($SETTINGS['duo']) && $SETTINGS['duo'] === "1" ? 1 : '', '\', \''.$nextUrl.'\', \'', isset($SETTINGS['psk_authentication']) && $SETTINGS['psk_authentication'] === "1" ? 1 : '', '\')" class="input_text text ui-widget-content ui-corner-all" />
                        </div>
                        <div id="connect_psk_confirm" style="margin-bottom:3px; display:none;">
                            <label for="psk_confirm" class="form_label">'.$LANG['home_personal_saltkey_confirm'].'</label>
                            <input type="password" size="10" id="psk_confirm" name="psk_confirm" onkeypress="if (event.keyCode == 13) launchIdentify(\'', isset($SETTINGS['duo']) && $SETTINGS['duo'] === "1" ? 1 : '', '\', \''.$nextUrl.'\', \'', isset($SETTINGS['psk_authentication']) && $SETTINGS['psk_authentication'] === "1" ? 1 : '', '\')" class="input_text text ui-widget-content ui-corner-all" />
                        </div>';
        }

        // Google Authenticator code
        if (isset($SETTINGS['google_authentication']) && $SETTINGS['google_authentication'] === "1") {
            echo '
                        <div id="ga_code_div" style="margin-bottom:10px;">
                            '.$LANG['ga_identification_code'].'
                            <input type="text" size="4" id="ga_code" name="ga_code" style="margin:0px;" class="input_text text ui-widget-content ui-corner-all numeric_only" onkeypress="if (event.keyCode == 13) launchIdentify(\'', isset($SETTINGS['duo']) && $SETTINGS['duo'] === "1" ? 1 : '', '\', \''.$nextUrl.'\')" />
                        <div id="2fa_new_code_div" style="text-align:center; display:none; margin-top:5px; padding:5px;" class="ui-state-default ui-corner-all"></div>
                        <div style="margin-top:2px; font-size:10px; text-align:center; cursor:pointer;" onclick="send_user_new_temporary_ga_code()">'.$LANG['i_need_to_generate_new_ga_code'].'</div>
                        </div>';
        }
        echo '
                        <div style="margin-bottom:3px;">
                            <label for="duree_session" class="">'.$LANG['index_session_duration'].'&nbsp;('.$LANG['minutes'].') </label>
                            <input type="text" size="4" id="duree_session" name="duree_session" value="', isset($SETTINGS['default_session_expiration_time']) ? $SETTINGS['default_session_expiration_time'] : "60", '" onkeypress="if (event.keyCode == 13) launchIdentify(\'', isset($SETTINGS['duo']) && $SETTINGS['duo'] === "1" ? 1 : '', '\', \''.$nextUrl.'\')" class="input_text text ui-widget-content ui-corner-all numeric_only" />
                        </div>

                        <div style="text-align:center;margin-top:5px;font-size:10pt;">
                            <span onclick="OpenDialog(\'div_forgot_pw\')" style="padding:3px;cursor:pointer;">'.$LANG['forgot_my_pw'].'</span>
                        </div>
                        <div style="text-align:center;margin-top:15px;">
                            <input type="button" id="but_identify_user" onclick="launchIdentify(\'', isset($SETTINGS['duo']) && $SETTINGS['duo'] === "1" ? 1 : '', '\', \''.$nextUrl.'\', \'', isset($SETTINGS['psk_authentication']) && $SETTINGS['psk_authentication'] === "1" ? 1 : '', '\')" style="padding:3px;cursor:pointer;" class="ui-state-default ui-corner-all" value="'.$LANG['index_identify_button'].'" />
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
                    <div id="div_forgot_pw_status" style="text-align:center;margin-top:15px;display:none; padding:5px;" class="ui-corner-all">
                        <i class="fa fa-cog fa-spin fa-2x"></i>&nbsp;<b>'.$LANG['please_wait'].'</b>
                    </div>
                </div>';
    }
    echo '
    </div>';
// FOOTER
/* DON'T MODIFY THE FOOTER ... MANY THANKS TO YOU */
    echo '
    <div id="footer">
        <div style="float:left;width:32%;">
            <a href="http://teampass.net" target="_blank" style="color:#F0F0F0;">'.$SETTINGS_EXT['tool_name'].'&nbsp;'.$SETTINGS_EXT['version'].'&nbsp;<i class="fa fa-copyright"></i>&nbsp;'.$SETTINGS_EXT['copyright'].'</a>
            &nbsp;|&nbsp;
            <a href="http://teampass.readthedocs.io/en/latest/" target="_blank" style="color:#F0F0F0;" class="tip" title="'.addslashes($LANG['documentation_canal']).' ReadTheDocs"><i class="fa fa-book"></i></a>
            &nbsp;
            <a href="https://www.reddit.com/r/TeamPass/" target="_blank" style="color:#F0F0F0;" class="tip" title="'.addslashes($LANG['admin_help']).'"><i class="fa fa-reddit-alien"></i></a>
        </div>
        <div style="float:left;width:32%;text-align:center;">
            ', ($session_user_id !== null && empty($session_user_id) === false) ? '<i class="fa fa-users"></i>&nbsp;'.$_SESSION['nb_users_online'].'&nbsp;'.$LANG['users_online'].'&nbsp;|&nbsp;<i class="fa fa-hourglass-end"></i>&nbsp;'.$LANG['index_expiration_in'].'&nbsp;<div style="display:inline;" id="countdown"></div>' : '', '
        </div><div id="countdown2"></div>
        <div style="float:right;text-align:right;">
            <i class="fa fa-clock-o"></i>&nbsp;'. $LANG['server_time']." : ".@date($SETTINGS['date_format'], (string) $_SERVER['REQUEST_TIME'])." - ".@date($SETTINGS['time_format'], (string) $_SERVER['REQUEST_TIME']).'
        </div>
    </div>';
// PAGE LOADING
    echo '
    <div id="div_loading" class="hidden">
        <div style="padding:5px; z-index:9999999;" class="ui-widget-content ui-state-focus ui-corner-all">
            <i class="fa fa-cog fa-spin fa-2x"></i>
        </div>
    </div>';
// Alert BOX
    echo '
    <div id="div_dialog_message" style="display:none;">
        <div id="div_dialog_message_text" style="text-align:center; padding:4px; font-size:12px; margin-top:10px;"></div>
    </div>';

// WARNING FOR QUERY ERROR
    echo '
    <div id="div_mysql_error" style="display:none;">
        <div style="padding:10px;text-align:center;" id="mysql_error_warning"></div>
    </div>';


//Personnal SALTKEY
    if (isset($SETTINGS['enable_pf_feature']) && $SETTINGS['enable_pf_feature'] === "1") {
        echo '
        <div id="div_set_personal_saltkey" style="display:none;padding:4px;">
            <i class="fa fa-key"></i> <b>'.$LANG['home_personal_saltkey'].'</b>
            <input type="password" name="input_personal_saltkey" id="input_personal_saltkey" style="width:200px;padding:5px;margin-left:30px;" class="text ui-widget-content ui-corner-all text_without_symbols tip" value="', isset($_SESSION['user_settings']['clear_psk']) ? (string) $_SESSION['user_settings']['clear_psk'] : '', '" title="<i class=\'fa fa-bullhorn\'></i>&nbsp;'.$LANG['text_without_symbols'].'" />
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
    <form method="post" id="duo_form" action="#">
        <input type="hidden" id="duo_login" name="duo_login" value="', null !== $post_duo_login ? $post_duo_login : '', '" />
        <input type="hidden" id="duo_data" name="duo_data" value="', null !== $post_duo_data ? htmlentities(base64_decode($post_duo_data)) : '', '" />
    </form>
</div>';

// INCREASE session time
    echo '
<div id="div_increase_session_time" style="display:none;padding:4px;">
    <b>'.$LANG['index_session_duration'].':</b>
    <input type="text" id="input_session_duration" style="width:50px;padding:5px;margin:0 10px 0 10px;" class="text ui-widget-content ui-corner-all" value="', isset($_SESSION['user_settings']['session_duration']) ? (int) $_SESSION['user_settings']['session_duration'] / 60 : 60, '" />
    <b>'.$LANG['minutes'].'</b>
    <div style="display:none;margin-top:5px;text-align:center;padding:4px;" id="input_session_duration_warning" class="ui-widget-content ui-state-error ui-corner-all"></div>
</div>';

    closelog();

?>
<script type="text/javascript">NProgress.start();</script>
    </body>
</html>
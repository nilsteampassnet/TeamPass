<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      index.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use voku\helper\AntiXSS;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

// Security Headers
header('X-XSS-Protection: 1; mode=block');
// deepcode ignore TooPermissiveXFrameOptions: Not the case as sameorigin is used
header('X-Frame-Options: SameOrigin');

// Cache Headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// **PREVENTING SESSION HIJACKING**
// Prevents javascript XSS attacks aimed to steal the session ID
//ini_set('session.cookie_httponly', 1);
// **PREVENTING SESSION FIXATION**
// Session ID cannot be passed through URLs
//ini_set('session.use_only_cookies', 1);
// Uses a secure connection (HTTPS) if possible
//ini_set('session.cookie_secure', 0);
//ini_set('session.cookie_samesite', 'Lax');
// Before we start processing, we should abort no install is present
if (file_exists(__DIR__.'/includes/config/settings.php') === false) {
    // This should never happen, but in case it does
    // this means if headers are sent, redirect will fallback to JS
    if (headers_sent()) {
        echo '<script language="javascript" type="text/javascript">document.location.replace("install/install.php");</script>';
    } else {
        header('Location: install/install.php');
    }
    // Now either way, we should stop processing further
    exit;
}


// initialise CSRFGuard library
require_once __DIR__.'/includes/libraries/csrfp/libs/csrf/csrfprotector.php';
csrfProtector::init();

// Load functions
require_once __DIR__. '/includes/config/include.php';
require_once __DIR__.'/sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$session->set('key', SessionManager::getCookieValue('PHPSESSID'));
// PHPSESSID isn't sent on first query.
if ($session->get('key') == null) {
    header('Refresh: 0');
    exit;
}
$request = SymfonyRequest::createFromGlobals();
$configManager = new ConfigManager(__DIR__, $request->getRequestUri());
$SETTINGS = $configManager->getAllSettings();
$antiXss = new AntiXSS();
$session->set('encryptClientServer', (int) $SETTINGS['encryptClientServer'] ?? 1);

// Quick major version check -> upgrade needed?
if (isset($SETTINGS['teampass_version']) === true && version_compare(TP_VERSION, $SETTINGS['teampass_version']) > 0) {
    // Perform redirection
    if (headers_sent()) {
        echo '<script language="javascript" type="text/javascript">document.location.replace("install/install.php");</script>';
    } else {
        header('Location: install/upgrade.php');
    }
    // No other way, we should stop processing further
    exit;
}


$SETTINGS = $antiXss->xss_clean($SETTINGS);

// Load Core library
require_once $SETTINGS['cpassman_dir'] . '/sources/core.php';
// Prepare POST variables
$post_language = filter_input(INPUT_POST, 'language', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$session_user_language = $session->get('user-language');
$session_user_admin = $session->get('user-admin');
$session_user_human_resources = (int) $session->get('user-can_manage_all_users');
$session_name = $session->get('user-name');
$session_lastname = $session->get('user-lastname');
$session_user_manager = (int) $session->get('user-manager');
$session_initial_url = $session->get('user-initial_url');
$session_nb_users_online = $session->get('system-nb_users_online');
$session_auth_type = $session->get('user-auth_type');

$server = [];
$server['request_uri'] = (string) $request->getRequestUri();
$server['request_time'] = (int) $request->server->get('REQUEST_TIME');

$get = [];
$get['page'] = $request->query->get('page') === null ? '' : $antiXss->xss_clean($request->query->get('page'));
$get['otv'] = $request->query->get('otv') === null ? '' : $antiXss->xss_clean($request->query->get('otv'));

// Avoid blank page and session destroy if user go to index.php without ?page=
if (empty($get['page']) && !empty($session_name)) {
    if ($session_user_admin === 1) {
        $redirect_page = 'admin';
    } else {
        $redirect_page = 'items';
    }

    // Redirect user on default page.
    header('Location: index.php?page='.$redirect_page);
    exit();
}

/* DEFINE WHAT LANGUAGE TO USE */
if (null === $session->get('user-validite_pw') && $post_language === null && $session_user_language === null) {
    //get default language
    $dataLanguage = DB::queryFirstRow(
        'SELECT m.valeur AS valeur, l.flag AS flag
        FROM ' . prefixTable('misc') . ' AS m
        INNER JOIN ' . prefixTable('languages') . ' AS l ON (m.valeur = l.name)
        WHERE m.type=%s_type AND m.intitule=%s_intitule',
        [
            'type' => 'admin',
            'intitule' => 'default_language',
        ]
    );
    if (empty($dataLanguage['valeur'])) {
        $session->set('user-language', 'english');
        $session->set('user-language_flag', 'us.png');
        $session_user_language = 'english';
    } else {
        $session->set('user-language', $dataLanguage['valeur']);
        $session->set('user-language_flag', $dataLanguage['flag']);
        $session_user_language = $dataLanguage['valeur'];
    }
} elseif (isset($SETTINGS['default_language']) === true && $session_user_language === null) {
    $session->set('user-language', $SETTINGS['default_language']);
    $session_user_language = $SETTINGS['default_language'];
} elseif ($post_language !== null) {
    $session->set('user-language', $post_language);
    $session_user_language = $post_language;
} elseif ($session_user_language === null || empty($session_user_language) === true) {
    if ($post_language !== null) {
        $session->set('user-language', $post_language);
        $session_user_language = $post_language;
    } elseif ($session_user_language !== null) {
        $session->set('user-language', $SETTINGS['default_language']);
        $session_user_language = $SETTINGS['default_language'];
    }
}
$lang = new Language($session_user_language, __DIR__. '/includes/language/'); 

if (isset($SETTINGS['cpassman_dir']) === false || $SETTINGS['cpassman_dir'] === '') {
    $SETTINGS['cpassman_dir'] = __DIR__;
    $SETTINGS['cpassman_url'] = (string) $server['request_uri'];
}

// Get the URL
$cpassman_url = isset($SETTINGS['cpassman_url']) ? $SETTINGS['cpassman_url'] : '';
// URL validation
if (!filter_var($cpassman_url, FILTER_VALIDATE_URL)) {
    $cpassman_url = '';
}
// Sanitize the URL to prevent XSS
$cpassman_url = htmlspecialchars($cpassman_url, ENT_QUOTES, 'UTF-8');

// Some template adjust
if (array_key_exists($get['page'], $mngPages) === true) {
    $menuAdmin = true;
} else {
    $menuAdmin = false;
}

// Some template adjust
if (array_key_exists($get['page'], $utilitiesPages) === true) {
    $menuUtilities = true;
} else {
    $menuUtilities = false;
}

// Get the favicon
$favicon = isset($SETTINGS['favicon']) ? $SETTINGS['favicon'] : '';
// URL Validation
if (!filter_var($favicon, FILTER_VALIDATE_URL)) {
    $favicon = '';
}
// Sanitize the URL to prevent XSS
$favicon = htmlspecialchars($favicon, ENT_QUOTES, 'UTF-8');

// Define the date and time format
$date_format = isset($SETTINGS['date_format']) ? $SETTINGS['date_format'] : 'Y-m-d';
$time_format = isset($SETTINGS['time_format']) ? $SETTINGS['time_format'] : 'H:i:s';

// Force dark theme on page generation
$theme = $_COOKIE['teampass_theme'] ?? 'light';
$theme_body = $theme === 'dark' ? 'dark-mode' : '';
$theme_meta = $theme === 'dark' ? '#343a40' : '#fff';
$theme_navbar = $theme === 'dark' ? 'navbar-dark' : 'navbar-white navbar-light';

?>
<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>

<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>

<head>
    <meta http-equiv='Content-Type' content='text/html;charset=utf-8' />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta http-equiv="x-ua-compatible" content="ie=edge" />
    <meta name="theme-color" content="<?php echo $theme_meta; ?>" />
    <title><?php echo $configManager->getSetting('teampass_title') ?? 'Teampass'; ?></title>
    <script type='text/javascript'>
        //<![CDATA[
        if (window.location.href.indexOf('page=') === -1 &&
            (window.location.href.indexOf('otv=') === -1 &&
                window.location.href.indexOf('action=') === -1)
        ) {
            if (window.location.href.indexOf('session_over=true') !== -1) {
                location.replace('./includes/core/logout.php');
            }
        }
        //]]>
    </script>

    <!-- IonIcons -->
    <link rel="stylesheet" href="includes/css/ionicons.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <!-- Theme style -->
    <link rel="stylesheet" href="plugins/adminlte/css/adminlte.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <link rel="stylesheet" href="plugins/pace-progress/themes/corner-indicator.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/css" />
    <link rel="stylesheet" href="plugins/select2/css/select2.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/css" />
    <link rel="stylesheet" href="plugins/select2/theme/select2-bootstrap4.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/css" />
    <!-- Theme style -->
    <link rel="stylesheet" href="includes/css/teampass.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" type="text/css" href="includes/fonts/fonts.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <!-- Altertify -->
    <link rel="stylesheet" href="plugins/alertifyjs/css/alertify.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" />
    <link rel="stylesheet" href="plugins/alertifyjs/css/themes/bootstrap.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" />
    <!-- Toastr -->
    <link rel="stylesheet" href="plugins/toastr/toastr.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" />
    <!-- favicon -->
    <link rel="shortcut icon" type="image/png" href="<?php echo $favicon;?>"/>
    <!-- manifest (PWA) -->
    <link rel="manifest" href="manifest.json?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <!-- Custom style -->
    <?php
    if (file_exists(__DIR__ . '/includes/css/custom.css') === true) {?>
        <link rel="stylesheet" href="includes/css/custom.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <?php
    } ?>
</head>




<?php
// display an item in the context of OTV link
if ((null === $session->get('user-validite_pw') || empty($session->get('user-validite_pw')) === true || empty($session->get('user-id')) === true)
    && empty($get['otv']) === false)
{
    include './includes/core/otv.php';
    exit;
} elseif ($session->has('user-validite_pw') && null !== $session->get('user-validite_pw') && ($session->get('user-validite_pw') === 0 || $session->get('user-validite_pw') === 1)
    && empty($get['page']) === false && empty($session->get('user-id')) === false
) {
    ?>
    <body class="hold-transition sidebar-mini layout-navbar-fixed layout-fixed <?php echo $theme_body; ?>">
        <div class="wrapper">

            <!-- Navbar -->
            <nav class="main-header navbar navbar-expand <?php echo $theme_navbar ?>">
                <!-- User encryption still ongoing -->
                <div id="user_not_ready" class="alert alert-warning hidden pointer p-2 mt-2" style="position:absolute; left:200px;">
                    <span class="align-middle infotip ml-2" title="<?php echo $lang->get('keys_encryption_not_ready'); ?>"><?php echo $lang->get('account_not_ready'); ?><span id="user_not_ready_progress"></span><i class="fa-solid fa-hourglass-half fa-beat-fade mr-2 ml-2"></i></span>
                </div>

                <!-- Left navbar links -->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-widget="pushmenu" href="#"><i class="fa-solid fa-bars"></i></a>
                    </li>
                </ul>

                <!-- Right navbar links -->
                <ul class="navbar-nav ml-auto">
                    <span class="fa-stack infotip pointer hidden mr-2" title="<?php echo $lang->get('get_your_recovery_keys'); ?>" id="open_user_keys_management" style="vertical-align: top;">
                        <i class="fa-solid fa-circle text-danger fa-stack-2x"></i>
                        <i class="fa-solid fa-bell fa-shake fa-stack-1x fa-inverse"></i>
                    </span>
                    <!-- Messages Dropdown Menu -->
                    <li class="nav-item dropdown">
                        <div class="dropdown show">
                            <a class="btn btn-primary dropdown-toggle" href="#" data-toggle="dropdown">
                                <?php
                                    echo $session_name . '&nbsp;' . $session_lastname; ?>
                            </a>

                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item user-menu" href="#" data-name="increase_session">
                                    <i class="far fa-clock fa-fw mr-2"></i><?php echo $lang->get('index_add_one_hour'); ?></a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item user-menu" href="#" data-name="profile">
                                    <i class="fa-solid fa-user-circle fa-fw mr-2"></i><?php echo $lang->get('my_profile'); ?>
                                </a>
                                <?php
                                    if (empty($session_auth_type) === false && $session_auth_type !== 'ldap' && $session_auth_type !== 'oauth2') {
                                        ?>
                                    <a class="dropdown-item user-menu" href="#" data-name="password-change">
                                        <i class="fa-solid fa-lock fa-fw mr-2"></i><?php echo $lang->get('index_change_pw'); ?>
                                    </a>
                                <?php
                                    } elseif ($session_auth_type === 'ldap') {
                                        ?>
                                    <a class="dropdown-item user-menu" href="#" data-name="sync-new-ldap-password">
                                        <i class="fa-solid fa-key fa-fw mr-2"></i><?php echo $lang->get('sync_new_ldap_password'); ?>
                                    </a>
                                <?php
                                    } ?>
                                <a class="dropdown-item user-menu<?php echo (int) $session_user_admin === 1 ? ' hidden' : '';?>" href="#" data-name="generate-new_keys">
                                    <i class="fa-solid fa-spray-can-sparkles fa-fw mr-2"></i><?php echo $lang->get('generate_new_keys'); ?>
                                </a>

                                <!--
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item user-menu" href="#" data-name="generate-an-otp">
                                    <i class="fa-solid fa-qrcode fa-fw mr-2"></i><?php echo $lang->get('generate_an_otp'); ?>
                                </a>
                                -->

                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item user-menu" href="#" data-name="logout">
                                    <i class="fa-solid fa-sign-out-alt fa-fw mr-2"></i><?php echo $lang->get('disconnect'); ?>
                                </a>
                            </div>
                        </div>
                    </li>
                    <li>
                        <span class="align-middle infotip ml-2 text-info" title="<?php echo $lang->get('index_expiration_in'); ?>" id="countdown"></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#" id="controlsidebar"><i class="fa-solid fa-th-large"></i></a>
                    </li>
                    <li id="switch-theme" class="nav-item pointer">
                        <i class="fa-solid fa-circle-half-stroke m-2 m-2"></i>
                    </li>
                </ul>
            </nav>
            <!-- /.navbar -->

            <!-- Main Sidebar Container -->
            <aside class="main-sidebar sidebar-dark-primary elevation-4">
                <!-- Brand Logo -->
                <a href="<?php echo $cpassman_url . '/index.php?page=' . ((int) $session_user_admin === 1 ? 'admin' : 'items'); ?>" class="brand-link">
                    <img src="includes/images/teampass-logo2-home.png" alt="Teampass Logo" class="brand-image">
                    <span class="brand-text font-weight-light"><?php echo TP_TOOL_NAME; ?></span>
                </a>

                <!-- Sidebar -->
                <div class="sidebar">
                    <!-- Sidebar Menu -->
                    <nav class="mt-2" style="margin-bottom:40px;">
                        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                            <?php
                                if ($session_user_admin === 0) {
                                    // ITEMS & SEARCH
                                    echo '
                    <li class="nav-item">
                        <a href="#" data-name="items" class="nav-link', $get['page'] === 'items' ? ' active' : '', '">
                        <i class="nav-icon fa-solid fa-key"></i>
                        <p>
                            ' . $lang->get('pw') . '
                        </p>
                        </a>
                    </li>';
                                }

    // IMPORT menu
    if (isset($SETTINGS['allow_import']) === true && (int) $SETTINGS['allow_import'] === 1&& $session_user_admin === 0) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="import" class="nav-link', $get['page'] === 'import' ? ' active' : '', '">
                        <i class="nav-icon fa-solid fa-file-import"></i>
                        <p>
                            ' . $lang->get('import') . '
                        </p>
                        </a>
                    </li>';
    }
    // EXPORT menu
    if (
                                    isset($SETTINGS['allow_print']) === true && (int) $SETTINGS['allow_print'] === 1
                                    && isset($SETTINGS['roles_allowed_to_print_select']) === true
                                    && empty($SETTINGS['roles_allowed_to_print_select']) === false
                                    && count(array_intersect(
                                        explode(';', $session->get('user-roles')),
                                        explode(',', str_replace(['"', '[', ']'], '', $SETTINGS['roles_allowed_to_print_select']))
                                    )) > 0
                                    && (int) $session_user_admin === 0
                                ) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="export" class="nav-link', $get['page'] === 'export' ? ' active' : '', '">
                        <i class="nav-icon fa-solid fa-file-export"></i>
                        <p>
                            ' . $lang->get('export') . '
                        </p>
                        </a>
                    </li>';
    }

    /*
    // OFFLINE MODE menu
    if (isset($SETTINGS['settings_offline_mode']) === true && (int) $SETTINGS['settings_offline_mode'] === 1) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="offline" class="nav-link', $get['page'] === 'offline' ? ' active' : '' ,'">
                        <i class="nav-icon fa-solid fa-plug"></i>
                        <p>
                            '.$lang->get('offline').'
                        </p>
                        </a>
                    </li>';
    }
    */

    if ($session_user_admin === 0) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="search" class="nav-link', $get['page'] === 'search' ? ' active' : '', '">
                        <i class="nav-icon fa-solid fa-search"></i>
                        <p>
                            ' . $lang->get('find') . '
                        </p>
                        </a>
                    </li>';
    }

    // Favourites menu
    if (
                                    isset($SETTINGS['enable_favourites']) === true && (int) $SETTINGS['enable_favourites'] === 1
                                    && (int) $session_user_admin === 0
                                ) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="favourites" class="nav-link', $get['page'] === 'favourites' ? ' active' : '', '">
                        <i class="nav-icon fa-solid fa-star"></i>
                        <p>
                            ' . $lang->get('favorites') . '
                        </p>
                        </a>
                    </li>';
    }
    /*
        // KB menu
        if (isset($SETTINGS['enable_kb']) === true && $SETTINGS['enable_kb'] === '1'
        ) {
            echo '
                        <li class="nav-item">
                            <a href="#" data-name="kb" class="nav-link', $get['page'] === 'kb' ? ' active' : '' ,'">
                            <i class="nav-icon fa-solid fa-map-signs"></i>
                            <p>
    '.$lang->get('kb_menu').'
                            </p>
                            </a>
                        </li>';
        }
    */
    // SUGGESTION menu
    if (
                                    isset($SETTINGS['enable_suggestion']) && (int) $SETTINGS['enable_suggestion'] === 1
                                    && $session_user_manager === 1
                                ) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="suggestion" class="nav-link', $get['page'] === 'suggestion' ? ' active' : '', '">
                        <i class="nav-icon fa-solid fa-lightbulb"></i>
                        <p>
                            ' . $lang->get('suggestion_menu') . '
                        </p>
                        </a>
                    </li>';
    }

    // Admin menu
    if ($session_user_admin === 1) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="admin" class="nav-link', $get['page'] === 'admin' ? ' active' : '', '">
                        <i class="nav-icon fa-solid fa-info"></i>
                        <p>
                            ' . $lang->get('admin_main') . '
                        </p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview', $menuAdmin === true ? ' menu-open' : '', '">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fa-solid fa-wrench"></i>
                            <p>
                                ' . $lang->get('admin_settings') . '
                                <i class="fa-solid fa-angle-left right"></i>
                            </p>
                        </a>
                        <ul class="nav-item nav-treeview">
                            <li class="nav-item">
                                <a href="#" data-name="options" class="nav-link', $get['page'] === 'options' ? ' active' : '', '">
                                    <i class="fa-solid fa-check-double nav-icon"></i>
                                    <p>' . $lang->get('options') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="2fa" class="nav-link', $get['page'] === '2fa' ? ' active' : '', '">
                                    <i class="fa-solid fa-qrcode nav-icon"></i>
                                    <p>' . $lang->get('mfa_short') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="api" class="nav-link', $get['page'] === 'api' ? ' active' : '', '">
                                    <i class="fa-solid fa-cubes nav-icon"></i>
                                    <p>' . $lang->get('api') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="backups" class="nav-link', $get['page'] === 'backups' ? ' active' : '', '">
                                    <i class="fa-solid fa-database nav-icon"></i>
                                    <p>' . $lang->get('backups') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="emails" class="nav-link', $get['page'] === 'emails' ? ' active' : '', '">
                                    <i class="fa-solid fa-envelope nav-icon"></i>
                                    <p>' . $lang->get('emails') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="fields" class="nav-link', $get['page'] === 'fields' ? ' active' : '', '">
                                    <i class="fa-solid fa-keyboard nav-icon"></i>
                                    <p>' . $lang->get('fields') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="ldap" class="nav-link', $get['page'] === 'ldap' ? ' active' : '', '">
                                    <i class="fa-solid fa-id-card nav-icon"></i>
                                    <p>' . $lang->get('ldap') . '</p>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="#" data-name="oauth" class="nav-link', $get['page'] === 'oauth' ? ' active' : '', '">
                                    <i class="fa-solid fa-plug nav-icon"></i>
                                    <p>' . $lang->get('oauth') . '</p>
                                </a>
                            </li>
                            
                            <li class="nav-item">
                                <a href="#" data-name="uploads" class="nav-link', $get['page'] === 'uploads' ? ' active' : '', '">
                                    <i class="fa-solid fa-file-upload nav-icon"></i>
                                    <p>' . $lang->get('uploads') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="statistics" class="nav-link', $get['page'] === 'statistics' ? ' active' : '', '">
                                    <i class="fa-solid fa-chart-bar nav-icon"></i>
                                    <p>' . $lang->get('statistics') . '</p>
                                </a>
                            </li>
                        </ul>
                    </li>';

        if (isset($SETTINGS['enable_tasks_manager']) && (int) $SETTINGS['enable_tasks_manager'] === 1) {
            echo '
                    <li class="nav-item">
                        <a href="#" data-name="tasks" class="nav-link', $get['page'] === 'tasks' ? ' active' : '', '">
                        <i class="fa-solid fa-tasks nav-icon"></i>
                        <p>' . $lang->get('tasks') . '</p>
                        </a>
                    </li>';
        }
        
        if (WIP === true) {
            echo '
                    <li class="nav-item">
                        <a href="#" data-name="tools" class="nav-link', $get['page'] === 'tools' ? ' active' : '', '">
                        <i class="nav-icon fa-solid fa-screwdriver-wrench"></i>
                        <p>
                            ' . $lang->get('tools') . '
                        </p>
                        </a>
                    </li>';
        }
    }

    if (
        $session_user_admin === 1
        || $session_user_manager === 1
        || $session_user_human_resources === 1
    ) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="folders" class="nav-link', $get['page'] === 'folders' ? ' active' : '', '">
                        <i class="nav-icon fa-solid fa-folder-open"></i>
                        <p>
                            ' . $lang->get('folders') . '
                        </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" data-name="roles" class="nav-link', $get['page'] === 'roles' ? ' active' : '', '">
                        <i class="nav-icon fa-solid fa-graduation-cap"></i>
                        <p>
                            ' . $lang->get('roles') . '
                        </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" data-name="users" class="nav-link', $get['page'] === 'users' ? ' active' : '', '">
                        <i class="nav-icon fa-solid fa-users"></i>
                        <p>
                            ' . $lang->get('users') . '
                        </p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview', $menuUtilities === true ? ' menu-open' : '', '">
                        <a href="#" class="nav-link">
                        <i class="nav-icon fa-solid fa-cubes"></i>
                        <p>' . $lang->get('admin_views') . '<i class="fa-solid fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="#" data-name="utilities.renewal" class="nav-link', $get['page'] === 'utilities.renewal' ? ' active' : '', '">
                                <i class="far fa-calendar-alt nav-icon"></i>
                                <p>' . $lang->get('renewal') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="utilities.deletion" class="nav-link', $get['page'] === 'utilities.deletion' ? ' active' : '', '">
                                <i class="fa-solid fa-trash-alt nav-icon"></i>
                                <p>' . $lang->get('deletion') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="utilities.logs" class="nav-link', $get['page'] === 'utilities.logs' ? ' active' : '', '">
                                <i class="fa-solid fa-history nav-icon"></i>
                                <p>' . $lang->get('logs') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="utilities.database" class="nav-link', $get['page'] === 'utilities.database' ? ' active' : '', '">
                                <i class="fa-solid fa-database nav-icon"></i>
                                <p>' . $lang->get('database') . '</p>
                                </a>
                            </li>
                        </ul>
                    </li>';
    } ?>
                        </ul>
                    </nav>
                    <!-- /.sidebar-menu -->
                <div class="menu-footer">
                    <div class="" id="sidebar-footer">
                        <i class="fa-solid fa-clock-o mr-2 infotip text-info pointer" title="<?php echo htmlspecialchars($lang->get('server_time') . ' ' .
                            date($date_format, (int) $server['request_time']) . ' - ' .
                            date($time_format, (int) $server['request_time']), ENT_QUOTES, 'UTF-8'); ?>"></i>
                        <i class="fa-solid fa-users mr-2 infotip text-info pointer" title="<?php echo $session_nb_users_online . ' ' . $lang->get('users_online'); ?>"></i>
                        <a href="<?php echo DOCUMENTATION_URL; ?>" target="_blank" class="text-info"><i class="fa-solid fa-book mr-2 infotip" title="<?php echo $lang->get('documentation_canal'); ?>"></i></a>
                        <a href="<?php echo HELP_URL; ?>" target="_blank" class="text-info"><i class="fa-solid fa-life-ring mr-2 infotip" title="<?php echo $lang->get('admin_help'); ?>"></i></a>
                        <?php if ($session_user_admin === 1) : ?><i class="fa-solid fa-bug infotip pointer text-info" title="<?php echo $lang->get('bugs_page'); ?>" onclick="generateBugReport()"></i><?php endif; ?>
                    </div>
                    <?php
    ?>
                </div>
                </div>
                <!-- /.sidebar -->
            </aside>

            <!-- Content Wrapper. Contains page content -->
            <div class="content-wrapper">

                <!-- DEFECT REPORT -->
                <div class="card card-danger m-2 hidden" id="dialog-bug-report">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa-solid fa-bug mr-2"></i>
                            <?php echo $lang->get('defect_report'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-2 alert alert-info">
                                    <i class="icon fa-solid fa-info mr-2"></i>
                                    <?php echo $lang->get('bug_report_to_github'); ?>
                                </div>
                                <textarea class="form-control" style="min-height:300px;" id="dialog-bug-report-text" placeholder="<?php echo $lang->get('please_wait_while_loading'); ?>"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary mr-2 clipboard-copy" data-clipboard-text="dialog-bug-report-text" id="dialog-bug-report-select-button"><?php echo $lang->get('copy_to_clipboard'); ?></button>
                        <button class="btn btn-primary" id="dialog-bug-report-github-button"><?php echo $lang->get('open_bug_report_in_github'); ?></button>
                        <button class="btn btn-default float-right close-element"><?php echo $lang->get('close'); ?></button>
                    </div>
                </div>
                <!-- /.DEFECT REPORT -->


                <!-- USER CHANGE AUTH PASSWORD -->
                <div class="card card-warning m-3 hidden" id="dialog-user-change-password">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa-solid fa-bullhorn mr-2"></i>
                            <?php echo $lang->get('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-5 alert alert-info hidden" id="dialog-user-change-password-info">
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('provide_your_current_password'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="profile-current-password">
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('index_new_pw'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="profile-password">
                                    <div class="input-group-append" style="margin: 0px;">
                                        <span class="input-group-text" id="profile-password-strength"></span>
                                        <input type="hidden" id="profile-password-complex" />
                                    </div>
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('index_change_pw_confirmation'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="profile-password-confirm">
                                </div>
                                <div class="form-control mt-3 font-weight-light grey" id="dialog-user-change-password-progress">
                                    <?php echo $lang->get('provide_current_psk_and_click_launch'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" id="dialog-user-change-password-do"><?php echo $lang->get('launch'); ?></button>
                        <button class="btn btn-default float-right" id="dialog-user-change-password-close"><?php echo $lang->get('close'); ?></button>
                    </div>
                </div>
                <!-- /.USER CHANGE AUTH PASSWORD -->


                <!-- LDAP USER HAS CHANGED AUTH PASSWORD -->
                <div class="card card-warning m-3 hidden" id="dialog-ldap-user-change-password">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa-solid fa-bullhorn mr-2"></i>
                            <?php echo $lang->get('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-5 alert alert-info hidden" id="dialog-ldap-user-change-password-info">
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('provide_your_previous_password'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="dialog-ldap-user-change-password-old">
                                </div>
                                <div class="input-group mb-3"  id="new-password-field">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('provide_your_current_password'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="dialog-ldap-user-change-password-current">
                                </div>
                                <div class="form-control mt-3 font-weight-light grey" id="dialog-ldap-user-change-password-progress">
                                    <?php echo $lang->get('provide_current_psk_and_click_launch'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" id="dialog-ldap-user-change-password-do"><?php echo $lang->get('launch'); ?></button>
                        <button class="btn btn-default float-right" id="dialog-ldap-user-change-password-close"><?php echo $lang->get('close'); ?></button>
                    </div>
                </div>
                <!-- /.LDAP USER HAS CHANGED AUTH PASSWORD -->


                <!-- ADMIN ASKS FOR USER PASSWORD CHANGE -->
                <div class="card card-warning m-3 hidden" id="dialog-admin-change-user-password">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa-solid fa-bullhorn mr-2"></i>
                            <?php echo $lang->get('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-2 alert alert-info" id="dialog-admin-change-user-password-info">
                                </div>
                                <div class="form-control mt-3 font-weight-light grey" id="dialog-admin-change-user-password-progress">
                                    <?php echo $lang->get('provide_current_psk_and_click_launch'); ?>
                                </div>
                                <div class="mt-3">                                    
                                    <label>
                                        <span class="mr-2 pointer fw-normal"><i class="fa-solid fa-eye mr-2 text-orange"></i><?php echo $lang->get('show_user_password');?></span>
                                        <input type="checkbox" id="dialog-admin-change-user-password-do-show-password" class="pointer">
                                    </label>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="admin_change_user_password_target_user" value="">
                        <input type="hidden" id="admin_change_user_encryption_code_target_user" value="">
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary mr-3" id="dialog-admin-change-user-password-do"><?php echo $lang->get('launch'); ?></button>
                        <button class="btn btn-default float-right" id="dialog-admin-change-user-password-close"><?php echo $lang->get('close'); ?></button>
                    </div>
                </div>
                <!-- /.ADMIN ASKS FOR USER PASSWORD CHANGE -->


                <!-- USER PROVIDES TEMPORARY CODE -->
                <div class="card card-warning m-3 hidden" id="dialog-user-temporary-code">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa-solid fa-bullhorn mr-2"></i>
                            <?php echo $lang->get('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-5 alert alert-info" id="dialog-user-temporary-code-info">
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('provide_your_current_password'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="dialog-user-temporary-code-current-password">
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('temporary_encryption_code'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="dialog-user-temporary-code-value">
                                </div>
                                <div class="form-control mt-3 font-weight-light grey" id="dialog-user-temporary-code-progress">
                                    <?php echo $lang->get('provide_current_psk_and_click_launch'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" id="dialog-user-temporary-code-do"><?php echo $lang->get('launch'); ?></button>
                        <button class="btn btn-default float-right" id="dialog-user-temporary-code-close"><?php echo $lang->get('close'); ?></button>
                    </div>
                </div>
                <!-- /.USER PROVIDES TEMPORARY CODE -->


                <!-- ENCRYPTION KEYS GENERATION -->
                <div class="card card-warning m-3 mt-3 hidden" id="dialog-encryption-keys">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa-solid fa-bullhorn mr-2"></i>
                            <?php echo $lang->get('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-2 alert alert-info" id="warning-text-reencryption">
                                    <i class="icon fa-solid fa-info mr-2"></i>
                                    <?php echo $lang->get('objects_encryption_explanation'); ?>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="sharekeys_reencryption_target_user" value="">
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" id="button_do_sharekeys_reencryption"><?php echo $lang->get('launch'); ?></button>
                        <button class="btn btn-default float-right" id="button_close_sharekeys_reencryption"><?php echo $lang->get('close'); ?></button>
                    </div>
                </div>
                <!-- /.ENCRYPTION KEYS GENERATION -->


                <!-- ENCRYPTION KEYS GENERATION FOR LDAP NEW USER -->
                <div class="card card-warning m-3 mt-3 hidden" id="dialog-ldap-user-build-keys-database">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa-solid fa-bullhorn mr-2"></i>
                            <?php echo $lang->get('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-2 alert alert-info" id="warning-text-reencryption">
                                    <i class="icon fa-solid fa-info mr-2"></i>
                                    <?php echo $lang->get('help_for_launching_items_encryption'); ?>
                                </div>

                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('temporary_encryption_code'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="dialog-ldap-user-build-keys-database-code">
                                </div>
                                
                                <div class="form-control mt-3 font-weight-light grey" id="dialog-ldap-user-build-keys-database-progress">
                                    <?php echo $lang->get('provide_current_psk_and_click_launch'); ?>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="sharekeys_reencryption_target_user" value="">
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" id="dialog-ldap-user-build-keys-database-do"><?php echo $lang->get('launch'); ?></button>
                        <button class="btn btn-default float-right" id="dialog-ldap-user-build-keys-database-close"><?php echo $lang->get('close'); ?></button>
                    </div>
                </div>
                <!-- /.ENCRYPTION KEYS GENERATION -->

                <!-- ENCRYPTION PERSONAL ITEMS GENERATION -->
                <div class="card card-warning m-3 hidden" id="dialog-encryption-personal-items-after-upgrade">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa-solid fa-bullhorn mr-2"></i>
                            <?php echo $lang->get('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-2 alert alert-info" id="warning-text-changing-password">
                                    <i class="icon fa-solid fa-info mr-2"></i>
                                    <?php echo $lang->get('objects_encryption_explanation'); ?>
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('personal_salt_key'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="user-current-defuse-psk">
                                </div>
                                <div class="form-control mt-3 font-weight-light grey" id="user-current-defuse-psk-progress">
                                    <?php echo $lang->get('provide_current_psk_and_click_launch'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" id="button_do_personal_items_reencryption"><?php echo $lang->get('launch'); ?></button>
                        <button class="btn btn-default float-right" id="button_close_personal_items_reencryption"><?php echo $lang->get('close'); ?></button>
                    </div>
                </div>
                <!-- /.ENCRYPTION PERSONAL ITEMS GENERATION -->
                

                <?php
                    // Case where user is allowed to see the page
                    if ($get['page'] === 'items') {
                        // SHow page with Items
                        if ((int) $session_user_admin !== 1) {
                            include $SETTINGS['cpassman_dir'] . '/pages/items.php';
                        } elseif ((int) $session_user_admin === 1) {
                            include $SETTINGS['cpassman_dir'] . '/pages/admin.php';
                        } else {
                            $session->set('system-error_code', ERR_NOT_ALLOWED);
                            //not allowed page
                            include $SETTINGS['cpassman_dir'] . '/error.php';
                        }
                    } elseif (in_array($get['page'], array_keys($mngPages)) === true) {
                        // Define if user is allowed to see management pages
                        if ($session_user_admin === 1) {
                            // deepcode ignore FileInclusion: $get['page'] is secured through usage of array_keys test bellow
                            include $SETTINGS['cpassman_dir'] . '/pages/' . basename($mngPages[$get['page']]);
                        } elseif ($session_user_manager === 1 || $session_user_human_resources === 1) {
                            if ($get['page'] === 'manage_main' || $get['page'] === 'manage_settings'
                            ) {
                                $session->set('system-error_code', ERR_NOT_ALLOWED);
                                //not allowed page
                                include $SETTINGS['cpassman_dir'] . '/error.php';
                            }
                        } else {
                            $session->set('system-error_code', ERR_NOT_ALLOWED);
                            //not allowed page
                            include $SETTINGS['cpassman_dir'] . '/error.php';
                        }
                    } elseif (empty($get['page']) === false && file_exists($SETTINGS['cpassman_dir'] . '/pages/' . $get['page'] . '.php') === true) {
                        // deepcode ignore FileInclusion: $get['page'] is tested against file_exists just below
                        include $SETTINGS['cpassman_dir'] . '/pages/' . basename($get['page'] . '.php');
                    } else {
                        $session->set('system-array_roles', ERR_NOT_EXIST);
                        //page doesn't exist
                        include $SETTINGS['cpassman_dir'].'/error.php';
                    }

    // Case where login attempts have been identified
    if ((int) $session->get('user-unsuccessfull_login_attempts_nb') !== 0
        && (bool) $session->get('user-unsuccessfull_login_attempts_shown') === false
    ) {
        ?>
                    <input type="hidden" id="user-login-attempts" value="1">
                <?php
    } ?>

            </div>
            <!-- /.content-wrapper -->

            <!-- Control Sidebar -->
            <aside class="control-sidebar control-sidebar-dark">
                <!-- Control sidebar content goes here -->
                <div class="p-3">
                    <h5><?php echo $lang->get('last_items_title'); ?></h5>
                    <div>
                        <ul class="list-unstyled" id="index-last-pwds">
                        </ul>
                    </div>
                </div>
            </aside>
            <!-- /.control-sidebar -->

            <!-- Main Footer -->
            <footer class="main-footer">
                <!-- To the right -->
                <div class="float-right d-none d-sm-inline">
                    <?php echo $lang->get('version_alone'); ?>&nbsp;<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>
                </div>
                <!-- Default to the left -->
                <strong>Copyright &copy; <?php echo TP_COPYRIGHT; ?> <a href="<?php echo TEAMPASS_URL; ?>"><?php echo TP_TOOL_NAME; ?></a>.</strong> All rights reserved.
            </footer>
        </div>
        <!-- ./wrapper -->

    <?php
        /* MAIN PAGE */

        echo '
<input type="hidden" id="temps_restant" value="', $session->get('user-session_duration') ?? '', '" />';
// display an item in the context of OTV link
} elseif ((null === $session->get('user-validite_pw')|| empty($session->get('user-validite_pw')) === true || empty($session->get('user-id')) === true)
    && empty($get['otv']) === false
) {
    // case where one-shot viewer
    if (empty($request->query->get('code')) === false && empty($request->query->get('stamp')) === false
    ) {
        include './includes/core/otv.php';
    } else {
        $session->set('system-error_code', ERR_VALID_SESSION);
        $session->set(
            'user-initial_url',
            filter_var(
                substr(
                    $server['request_uri'],
                    strpos($server['request_uri'], 'index.php?')
                ),
                FILTER_SANITIZE_URL
            )
        );
        include $SETTINGS['cpassman_dir'] . '/error.php';
    }
} elseif (//(empty($session->get('user-id')) === false && $session->get('user-id') !== null) ||
        empty($session->get('user-id')) === true
        || null === $session->get('user-validite_pw')
        || $session->get('user-validite_pw') === 0
    ) {
    // case where user not logged and can't access a direct link
    if (empty($get['page']) === false) {
        $session->set(
            'user-initial_url',
            filter_var(
                substr($server['request_uri'], strpos($server['request_uri'], 'index.php?')),
                FILTER_SANITIZE_URL
            )
        );
        // REDIRECTION PAGE ERREUR
        echo '
            <script language="javascript" type="text/javascript">
                window.location.href = "./index.php";
            </script>';
        exit;
    }
    
    // LOGIN form  
    include $SETTINGS['cpassman_dir'] . '/includes/core/login.php';
    
} else {
    // Clear session
    $session->invalidate();
}
    ?>

    <!-- Modal -->
    <div class="modal fade" id="warningModal" tabindex="-1" role="dialog" aria-labelledby="Caution" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="warningModalTitle"></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" id="warningModalCrossClose">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="warningModalBody">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" id="warningModalButtonClose"></button>
                    <button type="button" class="btn btn-primary" id="warningModalButtonAction"></button>
                </div>
            </div>
        </div>
    </div>



    <!-- REQUIRED SCRIPTS -->

    <!-- Font Awesome Icons -->
    <link href="plugins/fontawesome-free-6/css/fontawesome.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" rel="stylesheet">
    <link href="plugins/fontawesome-free-6/css/solid.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" rel="stylesheet">
    <link href="plugins/fontawesome-free-6/css/regular.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" rel="stylesheet">
    <link href="plugins/fontawesome-free-6/css/brands.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" rel="stylesheet">
    <link href="plugins/fontawesome-free-6/css/v5-font-face.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" rel="stylesheet" /> 
    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <script src="plugins/jquery/jquery.cookie.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/javascript"></script>
    <!-- jQuery UI -->
    <script src="plugins/jqueryUI/jquery-ui.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <link rel="stylesheet" href="plugins/jqueryUI/jquery-ui.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <!-- Popper -->
    <script src="plugins/popper/umd/popper.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- Bootstrap -->
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- AdminLTE -->
    <script src="plugins/adminlte/js/adminlte.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- Altertify -->
    <!--<script type="text/javascript" src="plugins/alertifyjs/alertify.min.js"></script>-->
    <!-- Toastr -->
    <script type="text/javascript" src="plugins/toastr/toastr.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- STORE.JS -->
    <script type="text/javascript" src="plugins/store.js/dist/store.everything.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- cryptojs-aesphp -->
    <script type="text/javascript" src="includes/libraries/cryptojs/crypto-js.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <script type="text/javascript" src="includes/libraries/cryptojs/encryption.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- pace -->
    <script type="text/javascript" data-pace-options='{ "ajax": true, "eventLag": false }' src="plugins/pace-progress/pace.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- clipboardjs -->
    <script type="text/javascript" src="plugins/clipboard/clipboard.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- select2 -->
    <script type="text/javascript" src="plugins/select2/js/select2.full.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- simplePassMeter -->
    <link rel="stylesheet" href="plugins/simplePassMeter/simplePassMeter.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/css" />
    <script type="text/javascript" src="plugins/simplePassMeter/simplePassMeter.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- platform -->
    <script type="text/javascript" src="plugins/platform/platform.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- radiobuttons -->
    <link rel="stylesheet" href="plugins/radioforbuttons/bootstrap-buttons.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/css" />
    <script type="text/javascript" src="plugins/radioforbuttons/jquery.radiosforbuttons.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- ICHECK -->
    <!--<link rel="stylesheet" href="./plugins/icheck-material/icheck-material.min.css">-->
    <link rel="stylesheet" href="./plugins/icheck/skins/all.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <script type="text/javascript" src="./plugins/icheck/icheck.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- bootstrap-add-clear -->
    <script type="text/javascript" src="plugins/bootstrap-add-clear/bootstrap-add-clear.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- DOMPurify -->
    <script type="text/javascript" src="plugins/DOMPurify/purify.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>

    <?php
    $get['page'] = $request->query->filter('page', null, FILTER_SANITIZE_SPECIAL_CHARS);
    if ($menuAdmin === true) {
        ?>
        <link rel="stylesheet" href="./plugins/toggles/css/toggles.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" />
        <link rel="stylesheet" href="./plugins/toggles/css/toggles-modern.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" />
        <script src="./plugins/toggles/toggles.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/javascript"></script>
        <!-- InputMask -->
        <script src="./plugins/inputmask/jquery.inputmask.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
        <!-- Sortable -->
        <!--<script src="./plugins/sortable/jquery.sortable.js"></script>-->
        <!-- PLUPLOAD -->
        <script type="text/javascript" src="plugins/plupload/js/plupload.full.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
        <!-- DataTables -->
        <link rel="stylesheet" src="./plugins/datatables/css/jquery.dataTables.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
        <link rel="stylesheet" src="./plugins/datatables/css/dataTables.bootstrap4.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
        <script type="text/javascript" src="./plugins/datatables/js/jquery.dataTables.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
        <script type="text/javascript" src="./plugins/datatables/js/dataTables.bootstrap4.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
        <link rel="stylesheet" src="./plugins/datatables/extensions/Responsive-2.2.2/css/responsive.bootstrap4.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
        <script type="text/javascript" src="./plugins/datatables/extensions/Responsive-2.2.2/js/dataTables.responsive.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
        <script type="text/javascript" src="./plugins/datatables/extensions/Responsive-2.2.2/js/responsive.bootstrap4.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
        <script type="text/javascript" src="./plugins/datatables/plugins/select.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
        <link rel="stylesheet" src="./plugins/datatables/extensions/Scroller-1.5.0/css/scroller.bootstrap4.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
        <script type="text/javascript" src="./plugins/datatables/extensions/Scroller-1.5.0/js/dataTables.scroller.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <?php
    } elseif (isset($get['page']) === true) {
        if (in_array($get['page'], ['items', 'import']) === true) {
            ?>
            <link rel="stylesheet" href="./plugins/jstree/themes/default/style.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" />
            <link rel="stylesheet" href="./plugins/jstree/themes/default-dark/style.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" />
            <script src="./plugins/jstree/jstree.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/javascript"></script>
            <!-- countdownTimer -->
            <script src="./plugins/jquery.countdown360/jquery.countdown360.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- SUMMERNOTE -->
            <link rel="stylesheet" href="./plugins/summernote/summernote-bs4.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
            <script src="./plugins/summernote/summernote-bs4.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- date-picker -->
            <link rel="stylesheet" href="./plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
            <script src="./plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- time-picker -->
            <link rel="stylesheet" href="./plugins/timepicker/bootstrap-timepicker.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
            <script src="./plugins/timepicker/bootstrap-timepicker.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- PLUPLOAD -->
            <script type="text/javascript" src="plugins/plupload/js/plupload.full.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- VALIDATE -->
            <script type="text/javascript" src="plugins/jquery-validation/jquery.validate.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- PWSTRENGHT -->
            <script type="text/javascript" src="plugins/zxcvbn/zxcvbn.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <script type="text/javascript" src="plugins/jquery.pwstrength/pwstrength-bootstrap.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- TOGGLE -->
            <link rel="stylesheet" href="./plugins/toggles/css/toggles.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" />
            <link rel="stylesheet" href="./plugins/toggles/css/toggles-modern.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" />
            <script src="./plugins/toggles/toggles.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/javascript"></script>
        <?php
        } elseif (in_array($get['page'], ['search', 'folders', 'users', 'roles', 'utilities.deletion', 'utilities.logs', 'utilities.database', 'utilities.renewal', 'tasks']) === true) {
            ?>
            <!-- DataTables -->
            <link rel="stylesheet" src="./plugins/datatables/css/jquery.dataTables.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
            <link rel="stylesheet" src="./plugins/datatables/css/dataTables.bootstrap4.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
            <script type="text/javascript" src="./plugins/datatables/js/jquery.dataTables.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <script type="text/javascript" src="./plugins/datatables/js/dataTables.bootstrap4.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <link rel="stylesheet" src="./plugins/datatables/extensions/Responsive-2.2.2/css/responsive.bootstrap4.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
            <script type="text/javascript" src="./plugins/datatables/extensions/Responsive-2.2.2/js/dataTables.responsive.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <script type="text/javascript" src="./plugins/datatables/extensions/Responsive-2.2.2/js/responsive.bootstrap4.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <script type="text/javascript" src="./plugins/datatables/plugins/select.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <link rel="stylesheet" src="./plugins/datatables/extensions/Scroller-1.5.0/css/scroller.bootstrap4.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
            <script type="text/javascript" src="./plugins/datatables/extensions/Scroller-1.5.0/js/dataTables.scroller.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- dater picker -->
            <link rel="stylesheet" href="./plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
            <script src="./plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- daterange picker -->
            <link rel="stylesheet" href="./plugins/daterangepicker/daterangepicker.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
            <script src="./plugins/moment/moment.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <script src="./plugins/daterangepicker/daterangepicker.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- SlimScroll -->
            <script src="./plugins/slimScroll/jquery.slimscroll.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- FastClick -->
            <script src="./plugins/fastclick/fastclick.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
        <?php
        } elseif ($get['page'] === 'profile') {
            ?>
            <!-- FILESAVER -->
            <script type="text/javascript" src="plugins/downloadjs/download.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- PLUPLOAD -->
            <script type="text/javascript" src="plugins/plupload/js/plupload.full.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
        <?php
        } elseif ($get['page'] === 'export') {
            ?>
            <!-- FILESAVER -->
            <script type="text/javascript" src="plugins/downloadjs/download.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- PWSTRENGHT -->
            <script type="text/javascript" src="plugins/zxcvbn/zxcvbn.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <script type="text/javascript" src="plugins/jquery.pwstrength/pwstrength-bootstrap.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
        <?php
        }
    }
    ?>
    <!-- functions -->
    <script type="text/javascript" src="includes/js/functions.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <script type="text/javascript" src="includes/js/CreateRandomString.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <input type="hidden" id="encryptClientServerStatus" value="<?php echo $SETTINGS['encryptClientServer'] ?? 1; ?>" />

    </body>

</html>

<script type="text/javascript">
    //override defaults
    /*alertify.defaults.transition = "slide";
    alertify.defaults.theme.ok = "btn btn-primary";
    alertify.defaults.theme.cancel = "btn btn-danger";
    alertify.defaults.theme.input = "form-control";*/

    toastr.options = {
        "closeButton": false,
        "debug": false,
        "newestOnTop": false,
        "progressBar": false,
        "positionClass": "toast-bottom-right",
        "preventDuplicates": true,
        "onClick": "close",
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "0",
        "extendedTimeOut": "0",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    }
</script>

<script>
    $(document).ready(function() {
        // PWA with windowControlsOverlay
        if ('windowControlsOverlay' in navigator) {
            // Event listener for window-controls-overlay changes
            navigator.windowControlsOverlay.addEventListener('geometrychange', function(event) {
                // Wait few time for resize animations
                $(this).delay(250).queue(function() {
                    // Move header content
                    adjustForWindowControlsOverlay(event.titlebarAreaRect);
                    $(this).dequeue();
                });
            });

            // Move header content
            adjustForWindowControlsOverlay(navigator.windowControlsOverlay.getTitlebarAreaRect());
        }

        function adjustForWindowControlsOverlay(rect) {
            // Display width - available space + 5px margin
            let margin = 5;
            let width = document.documentElement.clientWidth - rect.width + margin;

            if (width - margin !== document.documentElement.clientWidth) {
                // Add right padding to main-header
                $('.main-header').css('padding-right', width + 'px');

                // Window drag area
                $('.main-header').css('-webkit-app-region', 'drag');
                $('.main-header *').css('-webkit-app-region', 'no-drag');
            } else {
                // Remove right padding to main-header
                $('.main-header').css('padding-right', '0px');

                // No window drag area when titlebar is present
                $('.main-header').css('-webkit-app-region', 'no-drag');
            }
        }
    });

    // Handle external link open in current PWA
    if ("launchQueue" in window) {
        window.launchQueue.setConsumer((launchParams) => {
            if (launchParams.targetURL) {
                // Redirect on new URL in focus-existing client mode
                window.location.href = launchParams.targetURL;
            }
        });
    }
</script>

<?php
//$get = [];
//$get['page'] = $request->query->get('page') === null ? '' : $request->query->get('page');

// Load links, css and javascripts
if (isset($SETTINGS['cpassman_dir']) === true) {
    include_once $SETTINGS['cpassman_dir'] . '/includes/core/load.js.php';
    if ($menuAdmin === true) {
        include_once $SETTINGS['cpassman_dir'] . '/pages/admin.js.php';
        if ($get['page'] === '2fa') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/2fa.js.php';
        } elseif ($get['page'] === 'api') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/api.js.php';
        } elseif ($get['page'] === 'backups') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/backups.js.php';
        } elseif ($get['page'] === 'emails') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/emails.js.php';
        } elseif ($get['page'] === 'ldap') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/ldap.js.php';
        } elseif ($get['page'] === 'uploads') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/uploads.js.php';
        } elseif ($get['page'] === 'fields') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/fields.js.php';
        } elseif ($get['page'] === 'options') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/options.js.php';
        } elseif ($get['page'] === 'statistics') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/statistics.js.php';
        } elseif ($get['page'] === 'tasks') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/tasks.js.php';
        } elseif ($get['page'] === 'oauth' && WIP === true) {
            include_once $SETTINGS['cpassman_dir'] . '/pages/oauth.js.php';        
        } elseif ($get['page'] === 'tools') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/tools.js.php';
        }
    } elseif (isset($get['page']) === true && $get['page'] !== '') {
        if ($get['page'] === 'items') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/items.js.php';
        } elseif ($get['page'] === 'import') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/import.js.php';
        } elseif ($get['page'] === 'export') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/export.js.php';
        } elseif ($get['page'] === 'offline') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/offline.js.php';
        } elseif ($get['page'] === 'search') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/search.js.php';
        } elseif ($get['page'] === 'profile') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/profile.js.php';
        } elseif ($get['page'] === 'favourites') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/favorites.js.php';
        } elseif ($get['page'] === 'folders') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/folders.js.php';
        } elseif ($get['page'] === 'users') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/users.js.php';
        } elseif ($get['page'] === 'roles') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/roles.js.php';
        } elseif ($get['page'] === 'utilities.deletion') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/utilities.deletion.js.php';
        } elseif ($get['page'] === 'utilities.logs') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/utilities.logs.js.php';
        } elseif ($get['page'] === 'utilities.database') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/utilities.database.js.php';
        } elseif ($get['page'] === 'utilities.renewal') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/utilities.renewal.js.php';
        }
    } else {
        include_once $SETTINGS['cpassman_dir'] . '/includes/core/login.js.php';
    }
}

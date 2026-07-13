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
 * @copyright 2009-2026 Teampass.net
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

// Application root paths — defined here so every PHP include can rely on them.
// TEAMPASS_ROOT  : repository root (parent of public/)
// TEAMPASS_APP   : private application code (not web-accessible)
// TEAMPASS_STORAGE: writable runtime data (files, uploads, logs)
if (!defined('TEAMPASS_ROOT')) {
    define('TEAMPASS_ROOT', dirname(__DIR__));
}
if (!defined('TEAMPASS_APP')) {
    define('TEAMPASS_APP', TEAMPASS_ROOT . '/app');
}
if (!defined('TEAMPASS_STORAGE')) {
    define('TEAMPASS_STORAGE', TEAMPASS_ROOT . '/storage');
}

// Before we start processing, we should abort no install is present
if (file_exists(TEAMPASS_APP . '/config/settings.php') === false) {
    // This should never happen, but in case it does
    // this means if headers are sent, redirect will fallback to JS
    if (headers_sent()) {
        echo '<script type="text/javascript">document.location.replace("/install/install.php");</script>';
    } else {
        header('Location: /install/install.php');
    }
    // Now either way, we should stop processing further
    exit;
}

// One-Time-View / Secure Send is a public, unauthenticated endpoint. Handle it here
// — before CSRFGuard and before any authenticated routing — then exit. It performs
// no session-bound mutation, so it needs no CSRF token (the anonymous recipient has
// none); because it always exits, a crafted "?otv=" request can never fall through
// to a CSRF-protected handler. CSRFGuard therefore stays unconditionally enabled for
// every other index.php request, and the page renders its own self-contained layout.
if (isset($_GET['otv']) === true && $_GET['otv'] !== '') {
    require_once TEAMPASS_APP . '/core/otv.php';
    exit;
}

// initialise CSRFGuard library
require_once TEAMPASS_APP . '/includes/libraries/csrfp/libs/csrf/csrfprotector.php';
csrfProtector::init();
// Override the jsUrl to use the public asset path.
$scriptBasePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
if ($scriptBasePath === '.' || $scriptBasePath === '/') {
    $scriptBasePath = '';
}
csrfProtector::$config['jsUrl'] = $scriptBasePath . '/assets/lib/csrfp/csrfprotector.js';

// Load functions
require_once TEAMPASS_APP . '/config/include.php';
require_once TEAMPASS_APP . '/sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();

// Random encryption key
if ($session->get('key') === null)
    $session->set('key', bin2hex(random_bytes(16)));

$request = SymfonyRequest::createFromGlobals();
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();
$antiXss = new AntiXSS();
$session->set('encryptClientServer', (int) $SETTINGS['encryptClientServer'] ?? 1);

// Quick major version check -> upgrade needed?
if (isset($SETTINGS['teampass_version']) === true && version_compare(TP_VERSION, $SETTINGS['teampass_version']) > 0) {
    $session->invalidate();
    // Build an absolute URL so relative-path loops cannot occur regardless of
    // server layout (DocumentRoot = public/, Alias, subdirectory, reverse proxy…).
    $upgradeUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBasePath(), '/') . '/install/upgrade.php';
    if (headers_sent()) {
        echo '<script type="text/javascript">document.location.replace(' . json_encode($upgradeUrl) . ');</script>';
    } else {
        header('Location: ' . $upgradeUrl);
    }
    exit;
}


$SETTINGS = $antiXss->xss_clean($SETTINGS);

// Load Core library
require_once TEAMPASS_APP . '/sources/core.php';
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

// Force log of all queries
// Check if super privilege exists in session
if (!$session->has('hasSuperPrivilege')) {
    // Execute query
    $hasSuperPrivilege = (int) DB::queryFirstField(
        "SELECT COUNT(*) 
        FROM information_schema.user_privileges 
        WHERE GRANTEE = CONCAT(\"'\", CURRENT_USER(), \"'@'localhost'\") 
        AND PRIVILEGE_TYPE = 'SUPER'"
    );
    // Save in session
    $session->set('hasSuperPrivilege', $hasSuperPrivilege);
} else {
    // Get value from session
    $hasSuperPrivilege = (int) $session->get('hasSuperPrivilege');
}
// Enable or not if user has super privilege
if ($hasSuperPrivilege > 0) {
    if (defined('MYSQL_LOG') && MYSQL_LOG === true) {
        DB::query("SET GLOBAL general_log = 'ON'");
        DB::query("SET GLOBAL general_log_file = " . (defined('MYSQL_LOG_FILE') ? MYSQL_LOG_FILE : "'/var/log/teampass_mysql_query.log'"));
    } else {
        DB::query("SET GLOBAL general_log = 'OFF'");
    }
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
$lang = new Language($session_user_language, TEAMPASS_APP . '/includes/language/');

if (isset($SETTINGS['cpassman_dir']) === false || $SETTINGS['cpassman_dir'] === '') {
    $SETTINGS['cpassman_dir'] = TEAMPASS_ROOT;
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
    <link rel="stylesheet" href="./assets/css/ionicons.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <!-- Theme style -->
    <link rel="stylesheet" href="./plugins/adminlte/css/adminlte.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <link rel="stylesheet" href="./plugins/pace-progress/themes/corner-indicator.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/css" />
    <link rel="stylesheet" href="./plugins/select2/css/select2.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/css" />
    <link rel="stylesheet" href="./plugins/select2/theme/select2-bootstrap4.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/css" />
    <!-- Theme style -->
    <link rel="stylesheet" href="./assets/css/teampass.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" type="text/css" href="./assets/fonts/fonts.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <!-- Altertify -->
    <link rel="stylesheet" href="./plugins/alertifyjs/css/alertify.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" />
    <link rel="stylesheet" href="./plugins/alertifyjs/css/themes/bootstrap.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" />
    <!-- Toastr -->
    <link rel="stylesheet" href="./plugins/toastr/toastr.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" />
    <!-- favicon -->
    <link rel="shortcut icon" type="image/png" href="<?php echo $favicon;?>"/>
    <!-- manifest (PWA) -->
    <link rel="manifest" href="manifest.json?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <!-- Custom style -->
    <?php
    if (file_exists(__DIR__ . '/assets/css/custom.css') === true) {?>
        <link rel="stylesheet" href="./assets/css/custom.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <?php
    } ?>
</head>




<?php
// display an item in the context of OTV link
if ((null === $session->get('user-validite_pw') || empty($session->get('user-validite_pw')) === true || empty($session->get('user-id')) === true)
    && empty($get['otv']) === false)
{
    include TEAMPASS_APP . '/core/otv.php';
    exit;
} elseif ($session->has('user-validite_pw') && null !== $session->get('user-validite_pw') && ($session->get('user-validite_pw') === 0 || $session->get('user-validite_pw') === 1)
    && empty($get['page']) === false && empty($session->get('user-id')) === false
) {
    ?>
    <body class="hold-transition sidebar-mini layout-navbar-fixed layout-fixed <?php echo $theme_body; ?>">
        <a class="tp-skip-link" href="#tp-main-content"><?php echo $lang->get('a11y_skip_to_content'); ?></a>
        <div class="wrapper">

            <!-- Navbar -->
            <nav class="main-header navbar navbar-expand <?php echo $theme_navbar ?>">
                <!-- User encryption still ongoing -->
                <div id="user_not_ready" class="alert alert-warning hidden pointer p-2 mt-2" style="position:absolute; left:200px;">
                    <span class="align-middle infotip ml-2" title="<?php echo $lang->get('keys_encryption_not_ready'); ?>" id="user_not_ready_text"><?php echo $lang->get('account_not_ready'); ?><span id="user_not_ready_progress"></span><i class="fa-solid fa-hourglass-half fa-beat-fade mr-2 ml-2"></i></span>
                </div>

                <!-- Left navbar links -->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="<?php echo $lang->get('a11y_toggle_menu'); ?>"><i class="fa-solid fa-bars" aria-hidden="true"></i></a>
                    </li>
                </ul>

                <!-- Right navbar links — grouped by intent: Find · Awareness · View · Account -->
                <?php
                // Topbar feature gates — mirror the JS includes further down so a rendered
                // slot always has its filler script (and vice-versa).
                $tpShowSearch    = (int) ($SETTINGS['command_palette_enabled'] ?? 0) === 1 && (int) $session_user_admin !== 1;
                $tpShowScore     = (int) ($SETTINGS['security_dashboard_enabled'] ?? 0) === 1 && (int) $session_user_admin !== 1;
                $tpShowBell      = (int) ($SETTINGS['notification_center_enabled'] ?? 0) === 1;
                $tpShowAwareness = $tpShowScore === true || $tpShowBell === true;
                // Account chip initials + role label.
                $tpInitials = strtoupper(mb_substr((string) $session_name, 0, 1, 'UTF-8') . mb_substr((string) $session_lastname, 0, 1, 'UTF-8'));
                $tpRoleLabel = (int) $session_user_admin === 1
                    ? $lang->get('god')
                    : ($session_user_manager === 1 ? $lang->get('gestionnaire') : $lang->get('user'));
                ?>
                <ul class="navbar-nav ml-auto tp-topbar">

                    <?php if ($tpShowSearch === true) { ?>
                    <!-- Find: opens the command palette (also Ctrl+K) -->
                    <li class="nav-item">
                        <a class="nav-link tp-topbar-btn" href="#" id="tp-navbar-search" role="button"
                            aria-label="<?php echo $lang->get('search_results'); ?>"
                            title="<?php echo $lang->get('search_results'); ?> (Ctrl+K)">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <kbd class="tp-topbar-kbd d-none d-lg-inline-block">Ctrl K</kbd>
                        </a>
                    </li>
                    <li class="tp-topbar-sep" aria-hidden="true"></li>
                    <?php } ?>

                    <?php if ($tpShowAwareness === true) { ?>
                    <!-- Awareness: fixed slots filled in place by app/core/*.js.php (no prepend, no reflow) -->
                    <?php if ($tpShowScore === true) { ?>
                    <li class="nav-item d-flex align-items-center" id="tp-slot-score"></li>
                    <?php } ?>
                    <?php if ($tpShowBell === true) { ?>
                    <li class="nav-item dropdown" id="tp-slot-bell"></li>
                    <?php } ?>
                    <li class="tp-topbar-sep" aria-hidden="true"></li>
                    <?php } ?>

                    <!-- View: theme + recent items -->
                    <li id="switch-theme" class="nav-item">
                        <a class="nav-link tp-topbar-btn" href="#" role="button"
                            aria-label="<?php echo $lang->get('a11y_toggle_theme'); ?>"
                            title="<?php echo $lang->get('a11y_toggle_theme'); ?>">
                            <i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link tp-topbar-btn" data-widget="control-sidebar" data-slide="true" href="#"
                            id="controlsidebar" role="button"
                            aria-label="<?php echo $lang->get('a11y_open_sidebar'); ?>"
                            title="<?php echo $lang->get('last_items_title'); ?>">
                            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                        </a>
                    </li>
                    <li class="tp-topbar-sep" aria-hidden="true"></li>

                    <!-- Account: identity chip + live session countdown + menu -->
                    <li class="nav-item dropdown tp-topbar-account">
                        <a class="nav-link tp-account-chip" href="#" data-toggle="dropdown" role="button"
                            aria-haspopup="true" aria-expanded="false"
                            aria-label="<?php echo $lang->get('my_profile'); ?>">
                            <span class="tp-account-avatar"><?php echo htmlspecialchars($tpInitials, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="tp-account-identity">
                                <span class="tp-account-name"><?php echo $session_name . ' ' . $session_lastname; ?></span>
                                <span class="tp-account-exp infotip" id="countdown" title="<?php echo $lang->get('index_expiration_in'); ?>"></span>
                            </span>
                            <i class="fa-solid fa-chevron-down tp-account-caret" aria-hidden="true"></i>
                        </a>

                        <div class="dropdown-menu dropdown-menu-right tp-account-menu">
                            <div class="tp-account-menu-head">
                                <span class="tp-account-avatar tp-account-avatar-lg"><?php echo htmlspecialchars($tpInitials, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="tp-account-menu-identity">
                                    <span class="tp-account-menu-name"><?php echo $session_name . ' ' . $session_lastname; ?></span>
                                    <span class="tp-account-menu-role"><?php echo $tpRoleLabel; ?></span>
                                </span>
                            </div>
                                <a class="dropdown-item user-menu" href="#" data-name="increase_session">
                                    <i class="far fa-clock fa-fw mr-2"></i><?php echo $lang->get('index_add_one_hour'); ?></a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item user-menu" href="#" data-name="profile">
                                    <i class="fa-solid fa-user-circle fa-fw mr-2"></i><?php echo $lang->get('my_profile'); ?>
                                </a>
                                <?php if ((int) $session_user_admin === 0) { ?>
                                <a class="dropdown-item" href="#" id="onboarding-replay">
                                    <i class="fa-solid fa-compass fa-fw mr-2"></i><?php echo $lang->get('onboarding_replay_menu'); ?>
                                </a>
                                <?php } ?>
                                <?php
                                    if (empty($session_auth_type) === false && $session_auth_type !== 'ldap' && $session_auth_type !== 'oauth2') {
                                        ?>
                                    <a class="dropdown-item user-menu" href="#" data-name="password-change">
                                        <i class="fa-solid fa-lock fa-fw mr-2"></i><?php echo $lang->get('index_change_pw'); ?>
                                    </a>
                                <?php
                                /*
                                // TODO: to remove
                                    } elseif ($session_auth_type === 'ldap') {
                                        ?>
                                    <a class="dropdown-item user-menu" href="#" data-name="sync-new-ldap-password">
                                        <i class="fa-solid fa-key fa-fw mr-2"></i><?php echo $lang->get('sync_new_ldap_password'); ?>
                                    </a>
                                <?php
                                */
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
                    </li>
                </ul>
            </nav>
            <!-- /.navbar -->

            <!-- Main Sidebar Container -->
            <aside class="main-sidebar sidebar-dark-primary elevation-4">
                <!-- Brand Logo -->
                <div class="brand-link tp-brand-link">
                    <a href="<?php echo $cpassman_url . '/index.php?page=' . ((int) $session_user_admin === 1 ? 'admin' : 'items'); ?>" class="tp-brand-home-link">
                        <img src="./assets/images/teampass-logo2-home.png" alt="Teampass Logo" class="brand-image">
                        <span class="brand-text font-weight-light"><?php echo TP_TOOL_NAME; ?></span>
                    </a>
                    <?php if ((int) $session_user_admin === 1) { ?>
                    <a
                        id="tp-sidebar-version-badge"
                        href="#"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="tp-sidebar-version-badge infotip d-none"
                        title=""
                        aria-label=""
                    ></a>
                    <?php } ?>
                </div>

                <!-- Sidebar -->
                <div class="sidebar">
                    <!-- Sidebar Menu -->
                    <nav class="mt-2" style="margin-bottom:40px;">
                        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                            <?php
                                // SECURITY POSTURE DASHBOARD (F1) - visible to non-admin users only
                                // (admins have no access to items, so the dashboard is irrelevant for them).
                                if (isset($SETTINGS['security_dashboard_enabled']) === true && (int) $SETTINGS['security_dashboard_enabled'] === 1
                                    && $session_user_admin === 0) {
                                    echo '
                    <li class="nav-item">
                        <a href="#" data-name="dashboard" class="nav-link', $get['page'] === 'dashboard' ? ' active' : '', '">
                        <i class="nav-icon fa-solid fa-shield-halved"></i>
                        <p>
                            ' . $lang->get('security_dashboard') . '
                        </p>
                        </a>
                    </li>';
                                }

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
    if (isset($SETTINGS['allow_import']) === true && (int) $SETTINGS['allow_import'] === 1 && (int) $session_user_admin === 0) {
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
    // KB menu
    if (isset($SETTINGS['enable_kb']) === true && (int) $SETTINGS['enable_kb'] === 1 && (int) $session_user_admin === 0) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="kb" class="nav-link', $get['page'] === 'kb' ? ' active' : '', '">
                        <i class="nav-icon fas fa-book"></i>
                        <p>
' . $lang->get('kb_menu') . '
                        </p>
                        </a>
                    </li>';
    }
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

    // -------------------------------------------------------------------------
    // Management sidebar (grouped by domain).
    // Routing (data-name) and role visibility are unchanged; only the
    // presentation is reorganised into domain drawers. A drawer is rendered
    // only when at least one of its entries is visible for the current user.
    // -------------------------------------------------------------------------
    $isAdmin = $session_user_admin === 1;
    $canManage = $session_user_admin === 1
        || $session_user_manager === 1
        || $session_user_human_resources === 1;

    // Keep the drawer holding the active page expanded on load.
    $currentPage = $get['page'];
    $menuAccess = in_array($currentPage, ['users', 'roles', 'folders'], true);
    $menuGovernance = in_array($currentPage, ['reviews', 'reports'], true);
    $menuConfiguration = in_array($currentPage, ['options', 'fields', 'emails', 'uploads'], true);
    $menuAuthentication = in_array($currentPage, ['2fa', 'ldap', 'oauth', 'api'], true);
    $menuOperations = in_array($currentPage, ['tasks', 'backups', 'utilities.database', 'import', 'utilities.renewal', 'utilities.deletion'], true);
    $menuMonitoring = in_array($currentPage, ['statistics', 'utilities.logs', 'utilities.health', 'tools'], true);

    // DASHBOARD (admin only)
    if ($isAdmin === true) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="admin" class="nav-link', $currentPage === 'admin' ? ' active' : '', '">
                        <i class="nav-icon fa-solid fa-info"></i>
                        <p>' . $lang->get('dashboard') . '</p>
                        </a>
                    </li>';
    }

    // ACCESS - Users, Roles, Folders (admin / manager / HR)
    if ($canManage === true) {
        echo '
                    <li class="nav-item has-treeview', $menuAccess === true ? ' menu-open' : '', '">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fa-solid fa-users-gear"></i>
                            <p>' . $lang->get('menu_access') . '<i class="fa-solid fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="#" data-name="users" class="nav-link', $currentPage === 'users' ? ' active' : '', '">
                                    <i class="fa-solid fa-users nav-icon"></i>
                                    <p>' . $lang->get('users') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="roles" class="nav-link', $currentPage === 'roles' ? ' active' : '', '">
                                    <i class="fa-solid fa-graduation-cap nav-icon"></i>
                                    <p>' . $lang->get('roles') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="folders" class="nav-link', $currentPage === 'folders' ? ' active' : '', '">
                                    <i class="fa-solid fa-folder-open nav-icon"></i>
                                    <p>' . $lang->get('folders') . '</p>
                                </a>
                            </li>
                        </ul>
                    </li>';
    }

    // GOVERNANCE - Access reviews (admin / manager / HR) + Compliance reports (admin)
    if ($canManage === true) {
        echo '
                    <li class="nav-item has-treeview', $menuGovernance === true ? ' menu-open' : '', '">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fa-solid fa-shield-halved"></i>
                            <p>' . $lang->get('menu_governance') . '<i class="fa-solid fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="#" data-name="reviews" class="nav-link', $currentPage === 'reviews' ? ' active' : '', '">
                                    <i class="fa-solid fa-clipboard-check nav-icon"></i>
                                    <p>' . $lang->get('access_reviews') . '</p>
                                </a>
                            </li>';
        if ($isAdmin === true) {
            echo '
                            <li class="nav-item">
                                <a href="#" data-name="reports" class="nav-link', $currentPage === 'reports' ? ' active' : '', '">
                                    <i class="fa-solid fa-file-contract nav-icon"></i>
                                    <p>' . $lang->get('compliance_reports') . '</p>
                                </a>
                            </li>';
        }
        echo '
                        </ul>
                    </li>';
    }

    // CONFIGURATION - Options, Fields, Emails, Uploads (admin)
    if ($isAdmin === true) {
        echo '
                    <li class="nav-item has-treeview', $menuConfiguration === true ? ' menu-open' : '', '">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fa-solid fa-sliders"></i>
                            <p>' . $lang->get('menu_configuration') . '<i class="fa-solid fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="#" data-name="options" class="nav-link', $currentPage === 'options' ? ' active' : '', '">
                                    <i class="fa-solid fa-check-double nav-icon"></i>
                                    <p>' . $lang->get('options') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="fields" class="nav-link', $currentPage === 'fields' ? ' active' : '', '">
                                    <i class="fa-solid fa-keyboard nav-icon"></i>
                                    <p>' . $lang->get('fields') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="emails" class="nav-link', $currentPage === 'emails' ? ' active' : '', '">
                                    <i class="fa-solid fa-envelope nav-icon"></i>
                                    <p>' . $lang->get('emails') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="uploads" class="nav-link', $currentPage === 'uploads' ? ' active' : '', '">
                                    <i class="fa-solid fa-file-upload nav-icon"></i>
                                    <p>' . $lang->get('uploads') . '</p>
                                </a>
                            </li>
                        </ul>
                    </li>';
    }

    // AUTHENTICATION - MFA, LDAP, OAuth, API (admin)
    if ($isAdmin === true) {
        echo '
                    <li class="nav-item has-treeview', $menuAuthentication === true ? ' menu-open' : '', '">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fa-solid fa-lock"></i>
                            <p>' . $lang->get('menu_authentication') . '<i class="fa-solid fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="#" data-name="2fa" class="nav-link', $currentPage === '2fa' ? ' active' : '', '">
                                    <i class="fa-solid fa-qrcode nav-icon"></i>
                                    <p>' . $lang->get('mfa_short') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="ldap" class="nav-link', $currentPage === 'ldap' ? ' active' : '', '">
                                    <i class="fa-solid fa-id-card nav-icon"></i>
                                    <p>' . $lang->get('ldap') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="oauth" class="nav-link', $currentPage === 'oauth' ? ' active' : '', '">
                                    <i class="fa-solid fa-plug nav-icon"></i>
                                    <p>' . $lang->get('oauth') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="api" class="nav-link', $currentPage === 'api' ? ' active' : '', '">
                                    <i class="fa-solid fa-cubes nav-icon"></i>
                                    <p>' . $lang->get('api') . '</p>
                                </a>
                            </li>
                        </ul>
                    </li>';
    }

    // OPERATIONS - Tasks/Backups/Import (admin) + Database/Renewal/Deletion (admin / manager / HR)
    if ($canManage === true) {
        echo '
                    <li class="nav-item has-treeview', $menuOperations === true ? ' menu-open' : '', '">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fa-solid fa-gears"></i>
                            <p>' . $lang->get('menu_operations') . '<i class="fa-solid fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">';
        if ($isAdmin === true) {
            echo '
                            <li class="nav-item">
                                <a href="#" data-name="tasks" class="nav-link', $currentPage === 'tasks' ? ' active' : '', '">
                                    <i class="fa-solid fa-tasks nav-icon"></i>
                                    <p>' . $lang->get('tasks') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="backups" class="nav-link', $currentPage === 'backups' ? ' active' : '', '">
                                    <i class="fa-solid fa-database nav-icon"></i>
                                    <p>' . $lang->get('backups') . '</p>
                                </a>
                            </li>';
        }
        echo '
                            <li class="nav-item">
                                <a href="#" data-name="utilities.database" class="nav-link', $currentPage === 'utilities.database' ? ' active' : '', '">
                                    <i class="fa-solid fa-database nav-icon"></i>
                                    <p>' . $lang->get('database') . '</p>
                                </a>
                            </li>';
        if ($isAdmin === true) {
            echo '
                            <li class="nav-item">
                                <a href="#" data-name="import" class="nav-link', $currentPage === 'import' ? ' active' : '', '">
                                    <i class="fa-solid fa-file-import nav-icon"></i>
                                    <p>' . $lang->get('import') . '</p>
                                </a>
                            </li>';
        }
        echo '
                            <li class="nav-item">
                                <a href="#" data-name="utilities.renewal" class="nav-link', $currentPage === 'utilities.renewal' ? ' active' : '', '">
                                    <i class="far fa-calendar-alt nav-icon"></i>
                                    <p>' . $lang->get('renewal') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="utilities.deletion" class="nav-link', $currentPage === 'utilities.deletion' ? ' active' : '', '">
                                    <i class="fa-solid fa-trash-alt nav-icon"></i>
                                    <p>' . $lang->get('deletion') . '</p>
                                </a>
                            </li>
                        </ul>
                    </li>';
    }

    // MONITORING - Statistics/Health/Recovery tools (admin) + Logs (admin / manager / HR)
    if ($canManage === true) {
        echo '
                    <li class="nav-item has-treeview', $menuMonitoring === true ? ' menu-open' : '', '">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fa-solid fa-gauge-high"></i>
                            <p>' . $lang->get('menu_supervision') . '<i class="fa-solid fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">';
        if ($isAdmin === true) {
            echo '
                            <li class="nav-item">
                                <a href="#" data-name="statistics" class="nav-link', $currentPage === 'statistics' ? ' active' : '', '">
                                    <i class="fa-solid fa-chart-bar nav-icon"></i>
                                    <p>' . $lang->get('statistics') . '</p>
                                </a>
                            </li>';
        }
        echo '
                            <li class="nav-item">
                                <a href="#" data-name="utilities.logs" class="nav-link', $currentPage === 'utilities.logs' ? ' active' : '', '">
                                    <i class="fa-solid fa-history nav-icon"></i>
                                    <p>' . $lang->get('logs') . '</p>
                                </a>
                            </li>';
        if ($isAdmin === true) {
            echo '
                            <li class="nav-item">
                                <a href="#" data-name="utilities.health" class="nav-link', $currentPage === 'utilities.health' ? ' active' : '', '">
                                    <i class="fa-solid fa-heart-pulse nav-icon"></i>
                                    <p>' . $lang->get('system_health') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="tools" class="nav-link', $currentPage === 'tools' ? ' active' : '', '">
                                    <i class="fa-solid fa-person-drowning nav-icon"></i>
                                    <p>' . $lang->get('tools') . '</p>
                                </a>
                            </li>';
        }
        echo '
                        </ul>
                    </li>';
    }
    ?>
                        </ul>
                    </nav>
                    <!-- /.sidebar-menu -->
                <div class="menu-footer">
                    <div class="" id="sidebar-footer">
                        <?php $canOpenOnlineUsersDrawer = isset($SETTINGS['show_online_users_list']) === true && (int) $SETTINGS['show_online_users_list'] === 1; ?>
                        <i class="fa-solid fa-clock-o mr-2 infotip text-info pointer" title="<?php echo htmlspecialchars($lang->get('server_time') . ' ' .
                            date($date_format, (int) $server['request_time']) . ' - ' .
                            date($time_format, (int) $server['request_time']), ENT_QUOTES, 'UTF-8'); ?>"></i>
                        <?php if ($canOpenOnlineUsersDrawer === true) { ?>
                        <button
                            type="button"
                            class="btn btn-link tp-sidebar-footer-action text-info infotip pointer"
                            id="sidebar-online-users-trigger"
                            title="<?php echo (int) $session_nb_users_online . ' ' . $lang->get('users_online'); ?>"
                            aria-label="<?php echo (int) $session_nb_users_online . ' ' . $lang->get('users_online'); ?>"
                            aria-expanded="false"
                        >
                            <i class="fa-solid fa-users"></i>
                        </button>
                        <?php } else { ?>
                        <i
                            class="fa-solid fa-users mr-2 infotip text-info pointer"
                            id="sidebar-online-users-indicator"
                            title="<?php echo (int) $session_nb_users_online . ' ' . $lang->get('users_online'); ?>"
                            aria-label="<?php echo (int) $session_nb_users_online . ' ' . $lang->get('users_online'); ?>"
                        ></i>
                        <?php } ?>
                        <a href="<?php echo DOCUMENTATION_URL; ?>" target="_blank" class="text-info"><i class="fa-solid fa-book mr-2 infotip" title="<?php echo $lang->get('documentation_canal'); ?>"></i></a>
                        <a href="<?php echo HELP_URL; ?>" target="_blank" class="text-info"><i class="fa-solid fa-life-ring mr-2 infotip" title="<?php echo $lang->get('admin_help'); ?>"></i></a>
                        <?php if ($session_user_admin === 1) : ?><i class="fa-solid fa-bug infotip pointer text-info" title="<?php echo $lang->get('bugs_page'); ?>" onclick="generateBugReport()"></i><?php endif; ?>
                        <?php if ($canOpenOnlineUsersDrawer === true) { ?>
                        <div id="online-users-drawer" class="tp-online-users-drawer hidden" aria-hidden="true">
                            <div class="card card-outline card-info mb-0 shadow">
                                <div class="card-header py-2">
                                    <h3 class="card-title">
                                        <i class="fa-solid fa-users mr-2"></i><?php echo $lang->get('users_online'); ?>
                                        <span class="badge badge-info ml-2" id="online-users-drawer-count"><?php echo (int) $session_nb_users_online; ?></span>
                                    </h3>
                                </div>
                                <div class="card-body p-0">
                                    <div id="online-users-drawer-content" class="tp-online-users-drawer-content">
                                        <div class="p-3 text-center text-muted">
                                            <i class="fa-solid fa-spinner fa-spin mr-2"></i><?php echo $lang->get('loading'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
                </div>
                <!-- /.sidebar -->
            </aside>

            <!-- Content Wrapper. Contains page content -->
            <div class="content-wrapper" id="tp-main-content" role="main">

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
                                <div class="mb-5 alert alert-info" id="dialog-user-change-password-info">
                                    <i class="icon fa-solid fa-info mr-2"></i>
                                    <?php echo $lang->get('user_password_policy_tip'); ?>
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
                                <div class="mb-5 alert alert-info" id="dialog-ldap-user-change-password-info">
                                    <i class="icon fa-solid fa-info mr-2"></i>
                                    <?php echo $lang->get('user_password_changed'); ?>
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
                                <div class="form-check mb-3 alert alert-danger icheck-red hidden" id="dialog-ldap-user-change-password-confirm-ignore-div">
                                    <input type="checkbox" class="form-check-input form-item-control flat-blue" id="dialog-ldap-user-change-password-confirm-ignore" required>
                                    <label class="form-check-label ml-3" for="dialog-ldap-user-change-password-confirm-ignore"><i class="fa-solid fa-bolt fa-lg mr-2"></i><?php echo $lang->get('ignore_this_password_is_lost'); ?></label>
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
                                <div class="mt-3 hidden" id="dialog-admin-change-user-password-show-password-div">                                    
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
                                    <br/>
                                </div>
                                <div class="input-group mb-3<?php if ($session_auth_type === 'oauth2') echo ' hidden'; ?>">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('provide_your_current_password'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="dialog-ldap-user-build-keys-database-userpassword">
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

                <!-- ENCRYPTION PERSONAL ITEMS GENERATION WITH NEW PASSWORD -->
                <div class="card card-warning m-3 hidden" id="dialog-encryption-personal-items-after-password-change">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa-solid fa-bullhorn mr-2"></i>
                            <?php echo $lang->get('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-2 alert alert-info">
                                    <i class="icon fa-solid fa-info mr-2"></i>
                                    <?php echo $lang->get('attention_user_password_change'); ?>
                                </div>                                

                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('provide_your_previous_password'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="depiapc-previous-password">
                                    <br/>
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('your_current_password'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="depiapc-current-password">
                                </div>
                                
                                <div class="alert alert-danger mt-3" role="alert">                                    
                                    <label>
                                        <span class="mr-2 pointer fw-normal"><?php echo $lang->get('ignore_this_password_is_lost');?></span>
                                        <input type="checkbox" id="depiapc-ignore-password" class="pointer flat-blue">
                                    </label>
                                </div>

                                <div class="form-control mt-3 font-weight-light grey" id="depiapc-progress">
                                    <?php echo $lang->get('provide_current_psk_and_click_launch'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" id="button_depiapc_do"><?php echo $lang->get('launch'); ?></button>
                        <button class="btn btn-default float-right" id="button_depiapc_close"><?php echo $lang->get('close'); ?></button>
                    </div>
                </div>
                <!-- /.ENCRYPTION PERSONAL ITEMS GENERATION WITH NEW PASSWORD -->
                

                <?php
                    // Case where user is allowed to see the page
                    if ($get['page'] === 'items') {
                        // SHow page with Items
                        if ((int) $session_user_admin !== 1) {
                            include TEAMPASS_APP . '/pages/items.php';
                        } elseif ((int) $session_user_admin === 1) {
                            include TEAMPASS_APP . '/pages/admin.php';
                        } else {
                            $session->set('system-error_code', ERR_NOT_ALLOWED);
                            //not allowed page
                            include __DIR__ . '/error.php';
                        }
                    } elseif (in_array($get['page'], array_keys($mngPages)) === true) {
                        // Management pages a manager may open (delegated); all
                        // other management pages stay administrator-only.
                        $managerMngPages = ['reviews'];
                        // Define if user is allowed to see management pages
                        if ($session_user_admin === 1) {
                            // deepcode ignore FileInclusion: $get['page'] is secured through usage of array_keys test bellow
                            include TEAMPASS_APP . '/pages/' . basename($mngPages[$get['page']]);
                        } elseif (
                            ($session_user_manager === 1 || $session_user_human_resources === 1)
                            && in_array($get['page'], $managerMngPages, true) === true
                        ) {
                            include TEAMPASS_APP . '/pages/' . basename($mngPages[$get['page']]);
                        } else {
                            $session->set('system-error_code', ERR_NOT_ALLOWED);
                            //not allowed page
                            include __DIR__ . '/error.php';
                        }
                    } elseif (empty($get['page']) === false && file_exists(TEAMPASS_APP . '/pages/' . $get['page'] . '.php') === true) {
                        // deepcode ignore FileInclusion: $get['page'] is tested against file_exists just below
                        include TEAMPASS_APP . '/pages/' . basename($get['page'] . '.php');
                    } else {
                        $session->set('system-array_roles', ERR_NOT_EXIST);
                        //page doesn't exist
                        include __DIR__ . '/error.php';
                    }

?>

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
                <div class="float-right d-none d-sm-inline" id="footer-version">
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
        include TEAMPASS_APP . '/core/otv.php';
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
        include __DIR__ . '/error.php';
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
    include TEAMPASS_APP . '/core/login.php';
    
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" id="warningModalButtonClose" data-label-cancel="<?php echo addslashes($lang->get('cancel')); ?>"></button>
                    <button type="button" class="btn btn-primary" id="warningModalButtonAction" data-label-confirm="<?php echo addslashes($lang->get('confirm')); ?>"></button>
                </div>
            </div>
        </div>
    </div>



    <!-- REQUIRED SCRIPTS -->

    <!-- Font Awesome Icons -->
    <link href="./plugins/fontawesome-free/css/fontawesome.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" rel="stylesheet">
    <link href="./plugins/fontawesome-free/css/solid.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" rel="stylesheet">
    <link href="./plugins/fontawesome-free/css/regular.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" rel="stylesheet">
    <link href="./plugins/fontawesome-free/css/brands.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" rel="stylesheet">
    <!-- jQuery -->
    <script src="./plugins/jquery/jquery.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <script src="./plugins/jquery/jquery.cookie.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/javascript"></script>
    <!-- jQuery UI -->
    <script src="./plugins/jqueryUI/jquery-ui.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <link rel="stylesheet" href="./plugins/jqueryUI/jquery-ui.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <!-- Popper -->
    <script src="./plugins/popper/umd/popper.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- Bootstrap -->
    <script src="./plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- AdminLTE -->
    <script src="./plugins/adminlte/js/adminlte.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- Altertify -->
    <!--<script type="text/javascript" src="./plugins/alertifyjs/alertify.min.js"></script>-->
    <!-- Toastr -->
    <script type="text/javascript" src="./plugins/toastr/toastr.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- STORE.JS -->
    <script type="text/javascript" src="./plugins/store.js/dist/store.everything.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- cryptojs-aesphp -->
    <script type="text/javascript" src="./assets/lib/cryptojs/crypto-js.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <script type="text/javascript" src="./assets/lib/cryptojs/encryption.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- pace -->
    <script type="text/javascript" data-pace-options='{ "ajax": true, "eventLag": false }' src="./plugins/pace-progress/pace.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- select2 -->
    <script type="text/javascript" src="./plugins/select2/js/select2.full.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- simplePassMeter -->
    <link rel="stylesheet" href="./plugins/simplePassMeter/simplePassMeter.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/css" />
    <script type="text/javascript" src="./plugins/simplePassMeter/simplePassMeter.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- platform -->
    <script type="text/javascript" src="./plugins/platform/platform.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- radiobuttons -->
    <link rel="stylesheet" href="./plugins/radioforbuttons/bootstrap-buttons.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/css" />
    <script type="text/javascript" src="./plugins/radioforbuttons/jquery.radiosforbuttons.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- ICHECK -->
    <!--<link rel="stylesheet" href="./plugins/icheck-material/icheck-material.min.css">-->
    <link rel="stylesheet" href="./plugins/icheck/skins/all.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <script type="text/javascript" src="./plugins/icheck/icheck.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- bootstrap-add-clear -->
    <script type="text/javascript" src="./plugins/bootstrap-add-clear/bootstrap-add-clear.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- DOMPurify -->
    <script type="text/javascript" src="./plugins/DOMPurify/purify.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <!-- QRCode generator (offline, client-side) -->
    <script type="text/javascript" src="./plugins/qrcodejs/qrcode.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>

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
        <script type="text/javascript" src="./plugins/plupload/js/plupload.full.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
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
        <link rel="stylesheet" href="./assets/css/admin-dashboard.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
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
            <script type="text/javascript" src="./plugins/plupload/js/plupload.full.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- VALIDATE -->
            <script type="text/javascript" src="./plugins/jquery-validation/jquery.validate.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- PWSTRENGHT -->
            <script type="text/javascript" src="./plugins/zxcvbn/zxcvbn.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <script type="text/javascript" src="./plugins/jquery.pwstrength/pwstrength-bootstrap.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- TOGGLE -->
            <link rel="stylesheet" href="./plugins/toggles/css/toggles.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" />
            <link rel="stylesheet" href="./plugins/toggles/css/toggles-modern.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" />
            <script src="./plugins/toggles/toggles.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>" type="text/javascript"></script>
        <?php
        } elseif (in_array($get['page'], ['search', 'folders', 'users', 'roles', 'kb', 'utilities.deletion', 'utilities.logs', 'utilities.database', 'utilities.health', 'utilities.renewal', 'tasks', 'statistics']) === true) {
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
            if ($get['page'] === 'kb') {
                ?>
                <!-- SUMMERNOTE -->
                <link rel="stylesheet" href="./plugins/summernote/summernote-bs4.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
                <script src="./plugins/summernote/summernote-bs4.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <?php
            }
            ?>
        <?php
        } elseif ($get['page'] === 'profile') {
            ?>
            <!-- FILESAVER -->
            <script type="text/javascript" src="./plugins/downloadjs/download.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- PLUPLOAD -->
            <script type="text/javascript" src="./plugins/plupload/js/plupload.full.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
        <?php
        } elseif ($get['page'] === 'export') {
            ?>
            <!-- FILESAVER -->
            <script type="text/javascript" src="./plugins/downloadjs/download.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <!-- PWSTRENGHT -->
            <script type="text/javascript" src="./plugins/zxcvbn/zxcvbn.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
            <script type="text/javascript" src="./plugins/jquery.pwstrength/pwstrength-bootstrap.min.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
        <?php
        }
    }
    ?>
    <!-- functions -->
    <script type="text/javascript" src="./assets/js/functions.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <script type="text/javascript" src="./assets/js/CreateRandomString.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <input type="hidden" id="encryptClientServerStatus" value="<?php echo $SETTINGS['encryptClientServer'] ?? 1; ?>" />

    <!-- WebSocket real-time notifications -->
    <?php
    $websocketEnabled = isset($SETTINGS['websocket_enabled']) && $SETTINGS['websocket_enabled'] === '1';
    if ($websocketEnabled && isset($session) && $session->has('user-id')) {
        // Generate a WebSocket authentication token valid for 1 hour (matches reconnect token lifetime)
        $wsToken = generateWebSocketToken((int) $session->get('user-id'), 3600);
        $wsHost = $SETTINGS['websocket_host'] ?? '127.0.0.1';
        $wsPort = $SETTINGS['websocket_port'] ?? '8080';
    ?>
    <script type="text/javascript">
        window.TeamPassWebSocketEnabled = true;
        window.TeamPassWebSocketDebug = <?php echo (isset($SETTINGS['debug_mode']) && $SETTINGS['debug_mode'] === '1') ? 'true' : 'false'; ?>;
        window.TeamPassWebSocketToken = <?php echo $wsToken ? json_encode($wsToken) : 'null'; ?>;
        window.TeamPassWebSocketUrl = <?php
            $wsIsLoopback = in_array($wsHost, ['127.0.0.1', 'localhost', '::1'], true);
            echo json_encode($wsIsLoopback ? '/ws' : 'ws://' . $wsHost . ':' . $wsPort);
        ?>;

        window.TeamPassWsLang = {
            realtime_connection_lost: <?php echo json_encode($lang->get('ws_realtime_connection_lost')); ?>,
            reconnecting: <?php echo json_encode($lang->get('ws_reconnecting')); ?>,
            new_item: <?php echo json_encode($lang->get('ws_new_item')); ?>,
            item_created_by: <?php echo json_encode($lang->get('ws_item_created_by')); ?>,
            item_updated: <?php echo json_encode($lang->get('ws_item_updated')); ?>,
            item_updated_by: <?php echo json_encode($lang->get('ws_item_updated_by')); ?>,
            item_deleted: <?php echo json_encode($lang->get('ws_item_deleted')); ?>,
            item_deleted_by: <?php echo json_encode($lang->get('ws_item_deleted_by')); ?>,
            new_folder: <?php echo json_encode($lang->get('ws_new_folder')); ?>,
            folder_created: <?php echo json_encode($lang->get('ws_folder_created')); ?>,
            folder_updated: <?php echo json_encode($lang->get('ws_folder_updated')); ?>,
            folder_has_been_updated: <?php echo json_encode($lang->get('ws_folder_has_been_updated')); ?>,
            folder_deleted: <?php echo json_encode($lang->get('ws_folder_deleted')); ?>,
            folder_has_been_deleted: <?php echo json_encode($lang->get('ws_folder_has_been_deleted')); ?>,
            permissions_changed: <?php echo json_encode($lang->get('ws_permissions_changed')); ?>,
            folder_permissions_changed: <?php echo json_encode($lang->get('ws_folder_permissions_changed')); ?>,
            account_ready: <?php echo json_encode($lang->get('ws_account_ready')); ?>,
            account_operational: <?php echo json_encode($lang->get('ws_account_operational')); ?>,
            session_expired: <?php echo json_encode($lang->get('ws_session_expired')); ?>,
            please_reconnect: <?php echo json_encode($lang->get('ws_please_reconnect')); ?>,
            maintenance: <?php echo json_encode($lang->get('ws_maintenance')); ?>,
            connection_lost: <?php echo json_encode($lang->get('ws_connection_lost')); ?>,
            unable_to_reconnect: <?php echo json_encode($lang->get('ws_unable_to_reconnect')); ?>,
            realtime_connected: <?php echo json_encode($lang->get('ws_realtime_connected')); ?>,
            realtime_disconnected: <?php echo json_encode($lang->get('ws_realtime_disconnected')); ?>,
            progress: <?php echo json_encode($lang->get('ws_progress')); ?>,
            operation_completed: <?php echo json_encode($lang->get('ws_operation_completed')); ?>,
            operation_failed: <?php echo json_encode($lang->get('ws_operation_failed')); ?>,
            task: <?php echo json_encode($lang->get('ws_task')); ?>,
            being_edited_by: <?php echo json_encode($lang->get('ws_being_edited_by')); ?>,
            item_now_available: <?php echo json_encode($lang->get('ws_item_now_available')); ?>,
            item_edition_released: <?php echo json_encode($lang->get('ws_item_edition_released')); ?>,
            item_viewed_by: <?php echo json_encode($lang->get('item_viewed_by')); ?>,
            kb_now_available: <?php echo json_encode($lang->get('kb_now_available')); ?>,
            kb_edition_released: <?php echo json_encode($lang->get('kb_edition_released')); ?>,
            kb_viewed_by: <?php echo json_encode($lang->get('kb_viewed_by')); ?>,
            item_reloading: <?php echo json_encode($lang->get('ws_item_reloading')); ?>,
            click_to_reload: <?php echo json_encode($lang->get('ws_click_to_reload')); ?>,
            item_moved_away: <?php echo json_encode($lang->get('ws_click_to_reload')); ?>,
            item_moved: <?php echo json_encode($lang->get('ws_item_moved')); ?>
        };
    </script>
    <script type="text/javascript" src="./assets/js/teampass-websocket.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <script type="text/javascript" src="./assets/js/teampass-websocket-init.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>
    <?php
    }
    ?>

    <?php
    // Include phpseclib v3 migration modal if migration is in progress
    if (isset($session)
        && ($session->get('phpseclibv3_migration_started') === true || $session->get('phpseclibv3_migration_in_progress') === true)
    ) {
        include_once TEAMPASS_APP . '/core/phpseclibv3_migration_modal.php';
    }
    ?>

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

    // Clipboard translations
    const TRANSLATIONS_CLIPBOARD = {
        clipboard_unsafe: "<?php echo $lang->get('clipboard_unsafe'); ?>",
        clipboard_clear_now: "<?php echo $lang->get('clipboard_clear_now'); ?>",
        clipboard_clearing_failed: "<?php echo $lang->get('clipboard_clearing_failed'); ?>",
        clipboard_cleared: "<?php echo $lang->get('clipboard_cleared'); ?>",
        unable_to_clear_clipboard: "<?php echo $lang->get('unable_to_clear_clipboard'); ?>"
    };
</script>

<script type="text/javascript" src="./assets/js/secure-clipboard-cleaner.js?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>"></script>

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
    include_once TEAMPASS_APP . '/core/load.js.php';
    // Browser-extension auto-configuration bridge (authenticated users, API enabled).
    if ((int) ($SETTINGS['api'] ?? 0) === 1
        && empty($session->get('user-id')) === false
        && (int) $session->get('user-id') > 0
    ) {
        include_once TEAMPASS_APP . '/core/extension-autoconfig.js.php';
    }
    // First-run onboarding wizard (non-admin authenticated users only).
    if (empty($session->get('user-id')) === false
        && (int) $session->get('user-id') > 0
        && (int) $session->get('user-admin') !== 1
    ) {
        include_once TEAMPASS_APP . '/core/onboarding.js.php';
    }
    // Proactive Health Nudges (F8): in-app banner for authenticated users when both the
    // Security posture dashboard (F1) and the nudges feature are enabled by an admin.
    if ((int) ($SETTINGS['security_dashboard_enabled'] ?? 0) === 1
        && (int) ($SETTINGS['security_nudges_enabled'] ?? 0) === 1
        && empty($session->get('user-id')) === false
        && (int) $session->get('user-id') > 0
    ) {
        include_once TEAMPASS_APP . '/core/security-nudges.js.php';
    }
    // Personal Security Score (F10): always-on topbar badge for authenticated non-admin users
    // when the Security posture dashboard (F1) is enabled by an admin (rides on the same gate).
    // Admins have no access to items, so the personal score does not apply to them.
    if ((int) ($SETTINGS['security_dashboard_enabled'] ?? 0) === 1
        && empty($session->get('user-id')) === false
        && (int) $session->get('user-id') > 0
        && (int) $session->get('user-admin') !== 1
    ) {
        include_once TEAMPASS_APP . '/core/security-score.js.php';
    }
    // Micro-Learning (F11): contextual, dismissible security tips for
    // authenticated non-admin users when the feature is enabled by an admin.
    if ((int) ($SETTINGS['micro_learning_enabled'] ?? 0) === 1
        && empty($session->get('user-id')) === false
        && (int) $session->get('user-id') > 0
        && (int) $session->get('user-admin') !== 1
    ) {
        include_once TEAMPASS_APP . '/core/micro-learning.js.php';
    }
    // Command Palette (F15): Ctrl+K global search for authenticated non-admin
    // users when the feature is enabled by an admin (admins have no item access).
    if ((int) ($SETTINGS['command_palette_enabled'] ?? 0) === 1
        && empty($session->get('user-id')) === false
        && (int) $session->get('user-id') > 0
        && (int) $session->get('user-admin') !== 1
    ) {
        include_once TEAMPASS_APP . '/core/command-palette.js.php';
    }
    // Notification Centre (D2): navbar bell + inbox for all authenticated users
    // when the feature is enabled by an admin.
    if ((int) ($SETTINGS['notification_center_enabled'] ?? 0) === 1
        && empty($session->get('user-id')) === false
        && (int) $session->get('user-id') > 0
    ) {
        include_once TEAMPASS_APP . '/core/notification-center.js.php';
    }
    // Data Classification (F4): item-card badge + selector for authenticated non-admin
    // users when the feature is enabled by an admin.
    if ((int) ($SETTINGS['data_classification_enabled'] ?? 0) === 1
        && empty($session->get('user-id')) === false
        && (int) $session->get('user-id') > 0
        && (int) $session->get('user-admin') !== 1
    ) {
        include_once TEAMPASS_APP . '/core/item-classification.js.php';
    }
    if ($menuAdmin === true && $session_user_admin !== 1 && $get['page'] === 'reviews') {
        // Delegated manager on the Recertification page: load only its own JS.
        // admin.js.php must NOT be included here — its userAccessPage('admin')
        // guard rejects non-admins and ends the session (ERR_NOT_ALLOWED).
        include_once TEAMPASS_APP . '/pages/reviews.js.php';
    } elseif ($menuAdmin === true) {
        include_once TEAMPASS_APP . '/pages/admin.js.php';
        if ($get['page'] === '2fa') {
            include_once TEAMPASS_APP . '/pages/2fa.js.php';
        } elseif ($get['page'] === 'api') {
            include_once TEAMPASS_APP . '/pages/api.js.php';
        } elseif ($get['page'] === 'backups') {
            include_once TEAMPASS_APP . '/pages/backups.js.php';
        } elseif ($get['page'] === 'emails') {
            include_once TEAMPASS_APP . '/pages/emails.js.php';
        } elseif ($get['page'] === 'ldap') {
            include_once TEAMPASS_APP . '/pages/ldap.js.php';
        } elseif ($get['page'] === 'uploads') {
            include_once TEAMPASS_APP . '/pages/uploads.js.php';
        } elseif ($get['page'] === 'fields') {
            include_once TEAMPASS_APP . '/pages/fields.js.php';
        } elseif ($get['page'] === 'options') {
            include_once TEAMPASS_APP . '/pages/options.js.php';
        } elseif ($get['page'] === 'statistics') {
            include_once TEAMPASS_APP . '/pages/statistics.js.php';
        } elseif ($get['page'] === 'tasks') {
            include_once TEAMPASS_APP . '/pages/tasks.js.php';
        } elseif ($get['page'] === 'oauth') {
            include_once TEAMPASS_APP . '/pages/oauth.js.php';        
        } elseif ($get['page'] === 'tools') {
            include_once TEAMPASS_APP . '/pages/tools.js.php';
        } elseif ($get['page'] === 'reports') {
            include_once TEAMPASS_APP . '/pages/reports.js.php';
        } elseif ($get['page'] === 'reviews') {
            include_once TEAMPASS_APP . '/pages/reviews.js.php';
        }
    } elseif (isset($get['page']) === true && $get['page'] !== '') {
        if ($get['page'] === 'items') {
            include_once TEAMPASS_APP . '/pages/items.js.php';
        } elseif ($get['page'] === 'dashboard') {
            include_once TEAMPASS_APP . '/pages/dashboard.js.php';
        } elseif ($get['page'] === 'import') {
            include_once TEAMPASS_APP . '/pages/import.js.php';
        } elseif ($get['page'] === 'export') {
            include_once TEAMPASS_APP . '/pages/export.js.php';
        } elseif ($get['page'] === 'search') {
            include_once TEAMPASS_APP . '/pages/search.js.php';
        } elseif ($get['page'] === 'profile') {
            include_once TEAMPASS_APP . '/pages/profile.js.php';
        } elseif ($get['page'] === 'favourites') {
            include_once TEAMPASS_APP . '/pages/favorites.js.php';
        } elseif ($get['page'] === 'kb') {
            include_once TEAMPASS_APP . '/pages/kb.js.php';
        } elseif ($get['page'] === 'folders') {
            include_once TEAMPASS_APP . '/pages/folders.js.php';
        } elseif ($get['page'] === 'users') {
            include_once TEAMPASS_APP . '/pages/users.js.php';
        } elseif ($get['page'] === 'roles') {
            include_once TEAMPASS_APP . '/pages/roles.js.php';
        } elseif ($get['page'] === 'utilities.deletion') {
            include_once TEAMPASS_APP . '/pages/utilities.deletion.js.php';
        } elseif ($get['page'] === 'utilities.logs') {
            include_once TEAMPASS_APP . '/pages/utilities.logs.js.php';
        } elseif ($get['page'] === 'utilities.database') {
            include_once TEAMPASS_APP . '/pages/utilities.database.js.php';
        } elseif ($get['page'] === 'utilities.health') {
            include_once TEAMPASS_APP . '/pages/utilities.health.js.php';
        } elseif ($get['page'] === 'utilities.renewal') {
            include_once TEAMPASS_APP . '/pages/utilities.renewal.js.php';
        }
    } else {
        include_once TEAMPASS_APP . '/core/login.js.php';
    }
}

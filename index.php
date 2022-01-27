<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 *
 * @file      index.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

header('X-XSS-Protection: 1; mode=block');
header('X-Frame-Options: SameOrigin');
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
if (file_exists('includes/config/settings.php') === false) {
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
require_once './includes/libraries/csrfp/libs/csrf/csrfprotector.php';
csrfProtector::init();
session_id();

// Load config
if (file_exists('../includes/config/tp.config.php') === true) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php') === true) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception('Error file "/includes/config/tp.config.php" not exists', 1);
}

// initialize session
if (isset($SETTINGS['cpassman_dir']) === false || $SETTINGS['cpassman_dir'] === '') {
    if (isset($SETTINGS['cpassman_dir']) === false) {
        $SETTINGS = [];
    }
    $SETTINGS['cpassman_dir'] = '.';
}

// Include files
require_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
// Quick major version check -> upgrade needed?
if (isset($SETTINGS['cpassman_version']) === true && version_compare(TP_VERSION, $SETTINGS['cpassman_version']) > 0) {
    // Perform redirection
    if (headers_sent()) {
        echo '<script language="javascript" type="text/javascript">document.location.replace("install/install.php");</script>';
    } else {
        header('Location: install/upgrade.php');
    }
    // No other way, we should stop processing further
    exit;
}

require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

if (isset($SETTINGS['cpassman_url']) === false || $SETTINGS['cpassman_url'] === '') {
    $SETTINGS['cpassman_url'] = $superGlobal->get('REQUEST_URI', 'SERVER');
}

// Include files
require_once $SETTINGS['cpassman_dir'] . '/sources/SplClassLoader.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
// Open MYSQL database connection
require_once './includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;
// Load Core library
require_once $SETTINGS['cpassman_dir'] . '/sources/core.php';
// Prepare POST variables
$post_language = filter_input(INPUT_POST, 'language', FILTER_SANITIZE_STRING);
// Prepare superGlobal variables
$session_user_language = $superGlobal->get('user_language', 'SESSION');
$session_user_id = $superGlobal->get('user_id', 'SESSION');
$session_user_admin = (int) $superGlobal->get('user_admin', 'SESSION');
$session_user_human_resources = (int) $superGlobal->get('user_can_manage_all_users', 'SESSION');
$session_name = $superGlobal->get('name', 'SESSION');
$session_lastname = $superGlobal->get('lastname', 'SESSION');
$session_user_manager = (int) $superGlobal->get('user_manager', 'SESSION');
$session_validite_pw = $superGlobal->get('validite_pw', 'SESSION');
$session_initial_url = $superGlobal->get('initial_url', 'SESSION');
$session_nb_users_online = $superGlobal->get('nb_users_online', 'SESSION');
$session_auth_type = $superGlobal->get('auth_type', 'SESSION', 'user');

$server = [];
$server['request_uri'] = $superGlobal->get('REQUEST_URI', 'SERVER');
$server['request_time'] = (int) $superGlobal->get('REQUEST_TIME', 'SERVER');

$get = [];
$get['page'] = $superGlobal->get('page', 'GET') === null ? '' : $superGlobal->get('page', 'GET');
$get['language'] = $superGlobal->get('language', 'GET') === null ? '' : $superGlobal->get('language', 'GET');
$get['otv'] = $superGlobal->get('otv', 'GET') === null ? '' : $superGlobal->get('otv', 'GET');

/* DEFINE WHAT LANGUAGE TO USE */
if ($session_user_id === null && $post_language === null && $session_user_language === null) {
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
        $superGlobal->put('user_language', 'english', 'SESSION');
        $superGlobal->put('user_language_flag', 'us.png', 'SESSION');
        $session_user_language = 'english';
    } else {
        $superGlobal->put('user_language', $dataLanguage['valeur'], 'SESSION');
        $superGlobal->put('user_language_flag', $dataLanguage['flag'], 'SESSION');
        $session_user_language = $dataLanguage['valeur'];
    }
} elseif (isset($SETTINGS['default_language']) === true && $session_user_language === null) {
    $superGlobal->put('user_language', $SETTINGS['default_language'], 'SESSION');
    $session_user_language = $SETTINGS['default_language'];
} elseif ($post_language !== null) {
    $superGlobal->put('user_language', $post_language, 'SESSION');
    $session_user_language = $post_language;
} elseif ($session_user_language === null || empty($session_user_language) === true) {
    if ($post_language !== null) {
        $superGlobal->put('user_language', $post_language, 'SESSION');
        $session_user_language = $post_language;
    } elseif ($session_user_language !== null) {
        $superGlobal->put('user_language', $SETTINGS['default_language'], 'SESSION');
        $session_user_language = $SETTINGS['default_language'];
    }
} elseif ($session_user_language === '0') {
    $superGlobal->put('user_language', $SETTINGS['default_lang uage'], 'SESSION');
    $session_user_language = $SETTINGS['default_language'];
}

if (isset($SETTINGS['cpassman_dir']) === false || $SETTINGS['cpassman_dir'] === '') {
    $SETTINGS['cpassman_dir'] = '.';
    $SETTINGS['cpassman_url'] = (string) $server['request_uri'];
}

// Load user languages files
if (in_array($session_user_language, $languagesList) === true) {
    if (file_exists($SETTINGS['cpassman_dir'] . '/includes/language/' . $session_user_language . '.php') === true) {
        $_SESSION['teampass']['lang'] = include $SETTINGS['cpassman_dir'] . '/includes/language/' . $session_user_language . '.php';
    }
} else {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
}

// load 2FA Google
if (isset($SETTINGS['google_authentication']) === true && $SETTINGS['google_authentication'] === '1') {
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Authentication/TwoFactorAuth/TwoFactorAuth.php';
}

// load 2FA Yubico
if (isset($SETTINGS['yubico_authentication']) === true && $SETTINGS['yubico_authentication'] === '1') {
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Authentication/Yubico/Yubico.php';
}

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

?>
<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>

<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>

<head>
    <meta http-equiv='Content-Type' content='text/html;charset=utf-8' />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta http-equiv="x-ua-compatible" content="ie=edge" />
    <title>Teampass</title>
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
    <link rel="stylesheet" href="includes/css/ionicons.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="plugins/adminlte/css/adminlte.min.css">
    <link rel="stylesheet" href="plugins/pace-progress/themes/blue/pace-theme-corner-indicator.css" type="text/css" />
    <link rel="stylesheet" href="plugins/select2/css/select2.min.css" type="text/css" />
    <link rel="stylesheet" href="plugins/select2/css/select2-bootstrap.min.css" type="text/css" />
    <!-- Theme style -->
    <link rel="stylesheet" href="includes/css/teampass.css">
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" type="text/css" href="includes/fonts/fonts.css">
    <!-- Altertify -->
    <link rel="stylesheet" href="plugins/alertifyjs/css/alertify.min.css" />
    <link rel="stylesheet" href="plugins/alertifyjs/css/themes/bootstrap.min.css" />
    <!-- Toastr -->
    <link rel="stylesheet" href="plugins/toastr/toastr.min.css" />
    <!-- favicon -->
    <link rel="shortcut icon" type="image/png" href="<?php echo $SETTINGS['favicon'];?>"/>
</head>




<?php

// display an item in the context of OTV link
if (($session_validite_pw === null
        || empty($session_validite_pw) === true
        || empty($session_user_id) === true)
    && empty($get['otv']) === false
) {
    // case where one-shot viewer
    if (empty($get['code']) === false && empty($get['stamp']) === false
    ) {
        include './includes/core/otv.php';
    } else {
        $_SESSION['error']['code'] = ERR_VALID_SESSION;
        $superGlobal->put(
            'initial_url',
            filter_var(
                substr(
                    $server['request_uri'],
                    strpos($server['request_uri'], 'index.php?')
                ),
                FILTER_SANITIZE_URL
            ),
            'SESSION'
        );
        include $SETTINGS['cpassman_dir'] . '/error.php';
    }
} elseif (
    $session_validite_pw !== null
    && $session_validite_pw === true
    && empty($get['page']) === false
    && empty($session_user_id) === false
) {
    ?>

    <body class="hold-transition sidebar-mini layout-navbar-fixed layout-fixed">
        <div class="wrapper">

            <!-- Navbar -->
            <nav class="main-header navbar navbar-expand navbar-white navbar-light border-bottom">
                <!-- Left navbar links -->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
                    </li>
                    <?php
                        if ($get['page'] === 'items') {
                            ?>
                        <li class="nav-item d-none d-sm-inline-block">
                            <a class="nav-link" href="#">
                                <i class="far fa-arrow-alt-circle-right columns-position tree-increase infotip" title="<?php echo langHdl('move_right_columns_separator'); ?>"></i>
                            </a>
                        </li>
                        <li class="nav-item d-none d-sm-inline-block">
                            <a class="nav-link" href="#">
                                <i class="far fa-arrow-alt-circle-left columns-position tree-decrease infotip" title="<?php echo langHdl('move_left_columns_separator'); ?>"></i>
                            </a>
                        </li>
                    <?php
                        } ?>
                </ul>

                <!-- Right navbar links -->
                <ul class="navbar-nav ml-auto">
                    <!-- Messages Dropdown Menu -->
                    <li class="nav-item dropdown">
                        <div class="dropdown show">
                            <a class="btn btn-primary dropdown-toggle" href="#" data-toggle="dropdown">
                                <?php
                                    echo $session_name . '&nbsp;' . $session_lastname; ?>
                            </a>

                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item user-menu" href="#" data-name="increase_session">
                                    <i class="far fa-clock fa-fw mr-2"></i><?php echo langHdl('index_add_one_hour'); ?></a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item user-menu" href="#" data-name="profile">
                                    <i class="fas fa-user-circle fa-fw mr-2"></i><?php echo langHdl('my_profile'); ?>
                                </a>
                                <?php
                                    if (empty($session_auth_type) === false && $session_auth_type !== 'ldap') {
                                        ?>
                                    <a class="dropdown-item user-menu" href="#" data-name="password-change">
                                        <i class="fas fa-lock fa-fw mr-2"></i><?php echo langHdl('index_change_pw'); ?>
                                    </a>
                                <?php
                                    } ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item user-menu" href="#" data-name="logout">
                                    <i class="fas fa-sign-out-alt fa-fw mr-2"></i><?php echo langHdl('disconnect'); ?>
                                </a>
                            </div>
                        </div>
                    </li>
                    <li>
                        <span class="align-middle infotip ml-2 text-info" title="<?php echo langHdl('index_expiration_in'); ?>" id="countdown"></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#" id="controlsidebar"><i class="fas fa-th-large"></i></a>
                    </li>
                </ul>
            </nav>
            <!-- /.navbar -->

            <!-- Main Sidebar Container -->
            <aside class="main-sidebar sidebar-dark-primary elevation-4">
                <!-- Brand Logo -->
                <a href="<?php echo $SETTINGS['cpassman_url'] . '/index.php?page=items'; ?>" class="brand-link">
                    <img src="includes/images/teampass-logo2-home.png" alt="Teampass Logo" class="brand-image">
                    <span class="brand-text font-weight-light"><?php echo TP_TOOL_NAME; ?></span>
                </a>

                <!-- Sidebar -->
                <div class="sidebar">
                    <!-- Sidebar Menu -->
                    <nav class="mt-2" style="margin-bottom:20px;">
                        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                            <?php
                                if ($session_user_admin === 0) {
                                    // ITEMS & SEARCH
                                    echo '
                    <li class="nav-item">
                        <a href="#" data-name="items" class="nav-link', $get['page'] === 'items' ? ' active' : '', '">
                        <i class="nav-icon fas fa-key"></i>
                        <p>
                            ' . langHdl('pw') . '
                        </p>
                        </a>
                    </li>';
                                }

    // IMPORT menu
    if (
                                    isset($SETTINGS['allow_import']) === true && (int) $SETTINGS['allow_import'] === 1
                                    && $session_user_admin === 0
                                ) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="import" class="nav-link', $get['page'] === 'import' ? ' active' : '', '">
                        <i class="nav-icon fas fa-file-import"></i>
                        <p>
                            ' . langHdl('import') . '
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
                                        explode(';', $superGlobal->get('fonction_id', 'SESSION')),
                                        explode(',', str_replace(['"', '[', ']'], '', $SETTINGS['roles_allowed_to_print_select']))
                                    )) > 0
                                    && (int) $session_user_admin === 0
                                ) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="export" class="nav-link', $get['page'] === 'export' ? ' active' : '', '">
                        <i class="nav-icon fas fa-file-export"></i>
                        <p>
                            ' . langHdl('export') . '
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
                        <i class="nav-icon fas fa-plug"></i>
                        <p>
                            '.langHdl('offline').'
                        </p>
                        </a>
                    </li>';
    }
    */

    if ($session_user_admin === 0) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="search" class="nav-link', $get['page'] === 'search' ? ' active' : '', '">
                        <i class="nav-icon fas fa-search"></i>
                        <p>
                            ' . langHdl('find') . '
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
                        <a href="#" data-name="favourites" class="nav-link', $get['page'] === 'admin' ? ' favourites' : '', '">
                        <i class="nav-icon fas fa-star"></i>
                        <p>
                            ' . langHdl('favorites') . '
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
                            <i class="nav-icon fas fa-map-signs"></i>
                            <p>
    '.langHdl('kb_menu').'
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
                        <i class="nav-icon fas fa-lightbulb"></i>
                        <p>
                            ' . langHdl('suggestion_menu') . '
                        </p>
                        </a>
                    </li>';
    }

    // Admin menu
    if ($session_user_admin === 1) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="admin" class="nav-link', $get['page'] === 'admin' ? ' active' : '', '">
                        <i class="nav-icon fas fa-info"></i>
                        <p>
                            ' . langHdl('admin_main') . '
                        </p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview', $menuAdmin === true ? ' menu-open' : '', '">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-wrench"></i>
                            <p>
                                ' . langHdl('admin_settings') . '
                                <i class="fas fa-angle-left right"></i>
                            </p>
                        </a>
                        <ul class="nav-item nav-treeview">
                            <li class="nav-item">
                                <a href="#" data-name="options" class="nav-link', $get['page'] === 'options' ? ' active' : '', '">
                                    <i class="fas fa-check-double nav-icon"></i>
                                    <p>' . langHdl('options') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="2fa" class="nav-link', $get['page'] === '2fa' ? ' active' : '', '">
                                    <i class="fas fa-qrcode nav-icon"></i>
                                    <p>' . langHdl('mfa_short') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="api" class="nav-link', $get['page'] === 'api' ? ' active' : '', '">
                                    <i class="fas fa-cubes nav-icon"></i>
                                    <p>' . langHdl('api') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="backups" class="nav-link', $get['page'] === 'backups' ? ' active' : '', '">
                                    <i class="fas fa-database nav-icon"></i>
                                    <p>' . langHdl('backups') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="emails" class="nav-link', $get['page'] === 'emails' ? ' active' : '', '">
                                    <i class="fas fa-envelope nav-icon"></i>
                                    <p>' . langHdl('emails') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="fields" class="nav-link', $get['page'] === 'fields' ? ' active' : '', '">
                                    <i class="fas fa-keyboard nav-icon"></i>
                                    <p>' . langHdl('fields') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="ldap" class="nav-link', $get['page'] === 'ldap' ? ' active' : '', '">
                                    <i class="fas fa-id-card nav-icon"></i>
                                    <p>' . langHdl('ldap') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="uploads" class="nav-link', $get['page'] === 'uploads' ? ' active' : '', '">
                                    <i class="fas fa-file-upload nav-icon"></i>
                                    <p>' . langHdl('uploads') . '</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="statistics" class="nav-link', $get['page'] === 'statistics' ? ' active' : '', '">
                                    <i class="fas fa-chart-bar nav-icon"></i>
                                    <p>' . langHdl('statistics') . '</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="#" data-name="actions" class="nav-link', $get['page'] === 'actions' ? ' active' : '', '">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>
                            ' . langHdl('actions') . '
                        </p>
                        </a>
                    </li>';
    }

    if (
                                    $session_user_admin === 1
                                    || $session_user_manager === 1
                                    || $session_user_human_resources === 1
                                ) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="folders" class="nav-link', $get['page'] === 'folders' ? ' active' : '', '">
                        <i class="nav-icon fas fa-folder-open"></i>
                        <p>
                            ' . langHdl('folders') . '
                        </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" data-name="roles" class="nav-link', $get['page'] === 'roles' ? ' active' : '', '">
                        <i class="nav-icon fas fa-graduation-cap"></i>
                        <p>
                            ' . langHdl('roles') . '
                        </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" data-name="users" class="nav-link', $get['page'] === 'users' ? ' active' : '', '">
                        <i class="nav-icon fas fa-users"></i>
                        <p>
                            ' . langHdl('users') . '
                        </p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview', $menuUtilities === true ? ' menu-open' : '', '">
                        <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-cubes"></i>
                        <p>' . langHdl('admin_views') . '<i class="fas fa-angle-left right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                          <li class="nav-item">
                            <a href="#" data-name="utilities.renewal" class="nav-link', $get['page'] === 'utilities.renewal' ? ' active' : '', '">
                              <i class="far fa-calendar-alt nav-icon"></i>
                              <p>' . langHdl('renewal') . '</p>
                            </a>
                          </li>
                          <li class="nav-item">
                            <a href="#" data-name="utilities.deletion" class="nav-link', $get['page'] === 'utilities.deletion' ? ' active' : '', '">
                              <i class="fas fa-trash-alt nav-icon"></i>
                              <p>' . langHdl('deletion') . '</p>
                            </a>
                          </li>
                          <li class="nav-item">
                            <a href="#" data-name="utilities.logs" class="nav-link', $get['page'] === 'utilities.logs' ? ' active' : '', '">
                              <i class="fas fa-history nav-icon"></i>
                              <p>' . langHdl('logs') . '</p>
                            </a>
                          </li>
                          <li class="nav-item">
                            <a href="#" data-name="utilities.database" class="nav-link', $get['page'] === 'utilities.database' ? ' active' : '', '">
                              <i class="fas fa-database nav-icon"></i>
                              <p>' . langHdl('database') . '</p>
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
                        <i class="fas fa-clock-o mr-2 infotip text-info pointer" title="<?php echo langHdl('server_time') . ' ' .
                            date($SETTINGS['date_format'], (int) $server['request_time']) . ' - ' .
                            date($SETTINGS['time_format'], (int) $server['request_time']); ?>"></i>
                        <i class="fas fa-users mr-2 infotip text-info pointer" title="<?php echo $session_nb_users_online . ' ' . langHdl('users_online'); ?>"></i>
                        <a href="<?php echo READTHEDOC_URL; ?>" target="_blank" class="text-info"><i class="fas fa-book mr-2 infotip" title="<?php echo langHdl('documentation_canal'); ?> ReadTheDocs"></i></a>
                        <a href="<?php echo REDDIT_URL; ?>" target="_blank" class="text-info"><i class="fab fa-reddit-alien mr-2 infotip" title="<?php echo langHdl('admin_help'); ?>"></i></a>
                        <i class="fas fa-bug infotip pointer text-info" title="<?php echo langHdl('bugs_page'); ?>" onclick="generateBugReport()"></i>
                    </div>
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
                            <i class="fas fa-bug mr-2"></i>
                            <?php echo langHdl('defect_report'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-2 alert alert-info">
                                    <i class="icon fas fa-info mr-2"></i>
                                    <?php echo langHdl('bug_report_to_github'); ?>
                                </div>
                                <textarea class="form-control" style="min-height:300px;" id="dialog-bug-report-text" placeholder="<?php echo langHdl('please_wait_while_loading'); ?>"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary mr-2 clipboard-copy" data-clipboard-text="dialog-bug-report-text" id="dialog-bug-report-select-button"><?php echo langHdl('copy_to_clipboard'); ?></button>
                        <button class="btn btn-primary" id="dialog-bug-report-github-button"><?php echo langHdl('open_bug_report_in_github'); ?></button>
                        <button class="btn btn-default float-right close-element"><?php echo langHdl('close'); ?></button>
                    </div>
                </div>
                <!-- /.DEFECT REPORT -->


                <!-- USER CHANGE AUTH PASSWORD -->
                <div class="card card-warning m-3 hidden" id="dialog-user-change-password">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bullhorn mr-2"></i>
                            <?php echo langHdl('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-5 alert alert-info hidden" id="dialog-user-change-password-info">
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo langHdl('provide_your_current_password'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="profile-current-password">
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo langHdl('index_new_pw'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="profile-password">
                                    <div class="input-group-append" style="margin: 0px;">
                                        <span class="input-group-text" id="profile-password-strength"></span>
                                        <input type="hidden" id="profile-password-complex" />
                                    </div>
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo langHdl('index_change_pw_confirmation'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="profile-password-confirm">
                                </div>
                                <div class="form-control mt-3 font-weight-light grey" id="dialog-user-change-password-progress">
                                    <?php echo langHdl('provide_current_psk_and_click_launch'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" id="dialog-user-change-password-do"><?php echo langHdl('launch'); ?></button>
                        <button class="btn btn-default float-right" id="dialog-user-change-password-close"><?php echo langHdl('close'); ?></button>
                    </div>
                </div>
                <!-- /.USER CHANGE AUTH PASSWORD -->


                <!-- LDAP USER HAS CHANGED AUTH PASSWORD -->
                <div class="card card-warning m-3 hidden" id="dialog-ldap-user-change-password">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bullhorn mr-2"></i>
                            <?php echo langHdl('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-5 alert alert-info hidden" id="dialog-ldap-user-change-password-info">
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo langHdl('provide_your_previous_password'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="dialog-ldap-user-change-password-old">
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo langHdl('provide_your_current_password'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="dialog-ldap-user-change-password-current">
                                </div>
                                <div class="form-control mt-3 font-weight-light grey" id="dialog-ldap-user-change-password-progress">
                                    <?php echo langHdl('provide_current_psk_and_click_launch'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" id="dialog-ldap-user-change-password-do"><?php echo langHdl('launch'); ?></button>
                        <button class="btn btn-default float-right" id="dialog-ldap-user-change-password-close"><?php echo langHdl('close'); ?></button>
                    </div>
                </div>
                <!-- /.LDAP USER HAS CHANGED AUTH PASSWORD -->


                <!-- ADMIN ASKS FOR USER PASSWORD CHANGE -->
                <div class="card card-warning m-3 hidden" id="dialog-admin-change-user-password">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bullhorn mr-2"></i>
                            <?php echo langHdl('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-2 alert alert-info" id="dialog-admin-change-user-password-info">
                                </div>
                                <div class="form-control mt-3 font-weight-light grey" id="dialog-admin-change-user-password-progress">
                                    <?php echo langHdl('provide_current_psk_and_click_launch'); ?>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="admin_change_user_password_target_user" value="">
                        <input type="hidden" id="admin_change_user_encryption_code_target_user" value="">
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" id="dialog-admin-change-user-password-do"><?php echo langHdl('launch'); ?></button>
                        <button class="btn btn-default float-right" id="dialog-admin-change-user-password-close"><?php echo langHdl('close'); ?></button>
                    </div>
                </div>
                <!-- /.ADMIN ASKS FOR USER PASSWORD CHANGE -->


                <!-- USER PROVIDES TEMPORARY CODE -->
                <div class="card card-warning m-3 hidden" id="dialog-user-temporary-code">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bullhorn mr-2"></i>
                            <?php echo langHdl('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-5 alert alert-info" id="dialog-user-temporary-code-info">
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo langHdl('provide_your_current_password'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="dialog-user-temporary-code-current-password">
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo langHdl('temporary_encryption_code'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="dialog-user-temporary-code-value">
                                </div>
                                <div class="form-control mt-3 font-weight-light grey" id="dialog-user-temporary-code-progress">
                                    <?php echo langHdl('provide_current_psk_and_click_launch'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" id="dialog-user-temporary-code-do"><?php echo langHdl('launch'); ?></button>
                        <button class="btn btn-default float-right" id="dialog-user-temporary-code-close"><?php echo langHdl('close'); ?></button>
                    </div>
                </div>
                <!-- /.USER PROVIDES TEMPORARY CODE -->


                <!-- ENCRYPTION KEYS GENERATION -->
                <div class="card card-warning m-3 mt-3 hidden" id="dialog-encryption-keys">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bullhorn mr-2"></i>
                            <?php echo langHdl('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-2 alert alert-info" id="warning-text-reencryption">
                                    <i class="icon fas fa-info mr-2"></i>
                                    <?php echo langHdl('objects_encryption_explanation'); ?>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="sharekeys_reencryption_target_user" value="">
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" id="button_do_sharekeys_reencryption"><?php echo langHdl('launch'); ?></button>
                        <button class="btn btn-default float-right" id="button_close_sharekeys_reencryption"><?php echo langHdl('close'); ?></button>
                    </div>
                </div>
                <!-- /.ENCRYPTION KEYS GENERATION -->


                <!-- ENCRYPTION KEYS GENERATION FOR LDAP NEW USER -->
                <div class="card card-warning m-3 mt-3 hidden" id="dialog-ldap-user-build-keys-database">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bullhorn mr-2"></i>
                            <?php echo langHdl('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-2 alert alert-info" id="warning-text-reencryption">
                                    <i class="icon fas fa-info mr-2"></i>
                                    <?php echo langHdl('help_for_launching_items_encryption'); ?>
                                </div>

                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo langHdl('temporary_encryption_code'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="dialog-ldap-user-build-keys-database-code">
                                </div>
                                
                                <div class="form-control mt-3 font-weight-light grey" id="dialog-ldap-user-build-keys-database-progress">
                                    <?php echo langHdl('provide_current_psk_and_click_launch'); ?>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="sharekeys_reencryption_target_user" value="">
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" id="dialog-ldap-user-build-keys-database-do"><?php echo langHdl('launch'); ?></button>
                        <button class="btn btn-default float-right" id="dialog-ldap-user-build-keys-database-close"><?php echo langHdl('close'); ?></button>
                    </div>
                </div>
                <!-- /.ENCRYPTION KEYS GENERATION -->
              
               
               <!-- ENCRYPTION PERSONAL ITEMS GENERATION -->
                <div class="card card-warning m-3 hidden" id="dialog-encryption-personal-items-after-upgrade">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bullhorn mr-2"></i>
                            <?php echo langHdl('your_attention_is_required'); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="mb-2 alert alert-info" id="warning-text-changing-password">
                                    <i class="icon fas fa-info mr-2"></i>
                                    <?php echo langHdl('objects_encryption_explanation'); ?>
                                </div>
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo langHdl('personal_salt_key'); ?></span>
                                    </div>
                                    <input type="password" class="form-control" id="user-current-defuse-psk">
                                </div>
                                <div class="form-control mt-3 font-weight-light grey" id="user-current-defuse-psk-progress">
                                    <?php echo langHdl('provide_current_psk_and_click_launch'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" id="button_do_personal_items_reencryption"><?php echo langHdl('launch'); ?></button>
                        <button class="btn btn-default float-right" id="button_close_personal_items_reencryption"><?php echo langHdl('close'); ?></button>
                    </div>
                </div>
                <!-- /.ENCRYPTION PERSONAL ITEMS GENERATION -->
             

                <?php
                    if ($session_initial_url !== null && empty($session_initial_url) === false) {
                        include $session_initial_url;
                    } elseif ($get['page'] === 'items') {
                        // SHow page with Items
                        if ((int) $session_user_admin !== 1) {
                            include $SETTINGS['cpassman_dir'] . '/pages/items.php';
                        } elseif ((int) $session_user_admin === 1) {
                            include $SETTINGS['cpassman_dir'] . '/pages/admin.php';
                        } else {
                            $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
                            //not allowed page
                            include $SETTINGS['cpassman_dir'] . '/error.php';
                        }
                    } elseif (in_array($get['page'], array_keys($mngPages)) === true) {
                        // Define if user is allowed to see management pages
                        if ($session_user_admin === 1) {
                            include $SETTINGS['cpassman_dir'] . '/pages/' . $mngPages[$get['page']];
                        } elseif ($session_user_manager === 1 || $session_user_human_resources === 1) {
                            if ($get['page'] !== 'manage_main'
                                && $get['page'] !== 'manage_settings'
                            ) {
                                //include $SETTINGS['cpassman_dir'] . '/pages/' . $mngPages[$_GET['page']];
                            } else {
                                $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
                                //not allowed page
                                include $SETTINGS['cpassman_dir'] . '/error.php';
                            }
                        } else {
                            $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
                            //not allowed page
                            include $SETTINGS['cpassman_dir'] . '/error.php';
                        }
                    } elseif (empty($get['page']) === false) {
                        include $SETTINGS['cpassman_dir'] . '/pages/' . $get['page'] . '.php';
                    } else {
                        $_SESSION['error']['code'] = ERR_NOT_EXIST;
                        //page doesn't exist
                        include $SETTINGS['cpassman_dir'].'/error.php';
                    }

    // Case where login attempts have been identified
    if (isset($_SESSION['unsuccessfull_login_attempts']) === true
        && $_SESSION['unsuccessfull_login_attempts_nb'] !== 0
        && $_SESSION['unsuccessfull_login_attempts_shown'] === false
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
                    <h5><?php echo langHdl('last_items_title'); ?></h5>
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
                    <?php echo langHdl('version_alone'); ?>&nbsp;<?php echo TP_VERSION_FULL; ?>
                </div>
                <!-- Default to the left -->
                <strong>Copyright &copy; <?php echo TP_COPYRIGHT; ?> <a href="<?php echo TEAMPASS_URL; ?>"><?php echo TP_TOOL_NAME; ?></a>.</strong> All rights reserved.
            </footer>
        </div>
        <!-- ./wrapper -->

    <?php
        /*
    // SENDING STATISTICS?
    if (isset($SETTINGS['send_stats']) && $SETTINGS['send_stats'] === '1'
        && (!isset($_SESSION['temporary']['send_stats_done']) || $_SESSION['temporary']['send_stats_done'] !== '1')
    ) {
        echo '
<input type="hidden" name="send_statistics" id="send_statistics" value="1" />';
    } else {
        echo '
<input type="hidden" name="send_statistics" id="send_statistics" value="0" />';
    }
    */

        /* MAIN PAGE */
        echo '
<input type="hidden" id="temps_restant" value="', $_SESSION['sessionDuration'] ?? '', '" />';
} elseif ((empty($session_user_id) === false
            && $session_user_id !== null)
        || empty($session_user_id) === true
        || $session_user_id === null
    ) {
    // case where user not logged and can't access a direct link
    if (empty($get['page']) === false) {
        $superGlobal->put(
            'initialUrl',
            filter_var(
                substr($server['request_uri'], strpos($server['request_uri'], 'index.php?')),
                FILTER_SANITIZE_URL
            ),
            'SESSION'
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
    }
    $superGlobal->put('initialUrl', '', 'SESSION');

    // LOGIN form
    include $SETTINGS['cpassman_dir'] . '/includes/core/login.php';
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
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.css">
    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>
    <!-- jQuery -->
    <script src="plugins/jqueryUI/jquery-ui.min.js"></script>
    <!-- Popper -->
    <script src="plugins/popper/umd/popper.min.js"></script>
    <!-- Bootstrap -->
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE -->
    <script src="plugins/adminlte/js/adminlte.min.js"></script>
    <!-- Altertify -->
    <!--<script type="text/javascript" src="plugins/alertifyjs/alertify.min.js"></script>-->
    <!-- Toastr -->
    <script type="text/javascript" src="plugins/toastr/toastr.min.js"></script>
    <!-- STORE.JS -->
    <script type="text/javascript" src="plugins/store.js/dist/store.everything.min.js"></script>
    <!-- aes -->
    <script type="text/javascript" src="includes/libraries/Encryption/Crypt/aes.js"></script>
    <script type="text/javascript" src="includes/libraries/Encryption/Crypt/aes-ctr.js"></script>
    <!-- pace -->
    <script type="text/javascript" data-pace-options='{ "ajax": true }' src="plugins/pace-progress/pace.min.js"></script>
    <!-- clipboardjs -->
    <script type="text/javascript" src="plugins/clipboard/clipboard.min.js"></script>
    <!-- select2 -->
    <script type="text/javascript" src="plugins/select2/js/select2.full.min.js"></script>
    <!-- simplePassMeter -->
    <link rel="stylesheet" href="plugins/simplePassMeter/simplePassMeter.css" type="text/css" />
    <script type="text/javascript" src="plugins/simplePassMeter/simplePassMeter.js"></script>
    <!-- platform -->
    <script type="text/javascript" src="plugins/platform/platform.js"></script>
    <!-- radiobuttons -->
    <link rel="stylesheet" href="plugins/radioforbuttons/bootstrap-buttons.min.css" type="text/css" />
    <script type="text/javascript" src="plugins/radioforbuttons/jquery.radiosforbuttons.min.js"></script>
    <!-- ICHECK -->
    <!--<link rel="stylesheet" href="./plugins/icheck-material/icheck-material.min.css">-->
    <link rel="stylesheet" href="./plugins/icheck/skins/all.css">
    <script type="text/javascript" src="./plugins/icheck/icheck.min.js"></script>
    <!-- bootstrap-add-clear -->
    <script type="text/javascript" src="plugins/bootstrap-add-clear/bootstrap-add-clear.min.js"></script>

    <?php
    $get = [];
    $get['page'] = $superGlobal->get('page', 'GET') === null ? '' : $superGlobal->get('page', 'GET');
    if ($menuAdmin === true) {
        ?>
        <link rel="stylesheet" href="./plugins/toggles/css/toggles.css" />
        <link rel="stylesheet" href="./plugins/toggles/css/toggles-modern.css" />
        <script src="./plugins/toggles/toggles.min.js" type="text/javascript"></script>
        <!-- InputMask -->
        <script src="./plugins/inputmask/jquery.inputmask.min.js"></script>
        <!-- Sortable -->
        <!--<script src="./plugins/sortable/jquery.sortable.js"></script>-->
        <!-- PLUPLOAD -->
        <script type="text/javascript" src="includes/libraries/Plupload/plupload.full.min.js"></script>
    <?php
    } elseif (isset($get['page']) === true) {
        if (in_array($get['page'], ['items', 'import']) === true) {
            ?>
            <link rel="stylesheet" href="./plugins/jstree/themes/default/style.min.css" />
            <script src="./plugins/jstree/jstree.min.js" type="text/javascript"></script>
            <!-- SUMMERNOTE -->
            <link rel="stylesheet" href="./plugins/summernote/summernote-bs4.css">
            <script src="./plugins/summernote/summernote-bs4.min.js"></script>
            <!-- date-picker -->
            <link rel="stylesheet" href="./plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css">
            <script src="./plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js"></script>
            <!-- time-picker -->
            <link rel="stylesheet" href="./plugins/timepicker/bootstrap-timepicker.min.css">
            <script src="./plugins/timepicker/bootstrap-timepicker.min.js"></script>
            <!-- PLUPLOAD -->
            <script type="text/javascript" src="includes/libraries/Plupload/plupload.full.min.js"></script>
            <!-- VALIDATE -->
            <script type="text/javascript" src="plugins/jquery-validation/jquery.validate.js"></script>
            <!-- PWSTRENGHT -->
            <!--<script type="text/javascript" src="plugins/jquery.pwstrength/i18next.js"></script>-->
            <script type="text/javascript" src="plugins/zxcvbn/zxcvbn.js"></script>
            <script type="text/javascript" src="plugins/jquery.pwstrength/pwstrength-bootstrap.min.js"></script>
        <?php
        } elseif (in_array($get['page'], ['search', 'folders', 'users', 'roles', 'utilities.deletion', 'utilities.logs', 'utilities.database', 'utilities.renewal']) === true) {
            ?>
            <!-- DataTables -->
            <link rel="stylesheet" src="./plugins/datatables/css/jquery.dataTables.min.css">
            <link rel="stylesheet" src="./plugins/datatables/css/dataTables.bootstrap4.min.css">
            <script type="text/javascript" src="./plugins/datatables/js/jquery.dataTables.min.js"></script>
            <script type="text/javascript" src="./plugins/datatables/js/dataTables.bootstrap4.min.js"></script>
            <link rel="stylesheet" src="./plugins/datatables/extensions/Responsive-2.2.2/css/responsive.bootstrap4.min.css">
            <script type="text/javascript" src="./plugins/datatables/extensions/Responsive-2.2.2/js/dataTables.responsive.min.js"></script>
            <script type="text/javascript" src="./plugins/datatables/extensions/Responsive-2.2.2/js/responsive.bootstrap4.min.js"></script>
            <script type="text/javascript" src="./plugins/datatables/plugins/select.js"></script>
            <link rel="stylesheet" src="./plugins/datatables/extensions/Scroller-1.5.0/css/scroller.bootstrap4.min.css">
            <script type="text/javascript" src="./plugins/datatables/extensions/Scroller-1.5.0/js/dataTables.scroller.min.js"></script>
            <!-- dater picker -->
            <link rel="stylesheet" href="./plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css">
            <script src="./plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js"></script>
            <!-- daterange picker -->
            <link rel="stylesheet" href="./plugins/daterangepicker/daterangepicker.css">
            <script src="./plugins/moment/moment.min.js"></script>
            <script src="./plugins/daterangepicker/daterangepicker.js"></script>
            <!-- SlimScroll -->
            <script src="./plugins/slimScroll/jquery.slimscroll.min.js"></script>
            <!-- FastClick -->
            <script src="./plugins/fastclick/fastclick.min.js"></script>
        <?php
        } elseif ($get['page'] === 'profile') {
            ?>
            <!-- PLUPLOAD -->
            <script type="text/javascript" src="includes/libraries/Plupload/plupload.full.min.js"></script>
        <?php
        } elseif ($get['page'] === 'export') {
            ?>
            <!-- FILESAVER -->
            <script type="text/javascript" src="plugins/downloadjs/download.js"></script>
        <?php
        }
    }
    ?>
    <!-- functions -->
    <script type="text/javascript" src="includes/js/functions.js"></script>

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


<?php
$get = [];
$get['page'] = $superGlobal->get('page', 'GET') === null ? '' : $superGlobal->get('page', 'GET');

// Load links, css and javascripts
if (
    isset($_SESSION['CPM']) === true
    && isset($SETTINGS['cpassman_dir']) === true
) {
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
        } elseif ($get['page'] === 'actions') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/actions.js.php';
        } elseif ($get['page'] === 'fields') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/fields.js.php';
        } elseif ($get['page'] === 'options') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/options.js.php';
        } elseif ($get['page'] === 'statistics') {
            include_once $SETTINGS['cpassman_dir'] . '/pages/statistics.js.php';
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

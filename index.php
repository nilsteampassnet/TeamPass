<?php
/**
 * Teampass - a collaborative passwords manager
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 * @package   Index
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2018 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * @version   GIT: <git_id>
 * @link      http://www.teampass.net
 */

header('X-XSS-Protection: 1; mode=block');
header('X-Frame-Options: SameOrigin');

// **PREVENTING SESSION HIJACKING**
// Prevents javascript XSS attacks aimed to steal the session ID
ini_set('session.cookie_httponly', 1);

// **PREVENTING SESSION FIXATION**
// Session ID cannot be passed through URLs
ini_set('session.use_only_cookies', 1);

// Uses a secure connection (HTTPS) if possible
ini_set('session.cookie_secure', 0);

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
    exit();
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

// Include files
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

// initialize session
$_SESSION['CPM'] = 1;
if (isset($SETTINGS['cpassman_dir']) === false || $SETTINGS['cpassman_dir'] === '') {
    $SETTINGS['cpassman_dir'] = '.';
    $SETTINGS['cpassman_url'] = $superGlobal->get('REQUEST_URI', 'SERVER');
}

// Include files
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

// Open MYSQL database connection
require_once './includes/libraries/Database/Meekrodb/db.class.php';
DB::$host         = DB_HOST;
DB::$user         = DB_USER;
DB::$password     = defuse_return_decrypted(DB_PASSWD);
DB::$dbName       = DB_NAME;
DB::$port         = DB_PORT;
DB::$encoding     = DB_ENCODING;
//DB::$errorHandler = true;

$link = mysqli_connect(DB_HOST, DB_USER, defuse_return_decrypted(DB_PASSWD), DB_NAME, DB_PORT);
$link->set_charset(DB_ENCODING);

// Load Core library
require_once $SETTINGS['cpassman_dir'].'/sources/core.php';

// Prepare POST variables
$post_language     = filter_input(INPUT_POST, 'language', FILTER_SANITIZE_STRING);
$post_sig_response = filter_input(INPUT_POST, 'sig_response', FILTER_SANITIZE_STRING);
$post_duo_login    = filter_input(INPUT_POST, 'duo_login', FILTER_SANITIZE_STRING);
$post_duo_pwd      = filter_input(INPUT_POST, 'duo_pwd', FILTER_SANITIZE_STRING);
$post_duo_data     = filter_input(INPUT_POST, 'duo_data', FILTER_SANITIZE_STRING);
$post_login        = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING);
$post_pw           = filter_input(INPUT_POST, 'pw', FILTER_SANITIZE_STRING);

// Prepare superGlobal variables
$session_user_language        = $superGlobal->get('user_language', 'SESSION');
$session_user_id              = $superGlobal->get('user_id', 'SESSION');
$session_user_flag            = $superGlobal->get('user_language_flag', 'SESSION');
$session_user_admin           = $superGlobal->get('user_admin', 'SESSION');
$session_user_human_resources = $superGlobal->get('user_can_manage_all_users', 'SESSION');
$session_user_avatar_thumb    = $superGlobal->get('user_avatar_thumb', 'SESSION');
$session_name                 = $superGlobal->get('name', 'SESSION');
$session_lastname             = $superGlobal->get('lastname', 'SESSION');
$session_user_manager         = $superGlobal->get('user_manager', 'SESSION');
$session_user_read_only       = $superGlobal->get('user_read_only', 'SESSION');
$session_is_admin             = $superGlobal->get('is_admin', 'SESSION');
$session_login                = $superGlobal->get('login', 'SESSION');
$session_validite_pw          = $superGlobal->get('validite_pw', 'SESSION');
$session_nb_folders           = $superGlobal->get('nb_folders', 'SESSION');
$session_nb_roles             = $superGlobal->get('nb_roles', 'SESSION');
$session_autoriser            = $superGlobal->get('autoriser', 'SESSION');
$session_hide_maintenance     = $superGlobal->get('hide_maintenance', 'SESSION');
$session_initial_url          = $superGlobal->get('initial_url', 'SESSION');
$server_request_uri           = $superGlobal->get('REQUEST_URI', 'SERVER');
$session_nb_users_online      = $superGlobal->get('nb_users_online', 'SESSION');
$pageSel                      = $superGlobal->get('page', 'GET');

/* DEFINE WHAT LANGUAGE TO USE */
if (isset($_GET['language']) === true) {
    // case of user has change language in the login page
    $dataLanguage = DB::queryFirstRow(
        'SELECT flag, name
        FROM '.prefixTable('languages').'
        WHERE name = %s',
        filter_var($_GET['language'], FILTER_SANITIZE_STRING)
    );
    $superGlobal->put('user_language', $dataLanguage['name'], 'SESSION');
    $superGlobal->put('user_language_flag', $dataLanguage['flag'], 'SESSION');
} elseif ($session_user_id === null && null === $post_language && $session_user_language === null) {
    //get default language
    $dataLanguage = DB::queryFirstRow(
        'SELECT m.valeur AS valeur, l.flag AS flag
        FROM '.prefixTable('misc').' AS m
        INNER JOIN '.prefixTable('languages').' AS l ON (m.valeur = l.name)
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
} elseif (null !== $post_language) {
    $superGlobal->put('user_language', $post_language, 'SESSION');
    $session_user_language = $post_language;
} elseif ($session_user_language === null || empty($session_user_language) === true) {
    if (null !== $post_language) {
        $superGlobal->put('user_language', $post_language, 'SESSION');
        $session_user_language = $post_language;
    } elseif ($session_user_language !== null) {
        $superGlobal->put('user_language', $SETTINGS['default_language'], 'SESSION');
        $session_user_language = $SETTINGS['default_language'];
    }
} elseif ($session_user_language === '0') {
    $superGlobal->put('user_language', $SETTINGS['default_language'], 'SESSION');
    $session_user_language = $SETTINGS['default_language'];
}

if (isset($SETTINGS['cpassman_dir']) === false || $SETTINGS['cpassman_dir'] === '') {
    $SETTINGS['cpassman_dir'] = '.';
    $SETTINGS['cpassman_url'] = (string) $server_request_uri;
}

// Load user languages files
if (in_array($session_user_language, $languagesList) === true) {
    if (file_exists($SETTINGS['cpassman_dir'].'/includes/language/'.$session_user_language.'.php') === true) {
        $_SESSION['teampass']['lang'] = include $SETTINGS['cpassman_dir'].'/includes/language/'.$session_user_language.'.php';
    }
} else {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
}

// load 2FA Google
if (isset($SETTINGS['google_authentication']) === true && $SETTINGS['google_authentication'] === '1') {
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/TwoFactorAuth/TwoFactorAuth.php';
}

// load 2FA Yubico
if (isset($SETTINGS['yubico_authentication']) === true && $SETTINGS['yubico_authentication'] === '1') {
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/Yubico/Yubico.php';
}

// Some template adjust
if (array_key_exists($pageSel, $mngPages) === true) {
    $menuAdmin = true;
} else {
    $menuAdmin = false;
}

?>
<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>

<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<head>
    <meta http-equiv='Content-Type' content='text/html;charset=utf-8' />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Teampass</title>
    <script type='text/javascript'>
        //<![CDATA[
        if (window.location.href.indexOf('page=') === -1
            && (window.location.href.indexOf('otv=') === -1
            && window.location.href.indexOf('action=') === -1)
        ) {
            if (window.location.href.indexOf('session_over=true') !== -1) {
                location.replace('./logout.php');
            }
        }
        //]]>
    </script>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="plugins/font-awesome/css/font-awesome.min.css">
    <!-- IonIcons -->
    <link rel="stylesheet" href="includes/css/ionicons.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="includes/css/teampass.css">
    <!-- Google Font: Source Sans Pro -->
    <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
    <!-- Theme style -->
    <link rel="stylesheet" href="includes/css/teampass.css">
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" type="text/css" href="plugins/font-source-sans-pro">
    <!-- Altertify -->
    <link rel="stylesheet" href="plugins/alertifyjs/css/alertify.min.css"/>
    <link rel="stylesheet" href="plugins/alertifyjs/css/themes/bootstrap.min.css"/>
    
    </head>



<?php
//print_r($_SESSION);
// display an item in the context of OTV link
if (($session_validite_pw === null
    || empty($session_validite_pw) === true
    || empty($session_user_id) === true)
    && isset($_GET['otv']) === true
    && filter_var($_GET['otv'], FILTER_SANITIZE_STRING) === 'true'
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
                substr(
                    $server_request_uri,
                    strpos($server_request_uri, "index.php?")
                ),
                FILTER_SANITIZE_URL
            ),
            "SESSION"
        );
        include $SETTINGS['cpassman_dir'].'/error.php';
    }
} elseif ($session_validite_pw !== null
    && $session_validite_pw === true
    && empty($_GET['page']) === false
    && empty($session_user_id) === false
) {
    // Do some template preparation
    // Avatar
    if ($session_user_avatar_thumb !== null && empty($session_user_avatar_thumb) === false) {
        if (file_exists('includes/avatars/'.$session_user_avatar_thumb)) {
            $avatar = $SETTINGS['cpassman_url'].'/includes/avatars/'.$session_user_avatar_thumb;
        } else {
            $avatar = $SETTINGS['cpassman_url'].'/includes/images/photo.jpg';
        }
    } else {
        $avatar = $SETTINGS['cpassman_url'].'/includes/images/photo.jpg';
    }
    ?>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand bg-white navbar-light border-bottom">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#"><i class="fa fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="index.html" class="nav-link">Home</a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="#" class="nav-link">Contact</a>
            </li>
        </ul>

        <!-- SEARCH FORM -->
        <!--
        <form class="form-inline ml-3">
        <div class="input-group input-group-sm">
            <input class="form-control form-control-navbar" type="search" placeholder="Search" aria-label="Search">
            <div class="input-group-append">
            <button class="btn btn-navbar" type="submit">
                <i class="fa fa-search"></i>
            </button>
            </div>
        </div>
        </form>
        -->

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <!-- Messages Dropdown Menu -->
            <li class="nav-item dropdown">

                <div class="dropdown show">
                    <a class="btn btn-secondary btn-sm  dropdown-toggle" href="#" data-toggle="dropdown">
                        <?php
                        echo $session_name.'&nbsp;'.$session_lastname;
                        ?>
                    </a>

                    <div class="dropdown-menu dropdown-menu-right">
                        <?php
                        echo ($session_user_admin === '1' && TP_ADMIN_FULL_RIGHT === true) ? '' : isset($SETTINGS['enable_pf_feature']) === true && $SETTINGS['enable_pf_feature'] == 1 ? '
                        <a class="dropdown-item user-menu" href="#" data-name="set_psk">
                            <span class="fa fa-key fa-fw"></span>&nbsp;'.langHdl('home_personal_saltkey_button').'
                        </a>' : '', '
                        <a class="dropdown-item user-menu" href="#" data-name="increase_session">
                            <span class="fa fa-clock-o fa-fw"></span>&nbsp;'.langHdl('index_add_one_hour').'
                        </a>
                        <a class="dropdown-item user-menu" href="#" data-name="profile">
                            <span class="fa fa-user fa-fw"></span>&nbsp;'.langHdl('my_profile').'
                        </a>
                        <a class="dropdown-item user-menu" href="#" data-name="logout">
                            <span class="fa fa-sign-out fa-fw"></span>&nbsp;'.langHdl('disconnect').'
                        </a>';
                        ?>
                    </div>
                </div>
            </li>


            <!-- Messages Dropdown Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="fa fa-comments-o"></i>
                    <span class="badge badge-danger navbar-badge">3</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <a href="#" class="dropdown-item">
                    <!-- Message Start -->
                    <div class="media">
                    <img src="dist/img/user1-128x128.jpg" alt="User Avatar" class="img-size-50 mr-3 img-circle">
                    <div class="media-body">
                        <h3 class="dropdown-item-title">
                            Brad Diesel
                            <span class="float-right text-sm text-danger"><i class="fa fa-star"></i></span>
                        </h3>
                        <p class="text-sm">Call me whenever you can...</p>
                        <p class="text-sm text-muted"><i class="fa fa-clock-o mr-1"></i> 4 Hours Ago</p>
                    </div>
                    </div>
                    <!-- Message End -->
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <!-- Message Start -->
                    <div class="media">
                    <img src="dist/img/user8-128x128.jpg" alt="User Avatar" class="img-size-50 img-circle mr-3">
                    <div class="media-body">
                        <h3 class="dropdown-item-title">
                            coucou
                        <span class="float-right text-sm text-muted"><i class="fa fa-star"></i></span>
                        </h3>
                        <p class="text-sm">I got your message bro</p>
                        <p class="text-sm text-muted"><i class="fa fa-clock-o mr-1"></i> 4 Hours Ago</p>
                    </div>
                    </div>
                    <!-- Message End -->
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <!-- Message Start -->
                    <div class="media">
                    <img src="dist/img/user3-128x128.jpg" alt="User Avatar" class="img-size-50 img-circle mr-3">
                    <div class="media-body">
                        <h3 class="dropdown-item-title">
                        Nora Silvester
                        <span class="float-right text-sm text-warning"><i class="fa fa-star"></i></span>
                        </h3>
                        <p class="text-sm">The subject goes here</p>
                        <p class="text-sm text-muted"><i class="fa fa-clock-o mr-1"></i> 4 Hours Ago</p>
                    </div>
                    </div>
                    <!-- Message End -->
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item dropdown-footer">See All Messages</a>
                </div>
            </li>
            <!-- Notifications Dropdown Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="fa fa-bell-o"></i>
                <span class="badge badge-warning navbar-badge">15</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <span class="dropdown-header">15 Notifications</span>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <i class="fa fa-envelope mr-2"></i> 4 new messages
                    <span class="float-right text-muted text-sm">3 mins</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <i class="fa fa-users mr-2"></i> 8 friend requests
                    <span class="float-right text-muted text-sm">12 hours</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <i class="fa fa-file mr-2"></i> 3 new reports
                    <span class="float-right text-muted text-sm">2 days</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item dropdown-footer">See All Notifications</a>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#"><i
                    class="fa fa-th-large"></i></a>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="index.php?page=admin" class="brand-link">
            <img src="dist/img/AdminLTELogo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3"
                style="opacity: .8">
            <span class="brand-text font-weight-light">Teampass</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <!-- Add icons to the links using the .nav-icon class
                        with font-awesome or any other icon font library -->
                    <!--
                        <li class="nav-item has-treeview menu-open">
                        <a href="#" class="nav-link active">
                        <i class="nav-icon fa fa-dashboard"></i>
                        <p>
                            Starter Pages
                            <i class="right fa fa-angle-left"></i>
                        </p>
                        </a>
                        <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="#" class="nav-link active">
                            <i class="fa fa-circle-o nav-icon"></i>
                            <p>Active Page</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                            <i class="fa fa-circle-o nav-icon"></i>
                            <p>Inactive Page</p>
                            </a>
                        </li>
                        </ul>
                    </li>
                    -->
                    <?php
                    if ($session_user_admin === '0' || TP_ADMIN_FULL_RIGHT === false) {
                        // ITEMS & SEARCH
                        echo '
                    <li class="nav-item">
                        <a href="#" data-name="items" class="nav-link', $pageSel === 'items' ? ' active' : '' ,'"">
                        <i class="nav-icon fa fa-key"></i>
                        <p>
                            '.langHdl('pw').'
                        </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" data-name="search" class="nav-link', $pageSel === 'search' ? ' active' : '' ,'"">
                        <i class="nav-icon fa fa-binoculars"></i>
                        <p>
                            '.langHdl('find').'
                        </p>
                        </a>
                    </li>';
                    }

                    // Favourites menu
                    if (isset($SETTINGS['enable_favourites'])
                        && $SETTINGS['enable_favourites'] === '1'
                        && ($session_user_admin === '0'
                        || ($session_user_admin === '1'
                        && TP_ADMIN_FULL_RIGHT === false))
                    ) {
                        echo '
                    <li class="nav-item">
                        <a href="#" data-name="favourites" class="nav-link', $pageSel === 'admin' ? ' favourites' : '' ,'"">
                        <i class="nav-icon fa fa-star"></i>
                        <p>
                            '.langHdl('my_favourites').'
                        </p>
                        </a>
                    </li>';
                    }

                    // KB menu
                    if (isset($SETTINGS['enable_kb']) && $SETTINGS['enable_kb'] === '1'
                    ) {
                        echo '
                    <li class="nav-item">
                        <a href="#" data-name="kb" class="nav-link', $pageSel === 'kb' ? ' active' : '' ,'"">
                        <i class="nav-icon fa fa-map-signs"></i>
                        <p>
                            '.langHdl('kb_menu').'
                        </p>
                        </a>
                    </li>';
                    }

                    // SUGGESTION menu
                    if (isset($SETTINGS['enable_suggestion']) && $SETTINGS['enable_suggestion'] === '1'
                        && ($session_user_admin === '1' || $session_user_manager === '1')
                    ) {
                        echo '
                    <li class="nav-item">
                        <a href="#" data-name="suggestion" class="nav-link', $pageSel === 'suggestion' ? ' active' : '' ,'"">
                        <i class="nav-icon fa fa-lightbulb-o"></i>
                        <p>
                            '.langHdl('suggestion_menu').'
                        </p>
                        </a>
                    </li>';
                    }

                    // Admin menu
                    if ($session_user_admin === '1') {
                        echo '
                        <li class="nav-item">
                            <a href="#" data-name="admin" class="nav-link', $pageSel === 'admin' ? ' active' : '' ,'">
                            <i class="nav-icon fa fa-info"></i>
                            <p>
                                '.langHdl('admin_main').'
                            </p>
                            </a>
                        </li>
                        <li class="nav-item has-treeview', $menuAdmin === true ? ' menu-open' : '', '">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fa fa-wrench"></i>
                                <p>
                                    '.langHdl('admin_settings').'
                                    <i class="fa fa-angle-left right"></i>
                                </p>
                            </a>
                            <ul class="nav-item nav-treeview">
                                <li class="nav-item">
                                    <a href="#" data-name="options" class="nav-link', $pageSel === 'options' ? ' active' : '' ,'">
                                        <i class="fa fa-check-square-o nav-icon"></i>
                                        <p>'.langHdl('options').'</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="#" data-name="statistics" class="nav-link', $pageSel === 'statistics' ? ' active' : '' ,'">
                                        <i class="fa fa-area-chart nav-icon"></i>
                                        <p>'.langHdl('statistics').'</p>
                                    </a>
                                </li>
                            </ul>
                        </li>';

                        if ($session_user_admin === '1'
                            || $session_user_manager === '1'
                            || $session_user_human_resources === '1'
                        ) {
                            echo '
                        <li class="nav-item">
                            <a href="#" class="nav-link', $pageSel === 'folders' ? ' active' : '' ,'"">
                            <i class="nav-icon fa fa-folder-open"></i>
                            <p>
                                '.langHdl('folders').'
                            </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link', $pageSel === 'functions' ? ' active' : '' ,'"">
                            <i class="nav-icon fa fa-graduation-cap"></i>
                            <p>
                                '.langHdl('roles').'
                            </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link', $pageSel === 'users' ? ' active' : '' ,'"">
                            <i class="nav-icon fa fa-users"></i>
                            <p>
                                '.langHdl('users').'
                            </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link', $pageSel === 'views' ? ' active' : '' ,'"">
                            <i class="nav-icon fa fa-cubes"></i>
                            <p>
                                '.langHdl('admin_views').'
                            </p>
                            </a>
                        </li>';
                        }
                    }
                    ?>
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
    <?php
    if ($session_initial_url !== null && empty($session_initial_url) === false) {
        include $session_initial_url;
    } elseif ($_GET['page'] == "items") {
        // SHow page with Items
        if (($session_user_admin !== '1')
            ||($session_user_admin === '1'
            && $SETTINGS_EXT['admin_full_right'] === false)
        ) {
            include $SETTINGS['cpassman_dir'].'/pages/items.php';
        } else {
            $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
            include $SETTINGS['cpassman_dir'].'/error.php';
        }
    } elseif (in_array($_GET['page'], array_keys($mngPages)) === true) {
        // Define if user is allowed to see management pages
        if ($session_user_admin === '1') {
            include $SETTINGS['cpassman_dir'].'./pages/'.$mngPages[$_GET['page']];
        } elseif ($session_user_manager === '1' || $session_user_human_resources == '1') {
            if (($_GET['page'] !== "manage_main" && $_GET['page'] !== "manage_settings")) {
                include $SETTINGS['cpassman_dir'].'./pages/'.$mngPages[$_GET['page']];
            } else {
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $SETTINGS['cpassman_dir'].'/error.php';
            }
        } else {
            $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
            include $SETTINGS['cpassman_dir'].'/error.php';
        }
    } elseif (isset($_GET['page']) === true) {
        include $SETTINGS['cpassman_dir'].'./pages/'.$_GET['page'].'.php';
    } else {
        $_SESSION['error']['code'] = ERR_NOT_EXIST; //page doesn't exist
        include $SETTINGS['cpassman_dir'].'/error.php';
    }
    ?>
    </div>
    <!-- /.content-wrapper -->

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
        <!-- Control sidebar content goes here -->
        <div class="p-3">
        <h5>Title</h5>
        <?php
        echo '<p><span class="fa fa-clock-o"></span>&nbsp;'.langHdl('server_time').":<br />".
            @date($SETTINGS['date_format'], (string) $_SERVER['REQUEST_TIME'])." - ".
            @date($SETTINGS['time_format'], (string) $_SERVER['REQUEST_TIME']).'</p>'.
            '<p><span class="fa fa-users"></span>&nbsp;'.$session_nb_users_online.'&nbsp;'.langHdl('users_online').'</p>'.
            '<p><span class="fa fa-hourglass-end"></span>&nbsp;'.langHdl('index_expiration_in').'&nbsp;<span id="countdown"></span></p>';
        ?>
        </div>
    </aside>
    <!-- /.control-sidebar -->

    <!-- Main Footer -->
    <footer class="main-footer">
        <!-- To the right -->
        <div class="float-right d-none d-sm-inline">
            <?php echo langHdl('version_alone');?>&nbsp;<?php echo TP_VERSION_FULL;?>&nbsp;
            <a href="https://teampass.readthedocs.io/en/latest/" target="_blank" class="infotip" title="<?php echo langHdl('documentation_canal');?> ReadTheDocs"><i class="fa fa-book"></i></a>
            &nbsp;
            <a href="https://www.reddit.com/r/TeamPass/" target="_blank" class="infotip" title="<?php echo langHdl('admin_help');?>"><i class="fa fa-reddit-alien"></i></a>
            &nbsp;
            <a href="#" class="infotip" title="<?php echo langHdl('bugs_page');?>" onclick="generateBugReport()"><i class="fa fa-bug"></i></a>
        </div>
        <!-- Default to the left -->
        <strong>Copyright &copy; <?php echo TP_COPYRIGHT;?> <a href="https://teampass.net"><?php echo TP_TOOL_NAME;?></a>.</strong> All rights reserved.
    </footer>
</div>
<!-- ./wrapper -->

    <?php
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

    /* MAIN PAGE */
    echo '
<input type="hidden" id="temps_restant" value="', isset($_SESSION['fin_session']) ? $_SESSION['fin_session'] : '', '" />
<input type="hidden" name="language" id="language" value="" />
<input type="hidden" name="user_pw_complexity" id="user_pw_complexity" value="', isset($_SESSION['user_pw_complexity']) ? $_SESSION['user_pw_complexity'] : '', '" />
<input type="hidden" name="user_session" id="user_session" value=""/>
<input type="hidden" name="encryptClientServer" id="encryptClientServer" value="', isset($SETTINGS['encryptClientServer']) ? $SETTINGS['encryptClientServer'] : '1', '" />
<input type="hidden" name="please_login" id="please_login" value="" />
<input type="hidden" name="disabled_action_on_going" id="disabled_action_on_going" value="" />
<input type="hidden" id="duo_sig_response" value="', null !== $post_sig_response ? $post_sig_response : '', '" />';
} elseif ((empty($session_user_id) === false
    && $session_user_id !== null)
    || empty($session_user_id) === true
    || $session_user_id === null
) {
    // case where user not logged and can't access a direct link
    if (empty($_GET['page']) === false) {
        $superGlobal->put(
            "initialUrl",
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
        $superGlobal->put("initialUrl", '', "SESSION");
    }

    // LOGIN form
    include $SETTINGS['cpassman_dir'].'/login.php';
}

?>




<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="dist/js/adminlte.js"></script>
<!-- Altertify -->
<script src="plugins/alertifyjs/alertify.min.js"></script>
<!-- aes -->
<script type="text/javascript" src="includes/libraries/Encryption/Crypt/aes.js"></script>
<script type="text/javascript" src="includes/libraries/Encryption/Crypt/aes-ctr.js"></script>
<!-- functions -->
<script type="text/javascript" src="includes/js/functions.js"></script>
<!-- nprogress -->
<link rel="stylesheet" href="plugins/nprogress/nprogress.css" type="text/css" />
<script type="text/javascript" src="plugins/nprogress/nprogress.js"></script>
<!-- clipboardjs -->
<script type="text/javascript" src="plugins/clipboard/clipboard.min.js"></script>
<?php
if ($menuAdmin === true) {
?>
<link rel="stylesheet" href="./plugins/toggles/css/toggles.css" />
<link rel="stylesheet" href="./plugins/toggles/css/toggles-modern.css" />
<script src="./plugins/toggles/toggles.min.js" type="text/javascript"></script>
<?php
} elseif ($pageSel === 'items') {
?>
<link rel="stylesheet" href="./plugins/jstree/themes/default/style.min.css" />
<script src="./plugins/jstree/jstree.min.js" type="text/javascript"></script>
<?php
}
?>

</body>
</html>

<script type="text/javascript">
NProgress.start();
//override defaults
alertify.defaults.transition = "slide";
alertify.defaults.theme.ok = "btn btn-primary";
alertify.defaults.theme.cancel = "btn btn-danger";
alertify.defaults.theme.input = "form-control";
</script>


<?php

// Load links, css and javascripts
if (isset($_SESSION['CPM']) === true
    && isset($SETTINGS['cpassman_dir']) === true
) {
    include_once $SETTINGS['cpassman_dir'].'/load.js.php';

    if ($menuAdmin === true) {
        include_once $SETTINGS['cpassman_dir'].'/pages/admin.js.php';
    } elseif ($pageSel === 'items') {
        include_once $SETTINGS['cpassman_dir'].'/pages/items.js.php';
    }
}

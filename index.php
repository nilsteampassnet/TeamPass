<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2019 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
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
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;
//DB::$errorHandler = true;

$link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
$link->set_charset(DB_ENCODING);

// Load Core library
require_once $SETTINGS['cpassman_dir'].'/sources/core.php';

// Prepare POST variables
$post_language = filter_input(INPUT_POST, 'language', FILTER_SANITIZE_STRING);
$post_sig_response = filter_input(INPUT_POST, 'sig_response', FILTER_SANITIZE_STRING);
$post_duo_login = filter_input(INPUT_POST, 'duo_login', FILTER_SANITIZE_STRING);
$post_duo_pwd = filter_input(INPUT_POST, 'duo_pwd', FILTER_SANITIZE_STRING);
$post_duo_data = filter_input(INPUT_POST, 'duo_data', FILTER_SANITIZE_STRING);
$post_login = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING);
$post_pw = filter_input(INPUT_POST, 'pw', FILTER_SANITIZE_STRING);

// Prepare superGlobal variables
$session_user_language = $superGlobal->get('user_language', 'SESSION');
$session_user_id = $superGlobal->get('user_id', 'SESSION');
$session_user_flag = $superGlobal->get('user_language_flag', 'SESSION');
$session_user_admin = (int) $superGlobal->get('user_admin', 'SESSION');
$session_user_human_resources = (int) $superGlobal->get('user_can_manage_all_users', 'SESSION');
$session_user_avatar_thumb = $superGlobal->get('user_avatar_thumb', 'SESSION');
$session_name = $superGlobal->get('name', 'SESSION');
$session_lastname = $superGlobal->get('lastname', 'SESSION');
$session_user_manager = (int) $superGlobal->get('user_manager', 'SESSION');
$session_user_read_only = $superGlobal->get('user_read_only', 'SESSION');
$session_is_admin = $superGlobal->get('is_admin', 'SESSION');
$session_login = $superGlobal->get('login', 'SESSION');
$session_validite_pw = $superGlobal->get('validite_pw', 'SESSION');
$session_nb_folders = $superGlobal->get('nb_folders', 'SESSION');
$session_nb_roles = $superGlobal->get('nb_roles', 'SESSION');
$session_autoriser = $superGlobal->get('autoriser', 'SESSION');
$session_hide_maintenance = $superGlobal->get('hide_maintenance', 'SESSION');
$session_initial_url = $superGlobal->get('initial_url', 'SESSION');
$server_request_uri = $superGlobal->get('REQUEST_URI', 'SERVER');
$session_nb_users_online = $superGlobal->get('nb_users_online', 'SESSION');
$pageSel = $superGlobal->get('page', 'GET');

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

// Some template adjust
if (array_key_exists($pageSel, $utilitiesPages) === true) {
    $menuUtilities = true;
} else {
    $menuUtilities = false;
}

?>
<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>

<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<head>
    <meta http-equiv='Content-Type' content='text/html;charset=utf-8' />
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta http-equiv="x-ua-compatible" content="ie=edge"/>
    <title>Teampass</title>
    <script type='text/javascript'>
        //<![CDATA[
        if (window.location.href.indexOf('page=') === -1
            && (window.location.href.indexOf('otv=') === -1
            && window.location.href.indexOf('action=') === -1)
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
    <link rel="stylesheet" href="includes/css/adminlte.css">
    <link rel="stylesheet" href="plugins/nprogress/nprogress.css" type="text/css" />
    <link rel="stylesheet" href="plugins/select2/select2.min.css" type="text/css" />
    <link rel="stylesheet" href="plugins/select2/select2-bootstrap.min.css" type="text/css" />
    <!-- Theme style -->
    <link rel="stylesheet" href="includes/css/teampass.css">
    <!-- Google Font: Source Sans Pro -->
    <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
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
    if (isset($_GET['code']) && empty($_GET['code']) === false
        && isset($_GET['stamp']) && empty($_GET['stamp']) === false
    ) {
        include './includes/core/otv.php';
    } else {
        $_SESSION['error']['code'] = ERR_VALID_SESSION;
        $superGlobal->put(
            'initial_url',
            filter_var(
                substr(
                    $server_request_uri,
                    strpos($server_request_uri, 'index.php?')
                ),
                FILTER_SANITIZE_URL
            ),
            'SESSION'
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
    } ?>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand bg-white navbar-light border-bottom">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
            </li>
            <!--
            <li class="nav-item d-none d-sm-inline-block">
                <a href="index.html" class="nav-link">Home</a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="#" class="nav-link">Contact</a>
            </li>
            -->
        </ul>

        <!-- SEARCH FORM -->
        <!--
        <form class="form-inline ml-3">
        <div class="input-group input-group-sm">
            <input class="form-control form-control-navbar" type="search" placeholder="Search" aria-label="Search">
            <div class="input-group-append">
            <button class="btn btn-navbar" type="submit">
                <i class="fas fa-search"></i>
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
                    <a class="btn btn-primary dropdown-toggle" href="#" data-toggle="dropdown">
                        <?php
                        echo $session_name.'&nbsp;'.$session_lastname; ?>
                    </a>

                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item user-menu" href="#" data-name="increase_session">
                            <i class="far fa-clock fa-fw mr-2"></i><?php echo langHdl('index_add_one_hour'); ?></a>
                        <div class="dropdown-divider"></div>
                        <?php
                        echo ($session_user_admin === 1 && TP_ADMIN_FULL_RIGHT === true) ? '' : isset($SETTINGS['enable_pf_feature']) === true && $SETTINGS['enable_pf_feature'] == 1 ? '
                        <a class="dropdown-item user-menu" href="#" data-name="set_psk">
                            <i class="fas fa-key fa-fw mr-2"></i>'.langHdl('home_personal_saltkey_button').'
                        </a>' : '', '
                        <a class="dropdown-item user-menu" href="#" data-name="profile">
                            <i class="fas fa-user-circle fa-fw mr-2"></i>'.langHdl('my_profile').'
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item user-menu" href="#" data-name="logout">
                            <i class="fas fa-sign-out-alt fa-fw mr-2"></i>'.langHdl('disconnect').'
                        </a>'; ?>
                    </div>
                </div>
            </li>
            <li>
                <span class="align-middle infotip ml-2 text-info" title="<?php echo langHdl('index_expiration_in'); ?>" id="countdown"></span>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#" id="controlsidebar"><i
                    class="fas fa-th-large"></i></a>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="index.php" class="brand-link">
            <img src="includes/images/logoTeampassHome.png" alt="Teampass Logo" class="brand-image img-circle elevation-3"
                style="opacity: .8">
            <span class="brand-text font-weight-light">Teampass</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <?php
                    if ($session_user_admin === 0 || TP_ADMIN_FULL_RIGHT === false) {
                        // ITEMS & SEARCH
                        echo '
                    <li class="nav-item">
                        <a href="#" data-name="items" class="nav-link', $pageSel === 'items' ? ' active' : '' ,'"">
                        <i class="nav-icon fas fa-key"></i>
                        <p>
                            '.langHdl('pw').'
                        </p>
                        </a>
                    </li>';
                    }

    // IMPORT menu
    if (isset($SETTINGS['allow_import']) === true && (int) $SETTINGS['allow_import'] === 1) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="import" class="nav-link', $pageSel === 'import' ? ' active' : '' ,'"">
                        <i class="nav-icon fas fa-file-import"></i>
                        <p>
                            '.langHdl('import').'
                        </p>
                        </a>
                    </li>';
    }

    /*
    // EXPORT menu
    if (isset($SETTINGS['roles_allowed_to_print']) === true && (int) $SETTINGS['roles_allowed_to_print'] === 1) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="export" class="nav-link', $pageSel === 'export' ? ' active' : '' ,'"">
                        <i class="nav-icon fas fa-file-export"></i>
                        <p>
                            '.langHdl('import').'
                        </p>
                        </a>
                    </li>';
    }
    */

    /*
    // OFFLINE MODE menu
    if (isset($SETTINGS['settings_offline_mode']) === true && (int) $SETTINGS['settings_offline_mode'] === 1) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="offline" class="nav-link', $pageSel === 'offline' ? ' active' : '' ,'"">
                        <i class="nav-icon fas fa-plug"></i>
                        <p>
                            '.langHdl('offline').'
                        </p>
                        </a>
                    </li>';
    }
    */

    echo '
                    <li class="nav-item">
                        <a href="#" data-name="search" class="nav-link', $pageSel === 'search' ? ' active' : '' ,'"">
                        <i class="nav-icon fas fa-search"></i>
                        <p>
                            '.langHdl('find').'
                        </p>
                        </a>
                    </li>';

    // Favourites menu
    if (isset($SETTINGS['enable_favourites']) === true && $SETTINGS['enable_favourites'] === '1'
                        && ($session_user_admin === 0 || ($session_user_admin === 1
                        && TP_ADMIN_FULL_RIGHT === false))
                    ) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="favourites" class="nav-link', $pageSel === 'admin' ? ' favourites' : '' ,'"">
                        <i class="nav-icon fas fa-star"></i>
                        <p>
                            '.langHdl('favorites').'
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
                        <a href="#" data-name="kb" class="nav-link', $pageSel === 'kb' ? ' active' : '' ,'"">
                        <i class="nav-icon fas fa-map-signs"></i>
                        <p>
                            '.langHdl('kb_menu').'
                        </p>
                        </a>
                    </li>';
    }
*/
    // SUGGESTION menu
    if (isset($SETTINGS['enable_suggestion']) && $SETTINGS['enable_suggestion'] === '1'
                                    && ($session_user_admin === 1 || $session_user_manager === 1)
                                ) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="suggestion" class="nav-link', $pageSel === 'suggestion' ? ' active' : '' ,'"">
                        <i class="nav-icon fas fa-lightbulb"></i>
                        <p>
                            '.langHdl('suggestion_menu').'
                        </p>
                        </a>
                    </li>';
    }

    // Admin menu
    if ($session_user_admin === 1) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="admin" class="nav-link', $pageSel === 'admin' ? ' active' : '' ,'">
                        <i class="nav-icon fas fa-info"></i>
                        <p>
                            '.langHdl('admin_main').'
                        </p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview', $menuAdmin === true ? ' menu-open' : '', '">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-wrench"></i>
                            <p>
                                '.langHdl('admin_settings').'
                                <i class="fas fa-angle-left right"></i>
                            </p>
                        </a>
                        <ul class="nav-item nav-treeview">
                            <li class="nav-item">
                                <a href="#" data-name="options" class="nav-link', $pageSel === 'options' ? ' active' : '' ,'">
                                    <i class="fas fa-check-double nav-icon"></i>
                                    <p>'.langHdl('options').'</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="2fa" class="nav-link', $pageSel === '2fa' ? ' active' : '' ,'">
                                    <i class="fas fa-qrcode nav-icon"></i>
                                    <p>'.langHdl('mfa_short').'</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="api" class="nav-link', $pageSel === 'api' ? ' active' : '' ,'">
                                    <i class="fas fa-cubes nav-icon"></i>
                                    <p>'.langHdl('api').'</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="backups" class="nav-link', $pageSel === 'backups' ? ' active' : '' ,'">
                                    <i class="fas fa-database nav-icon"></i>
                                    <p>'.langHdl('backups').'</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="emails" class="nav-link', $pageSel === 'emails' ? ' active' : '' ,'">
                                    <i class="fas fa-envelope nav-icon"></i>
                                    <p>'.langHdl('emails').'</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="fields" class="nav-link', $pageSel === 'fields' ? ' active' : '' ,'">
                                    <i class="fas fa-keyboard nav-icon"></i>
                                    <p>'.langHdl('fields').'</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="ldap" class="nav-link', $pageSel === 'ldap' ? ' active' : '' ,'">
                                    <i class="fas fa-id-card nav-icon"></i>
                                    <p>'.langHdl('ldap').'</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="uploads" class="nav-link', $pageSel === 'uploads' ? ' active' : '' ,'">
                                    <i class="fas fa-file-upload nav-icon"></i>
                                    <p>'.langHdl('uploads').'</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="#" data-name="statistics" class="nav-link', $pageSel === 'statistics' ? ' active' : '' ,'">
                                    <i class="fas fa-chart-bar nav-icon"></i>
                                    <p>'.langHdl('statistics').'</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="#" data-name="actions" class="nav-link', $pageSel === 'actions' ? ' active' : '' ,'">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>
                            '.langHdl('actions').'
                        </p>
                        </a>
                    </li>';
    }

    if ($session_user_admin === 1
        || $session_user_manager === 1
        || $session_user_human_resources === 1
    ) {
        echo '
                    <li class="nav-item">
                        <a href="#" data-name="folders" class="nav-link', $pageSel === 'folders' ? ' active' : '' ,'">
                        <i class="nav-icon fas fa-folder-open"></i>
                        <p>
                            '.langHdl('folders').'
                        </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" data-name="roles" class="nav-link', $pageSel === 'roles' ? ' active' : '' ,'">
                        <i class="nav-icon fas fa-graduation-cap"></i>
                        <p>
                            '.langHdl('roles').'
                        </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" data-name="users" class="nav-link', $pageSel === 'users' ? ' active' : '' ,'">
                        <i class="nav-icon fas fa-users"></i>
                        <p>
                            '.langHdl('users').'
                        </p>
                        </a>
                    </li>
                    <li class="nav-item has-treeview', $menuUtilities === true ? ' menu-open' : '', '">
                        <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-cubes"></i>
                        <p>
                            '.langHdl('admin_views').'
                            <i class="fas fa-angle-left right"></i>
                        </p>
                        </a>
                        <ul class="nav nav-treeview">
                          <li class="nav-item">
                            <a href="#" data-name="utilities.renewal" class="nav-link', $pageSel === 'utilities.renewal' ? ' active' : '' ,'">
                              <i class="far fa-calendar-alt nav-icon"></i>
                              <p>'.langHdl('renewal').'</p>
                            </a>
                          </li>
                          <li class="nav-item">
                            <a href="#" data-name="utilities.deletion" class="nav-link', $pageSel === 'utilities.deletion' ? ' active' : '' ,'">
                              <i class="fas fa-trash-alt nav-icon"></i>
                              <p>'.langHdl('deletion').'</p>
                            </a>
                          </li>
                          <li class="nav-item">
                            <a href="#" data-name="utilities.logs" class="nav-link', $pageSel === 'utilities.logs' ? ' active' : '' ,'">
                              <i class="fas fa-history nav-icon"></i>
                              <p>'.langHdl('logs').'</p>
                            </a>
                          </li>
                          <li class="nav-item">
                            <a href="#" data-name="utilities.database" class="nav-link', $pageSel === 'utilities.database' ? ' active' : '' ,'">
                              <i class="fas fa-database nav-icon"></i>
                              <p>'.langHdl('database').'</p>
                            </a>
                          </li>
                        </ul>
                    </li>';
    } ?>
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
        <div class="footer" style="margin-top:-30px; color:white;">
            <div class="ml-3" id="sidebar-footer">
                <i class="fas fa-clock-o mr-2 infotip text-info pointer" title="<?php echo langHdl('server_time').' '.
                    @date($SETTINGS['date_format'], (string) $_SERVER['REQUEST_TIME']).' - '.
                    @date($SETTINGS['time_format'], (string) $_SERVER['REQUEST_TIME']); ?>"></i>
                <i class="fas fa-users mr-2 infotip text-info pointer" title="<?php echo $session_nb_users_online.' '.langHdl('users_online'); ?>"></i>
                <a href="https://teampass.readthedocs.io/en/latest/" target="_blank" class="text-info"><i class="fas fa-book mr-2 infotip" title="<?php echo langHdl('documentation_canal'); ?> ReadTheDocs"></i></a>
                <a href="https://www.reddit.com/r/TeamPass/" target="_blank" class="text-info"><i class="fab fa-reddit-alien mr-2 infotip" title="<?php echo langHdl('admin_help'); ?>"></i></a>
                <i class="fas fa-bug infotip pointer text-info" title="<?php echo langHdl('bugs_page'); ?>" onclick="generateBugReport()"></i>
            </div>
        </div>
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">

        <!-- PERSONAL SALTKEY -->
        <div class="card card-warning m-2 hidden" id="dialog-request-psk">
            <div class="card-header">
                <h3 class="card-title">
                <i class="fas fa-key mr-2"></i>
                <?php echo langHdl('home_personal_saltkey_label'); ?>
                </h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-12 col-md-12">
                        <h6 class="text-center">
                        <?php
                        echo isset($SETTINGS['personal_saltkey_security_level']) === true
                            && empty($SETTINGS['personal_saltkey_security_level']) === false ?
                        '<div class="text-info text-center"><i class="fas fa-info mr-3"></i>'.
                        langHdl('complex_asked').' : <b>'.
                        TP_PW_COMPLEXITY[$SETTINGS['personal_saltkey_security_level']][1].
                        '</b></div>'
                        :
                        ''; ?>
                        </h6>                        

                        <input class="form-control form-control-lg" type="password" placeholder="<?php echo langHdl('personal_salt_key'); ?>" value="<?php echo isset($_SESSION['user_settings']['clear_psk']) ? (string) $_SESSION['user_settings']['clear_psk'] : ''; ?>" id="user_personal_saltkey">

                        <div class="text-center" style="margin: 10px 0 0 40%;">
                            <?php
                            echo '<div id="psk_strength"></div>'.
                                '<input type="hidden" id="psk_strength_value" />'; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-default" id="button_save_user_psk"><?php echo langHdl('submit'); ?></button>
                <button class="btn btn-default float-right close-element"><?php echo langHdl('cancel'); ?></button>
            </div>
        </div>
        <!-- /.PERSONAL SALTKEY -->


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


    <?php
    if ($session_initial_url !== null && empty($session_initial_url) === false) {
        include $session_initial_url;
    } elseif ($_GET['page'] == 'items') {
        // SHow page with Items
        if (($session_user_admin !== 1)
            || ($session_user_admin === 1
            && TP_ADMIN_FULL_RIGHT === false)
        ) {
            include $SETTINGS['cpassman_dir'].'/pages/items.php';
        } else {
            $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
            include $SETTINGS['cpassman_dir'].'/error.php';
        }
    } elseif (in_array($_GET['page'], array_keys($mngPages)) === true) {
        // Define if user is allowed to see management pages
        if ($session_user_admin === 1) {
            include $SETTINGS['cpassman_dir'].'./pages/'.$mngPages[$_GET['page']];
        } elseif ($session_user_manager === 1 || $session_user_human_resources === 1) {
            if (($_GET['page'] !== 'manage_main' && $_GET['page'] !== 'manage_settings')) {
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
        //include $SETTINGS['cpassman_dir'].'/error.php';
    }

    // Case where login attempts have been identified
    if (isset($_SESSION['unsuccessfull_login_attempts']) === true
        && $_SESSION['unsuccessfull_login_attempts']['nb'] !== 0
        && $_SESSION['unsuccessfull_login_attempts']['shown'] === false
    ) {
        ?>
        <input type="hidden" id="user-login-attempts" value="">
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
        <strong>Copyright &copy; <?php echo TP_COPYRIGHT; ?> <a href="https://teampass.net"><?php echo TP_TOOL_NAME; ?></a>.</strong> All rights reserved.
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
<input type="text" id="temps_restant" value="', isset($_SESSION['sessionDuration']) ? $_SESSION['sessionDuration'] : '', '" />';
} elseif ((empty($session_user_id) === false
    && $session_user_id !== null)
    || empty($session_user_id) === true
    || $session_user_id === null
) {
    // case where user not logged and can't access a direct link
    if (empty($_GET['page']) === false) {
        $superGlobal->put(
            'initialUrl',
            filter_var(
                substr($server_request_uri, strpos($server_request_uri, 'index.php?')),
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
    } else {
        $superGlobal->put('initialUrl', '', 'SESSION');
    }

    // LOGIN form
    include $SETTINGS['cpassman_dir'].'/includes/core/login.php';
}

?>




<!-- REQUIRED SCRIPTS -->

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="plugins/fontawesome5/css/all.css">
<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- jQuery -->
<script src="plugins/jqueryUI/jquery-ui.min.js"></script>
<!-- Popper -->
<script src="plugins/popper/umd/popper.min.js"></script>
<!-- Bootstrap -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="dist/js/adminlte.js"></script>
<!-- Altertify -->
<script type="text/javascript" src="plugins/alertifyjs/alertify.min.js"></script>
<!-- STORE.JS -->
<script type="text/javascript" src="plugins/store.js/dist/store.everything.min.js"></script>
<!-- aes -->
<script type="text/javascript" src="includes/libraries/Encryption/Crypt/aes.js"></script>
<script type="text/javascript" src="includes/libraries/Encryption/Crypt/aes-ctr.js"></script>
<!-- nprogress -->
<script type="text/javascript" src="plugins/nprogress/nprogress.js"></script>
<!-- clipboardjs -->
<script type="text/javascript" src="plugins/clipboard/clipboard.min.js"></script>
<!-- select2 -->
<script type="text/javascript" src="plugins/select2/select2.full.min.js"></script>
<!-- simplePassMeter -->
<link rel="stylesheet" href="plugins/simplePassMeter/simplePassMeter.css" type="text/css" />
<script type="text/javascript" src="plugins/simplePassMeter/simplePassMeter.js"></script>
<!-- platform -->
<script type="text/javascript" src="plugins/platform/platform.js"></script>
<!-- radiobuttons -->
<link rel="stylesheet" href="plugins/radioforbuttons/bootstrap-buttons.min.css" type="text/css" />
<script type="text/javascript" src="plugins/radioforbuttons/jquery.radiosforbuttons.min.js"></script>

<?php
if ($menuAdmin === true) {
    ?>
<link rel="stylesheet" href="./plugins/toggles/css/toggles.css" />
<link rel="stylesheet" href="./plugins/toggles/css/toggles-modern.css" />
<script src="./plugins/toggles/toggles.min.js" type="text/javascript"></script>
<!-- InputMask -->
<script src="./plugins/input-mask/jquery.inputmask.js"></script>
<script src="./plugins/input-mask/jquery.inputmask.extensions.js"></script>
<!-- Ion Slider -->
<!--<link rel="stylesheet" href="./plugins/ionslider/ion.rangeSlider.css">
<link rel="stylesheet" href="./plugins/ionslider/ion.rangeSlider.skinNice.css">
<script src="./plugins/ionslider/ion.rangeSlider.min.js"></script>-->
<!-- ICHECK -->
<link rel="stylesheet" href="./plugins/iCheck/all.css">
<script type="text/javascript" src="./plugins/iCheck/icheck.min.js"></script>
<!-- Sortable -->
<!--<script src="./plugins/sortable/jquery.sortable.js"></script>-->
<!-- PLUPLOAD -->
<script type="text/javascript" src="includes/libraries/Plupload/plupload.full.min.js"></script>
    <?php
} elseif ($pageSel === 'items' || $pageSel === 'import') {
        ?>
<link rel="stylesheet" href="./plugins/jstree/themes/default/style.min.css" />
<script src="./plugins/jstree/jstree.min.js" type="text/javascript"></script>
<!-- CKEDITOR -->
<script src="./plugins/ckeditor/ckeditor.js"></script>
<!-- date-picker -->
<link rel="stylesheet" href="./plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css">
<script src="./plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js"></script>
<!-- PLUPLOAD -->
<script type="text/javascript" src="includes/libraries/Plupload/plupload.full.min.js"></script>
<!-- VALIDATE -->
<script type="text/javascript" src="plugins/jquery-validation/jquery.validate.js"></script>
<!-- ICHECK -->
<link rel="stylesheet" href="./plugins/iCheck/all.css">
<script type="text/javascript" src="./plugins/iCheck/icheck.min.js"></script>
    <?php
    } elseif ($pageSel === 'search' || $pageSel === 'folders'
        || $pageSel === 'users' || $pageSel === 'roles'
        || $pageSel === 'utilities.deletion' || $pageSel === 'utilities.logs'
        || $pageSel === 'utilities.database'
    ) {
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
<!-- daterange picker -->
<link rel="stylesheet" href="./plugins/daterangepicker/daterangepicker-bs3.css">
<script src="./plugins/moment/moment.min.js"></script>
<script src="./plugins/daterangepicker/daterangepicker.js"></script>
<!-- SlimScroll -->
<script src="./plugins/slimScroll/jquery.slimscroll.min.js"></script>
<!-- FastClick -->
<script src="./plugins/fastclick/fastclick.js"></script>
<!-- ICHECK -->
<link rel="stylesheet" href="./plugins/iCheck/all.css">
<script type="text/javascript" src="./plugins/iCheck/icheck.min.js"></script>
    <?php
    } elseif ($pageSel === 'profile') {
        ?>
<!-- PLUPLOAD -->
<script type="text/javascript" src="includes/libraries/Plupload/plupload.full.min.js"></script>
<!-- ICHECK -->
<link rel="stylesheet" href="./plugins/iCheck/all.css">
<script type="text/javascript" src="./plugins/iCheck/icheck.min.js"></script>
        <?php
    }
?>
<!-- functions -->
<script type="text/javascript" src="includes/js/functions.js"></script>

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
    include_once $SETTINGS['cpassman_dir'].'/includes/core/load.js.php';

    if ($menuAdmin === true) {
        include_once $SETTINGS['cpassman_dir'].'/pages/admin.js.php';
        if ($pageSel === '2fa') {
            include_once $SETTINGS['cpassman_dir'].'/pages/2fa.js.php';
        } elseif ($pageSel === 'api') {
            include_once $SETTINGS['cpassman_dir'].'/pages/api.js.php';
        } elseif ($pageSel === 'backups') {
            include_once $SETTINGS['cpassman_dir'].'/pages/backups.js.php';
        } elseif ($pageSel === 'emails') {
            include_once $SETTINGS['cpassman_dir'].'/pages/emails.js.php';
        } elseif ($pageSel === 'ldap') {
            include_once $SETTINGS['cpassman_dir'].'/pages/ldap.js.php';
        } elseif ($pageSel === 'uploads') {
            include_once $SETTINGS['cpassman_dir'].'/pages/uploads.js.php';
        } elseif ($pageSel === 'actions') {
            include_once $SETTINGS['cpassman_dir'].'/pages/actions.js.php';
        } elseif ($pageSel === 'fields') {
            include_once $SETTINGS['cpassman_dir'].'/pages/fields.js.php';
        }
    } elseif ($pageSel === 'items') {
        include_once $SETTINGS['cpassman_dir'].'/pages/items.js.php';
    } elseif ($pageSel === 'import') {
        include_once $SETTINGS['cpassman_dir'].'/pages/import.js.php';
    } elseif ($pageSel === 'export') {
        include_once $SETTINGS['cpassman_dir'].'/pages/export.js.php';
    } elseif ($pageSel === 'offline') {
        include_once $SETTINGS['cpassman_dir'].'/pages/offline.js.php';
    } elseif ($pageSel === 'search') {
        include_once $SETTINGS['cpassman_dir'].'/pages/search.js.php';
    } elseif ($pageSel === 'profile') {
        include_once $SETTINGS['cpassman_dir'].'/pages/profile.js.php';
    } elseif ($pageSel === 'favourites') {
        include_once $SETTINGS['cpassman_dir'].'/pages/favorites.js.php';
    } elseif ($pageSel === 'folders') {
        include_once $SETTINGS['cpassman_dir'].'/pages/folders.js.php';
    } elseif ($pageSel === 'users') {
        include_once $SETTINGS['cpassman_dir'].'/pages/users.js.php';
    } elseif ($pageSel === 'roles') {
        include_once $SETTINGS['cpassman_dir'].'/pages/roles.js.php';
    } elseif ($pageSel === 'utilities.deletion') {
        include_once $SETTINGS['cpassman_dir'].'/pages/utilities.deletion.js.php';
    } elseif ($pageSel === 'utilities.logs') {
        include_once $SETTINGS['cpassman_dir'].'/pages/utilities.logs.js.php';
    } elseif ($pageSel === 'utilities.database') {
        include_once $SETTINGS['cpassman_dir'].'/pages/utilities.database.js.php';
    } else {
        include_once $SETTINGS['cpassman_dir'].'/includes/core/login.js.php';
    }
}

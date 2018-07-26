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
 * @copyright 2009-2018 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
 */
if (isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id']) === true
    || isset($_SESSION['key']) === false || empty($_SESSION['key']) === true) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php') === true) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php') === true) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'profile', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

// Load template
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

// prepare avatar
if (isset($_SESSION['user_avatar']) === true) {
    if (file_exists('includes/avatars/'.$_SESSION['user_avatar'])) {
        $avatar = $SETTINGS['cpassman_url'].'/includes/avatars/'.$_SESSION['user_avatar'];
    } else {
        $avatar = $SETTINGS['cpassman_url'].'/includes/images/photo.jpg';
    }
} else {
    $avatar = $SETTINGS['cpassman_url'].'/includes/images/photo.jpg';
}

// user type
if ($_SESSION['user_admin'] === '1') {
    $_SESSION['user_privilege'] = langHdl('god');
} elseif ($_SESSION['user_manager'] === '1') {
    $_SESSION['user_privilege'] = langHdl('gestionnaire');
} elseif ($_SESSION['user_read_only'] === '1') {
    $_SESSION['user_privilege'] = langHdl('read_only_account');
} elseif ($_SESSION['user_can_manage_all_users'] === '1') {
    $_SESSION['user_privilege'] = langHdl('human_resources');
} else {
    $_SESSION['user_privilege'] = langHdl('user');
}

// prepare list of timezones
foreach (timezone_identifiers_list() as $zone) {
    $arrayTimezones[$zone] = $zone;
}

// prepare lsit of flags
$rows = DB::query('SELECT label FROM '.prefixTable('languages').' ORDER BY label ASC');
foreach ($rows as $record) {
    $arraFlags[$record['label']] = $record['label'];
}

// Do some stats
DB::query('SELECT id_item FROM '.prefixTable('log_items').' WHERE action = "at_creation" AND  id_user = "'.$_SESSION['user_id'].'"');
$userItemsNumber = DB::count();

DB::query('SELECT id_item FROM '.prefixTable('log_items').' WHERE action = "at_modification" AND  id_user = "'.$_SESSION['user_id'].'"');
$userModificationNumber = DB::count();

DB::query('SELECT id_item FROM '.prefixTable('log_items').' WHERE action = "at_shown" AND  id_user = "'.$_SESSION['user_id'].'"');
$userSeenItemsNumber = DB::count();

DB::query('SELECT id_item FROM '.prefixTable('log_items').' WHERE action = "at_password_shown" AND  id_user = "'.$_SESSION['user_id'].'"');
$userSeenPasswordsNumber = DB::count();

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <?php echo langHdl('profile'); ?>
                </h1>
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</div>
<!-- /.content-header -->


<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <div class="row">
        
            <div class="col-md-3">

                <!-- Profile  -->
                <div class="card card-primary card-outline">
                    <div class="card-body box-profile">
                        <div class="text-center">
                            <img class="profile-user-img img-fluid img-circle" src="<?php echo $avatar; ?>" alt="User profile picture">
                        </div>

                        <h3 class="profile-username text-center">
                            <?php
                            if (isset($_SESSION['name']) === true && empty($_SESSION['name']) === false) {
                                echo $_SESSION['name'].' '.$_SESSION['lastname'];
                            } else {
                                echo $_SESSION['login'];
                            }
                            ?>
                        </h3>

                        <p class="text-muted text-center"><?php echo $_SESSION['user_privilege']; ?></p>

                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item">
                                <b><?php echo langHdl('created_items'); ?></b>
                                <a class="float-right"><?php echo $userItemsNumber; ?></a>
                            </li>
                            <li class="list-group-item">
                                <b><?php echo langHdl('modification_performed'); ?></b>
                                <a class="float-right"><?php echo $userModificationNumber; ?></a>
                            </li>
                            <li class="list-group-item">
                                <b><?php echo langHdl('items_opened'); ?></b>
                                <a class="float-right"><?php echo $userSeenItemsNumber; ?></a>
                            </li>
                            <li class="list-group-item">
                                <b><?php echo langHdl('passwords_seen'); ?></b>
                                <a class="float-right"><?php echo $userSeenPasswordsNumber; ?></a>
                            </li>
                        </ul>
                    </div>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->

            <div class="col-md-9">
                <div class="card">
                    <div class="card-header p-2">
                        <ul class="nav nav-pills">
                        <li class="nav-item"><a class="nav-link active" href="#tab_information" data-toggle="tab"><?php echo langHdl('information'); ?></a></li>
                        <li class="nav-item"><a class="nav-link" href="#timeline" data-toggle="tab">Timeline</a></li>
                        <li class="nav-item"><a class="nav-link" href="#tab_settings" data-toggle="tab"><?php echo langHdl('settings'); ?></a></li>
                        </ul>
                    </div><!-- /.card-header -->
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- INFO -->
                            <div class="active tab-pane" id="tab_information">
                                <ul class="list-group list-group-unbordered mb-3">
                                    <li class="list-group-item">
                                        <b><i class="fa fa-child fa-fw fa-lg mr-2"></i><?php echo langHdl('index_last_seen'); ?></b>
                                        <a class="float-right">
                                        <?php
                                        if (isset($SETTINGS['date_format']) === true) {
                                            echo date($SETTINGS['date_format'], $_SESSION['derniere_connexion']);
                                        } else {
                                            echo date('d/m/Y', $_SESSION['derniere_connexion']);
                                        }
                                        echo ' '.langHdl('at').' ';
                                        if (isset($SETTINGS['time_format']) === true) {
                                            echo date($SETTINGS['time_format'], $_SESSION['derniere_connexion']);
                                        } else {
                                            echo date('H:i:s', $_SESSION['derniere_connexion']);
                                        }
                                        ?>
                                        </a>
                                    </li>
                                    <?php
                                    if (isset($_SESSION['last_pw_change']) && !empty($_SESSION['last_pw_change'])) {
                                        // Handle last password change string
                                        if (isset($_SESSION['last_pw_change']) === true) {
                                            if (isset($SETTINGS['date_format']) === true) {
                                                $last_pw_change = date($SETTINGS['date_format'], $_SESSION['last_pw_change']);
                                            } else {
                                                $last_pw_change = date('d/m/Y', $_SESSION['last_pw_change']);
                                            }
                                        } else {
                                            $last_pw_change = '-';
                                        }

                                        // Handle expiration for pw
                                        if (isset($_SESSION['numDaysBeforePwExpiration']) === false ||
                                            $_SESSION['numDaysBeforePwExpiration'] === '' ||
                                            $_SESSION['numDaysBeforePwExpiration'] === 'infinite'
                                        ) {
                                            $numDaysBeforePwExpiration = '';
                                        } else {
                                            $numDaysBeforePwExpiration = $LANG['index_pw_expiration'].' '.$_SESSION['numDaysBeforePwExpiration'].' '.$LANG['days'].'.';
                                        }
                                        echo '
                                    <li class="list-group-item">
                                        <b><i class="fa fa-calendar fa-fw fa-lg mr-2"></i>'.langHdl('index_last_pw_change').'</b>
                                        <a class="float-right">'.$last_pw_change.' '.$numDaysBeforePwExpiration.'</a>
                                    </li>';
                                    }
                                    ?>
                                    <li class="list-group-item">
                                        <b><i class="fa fa-cloud-upload fa-fw fa-lg mr-2"></i><?php echo langHdl('index_last_pw_change'); ?></b>
                                        <a class="float-right">
                                            <span id="plupload_runtime2" class="text-danger"><?php echo langHdl('error_upload_runtime_not_found'); ?></span>
                                            <input type="hidden" id="upload_enabled2" value="" />
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <!-- INFO -->
                            <div class="tab-pane" id="timeline">
                                <?php
                                if (isset($_SESSION['unsuccessfull_login_attempts']) === true
                                    && $_SESSION['unsuccessfull_login_attempts']['nb'] !== 0
                                ) {
                                    echo '
                                    <div style="margin-bottom:6px;" class="',
                                        $_SESSION['unsuccessfull_login_attempts']['shown'] === false ?
                                        'ui-widget-content ui-state-error ui-corner-all'
                                        :
                                        ''
                                        ,'">
                                        <i class="fa fa-history fa-fw fa-lg"></i>&nbsp;
                                        '.$LANG['login_attempts'].':
                                        <div style="margin:1px 0 0 36px;">';
                                    foreach ($_SESSION['unsuccessfull_login_attempts']['attempts'] as $entry) {
                                        echo '<span class="fa fa-caret-right"></span>&nbsp;'.$entry.'<br/>';
                                    }
                                    echo '
                                        </div>
                                    </div>';
                                    $_SESSION['unsuccessfull_login_attempts']['shown'] = true;
                                }
                                ?>
                            </div>

                            <!-- SETTINGS -->
                            <div class="tab-pane" id="tab_settings">
                                <form class="needs-validation" novalidate onsubmit="return false;">
                                    <div class="form-group">
                                        <label for="profile-email" class="col-sm-2 control-label"><?php echo langHdl('email'); ?></label>
                                        <div class="col-sm-10">
                                            <input type="email" class="form-control" id="profile-email" placeholder="name@domain.com" value="<?php echo $_SESSION['user_email']; ?>">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <div class="col-sm-offset-2 col-sm-10">
                                            <button type="submit" class="btn btn-danger">Submit</button>
                                        </div>
                                    </div>
                                </form>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</div>
<!-- /.content -->
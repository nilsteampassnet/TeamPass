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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], curPage($SETTINGS), $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

// Load template
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

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
$zoneToPreSelect = $SETTINGS['timezone'];
foreach (timezone_identifiers_list() as $zone) {
    $arrayTimezones[$zone] = $zone;
    if ($_SESSION['user_settings']['usertimezone'] === $zone) {
        $zoneToPreSelect = $_SESSION['user_settings']['usertimezone'];
    }
}

// prepare lsit of flags
$languageToPreSelect = $SETTINGS['default_language'];
$rows = DB::query('SELECT label FROM '.prefixTable('languages').' ORDER BY label ASC');
foreach ($rows as $record) {
    $arrayFlags[$record['label']] = $record['label'];
    if ($_SESSION['user_settings']['user_language'] === $record['label']) {
        $zoneToPreSelect = $_SESSION['user_language']['usertimezone'];
    }
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

$userInfo = DB::queryFirstRow(
    'SELECT avatar 
    FROM '.prefixTable('users').' 
    WHERE id = "'.$_SESSION['user_id'].'"'
);
if (empty($userInfo['avatar']) === true) {
    $avatar = $SETTINGS['cpassman_url'].'/includes/images/photo.jpg';
} else {
    $avatar = $SETTINGS['cpassman_url'].'/includes/avatars/'.$userInfo['avatar'];
}

// Get Groups name
$userParOfGroups = array();
foreach($_SESSION['user_roles'] as $role) {
    $tmp = DB::queryFirstRow(
        'SELECT title 
        FROM '.prefixTable('roles_title').' 
        WHERE id = "'.$role.'"'
    );
    array_push($userParOfGroups, $tmp['title']);
}



?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-user-circle mr-2"></i><?php echo langHdl('profile'); ?>
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
                            <img class="profile-user-img img-fluid img-circle" src="<?php echo $avatar; ?>" alt="User profile picture" id="profile-user-avatar">
                        </div>

                        <h3 id="profile-username" class="text-center">
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
                        <ul class="nav nav-pills" id="profile-tabs">
                            <li class="nav-item">
                                <a class="nav-link<?php echo isset($_GET['tab']) === false ? ' active' : ''; ?>" href="#tab_information" data-toggle="tab"><?php echo langHdl('information'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link<?php echo isset($_GET['tab']) === true && $_GET['tab'] === 'timeline' ? ' active' : ''; ?>" href="#timeline" data-toggle="tab">Timeline</a>
                            </li>
                            <li class="nav-item"><a class="nav-link" href="#tab_settings" data-toggle="tab"><?php echo langHdl('settings'); ?></a></li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#">
                                <?php echo langHdl('actions'); ?> <span class="caret"></span>
                                </a>
                                <div class="dropdown-menu">
                                    <?php
                                    if (isset($SETTINGS['duo']) === false || (int) $SETTINGS['duo'] === 0) {
                                        echo '
                                    <a class="dropdown-item" tabindex="-1" href="#tab_change_pw" data-toggle="tab">'.langHdl('index_change_pw').'</a>';
                                    }
                                    ?>
                                    <a class="dropdown-item" tabindex="-1" href="#tab_change_psk" data-toggle="tab"><?php echo langHdl('menu_title_new_personal_saltkey'); ?></a>
                                    <a class="dropdown-item" tabindex="-1" href="#tab_reset_psk" data-toggle="tab"><?php echo langHdl('personal_saltkey_lost'); ?></a>
                                </div>
                            </li>
                        </ul>
                    </div><!-- /.card-header -->
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- INFO -->
                            <div class="<?php echo isset($_GET['tab']) === false ? 'active ' : ''; ?> tab-pane" id="tab_information">
                                <ul class="list-group list-group-unbordered mb-3">
                                    <li class="list-group-item">
                                        <b><i class="fas fa-users fa-fw fa-lg mr-2"></i><?php echo langHdl('part_of_groups'); ?></b>
                                        <a class="float-right">
                                            <span id="profile-groups" class=""><?php echo implode(', ', $userParOfGroups); ?></span>
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <b><i class="fas fa-child fa-fw fa-lg mr-2"></i><?php echo langHdl('index_last_seen'); ?></b>
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
                                        if (isset($_SESSION['numDaysBeforePwExpiration']) === false
                                            || $_SESSION['numDaysBeforePwExpiration'] === ''
                                            || $_SESSION['numDaysBeforePwExpiration'] === 'infinite'
                                        ) {
                                            $numDaysBeforePwExpiration = '';
                                        } else {
                                            $numDaysBeforePwExpiration = $LANG['index_pw_expiration'].' '.$_SESSION['numDaysBeforePwExpiration'].' '.$LANG['days'].'.';
                                        }
                                        echo '
                                    <li class="list-group-item">
                                        <b><i class="fas fa-calendar-alt fa-fw fa-lg mr-2"></i>'.langHdl('index_last_pw_change').'</b>
                                        <a class="float-right">'.$last_pw_change.' '.$numDaysBeforePwExpiration.'</a>
                                    </li>';
                                    }
                                    ?>
                                    <li class="list-group-item">
                                        <b><i class="fas fa-cloud-upload-alt fa-fw fa-lg mr-2"></i><?php echo langHdl('upload_feature'); ?></b>
                                        <a class="float-right">
                                            <span id="profile-plupload-runtime" class="text-danger" data-enabled="0"><?php echo langHdl('error_upload_runtime_not_found'); ?></span>
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <b><i class="fas fa-stream fa-fw fa-lg mr-2"></i><?php echo langHdl('tree_load_strategy'); ?></b>
                                        <a class="float-right">
                                            <span id="profile-plupload-runtime"><?php echo $_SESSION['user_settings']['treeloadstrategy']; ?></span>
                                        </a>
                                    </li>
                                    <?php
                                    if (isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1) {
                                        echo '
                                    <li class="list-group-item">
                                        <b><i class="fas fa-paper-plane fa-fw fa-lg mr-2"></i>'.langHdl('user_profile_api_key').'</b>
                                        <a class="float-right" id="profile-user-api-token">',
                                        isset($_SESSION['user_settings']['api-key']) === true ? $_SESSION['user_settings']['api-key'] : '', '</a>
                                    </li>';
                                    }
                                    if (isset($SETTINGS['agses_authentication_enabled']) === true
                                        && (int) $SETTINGS['agses_authentication_enabled'] === 1
                                    ) {
                                        echo '
                                    <li class="list-group-item">
                                        <b><i class="fas fa-id-card-o fa-fw fa-lg mr-2"></i>'.langHdl('user_profile_agses_card_id').'</b>
                                        <a class="float-right">',
                                        isset($_SESSION['user_settings']['agses-usercardid']) ? $_SESSION['user_settings']['agses-usercardid'] : '', '</a>
                                    </li>';
                                    }
                                    ?>
                                </ul>
                            </div>

                            <!-- TIMELINE -->
                            <div class="tab-pane<?php echo isset($_GET['tab']) === true && $_GET['tab'] === 'timeline' ? ' active' : ''; ?>" id="timeline">
                                <?php
                                if (isset($_SESSION['unsuccessfull_login_attempts']) === true
                                    && $_SESSION['unsuccessfull_login_attempts']['nb'] !== 0
                                    && $_SESSION['unsuccessfull_login_attempts']['shown'] === false
                                ) {
                                    ?>
                                    <div class="alert alert-warning mt-4">
                                        <span class="text-bold"><?php echo langHdl('last_login_attempts'); ?></span>
                                        <ul class="">
                                        <?php
                                        foreach ($_SESSION['unsuccessfull_login_attempts']['attempts'] as $entry) {
                                            echo '<li class="">'.$entry.'</li>';
                                        } ?>
                                        </ul>
                                    </div>
                                    <?php
                                    $_SESSION['unsuccessfull_login_attempts']['shown'] = true;
                                }
                                ?>
                                <div class="mt-4">
                                    <ul class="list-group list-group-flush">
                                        <?php
                                        $rows = DB::query(
                                            'SELECT label AS labelAction, date, null
                                            FROM '.prefixTable('log_system').'
                                            WHERE qui = %i
                                            UNION
                                            SELECT l.action, l.date, i.label AS itemLabel
                                            FROM '.prefixTable('log_items').' AS l
                                            INNER JOIN '.prefixTable('items').' AS i ON (l.id_item = i.id)
                                            WHERE l.id_user = %i AND l.action IN ("at_access")
                                            ORDER BY date DESC
                                            LIMIT 0, 40',
                                            $_SESSION['user_id'],
                                            $_SESSION['user_id']
                                        );
                                        foreach ($rows as $record) {
                                            if (substr($record['labelAction'], 0, 3) === 'at_') {
                                                $text = langHdl(substr($record['labelAction'], 3));
                                            } else {
                                                $text = langHdl($record['labelAction']);
                                            }
                                            if (empty($record['NULL']) === false) {
                                                $text .= ' '.langHdl('for').' <span class="font-weight-light">'.addslashes($record['NULL']).'</span>';
                                            }
                                            echo '<li class="list-group-item">'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], $record['date']).' - '.$text.'</li>';
                                        }
                                        ?>
                                    </ul>
                                </div>
                                <?php

                                ?>
                            </div>

                            <!-- SETTINGS -->
                            <div class="tab-pane" id="tab_settings">
                                <form class="needs-validation" novalidate onsubmit="return false;">
                                    <div class="form-group">
                                        <label for="profile-name" class="col-sm-2 control-label"><?php echo langHdl('name'); ?></label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" id="profile-user-name" placeholder="" value="<?php echo $_SESSION['name']; ?>">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="profile-lastname" class="col-sm-2 control-label"><?php echo langHdl('lastname'); ?></label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" id="profile-user-lastname" placeholder="" value="<?php echo $_SESSION['lastname']; ?>">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="profile-email" class="col-sm-2 control-label"><?php echo langHdl('email'); ?></label>
                                        <div class="col-sm-10">
                                            <input type="email" class="form-control" id="profile-user-email" placeholder="name@domain.com" value="<?php echo $_SESSION['user_email']; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="col-sm-2 control-label"><?php echo langHdl('timezone_selection'); ?></label>
                                        <div class="col-sm-10">
                                            <select class="form-control" id="profile-user-timezone">
                                                <?php
                                                foreach ($arrayTimezones as $zone) {
                                                    echo '<option value="'.$zone.'"',
                                                    strtolower($zoneToPreSelect) === strtolower($zone) ? ' selected="selected"' : '',
                                                    '>'.$zone.'</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="col-sm-2 control-label"><?php echo langHdl('language'); ?></label>
                                        <div class="col-sm-10">
                                            <select class="form-control" id="profile-user-language">
                                                <?php
                                                foreach ($arrayFlags as $flag) {
                                                    echo '<option value="'.$flag.'"',
                                                    strtolower($flag) === strtolower($languageToPreSelect) ? ' selected="selected"' : '',
                                                    '>'.$flag.'</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label"><?php echo langHdl('tree_load_strategy'); ?></label>
                                        <div class="col-sm-10">
                                            <select class="form-control" id="profile-user-treeloadstrategy">
                                                <option value="<?php echo langHdl('sequential'); ?>"<?php
                                                if ($_SESSION['user_settings']['treeloadstrategy'] === 'sequential') {
                                                    echo ' selected';
                                                }?>><?php echo langHdl('sequential'); ?></option>
                                                <option value="<?php echo langHdl('full'); ?>"<?php
                                                if ($_SESSION['user_settings']['treeloadstrategy'] === 'full') {
                                                    echo ' selected';
                                                }?>><?php echo langHdl('full'); ?></option>
                                            </select>
                                        </div>
                                    </div>

                                    <?php
                                    if (isset($SETTINGS['agses_authentication_enabled']) === true
                                        && $SETTINGS['agses_authentication_enabled'] === '1'
                                    ) {
                                        ?>
                                        <div class="form-group">
                                            <label class="col-sm-2 control-label"><?php echo langHdl('user_profile_agses_card_id'); ?></label>
                                            <div class="col-sm-10">
                                                <input type="numeric" class="form-control" id="profile-user-agsescardid" placeholder="name@domain.com" value="<?php
                                                if (isset($_SESSION['user_settings']['agses-usercardid']) === true) {
                                                    echo $_SESSION['user_settings']['agses-usercardid'];
                                                } ?>">
                                            </div>
                                        </div>
                                        <?php
                                    }
                                    ?>                                    

                                    <div class="form-group">
                                        <div class="row">
                                            <div class="col-sm-offset-2 col-sm-2">
                                                <button type="button" class="btn btn-info" id="profile-user-save-settings"><?php echo langHdl('save'); ?></button>
                                            </div>
                                            <div class="col-sm-8">
                                                <button type="button" class="btn btn-warning float-right ml-2" id="profile-avatar-file"><?php echo langHdl('upload_new_avatar'); ?></button>
                                                <button type="button" class="btn btn-warning float-right" id="profile-button-api_token"><?php echo langHdl('generate_api_token'); ?></button>
                                                <div id="profile-avatar-file-container" class="hidden"></div>
                                                <div id="profile-avatar-file-list" class="hidden"></div>
                                                <input type="hidden" id="profile-user-token">
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                
                            </div>

                            
                            <!-- CHANGE PW -->
                            <div class="tab-pane" id="tab_change_pw">
                                <h3 class="card-title mb-3"><?php echo langHdl('index_change_pw'); ?></h3>
                                <div class="card card-info card-outline">
                                    <div class="card-body">
                                        <?php echo langHdl('changing_pw_purpose'); ?>
                                    </div>
                                </div>
                                <form class="needs-validation" novalidate onsubmit="return false;">
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><?php echo langHdl('index_new_pw'); ?></span>
                                        </div>
                                        <input type="password" class="form-control" id="profile-password">
                                        <div class="input-group-append">
                                            <span class="input-group-text" id="profile-password-strength"></span>
                                            <input type="hidden" id="profile-password-complex" />
                                        </div>
                                    </div>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><?php echo langHdl('index_change_pw_confirmation'); ?></span>
                                        </div>
                                        <input type="password" class="form-control"  id="profile-password-confirm">
                                    </div>
                                    <div class="form-group">
                                        <button type="button" class="btn btn-info" id="profile-save-password-change"><?php echo langHdl('perform'); ?></button>
                                    </div>
                                </form>
                            </div>

                            
                            <!-- CHANGE PSK -->
                            <div class="tab-pane" id="tab_change_psk">
                                <h3 class="card-title mb-3">
                                    <?php echo langHdl('menu_title_new_personal_saltkey'); ?>
                                </h3>
                                <div class="card card-info card-outline">
                                    <div class="card-body">
                                        <?php echo langHdl('changing_psk_purpose'); ?>
                                    </div>
                                </div>
                                <div class="callout callout-info">
                                    <h6>
                                    <i class="fas fa-info mr-2"></i>
                                    <?php echo langHdl('complex_asked').' : <b>'.TP_PW_COMPLEXITY[$SETTINGS['personal_saltkey_security_level']][1].'</b>'; ?>
                                    </h6>
                                </div>
                                <form class="needs-validation" novalidate onsubmit="return false;">
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><?php echo langHdl('current_saltkey'); ?></span>
                                        </div>
                                        <input type="password" class="form-control"  id="profile-current-saltkey">
                                    </div>
                                    <!--<div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><?php echo langHdl('current_saltkey_confirm'); ?></span>
                                        </div>
                                        <input type="password" class="form-control"  id="profile-current-saltkey-confirm">
                                    </div>-->
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><?php echo langHdl('new_saltkey'); ?></span>
                                        </div>
                                        <input type="password" class="form-control infotip" id="profile-saltkey" title="<?php echo langHdl('text_without_symbols'); ?>">
                                        <div class="input-group-append">
                                            <span class="input-group-text" id="profile-saltkey-strength"></span>
                                            <input type="hidden" id="profile-saltkey-complex" />
                                        </div>
                                    </div>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><?php echo langHdl('new_saltkey_confirm'); ?></span>
                                        </div>
                                        <input type="password" class="form-control"  id="profile-saltkey-confirm">
                                    </div>
                                    <div class="input-group mb-3" style="min-height:50px;">
                                        <div class="alert alert-warning hidden" id="profile-save-saltkey-alert">
                                            <h5>
                                                <i class="icon fa fa-cog fa-spin mr-2"></i>
                                                <?php echo langHdl('please_wait_now_converting_passwords'); ?> ... <span id="profile-save-saltkey-progress">0%</span>
                                            </h5>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <button type="button" class="btn btn-info" id="profile-save-saltkey-change"><?php echo langHdl('perform'); ?></button>
                                    </div>
                                </form>
                            </div>

                                                        
                            <!-- RESET PSK -->
                            <div class="tab-pane" id="tab_reset_psk">
                                <h3 class="card-title mb-3"><?php echo langHdl('personal_saltkey_lost'); ?></h3>
                                <div class="card card-warning card-outline">
                                    <div class="card-body">
                                        <?php echo langHdl('reseting_personal_saltkey_purpose'); ?>
                                    </div>
                                </div>
                                <form class="needs-validation" novalidate onsubmit="return false;">
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><?php echo langHdl('new_saltkey'); ?></span>
                                        </div>
                                        <input type="password" class="form-control" id="profile-reset-psk">
                                        <div class="input-group-append">
                                            <span class="input-group-text" id="profile-reset-psk-strength"></span>
                                            <input type="hidden" id="profile-reset-psk-complex" />
                                        </div>
                                    </div>
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><?php echo langHdl('new_saltkey_confirm'); ?></span>
                                        </div>
                                        <input type="password" class="form-control"  id="profile-reset-psk-confirm">
                                    </div>
                                    <div class="input-group mb-3">
                                        <input type="checkbox" class="form-check-input form-item-control" id="profile-reset-psk-delete-items">
                                        <label class="form-check-label ml-2" for="profile-reset-psk-delete-items">
                                            <?php echo langHdl('confirm_delete_all_personal_items'); ?>
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <button type="button" class="btn btn-warning" id="button-profile-reset-psk"><?php echo langHdl('perform'); ?></button>
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
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
 * @file      profile.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language(); 

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => returnIfSet($superGlobal->get('type', 'POST')),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($superGlobal->get('user_id', 'SESSION'), null),
        'user_key' => returnIfSet($superGlobal->get('key', 'SESSION'), null),
        'CPM' => returnIfSet($superGlobal->get('CPM', 'SESSION'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('profile') === false) {
    // Not allowed page
    $superGlobal->put('code', ERR_NOT_ALLOWED, 'SESSION', 'error');
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

// Prepare GET variables
$get = [];
$get['tab'] = $superGlobal->get('tab', 'GET') === null ? '' : $superGlobal->get('tab', 'GET');
// user type
if ($_SESSION['user_admin'] === '1') {
    $_SESSION['user_privilege'] = $lang->get('god');
} elseif ($_SESSION['user_manager'] === '1') {
    $_SESSION['user_privilege'] = $lang->get('gestionnaire');
} elseif ($_SESSION['user_read_only'] === '1') {
    $_SESSION['user_privilege'] = $lang->get('read_only_account');
} elseif ($_SESSION['user_can_manage_all_users'] === '1') {
    $_SESSION['user_privilege'] = $lang->get('human_resources');
} else {
    $_SESSION['user_privilege'] = $lang->get('user');
}

// prepare list of timezones
$zones = timezone_list();
// prepare list of languages
$languages = DB::query('SELECT label, name FROM ' . prefixTable('languages') . ' ORDER BY label ASC');
// Do some stats
DB::query('SELECT id_item FROM ' . prefixTable('log_items') . ' WHERE action = "at_creation" AND  id_user = "' . $_SESSION['user_id'] . '"');
$userItemsNumber = DB::count();
DB::query('SELECT id_item FROM ' . prefixTable('log_items') . ' WHERE action = "at_modification" AND  id_user = "' . $_SESSION['user_id'] . '"');
$userModificationNumber = DB::count();
DB::query('SELECT id_item FROM ' . prefixTable('log_items') . ' WHERE action = "at_shown" AND  id_user = "' . $_SESSION['user_id'] . '"');
$userSeenItemsNumber = DB::count();
DB::query('SELECT id_item FROM ' . prefixTable('log_items') . ' WHERE action = "at_password_shown" AND  id_user = "' . $_SESSION['user_id'] . '"');
$userSeenPasswordsNumber = DB::count();
$userInfo = DB::queryFirstRow(
    'SELECT avatar, last_pw_change
    FROM ' . prefixTable('users') . ' 
    WHERE id = "' . $_SESSION['user_id'] . '"'
);
if (empty($userInfo['avatar']) === true) {
    $avatar = $SETTINGS['cpassman_url'] . '/includes/images/photo.jpg';
} else {
    $avatar = $SETTINGS['cpassman_url'] . '/includes/avatars/' . $userInfo['avatar'];
}

// Get Groups name
$userParOfGroups = [];
foreach ($_SESSION['user_roles'] as $role) {
    $tmp = DB::queryFirstRow(
        'SELECT title 
        FROM ' . prefixTable('roles_title') . ' 
        WHERE id = "' . $role . '"'
    );
    if ($tmp !== null) {
        array_push($userParOfGroups, $tmp['title']);
    }
}

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-user-circle mr-2"></i><?php echo $lang->get('profile'); ?>
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
                                echo $_SESSION['name'] . ' ' . $_SESSION['lastname'];
                            } else {
                                echo $_SESSION['login'];
                            }
                            ?>
                        </h3>

                        <p class="text-info text-center"><?php echo $_SESSION['user_email']; ?></p>
                        <p class="text-muted text-center"><?php echo $_SESSION['user_privilege']; ?></p>

                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item">
                                <b><?php echo $lang->get('created_items'); ?></b>
                                <a class="float-right"><?php echo $userItemsNumber; ?></a>
                            </li>
                            <li class="list-group-item">
                                <b><?php echo $lang->get('modification_performed'); ?></b>
                                <a class="float-right"><?php echo $userModificationNumber; ?></a>
                            </li>
                            <li class="list-group-item">
                                <b><?php echo $lang->get('items_opened'); ?></b>
                                <a class="float-right"><?php echo $userSeenItemsNumber; ?></a>
                            </li>
                            <li class="list-group-item">
                                <b><?php echo $lang->get('passwords_seen'); ?></b>
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
                                <a class="nav-link<?php echo empty($get['tab']) === true ? ' active' : ''; ?>" href="#tab_information" data-toggle="tab"><?php echo $lang->get('information'); ?></a>
                            </li>
                            <li class="nav-item"><a class="nav-link<?php echo $get['tab'] === 'settings' ? ' active' : ''; ?>" href="#tab_settings" data-toggle="tab"><?php echo $lang->get('settings'); ?></a></li>
                            <li class="nav-item"><a class="nav-link<?php echo $get['tab'] === 'keys' ? ' active' : ''; ?>" href="#tab_keys" data-toggle="tab"><?php echo $lang->get('keys_management'); ?></a></li>
                            <li class="nav-item">
                                <a class="nav-link<?php echo $get['tab'] === 'timeline' ? ' active' : ''; ?>" href="#tab_timeline" data-toggle="tab">Timeline</a>
                            </li>
                        </ul>
                    </div><!-- /.card-header -->
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- INFO -->
                            <div class="<?php echo empty($get['tab']) === true ? 'active ' : ''; ?> tab-pane" id="tab_information">
                                <ul class="list-group list-group-unbordered mb-3">
                                    <li class="list-group-item">
                                        <b><i class="fas fa-users fa-fw fa-lg mr-2"></i><?php echo $lang->get('part_of_groups'); ?></b>
                                        <a class="float-right">
                                            <span id="profile-groups" class=""><?php echo implode(', ', $userParOfGroups); ?></span>
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <b><i class="fas fa-child fa-fw fa-lg mr-2"></i><?php echo $lang->get('index_last_seen'); ?></b>
                                        <a class="float-right">
                                            <?php
                                            if (isset($SETTINGS['date_format']) === true) {
                                                echo date($SETTINGS['date_format'], (int) $_SESSION['last_connection']);
                                            } else {
                                                echo date('d/m/Y', (int) $_SESSION['last_connection']);
                                            }
                                            echo ' ' . $lang->get('at') . ' ';
                                            if (isset($SETTINGS['time_format']) === true) {
                                                echo date($SETTINGS['time_format'], (int) $_SESSION['last_connection']);
                                            } else {
                                                echo date('H:i:s', (int) $_SESSION['last_connection']);
                                            }
                                            ?>
                                        </a>
                                    </li>
                                    <?php
                                    if (isset($_SESSION['last_pw_change']) && ! empty($_SESSION['last_pw_change'])) {
                                        // Handle last password change string
                                        if (isset($_SESSION['last_pw_change']) === true) {
                                            if (isset($SETTINGS['date_format']) === true) {
                                                $last_pw_change = date($SETTINGS['date_format']." ".$SETTINGS['time_format'], (int) $userInfo['last_pw_change']);
                                            } else {
                                                $last_pw_change = date('d/m/Y', (int) $_SESSION['last_pw_change']);
                                            }
                                        } else {
                                            $last_pw_change = '-';
                                        }

                                        // Handle expiration for pw
                                        if (
                                            isset($_SESSION['numDaysBeforePwExpiration']) === false
                                            || $_SESSION['numDaysBeforePwExpiration'] === ''
                                            || $_SESSION['numDaysBeforePwExpiration'] === 'infinite'
                                        ) {
                                            $numDaysBeforePwExpiration = '';
                                        } else {
                                            $numDaysBeforePwExpiration = $LANG['index_pw_expiration'] . ' ' . $_SESSION['numDaysBeforePwExpiration'] . ' ' . $LANG['days'] . '.';
                                        }
                                        echo '
                                    <li class="list-group-item">
                                        <b><i class="fas fa-calendar-alt fa-fw fa-lg mr-2"></i>' . $lang->get('index_last_pw_change') . '</b>
                                        <a class="float-right">' . $last_pw_change . ' ' . $numDaysBeforePwExpiration . '</a>
                                    </li>';
                                    }
                                    ?>
                                    <li class="list-group-item">
                                        <b><i class="fas fa-cloud-upload-alt fa-fw fa-lg mr-2"></i><?php echo $lang->get('upload_feature'); ?></b>
                                        <a class="float-right">
                                            <span id="profile-plupload-runtime" class="text-danger" data-enabled="0"><?php echo $lang->get('error_upload_runtime_not_found'); ?></span>
                                        </a>
                                    </li>
                                    <li class="list-group-item">
                                        <b><i class="fas fa-stream fa-fw fa-lg mr-2"></i><?php echo $lang->get('tree_load_strategy'); ?></b>
                                        <a class="float-right">
                                            <span id="profile-plupload-runtime"><?php echo isset($_SESSION['user']['user_treeloadstrategy']) === true ? $_SESSION['user']['user_treeloadstrategy'] : ''; ?></span>
                                        </a>
                                    </li>
                                    <?php
                                    if (isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1) {
                                        echo '
                                    <li class="list-group-item">
                                        <b><i class="fas fa-paper-plane fa-fw fa-lg mr-2"></i>' . $lang->get('user_profile_api_key') . '</b>
                                        <a class="float-right" id="profile-user-api-token">',
                                            isset($_SESSION['user']['api-key']) === true ? $_SESSION['user']['api-key'] : '',
                                        '</a>
                                    </li>';
                                    }
                                    if (
                                        isset($SETTINGS['agses_authentication_enabled']) === true
                                        && (int) $SETTINGS['agses_authentication_enabled'] === 1
                                    ) {
                                        echo '
                                    <li class="list-group-item">
                                        <b><i class="fas fa-id-card-o fa-fw fa-lg mr-2"></i>' . $lang->get('user_profile_agses_card_id') . '</b>
                                        <a class="float-right">',
                                            $_SESSION['user_agsescardid'] ?? '',
                                            '</a>
                                    </li>';
                                    }
                                    ?>
                                </ul>
                            </div>

                            <!-- TIMELINE -->
                            <div class="tab-pane<?php echo $get['tab'] === 'timeline' ? ' active' : ''; ?>" id="tab_timeline">
                                <?php
                                if (
                                    isset($_SESSION['user']['unsuccessfull_login_attempts_list']) === true
                                    && $_SESSION['user']['unsuccessfull_login_attempts_nb'] !== 0
                                    && $_SESSION['user']['unsuccessfull_login_attempts_shown'] === false
                                ) {
                                    ?>
                                    <div class="alert alert-warning mt-4">
                                        <span class="text-bold"><?php echo $lang->get('last_login_attempts'); ?></span>
                                        <ul class="">
                                            <?php
                                                foreach ($_SESSION['user']['unsuccessfull_login_attempts_list'] as $entry) {
                                                    echo '<li class="">' . $entry . '</li>';
                                                } ?>
                                        </ul>
                                    </div>
                                <?php
                                    $_SESSION['user']['unsuccessfull_login_attempts_shown'] = true;
                                }
                                ?>
                                <div class="mt-4">
                                    <ul class="list-group list-group-flush">
                                        <?php
                                        $rows = DB::query(
                                            'SELECT label AS labelAction, date, null
                                                    FROM ' . prefixTable('log_system') . '
                                                    WHERE qui = %i
                                                    UNION
                                                    SELECT l.action, l.date, i.label AS itemLabel
                                                    FROM ' . prefixTable('log_items') . ' AS l
                                                    INNER JOIN ' . prefixTable('items') . ' AS i ON (l.id_item = i.id)
                                                    WHERE l.id_user = %i AND l.action IN ("at_access")
                                                    ORDER BY date DESC
                                                    LIMIT 0, 40',
                                            $_SESSION['user_id'],
                                            $_SESSION['user_id']
                                        );
                                        foreach ($rows as $record) {
                                            if (substr($record['labelAction'], 0, 3) === 'at_') {
                                                $text = $lang->get(substr($record['labelAction'], 3));
                                            } else {
                                                $text = $lang->get($record['labelAction']);
                                            }
                                            if (empty($record['NULL']) === false) {
                                                $text .= ' ' . $lang->get('for') . ' <span class="font-weight-light">' . addslashes($record['NULL']) . '</span>';
                                            }
                                            echo '<li class="list-group-item">' . date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['date']) . ' - ' . $text . '</li>';
                                        }
                                        ?>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- SETTINGS -->
                            <div class="tab-pane<?php echo $get['tab'] === 'settings' ? ' active' : ''; ?>" id="tab_settings">
                                <form class="needs-validation" novalidate onsubmit="return false;">
                                    <div class="form-group">
                                        <label for="profile-name" class="col-sm-2 control-label"><?php echo $lang->get('name'); ?></label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" id="profile-user-name" placeholder="" value="<?php echo $_SESSION['name']; ?>">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="profile-lastname" class="col-sm-2 control-label"><?php echo $lang->get('lastname'); ?></label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" id="profile-user-lastname" placeholder="" value="<?php echo $_SESSION['lastname']; ?>">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="profile-email" class="col-sm-2 control-label"><?php echo $lang->get('email'); ?></label>
                                        <div class="col-sm-10">
                                            <input type="email" class="form-control" id="profile-user-email" placeholder="name@domain.com" value="<?php echo $_SESSION['user_email']; ?>">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-10 control-label"><?php echo $lang->get('timezone_selection'); ?></label>
                                        <div class="col-sm-10">
                                            <select class="form-control" id="profile-user-timezone">
                                                <?php
                                                foreach ($zones as $key => $zone) {
                                                    echo '
                                                <option value="' . $key . '"',
                                                    isset($_SESSION['user_timezone']) === true && $_SESSION['user_timezone'] === $key ?
                                                    ' selected' :
                                                    (isset($SETTINGS['timezone']) === true && $SETTINGS['timezone'] === $key ? ' selected' : ''),
                                                '>' . $zone . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="col-sm-10 control-label"><?php echo $lang->get('language'); ?></label>
                                        <div class="col-sm-10">
                                            <select class="form-control" id="profile-user-language">
                                                <?php
                                                    foreach ($languages as $language) {
                                                        echo '<option value="' . $language['name'] . '"',
                                                        strtolower($_SESSION['user']['user_language']) === strtolower($language['name']) ?
                                                        ' selected="selected"' : '',
                                                    '>' . $language['label'] . '</option>';
                                                    }
                                                ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-10 control-label"><?php echo $lang->get('tree_load_strategy'); ?></label>
                                        <div class="col-sm-10">
                                            <select class="form-control" id="profile-user-treeloadstrategy">
                                                
                                                <option value="sequential" <?php echo isset($_SESSION['user']['user_treeloadstrategy']) === true && $_SESSION['user']['user_treeloadstrategy'] === 'sequential' ? ' selected' : '';?>>
                                                    <?php echo $lang->get('sequential'); ?>
                                                </option>
                                                
                                                <option value="full" <?php echo isset($_SESSION['user']['user_treeloadstrategy']) === true && $_SESSION['user']['user_treeloadstrategy'] === 'full' ? ' selected' : '';?>>
                                                    <?php echo $lang->get('full'); ?>
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <?php
                                    if (
                                        isset($SETTINGS['agses_authentication_enabled']) === true
                                        && (int) $SETTINGS['agses_authentication_enabled'] === 1
                                    ) {
                                        ?>
                                        <div class="form-group">
                                            <label class="col-sm-10 control-label"><?php echo $lang->get('user_profile_agses_card_id'); ?></label>
                                            <div class="col-sm-10">
                                                <input type="numeric" class="form-control" id="profile-user-agsescardid" placeholder="name@domain.com" value="<?php
                                                if (isset($_SESSION['user_agsescardid']) === true) {
                                                    echo $_SESSION['user_agsescardid'];
                                                } ?>">
                                            </div>
                                        </div>
                                    <?php
                                    }
                                    ?>

                                    <div class="form-group">
                                        <div class="row">
                                            <div class="col-sm-offset-2 col-sm-2">
                                                <button type="button" class="btn btn-info" id="profile-user-save-settings"><?php echo $lang->get('save'); ?></button>
                                            </div>
                                            <div class="col-sm-8">
                                                <button type="button" class="btn btn-warning float-right ml-2" id="profile-avatar-file"><?php echo $lang->get('upload_new_avatar'); ?></button>
                                                <?php
                                                if (isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1) {
                                                    echo '<button type="button" class="btn btn-warning float-right" id="profile-button-api_token">' . $lang->get('generate_api_token') . '</button>';
                                                }
                                                ?>
                                                <div id="profile-avatar-file-container" class="hidden"></div>
                                                <div id="profile-avatar-file-list" class="hidden"></div>
                                                <input type="hidden" id="profile-user-token">
                                            </div>
                                        </div>
                                    </div>
                                </form>

                            </div>

                            <!-- KEYS -->
                            <div class="tab-pane<?php echo $get['tab'] === 'keys' ? ' active' : ''; ?>" id="tab_keys">
                                <form class="needs-validation" novalidate onsubmit="return false;">
                                    
                                    <div class="alert alert-danger hidden" id="keys_not_recovered">
                                        <h5><i class="icon fa-solid fa-ban ml-2"></i><?php echo $lang->get('keys_not_recovered'); ?></h5>
                                        <?php echo $lang->get('keys_not_recovered_explanation'); ?>
                                    </div>

                                    <div class="row">
                                        <div class="card card-default col-sm-12">
                                            <div class="card-header">
                                                <h3 class="card-title">
                                                <i class="fa-solid fa-download mr-2"></i><?php echo $lang->get('download'); ?>
                                                </h3>
                                            </div>
                                            <!-- /.card-header -->
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <div class="row">
                                                        <div class="col-sm-offset-8 col-sm-8">
                                                            <i class="fa-solid fa-calendar-days mr-2"></i><?php echo $lang->get('recovery_keys_download_date'); ?>
                                                            <span class="badge badge-secondary ml-2" id="profile-keys_download-date"></span>
                                                        </div>
                                                        
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <div class="row">
                                                        <div class="col-sm-12">
                                                            <button type="button" class="btn btn-warning float-right ml-2" id="open-dialog-keys-download"><?php echo $lang->get('download_recovery_keys'); ?></button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
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


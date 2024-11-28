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
 * @file      profile.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');

$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('profile') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
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
$get['tab'] = $request->query->get('tab') === null ? '' : $request->query->get('tab');
// user type
if ($session->get('user-admin') === 1) {
    $session->set('user-privilege', $lang->get('god'));
} elseif ($session->get('user-manager') === 1) {
    $session->set('user-privilege', $lang->get('gestionnaire'));
} elseif ($session->get('user-read_only') === 1) {
    $session->set('user-privilege', $lang->get('read_only_account'));
} elseif ($session->get('user-can_manage_all_users') === 1) {
    $session->set('user-privilege', $lang->get('human_resources'));
} else {
    $session->set('user-privilege', $lang->get('user'));
}

// prepare list of timezones
$zones = timezone_list();
// prepare list of languages
$languages = DB::query(
    'SELECT label, name FROM ' . prefixTable('languages') . ' ORDER BY label ASC'
);

// Do some stats
$userItemsNumber = DB::queryFirstField(
    'SELECT COUNT(id_item) as count
    FROM ' . prefixTable('log_items') . '
    WHERE action = "at_creation" AND  id_user = %i',
    $session->get('user-id')
);

$userModificationNumber = DB::queryFirstField(
    'SELECT COUNT(id_item) as count
    FROM ' . prefixTable('log_items') . '
    WHERE action = "at_modification" AND  id_user = %i',
    $session->get('user-id')
);

$userSeenItemsNumber = DB::queryFirstField(
    'SELECT COUNT(id_item) as count
    FROM ' . prefixTable('log_items') . '
    WHERE action = "at_shown" AND  id_user = %i',
    $session->get('user-id')
);

$userSeenPasswordsNumber = DB::queryFirstField(
    'SELECT COUNT(id_item)
    FROM ' . prefixTable('log_items') . '
    WHERE action = "at_password_shown" AND  id_user = %i',
    $session->get('user-id')
);

$userInfo = DB::queryFirstRow(
    'SELECT avatar, last_pw_change
    FROM ' . prefixTable('users') . ' 
    WHERE id = %i',
    $session->get('user-id')
);

if (empty($userInfo['avatar']) === true) {
    $avatar = $SETTINGS['cpassman_url'] . '/includes/images/photo.jpg';
} else {
    $avatar = $SETTINGS['cpassman_url'] . '/includes/avatars/' . $userInfo['avatar'];
}

// Get Groups name
$userParOfGroups = [];
foreach ($session->get('user-roles_array') as $role) {
    $tmp = DB::queryFirstRow(
        'SELECT title 
        FROM ' . prefixTable('roles_title') . ' 
        WHERE id = %i',
        $role
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
                            if (null !== $session->get('user-name') && empty($session->get('user-name')) === false) {
                                echo $session->get('user-name') . ' ' . $session->get('user-lastname');
                            } else {
                                echo $session->get('user-login');
                            }
                            ?>
                        </h3>

                        <p class="text-info text-center"><?php echo $session->get('user-email'); ?></p>
                        <p class="text-muted text-center"><?php echo $session->get('user-privilege'); ?></p>

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
                                                echo date($SETTINGS['date_format'], (int) $session->get('user-last_connection'));
                                            } else {
                                                echo date('d/m/Y', (int) $session->get('user-last_connection'));
                                            }
                                            echo ' ' . $lang->get('at') . ' ';
                                            if (isset($SETTINGS['time_format']) === true) {
                                                echo date($SETTINGS['time_format'], (int) $session->get('user-last_connection'));
                                            } else {
                                                echo date('H:i:s', (int) $session->get('user-last_connection'));
                                            }
                                            ?>
                                        </a>
                                    </li>
                                    <?php
                                    if (null !== $session->get('user-last_pw_change') && ! empty($session->get('user-last_pw_change') === true)) {
                                        // Handle last password change string
                                        if ($session->has('user-last_pw_change') && null !== $session->get('user-last_pw_change')) {
                                            if (isset($SETTINGS['date_format']) === true) {
                                                $last_pw_change = date($SETTINGS['date_format']." ".$SETTINGS['time_format'], (int) $userInfo['last_pw_change']);
                                            } else {
                                                $last_pw_change = date('d/m/Y', (int) $session->get('user-last_pw_change'));
                                            }
                                        } else {
                                            $last_pw_change = '-';
                                        }

                                        // Handle expiration for pw
                                        if (
                                            $session->has('user-num_days_before_exp') && null !== $session->get('user-num_days_before_exp')
                                            || $session->get('user-num_days_before_exp') === ''
                                            || $session->get('user-num_days_before_exp') === 'infinite'
                                        ) {
                                            $numDaysBeforePwExpiration = '';
                                        } else {
                                            $numDaysBeforePwExpiration = $LANG['index_pw_expiration'] . ' ' . $session->get('user-num_days_before_exp') . ' ' . $LANG['days'] . '.';
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
                                            <?php echo null !== $session->get('user-tree_load_strategy') ? $session->get('user-tree_load_strategy') : ''; ?>
                                        </a>
                                    </li>
                                    <?php
                                    if (isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1) {
                                        echo '
                                    <li class="list-group-item">
                                        <b><i class="fas fa-paper-plane fa-fw fa-lg mr-2"></i>' . $lang->get('user_profile_api_key') . '</b>
                                        <button class="btn btn-sm btn-primary float-right" id="copy-api-key"><i class="fa-regular fa-copy  pointer"></i></button>
                                        <a class="float-right mr-2" id="profile-user-api-token">',
                                            null !== $session->get('user-api_key') ? $session->get('user-api_key') : '',
                                        '</a>
                                    </li>';
                                    }
                                    ?>
                                </ul>
                            </div>

                            <!-- TIMELINE -->
                            <div class="tab-pane<?php echo $get['tab'] === 'timeline' ? ' active' : ''; ?>" id="tab_timeline">
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
                                            $session->get('user-id'),
                                            $session->get('user-id')
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
                                <?php if (($SETTINGS['disable_user_edit_profile'] ?? '0') === '0') : ?>
                                    <div class="form-group">
                                        <label for="profile-name" class="col-sm-2 control-label"><?php echo $lang->get('name'); ?></label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" id="profile-user-name" placeholder="" value="<?php echo $session->get('user-name'); ?>">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="profile-lastname" class="col-sm-2 control-label"><?php echo $lang->get('lastname'); ?></label>
                                        <div class="col-sm-10">
                                            <input type="text" class="form-control" id="profile-user-lastname" placeholder="" value="<?php echo $session->get('user-lastname'); ?>">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="profile-email" class="col-sm-2 control-label"><?php echo $lang->get('email'); ?></label>
                                        <div class="col-sm-10">
                                            <input type="email" class="form-control" id="profile-user-email" placeholder="name@domain.com" value="<?php echo $session->get('user-email'); ?>">
                                        </div>
                                    </div>
                                <?php endif; /* disable_user_edit_profile */
                                if (($SETTINGS['disable_user_edit_timezone'] ?? '0') === '0') : ?>
                                    <div class="form-group">
                                        <label class="col-sm-10 control-label"><?php echo $lang->get('timezone_selection');?></label>
                                        <div class="col-sm-10">
                                            <select class="form-control" id="profile-user-timezone">
                                                <?php foreach ($zones as $key => $zone): ?>
                                                    <option value="<?php echo $key; ?>"<?php 
                                                        if ($session->has('user-timezone'))
                                                            if($session->get('user-timezone') === $key)
                                                                echo ' selected';
                                                            elseif ($session->get('user-timezone') === 'not_defined')
                                                                if (isset($SETTINGS['timezone']) && $SETTINGS['timezone'] === $key)
                                                                    echo ' selected';
                                                    ?>><?php echo $zone; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php endif; /* disable_user_edit_timezone */
                                if (($SETTINGS['disable_user_edit_language'] ?? '0') === '0') : ?>
                                    <div class="form-group">
                                        <label class="col-sm-10 control-label"><?php echo $lang->get('language'); ?></label>
                                        <div class="col-sm-10">
                                            <select class="form-control" id="profile-user-language">
                                                <?php
                                                    foreach ($languages as $language) {
                                                        echo '<option value="' . $language['name'] . '"',
                                                        strtolower($session->get('user-language')) === strtolower($language['name']) ?
                                                        ' selected="selected"' : '',
                                                    '>' . $language['label'] . '</option>';
                                                    }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php endif; /* disable_user_edit_language */
                                if (($SETTINGS['disable_user_edit_tree_load_strategy'] ?? '0') === '0') : ?>
                                    <div class="form-group">
                                        <label class="col-sm-10 control-label"><?php echo $lang->get('tree_load_strategy'); ?></label>
                                        <div class="col-sm-10">
                                            <select class="form-control" id="profile-user-treeloadstrategy">
                                                
                                                <option value="sequential" <?php echo $session->has('user-tree_load_strategy') && $session->get('user-tree_load_strategy') && null !== $session->get('user-tree_load_strategy') && $session->get('user-tree_load_strategy') === 'sequential' ? ' selected' : '';?>>
                                                    <?php echo $lang->get('sequential'); ?>
                                                </option>
                                                
                                                <option value="full" <?php echo $session->has('user-tree_load_strategy') && $session->get('user-tree_load_strategy') && null !== $session->get('user-tree_load_strategy') && $session->get('user-tree_load_strategy') === 'full' ? ' selected' : '';?>>
                                                    <?php echo $lang->get('full'); ?>
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                <?php endif; /* disable_user_edit_tree_load_strategy */ ?>

                                    <div class="form-group">
                                        <label class="col-sm-10 control-label"><?php echo $lang->get('items_page_split_view_mode'); ?></label>
                                        <div class="col-sm-10">
                                            <select class="form-control" id="profile-user-split_view_mode">
                                                
                                                <option value="0" <?php echo $session->has('user-split_view_mode') && $session->get('user-split_view_mode') && null !== $session->get('user-split_view_mode') && $session->get('user-split_view_mode') === 0 ? 'selected' : '';?>>
                                                    <?php echo $lang->get('no'); ?>
                                                </option>
                                                
                                                <option value="1" <?php echo $session->has('user-split_view_mode') && $session->get('user-split_view_mode') && null !== $session->get('user-split_view_mode') && (int) $session->get('user-split_view_mode') === 1 ? 'selected' : '';?>>
                                                    <?php echo $lang->get('yes'); ?>
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <div class="row">
                                            <div class="col-sm-offset-2 col-sm-2">
                                                <button type="button" class="btn btn-info" id="profile-user-save-settings"><?php echo $lang->get('save'); ?></button>
                                            </div>
                                            <div class="col-sm-8">
                                                <?php if (($SETTINGS['disable_user_edit_profile'] ?? '0') === '0') { ?>
                                                    <button type="button" class="btn btn-warning float-right ml-2" id="profile-avatar-file"><?php echo $lang->get('upload_new_avatar'); ?></button>
                                                <?php 
                                                }
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


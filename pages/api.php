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
 * @file      api.php
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
use TeampassClasses\NestedTree\NestedTree;
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('api') === false) {
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


?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-cubes mr-2"></i><?php echo $lang->get('api'); ?>
                </h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class='card card-primary'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo $lang->get('api_configuration'); ?></h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <div class='card-body'>

                        <div class='row mb-5'>
                            <div class='col-10'>
                                <?php echo $lang->get('settings_api'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('settings_api_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='api' data-toggle-on='<?php echo isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='api_input' value='<?php echo isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1 ? '1' : '0'; ?>' />
                            </div>
                        </div>

                        <div class='row mb-5'>
                            <div class='col-10'>
                                <?php echo $lang->get('settings_api_token_duration'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('settings_api_token_duration_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                            <input type='text' class='form-control form-control-sm' id='api_token_duration' value='<?php echo isset($SETTINGS['api_token_duration']) === true ? (int) $SETTINGS['api_token_duration'] : 60; ?>'>
                            </div>
                        </div>

                        <ul class="nav nav-tabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#keys" role="tab" aria-controls="keys"><?php echo $lang->get('settings_api_keys_list'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#ips" role="tab" aria-controls="ips"><?php echo $lang->get('api_whitelist_ips'); ?></a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="keys" role="tabpanel" aria-labelledby="keys-tab">
                                <small id="passwordHelpBlock" class="form-text text-muted mt-4">
                                    <?php echo $lang->get('settings_api_keys_list_tip'); ?>
                                </small>
                                <div class="mt-4">
                                    <?php
                                    $rowsKeys = DB::query(
                                        'SELECT increment_id, label, timestamp, user_id, value 
                                        FROM ' . prefixTable('api') . '
                                        WHERE type = %s
                                        ORDER BY timestamp ASC',
                                        'key'
                                    );
                                    ?>
                                    <table class="table table-hover table-striped<?php echo DB::count() > 0 ? '' : ' hidden'; ?> table-responsive" style="width:100%" id="table-api-keys">
                                        <thead>
                                            <tr>
                                                <th width="50px"></th>
                                                <th><?php echo $lang->get('label'); ?></th>
                                                <th><?php echo $lang->get('settings_api_key'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            foreach ($rowsKeys as $key) {
                                                echo '
                                                    <tr data-id="' . $key['increment_id'] . '">
                                                    <td width="50px"><i class="fas fa-trash infotip pointer delete-api-key" title="' . $lang->get('del_button') . '"></i></td>
                                                    <td><span class="edit-api-key pointer">' . $key['label'] . '</span></td>
                                                    <td>' . $key['value']. '</td>                        
                                                </tr>';
                                            } ?>
                                        </tbody>
                                    </table>

                                    <div class="mt-2<?php echo DB::count() > 0 ? ' hidden' : ''; ?>" id="api-no-keys">
                                        <i class="fas fa-info mr-2 text-warning"></i><?php echo $lang->get('no_data_defined'); ?>
                                    </div>

                                </div>

                                <div class="form-group mt-4">
                                    <div class="callout callout-info">
                                        <span class="text-bold"><?php echo $lang->get('adding_new_api_key'); ?></span>

                                        <div class="row mt-1 ml-1">
                                            <input type="text" placeholder="<?php echo $lang->get('label'); ?>" class="col-4 form-control form-control-sm purify" id="new_api_key_label" data-field="label">
                                            <span class="fa-stack ml-2 infotip pointer" title="<?php echo $lang->get('adding_new_api_key'); ?>" id="button-new-api-key">
                                                <i class="fas fa-square fa-stack-2x"></i>
                                                <i class="fas fa-plus fa-stack-1x fa-inverse"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="tab-pane fade show mb-4" id="ips" role="tabpanel" aria-labelledby="ips-tab">
                                <small id="passwordHelpBlock" class="form-text text-muted mt-4">
                                    <?php echo $lang->get('api_whitelist_ips_tip'); ?>
                                </small>
                                <div class="col-12 mt-4" id="table-api-ip">
                                    <?php
                                    $rowsIps = DB::query(
                                                'SELECT increment_id, label, timestamp value FROM ' . prefixTable('api') . '
                                                WHERE type = %s
                                                ORDER BY timestamp ASC',
                                                'ip'
                                            );
                                    ?>
                                    <table class="table table-hover table-striped<?php echo DB::count() > 0 ? '' : ' hidden'; ?> table-responsive" style="width:100%" id="table-api-ips">
                                        <thead>
                                            <tr>
                                                <th width="50px"></th>
                                                <th><?php echo $lang->get('label'); ?></th>
                                                <th><?php echo $lang->get('settings_api_ip'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            foreach ($rowsIps as $ip) {
                                                echo '
                                                <tr data-id="' . $ip['increment_id'] . '">
                                                    <td width="50px"><i class="fas fa-trash infotip pointer delete-api-ip" title="' . $lang->get('del_button') . '"></i></td>
                                                    <td><span class="edit-api-ip pointer" data-field="label">' . $ip['label'] . '</span></td>
                                                    <td><span class="edit-api-ip pointer" data-field="value">' . $ip['value'] . '</span></td>                        
                                                </tr>';
                                            } ?>
                                        </tbody>
                                    </table>

                                    <div class="mt-2<?php echo DB::count() > 0 ? ' hidden' : ''; ?>" id="api-no-ips">
                                        <i class="fas fa-info mr-2 text-warning"></i><?php echo $lang->get('no_data_defined'); ?>
                                    </div>
                                </div>

                                <div class="form-group mt-4" id="new-api-ip">
                                    <div class="callout callout-info">
                                        <span class="text-bold"><?php echo $lang->get('adding_new_api_ip'); ?></span>

                                        <div class="row mt-1 ml-1">
                                            <input type="text" placeholder="<?php echo $lang->get('ip'); ?>" class="col-4 form-control" id="new_api_ip_value" data-inputmask="'alias': 'ip'">
                                            <input type="text" placeholder="<?php echo $lang->get('label'); ?>" class="col-4 form-control ml-2 purify" id="new_api_ip_label" data-field="label">
                                            <span class="fa-stack ml-2 infotip pointer" title="<?php echo $lang->get('settings_api_add_ip'); ?>" id="button-new-api-ip">
                                                <i class="fas fa-square fa-stack-2x"></i>
                                                <i class="fas fa-plus fa-stack-1x fa-inverse"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="form-group">

                        </div>

                        <div class="form-group mt-8">

                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

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
 * @file      api.php
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

if (
    isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id']) === true
    || isset($_SESSION['key']) === false || empty($_SESSION['key']) === true
) {
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
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'api', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Load template
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-cubes mr-2"></i><?php echo langHdl('api'); ?>
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
                        <h3 class='card-title'><?php echo langHdl('api_configuration'); ?></h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <div class='card-body'>

                        <div class='row mb-5'>
                            <div class='col-10'>
                                <?php echo langHdl('settings_api'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo langHdl('settings_api_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='api' data-toggle-on='<?php echo isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='api_input' value='<?php echo isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1 ? '1' : '0'; ?>' />
                            </div>
                        </div>

                        <ul class="nav nav-tabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#keys" role="tab" aria-controls="keys"><?php echo langHdl('settings_api_keys_list'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#ips" role="tab" aria-controls="ips"><?php echo langHdl('api_whitelist_ips'); ?></a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="keys" role="tabpanel" aria-labelledby="keys-tab">
                                <small id="passwordHelpBlock" class="form-text text-muted mt-4">
                                    <?php echo langHdl('settings_api_keys_list_tip'); ?>
                                </small>
                                <div class="mt-4">
                                    <?php
                                    $rows = DB::query(
    'SELECT id, label, value FROM ' . prefixTable('api') . '
                                        WHERE type = %s
                                        ORDER BY timestamp ASC',
    'key'
);
                                    ?>
                                    <table class="table table-hover table-striped<?php echo DB::count() > 0 ? '' : ' hidden'; ?>" style="width:100%" id="table-api-keys">
                                        <thead>
                                            <tr>
                                                <th width="50px"></th>
                                                <th><?php echo langHdl('label'); ?></th>
                                                <th><?php echo langHdl('settings_api_key'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            foreach ($rows as $record) {
                                                echo '
                                                    <tr data-id="' . $record['id'] . '">
                                                    <td width="50px"><i class="fa fa-trash infotip pointer delete-api-key" title="' . langHdl('del_button') . '"></i></td>
                                                    <td><span class="edit-api-key pointer">' . $record['label'] . '</span></td>
                                                    <td>' . $record['value'] . '</td>                        
                                                </tr>';
                                            } ?>
                                        </tbody>
                                    </table>

                                    <div class="mt-2<?php echo DB::count() > 0 ? ' hidden' : ''; ?>" id="api-no-keys">
                                        <i class="fas fa-info mr-2 text-warning"></i><?php echo langHdl('no_data_defined'); ?>
                                    </div>

                                </div>

                                <div class="form-group mt-4">
                                    <div class="callout callout-info">
                                        <span class="text-bold"><?php echo langHdl('adding_new_api_key'); ?></span>

                                        <div class="row mt-1 ml-1">
                                            <input type="text" placeholder="<?php echo langHdl('label'); ?>" class="col-4 form-control form-control-sm" id="new_api_key_label">
                                            <span class="fa-stack ml-2 infotip pointer" title="<?php echo langHdl('adding_new_api_key'); ?>" id="button-new-api-key">
                                                <i class="fas fa-square fa-stack-2x"></i>
                                                <i class="fas fa-plus fa-stack-1x fa-inverse"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="tab-pane fade show mb-4" id="ips" role="tabpanel" aria-labelledby="ips-tab">
                                <small id="passwordHelpBlock" class="form-text text-muted mt-4">
                                    <?php echo langHdl('api_whitelist_ips_tip'); ?>
                                </small>
                                <div class="col-12 mt-4" id="table-api-ip">
                                    <?php
                                    $rows = DB::query(
                                                'SELECT id, label, value FROM ' . prefixTable('api') . '
                                        WHERE type = %s
                                        ORDER BY timestamp ASC',
                                                'ip'
                                            );
                                    ?>
                                    <table class="table table-hover table-striped<?php echo DB::count() > 0 ? '' : ' hidden'; ?>" style="width:100%" id="table-api-ips">
                                        <thead>
                                            <tr>
                                                <th width="50px"></th>
                                                <th><?php echo langHdl('label'); ?></th>
                                                <th><?php echo langHdl('settings_api_ip'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            foreach ($rows as $record) {
                                                echo '
                                                <tr data-id="' . $record['id'] . '">
                                                    <td width="50px"><i class="fa fa-trash infotip pointer delete-api-ip" title="' . langHdl('del_button') . '"></i></td>
                                                    <td><span class="edit-api-ip pointer" data-field="label">' . $record['label'] . '</span></td>
                                                    <td><span class="edit-api-ip pointer" data-field="value">' . $record['value'] . '</span></td>                        
                                                </tr>';
                                            } ?>
                                        </tbody>
                                    </table>

                                    <div class="mt-2<?php echo DB::count() > 0 ? ' hidden' : ''; ?>" id="api-no-ips">
                                        <i class="fas fa-info mr-2 text-warning"></i><?php echo langHdl('no_data_defined'); ?>
                                    </div>
                                </div>

                                <div class="form-group mt-4">
                                    <div class="callout callout-info">
                                        <span class="text-bold"><?php echo langHdl('adding_new_api_ip'); ?></span>

                                        <div class="row mt-1 ml-1">
                                            <input type="text" placeholder="<?php echo langHdl('ip'); ?>" class="col-4 form-control" id="new_api_ip_value" data-inputmask="'alias': 'ip'" data-mask>
                                            <input type="text" placeholder="<?php echo langHdl('label'); ?>" class="col-4 form-control ml-2" id="new_api_ip_label">
                                            <span class="fa-stack ml-2 infotip pointer" title="<?php echo langHdl('settings_api_add_ip'); ?>" id="button-new-api-ip">
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

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
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'api', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

// Load template
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

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
                        <?php
                        if (isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1) {
                            ?>
                        
                        <div class="form-group">
                            <label for="path_to_files_folder" class="control-label">
                                <?php echo langHdl('settings_api_keys_list'); ?>
                            </label>
                            <small id="passwordHelpBlock" class="form-text text-muted">
                                    <?php echo langHdl('settings_api_keys_list_tip'); ?>
                                </small>
                            <div class="col-12 mt-2">
                                <table class="table table-hover table-striped" style="width:100%" id="table-api-keys">
                                    <thead>
                                        <tr>
                                            <th width="50px"></th>
                                            <th><?php echo langHdl('label'); ?></th>
                                            <th><?php echo langHdl('settings_api_key'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        $rows = DB::query(
                                            'SELECT id, label, value FROM '.prefixTable('api').'
                                            WHERE type = %s
                                            ORDER BY timestamp ASC',
                                            'key'
                                        );
                            foreach ($rows as $record) {
                                echo '
                                            <tr data-id="'.$record['id'].'">
                                            <td width="50px"><i class="fa fa-trash infotip pointer delete-api-key" title="'.langHdl('del_button').'"></i></td>
                                            <td><span class="edit-api-key pointer">'.$record['label'].'</span></td>
                                            <td>'.$record['value'].'</td>                        
                                        </tr>';
                            } ?>
                                    </tbody>
                                </table>
                                <button class="btn btn-primary mt-2" id="button-new-api-key"><?php echo langHdl('settings_api_add_key'); ?></button>
                            </div>
                        </div>

                        <div class="form-group mt-8">
                            <label for="path_to_files_folder" class="control-label">
                                <?php echo langHdl('api_whitelist_ips'); ?>
                            </label>
                            <small id="passwordHelpBlock" class="form-text text-muted">
                                    <?php echo langHdl('api_whitelist_ips_tip'); ?>
                                </small>
                            <div class="col-12 mt-2 hidden" id="table-api-ip">
                                <table class="table table-hover table-striped" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th width="50px"></th>
                                            <th><?php echo langHdl('label'); ?></th>
                                            <th><?php echo langHdl('settings_api_ip'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $rows = DB::query(
                                        'SELECT id, label, value FROM '.prefixTable('api').'
                                        WHERE type = %s
                                        ORDER BY timestamp ASC',
                                        'ip'
                                    );
                            foreach ($rows as $record) {
                                echo '
                                        <tr data-id="'.$record['id'].'">
                                            <td width="50px"><i class="fa fa-trash infotip pointer delete-api-ip" title="'.langHdl('del_button').'"></i></td>
                                            <td><span class="edit-api-ip pointer">'.$record['label'].'</span></td>
                                            <td>'.$record['value'].'</td>                        
                                        </tr>';
                            } ?>
                                    </tbody>
                                </table>
                            </div>
                            <button class="btn btn-primary mt-2" id="button-new-api-ip"><?php echo langHdl('settings_api_add_ip'); ?></button>
                        </div>

                        <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

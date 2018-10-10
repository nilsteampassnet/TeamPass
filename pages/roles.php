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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'roles', $SETTINGS) === false) {
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
        <i class="fa fa-graduation-cap mr-2"></i><?php echo langHdl('roles'); ?>
        </h1>
        </div><!-- /.col -->
    </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-middle">
                    <h3 class="card-title">
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="new">
                            <i class="fa fa-plus mr-2"></i><?php echo langHdl('new'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="delete">
                            <i class="fa fa-trash mr-2"></i><?php echo langHdl('delete'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="refresh">
                            <i class="fa fa-refresh mr-2"></i><?php echo langHdl('refresh'); ?>
                        </button>
                    </h3>
                </div>

                <!-- /.card-header -->
                <div class="card-body form" id="folders-list">
                    <table id="table-folders" class="table table-bordered table-striped" style="width:100%">
                        <thead>
                        <tr>
                            <th></th>
                            <th><?php echo langHdl('group'); ?></th>
                            <th><?php echo langHdl('group_parent'); ?></th>
                            <th><i class="fa fa-gavel fa-lg infotip" title="<?php echo langHdl('password_strength'); ?>"></i></th>
                            <th><i class="fa fa-recycle fa-lg infotip" title="<?php echo langHdl('group_pw_duration').' '.langHdl('group_pw_duration_tip'); ?>"></i></th>
                            <th><i class="fa fa-pencil fa-lg infotip" title="<?php echo langHdl('auth_creation_without_complexity'); ?>"></i></th>
                            <th><i class="fa fa-pencil-square-o fa-lg infotip" title="<?php echo langHdl('auth_modification_without_complexity'); ?>"></i></th>
                        </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
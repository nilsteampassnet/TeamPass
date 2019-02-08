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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'utilities.logs', $SETTINGS) === false) {
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
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-history mr-2"></i><?php echo langHdl('logs'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->
    

<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs">
                            <li class="nav-item">
                              <a class="nav-link active" id="connections-tab" data-toggle="tab" href="#connections" aria-controls="connections" aria-selected="true"><?php echo langHdl('connections'); ?></a>
                            </li>
                            <li class="nav-item">
                              <a class="nav-link" data-toggle="tab" href="#failed" role="tab" aria-controls="failed" aria-selected="false"><?php echo langHdl('failed_logins'); ?></a>
                            </li>
                            <li class="nav-item">
                              <a class="nav-link" data-toggle="tab" href="#errors" role="tab" aria-controls="errors" aria-selected="false"><?php echo langHdl('errors'); ?></a>
                            </li>
                            <li class="nav-item">
                              <a class="nav-link" data-toggle="tab" href="#copy" role="tab" aria-controls="copy" aria-selected="false"><?php echo langHdl('at_copy'); ?></a>
                            </li>
                            <li class="nav-item">
                              <a class="nav-link" data-toggle="tab" href="#admin" role="tab" aria-controls="admin" aria-selected="false"><?php echo langHdl('admin'); ?></a>
                            </li>
                            <li class="nav-item">
                              <a class="nav-link" data-toggle="tab" href="#items" role="tab" aria-controls="items" aria-selected="false"><?php echo langHdl('items'); ?></a>
                            </li>
                        </ul>


                        <div class="tab-content mt-1" id="myTabContent">
                            <div class="tab-pane fade show active" id="connections" role="tabpanel" aria-labelledby="connections-tab">
                                <table class="table table-striped" id="table-connections" style="width:100%;">
                                    <thead><tr>
                                        <th style=""><?php echo langHdl('date'); ?></th>
                                        <th style=""><?php echo langHdl('action'); ?></th>
                                        <th style=""><?php echo langHdl('user'); ?></th>
                                    </tr></thead>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="errors" role="tabpanel" aria-labelledby="errors-tab">
                                <table class="table table-striped" id="table-errors" style="width:100%;">
                                    <thead><tr>
                                        <th style=""><?php echo langHdl('date'); ?></th>
                                        <th style=""><?php echo langHdl('label'); ?></th>
                                        <th style=""><?php echo langHdl('user'); ?></th>
                                    </tr></thead>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="copy" role="tabpanel" aria-labelledby="copy-tab">
                                <table class="table table-striped" id="table-copy" style="width:100%;">
                                    <thead><tr>
                                        <th style=""><?php echo langHdl('date'); ?></th>
                                        <th style=""><?php echo langHdl('label'); ?></th>
                                        <th style=""><?php echo langHdl('user'); ?></th>
                                    </tr></thead>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="admin" role="tabpanel" aria-labelledby="admin-tab">
                                <table class="table table-striped" id="table-admin" style="width:100%;">
                                    <thead><tr>
                                        <th style=""><?php echo langHdl('date'); ?></th>
                                        <th style=""><?php echo langHdl('user'); ?></th>
                                        <th style=""><?php echo langHdl('action'); ?></th>
                                    </tr></thead>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="items" role="tabpanel" aria-labelledby="items-tab">
                                <table class="table table-striped" id="table-items" style="width:100%;">
                                    <thead><tr>
                                        <th style=""><?php echo langHdl('date'); ?></th>
                                        <th style=""><?php echo langHdl('id'); ?></th>
                                        <th style=""><?php echo langHdl('label'); ?></th>
                                        <th style=""><?php echo langHdl('folder'); ?></th>
                                        <th style=""><?php echo langHdl('user'); ?></th>
                                        <th style=""><?php echo langHdl('action'); ?></th>
                                        <th style=""><?php echo langHdl('at_personnel'); ?></th>
                                    </tr></thead>
                                </table>
                            </div>
                            <div class="tab-pane fade" id="failed" role="tabpanel" aria-labelledby="failed-tab">
                                <table class="table table-striped" id="table-failed" style="width:100%;">
                                    <thead><tr>
                                        <th style=""><?php echo langHdl('date'); ?></th>
                                        <th style=""><?php echo langHdl('label'); ?></th>
                                        <th style=""><?php echo langHdl('user'); ?></th>
                                        <th style=""><?php echo langHdl('ip'); ?></th>
                                    </tr></thead>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer<?php
                    echo (isset($_SESSION['user_admin']) && (int) $_SESSION['user_admin'] === 1) ? '' : ' hidden';
                    ?>">
                        <div class="form-group">
                            <label><i class="fas fa-broom mr-2"></i><?php echo langHdl('purge').' '.langHdl('date_range'); ?>:</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fa fa-calendar"></i>
                                </span>
                                </div>
                                <input type="text" class="form-control float-right" id="purge-date-range">
                                <span class="input-group-append">
                                    <button type="button" class="btn btn-info btn-flat" id="clear-purge-date"><i class="fas fa-broom"></i></button>
                                </span>
                            </div>
                        </div>
                        <div class="form-group mt-2 group-confirm-purge hidden">
                            <input type="checkbox" class="form-check-input form-item-control" id="checkbox-purge-confirm">
                            <label class="form-check-label ml-2" for="checkbox-purge-confirm">
                                <?php echo langHdl('please_confirm_deletion'); ?>
                            </label>
                        </div>
                        <div class="form-group mt-2 group-confirm-purge hidden">
                            <button class="btn btn-danger" id="button-perform-purge"><?php echo langHdl('submit'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.col-md-6 -->
        </div>
        <!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content -->






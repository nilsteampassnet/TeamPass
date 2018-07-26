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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], curPage($SETTINGS), $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

// Load
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6">
        <h1 class="m-0 text-dark"><?php echo langHdl('find'); ?></h1>
        </div><!-- /.col -->
    </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Data Table With Full Features</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <table id="search-results-items" class="table table-bordered table-striped">
                        <thead><tr>
                            <th></th>
                            <th style="width:15%;"><?php echo langHdl('label'); ?></th>
                            <th style="width:20%;"><?php echo langHdl('login'); ?></th>
                            <th style="width:25%;"><?php echo langHdl('description'); ?></th>
                            <th style="width:13%;"><?php echo langHdl('tags'); ?></th>
                            <th style="width:13%;"><?php echo langHdl('url'); ?></th>
                            <th style="width:20%;"><?php echo langHdl('group'); ?></th>
                        </tr></thead>
                        <tbody>
                            <tr><td></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
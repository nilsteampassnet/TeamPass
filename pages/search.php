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
 * @file      search.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('search') === false) {
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
                <h1 class="m-0 text-dark"><i class="fas fa-search mr-2"></i><?php echo $lang->get('find'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<!-- MASS OPERATION -->
<div class="card card-warning m-2 hidden" id="dialog-mass-operation">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-bug mr-2"></i>
            <?php echo $lang->get('mass_operation'); ?>
        </h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-sm-12 col-md-12" id="dialog-mass-operation-html">

            </div>
        </div>
    </div>
    <div class="card-footer">
        <button class="btn btn-primary mr-2" id="dialog-mass-operation-button"><?php echo $lang->get('perform'); ?></button>
        <button class="btn btn-default float-right close-element"><?php echo $lang->get('cancel'); ?></button>
    </div>
</div>
<!-- /.MASS OPERATION -->

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mr-2" id="search-select"></h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <table id="search-results-items" class="table table-bordered table-striped" style="width:100%">
                        <thead>
                            <tr>
                                <th></th>
                                <th><?php echo $lang->get('label'); ?></th>
                                <th><?php echo $lang->get('login'); ?></th>
                                <th><?php echo $lang->get('description'); ?></th>
                                <th><?php echo $lang->get('tags'); ?></th>
                                <th><?php echo $lang->get('url'); ?></th>
                                <th><?php echo $lang->get('group'); ?></th>
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

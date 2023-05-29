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
 * @file      actions.php
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'ldap', $SETTINGS) === false) {
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
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-cogs mr-2"></i><?php echo langHdl('actions'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->


<!-- Main content -->
<div class='content'>
    <div class='container-fluid'>
        <div class='row'>
            <div class='col-md-12'>
                <div class='card card-primary'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo langHdl('set_of_actions'); ?></h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <div class='card-body'>

                        <div class="row mt-3">
                            <div class="col-12">
                                <span class="fa-stack mr-3 infotip pointer button-action" data-action="optimize-db" title="<?php echo langHdl('start'); ?>">
                                    <i class="fas fa-square fa-stack-2x" style="color:tomato"></i>
                                    <i class="fas fa-cog fa-stack-1x fa-inverse"></i>
                                </span>
                                <?php echo langHdl('admin_action_db_optimize');
$data = DB::queryfirstrow(
    'SELECT field_1, date FROM ' . prefixTable('log_system') . '
                                    WHERE label = %s
                                    ORDER BY id DESC',
    'admin_action_db_optimize'
);
if (DB::count() > 0) {
    $tmp = langHdl('last_execution') . ' ' .
                                        date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $data['date']);
    $tmp .= $data['field_1'] === 'success' ?
                                        '<i class="fas fa-check ml-2 text-success"></i>' : '<i class="fas fa-times ml-2 text-danger"></i>';
} else {
    $tmp = langHdl('never_performed');
}
                                ?>
                                <span class="ml-3 text-muted" id="optimize-db-result"><?php echo $tmp; ?></span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

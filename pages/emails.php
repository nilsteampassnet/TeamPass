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
 * @file      emails.php
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'emails', $SETTINGS) === false) {
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
                    <i class="fas fa-envelope mr-2"></i><?php echo langHdl('emails'); ?>
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
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class='card-title'><?php echo langHdl('email_configuration'); ?></h3>
                    </div>

                    <div class="card-body">

                        <div class="row mb-2">
                            <div class="col-5">
                                <?php echo langHdl('admin_email_smtp_server'); ?>
                            </div>
                            <div class="col-7 mb-0">
                                <input type='text' class='form-control form-control-sm' id='email_smtp_server' value='<?php echo isset($SETTINGS['email_smtp_server']) === true ? $SETTINGS['email_smtp_server'] : ''; ?>'>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-5">
                                <?php echo langHdl('admin_email_auth'); ?>
                            </div>
                            <div class="col-7 mb-0">
                                <div class='toggle toggle-modern' id='email_smtp_auth' data-toggle-on='<?php echo isset($SETTINGS['email_smtp_auth']) === true && $SETTINGS['email_smtp_auth'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='email_smtp_auth_input' value='<?php echo isset($SETTINGS['email_smtp_auth']) && $SETTINGS['email_smtp_auth'] === '1' ? '1' : '0'; ?>' />
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-5">
                                <?php echo langHdl('admin_email_auth_username'); ?>
                            </div>
                            <div class="col-7 mb-0">
                                <input type='text' class='form-control form-control-sm' id='email_auth_username' value='<?php echo isset($SETTINGS['email_auth_username']) === true ? $SETTINGS['email_auth_username'] : ''; ?>'>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-5">
                                <?php echo langHdl('admin_email_auth_pwd'); ?>
                            </div>
                            <div class="col-7 mb-0">
                                <input type='password' class='form-control form-control-sm' id='email_auth_pwd' value='<?php echo isset($SETTINGS['email_auth_pwd']) === true ? $SETTINGS['email_auth_pwd'] : ''; ?>'>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-5">
                                <?php echo langHdl('admin_email_server_url'); ?>
                            </div>
                            <div class="col-7 mb-0">
                                <input type='text' class='form-control form-control-sm' id='email_server_url' value='<?php echo isset($SETTINGS['email_server_url']) === true ? $SETTINGS['email_server_url'] : ''; ?>'>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-5">
                                <?php echo langHdl('admin_email_port'); ?>
                            </div>
                            <div class="col-7 mb-0">
                                <input type='text' class='form-control form-control-sm' id='email_port' value='<?php echo isset($SETTINGS['email_port']) === true ? $SETTINGS['email_port'] : ''; ?>'>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-5">
                                <?php echo langHdl('admin_email_security'); ?>
                            </div>
                            <div class="col-7 mb-0">
                                <select class='form-control form-control-sm' id='email_security'>
                                    <option value="none" <?php echo isset($SETTINGS['email_security']) === false || $SETTINGS['email_security'] === 'none' ? ' selected' : ''; ?>><?php echo langHdl('none'); ?></option>
                                    <option value="ssl" <?php echo isset($SETTINGS['email_security']) === true && $SETTINGS['email_security'] === 'ssl' ? ' selected' : ''; ?>>SSL</option>
                                    <option value="tls" <?php echo isset($SETTINGS['email_security']) === true && $SETTINGS['email_security'] === 'tls' ? ' selected' : ''; ?>>TLS</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-5">
                                <?php echo langHdl('admin_email_from'); ?>
                            </div>
                            <div class="col-7 mb-0">
                                <input type='text' class='form-control form-control-sm' id='email_from' value='<?php echo isset($SETTINGS['email_from']) === true ? $SETTINGS['email_from'] : ''; ?>'>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-5">
                                <?php echo langHdl('admin_email_from_name'); ?>
                            </div>
                            <div class="col-7 mb-0">
                                <input type='text' class='form-control form-control-sm' id='email_from_name' value='<?php echo isset($SETTINGS['email_from_name']) === true ? $SETTINGS['email_from_name'] : ''; ?>'>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class='card-title'><?php echo langHdl('email_configuration_test'); ?></h3>
                    </div>

                    <div class="card-body">

                        <div class="row mb-2">
                            <div class="col-5">
                                <?php echo langHdl('email_debug_level'); ?>
                            </div>
                            <div class="col-7 mb-0">
                                <select class='form-control form-control-sm' id='email_debug_level'>
                                    <option value='0'<?php echo isset($SETTINGS['email_debug_level']) === true && $SETTINGS['email_debug_level'] === '0' ? ' selected' : ''; ?>><?php echo langHdl('none'); ?></option>
                                    <option value='1'<?php echo isset($SETTINGS['email_debug_level']) === true && $SETTINGS['email_debug_level'] === '1' ? ' selected' : ''; ?>><?php echo langHdl('email_debug_client'); ?></option>
                                    <option value='2'<?php echo isset($SETTINGS['email_debug_level']) === true && $SETTINGS['email_debug_level'] === '2' ? ' selected' : ''; ?>><?php echo langHdl('email_debug_server'); ?></option>
                                    <option value='3'<?php echo isset($SETTINGS['email_debug_level']) === true && $SETTINGS['email_debug_level'] === '3' ? ' selected' : ''; ?>><?php echo langHdl('email_debug_connection'); ?></option>
                                    <option value='4'<?php echo isset($SETTINGS['email_debug_level']) === true && $SETTINGS['email_debug_level'] === '4' ? ' selected' : ''; ?>><?php echo langHdl('email_debug_low_level'); ?></option>
                                </select>
                                <small class='form-text text-muted'>
                                    <?php echo langHdl('email_debug_level_usage'); ?>
                                </small>
                            </div>
                        </div>

                        <button class="btn btn-primary button" data-action="send-test-email">
                            <?php echo langHdl('send_a_test_email'); ?>
                        </button>
                        <small id='passwordHelpBlock' class='form-text text-muted'>
                            <?php echo langHdl('admin_email_test_configuration_tip'); ?>
                        </small>

                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class='card-title'><?php echo langHdl('manage_emails_not_sent'); ?></h3>
                    </div>

                    <div class="card-body">
                        <div id="unsent-emails">
                            <?php
                            DB::query('SELECT * FROM ' . prefixTable('emails') . ' WHERE status = %s OR status = %s', 'not_sent', '');
echo str_replace('#nb_emails#', (string) DB::count(), langHdl('email_send_backlog'));
                            ?>
                        </div>

                        <button class="btn btn-primary button mt-3" data-action="send-waiting-emails">
                            <?php echo langHdl('send_waiting_emails'); ?>
                        </button>
                        <small id='passwordHelpBlock' class="form-text text-muted mt-2">
                            <?php echo langHdl('admin_email_send_backlog_tip'); ?>
                        </small>

                    </div>
                </div>
            </div>
        </div>


    </div>
    </div>

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
 * @file      emails.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';
require_once __DIR__ . '/../sources/emails_transport_logic.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('emails') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //
$localPasswordRecoveryEnabled = isset($SETTINGS['enable_local_password_recovery']) === true
    ? (int) $SETTINGS['enable_local_password_recovery'] === 1
    : ((int) ($SETTINGS['disable_show_forgot_pwd_link'] ?? 0) !== 1);

?>
<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-envelope mr-2"></i><?php echo $lang->get('emails'); ?>
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
                        <h3 class='card-title'><?php echo $lang->get('email_configuration'); ?></h3>
                    </div>

                    <div class="card-body">
                        <?php require __DIR__ . '/emails_transport_form.php'; ?>

                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class='card-title'><?php echo $lang->get('email_configuration_test'); ?></h3>
                    </div>

                    <div class="card-body">

                        <div class="row mb-2">
                            <div class="col-5">
                                <?php echo $lang->get('email_debug_level'); ?>
                            </div>
                            <div class="col-7 mb-0">
                                <select class='form-control form-control-sm no-save' id='email_debug_level' data-email-profile='true'>
                                    <option value='0'<?php echo isset($SETTINGS['email_debug_level']) === true && $SETTINGS['email_debug_level'] === '0' ? ' selected' : ''; ?>><?php echo $lang->get('none'); ?></option>
                                    <option value='1'<?php echo isset($SETTINGS['email_debug_level']) === true && $SETTINGS['email_debug_level'] === '1' ? ' selected' : ''; ?>><?php echo $lang->get('email_debug_client'); ?></option>
                                    <option value='2'<?php echo isset($SETTINGS['email_debug_level']) === true && $SETTINGS['email_debug_level'] === '2' ? ' selected' : ''; ?>><?php echo $lang->get('email_debug_server'); ?></option>
                                    <option value='3'<?php echo isset($SETTINGS['email_debug_level']) === true && $SETTINGS['email_debug_level'] === '3' ? ' selected' : ''; ?>><?php echo $lang->get('email_debug_connection'); ?></option>
                                    <option value='4'<?php echo isset($SETTINGS['email_debug_level']) === true && $SETTINGS['email_debug_level'] === '4' ? ' selected' : ''; ?>><?php echo $lang->get('email_debug_low_level'); ?></option>
                                </select>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('email_debug_level_usage'); ?>
                                </small>
                            </div>
                        </div>

                        <button class="btn btn-primary button" data-action="send-test-email">
                            <?php echo $lang->get('send_a_test_email'); ?>
                        </button>
                        <small id='passwordHelpBlock' class='form-text text-muted'>
                            <?php echo $lang->get('admin_email_test_configuration_tip'); ?>
                        </small>

                        <div id='email-test-debug-block' class='mt-3' hidden>
                            <label class='mb-1'><?php echo $lang->get('email_debug_output'); ?></label>
                            <pre id='email-test-debug' class='bg-light border rounded p-2 mb-0'
                                 style='max-height:20rem; overflow:auto; white-space:pre-wrap; word-break:break-all;'></pre>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class='card-title'><?php echo $lang->get('manage_emails_not_sent'); ?></h3>
                    </div>

                    <div class="card-body">
                        <div id="unsent-emails">
                            <?php
                            DB::query('SELECT * FROM ' . prefixTable('emails') . ' WHERE status = %s OR status = %s', 'not_sent', '');
echo str_replace('#nb_emails#', (string) DB::count(), $lang->get('email_send_backlog'));
                            ?>
                        </div>

                        <button class="btn btn-primary button mt-3" data-action="send-waiting-emails">
                            <?php echo $lang->get('send_waiting_emails'); ?>
                        </button>
                        <small id='passwordHelpBlock' class="form-text text-muted mt-2">
                            <?php echo $lang->get('admin_email_send_backlog_tip'); ?>
                        </small>

                    </div>
                </div>
            </div>
        </div>


    </div>
    </div>

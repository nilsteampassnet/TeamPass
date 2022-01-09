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
 * @file      statistics.php
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'statistics', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// get current statistics items
$statistics_items = [];
if (isset($SETTINGS['send_statistics_items'])) {
    $statistics_items = array_filter(explode(';', $SETTINGS['send_statistics_items']));
}

require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-chart-bar mr-2"></i><?php echo langHdl('statistics'); ?></h1>
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
                        <h3 class='card-title'><?php echo langHdl('configuration'); ?></h3>
                    </div>

                    <div class='card-body'>
                        <div class='row mb-5'>
                            <div class='col-10'>
                                <?php echo langHdl('sending_anonymous_statistics'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo langHdl('sending_anonymous_statistics_details'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='send_stats' data-toggle-on='<?php echo isset($SETTINGS['send_stats']) === true && (int) $SETTINGS['send_stats'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='send_stats_input' value='<?php echo isset($SETTINGS['send_stats']) === true && (int) $SETTINGS['send_stats'] === 1 ? '1' : '0'; ?>' />
                            </div>
                        </div>

                        <div class='row mb-5' id='statistics-options'>
                            <table class="table table-bordered">
                                <tr>
                                    <th style="width:60%;"><?php echo langHdl('characteristic'); ?></th>
                                    <th style="width:40%;"><?php echo langHdl('current_value'); ?></th>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_country" style="margin-right:15px;" <?php echo in_array('stat_country', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?> class="stat_option"><label class="ml-2" for="stat_country"><b><?php echo langHdl('country'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo langHdl('country_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_country" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_users" style="margin-right:15px;" <?php echo in_array('stat_users', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?> class="stat_option"><label class="ml-2" for="stat_users"><b><?php echo langHdl('users'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo langHdl('users_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_users" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_items" style="margin-right:15px;" <?php echo in_array('stat_items', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_items"><b><?php echo langHdl('items_all'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo langHdl('items_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_items" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_items_shared" style="margin-right:15px;" <?php echo in_array('stat_items_shared', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_items_shared"><b><?php echo langHdl('items_shared'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_items_shared" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_folders" style="margin-right:15px;" <?php echo in_array('stat_folders', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_folders"><b><?php echo langHdl('folders_all'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo langHdl('folders_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_folders" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_folders_shared" style="margin-right:15px;" <?php echo in_array('stat_folders_shared', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_folders_shared"><b><?php echo langHdl('folders_shared'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_folders_shared" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_admins" style="margin-right:15px;" <?php echo in_array('stat_admins', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_admins"><b><?php echo langHdl('administrators_number'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo langHdl('administrators_number_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_admin" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_managers" style="margin-right:15px;" <?php echo in_array('stat_managers', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_managers"><b><?php echo langHdl('managers_number'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo langHdl('managers_number_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_manager" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_ro" style="margin-right:15px;" <?php echo in_array('stat_ro', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_ro"><b><?php echo langHdl('readonly_number'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo langHdl('readonly_number_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_ro" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_mysqlversion" style="margin-right:15px;" <?php echo in_array('stat_mysqlversion', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_mysqlversion"><b><?php echo langHdl('mysql_version'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_mysql" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_phpversion" style="margin-right:15px;" <?php echo in_array('stat_phpversion', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_phpversion"><b><?php echo langHdl('php_version'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_php" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_teampassversion" style="margin-right:15px;" <?php echo in_array('stat_teampassversion', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_teampassversion"><b><?php echo langHdl('teampass_version'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_teampassv" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_languages" style="margin-right:15px;" <?php echo in_array('stat_languages', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_languages"><b><?php echo langHdl('languages_used'); ?></b></label>
                                        <i class="fas fa-question-circle infotip ml-2" title="<?php echo langHdl('languages_statistics'); ?>"></i>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_languages" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_kb" style="margin-right:15px;" <?php echo in_array('stat_kb', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_kb"><b><?php echo langHdl('kb_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_kb" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_suggestion" style="margin-right:15px;" <?php echo in_array('stat_suggestion', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_suggestion"><b><?php echo langHdl('suggestion_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_suggestion" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_customfields" style="margin-right:15px;" <?php echo in_array('stat_customfields', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_customfields"><b><?php echo langHdl('customfields_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_customfields" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_api" style="margin-right:15px;" <?php echo in_array('stat_api', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_api"><b><?php echo langHdl('api_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_api" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_2fa" style="margin-right:15px;" <?php echo in_array('stat_2fa', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_2fa"><b><?php echo langHdl('2fa_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_2fa" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_agses" style="margin-right:15px;" <?php echo in_array('stat_agses', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_agses"><b><?php echo langHdl('agses_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_agses" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_duo" style="margin-right:15px;" <?php echo in_array('stat_duo', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_duo"><b><?php echo langHdl('duo_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_duo" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_ldap" style="margin-right:15px;" <?php echo in_array('stat_ldap', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_ldap"><b><?php echo langHdl('ldap_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_ldap" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_syslog" style="margin-right:15px;" <?php echo in_array('stat_syslog', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_syslog"><b><?php echo langHdl('syslog_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_syslog" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_stricthttps" style="margin-right:15px;" <?php echo in_array('stat_stricthttps', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_stricthttps"><b><?php echo langHdl('stricthttps_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_https" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_fav" style="margin-right:15px;" <?php echo in_array('stat_fav', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_fav"><b><?php echo langHdl('favourites_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_fav" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="stat_pf" style="margin-right:15px;" <?php echo in_array('stat_pf', $statistics_items) || count($statistics_items) === 0 ? 'checked' : ''; ?>><label class="ml-2" for="stat_pf"><b><?php echo langHdl('personalfolders_option_enabled'); ?></b></label>
                                    </td>
                                    <td>
                                        <div class="spin_wait" id="value_pf" style="text-align:center;"><span class="fas fa-spinner fa-pulse"></span></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <input type="checkbox" class="form-check-input form-control stat_option flat-blue" id="cb_select_all" style="margin:10px 15px 0 4px;"><label class="ml-2" for="cb_select_all"><b><?php echo langHdl('select_all'); ?></b></label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="button" class="btn btn-primary tp-action" id="statistics-save"><?php echo langHdl('save'); ?></button>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

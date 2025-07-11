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
 * @file      options.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('options') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //
 
// Generates zones
$zones = timezone_list();
?>

<!-- Content Header (Page header) -->
<div class='content-header'>
    <div class='container-fluid'>
        <div class='row mb-2'>
            <div class='col-sm-6'>
                <h1 class='m-0 text-dark'><?php echo $lang->get('options'); ?></h1>
            </div><!-- /.col -->
            <div class='col-sm-6 text-right'>
                <div class="input-group input-group-sm">
                    <input type="search" class="form-control" placeholder="<?php echo $lang->get('find'); ?>" id="find-options">
                    <div class="input-group-append">
                        <div class="btn btn-primary" id="button-find-options">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                </div>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->


<!-- Main content -->
<div class='content'>
    <div class='container-fluid'>
        <div class='row'>
            <div class='col-md-6'>
                <div class='card card-primary'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo $lang->get('admin_settings_title'); ?></h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <form role='form-horizontal'>
                        <div class='card-body'>
                            <div class='form-group option' data-keywords="server setting">
                                <label for='cpassman_dir' class='col-sm-10 control-label'>
                                    <?php echo $lang->get('admin_misc_cpassman_dir'); ?>
                                </label>
                                <div class='col-sm-12'>
                                    <input type='text' class='form-control form-control-sm' id='cpassman_dir' value='<?php echo isset($SETTINGS['cpassman_dir']) === true ? htmlspecialchars($SETTINGS['cpassman_dir']) : ''; ?>'>
                                </div>
                            </div>

                            <div class='form-group option' data-keywords="server setting">
                                <label for='cpassman_url' class='col-sm-10 control-label'>
                                    <?php echo $lang->get('admin_misc_cpassman_url'); ?>
                                </label>
                                <div class='col-sm-12'>
                                    <input type='text' class='form-control form-control-sm' id='cpassman_url' value='<?php echo isset($SETTINGS['cpassman_url']) === true ? htmlspecialchars($SETTINGS['cpassman_url']) : ''; ?>'>
                                </div>
                            </div>

                            <div class='form-group option' data-keywords="server setting">
                                <label for='path_to_upload_folder' class='col-sm-10 control-label'>
                                    <?php echo $lang->get('admin_path_to_upload_folder'); ?>
                                </label>
                                <div class='col-sm-12'>
                                    <input type='text' class='form-control form-control-sm' id='path_to_upload_folder' value='<?php echo isset($SETTINGS['path_to_upload_folder']) === true ? htmlspecialchars($SETTINGS['path_to_upload_folder']) : ''; ?>'>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo $lang->get('admin_path_to_upload_folder_tip'); ?>
                                    </small>
                                </div>
                            </div>

                            <div class='form-group option' data-keywords="server setting">
                                <label for='path_to_files_folder' class='col-sm-10 control-label'>
                                    <?php echo $lang->get('admin_path_to_files_folder'); ?>
                                </label>
                                <div class='col-sm-12'>
                                    <input type='text' class='form-control form-control-sm' id='path_to_files_folder' value='<?php echo isset($SETTINGS['path_to_files_folder']) === true ? htmlspecialchars($SETTINGS['path_to_files_folder']) : ''; ?>'>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo $lang->get('admin_path_to_files_folder_tip'); ?>
                                    </small>
                                </div>
                            </div>

                            <div class='form-group option' data-keywords="server setting">
                                <label for='favicon' class='col-sm-10 control-label'>
                                    <?php echo $lang->get('admin_misc_favicon'); ?>
                                </label>
                                <div class='col-sm-12'>
                                    <input type='text' class='form-control form-control-sm' id='favicon' value='<?php echo isset($SETTINGS['favicon']) === true ? htmlspecialchars($SETTINGS['favicon']) : ''; ?>'>
                                </div>
                            </div>

                            <div class='form-group option' data-keywords="server setting">
                                <label for='custom_logo' class='col-sm-10 control-label'>
                                    <?php echo $lang->get('admin_misc_custom_logo'); ?>
                                </label>
                                <div class='col-sm-12'>
                                    <input type='text' class='form-control form-control-sm' id='custom_logo' value='<?php echo isset($SETTINGS['custom_logo']) === true ? htmlspecialchars($SETTINGS['custom_logo']) : ''; ?>'>
                                </div>
                            </div>

                            <div class='form-group option' data-keywords="server setting">
                                <label for='custom_login_text' class='col-sm-10 control-label'>
                                    <?php echo $lang->get('admin_misc_custom_login_text'); ?>
                                </label>
                                <div class='col-sm-12'>
                                    <input type='text' class='form-control form-control-sm' id='custom_login_text' value='<?php echo isset($SETTINGS['custom_login_text']) === true ? htmlspecialchars($SETTINGS['custom_login_text']) : ''; ?>'>
                                </div>
                            </div>
                        </div>
                        <!-- /.card-body -->
                    </form>
                </div>
                <!-- /.card -->

                <div class='card card-primary'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo $lang->get('admin_settings_title'); ?></h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <div class='card-body'>
                        <div class='row mb-2 option' data-keywords="setting maintenance mode">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_maintenance_mode'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='maintenance_mode' data-toggle-on='<?php echo isset($SETTINGS['maintenance_mode']) && (int) $SETTINGS['maintenance_mode'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='maintenance_mode_input' value='<?php echo isset($SETTINGS['maintenance_mode']) && (int) $SETTINGS['maintenance_mode'] === 1 ? '1' : '0'; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="server setting session expiration time">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_default_session_expiration_time'); ?>
                            </div>
                            <div class='col-2 mb-2'>
                                <input type='number' class='form-control form-control-sm' id='default_session_expiration_time' value='<?php echo htmlspecialchars($SETTINGS['default_session_expiration_time']) ?? '60'; ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="server setting session expiration time">
                            <div class='col-10'>
                                <?php echo $lang->get('maximum_session_expiration_time'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('maximum_session_expiration_time_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2 mb-2'>
                                <input type='number' class='form-control form-control-sm' id='maximum_session_expiration_time' value='<?php echo htmlspecialchars($SETTINGS['maximum_session_expiration_time']) ?? '60'; ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="server setting encryption client">
                            <div class='col-10'>
                                <?php echo $lang->get('encryptClientServer'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('encryptClientServer_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='encryptClientServer' data-toggle-on='<?php echo isset($SETTINGS['encryptClientServer']) && (int) $SETTINGS['encryptClientServer'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='encryptClientServer_input' value='<?php echo isset($SETTINGS['encryptClientServer']) && (int) $SETTINGS['encryptClientServer'] === 1 ? '1' : '0'; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="server setting ">
                            <div class='col-10'>
                                <?php echo $lang->get('enable_http_request_login'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='enable_http_request_login' data-toggle-on='<?php echo isset($SETTINGS['enable_http_request_login']) && (int) $SETTINGS['enable_http_request_login'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_http_request_login_input' value='<?php echo isset($SETTINGS['enable_http_request_login']) && (int) $SETTINGS['enable_http_request_login'] === 1 ? '1' : '0'; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="server setting strict hsts sts">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_enable_sts'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('settings_enable_sts_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='enable_sts' data-toggle-on='<?php echo isset($SETTINGS['enable_sts']) && (int) $SETTINGS['enable_sts'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_sts_input' value='<?php echo isset($SETTINGS['enable_sts']) && (int) $SETTINGS['enable_sts'] === 1 ? '1' : '0'; ?>' />
                            </div>
                        </div>
<!--
                        <div class='row mb-2 option' data-keywords="server setting proxy ip">
                            <div class='col-10'>
                                <?php echo $lang->get('admin_proxy_ip'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('admin_proxy_ip_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='text' class='form-control form-control-sm' id='proxy_ip' value='<?php echo htmlspecialchars($SETTINGS['proxy_ip']) ?? '60'; ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="server setting proxy port">
                            <div class='col-10'>
                                <?php echo $lang->get('admin_proxy_port'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('admin_proxy_port_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='proxy_port' value='<?php echo htmlspecialchars($SETTINGS['proxy_port']) ?? '60'; ?>'>
                            </div>
                        </div>
-->
                        <div class='row mb-2 option' data-keywords="user ui setting login length password">
                            <div class='col-10'>
                                <?php echo $lang->get('admin_pwd_maximum_length'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('admin_pwd_maximum_length_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='pwd_maximum_length' value='<?php echo htmlspecialchars($SETTINGS['pwd_maximum_length']) ?? '60'; ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="user ui setting login length password">
                            <div class='col-10'>
                                <?php echo $lang->get('password_length_by_default'); ?>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='pwd_default_length' value='<?php echo htmlspecialchars($SETTINGS['pwd_default_length']) ?? '14'; ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="user ui setting time date">
                            <div class='col-4'>
                                <?php echo $lang->get('timezone_selection'); ?>
                            </div>
                            <div class='col-8'>
                                <select class='form-control form-control-sm' id='timezone'>
                                    <option value=''>-- <?php echo $lang->get('select'); ?> --</option>
                                    <?php
                                    // get list of all timezones
                                    foreach ($zones as $key => $zone) {
                                        echo '
                                <option value="' . $key . '"', isset($SETTINGS['timezone']) === true && (int) $SETTINGS['timezone'] === $key ? ' selected' : '', '>' . $zone . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="user ui setting date format">
                            <div class='col-4'>
                                <?php echo $lang->get('date_format'); ?>
                            </div>
                            <div class='col-8'>
                                <select class='form-control form-control-sm' id='date_format'>
                                    <option value=''>-- <?php echo $lang->get('select'); ?> --</option>
                                    <option value="d/m/Y" <?php echo isset($SETTINGS['date_format']) === false || $SETTINGS['date_format'] === 'd/m/Y' ? ' selected' : ''; ?>>d/m/Y</option>
                                    <option value="m/d/Y" <?php echo $SETTINGS['date_format'] === 'm/d/Y' ? ' selected' : ''; ?>>m/d/Y</option>
                                    <option value="d-M-Y" <?php echo $SETTINGS['date_format'] === 'd-M-Y' ? ' selected' : ''; ?>>d-M-Y</option>
                                    <option value="d/m/y" <?php echo $SETTINGS['date_format'] === 'd/m/y' ? ' selected' : ''; ?>>d/m/y</option>
                                    <option value="m/d/y" <?php echo $SETTINGS['date_format'] === 'm/d/y' ? ' selected' : ''; ?>>m/d/y</option>
                                    <option value="d-M-y" <?php echo $SETTINGS['date_format'] === 'd-M-y' ? ' selected' : ''; ?>>d-M-y</option>
                                    <option value="d-m-y" <?php echo $SETTINGS['date_format'] === 'd-m-y' ? ' selected' : ''; ?>>d-m-y</option>
                                    <option value="Y-m-d" <?php echo $SETTINGS['date_format'] === 'Y-m-d' ? ' selected' : ''; ?>>Y-m-d</option>
                                </select>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="user ui setting time format">
                            <div class='col-4'>
                                <?php echo $lang->get('time_format'); ?>
                            </div>
                            <div class='col-8'>
                                <select class='form-control form-control-sm' id='time_format'>
                                    <option value=''>-- <?php echo $lang->get('select'); ?> --</option>
                                    <option value="H:i:s" <?php echo isset($SETTINGS['time_format']) === false || $SETTINGS['time_format'] === 'H:i:s' ? ' selected' : ''; ?>>H:i:s</option>
                                    <option value="H:i:s a" <?php echo $SETTINGS['time_format'] === 'H:i:s a' ? ' selected' : ''; ?>>H:i:s a</option>
                                    <option value="g:i:s a" <?php echo $SETTINGS['time_format'] === 'g:i:s a' ? ' selected' : ''; ?>>g:i:s a</option>
                                    <option value="G:i:s" <?php echo $SETTINGS['time_format'] === 'G:i:s' ? ' selected' : ''; ?>>G:i:s</option>
                                </select>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="user ui setting language">
                            <div class='col-8'>
                                <?php echo $lang->get('settings_default_language'); ?>
                            </div>
                            <div class='col-4'>
                                <select class='form-control form-control-sm' id='default_language'>
                                    <option value=''>-- <?php echo $lang->get('select'); ?> --</option>
                                    <?php
                                    $languagesList = $languagesList ?? [];
                                    foreach ($languagesList as $teampassLang) {
                                        echo '
                                <option value="' . $teampassLang . '"', isset($SETTINGS['default_language']) === true && (int) $SETTINGS['default_language'] === $teampassLang ? ' selected' : '', '>' . $teampassLang . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <!--
                    <div class='row mb-2 option'>
                        <div class='col-10'>
                            <?php echo $lang->get('number_of_used_pw'); ?>
                        </div>
                        <div class='col-2'>
                            <input type='text' class='form-control form-control-sm' id='number_of_used_pw' value='<?php echo htmlspecialchars($SETTINGS['number_of_used_pw']) ?? '5'; ?>'>
                        </div>
                    </div>
                    -->

                        <div class='row mb-2 option' data-keywords="user login password duration">
                            <div class='col-10'>
                                <?php echo $lang->get('pw_life_duration'); ?>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='pw_life_duration' value='<?php echo htmlspecialchars($SETTINGS['pw_life_duration']) ?? '5'; ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="log user login password security">
                            <div class='col-10'>
                                <?php echo $lang->get('nb_false_login_attempts'); ?>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='nb_bad_authentication' value='<?php echo htmlspecialchars($SETTINGS['nb_bad_authentication']) ?? '0'; ?>'>
                            </div>
                        </div>

                        <!--
                        <div class='row mb-2 option' data-keywords="log user login">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_log_connections'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='log_connections' data-toggle-on='<?php echo isset($SETTINGS['log_connections']) === true && (int) $SETTINGS['log_connections'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='log_connections_input' value='<?php echo isset($SETTINGS['log_connections']) && (int) $SETTINGS['log_connections'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>
                        -->

                        <div class='row mb-2 option' data-keywords="log item password log security">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_log_accessed'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='log_accessed' data-toggle-on='<?php echo isset($SETTINGS['log_accessed']) === true && (int) $SETTINGS['log_accessed'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='log_accessed_input' value='<?php echo isset($SETTINGS['log_accessed']) && (int) $SETTINGS['log_accessed'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="folder personal user">
                            <div class='col-10'>
                                <?php echo $lang->get('enable_personal_folder_feature'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('enable_personal_folder_feature_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='enable_pf_feature' data-toggle-on='<?php echo isset($SETTINGS['enable_pf_feature']) === true && (int) $SETTINGS['enable_pf_feature'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_pf_feature_input' value='<?php echo isset($SETTINGS['enable_pf_feature']) && (int) $SETTINGS['enable_pf_feature'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <!--
                  <div class='row mb-2 option'>
                      <div class='col-10'>
                          <?php echo $lang->get('enable_personal_saltkey_cookie'); ?>
                      </div>
                      <div class='col-2'>
                          <div class='toggle toggle-modern' id='enable_personal_saltkey_cookie' data-toggle-on='<?php echo isset($SETTINGS['enable_personal_saltkey_cookie']) === true && (int) $SETTINGS['enable_personal_saltkey_cookie'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_personal_saltkey_cookie_input' value='<?php echo isset($SETTINGS['enable_personal_saltkey_cookie']) && (int) $SETTINGS['enable_personal_saltkey_cookie'] === 1 ? 1 : 0; ?>' />
                      </div>
                  </div>

                  <div class='row mb-2 option'>
                      <div class='col-10'>
                          <?php echo $lang->get('personal_saltkey_cookie_duration'); ?>
                      </div>
                      <div class='col-2'>
                      <input type='text' class='form-control form-control-sm' id='personal_saltkey_cookie_duration' value='<?php echo htmlspecialchars($SETTINGS['personal_saltkey_cookie_duration']) ?? '31'; ?>'>
                      </div>
                  </div>

                    <div class='row mb-2 option'>
                        <div class='col-8'>
                            <?php echo $lang->get('personal_saltkey_security_level'); ?>
                        </div>
                        <div class='col-4'>
                            <select class='form-control form-control-sm' id='personal_saltkey_security_level'>
                                <option value=''>-- <?php echo $lang->get('select'); ?> --</option>
                                <?php
                                foreach (TP_PW_COMPLEXITY as $complex) {
                                    echo '
                                <option value="' . $complex[0] . '"', isset($SETTINGS['personal_saltkey_security_level']) === true && (int) $SETTINGS['personal_saltkey_security_level'] === $complex[0] ? ' selected' : '', '>' . $complex[1] . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                  <div class='row mb-2 option'>
                      <div class='col-10'>
                          <?php echo $lang->get('settings_attachments_encryption'); ?>
                          <small id='passwordHelpBlock' class='form-text text-muted'>
                              <?php echo $lang->get('settings_attachments_encryption_tip'); ?>
                          </small>
                      </div>
                      <div class='col-2'>
                          <div class='toggle toggle-modern' id='enable_attachment_encryption' data-toggle-on='<?php echo isset($SETTINGS['enable_attachment_encryption']) === true && (int) $SETTINGS['enable_attachment_encryption'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_attachment_encryption_input' value='<?php echo isset($SETTINGS['enable_attachment_encryption']) && (int) $SETTINGS['enable_attachment_encryption'] === 1 ? 1 : 0; ?>' />
                      </div>
                  </div>
                  -->

                        <div class='row mb-2 option' data-keywords="image">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_secure_display_image'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('settings_secure_display_image_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='secure_display_image' data-toggle-on='<?php echo isset($SETTINGS['secure_display_image']) === true && (int) $SETTINGS['secure_display_image'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='secure_display_image_input' value='<?php echo isset($SETTINGS['secure_display_image']) && (int) $SETTINGS['secure_display_image'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="option">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_kb'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('settings_kb_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='enable_kb' data-toggle-on='<?php echo isset($SETTINGS['enable_kb']) === true && (int) $SETTINGS['enable_kb'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_kb_input' value='<?php echo isset($SETTINGS['enable_kb']) && (int) $SETTINGS['enable_kb'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="option">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_suggestion'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('settings_suggestion_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='enable_suggestion' data-toggle-on='<?php echo isset($SETTINGS['enable_suggestion']) === true && (int) $SETTINGS['enable_suggestion'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_suggestion_input' value='<?php echo isset($SETTINGS['enable_suggestion']) && (int) $SETTINGS['enable_suggestion'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="display">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_get_tp_info'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('settings_get_tp_info_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='get_tp_info' data-toggle-on='<?php echo isset($SETTINGS['get_tp_info']) === true && (int) $SETTINGS['get_tp_info'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='get_tp_info_input' value='<?php echo isset($SETTINGS['get_tp_info']) && (int) $SETTINGS['get_tp_info'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            

                <div class='card card-primary'>
                        <div class='card-header'>
                            <h3 class='card-title'><?php echo $lang->get('otv_link'); ?></h3>
                        </div>
                        <!-- /.card-header -->
                        <!-- form start -->
                        <div class='card-body'>
                            <div class='row mb-2 option' data-keywords="one time link">
                                <div class='col-10'>
                                    <?php echo $lang->get('otv_is_enabled'); ?>
                                </div>
                                <div class='col-2'>
                                    <div class='toggle toggle-modern' id='otv_is_enabled' data-toggle-on='<?php echo isset($SETTINGS['otv_is_enabled']) === true && (int) $SETTINGS['otv_is_enabled'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='otv_is_enabled_input' value='<?php echo isset($SETTINGS['otv_is_enabled']) && (int) $SETTINGS['otv_is_enabled'] === 1 ? 1 : 0; ?>' />
                                </div>
                            </div>

                            <div class='row mb-2 option' data-keywords="one time period expiration link">
                                <div class='col-10'>
                                    <?php echo $lang->get('settings_otv_expiration_period'); ?>
                                </div>
                                <div class='col-2'>
                                    <input type='number' class='form-control form-control-sm' id='otv_expiration_period' value='<?php echo htmlspecialchars($SETTINGS['otv_expiration_period']) ?? '7'; ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 option' data-keywords="one time subdomain link">
                                <div class='col-12'>
                                    <?php echo $lang->get('settings_otv_subdomain'); ?>
                                    <small class='form-text text-muted'>
                                        <?php echo $lang->get('settings_otv_subdomain_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-sm-12'>
                                    <input type='text' class='form-control form-control-sm' id='otv_subdomain' value='<?php echo isset($SETTINGS['otv_subdomain']) === true ? htmlspecialchars($SETTINGS['otv_subdomain']) : ''; ?>'>
                                </div>
                            </div>
                        </div>
                    </div>

            </div>
            <!-- /.col-md-6 -->
            <!--/.col (left) -->
            <!-- right column -->
            <div class='col-md-6'>
                <!-- Horizontal Form -->
                <div class='card card-info'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo $lang->get('admin_settings_title'); ?></h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- card-body -->
                    <div class='card-body'>
                        <div class='row mb-2 option' data-keywords="item edit">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_delay_for_item_edition'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('settings_delay_for_item_edition_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='delay_item_edition' value='<?php echo $SETTINGS['delay_item_edition'] ?? '9'; ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="right manager item">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_manager_edit'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='manager_edit' data-toggle-on='<?php echo isset($SETTINGS['manager_edit']) === true && (int) $SETTINGS['manager_edit'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='manager_edit_input' value='<?php echo isset($SETTINGS['manager_edit']) && (int) $SETTINGS['manager_edit'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="right manager move item">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_manager_move_item'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='manager_move_item' data-toggle-on='<?php echo isset($SETTINGS['manager_move_item']) === true && (int) $SETTINGS['manager_move_item'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='manager_move_item_input' value='<?php echo isset($SETTINGS['manager_move_item']) && (int) $SETTINGS['manager_move_item'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="password last">
                            <div class='col-10'>
                                <?php echo $lang->get('max_last_items'); ?>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='max_last_items' value='<?php echo isset($SETTINGS['max_last_items']) === true ? $SETTINGS['max_last_items'] : '7'; ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="create duplicate folder">
                            <div class='col-10'>
                                <?php echo $lang->get('duplicate_folder'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='duplicate_folder' data-toggle-on='<?php echo isset($SETTINGS['duplicate_folder']) === true && (int) $SETTINGS['duplicate_folder'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='duplicate_folder_input' value='<?php echo isset($SETTINGS['duplicate_folder']) && (int) $SETTINGS['duplicate_folder'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="password duplicate">
                            <div class='col-10'>
                                <?php echo $lang->get('duplicate_item'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='duplicate_item' data-toggle-on='<?php echo isset($SETTINGS['duplicate_item']) === true && (int) $SETTINGS['duplicate_item'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='duplicate_item_input' value='<?php echo isset($SETTINGS['duplicate_item']) && (int) $SETTINGS['duplicate_item'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="password duplicate folder">
                            <div class='col-10'>
                                <?php echo $lang->get('duplicate_item_in_folder'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='item_duplicate_in_same_folder' data-toggle-on='<?php echo isset($SETTINGS['item_duplicate_in_same_folder']) === true && (int) $SETTINGS['item_duplicate_in_same_folder'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='item_duplicate_in_same_folder_input' value='<?php echo isset($SETTINGS['item_duplicate_in_same_folder']) && (int) $SETTINGS['item_duplicate_in_same_folder'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="folder display optimization hide">
                            <div class='col-10'>
                                <?php echo $lang->get('show_only_accessible_folders'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('show_only_accessible_folders_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='show_only_accessible_folders' data-toggle-on='<?php echo isset($SETTINGS['show_only_accessible_folders']) === true && (int) $SETTINGS['show_only_accessible_folders'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='show_only_accessible_folders_input' value='<?php echo isset($SETTINGS['show_only_accessible_folders']) && (int) $SETTINGS['show_only_accessible_folders'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="folder creation">
                            <div class='col-10'>
                                <?php echo $lang->get('subfolder_rights_as_parent'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('subfolder_rights_as_parent_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='subfolder_rights_as_parent' data-toggle-on='<?php echo isset($SETTINGS['subfolder_rights_as_parent']) === true && (int) $SETTINGS['subfolder_rights_as_parent'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='subfolder_rights_as_parent_input' value='<?php echo isset($SETTINGS['subfolder_rights_as_parent']) && (int) $SETTINGS['subfolder_rights_as_parent'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="password creation">
                            <div class='col-10'>
                                <?php echo $lang->get('create_item_without_password'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='create_item_without_password' data-toggle-on='<?php echo isset($SETTINGS['create_item_without_password']) === true && (int) $SETTINGS['create_item_without_password'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='create_item_without_password_input' value='<?php echo isset($SETTINGS['create_item_without_password']) && (int) $SETTINGS['create_item_without_password'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="profile">
                            <div class='col-10'>
                                <?php echo $lang->get('disable_user_edit_profile'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='disable_user_edit_profile' data-toggle-on='<?php echo isset($SETTINGS['disable_user_edit_profile']) === true && (int) $SETTINGS['disable_user_edit_profile'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='disable_user_edit_profile_input' value='<?php echo isset($SETTINGS['disable_user_edit_profile']) && (int) $SETTINGS['disable_user_edit_profile'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="language lang">
                            <div class='col-10'>
                                <?php echo $lang->get('disable_user_edit_language'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='disable_user_edit_language' data-toggle-on='<?php echo isset($SETTINGS['disable_user_edit_language']) === true && (int) $SETTINGS['disable_user_edit_language'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='disable_user_edit_language_input' value='<?php echo isset($SETTINGS['disable_user_edit_language']) && (int) $SETTINGS['disable_user_edit_language'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="timezone">
                            <div class='col-10'>
                                <?php echo $lang->get('disable_user_edit_timezone'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='disable_user_edit_timezone' data-toggle-on='<?php echo isset($SETTINGS['disable_user_edit_timezone']) === true && (int) $SETTINGS['disable_user_edit_timezone'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='disable_user_edit_timezone_input' value='<?php echo isset($SETTINGS['disable_user_edit_timezone']) && (int) $SETTINGS['disable_user_edit_timezone'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="tree load strategy">
                            <div class='col-10'>
                                <?php echo $lang->get('disable_drag_drop'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='disable_drag_drop' data-toggle-on='<?php echo isset($SETTINGS['disable_drag_drop']) === true && (int) $SETTINGS['disable_drag_drop'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='disable_drag_drop_input' value='<?php echo isset($SETTINGS['disable_drag_drop']) && (int) $SETTINGS['disable_drag_drop'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="tree load strategy">
                            <div class='col-10'>
                                <?php echo $lang->get('disable_user_edit_tree_load_strategy'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='disable_user_edit_tree_load_strategy' data-toggle-on='<?php echo isset($SETTINGS['disable_user_edit_tree_load_strategy']) === true && (int) $SETTINGS['disable_user_edit_tree_load_strategy'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='disable_user_edit_tree_load_strategy_input' value='<?php echo isset($SETTINGS['disable_user_edit_tree_load_strategy']) && (int) $SETTINGS['disable_user_edit_tree_load_strategy'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="favorite">
                            <div class='col-10'>
                                <?php echo $lang->get('enable_favourites'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='enable_favourites' data-toggle-on='<?php echo isset($SETTINGS['enable_favourites']) === true && (int) $SETTINGS['enable_favourites'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_favourites_input' value='<?php echo isset($SETTINGS['enable_favourites']) && (int) $SETTINGS['enable_favourites'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="folder creation">
                            <div class='col-10'>
                                <?php echo $lang->get('enable_user_can_create_folders'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='enable_user_can_create_folders' data-toggle-on='<?php echo isset($SETTINGS['enable_user_can_create_folders']) === true && (int) $SETTINGS['enable_user_can_create_folders'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_user_can_create_folders_input' value='<?php echo isset($SETTINGS['enable_user_can_create_folders']) && (int) $SETTINGS['enable_user_can_create_folders'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="folder creation">
                            <div class='col-10'>
                                <?php echo $lang->get('can_create_root_folder'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='can_create_root_folder' data-toggle-on='<?php echo isset($SETTINGS['can_create_root_folder']) === true && (int) $SETTINGS['can_create_root_folder'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='can_create_root_folder_input' value='<?php echo isset($SETTINGS['can_create_root_folder']) && (int) $SETTINGS['can_create_root_folder'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="password delete massive">
                            <div class='col-10'>
                                <?php echo $lang->get('enable_massive_move_delete'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('enable_massive_move_delete_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='enable_massive_move_delete' data-toggle-on='<?php echo isset($SETTINGS['enable_massive_move_delete']) === true && (int) $SETTINGS['enable_massive_move_delete'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_massive_move_delete_input' value='<?php echo isset($SETTINGS['enable_massive_move_delete']) && (int) $SETTINGS['enable_massive_move_delete'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="password display">
                            <div class='col-10'>
                                <?php echo $lang->get('password_overview_delay'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('password_overview_delay_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='password_overview_delay' value='<?php echo isset($SETTINGS['password_overview_delay']) === true ? htmlspecialchars($SETTINGS['password_overview_delay']) : '4'; ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="password delete view expiration">
                            <div class='col-10'>
                                <?php echo $lang->get('admin_setting_activate_expiration'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('admin_setting_activate_expiration_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='activate_expiration' data-toggle-on='<?php echo isset($SETTINGS['activate_expiration']) === true && (int) $SETTINGS['activate_expiration'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='activate_expiration_input' value='<?php echo isset($SETTINGS['activate_expiration']) && (int) $SETTINGS['activate_expiration'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="password delete view expiration">
                            <div class='col-10'>
                                <?php echo $lang->get('admin_setting_enable_delete_after_consultation'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('admin_setting_enable_delete_after_consultation_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='enable_delete_after_consultation' data-toggle-on='<?php echo isset($SETTINGS['enable_delete_after_consultation']) === true && (int) $SETTINGS['enable_delete_after_consultation'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_delete_after_consultation_input' value='<?php echo isset($SETTINGS['enable_delete_after_consultation']) && (int) $SETTINGS['enable_delete_after_consultation'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="export print">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_printing'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('settings_printing_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='allow_print' data-toggle-on='<?php echo isset($SETTINGS['allow_print']) === true && (int) $SETTINGS['allow_print'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='allow_print_input' value='<?php echo isset($SETTINGS['allow_print']) && (int) $SETTINGS['allow_print'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="export print">
                            <div class='col-6'>
                                <?php echo $lang->get('settings_roles_allowed_to_print'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('settings_roles_allowed_to_print_tip'); ?>
                                </small>
                            </div>
                            <div class='col-6'>
                                <select class='form-control form-control-sm select2 disabled' id='roles_allowed_to_print_select' onchange='' multiple="multiple" style="width:100%;">
                                    <?php
                                    // Get selected groups
                                    if (isset($SETTINGS['allow_print']) === true) {
                                        $arrRolesToPrint = json_decode($SETTINGS['roles_allowed_to_print_select'], true);
                                        if ($arrRolesToPrint === 0 || empty($arrRolesToPrint) === true) {
                                            $arrRolesToPrint = [];
                                        }
                                        // Get full list
                                        $roles = getRolesTitles();
                                        foreach ($roles as $role) {
                                            echo '
                                    <option value="' . htmlspecialchars($role['id']) . '"', in_array($role['id'], $arrRolesToPrint) === true ? ' selected' : '', '>' . htmlspecialchars(addslashes($role['title'])) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="import">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_importing'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='allow_import' data-toggle-on='<?php echo isset($SETTINGS['allow_import']) === true && (int) $SETTINGS['allow_import'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='allow_import_input' value='<?php echo isset($SETTINGS['allow_import']) && (int) $SETTINGS['allow_import'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="role restriction modify right">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_anyone_can_modify'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('settings_anyone_can_modify_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='anyone_can_modify' data-toggle-on='<?php echo isset($SETTINGS['anyone_can_modify']) === true && (int) $SETTINGS['anyone_can_modify'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='anyone_can_modify_input' value='<?php echo isset($SETTINGS['anyone_can_modify']) && (int) $SETTINGS['anyone_can_modify'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option <?php echo isset($SETTINGS['anyone_can_modify']) === true && (int) $SETTINGS['anyone_can_modify'] === '1' ? '' : 'hidden'; ?>' id="form-item-row-modify" data-keywords="role restriction modify right">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_anyone_can_modify_bydefault'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='anyone_can_modify_bydefault' data-toggle-on='<?php echo isset($SETTINGS['anyone_can_modify_bydefault']) === true && (int) $SETTINGS['anyone_can_modify_bydefault'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='anyone_can_modify_bydefault_input' value='<?php echo isset($SETTINGS['anyone_can_modify_bydefault']) && (int) $SETTINGS['anyone_can_modify_bydefault'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="role restriction">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_restricted_to'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='restricted_to' data-toggle-on='<?php echo isset($SETTINGS['restricted_to']) === true && (int) $SETTINGS['restricted_to'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='restricted_to_input' value='<?php echo isset($SETTINGS['restricted_to']) && (int) $SETTINGS['restricted_to'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option <?php echo isset($SETTINGS['restricted_to']) === true && (int) $SETTINGS['restricted_to'] === '1' ? '' : 'hidden'; ?>' id="form-item-row-restricted" data-keywords="role restriction">
                            <div class='col-10'>
                                <?php echo $lang->get('restricted_to_roles'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='restricted_to_roles' data-toggle-on='<?php echo isset($SETTINGS['restricted_to_roles']) === true && (int) $SETTINGS['restricted_to_roles'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='restricted_to_roles_input' value='<?php echo isset($SETTINGS['restricted_to_roles']) && (int) $SETTINGS['restricted_to_roles'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="display optimization icon">
                            <div class='col-10'>
                                <?php echo $lang->get('copy_to_clipboard_small_icons'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('copy_to_clipboard_small_icons_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='copy_to_clipboard_small_icons' data-toggle-on='<?php echo isset($SETTINGS['copy_to_clipboard_small_icons']) === true && (int) $SETTINGS['copy_to_clipboard_small_icons'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='copy_to_clipboard_small_icons_input' value='<?php echo isset($SETTINGS['copy_to_clipboard_small_icons']) && (int) $SETTINGS['copy_to_clipboard_small_icons'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="display optimization icon clipboard">
                            <div class='col-10'>
                                <?php echo $lang->get('clipboard_password_life_duration'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('clipboard_password_life_duration_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='clipboard_life_duration' value='<?php echo isset($SETTINGS['clipboard_life_duration']) === true ? htmlspecialchars($SETTINGS['clipboard_life_duration']) : '30'; ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="display tree counter">
                            <div class='col-10'>
                                <?php echo $lang->get('show_item_data'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('show_item_data_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='show_item_data' data-toggle-on='<?php echo isset($SETTINGS['show_item_data']) === true && (int) $SETTINGS['show_item_data'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='show_item_data_input' value='<?php echo isset($SETTINGS['show_item_data']) && (int) $SETTINGS['show_item_data'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>


                        <div class='row mb-2 option' data-keywords="display optimization description">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_show_description'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='show_description' data-toggle-on='<?php echo isset($SETTINGS['show_description']) === true && (int) $SETTINGS['show_description'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='show_description_input' value='<?php echo isset($SETTINGS['show_description']) && (int) $SETTINGS['show_description'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>
<?php
if (isset($SETTINGS['show_description']) === true && (int) $SETTINGS['show_description'] === '1') {
    ?>
                        <div class='row mb-2 option' data-keywords="display tree counter">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_tree_counters'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('settings_tree_counters_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='tree_counters' data-toggle-on='<?php echo isset($SETTINGS['tree_counters']) === true && (int) $SETTINGS['tree_counters'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='tree_counters_input' value='<?php echo isset($SETTINGS['tree_counters']) && (int) $SETTINGS['tree_counters'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>
<?php
}
?>

                        <div class='row mb-2 option' data-keywords="query display optimization">
                            <div class='col-10'>
                                <?php echo $lang->get('limited_search_default'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('limited_search_default_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='limited_search_default' data-toggle-on='<?php echo isset($SETTINGS['limited_search_default']) === true && (int) $SETTINGS['limited_search_default'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='limited_search_default_input' value='<?php echo isset($SETTINGS['limited_search_default']) && (int) $SETTINGS['limited_search_default'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="query display optimization">
                            <div class='col-10'>
                                <?php echo $lang->get('highlight_selected'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('highlight_selected_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='highlight_selected' data-toggle-on='<?php echo isset($SETTINGS['highlight_selected']) === true && (int) $SETTINGS['highlight_selected'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='highlight_selected_input' value='<?php echo isset($SETTINGS['highlight_selected']) && (int) $SETTINGS['highlight_selected'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="query display optimization">
                            <div class='col-10'>
                                <?php echo $lang->get('highlight_favorites'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('highlight_favorites_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='highlight_favorites' data-toggle-on='<?php echo isset($SETTINGS['highlight_favorites']) === true && (int) $SETTINGS['highlight_favorites'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='highlight_favorites_input' value='<?php echo isset($SETTINGS['highlight_favorites']) && (int) $SETTINGS['highlight_favorites'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="query display optimization">
                            <div class='col-10'>
                                <?php echo $lang->get('nb_items_by_query'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('nb_items_by_query_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='text' class='form-control form-control-sm' id='nb_items_by_query' value='<?php echo isset($SETTINGS['nb_items_by_query']) === true ? htmlspecialchars($SETTINGS['nb_items_by_query']) : ''; ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="email notification login">
                            <div class='col-10'>
                                <?php echo $lang->get('enable_send_email_on_user_login'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='enable_send_email_on_user_login' data-toggle-on='<?php echo isset($SETTINGS['enable_send_email_on_user_login']) === true && (int) $SETTINGS['enable_send_email_on_user_login'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_send_email_on_user_login_input' value='<?php echo isset($SETTINGS['enable_send_email_on_user_login']) && (int) $SETTINGS['enable_send_email_on_user_login'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="email notification">
                            <div class='col-10'>
                                <?php echo $lang->get('enable_email_notification_on_item_shown'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='enable_email_notification_on_item_shown' data-toggle-on='<?php echo isset($SETTINGS['enable_email_notification_on_item_shown']) === true && (int) $SETTINGS['enable_email_notification_on_item_shown'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_email_notification_on_item_shown_input' value='<?php echo isset($SETTINGS['enable_email_notification_on_item_shown']) && (int) $SETTINGS['enable_email_notification_on_item_shown'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="email notification password change">
                            <div class='col-10'>
                                <?php echo $lang->get('enable_email_notification_on_user_pw_change'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='enable_email_notification_on_user_pw_change' data-toggle-on='<?php echo isset($SETTINGS['enable_email_notification_on_user_pw_change']) === true && (int) $SETTINGS['enable_email_notification_on_user_pw_change'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_email_notification_on_user_pw_change_input' value='<?php echo isset($SETTINGS['enable_email_notification_on_user_pw_change']) && (int) $SETTINGS['enable_email_notification_on_user_pw_change'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="history manual">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_insert_manual_entry_item_history'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('settings_insert_manual_entry_item_history_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='insert_manual_entry_item_history' data-toggle-on='<?php echo isset($SETTINGS['insert_manual_entry_item_history']) === true && (int) $SETTINGS['insert_manual_entry_item_history'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='insert_manual_entry_item_history_input' value='<?php echo isset($SETTINGS['insert_manual_entry_item_history']) && (int) $SETTINGS['insert_manual_entry_item_history'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="offline export">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_offline_mode'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('settings_offline_mode_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='settings_offline_mode' data-toggle-on='<?php echo isset($SETTINGS['settings_offline_mode']) === true && (int) $SETTINGS['settings_offline_mode'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='settings_offline_mode_input' value='<?php echo isset($SETTINGS['settings_offline_mode']) && (int) $SETTINGS['settings_offline_mode'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="offline">
                            <div class='col-7'>
                                <?php echo $lang->get('offline_mode_key_level'); ?>
                            </div>
                            <div class='col-5'>
                                <select class='form-control form-control-sm' id='offline_key_level'>
                                    <?php
                                    foreach (TP_PW_COMPLEXITY as $complex) {
                                        echo '
                                <option value="' . $complex[0] . '"', isset($SETTINGS['offline_key_level']) === true && (int) $SETTINGS['offline_key_level'] === $complex[0] ? ' selected' : '', '>' . $complex[1] . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="syslog">
                            <div class='col-10'>
                                <?php echo $lang->get('syslog_enable'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='syslog_enable' data-toggle-on='<?php echo isset($SETTINGS['syslog_enable']) === true && (int) $SETTINGS['syslog_enable'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='syslog_enable_input' value='<?php echo isset($SETTINGS['syslog_enable']) && (int) $SETTINGS['syslog_enable'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="syslog">
                            <div class='col-7'>
                                <?php echo $lang->get('syslog_host'); ?>
                            </div>
                            <div class='col-5'>
                                <input type='text' class='form-control form-control-sm' id='syslog_host' value='<?php echo isset($SETTINGS['syslog_host']) === true ? htmlspecialchars($SETTINGS['syslog_host']) : ''; ?>'>
                            </div>
                        </div>

                        <div class='row mb-5 option' data-keywords="syslog port">
                            <div class='col-10'>
                                <?php echo $lang->get('syslog_port'); ?>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='syslog_port' value='<?php echo isset($SETTINGS['syslog_port']) === true ? htmlspecialchars($SETTINGS['syslog_port']) : ''; ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="password server">
                            <div class='col-10'>
                                <?php echo $lang->get('server_password_change_enable'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('server_password_change_enable_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern disabled' id='enable_server_password_change' data-toggle-on='<?php echo isset($SETTINGS['enable_server_password_change']) === true && (int) $SETTINGS['enable_server_password_change'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_server_password_change_input' value='<?php echo isset($SETTINGS['enable_server_password_change']) && (int) $SETTINGS['enable_server_password_change'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!--/.col (right) -->
        </div>
        <!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content -->

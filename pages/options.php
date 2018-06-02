<?php
/**
 * Teampass - a collaborative passwords manager
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 * @package   Login.php
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2018 Nils Laumaillé
* @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * @version   GIT: <git_id>
 * @link      http://www.teampass.net
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
    throw new Exception('Error file "/includes/config/tp.config.php" not exists', 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'admin') === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

// Load template
require_once $SETTINGS['cpassman_dir'].'/template.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

?>

    <!-- Content Header (Page header) -->
    <div class='content-header'>
      <div class='container-fluid'>
        <div class='row mb-2'>
          <div class='col-sm-6'>
            <h1 class='m-0 text-dark'><?php echo langHdl('options');?></h1>
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
                <h3 class='card-title'><?php echo langHdl('admin_settings_title');?></h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
              <form role='form-horizontal'>
                <div class='card-body'>
                  <div class='form-group'>
                    <label for='cpassman_dir' class='col-sm-10 control-label'>
                        <?php echo langHdl('admin_misc_cpassman_dir');?>
                    </label>
                    <div class='col-sm-12'>
                      <input type='text' class='form-control' id='cpassman_dir'
                          value='<?php echo isset($SETTINGS['cpassman_dir']) === true ? $SETTINGS['cpassman_dir'] : '
                          ';?>'
                          onchange='updateSetting($(this).attr('id'));'>
                    </div>
                  </div>

                  <div class='form-group'>
                    <label for='cpassman_url' class='col-sm-10 control-label'>
                        <?php echo langHdl('admin_misc_cpassman_url');?>
                    </label>
                    <div class='col-sm-12'>
                      <input type='text' class='form-control' id='cpassman_url'
                          value='<?php echo isset($SETTINGS['cpassman_url']) === true ? $SETTINGS['cpassman_url'] : '';?>'
                          onchange='updateSetting($(this).attr('id'));'>
                    </div>
                  </div>

                  <div class='form-group'>
                    <label for='path_to_upload_folder' class='col-sm-10 control-label'>
                        <?php echo langHdl('admin_path_to_upload_folder');?>
                    </label>
                    <div class='col-sm-12'>
                        <input type='text' class='form-control' id='path_to_upload_folder'
                            value='<?php echo isset($SETTINGS['path_to_upload_folder']) === true ? $SETTINGS['path_to_upload_folder'] : '';?>'
                            onchange='updateSetting($(this).attr('id'));'>
                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                <?php echo langHdl('admin_path_to_upload_folder_tip');?>
                            </small>
                    </div>
                  </div>

                  <div class='form-group'>
                    <label for='url_to_upload_folder' class='col-sm-10 control-label'>
                        <?php echo langHdl('admin_url_to_upload_folder');?>
                    </label>
                    <div class='col-sm-12'>
                      <input type='text' class='form-control' id='url_to_upload_folder'
                          value='<?php echo isset($SETTINGS['url_to_upload_folder']) === true ? $SETTINGS['url_to_upload_folder'] : '';?>'
                          onchange='updateSetting($(this).attr('id'));'>
                    </div>
                  </div>

                  <div class='form-group'>
                    <label for='path_to_files_folder' class='col-sm-10 control-label'>
                        <?php echo langHdl('admin_path_to_files_folder');?>
                    </label>
                    <div class='col-sm-12'>
                        <input type='text' class='form-control' id='path_to_files_folder'
                            value='<?php echo isset($SETTINGS['path_to_files_folder']) === true ? $SETTINGS['path_to_files_folder'] : '';?>'
                            onchange='updateSetting($(this).attr('id'));'>
                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                <?php echo langHdl('admin_path_to_files_folder_tip');?>
                            </small>
                    </div>
                  </div>

                  <div class='form-group'>
                    <label for='url_to_files_folder' class='col-sm-10 control-label'>
                        <?php echo langHdl('admin_url_to_files_folder');?>
                    </label>
                    <div class='col-sm-12'>
                      <input type='text' class='form-control' id='url_to_files_folder'
                          value='<?php echo isset($SETTINGS['url_to_files_folder']) === true ? $SETTINGS['url_to_files_folder'] : '';?>'
                          onchange='updateSetting($(this).attr('id'));'>
                    </div>
                  </div>

                  <div class='form-group'>
                    <label for='favicon' class='col-sm-10 control-label'>
                        <?php echo langHdl('admin_misc_favicon');?>
                    </label>
                    <div class='col-sm-12'>
                      <input type='text' class='form-control' id='favicon'
                          value='<?php echo isset($SETTINGS['favicon']) === true ? $SETTINGS['favicon'] : '';?>'
                          onchange='updateSetting($(this).attr('id'));'>
                    </div>
                  </div>

                  <div class='form-group'>
                    <label for='custom_logo' class='col-sm-10 control-label'>
                        <?php echo langHdl('admin_misc_custom_logo');?>
                    </label>
                    <div class='col-sm-12'>
                      <input type='text' class='form-control' id='custom_logo'
                          value='<?php echo isset($SETTINGS['custom_logo']) === true ? $SETTINGS['custom_logo'] : '';?>'
                          onchange='updateSetting($(this).attr('id'));'>
                    </div>
                  </div>

                  <div class='form-group'>
                    <label for='custom_login_text' class='col-sm-10 control-label'>
                        <?php echo langHdl('admin_misc_custom_login_text');?>
                    </label>
                    <div class='col-sm-12'>
                      <input type='text' class='form-control' id='custom_login_text'
                          value='<?php echo isset($SETTINGS['custom_login_text']) === true ? $SETTINGS['custom_login_text'] : '';?>'
                          onchange='updateSetting($(this).attr('id'));'>
                    </div>
                  </div>
                </div>
                <!-- /.card-body -->
              </form>
            </div>
            <!-- /.card -->

            <div class='card card-primary'>
              <div class='card-header'>
                <h3 class='card-title'><?php echo langHdl('admin_settings_title');?></h3>
              </div>
              <!-- /.card-header -->
              <!-- form start -->
                <div class='card-body'>
                  <div class='row mb-2'>
                      <div class='col-10'>
                          <?php echo langHdl('settings_maintenance_mode');?>
                      </div>
                      <div class='col-2'>
                          <div class='toggle toggle-modern' id='maintenance_mode' data-toggle-on='<?php echo isset($SETTINGS['maintenance_mode']) && $SETTINGS['maintenance_mode'] == 1 ? 'true' : 'false';?>'></div><input type='hidden' id='maintenance_mode_input' value='<?php echo isset($SETTINGS['maintenance_mode']) && $SETTINGS['maintenance_mode'] == 1 ? '1' : '0';?>' />
                      </div>
                  </div>

                  <div class='row mb-2'>
                      <div class='col-10'>
                          <?php echo langHdl('settings_default_session_expiration_time');?>
                      </div>
                      <div class='col-2 mb-2'>
                          <input type='text' class='form-control form-control-sm' id='default_session_expiration_time' value='<?php echo isset($SETTINGS['default_session_expiration_time']) ? $SETTINGS['default_session_expiration_time'] : '60';?>' onchange='updateSetting($(this).attr("id"), $(this).val());' />
                      </div>
                  </div>

                  <div class='row mb-2'>
                      <div class='col-10'>
                          <?php echo langHdl('enable_http_request_login');?>
                      </div>
                      <div class='col-2'>
                          <div class='toggle toggle-modern' id='enable_http_request_login' data-toggle-on='<?php echo isset($SETTINGS['enable_http_request_login']) && $SETTINGS['enable_http_request_login'] == 1 ? 'true' : 'false';?>'></div><input type='hidden' id='enable_http_request_login_input' value='<?php echo isset($SETTINGS['enable_http_request_login']) && $SETTINGS['enable_http_request_login'] == 1 ? '1' : '0';?>' />
                      </div>
                  </div>

                  <div class='row mb-2'>
                      <div class='col-10'>
                          <?php echo langHdl('settings_enable_sts');?>
                          <small id='passwordHelpBlock' class='form-text text-muted'>
                              <?php echo langHdl('settings_enable_sts_tip');?>
                          </small>
                      </div>
                      <div class='col-2'>
                          <div class='toggle toggle-modern' id='enable_sts' data-toggle-on='<?php echo isset($SETTINGS['enable_sts']) && $SETTINGS['enable_sts'] == 1 ? 'true' : 'false';?>'></div><input type='hidden' id='enable_sts_input' value='<?php echo isset($SETTINGS['enable_sts']) && $SETTINGS['enable_sts'] == 1 ? '1' : '0';?>' />
                      </div>
                  </div>

                  <div class='row mb-2'>
                        <div class='col-10'>
                            <?php echo langHdl('admin_proxy_ip');?>
                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                <?php echo langHdl('admin_proxy_ip_tip');?>
                            </small>
                        </div>
                        <div class='col-2'>
                        <input type='text' class='form-control form-control-sm' id='proxy_ip' value='<?php echo isset($SETTINGS['proxy_ip']) ? $SETTINGS['proxy_ip'] : '60';?>' onchange='updateSetting($(this).attr("id"), $(this).val());' />
                        </div>
                    </div>

                    <div class='row mb-2'>
                        <div class='col-10'>
                            <?php echo langHdl('admin_proxy_port');?>
                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                <?php echo langHdl('admin_proxy_port_tip');?>
                            </small>
                        </div>
                        <div class='col-2'>
                            <input type='text' class='form-control form-control-sm' id='proxy_port' value='<?php echo isset($SETTINGS['proxy_port']) ? $SETTINGS['proxy_port'] : '60';?>' onchange='updateSetting($(this).attr("id"), $(this).val());' />
                        </div>
                    </div>

                    <div class='row mb-2'>
                        <div class='col-10'>
                            <?php echo langHdl('admin_pwd_maximum_length');?>
                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                <?php echo langHdl('admin_pwd_maximum_length_tip');?>
                            </small>
                        </div>
                        <div class='col-2'>
                            <input type='text' class='form-control form-control-sm' id='pwd_maximum_length' value='<?php echo isset($SETTINGS['pwd_maximum_length']) ? $SETTINGS['pwd_maximum_length'] : '60';?>' onchange='updateSetting($(this).attr("id"), $(this).val());' />
                        </div>
                    </div>

                    <div class='row mb-2'>
                        <div class='col-4'>
                            <?php echo langHdl('timezone_selection');?>
                        </div>
                        <div class='col-8'>
                            <select class='form-control form-control-sm' id='timezone' onchange='updateSetting($(this).attr("id"), $(this).val());'>
                                <option value=''>-- <?php echo langHdl('select');?> --</option>
                                <?php
                                // get list of all timezones
                                $zones = timezone_identifiers_list();
                                foreach ($zones as $zone) {
                                    echo '
                                <option value="'.$zone.'"', isset($SETTINGS['timezone']) === true && $SETTINGS['timezone'] === $zone ? ' selected' : '', '>'.$zone.'</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class='row mb-2'>
                        <div class='col-4'>
                            <?php echo langHdl('date_format');?>
                        </div>
                        <div class='col-8'>
                            <select class='form-control form-control-sm' id='date_format' onchange='updateSetting($(this).attr("id"), $(this).val());'>
                                <option value=''>-- <?php echo langHdl('select');?> --</option>
                                <option value="d/m/Y"<?php echo isset($SETTINGS['date_format']) === false || $SETTINGS['date_format'] === "d/m/Y" ? ' selected' : '';?>>d/m/Y</option>
                                <option value="m/d/Y"<?php echo $SETTINGS['date_format'] == "m/d/Y" ? ' selected' : '';?>>m/d/Y</option>
                                <option value="d-M-Y"<?php echo $SETTINGS['date_format'] == "d-M-Y" ? ' selected' : '';?>>d-M-Y</option>
                                <option value="d/m/y"<?php echo $SETTINGS['date_format'] == "d/m/y" ? ' selected' : '';?>>d/m/y</option>
                                <option value="m/d/y"<?php echo $SETTINGS['date_format'] == "m/d/y" ? ' selected' : '';?>>m/d/y</option>
                                <option value="d-M-y"<?php echo $SETTINGS['date_format'] == "d-M-y" ? ' selected' : '';?>>d-M-y</option>
                                <option value="d-m-y"<?php echo $SETTINGS['date_format'] == "d-m-y" ? ' selected' : '';?>>d-m-y</option>
                                <option value="Y-m-d"<?php echo $SETTINGS['date_format'] == "Y-m-d" ? ' selected' : '';?>>Y-m-d</option>
                            </select>
                        </div>
                    </div>

                    <div class='row mb-2'>
                        <div class='col-4'>
                            <?php echo langHdl('time_format');?>
                        </div>
                        <div class='col-8'>
                            <select class='form-control form-control-sm' id='time_format' onchange='updateSetting($(this).attr("id"), $(this).val());'>
                                <option value=''>-- <?php echo langHdl('select');?> --</option>
                                <option value="H:i:s"<?php echo isset($SETTINGS['time_format']) === false || $SETTINGS['time_format'] === "H:i:s" ? ' selected' : '';?>>H:i:s</option>
                                <option value="H:i:s a"<?php echo $SETTINGS['time_format'] == "H:i:s a" ? ' selected' : '';?>>H:i:s a</option>
                                <option value="g:i:s a"<?php echo $SETTINGS['time_format'] == "g:i:s a" ? ' selected' : '';?>>g:i:s a</option>
                                <option value="G:i:s"<?php echo $SETTINGS['time_format'] == "G:i:s" ? ' selected' : '';?>>G:i:s</option>
                            </select>
                        </div>
                    </div>

                    <div class='row mb-2'>
                        <div class='col-8'>
                            <?php echo langHdl('settings_default_language');?>
                        </div>
                        <div class='col-4'>
                            <select class='form-control form-control-sm' id='default_language' onchange='updateSetting($(this).attr("id"), $(this).val());'>
                                <option value=''>-- <?php echo langHdl('select');?> --</option>
                                <?php
                                foreach ($languagesList as $lang) {
                                    echo '
                                <option value="'.$lang.'"', isset($SETTINGS['default_language']) === true && $SETTINGS['default_language'] === $lang ? ' selected' : '', '>'.$lang.'</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class='row mb-2'>
                        <div class='col-10'>
                            <?php echo langHdl('number_of_used_pw');?>
                        </div>
                        <div class='col-2'>
                            <input type='text' class='form-control form-control-sm' id='number_of_used_pw' value='<?php echo isset($SETTINGS['number_of_used_pw']) ? $SETTINGS['number_of_used_pw'] : '5';?>' onchange='updateSetting($(this).attr("id"), $(this).val());' />
                        </div>
                    </div>

                    <div class='row mb-2'>
                        <div class='col-10'>
                            <?php echo langHdl('pw_life_duration');?>
                        </div>
                        <div class='col-2'>
                            <input type='text' class='form-control form-control-sm' id='pw_life_duration' value='<?php echo isset($SETTINGS['pw_life_duration']) ? $SETTINGS['pw_life_duration'] : '5';?>' onchange='updateSetting($(this).attr("id"), $(this).val());' />
                        </div>
                    </div>

                    <div class='row mb-2'>
                        <div class='col-10'>
                            <?php echo langHdl('nb_false_login_attempts');?>
                        </div>
                        <div class='col-2'>
                            <input type='text' class='form-control form-control-sm' id='nb_bad_authentication' value='<?php echo isset($SETTINGS['nb_bad_authentication']) ? $SETTINGS['nb_bad_authentication'] : '0';?>' onchange='updateSetting($(this).attr("id"), $(this).val());' />
                        </div>
                    </div>

                    <div class='row mb-2'>
                      <div class='col-10'>
                          <?php echo langHdl('settings_log_connections');?>
                      </div>
                      <div class='col-2'>
                          <div class='toggle toggle-modern' id='enalog_connectionsble_sts' data-toggle-on='<?php echo isset($SETTINGS['log_connections']) === true && $SETTINGS['log_connections'] === '1' ? 'true' : 'false';?>'></div><input type='hidden' id='log_connections_input' value='<?php echo isset($SETTINGS['log_connections']) && $SETTINGS['log_connections'] === '1' ? '1' : '0';?>' />
                      </div>
                  </div>

                  <div class='row mb-2'>
                      <div class='col-10'>
                          <?php echo langHdl('settings_log_accessed');?>
                      </div>
                      <div class='col-2'>
                          <div class='toggle toggle-modern' id='log_accessed' data-toggle-on='<?php echo isset($SETTINGS['log_accessed']) === true && $SETTINGS['log_accessed'] === '1' ? 'true' : 'false';?>'></div><input type='hidden' id='log_accessed_input' value='<?php echo isset($SETTINGS['log_accessed']) && $SETTINGS['log_accessed'] === '1' ? '1' : '0';?>' />
                      </div>
                  </div>

                  <div class='row mb-2'>
                      <div class='col-10'>
                          <?php echo langHdl('enable_personal_folder_feature');?>
                          <small id='passwordHelpBlock' class='form-text text-muted'>
                              <?php echo langHdl('enable_personal_folder_feature_tip');?>
                          </small>
                      </div>
                      <div class='col-2'>
                          <div class='toggle toggle-modern' id='enable_pf_feature' data-toggle-on='<?php echo isset($SETTINGS['enable_pf_feature']) === true && $SETTINGS['enable_pf_feature'] === '1' ? 'true' : 'false';?>'></div><input type='hidden' id='enable_pf_feature_input' value='<?php echo isset($SETTINGS['enable_pf_feature']) && $SETTINGS['enable_pf_feature'] === '1' ? '1' : '0';?>' />
                      </div>
                  </div>

                  <div class='row mb-2'>
                      <div class='col-10'>
                          <?php echo langHdl('enable_personal_saltkey_cookie');?>
                      </div>
                      <div class='col-2'>
                          <div class='toggle toggle-modern' id='enable_personal_saltkey_cookie' data-toggle-on='<?php echo isset($SETTINGS['enable_personal_saltkey_cookie']) === true && $SETTINGS['enable_personal_saltkey_cookie'] === '1' ? 'true' : 'false';?>'></div><input type='hidden' id='enable_personal_saltkey_cookie_input' value='<?php echo isset($SETTINGS['enable_personal_saltkey_cookie']) && $SETTINGS['enable_personal_saltkey_cookie'] === '1' ? '1' : '0';?>' />
                      </div>
                  </div>

                  <div class='row mb-2'>
                      <div class='col-10'>
                          <?php echo langHdl('personal_saltkey_cookie_duration');?>
                      </div>
                      <div class='col-2'>
                      <input type='text' class='form-control form-control-sm' id='personal_saltkey_cookie_duration' value='<?php echo isset($SETTINGS['personal_saltkey_cookie_duration']) ? $SETTINGS['personal_saltkey_cookie_duration'] : '31';?>' onchange='updateSetting($(this).attr("id"), $(this).val());' />
                      </div>
                  </div>

                    <div class='row mb-2'>
                        <div class='col-8'>
                            <?php echo langHdl('personal_saltkey_security_level');?>
                        </div>
                        <div class='col-4'>
                            <select class='form-control form-control-sm' id='personal_saltkey_security_level' onchange='updateSetting($(this).attr("id"), $(this).val());'>
                                <option value=''>-- <?php echo langHdl('select');?> --</option>
                                <?php
                                foreach (TP_PW_COMPLEXITY as $complex) {
                                    echo '
                                <option value="'.$complex[0].'"', isset($SETTINGS['personal_saltkey_security_level']) === true && $SETTINGS['personal_saltkey_security_level'] == $complex[0] ? ' selected' : '', '>'.$complex[1].'</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                  <div class='row mb-2'>
                      <div class='col-10'>
                          <?php echo langHdl('settings_attachments_encryption');?>
                          <small id='passwordHelpBlock' class='form-text text-muted'>
                              <?php echo langHdl('settings_attachments_encryption_tip');?>
                          </small>
                      </div>
                      <div class='col-2'>
                          <div class='toggle toggle-modern' id='enable_attachment_encryption' data-toggle-on='<?php echo isset($SETTINGS['enable_attachment_encryption']) === true && $SETTINGS['enable_attachment_encryption'] === '1' ? 'true' : 'false';?>'></div><input type='hidden' id='enable_attachment_encryption_input' value='<?php echo isset($SETTINGS['enable_attachment_encryption']) && $SETTINGS['enable_attachment_encryption'] === '1' ? '1' : '0';?>' />
                      </div>
                  </div>

                  <div class='row mb-2'>
                      <div class='col-10'>
                          <?php echo langHdl('settings_secure_display_image');?>
                          <small id='passwordHelpBlock' class='form-text text-muted'>
                              <?php echo langHdl('settings_secure_display_image_tip');?>
                          </small>
                      </div>
                      <div class='col-2'>
                          <div class='toggle toggle-modern' id='secure_display_image' data-toggle-on='<?php echo isset($SETTINGS['secure_display_image']) === true && $SETTINGS['secure_display_image'] === '1' ? 'true' : 'false';?>'></div><input type='hidden' id='secure_display_image_input' value='<?php echo isset($SETTINGS['secure_display_image']) && $SETTINGS['secure_display_image'] === '1' ? '1' : '0';?>' />
                      </div>
                  </div>

                  <div class='row mb-2'>
                      <div class='col-10'>
                          <?php echo langHdl('settings_kb');?>
                          <small id='passwordHelpBlock' class='form-text text-muted'>
                              <?php echo langHdl('settings_kb_tip');?>
                          </small>
                      </div>
                      <div class='col-2'>
                          <div class='toggle toggle-modern' id='enable_kb' data-toggle-on='<?php echo isset($SETTINGS['enable_kb']) === true && $SETTINGS['enable_kb'] === '1' ? 'true' : 'false';?>'></div><input type='hidden' id='enable_kb_input' value='<?php echo isset($SETTINGS['enable_kb']) && $SETTINGS['enable_kb'] === '1' ? '1' : '0';?>' />
                      </div>
                  </div>

                  <div class='row mb-2'>
                      <div class='col-10'>
                          <?php echo langHdl('settings_suggestion');?>
                          <small id='passwordHelpBlock' class='form-text text-muted'>
                              <?php echo langHdl('settings_suggestion_tip');?>
                          </small>
                      </div>
                      <div class='col-2'>
                          <div class='toggle toggle-modern' id='enable_suggestion' data-toggle-on='<?php echo isset($SETTINGS['enable_suggestion']) === true && $SETTINGS['enable_suggestion'] === '1' ? 'true' : 'false';?>'></div><input type='hidden' id='enable_suggestion_input' value='<?php echo isset($SETTINGS['enable_suggestion']) && $SETTINGS['enable_suggestion'] === '1' ? '1' : '0';?>' />
                      </div>
                  </div>

                  <div class='row mb-2'>
                      <div class='col-10'>
                          <?php echo langHdl('settings_get_tp_info');?>
                          <small id='passwordHelpBlock' class='form-text text-muted'>
                              <?php echo langHdl('settings_get_tp_info_tip');?>
                          </small>
                      </div>
                      <div class='col-2'>
                          <div class='toggle toggle-modern' id='get_tp_info' data-toggle-on='<?php echo isset($SETTINGS['get_tp_info']) === true && $SETTINGS['get_tp_info'] === '1' ? 'true' : 'false';?>'></div><input type='hidden' id='get_tp_info_input' value='<?php echo isset($SETTINGS['get_tp_info']) && $SETTINGS['get_tp_info'] === '1' ? '1' : '0';?>' />
                      </div>
                  </div>

                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
          </div>
          <!-- /.col-md-6 -->
          <!--/.col (left) -->
            <!-- right column -->
            <div class='col-md-6'>
                <!-- Horizontal Form -->
                <div class='card card-info'>
                <div class='card-header'>
                    <h3 class='card-title'><?php echo langHdl('admin_settings_title');?></h3>
                </div>
                <!-- /.card-header -->
                <!-- card-body -->
                <div class='card-body'>
                    <div class='row mb-2'>
                        <div class='col-10'>
                            <?php echo langHdl('settings_delay_for_item_edition');?>
                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                <?php echo langHdl('settings_delay_for_item_edition_tip');?>
                            </small>
                        </div>
                        <div class='col-2'>
                            <input type='text' class='form-control form-control-sm' id='delay_item_edition' value='<?php echo isset($SETTINGS['delay_item_edition']) ? $SETTINGS['delay_item_edition'] : '9';?>' onchange='updateSetting($(this).attr("id"), $(this).val());' />
                        </div>
                    </div>                    

                    <div class='row mb-2'>
                        <div class='col-10'>
                            <?php echo langHdl('otv_is_enabled');?>
                        </div>
                        <div class='col-2'>
                            <div class='toggle toggle-modern' id='otv_is_enabled' data-toggle-on='<?php echo isset($SETTINGS['otv_is_enabled']) === true && $SETTINGS['otv_is_enabled'] === '1' ? 'true' : 'false';?>'></div><input type='hidden' id='otv_is_enabled_input' value='<?php echo isset($SETTINGS['otv_is_enabled']) && $SETTINGS['otv_is_enabled'] === '1' ? '1' : '0';?>' />
                        </div>
                    </div>

                    <div class='row mb-2'>
                        <div class='col-10'>
                            <?php echo langHdl('settings_otv_expiration_period');?>
                        </div>
                        <div class='col-2'>
                            <input type='text' class='form-control form-control-sm' id='otv_expiration_period' value='<?php echo isset($SETTINGS['otv_expiration_period']) ? $SETTINGS['otv_expiration_period'] : '7';?>' onchange='updateSetting($(this).attr("id"), $(this).val());' />
                        </div>
                    </div>

                    <div class='row mb-2'>
                        <div class='col-10'>
                            <?php echo langHdl('settings_manager_edit');?>
                        </div>
                        <div class='col-2'>
                            <div class='toggle toggle-modern' id='manager_edit' data-toggle-on='<?php echo isset($SETTINGS['manager_edit']) === true && $SETTINGS['manager_edit'] === '1' ? 'true' : 'false';?>'></div><input type='hidden' id='manager_edit_input' value='<?php echo isset($SETTINGS['manager_edit']) && $SETTINGS['manager_edit'] === '1' ? '1' : '0';?>' />
                        </div>
                    </div> 

                    <div class='row mb-2'>
                        <div class='col-10'>
                            <?php echo langHdl('settings_manager_move_item');?>
                        </div>
                        <div class='col-2'>
                            <div class='toggle toggle-modern' id='manager_move_item' data-toggle-on='<?php echo isset($SETTINGS['manager_move_item']) === true && $SETTINGS['manager_move_item'] === '1' ? 'true' : 'false';?>'></div><input type='hidden' id='manager_move_item_input' value='<?php echo isset($SETTINGS['manager_move_item']) && $SETTINGS['manager_move_item'] === '1' ? '1' : '0';?>' />
                        </div>
                    </div> 

                </div>
                <!-- /.card-body -->
            <!-- /.card -->
            </div>
            <!--/.col (right) -->
        </div>
        <!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content -->






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
// Prepare network security context
$tpNetworkContext = teampassGetNetworkContextForAdmin($SETTINGS);
$tpNetworkRules = teampassLoadNetworkAclRules(false);

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

<style>
    #settings-navigation-card .card-body {
        overflow-x: hidden;
    }
    #settings-navigation-card #settings-nav-tab {
        align-items: stretch;
    }
    #settings-navigation-card #settings-nav-tab .nav-link {
        min-width: 0;
        width: 100%;
        max-width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    #settings-navigation-card {
        top: 1rem;
    }
    #settings-tab-content .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
    }
    #settings-tab-content .card-header .card-title {
        margin: 0;
        flex: 1 1 auto;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .5rem;
    }
    #settings-tab-content .card-header .card-tools {
        margin: 0;
        float: none;
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        align-self: center;
        height: 100%;
    }
    .tp-section-favorites-tools {
        display: flex;
        align-items: center;
        align-self: center;
        height: 100%;
        margin: 0 !important;
    }
    .tp-section-favorites-tools .btn-group {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: auto;
    }
    .tp-section-favorites-tools .btn.btn-tool.dropdown-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        align-self: center;
        min-width: 2rem;
        height: 2rem;
        padding: 0 .5rem;
        line-height: 1;
        margin: 0;
        border: 1px solid rgba(255, 255, 255, .55);
        border-radius: .3rem;
        background-color: rgba(255, 255, 255, .97);
        color: #5f6b77;
        box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .10);
        transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease, color .15s ease, transform .15s ease;
        transform: translateY(0);
    }
    .tp-section-favorites-tools .btn.btn-tool.dropdown-toggle:hover,
    .tp-section-favorites-tools .btn.btn-tool.dropdown-toggle:focus,
    .tp-section-favorites-tools .show > .btn.btn-tool.dropdown-toggle {
        background-color: #ffffff;
        border-color: rgba(255, 255, 255, .75);
        color: #495057;
        box-shadow: 0 .25rem .6rem rgba(0, 0, 0, .18);
    }
    .tp-section-favorites-tools .btn.btn-tool.dropdown-toggle .fa-star {
        color: #d48a12;
        font-size: .85rem;
    }
    .tp-section-favorites-menu {
        min-width: 300px;
        max-width: 360px;
        max-height: 360px;
        overflow-y: auto;
        padding: .25rem 0;
        border: 1px solid rgba(0, 0, 0, .12);
        box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15);
        background-color: #ffffff;
    }
    .tp-favorite-menu-item {
        display: flex;
        align-items: flex-start;
        gap: .65rem;
        white-space: normal;
        color: inherit;
    }
    .tp-favorite-menu-item i {
        width: 1rem;
        margin-top: .15rem;
        text-align: center;
        flex: 0 0 1rem;
    }
    .tp-favorite-menu-item .tp-favorite-menu-label {
        flex: 1 1 auto;
        line-height: 1.3;
        min-width: 0;
    }
    .option.tp-option-is-favorite {
        border-left: 3px solid #f0ad4e;
        padding-left: .5rem;
    }
    .tp-favorite-highlight {
        animation: tpFavoriteHighlight 2.2s ease;
    }
    @keyframes tpFavoriteHighlight {
        0% { background-color: rgba(255, 193, 7, .35); }
        100% { background-color: transparent; }
    }
    #settings-favorites-list .tp-favorite-actions {
        display: flex;
        gap: .5rem;
        align-items: center;
    }
</style>


<!-- Main content -->
<div class='content'>
    <div class='container-fluid'>
        <div class='row'>
            <div class='col-lg-3 col-md-4'>
                <div class='card card-info sticky-top' id='settings-navigation-card'>
                    <div class='card-header'>
                        <h3 class='card-title'><i class="fa-solid fa-list mr-2"></i><?php echo $lang->get('settings_navigation_title'); ?></h3>
                    </div>
                    <div class='card-body p-2'>
                        <div class='nav flex-column nav-pills' id='settings-nav-tab' role='tablist' aria-orientation='vertical'>
                            <a class='nav-link active' id='settings-nav-favorites' data-toggle='pill' href='#settings-tab-favorites' role='tab' aria-controls='settings-tab-favorites' aria-selected='true' title="<?php echo $lang->get('settings_category_favorites_title'); ?>"><i class="fa-solid fa-star mr-2"></i><?php echo $lang->get('settings_category_favorites_title'); ?></a>
                            <a class='nav-link' id='settings-nav-general' data-toggle='pill' href='#settings-tab-general' role='tab' aria-controls='settings-tab-general' aria-selected='false' title="<?php echo $lang->get('settings_category_general_info_title'); ?>"><i class="fa-solid fa-folder-open mr-2"></i><?php echo $lang->get('settings_category_general_info_title'); ?></a>
                            <a class='nav-link' id='settings-nav-system' data-toggle='pill' href='#settings-tab-system' role='tab' aria-controls='settings-tab-system' aria-selected='false' title="<?php echo $lang->get('settings_category_system_title'); ?>"><i class="fa-solid fa-gears mr-2"></i><?php echo $lang->get('settings_category_system_title'); ?></a>
                            <a class='nav-link' id='settings-nav-security' data-toggle='pill' href='#settings-tab-security' role='tab' aria-controls='settings-tab-security' aria-selected='false' title="<?php echo $lang->get('settings_category_security_title'); ?>"><i class="fa-solid fa-shield-halved mr-2"></i><?php echo $lang->get('settings_category_security_title'); ?></a>
                            <a class='nav-link' id='settings-nav-websocket' data-toggle='pill' href='#settings-tab-websocket' role='tab' aria-controls='settings-tab-websocket' aria-selected='false' title="<?php echo $lang->get('settings_realtime_title'); ?>"><i class="fa-solid fa-bolt mr-2"></i><?php echo $lang->get('settings_realtime_title'); ?></a>
                            <a class='nav-link' id='settings-nav-networks' data-toggle='pill' href='#settings-tab-networks' role='tab' aria-controls='settings-tab-networks' aria-selected='false' title="<?php echo $lang->get('settings_category_networks_title'); ?>"><i class="fa-solid fa-network-wired mr-2"></i><?php echo $lang->get('settings_category_networks_title'); ?></a>
                            <a class='nav-link' id='settings-nav-logging' data-toggle='pill' href='#settings-tab-logging' role='tab' aria-controls='settings-tab-logging' aria-selected='false' title="<?php echo $lang->get('settings_category_logging_title'); ?>"><i class="fa-solid fa-clipboard-list mr-2"></i><?php echo $lang->get('settings_category_logging_title'); ?></a>
                            <a class='nav-link' id='settings-nav-integration' data-toggle='pill' href='#settings-tab-integration' role='tab' aria-controls='settings-tab-integration' aria-selected='false' title="<?php echo $lang->get('settings_category_integration_title'); ?>"><i class="fa-solid fa-plug mr-2"></i><?php echo $lang->get('settings_category_integration_title'); ?></a>
                            <a class='nav-link' id='settings-nav-items' data-toggle='pill' href='#settings-tab-items' role='tab' aria-controls='settings-tab-items' aria-selected='false' title="<?php echo $lang->get('settings_category_items_title'); ?>"><i class="fa-solid fa-folder-tree mr-2"></i><?php echo $lang->get('settings_category_items_title'); ?></a>
                            <a class='nav-link' id='settings-nav-users' data-toggle='pill' href='#settings-tab-users' role='tab' aria-controls='settings-tab-users' aria-selected='false' title="<?php echo $lang->get('settings_category_users_title'); ?>"><i class="fa-solid fa-users-cog mr-2"></i><?php echo $lang->get('settings_category_users_title'); ?></a>
                            <a class='nav-link' id='settings-nav-collaboration' data-toggle='pill' href='#settings-tab-collaboration' role='tab' aria-controls='settings-tab-collaboration' aria-selected='false' title="<?php echo $lang->get('settings_category_collaboration_title'); ?>"><i class="fa-solid fa-people-arrows mr-2"></i><?php echo $lang->get('settings_category_collaboration_title'); ?></a>
                            <a class='nav-link' id='settings-nav-inactive' data-toggle='pill' href='#settings-tab-inactive' role='tab' aria-controls='settings-tab-inactive' aria-selected='false' title="<?php echo $lang->get('settings_category_inactive_users_title'); ?>"><i class="fa-solid fa-user-clock mr-2"></i><?php echo $lang->get('settings_category_inactive_users_title'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class='col-lg-9 col-md-8'>
                <div class='tab-content' id='settings-tab-content'>
<div class='tab-pane fade show active' id='settings-tab-favorites' role='tabpanel' aria-labelledby='settings-nav-favorites' data-section-label='<?php echo $lang->get('settings_category_favorites_title'); ?>'>
                        <div class='card card-info' id='settings-favorites-card'>
                            <div class='card-header'>
                                <h3 class='card-title'><i class="fa-solid fa-star mr-2"></i><?php echo $lang->get('settings_category_favorites_title'); ?>
                                    <span class="badge text-bg-secondary">
                                        <?php echo $lang->get('settings_category_favorites_goal'); ?>
                                    </span>
                                </h3>
                            </div>
                            <div class='card-body'>
                                <p class='text-muted mb-3'><?php echo $lang->get('settings_category_favorites_tip'); ?></p>
                                <div id='settings-favorites-alert' class='alert d-none mb-3' role='alert'></div>
                                <div id='settings-favorites-empty' class='alert alert-info mb-0'>
                                    <i class="fa-solid fa-thumbtack mr-2"></i><?php echo $lang->get('settings_category_favorites_empty'); ?>
                                </div>
                                <div id='settings-favorites-list' class='list-group d-none'></div>
                            </div>
                        </div>
                    </div>
                    <div class='tab-pane fade' id='settings-tab-general' role='tabpanel' aria-labelledby='settings-nav-general' data-section-label='<?php echo $lang->get('settings_category_general_info_title'); ?>'>
<div class='card card-info'>
                    <div class='card-header'>
                        <h3 class='card-title'><i class="fa-solid fa-folder-open mr-2"></i><?php echo $lang->get('settings_category_general_info_title'); ?>
                            <span class="badge text-bg-secondary">
                                <?php echo $lang->get('settings_category_general_info_goal'); ?>
                            </span>
                        </h3>
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
                    </div>
                    <div class='tab-pane fade' id='settings-tab-system' role='tabpanel' aria-labelledby='settings-nav-system' data-section-label='<?php echo $lang->get('settings_category_system_title'); ?>'>
<div class='card card-info'>
                    <div class='card-header'>
                        <h3 class='card-title'><i class="fa-solid fa-gears mr-2"></i><?php echo $lang->get('settings_category_system_title'); ?>
                            <span class="badge text-bg-secondary">
                                <?php echo $lang->get('settings_category_system_goal'); ?>
                            </span>
                        </h3>
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
                                <input type='number' class='form-control form-control-sm' id='default_session_expiration_time' value='<?php echo htmlspecialchars($SETTINGS['default_session_expiration_time'] ?? '60'); ?>'>
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
                                <input type='number' class='form-control form-control-sm' id='maximum_session_expiration_time' value='<?php echo htmlspecialchars($SETTINGS['maximum_session_expiration_time'] ?? '60'); ?>'>
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
                                <option value="' . $key . '"', isset($SETTINGS['timezone']) === true && $SETTINGS['timezone'] === $key ? ' selected' : '', '>' . $zone . '</option>';
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
                                <option value="' . $teampassLang . '"', isset($SETTINGS['default_language']) === true && $SETTINGS['default_language'] === $teampassLang ? ' selected' : '', '>' . $teampassLang . '</option>';
                                    }
                                    ?>
                                </select>
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
                        
                        <div class='row mb-2 option' data-keywords="user ui setting login length password">
                            <div class='col-10'>
                                <?php echo $lang->get('admin_pwd_maximum_length'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('admin_pwd_maximum_length_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='pwd_maximum_length' value='<?php echo htmlspecialchars($SETTINGS['pwd_maximum_length'] ?? '60'); ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="user ui setting login length password">
                            <div class='col-10'>
                                <?php echo $lang->get('password_length_by_default'); ?>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='pwd_default_length' value='<?php echo htmlspecialchars($SETTINGS['pwd_default_length'] ?? '14'); ?>'>
                            </div>
                        </div>

                    </div>
                    <!-- /.card-body -->
                </div>
                    </div>
                    <div class='tab-pane fade' id='settings-tab-security' role='tabpanel' aria-labelledby='settings-nav-security' data-section-label='<?php echo $lang->get('settings_category_security_title'); ?>'>
<div class='card card-info'>
                    <div class='card-header'>
                        <h3 class='card-title'><i class="fa-solid fa-shield-halved mr-2"></i><?php echo $lang->get('settings_category_security_title'); ?>
                            <span class="badge text-bg-secondary">
                                <?php echo $lang->get('settings_category_security_goal'); ?>
                            </span>
                        </h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <div class='card-body'>

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

                        <div class='row mb-2 option' data-keywords="user login password duration">
                            <div class='col-10'>
                                <?php echo $lang->get('pw_life_duration'); ?>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='pw_life_duration' value='<?php echo htmlspecialchars($SETTINGS['pw_life_duration'] ?? '5'); ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="log user login password security anti bruteforce account lockout">
                            <div class='col-10'>
                                <?php echo $lang->get('nb_false_login_attempts'); ?>
                            </div>
                            <div class='col-2'>
                                <input type='number' min='0' step='1' class='form-control form-control-sm' id='nb_bad_authentication' value='<?php echo htmlspecialchars($SETTINGS['nb_bad_authentication'] ?? '0'); ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="log user login password security anti bruteforce ip address blacklist acl">
                            <div class='col-10'>
                                <?php echo $lang->get('nb_bad_authentication_by_ip'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('nb_bad_authentication_by_ip_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='number' min='0' step='1' class='form-control form-control-sm' id='nb_bad_authentication_by_ip' value='<?php echo htmlspecialchars($SETTINGS['nb_bad_authentication_by_ip'] ?? '30'); ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="log user login password security anti bruteforce lock duration minutes">
                            <div class='col-10'>
                                <?php echo $lang->get('bruteforce_lock_duration'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('bruteforce_lock_duration_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='number' min='1' step='1' class='form-control form-control-sm' id='bruteforce_lock_duration' value='<?php echo htmlspecialchars($SETTINGS['bruteforce_lock_duration'] ?? '10'); ?>'>
                            </div>
                        </div>

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

                        <div class='row mb-2 option' data-keywords="role restriction">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_restricted_to'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='restricted_to' data-toggle-on='<?php echo isset($SETTINGS['restricted_to']) === true && (int) $SETTINGS['restricted_to'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='restricted_to_input' value='<?php echo isset($SETTINGS['restricted_to']) && (int) $SETTINGS['restricted_to'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option <?php echo isset($SETTINGS['restricted_to']) === true && (int) $SETTINGS['restricted_to'] === 1 ? '' : 'hidden'; ?>' id="form-item-row-restricted" data-keywords="role restriction">
                            <div class='col-10'>
                                <?php echo $lang->get('restricted_to_roles'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='restricted_to_roles' data-toggle-on='<?php echo isset($SETTINGS['restricted_to_roles']) === true && (int) $SETTINGS['restricted_to_roles'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='restricted_to_roles_input' value='<?php echo isset($SETTINGS['restricted_to_roles']) && (int) $SETTINGS['restricted_to_roles'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>
                        
                        <div class='row mb-2 option' data-keywords="user ui setting login length password">
                            <div class='col-10'>
                                <?php echo $lang->get('transparent_key_recovery_pbkdf2_iterations'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('transparent_key_recovery_pbkdf2_iterations_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='transparent_key_recovery_pbkdf2_iterations' value='<?php echo htmlspecialchars($SETTINGS['transparent_key_recovery_pbkdf2_iterations'] ?? '60'); ?>'>
                            </div>
                        </div>

                    </div>
                </div>
                    </div>
                    <div class='tab-pane fade' id='settings-tab-websocket' role='tabpanel' aria-labelledby='settings-nav-websocket' data-section-label='<?php echo $lang->get('settings_realtime_title'); ?>'>
<div class='card card-info'>
                    <div class='card-header'>
                        <h3 class='card-title'><i class="fa-solid fa-bolt mr-2"></i><?php echo $lang->get('settings_realtime_title'); ?>
                            <span class="badge text-bg-secondary">
                                <?php echo $lang->get('settings_realtime_title_goal'); ?>
                            </span>
                        </h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <div class='card-body'>

                        <h6 class='mt-3 mb-2'><i class='fa-solid fa-ear-listen mr-2'></i><?php echo $lang->get('settings_websocket_title'); ?> <span class='badge text-bg-secondary'><?php echo $lang->get('settings_websocket_title_goal'); ?></span></h6>

                        <div class='row mb-2 option' data-keywords="websocket">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_websocket_enabler'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('settings_websocket_enabler_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='websocket_enabled' data-toggle-on='<?php echo isset($SETTINGS['websocket_enabled']) === true && (int) $SETTINGS['websocket_enabled'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='websocket_enabled_input' value='<?php echo isset($SETTINGS['websocket_enabled']) && (int) $SETTINGS['websocket_enabled'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="websocket host">
                            <div class='col-8'>
                                <?php echo $lang->get('settings_websocket_host'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('settings_websocket_host_tip'); ?>
                                </small>
                            </div>
                            <div class='col-4'>
                                <input type='text' class='form-control form-control-sm' id='websocket_host' value='<?php echo htmlspecialchars($SETTINGS['websocket_host'] ?? '127.0.0.1'); ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="websocket port">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_websocket_port'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('settings_websocket_port_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='websocket_port' value='<?php echo htmlspecialchars($SETTINGS['websocket_port'] ?? '8080'); ?>'>
                            </div>
                        </div>

                        <hr />

                        <h6 class='mt-3 mb-2'><i class='fa-solid fa-database mr-2'></i><?php echo $lang->get('settings_redis_session_title'); ?> <span class='badge text-bg-secondary'><?php echo $lang->get('settings_redis_session_title_goal'); ?></span></h6>

                        <div class='row mb-2 option' data-keywords="redis session">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_redis_session_enabler'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('settings_redis_session_enabler_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='redis_session_enabled' data-toggle-on='<?php echo isset($SETTINGS['redis_session_enabled']) === true && (int) $SETTINGS['redis_session_enabled'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='redis_session_enabled_input' value='<?php echo isset($SETTINGS['redis_session_enabled']) && (int) $SETTINGS['redis_session_enabled'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="redis host">
                            <div class='col-8'>
                                <?php echo $lang->get('settings_redis_host'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('settings_redis_host_tip'); ?>
                                </small>
                            </div>
                            <div class='col-4'>
                                <input type='text' class='form-control form-control-sm' id='redis_host' value='<?php echo htmlspecialchars($SETTINGS['redis_host'] ?? '127.0.0.1'); ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="redis port">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_redis_port'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('settings_redis_port_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='number' class='form-control form-control-sm' id='redis_port' value='<?php echo htmlspecialchars($SETTINGS['redis_port'] ?? '6379'); ?>'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="redis prefix">
                            <div class='col-8'>
                                <?php echo $lang->get('settings_redis_prefix'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('settings_redis_prefix_tip'); ?>
                                </small>
                            </div>
                            <div class='col-4'>
                                <input type='text' class='form-control form-control-sm' id='redis_prefix' value='<?php echo htmlspecialchars($SETTINGS['redis_prefix'] ?? 'teampass_sess_'); ?>'>
                            </div>
                        </div>

                    </div>
                </div>
                    </div>
                    <div class='tab-pane fade' id='settings-tab-networks' role='tabpanel' aria-labelledby='settings-nav-networks' data-section-label='<?php echo $lang->get('settings_category_networks_title'); ?>'>
                        <div class='card card-info'>
                            <div class='card-header'>
                                <h3 class='card-title'><i class="fa-solid fa-network-wired mr-2"></i><?php echo $lang->get('settings_category_networks_title'); ?>
                                    <span class="badge text-bg-secondary">
                                        <?php echo $lang->get('settings_category_networks_goal'); ?>
                                    </span>
                                </h3>
                            </div>
                            <div class='card-body' id='network-security-block'>
                                <div id='network-security-alert' class='alert d-none'></div>

                                <div class='card card-outline card-primary mb-3'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><?php echo $lang->get('network_security_general_settings_title'); ?></h3>
                                    </div>
                                    <div class='card-body'>
                                        <div class='row mb-3 option' data-keywords='network security whitelist blacklist proxy waf'>
                                            <div class='col-10'>
                                                <?php echo $lang->get('network_security_enable_blacklist'); ?>
                                            </div>
                                            <div class='col-2 text-right'>
                                                <div class='toggle toggle-modern' id='network_blacklist_enabled_toggle' data-toggle-on='<?php echo isset($SETTINGS['network_blacklist_enabled']) === true && (int) $SETTINGS['network_blacklist_enabled'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='network_blacklist_enabled_input' value='<?php echo isset($SETTINGS['network_blacklist_enabled']) && (int) $SETTINGS['network_blacklist_enabled'] === 1 ? 1 : 0; ?>' />
                                            </div>
                                        </div>
                                        <div class='row mb-3 option' data-keywords='network security whitelist allow access'>
                                            <div class='col-10'>
                                                <?php echo $lang->get('network_security_enable_whitelist'); ?>
                                                <small class='form-text text-muted'><?php echo $lang->get('network_security_enable_whitelist_tip'); ?></small>
                                            </div>
                                            <div class='col-2 text-right'>
                                                <div class='toggle toggle-modern' id='network_whitelist_enabled_toggle' data-toggle-on='<?php echo isset($SETTINGS['network_whitelist_enabled']) === true && (int) $SETTINGS['network_whitelist_enabled'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='network_whitelist_enabled_input' value='<?php echo isset($SETTINGS['network_whitelist_enabled']) && (int) $SETTINGS['network_whitelist_enabled'] === 1 ? 1 : 0; ?>' />
                                            </div>
                                        </div>
                                        <div class='row mb-3 option' data-keywords='reverse proxy waf x-forwarded-for x-real-ip'>
                                            <div class='col-6'>
                                                <label for='network_security_mode'><?php echo $lang->get('network_security_mode'); ?></label>
                                            </div>
                                            <div class='col-6'>
                                                <select class='form-control form-control-sm' id='network_security_mode'>
                                                    <option value='direct' <?php echo ($SETTINGS['network_security_mode'] ?? 'direct') === 'direct' ? 'selected' : ''; ?>><?php echo $lang->get('network_security_mode_direct'); ?></option>
                                                    <option value='reverse_proxy' <?php echo ($SETTINGS['network_security_mode'] ?? 'direct') === 'reverse_proxy' ? 'selected' : ''; ?>><?php echo $lang->get('network_security_mode_reverse_proxy'); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class='row mb-3 option' data-keywords='x-forwarded-for x-real-ip reverse proxy header'>
                                            <div class='col-6'>
                                                <label for='network_security_header'><?php echo $lang->get('network_security_header'); ?></label>
                                            </div>
                                            <div class='col-6'>
                                                <select class='form-control form-control-sm' id='network_security_header'>
                                                    <option value='x-forwarded-for' <?php echo ($SETTINGS['network_security_header'] ?? 'x-forwarded-for') === 'x-forwarded-for' ? 'selected' : ''; ?>>X-Forwarded-For</option>
                                                    <option value='x-real-ip' <?php echo ($SETTINGS['network_security_header'] ?? 'x-forwarded-for') === 'x-real-ip' ? 'selected' : ''; ?>>X-Real-IP</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class='row mb-0 option' data-keywords='trusted proxies waf reverse proxy'>
                                            <div class='col-6'>
                                                <label for='network_trusted_proxies'><?php echo $lang->get('network_security_trusted_proxies'); ?></label>
                                                <small class='form-text text-muted'><?php echo $lang->get('network_security_trusted_proxies_tip'); ?></small>
                                            </div>
                                            <div class='col-6'>
                                                <textarea class='form-control form-control-sm' id='network_trusted_proxies' rows='4'><?php echo htmlspecialchars((string) ($SETTINGS['network_trusted_proxies'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            </div>
                                        </div>
                                        <div class='mt-3 text-right'>
                                            <button type='button' class='btn btn-primary btn-sm' id='network-security-save-settings'><?php echo $lang->get('network_security_save_settings'); ?></button>
                                        </div>
                                    </div>
                                </div>

                                <div class='card card-outline card-secondary mb-3'>
                                    <div class='card-header'>
                                        <h3 class='card-title'><?php echo $lang->get('network_security_detected_context_title'); ?></h3>
                                    </div>
                                    <div class='card-body'>
                                        <div class='row'>
                                            <div class='col-md-6'>
                                                <dl class='row mb-0'>
                                                    <dt class='col-sm-5'><?php echo $lang->get('network_security_detected_ip'); ?></dt>
                                                    <dd class='col-sm-7' id='network-detected-ip'><?php echo htmlspecialchars((string) ($tpNetworkContext['detected_ip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                                                    <dt class='col-sm-5'><?php echo $lang->get('network_security_remote_addr'); ?></dt>
                                                    <dd class='col-sm-7' id='network-remote-addr'><?php echo htmlspecialchars((string) ($tpNetworkContext['remote_addr'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                                                    <dt class='col-sm-5'><?php echo $lang->get('network_security_server_ip'); ?></dt>
                                                    <dd class='col-sm-7' id='network-server-ip'><?php echo htmlspecialchars((string) ($tpNetworkContext['server_ip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                                                </dl>
                                            </div>
                                            <div class='col-md-6'>
                                                <dl class='row mb-0'>
                                                    <dt class='col-sm-5'><?php echo $lang->get('network_security_mode'); ?></dt>
                                                    <dd class='col-sm-7' id='network-context-mode'><?php echo htmlspecialchars((string) ($tpNetworkContext['mode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                                                    <dt class='col-sm-5'><?php echo $lang->get('network_security_header'); ?></dt>
                                                    <dd class='col-sm-7' id='network-context-header'><?php echo htmlspecialchars((string) ($tpNetworkContext['header_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                                                    <dt class='col-sm-5'><?php echo $lang->get('network_security_proxy_status'); ?></dt>
                                                    <dd class='col-sm-7' id='network-context-proxy-used'><?php echo isset($tpNetworkContext['trusted_proxy_used']) === true && $tpNetworkContext['trusted_proxy_used'] === true ? $lang->get('yes') : $lang->get('no'); ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                        <div class='mt-3'>
                                            <button type='button' class='btn btn-outline-primary btn-sm mr-2' id='network-add-current-ip'><?php echo $lang->get('network_security_add_current_ip'); ?></button>
                                            <button type='button' class='btn btn-outline-secondary btn-sm' id='network-add-server-ip'><?php echo $lang->get('network_security_add_server_ip'); ?></button>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                        <div class='card card-outline card-success mb-3'>
                                            <div class='card-header'>
                                                <h3 class='card-title'><?php echo $lang->get('network_security_whitelist_title'); ?></h3>
                                            </div>
                                            <div class='card-body'>
                                                <input type='hidden' id='network-whitelist-rule-id' value='0'>
                                                <div class='form-group option' data-keywords='whitelist ip cidr'>
                                                    <label for='network-whitelist-rule-definition'><?php echo $lang->get('network_security_rule'); ?></label>
                                                    <input type='text' class='form-control form-control-sm' id='network-whitelist-rule-definition' value=''>
                                                    <small class='form-text text-muted'><?php echo $lang->get('network_security_rule_format_tip'); ?></small>
                                                </div>
                                                <div class='form-group option' data-keywords='whitelist comment description'>
                                                    <label for='network-whitelist-rule-comment'><?php echo $lang->get('network_security_comment'); ?></label>
                                                    <input type='text' class='form-control form-control-sm' id='network-whitelist-rule-comment' value=''>
                                                </div>
                                                <div class='form-group'>
                                                    <div class='custom-control custom-switch'>
                                                        <input type='checkbox' class='custom-control-input' id='network-whitelist-rule-enabled' checked>
                                                        <label class='custom-control-label' for='network-whitelist-rule-enabled'><?php echo $lang->get('network_security_rule_enabled'); ?></label>
                                                    </div>
                                                </div>
                                                <div class='text-right mb-3'>
                                                    <button type='button' class='btn btn-secondary btn-sm mr-2' id='network-whitelist-rule-reset'><?php echo $lang->get('network_security_reset_form'); ?></button>
                                                    <button type='button' class='btn btn-success btn-sm' id='network-whitelist-rule-save'><?php echo $lang->get('network_security_save_rule'); ?></button>
                                                </div>
                                                <div class='table-responsive'>
                                                    <table class='table table-sm table-striped'>
                                                        <thead>
                                                            <tr>
                                                                <th><?php echo $lang->get('network_security_rule'); ?></th>
                                                                <th><?php echo $lang->get('network_security_comment'); ?></th>
                                                                <th><?php echo $lang->get('network_security_status'); ?></th>
                                                                <th class='text-right'><?php echo $lang->get('network_security_actions'); ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id='network-whitelist-rules-body'>
                                                            <?php foreach (($tpNetworkRules['whitelist'] ?? []) as $tpNetworkRule) { ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars((string) $tpNetworkRule['rule_definition'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                                <td><?php echo htmlspecialchars((string) $tpNetworkRule['comment'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                                <td><?php echo (int) $tpNetworkRule['enabled'] === 1 ? $lang->get('enabled') : $lang->get('disabled'); ?></td>
                                                                <td></td>
                                                            </tr>
                                                            <?php } ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                <div>
                                        <div class='card card-outline card-danger mb-3'>
                                            <div class='card-header'>
                                                <h3 class='card-title'><?php echo $lang->get('network_security_blacklist_title'); ?></h3>
                                            </div>
                                            <div class='card-body'>
                                                <input type='hidden' id='network-blacklist-rule-id' value='0'>
                                                <div class='form-group option' data-keywords='blacklist ip cidr'>
                                                    <label for='network-blacklist-rule-definition'><?php echo $lang->get('network_security_rule'); ?></label>
                                                    <input type='text' class='form-control form-control-sm' id='network-blacklist-rule-definition' value=''>
                                                    <small class='form-text text-muted'><?php echo $lang->get('network_security_rule_format_tip'); ?></small>
                                                </div>
                                                <div class='form-group option' data-keywords='blacklist comment description'>
                                                    <label for='network-blacklist-rule-comment'><?php echo $lang->get('network_security_comment'); ?></label>
                                                    <input type='text' class='form-control form-control-sm' id='network-blacklist-rule-comment' value=''>
                                                </div>
                                                <div class='form-group'>
                                                    <div class='custom-control custom-switch'>
                                                        <input type='checkbox' class='custom-control-input' id='network-blacklist-rule-enabled' checked>
                                                        <label class='custom-control-label' for='network-blacklist-rule-enabled'><?php echo $lang->get('network_security_rule_enabled'); ?></label>
                                                    </div>
                                                </div>
                                                <div class='text-right mb-3'>
                                                    <button type='button' class='btn btn-secondary btn-sm mr-2' id='network-blacklist-rule-reset'><?php echo $lang->get('network_security_reset_form'); ?></button>
                                                    <button type='button' class='btn btn-danger btn-sm' id='network-blacklist-rule-save'><?php echo $lang->get('network_security_save_rule'); ?></button>
                                                </div>
                                                <div class='table-responsive'>
                                                    <table class='table table-sm table-striped'>
                                                        <thead>
                                                            <tr>
                                                                <th><?php echo $lang->get('network_security_rule'); ?></th>
                                                                <th><?php echo $lang->get('network_security_comment'); ?></th>
                                                                <th><?php echo $lang->get('network_security_status'); ?></th>
                                                                <th class='text-right'><?php echo $lang->get('network_security_actions'); ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id='network-blacklist-rules-body'>
                                                            <?php foreach (($tpNetworkRules['blacklist'] ?? []) as $tpNetworkRule) { ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars((string) $tpNetworkRule['rule_definition'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                                <td><?php echo htmlspecialchars((string) $tpNetworkRule['comment'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                                <td><?php echo (int) $tpNetworkRule['enabled'] === 1 ? $lang->get('enabled') : $lang->get('disabled'); ?></td>
                                                                <td></td>
                                                            </tr>
                                                            <?php } ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class='tab-pane fade' id='settings-tab-logging' role='tabpanel' aria-labelledby='settings-nav-logging' data-section-label='<?php echo $lang->get('settings_category_logging_title'); ?>'>
<div class='card card-info'>
                    <div class='card-header'>
                        <h3 class='card-title'><i class="fa-solid fa-clipboard-list mr-2"></i><?php echo $lang->get('settings_category_logging_title'); ?>
                            <span class="badge text-bg-secondary">
                                <?php echo $lang->get('settings_category_logging_goal'); ?>
                            </span>
                        </h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <div class='card-body'>

                        <div class='row mb-2 option' data-keywords="log item password log security">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_log_accessed'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='log_accessed' data-toggle-on='<?php echo isset($SETTINGS['log_accessed']) === true && (int) $SETTINGS['log_accessed'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='log_accessed_input' value='<?php echo isset($SETTINGS['log_accessed']) && (int) $SETTINGS['log_accessed'] === 1 ? 1 : 0; ?>' />
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
<?php $healthLogsMode = isset($SETTINGS['health_logs_mode']) === true && in_array((string) $SETTINGS['health_logs_mode'], ['auto', 'manual'], true) === true ? (string) $SETTINGS['health_logs_mode'] : 'auto'; ?>

                        <div id='health-logs-settings-block'>
                            <div id='health-logs-settings-alert' class='alert d-none mb-2'></div>

                            <div class='row mb-2'>
                                <div class='col-12 text-muted font-weight-bold'>
                                    <i class="fa-solid fa-file-waveform mr-2"></i><?php echo $lang->get('health_runtime_logs'); ?>
                                </div>
                            </div>

                            <div class='row mb-2 option' data-keywords="health logs runtime apache nginx teampass php-fpm log path auto manual">
                                <div class='col-8'>
                                    <?php echo $lang->get('health_logs_mode'); ?>
                                    <small class='form-text text-muted'>
                                        <?php echo $lang->get('health_logs_mode_tip'); ?>
                                    </small>
                                </div>
                                <div class='col-4'>
                                    <select class='form-control form-control-sm' id='health_logs_mode'>
                                        <option value='auto' <?php echo $healthLogsMode === 'auto' ? 'selected' : ''; ?>><?php echo $lang->get('health_logs_mode_auto'); ?></option>
                                        <option value='manual' <?php echo $healthLogsMode === 'manual' ? 'selected' : ''; ?>><?php echo $lang->get('health_logs_mode_manual'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class='row mb-2 option <?php echo $healthLogsMode === 'manual' ? '' : 'hidden'; ?>' id='health-log-teampass-path-row' data-keywords="health logs teampass dedicated vhost apache nginx error log path">
                                <div class='col-12'>
                                    <?php echo $lang->get('health_teampass_log_path'); ?>
                                    <small class='form-text text-muted'>
                                        <?php echo $lang->get('health_teampass_log_path_tip'); ?>
                                    </small>
                                    <input type='text' class='form-control form-control-sm mt-2' id='health_teampass_log_path' value='<?php echo htmlspecialchars((string) ($SETTINGS['health_teampass_log_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>'>
                                </div>
                            </div>

                            <div class='row mb-2 option <?php echo $healthLogsMode === 'manual' ? '' : 'hidden'; ?>' id='health-log-php-fpm-path-row' data-keywords="health logs php-fpm fpm pool error log path">
                                <div class='col-12'>
                                    <?php echo $lang->get('health_php_fpm_log_path'); ?>
                                    <small class='form-text text-muted'>
                                        <?php echo $lang->get('health_php_fpm_log_path_tip'); ?>
                                    </small>
                                    <input type='text' class='form-control form-control-sm mt-2' id='health_php_fpm_log_path' value='<?php echo htmlspecialchars((string) ($SETTINGS['health_php_fpm_log_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>'>
                                </div>
                            </div>

                            <div class='row mb-0 <?php echo $healthLogsMode === 'manual' ? '' : 'hidden'; ?>' id='health-logs-settings-save-row'>
                                <div class='col-12 text-right'>
                                    <button type='button' class='btn btn-primary btn-sm' id='health-logs-settings-save'>
                                        <i class='fas fa-save mr-1'></i><?php echo $lang->get('save'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>


                        
                    </div>
                </div>
                    </div>
                    <div class='tab-pane fade' id='settings-tab-integration' role='tabpanel' aria-labelledby='settings-nav-integration' data-section-label='<?php echo $lang->get('settings_category_integration_title'); ?>'>
<div class='card card-info'>
                    <div class='card-header'>
                        <h3 class='card-title'><i class="fa-solid fa-plug mr-2"></i><?php echo $lang->get('settings_category_integration_title'); ?>
                            <span class="badge text-bg-secondary">
                                <?php echo $lang->get('settings_category_integration_goal'); ?>
                            </span>
                        </h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <div class='card-body'>

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
                </div>
                    </div>
                    <div class='tab-pane fade' id='settings-tab-items' role='tabpanel' aria-labelledby='settings-nav-items' data-section-label='<?php echo $lang->get('settings_category_items_title'); ?>'>
<div class='card card-info'>
                    <div class='card-header'>
                        <h3 class='card-title'><i class="fa-solid fa-folder-tree mr-2"></i><?php echo $lang->get('settings_category_items_title'); ?>
                            <span class="badge text-bg-secondary">
                                <?php echo $lang->get('settings_category_items_goal'); ?>
                            </span>
                        </h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- card-body -->
                    <div class='card-body'>

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

                        <div class='row mb-2 option' data-keywords="password creation">
                            <div class='col-10'>
                                <?php echo $lang->get('create_item_without_password'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='create_item_without_password' data-toggle-on='<?php echo isset($SETTINGS['create_item_without_password']) === true && (int) $SETTINGS['create_item_without_password'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='create_item_without_password_input' value='<?php echo isset($SETTINGS['create_item_without_password']) && (int) $SETTINGS['create_item_without_password'] === 1 ? 1 : 0; ?>' />
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

                        <div class='row mb-2 option' data-keywords="folder creation">
                            <div class='col-10'>
                                <?php echo $lang->get('enable_user_can_create_folders'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='enable_user_can_create_folders' data-toggle-on='<?php echo isset($SETTINGS['enable_user_can_create_folders']) === true && (int) $SETTINGS['enable_user_can_create_folders'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='enable_user_can_create_folders_input' value='<?php echo isset($SETTINGS['enable_user_can_create_folders']) && (int) $SETTINGS['enable_user_can_create_folders'] === 1 ? 1 : 0; ?>' />
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
if (isset($SETTINGS['show_description']) === true && (int) $SETTINGS['show_description'] === 1) {
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

                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            


                    </div>
                    </div>
                    <div class='tab-pane fade' id='settings-tab-users' role='tabpanel' aria-labelledby='settings-nav-users' data-section-label='<?php echo $lang->get('settings_category_users_title'); ?>'>
<div class='card card-info'>
                    <div class='card-header'>
                        <h3 class='card-title'><i class="fa-solid fa-users-cog mr-2"></i><?php echo $lang->get('settings_category_users_title'); ?>
                            <span class="badge text-bg-secondary">
                                <?php echo $lang->get('settings_category_users_goal'); ?>
                            </span>
                        </h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <div class='card-body'>

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

                        <div class='row mb-2 option' data-keywords="users online connected visibility footer">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_show_online_users_list'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('settings_show_online_users_list_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='show_online_users_list' data-toggle-on='<?php echo isset($SETTINGS['show_online_users_list']) === true && (int) $SETTINGS['show_online_users_list'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='show_online_users_list_input' value='<?php echo isset($SETTINGS['show_online_users_list']) && (int) $SETTINGS['show_online_users_list'] === 1 ? 1 : 0; ?>' />
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

                        <div class='row mb-2 option <?php echo isset($SETTINGS['anyone_can_modify']) === true && (int) $SETTINGS['anyone_can_modify'] === 1 ? '' : 'hidden'; ?>' id="form-item-row-modify" data-keywords="role restriction modify right">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_anyone_can_modify_bydefault'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='anyone_can_modify_bydefault' data-toggle-on='<?php echo isset($SETTINGS['anyone_can_modify_bydefault']) === true && (int) $SETTINGS['anyone_can_modify_bydefault'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='anyone_can_modify_bydefault_input' value='<?php echo isset($SETTINGS['anyone_can_modify_bydefault']) && (int) $SETTINGS['anyone_can_modify_bydefault'] === 1 ? 1 : 0; ?>' />
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
                                <?php echo $lang->get('disable_user_edit_tree_load_strategy'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='disable_user_edit_tree_load_strategy' data-toggle-on='<?php echo isset($SETTINGS['disable_user_edit_tree_load_strategy']) === true && (int) $SETTINGS['disable_user_edit_tree_load_strategy'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='disable_user_edit_tree_load_strategy_input' value='<?php echo isset($SETTINGS['disable_user_edit_tree_load_strategy']) && (int) $SETTINGS['disable_user_edit_tree_load_strategy'] === 1 ? 1 : 0; ?>' />
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
                        
                    </div>
                </div>
                    </div>
                    <div class='tab-pane fade' id='settings-tab-collaboration' role='tabpanel' aria-labelledby='settings-nav-collaboration' data-section-label='<?php echo $lang->get('settings_category_collaboration_title'); ?>'>
<div class='card card-info'>
                    <div class='card-header'>
                        <h3 class='card-title'><i class="fa-solid fa-people-arrows mr-2"></i><?php echo $lang->get('settings_category_collaboration_title'); ?>
                            <span class="badge text-bg-secondary">
                                <?php echo $lang->get('settings_category_collaboration_goal'); ?>
                            </span>
                        </h3>
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
                                <input type='number' class='form-control form-control-sm' id='otv_expiration_period' value='<?php echo htmlspecialchars($SETTINGS['otv_expiration_period'] ?? '7'); ?>'>
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

                        <div class='row mb-2 option' data-keywords="items corrupted highlight warning list display">
                            <div class='col-10'>
                                <?php echo $lang->get('settings_show_corrupted_items_in_list'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('settings_show_corrupted_items_in_list_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='show_corrupted_items_in_list' data-toggle-on='<?php echo isset($SETTINGS['show_corrupted_items_in_list']) === true && (int) $SETTINGS['show_corrupted_items_in_list'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='show_corrupted_items_in_list_input' value='<?php echo isset($SETTINGS['show_corrupted_items_in_list']) && (int) $SETTINGS['show_corrupted_items_in_list'] === 1 ? 1 : 0; ?>' />
                            </div>
                        </div>
                        
                    </div>
                </div>
                    </div>
                    <div class='tab-pane fade' id='settings-tab-inactive' role='tabpanel' aria-labelledby='settings-nav-inactive' data-section-label='<?php echo $lang->get('settings_category_inactive_users_title'); ?>'>
<div class='card card-info'>
                    <div class='card-header'>
                        <h3 class='card-title'><i class="fa-solid fa-user-clock mr-2"></i><?php echo $lang->get('settings_category_inactive_users_title'); ?>
                            <span class="badge text-bg-secondary">
                                <?php echo $lang->get('settings_category_inactive_users_goal'); ?>
                            </span>
                        </h3>
                    </div>
                    <!-- /.card-header -->
                    <div class='card-body'>

                        <div class='row mb-2 option' data-keywords="inactive users management warn delete disable purge soft hard" id='inactive-users-mgmt-block'>
                            <div class='col-10'>
                                <?php echo $lang->get('inactive_users_mgmt_description'); ?>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='ium-enabled' data-toggle-on='false'></div><input type='hidden' id='ium-enabled_input' value='0' />
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="inactive users management message">
                            <div class='col-12'>
                                <div id='ium-alert' class='alert d-none mb-0' role='alert'></div>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="inactive users management inactivity days warning">
                            <div class='col-10'>
                                <?php echo $lang->get('inactive_users_mgmt_inactivity_days'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('inactive_users_mgmt_inactivity_days_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='number' min='1' class='form-control form-control-sm' id='ium-inactivity-days' value='90'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="inactive users management grace days delete disable">
                            <div class='col-10'>
                                <?php echo $lang->get('inactive_users_mgmt_grace_days'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('inactive_users_mgmt_grace_days_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <input type='number' min='0' class='form-control form-control-sm' id='ium-grace-days' value='7'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="inactive users management action disable soft hard purge">
                            <div class='col-4'>
                                <?php echo $lang->get('inactive_users_mgmt_action'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('inactive_users_mgmt_action_tip'); ?>
                                </small>
                            </div>
                            <div class='col-8'>
                                <select id='ium-action' class='form-control form-control-sm w-100' style='min-width: 260px;'>
                                    <option value='disable'><?php echo $lang->get('inactive_users_mgmt_action_disable'); ?></option>
                                    <option value='soft_delete'><?php echo $lang->get('inactive_users_mgmt_action_soft_delete'); ?></option>
                                    <option value='hard_delete'><?php echo $lang->get('inactive_users_mgmt_action_hard_delete'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="inactive users management schedule time daily">
                            <div class='col-4'>
                                <?php echo $lang->get('inactive_users_mgmt_time'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('inactive_users_mgmt_time_tip'); ?>
                                </small>
                            </div>
                            <div class='col-8'>
                                <input type='time' class='form-control form-control-sm' id='ium-time' value='02:00' style='max-width: 180px;'>
                            </div>
                        </div>

                        <div class='row mb-2 option' data-keywords="inactive users management save run now">
                            <div class='col-4'>
                                <?php echo $lang->get('actions'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('inactive_users_mgmt_actions_tip'); ?>
                                </small>
                            </div>
                            <div class='col-8'>
                                <button type='button' class='btn btn-primary btn-sm mr-2' id='ium-save'>
                                    <i class='fas fa-save mr-1'></i><?php echo $lang->get('save'); ?>
                                </button>
                                <button type='button' class='btn btn-secondary btn-sm' id='ium-run-now'>
                                    <i class='fa-solid fa-play mr-1'></i><?php echo $lang->get('inactive_users_mgmt_run_now'); ?>
                                </button>
                            </div>
                        </div>

                        <div class='row mb-0 option' data-keywords="inactive users management status next last summary">
                            <div class='col-12'>
                                <b><?php echo $lang->get('status'); ?></b>
                                <small class='form-text text-muted mb-0'>
                                    <?php echo $lang->get('inactive_users_mgmt_status_next_run'); ?>: <span id='ium-status-next-run'>-</span>
                                    &nbsp;|&nbsp;
                                    <?php echo $lang->get('inactive_users_mgmt_status_last_run'); ?>: <span id='ium-status-last-run'>-</span>
                                    &nbsp;|&nbsp;
                                    <?php echo $lang->get('inactive_users_mgmt_status_last_status'); ?>: <span id='ium-status-last-status'>-</span>
                                    <br>
                                    <?php echo $lang->get('inactive_users_mgmt_status_last_message'); ?>: <span id='ium-status-last-message'>-</span>
                                    <br>
                                    <?php echo $lang->get('inactive_users_mgmt_status_last_summary'); ?>: <span id='ium-status-last-summary'>-</span>
                                </small>
                            </div>
                        </div>

                    </div>
                    <!-- /.card-body -->
                </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</div>
<!-- /.content -->

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
 * @file      api.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('api') === false) {
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

// Get FQDN and API Key settings
$browserFqdn = getDomainFromSettingsUrl($SETTINGS['cpassman_url'] ?? '');

/**
 * Extract the domain name (host) from the application URL setting.
 * * @param string $url The URL string to parse, typically from $SETTINGS['passman_url'].
 * @return string The extracted domain name (host) or an empty string if invalid.
 */
function getDomainFromSettingsUrl(string $url): string
{
    if (empty($url)) {
        return 'localhost';
    }

    // Ensure protocol exists for parse_url to work correctly
    if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
        $url = 'http://' . $url;
    }

    $parsedUrl = parse_url($url);
    $host = isset($parsedUrl['host']) ? strtolower(trim($parsedUrl['host'], '.')) : 'localhost';

    // Special handling for localhost to get the subfolder name (e.g., "TeamPass")
    if (in_array($host, ['localhost', '127.0.0.1', '::1'])) {
        $path = isset($parsedUrl['path']) ? trim($parsedUrl['path'], '/') : '';
        if (!empty($path)) {
            $segments = explode('/', $path);
            // Check if the first segment is a directory and not the script name itself
            if (!empty($segments[0]) && strpos($segments[0], '.php') === false) {
                return $segments[0];
            }
        }
    }

    return $host;
}
?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-cubes mr-2"></i><?php echo $lang->get('api'); ?>
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
                <div class='card card-primary'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo $lang->get('api_configuration'); ?></h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <div class='card-body'>

                        <div class='row mb-2'>
                            <div class='col-10'>
                                <?php echo $lang->get('settings_api'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('settings_api_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='api' data-toggle-on='<?php echo isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1 ? 'true' : 'false'; ?>'></div><input type='hidden' id='api_input' value='<?php echo isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1 ? '1' : '0'; ?>' />
                            </div>
                        </div>

                        <div class='row mb-3'>
                            <div class='col-10'>
                                <?php echo $lang->get('settings_api_token_duration'); ?>
                                <small id='passwordHelpBlock' class='form-text text-muted'>
                                    <?php echo $lang->get('settings_api_token_duration_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                            <input type='text' class='form-control form-control-sm' id='api_token_duration' value='<?php echo isset($SETTINGS['api_token_duration']) === true ? (int) $SETTINGS['api_token_duration'] : 60; ?>'>
                            </div>
                        </div>

                        <ul class="nav nav-tabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#users" role="tab" aria-controls="users"><?php echo $lang->get('users'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#extension" role="tab" aria-controls="extension"><?php echo $lang->get('browser_extension'); ?></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#keys" role="tab" aria-controls="keys"><?php echo $lang->get('settings_api_keys_list'); ?></a>
                            </li>
                            <!--<li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#ips" role="tab" aria-controls="ips"><?php echo $lang->get('api_whitelist_ips'); ?></a>
                            </li>-->
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show" id="keys" role="tabpanel" aria-labelledby="keys-tab">
                                <small id="passwordHelpBlock" class="form-text text-muted mt-4">
                                    <?php echo $lang->get('settings_api_keys_list_tip'); ?>
                                </small>
                                
                                <div class="mt-4 text-orange">
                                    <i class="fa-solid fa-bullhorn mr-2"></i>Those keys are not anymore allowed to use the API. You should use API with a User account.
                                </div>
                                <div class="mt-4">
                                    <?php
                                    $rowsKeys = DB::query(
                                        'SELECT *
                                        FROM ' . prefixTable('api') . '
                                        WHERE type = %s
                                        ORDER BY timestamp ASC',
                                        'key'
                                    );
                                    ?>
                                    <table class="table table-hover table-striped<?php echo DB::count() > 0 ? '' : ' hidden'; ?> table-responsive" style="width:100%" id="table-api-keys">
                                        <thead>
                                            <tr>
                                                <th width="50px"></th>
                                                <th><?php echo $lang->get('label'); ?></th>
                                                <th><?php echo $lang->get('settings_api_key'); ?></th>
                                                <th><i class="fa-solid fa-user-check infotip" title="<?php echo $lang->get('enabled'); ?>"></i></th>
                                                <th><i class="fa-regular fa-square-plus infotip" title="<?php echo $lang->get('allowed_to_create'); ?>"></i></th>
                                                <th><i class="fa-solid fa-glasses infotip" title="<?php echo $lang->get('allowed_to_read'); ?>"></i></th>
                                                <th><i class="fa-solid fa-pencil infotip" title="<?php echo $lang->get('allowed_to_update'); ?>"></i></th>
                                                <th><i class="fa-solid fa-trash infotip" title="<?php echo $lang->get('allowed_to_delete'); ?>"></i></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            foreach ($rowsKeys as $key) {
                                                echo '
                                                    <tr data-id="' . strval($key['increment_id']) . '">
                                                    <td width="50px"><i class="fas fa-trash infotip pointer delete-api-key" title="' . $lang->get('del_button') . '"></i></td>
                                                    <td><span class="edit-api-key pointer">' . strval($key['label']) . '</span></td>
                                                    <td>' . strval($key['value']). '</td>
                                                    <td><i class="fas '.(intval($key['enabled']) === 1 ? 'fa-toggle-on text-info' : 'fa-toggle-off').' mr-1 text-center pointer api-clickme-action" data-field="enabled" data-increment-id="' . strval($key['increment_id']) . '"></i></td>
                                                    <td><i class="fas '.(intval($key['allowed_to_create']) === 1 ? 'fa-toggle-on text-info' : 'fa-toggle-off').' mr-1 text-center pointer api-clickme-action" data-field="allowed_to_create" data-increment-id="' . strval($key['increment_id']) . '"></i></td>
                                                    <td><i class="fas '.(intval($key['allowed_to_read']) === 1 ? 'fa-toggle-on text-info' : 'fa-toggle-off').' mr-1 text-center pointer api-clickme-action" data-field="allowed_to_read" data-increment-id="' . strval($key['increment_id']) . '"></i></td>
                                                    <td><i class="fas '.(intval($key['allowed_to_update']) === 1 ? 'fa-toggle-on text-info' : 'fa-toggle-off').' mr-1 text-center pointer api-clickme-action" data-field="allowed_to_update" data-increment-id="' . strval($key['increment_id']) . '"></i></td>
                                                    <td><i class="fas '.(intval($key['allowed_to_delete']) === 1 ? 'fa-toggle-on text-info' : 'fa-toggle-off').' mr-1 text-center pointer api-clickme-action" data-field="allowed_to_delete" data-increment-id="' . strval($key['increment_id']) . '"></i></td>
                                                </tr>';
                                            } ?>
                                        </tbody>
                                    </table>

                                    <div class="mt-2<?php echo DB::count() > 0 ? ' hidden' : ''; ?>" id="api-no-keys">
                                        <i class="fas fa-info mr-2 text-warning"></i><?php echo $lang->get('no_data_defined'); ?>
                                    </div>

                                </div>

                                <div class="form-group mt-4">
                                    <div class="callout callout-info">
                                        <span class="text-bold"><?php echo $lang->get('adding_new_api_key'); ?></span>

                                        <div class="row mt-1 ml-1">
                                            <input type="text" placeholder="<?php echo $lang->get('label'); ?>" class="col-4 form-control form-control-sm purify" id="new_api_key_label" data-field="label">
                                            <span class="fa-stack ml-2 infotip pointer" title="<?php echo $lang->get('adding_new_api_key'); ?>" id="button-new-api-key">
                                                <i class="fas fa-square fa-stack-2x"></i>
                                                <i class="fas fa-plus fa-stack-1x fa-inverse"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="tab-pane fade show mb-4" id="ips" role="tabpanel" aria-labelledby="ips-tab">
                                <small id="passwordHelpBlock" class="form-text text-muted mt-4">
                                    <?php echo $lang->get('api_whitelist_ips_tip'); ?>
                                </small>
                                <div class="col-12 mt-4" id="table-api-ip">
                                    <?php
                                    $rowsIps = DB::query(
                                                'SELECT increment_id, label, timestamp value FROM ' . prefixTable('api') . '
                                                WHERE type = %s
                                                ORDER BY timestamp ASC',
                                                'ip'
                                            );
                                    ?>
                                    <table class="table table-hover table-striped<?php echo DB::count() > 0 ? '' : ' hidden'; ?> table-responsive" style="width:100%" id="table-api-ips">
                                        <thead>
                                            <tr>
                                                <th width="50px"></th>
                                                <th><?php echo $lang->get('label'); ?></th>
                                                <th><?php echo $lang->get('settings_api_ip'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            foreach ($rowsIps as $ip) {
                                                echo '
                                                <tr data-id="' . strval($ip['increment_id']) . '">
                                                    <td width="50px"><i class="fas fa-trash infotip pointer delete-api-ip" title="' . $lang->get('del_button') . '"></i></td>
                                                    <td><span class="edit-api-ip pointer" data-field="label">' . strval($ip['label']) . '</span></td>
                                                    <td><span class="edit-api-ip pointer" data-field="value">' . strval($ip['value']) . '</span></td>
                                                </tr>';
                                            } ?>
                                        </tbody>
                                    </table>

                                    <div class="mt-2<?php echo DB::count() > 0 ? ' hidden' : ''; ?>" id="api-no-ips">
                                        <i class="fas fa-info mr-2 text-warning"></i><?php echo $lang->get('no_data_defined'); ?>
                                    </div>
                                </div>

                                <div class="form-group mt-4" id="new-api-ip">
                                    <div class="callout callout-info">
                                        <span class="text-bold"><?php echo $lang->get('adding_new_api_ip'); ?></span>

                                        <div class="row mt-1 ml-1">
                                            <input type="text" placeholder="<?php echo $lang->get('ip'); ?>" class="col-4 form-control" id="new_api_ip_value" data-inputmask="'alias': 'ip'">
                                            <input type="text" placeholder="<?php echo $lang->get('label'); ?>" class="col-4 form-control ml-2 purify" id="new_api_ip_label" data-field="label">
                                            <span class="fa-stack ml-2 infotip pointer" title="<?php echo $lang->get('settings_api_add_ip'); ?>" id="button-new-api-ip">
                                                <i class="fas fa-square fa-stack-2x"></i>
                                                <i class="fas fa-plus fa-stack-1x fa-inverse"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            
                            <div class="tab-pane fade show active" id="users" role="tabpanel" aria-labelledby="keys-tab">
                                <div class="row mb-4 mt-4">
                                    <div class="col-6 text-muted">
                                        <?php echo $lang->get('users_api_access_info'); ?>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-default pointer infotip" title="<?php echo $lang->get('build_missing_api_keys'); ?>" id="button-refresh-users-api">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <?php
                                    $rowsKeys = DB::query(
                                        'SELECT a.*, u.name, u.lastname, u.login
                                        FROM ' . prefixTable('api') . ' AS a
                                        INNER JOIN ' . prefixTable('users') . ' AS u ON a.user_id = u.id
                                        WHERE a.type = %s AND u.disabled = %i AND u.deleted_at IS NULL AND u.id NOT IN %li AND u.admin = %i
                                        ORDER BY u.login ASC',
                                        'user',
                                        0,
                                        [API_USER_ID, SSH_USER_ID, SSH_USER_ID, TP_USER_ID],
                                        0
                                    );
                                    ?>
                                    <table class="table table-hover table-striped<?php echo DB::count() > 0 ? '' : ' hidden'; ?> table-responsive" style="width:100%" id="table-api-keys">
                                        <thead>
                                            <tr>
                                                <th><?php echo $lang->get('user'); ?></th>
                                                <th><i class="fa-solid fa-user-check infotip" title="<?php echo $lang->get('enabled'); ?>"></i></th>
                                                <th><i class="fa-regular fa-square-plus infotip" title="<?php echo $lang->get('allowed_to_create'); ?>"></i></th>
                                                <th><i class="fa-solid fa-glasses infotip" title="<?php echo $lang->get('allowed_to_read'); ?>"></i></th>
                                                <th><i class="fa-solid fa-pencil infotip" title="<?php echo $lang->get('allowed_to_update'); ?>"></i></th>
                                                <th><i class="fa-solid fa-trash infotip" title="<?php echo $lang->get('allowed_to_delete'); ?>"></i></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            foreach ($rowsKeys as $key) {
                                                echo '
                                                    <tr data-id="' . strval($key['increment_id']) . '">
                                                    <td>' . strval($key['name']) . ' ' . strval($key['lastname']) . ' (<i>'.strval($key['login']).'</i>)</td>
                                                    <td><i class="fas '.(intval($key['enabled']) === 1 ? 'fa-toggle-on text-info' : 'fa-toggle-off').' mr-1 text-center pointer api-clickme-action" data-field="enabled" data-increment-id="' . strval($key['increment_id']) . '"></i></td>
                                                    <td><i class="fas '.(intval($key['allowed_to_create']) === 1 ? 'fa-toggle-on text-info' : 'fa-toggle-off').' mr-1 text-center pointer api-clickme-action" data-field="allowed_to_create" data-increment-id="' . strval($key['increment_id']) . '"></i></td>
                                                    <td><i class="fas '.(intval($key['allowed_to_read']) === 1 ? 'fa-toggle-on text-info' : 'fa-toggle-off').' mr-1 text-center pointer api-clickme-action" data-field="allowed_to_read" data-increment-id="' . strval($key['increment_id']) . '"></i></td>
                                                    <td><i class="fas '.(intval($key['allowed_to_update']) === 1 ? 'fa-toggle-on text-info' : 'fa-toggle-off').' mr-1 text-center pointer api-clickme-action" data-field="allowed_to_update" data-increment-id="' . strval($key['increment_id']) . '"></i></td>
                                                    <td><i class="fas '.(intval($key['allowed_to_delete']) === 1 ? 'fa-toggle-on text-info' : 'fa-toggle-off').' mr-1 text-center pointer api-clickme-action" data-field="allowed_to_delete" data-increment-id="' . strval($key['increment_id']) . '"></i></td>
                                                </tr>';
                                            } ?>
                                        </tbody>
                                    </table>

                                    <div class="mt-2<?php echo DB::count() > 0 ? ' hidden' : ''; ?>" id="api-no-keys">
                                        <i class="fas fa-info mr-2 text-warning"></i><?php echo $lang->get('no_data_defined'); ?>
                                    </div>

                                </div>
                            </div>

                            <div class="tab-pane fade show" id="extension" role="tabpanel" aria-labelledby="extension-tab">
                                <div class='col-12 mt-2'>
                                    <div class="alert alert-info" role="alert">
                                        <?php echo $lang->get('browser_extension_instructions'); ?>
                                    </div>
                                </div>

                                <div class='row mt-2 mb-2'>
                                    <div class='col-7'>
                                        <?php echo $lang->get('browser_extension_fqdn'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('browser_extension_fqdn_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-5'>
                                    <input type='text' class='form-control form-control-sm' id='browser_extension_fqdn' value='<?php echo isset($SETTINGS['browser_extension_fqdn']) === true ? (string) $SETTINGS['browser_extension_fqdn'] : $browserFqdn; ?>'>
                                    </div>
                                </div>
                                
                                <div class='row mt-2 mb-2'>
                                    <div class='col-5'>
                                        <?php echo $lang->get('browser_extension_key'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo $lang->get('browser_extension_key_tip'); ?>
                                        </small>
                                    </div>
                                    <div class='col-5'>
                                        <input type='text' class='form-control form-control-sm' disabled="disabled" id='browser_extension_key' value='<?php echo isset($SETTINGS['browser_extension_key']) === true ? (string) $SETTINGS['browser_extension_key'] : 'Please upgrade'; ?>'>
                                    </div>
                                    <div class='col-2'>
                                        <button class="btn btn-sm btn-primary ml-1" id="copy-extension-key"><i class="fa-regular fa-copy pointer"></i></button>
                                        <button class="btn btn-sm btn-primary ml-1" id="generate-extension-key"><i class="fa-solid fa-rotate pointer"></i></button>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="form-group">

                        </div>

                        <div class="form-group mt-8">

                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

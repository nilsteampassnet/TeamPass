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
 * @file      export.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config if $SETTINGS not defined
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
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

// Check user access and printing enabled
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('export') === false
    || isset($SETTINGS['allow_print']) === false || (int) $SETTINGS['allow_print'] === 0
    || isset($SETTINGS['roles_allowed_to_print_select']) === false
    || empty($SETTINGS['roles_allowed_to_print_select']) === true
    || count(array_intersect(
        explode(';', $session->get('user-roles')),
        explode(',', str_replace(['"', '[', ']'], '', $SETTINGS['roles_allowed_to_print_select']))
    )) === 0
    || (int) $session_user_admin === 1
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
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
                <h1 class="m-0 text-dark"><i class="fas fa-file-export mr-2"></i><?php echo $lang->get('export_items'); //$lang->get('export_items'); ?></h1>
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
                <div class="card-body">

                    <div class="row mt-3">
                        <div class="form-group col-12">
                            <label><?php echo $lang->get('select_folders_to_export'); ?></label>
                            <select class="form-control select2-all" style="width:100%;" id="export-folders" multiple>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?php echo $lang->get('export_format_type'); ?></label>
                        <select class="form-control select2" style="width:100%;" id="export-format">
                            <option value="csv"><?php echo $lang->get('csv'); ?></option>
                            <?php
                            if (isset($SETTINGS['settings_offline_mode']) === true && (int) $SETTINGS['settings_offline_mode'] === 1) {
                                echo '<option value="html">'.strtoupper($lang->get('html')).'</option>';
                            }
                            ?>
                            <option value="pdf"><?php echo $lang->get('pdf'); ?></option>
                        </select>
                    </div>

                    <div id="pwd" class="hidden">
                        <div class="form-group mb-1" id="pdf-password">
                            <label><?php echo $lang->get('file_protection_password'); ?></label>
                            <input type="password" class="form-control form-item-control col-md-12" id="export-password">
                        </div>
                        <div class="container-fluid">
                            <div class="row">
                                <div class="col-md-12 justify-content-center">
                                    <div id="export-password-strength" class="justify-content-center"></div>
                                    <input type="hidden" id="export-password-complex" value="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label><?php echo $lang->get('filename'); ?></label>
                        <input type="text" class="form-control form-item-control" id="export-filename" value="Teampass_export_<?php echo time(); ?>">
                    </div>

                    <div class="alert alert-warning mb-3 mt-3 hidden" id="export-progress">
                        <div class="card-body">
                            <span></span>
                        </div>
                    </div>

                </div>

                <div class="card-footer">
                    <a class="hidden" href='' target='_blank' id="download-export-file">
                        <button type="button" class="btn btn-success"><i class="fa-solid fa-file-export mr-2"></i><?php echo $lang->get('download'); ?></button>
                    </a>
                    <button type="submit" class="btn btn-primary" id="form-item-export-perform"><?php echo $lang->get('perform'); ?></button>
                </div>
            </div>
        </div>
    </div>
</section>

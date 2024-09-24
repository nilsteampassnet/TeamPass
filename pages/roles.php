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
 * @file      roles.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
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
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('roles') === false) {
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
                <h1 class="m-0 text-dark">
                    <i class="fa-solid fa-graduation-cap mr-2"></i><?php echo $lang->get('roles'); ?>
                </h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <!-- column Roles list -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header p-2">
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="new">
                            <i class="fa-solid fa-plus mr-2"></i><?php echo $lang->get('new'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2 disabled" data-action="edit" id="button-edit">
                            <i class="fa-solid fa-pen mr-2"></i><?php echo $lang->get('edit'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2 disabled" data-action="delete" id="button-delete">
                            <i class="fa-solid fa-trash mr-2"></i><?php echo $lang->get('delete'); ?>
                        </button>
                        <?php
                            echo isset($SETTINGS['enable_ad_users_with_ad_groups']) === true && (int) $SETTINGS['enable_ad_users_with_ad_groups'] === 1 && (int) $session->get('user-admin') === 1 ?
                        '<button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="ldap" id="button-ldap">
                            <i class="fa-solid fa-address-card mr-2"></i>'.$lang->get('ldap_synchronization').'
                        </button>' : '';
                        ?>
                        
                    </div><!-- /.card-header -->
                    <div class="card-body">
                        <div class="form-group" id="card-role-selection">
                            <select id="roles-list" class="form-control form-item-control select2" style="width:100%;">
                                <option></option>
                                <?php
                                $arrUserRoles = array_filter($session->get('user-roles_array'));
                                $where = '';
                                if (count($arrUserRoles) > 0 && (int) $session->get('user-admin') !== 1) {
                                    $where = ' WHERE id IN (' . implode(',', $arrUserRoles) . ')';
                                }
                                $rows = DB::query('SELECT * FROM ' . prefixTable('roles_title') . $where);
                                foreach ($rows as $reccord) {
                                    echo '
                                    <option value="' . $reccord['id'] . '"
                                        data-complexity-text="' . addslashes(TP_PW_COMPLEXITY[$reccord['complexity']][1]) . '"
                                        data-complexity-icon="' . TP_PW_COMPLEXITY[$reccord['complexity']][2] . '"
                                        data-complexity="' . TP_PW_COMPLEXITY[$reccord['complexity']][0] . '"
                                        data-allow-edit-all="' . $reccord['allow_pw_change'] . '">'.
                                        $reccord['title'] . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="card hidden card-info" id="card-role-definition">
                            <div class="card-header">
                                <h5><?php echo $lang->get('role_definition'); ?></h5>
                            </div><!-- /.card-header -->
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="form-role-label"><?php echo $lang->get('label'); ?></label>
                                    <input type="text" class="form-control" id="form-role-label" required>
                                </div>
                                <div class="form-group">
                                    <label for="form-complexity-list"><?php echo $lang->get('complexity'); ?></label>
                                    <select id="form-complexity-list" class="form-control form-item-control select2" style="width:100%;">
                                        <?php
                                        foreach (TP_PW_COMPLEXITY as $entry) {
                                            echo '
                                        <option value="' . $entry[0] . '">' . addslashes($entry[1]) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group mt-2">
                                    <input type="checkbox" class="form-check-input form-item-control" id="form-role-privilege">
                                    <label class="form-check-label ml-2" for="form-role-privilege">
                                        <?php echo $lang->get('role_can_edit_any_visible_item'); ?>
                                    </label>
                                    <small class='form-text text-muted'>
                                        <?php echo $lang->get('role_can_edit_any_visible_item_tip'); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn btn-info tp-action" data-action="submit-edition"><?php echo $lang->get('submit'); ?></button>
                                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel-edition"><?php echo $lang->get('cancel'); ?></button>
                            </div>
                        </div>

                        <div class="card hidden card-danger" id="card-role-deletion">
                            <div class="card-header">
                                <h5><?php echo $lang->get('caution'); ?></h5>
                            </div><!-- /.card-header -->
                            <div class="card-body">
                                <div class="form-group mt-2">
                                    <input type="checkbox" class="form-check-input form-item-control" id="form-role-delete">
                                    <label class="form-check-label ml-2" for="form-role-delete">
                                        <?php echo $lang->get('please_confirm_deletion'); ?><span class="ml-2" id="span-role-delete"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn btn-danger tp-action" data-action="submit-deletion"><?php echo $lang->get('submit'); ?></button>
                                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel-deletion"><?php echo $lang->get('cancel'); ?></button>
                            </div>
                        </div>

                        <!-- LDAP SYNC FORM -->
                        <?php
                        if (isset($SETTINGS['enable_ad_users_with_ad_groups']) === true && (int) $SETTINGS['enable_ad_users_with_ad_groups'] === 1 && (int) $session->get('user-admin') === 1) {
                            ?>
                        <div class="card hidden card-info" id="card-roles-ldap-sync">
                            <div class="card-header">
                                <h5>
                                    <?php echo $lang->get('ad_groupe_and_roles_mapping'); ?>
                                    <button type="button" class="btn btn-primary btn-sm tp-action ml-2" data-action="ldap-refresh">
                                        <i class="fa-solid fa-sync-alt mr-2"></i><?php echo $lang->get('refresh'); ?>
                                    </button>
                                </h5>
                            </div><!-- /.card-header -->
                            <div class="card-body">
                                
                                <div class="card-body table-responsive p-0" id="ldap-groups-table">
                                    <table class="table table-hover table-responsive">
                                        <thead>
                                            <tr>
                                                <th style="width: 25%;"><i class="fa-solid fa-people-group mr-1"></i><?php echo $lang->get('ad_group'); ?></th>
                                                <th style="width: 25pw;"></th>
                                                <th><i class="fa-solid fa-graduation-cap mr-1"></i><?php echo $lang->get('mapped_with_role'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="row-ldap-body">
                                        </tbody>
                                    </table>
                                </div>
                            
                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel-ldap"><?php echo $lang->get('cancel'); ?></button>
                            </div>
                        </div>
                            <?php
                        } ?>

                    </div>
                </div>
            </div>
            <!-- /.col -->
        </div>

        <div class="row">
            <!-- column Role details -->
            <div class="col-md-12">
                <div class="card hidden" id="card-role-details">
                    <div class="card-header p-2">
                        <h3 id="role-detail-header"></h3>
                        <div class="callout callout-info">
                            <div class="callout-body">
                                <div class="row">
                                    <div class="input-group input-group-sm col-8">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text"><?php echo $lang->get('only_display_folders_to_depth'); ?></div>
                                        </div>
                                        <select class="form-control form-control-sm w-10" id="folders-depth">
                                        </select>
                                    </div>
                                    <div class="input-group input-group-sm col-4">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text">
                                                <i class="fa-solid fa-search"></i>
                                            </div>
                                        </div>
                                        <input type="text" class="form-control" placeholder="<?php echo $lang->get('find'); ?>" id="folders-search">
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="input-group input-group-sm col-12">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text"><?php echo $lang->get('compare_with_another_role'); ?></div>
                                        </div>
                                        <select class="form-control form-control-sm w-10" id="folders-compare">
                                        </select>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="input-group input-group-sm col-12">
                                        <input type="checkbox" id="cb-all-selection" class="folder-select mr-2">
                                        <span id="cb-all-selection-lang" class="ml-2"><?php echo $lang->get('select_all'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" id="role-details">
                            &nbsp;
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.col -->
        </div>
    </div>
</section>

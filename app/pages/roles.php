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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('roles') === false) {
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
                        <div class="form-group mb-0" id="card-role-selection">
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
                                    <option value="' . strval($reccord['id']) . '"
                                        data-complexity-text="' . addslashes(TP_PW_COMPLEXITY[$reccord['complexity']][1]) . '"
                                        data-complexity-icon="' . TP_PW_COMPLEXITY[$reccord['complexity']][2] . '"
                                        data-complexity="' . TP_PW_COMPLEXITY[$reccord['complexity']][0] . '"
                                        data-allow-edit-all="' . strval($reccord['allow_pw_change']) . '">'.
                                        strval($reccord['title']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
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
                    <!-- Matrix loading progress bar -->
                    <div id="roles-load-progress" class="mt-2 mb-0 px-3" style="display:none">
                        <div class="progress" style="height:18px">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                                role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted roles-load-text mt-1 d-block"></small>
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

<!-- Modal: New / Edit role definition -->
<div class="modal fade" id="modal-role-definition" tabindex="-1" role="dialog" aria-labelledby="modal-role-definition-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title text-white" id="modal-role-definition-title">
                    <i class="fas fa-graduation-cap mr-2"></i>
                    <span id="modal-role-definition-header"><?php echo $lang->get('role_definition'); ?></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="<?php echo $lang->get('close'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="form-role-label"><?php echo $lang->get('label'); ?></label>
                    <input type="text" class="form-control" id="form-role-label" required>
                </div>
                <div class="form-group">
                    <label for="form-complexity-list"><?php echo $lang->get('complexity'); ?></label>
                    <select id="form-complexity-list" class="form-control form-item-control select2" style="width:100%;">
                        <?php
                        foreach (TP_PW_COMPLEXITY as $entry) {
                            echo '<option value="' . $entry[0] . '">' . addslashes($entry[1]) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group mt-2">
                    <input type="checkbox" class="form-check-input form-item-control" id="form-role-privilege">
                    <label class="form-check-label ml-2" for="form-role-privilege">
                        <?php echo $lang->get('role_can_edit_any_visible_item'); ?>
                    </label>
                    <small class="form-text text-muted">
                        <?php echo $lang->get('role_can_edit_any_visible_item_tip'); ?>
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info tp-action" data-action="submit-edition">
                    <i class="fas fa-save mr-1"></i><?php echo $lang->get('submit'); ?>
                </button>
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Delete role confirmation -->
<div class="modal fade" id="modal-role-deletion" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title text-white">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?php echo $lang->get('caution'); ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="<?php echo $lang->get('close'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group mt-2">
                    <input type="checkbox" class="form-check-input form-item-control" id="form-role-delete">
                    <label class="form-check-label ml-2" for="form-role-delete">
                        <?php echo $lang->get('please_confirm_deletion'); ?>
                        <span class="ml-1 font-weight-bold" id="span-role-delete"></span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger tp-action" data-action="submit-deletion">
                    <i class="fas fa-trash mr-1"></i><?php echo $lang->get('submit'); ?>
                </button>
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php if (isset($SETTINGS['enable_ad_users_with_ad_groups']) === true && (int) $SETTINGS['enable_ad_users_with_ad_groups'] === 1 && (int) $session->get('user-admin') === 1): ?>
<!-- Modal: LDAP synchronization -->
<div class="modal fade" id="modal-roles-ldap-sync" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title text-white">
                    <i class="fa-solid fa-address-card mr-2"></i><?php echo $lang->get('ad_groupe_and_roles_mapping'); ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="<?php echo $lang->get('close'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="p-2 border-bottom">
                    <button type="button" class="btn btn-primary btn-sm tp-action" data-action="ldap-refresh">
                        <i class="fa-solid fa-sync-alt mr-2"></i><?php echo $lang->get('refresh'); ?>
                    </button>
                </div>
                <div class="table-responsive" id="ldap-groups-table">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:35%"><i class="fa-solid fa-people-group mr-1"></i><?php echo $lang->get('ad_group'); ?></th>
                                <th style="width:8%"></th>
                                <th><i class="fa-solid fa-graduation-cap mr-1"></i><?php echo $lang->get('mapped_with_role'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="row-ldap-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('close'); ?></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Role rights edit sidebar -->
<div id="role-edit-sidebar">
    <div class="d-flex align-items-center justify-content-between p-3 sidebar-header">
        <h6 class="mb-0">
            <i class="fas fa-graduation-cap mr-2 text-warning" id="sidebar-role-icon"></i>
            <span id="sidebar-role-info" class="font-weight-bold"></span>
        </h6>
        <button type="button" id="sidebar-role-close" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="p-3">
        <div class="form-group">
            <label><?php echo $lang->get('right_types_label'); ?></label>
            <div class="mt-1">
                <input type="radio" class="form-radio-input" id="sb-right-write" name="sb-right" data-type="W">
                <label class="form-radio-label pointer mr-3" for="sb-right-write"><?php echo $lang->get('write'); ?></label>
                <input type="radio" class="form-radio-input" id="sb-right-read" name="sb-right" data-type="R">
                <label class="form-radio-label pointer mr-3" for="sb-right-read"><?php echo $lang->get('read'); ?></label>
                <input type="radio" class="form-radio-input" id="sb-right-noaccess" name="sb-right" data-type="">
                <label class="form-radio-label pointer" for="sb-right-noaccess"><?php echo $lang->get('no_access'); ?></label>
            </div>
        </div>
        <div class="form-group mt-3" id="sb-folder-rights-tuned">
            <div class="form-check">
                <input type="checkbox" class="form-check-input cb-sb-right" id="sb-right-no-delete">
                <label class="form-check-label ml-2" for="sb-right-no-delete"><?php echo $lang->get('role_cannot_delete_item'); ?></label>
            </div>
            <div class="form-check mt-1">
                <input type="checkbox" class="form-check-input cb-sb-right" id="sb-right-no-edit">
                <label class="form-check-label ml-2" for="sb-right-no-edit"><?php echo $lang->get('role_cannot_edit_item'); ?></label>
            </div>
        </div>
        <div class="callout callout-danger mt-3">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="sb-propagate-rights">
                <label class="form-check-label ml-2" for="sb-propagate-rights">
                    <?php echo $lang->get('propagate_rights_to_descendants'); ?>
                </label>
            </div>
        </div>
    </div>
    <div class="p-3 sidebar-footer border-top">
        <button type="button" class="btn btn-warning" id="sidebar-role-submit">
            <i class="fas fa-save mr-1"></i><?php echo $lang->get('submit'); ?>
        </button>
        <button type="button" class="btn btn-default float-right" id="sidebar-role-cancel">
            <?php echo $lang->get('cancel'); ?>
        </button>
    </div>
</div>
<div id="role-edit-overlay"></div>

<style>
/* Progress bar: no transition so bar stays in sync with counter */
#roles-load-progress .progress-bar { transition: none; }

#role-edit-sidebar {
    position: fixed;
    top: 0;
    right: 0;
    width: 390px;
    height: 100vh;
    background: #fff;
    color: #212529;
    border-left: 3px solid #ffc107;
    box-shadow: -4px 0 20px rgba(0,0,0,.15);
    z-index: 1050;
    display: flex;
    flex-direction: column;
    transform: translateX(100%);
    transition: transform .25s cubic-bezier(.4,0,.2,1);
    overflow: hidden;
}
#role-edit-sidebar.open { transform: translateX(0); }
#role-edit-sidebar .sidebar-header { background: #fffbf0; border-bottom: 1px solid #fde8a0; flex-shrink: 0; }
#role-edit-sidebar .p-3:nth-child(2) { overflow-y: auto; flex: 1; }
#role-edit-sidebar .sidebar-footer { flex-shrink: 0; background: #f8f9fa; border-top-color: #dee2e6 !important; }
#role-edit-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.25);
    z-index: 1049;
}
#table-role-details tbody tr.editing-active { background-color: #fffbf0 !important; }

/* iCheck elements inside sidebar: flex layout so label and widget align */
#role-edit-sidebar .form-check {
    display: flex;
    align-items: center;
    padding-left: 0;
    gap: 0.5rem;
}
#role-edit-sidebar .form-check .icheckbox_flat-orange,
#role-edit-sidebar .form-check .iradio_flat-orange { flex-shrink: 0; }
#role-edit-sidebar .form-check label { margin-bottom: 0; }

/* Dark mode overrides */
.dark-mode #role-edit-sidebar {
    background: #343a40;
    color: #fff;
    border-left-color: #f39c12;
    box-shadow: -4px 0 20px rgba(0,0,0,.4);
}
.dark-mode #role-edit-sidebar .sidebar-header {
    background: #3a3f47;
    border-bottom-color: #4c5158 !important;
}
.dark-mode #role-edit-sidebar .sidebar-footer {
    background: #2d3238;
    border-top-color: #4c5158 !important;
}
.dark-mode #role-edit-sidebar label { color: #ced4da; }
.dark-mode #role-edit-sidebar .btn-outline-secondary {
    color: #adb5bd;
    border-color: #6c757d;
}
.dark-mode #role-edit-sidebar .btn-outline-secondary:hover {
    background-color: #6c757d;
    color: #fff;
}
.dark-mode #table-role-details tbody tr.editing-active { background-color: #3d3a28 !important; }
</style>

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
 * @file      folders.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('folders') === false) {
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

// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Ensure Complexity levels are translated
if (defined('TP_PW_COMPLEXITY') === false) {
    define(
        'TP_PW_COMPLEXITY',
        [
            TP_PW_STRENGTH_1 => [TP_PW_STRENGTH_1, $lang->get('complex_level1'), 'fas fa-thermometer-empty text-danger'],
            TP_PW_STRENGTH_2 => [TP_PW_STRENGTH_2, $lang->get('complex_level2'), 'fas fa-thermometer-quarter text-warning'],
            TP_PW_STRENGTH_3 => [TP_PW_STRENGTH_3, $lang->get('complex_level3'), 'fas fa-thermometer-half text-warning'],
            TP_PW_STRENGTH_4 => [TP_PW_STRENGTH_4, $lang->get('complex_level4'), 'fas fa-thermometer-three-quarters text-success'],
            TP_PW_STRENGTH_5 => [TP_PW_STRENGTH_5, $lang->get('complex_level5'), 'fas fa-thermometer-full text-success'],
        ]
    );
}

$complexityHtml = '<div id="hidden-select-complexity" class="hidden"><select id="select-complexity" class="form-control form-item-control save-me">';
$complexitySelect = '';
foreach (TP_PW_COMPLEXITY as $level) {
    $complexitySelect .= '<option value="' . $level[0] . '">' . $level[1] . '</option>';
}
$complexityHtml .= $complexitySelect . '</select></div>';

/* Get full tree structure */
$tst = $tree->getDescendants();
// prepare options list
$droplist = '<option value="na">---' . $lang->get('select') . '---</option>';
if ((int) $session->get('user-admin') === 1 || (int) $session->get('user-manager') === 1 || (int) $session->get('user-can_create_root_folder') === 1) {
    $droplist .= '<option value="0">' . $lang->get('root') . '</option>';
}
foreach ($tst as $t) {
    if (
        in_array($t->id, $session->get('user-accessible_folders')) === true
        && in_array($t->id, $session->get('user-personal_visible_folders')) === false
    ) {
        $droplist .= '<option value="' . $t->id . '">' . addslashes($t->title);
        $text = '';
        foreach ($tree->getPath($t->id, false) as $fld) {
            $text .= empty($text) === true ? '     [' . $fld->title : ' > ' . $fld->title;
        }
        $droplist .= (empty($text) === true ? '' : $text . '</i>]') . '</option>';
    }
}

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-folder-open mr-2"></i><?php echo $lang->get('folders'); ?>
                </h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-middle">
                    <h3 class="card-title">
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="new">
                            <i class="fas fa-plus mr-2"></i><?php echo $lang->get('new'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="delete">
                            <i class="fas fa-trash mr-2"></i><?php echo $lang->get('delete'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="refresh">
                            <i class="fas fa-refresh mr-2"></i><?php echo $lang->get('refresh'); ?>
                        </button>
                    </h3>
                </div>


                <!--<div class="card-header">
                    <h3 class="card-title" id="folders-alphabet"></h3>
                </div>-->
                <!-- /.card-header -->

                <div class="card-body form table-responsive1" id="folders-list">
                    <div class="callout callout-info mt-3">
                        <div class="callout-body row">
                            <div class="input-group input-group-sm col-4">
                                <div class="input-group-prepend">
                                    <div class="input-group-text"><?php echo $lang->get('only_display_folders_to_depth'); ?></div>
                                </div>
                                <select class="form-control form-control-sm" id="folders-depth">
                                </select>
                            </div>
                            <div class="input-group input-group-sm col-4">
                                <div class="input-group-prepend">
                                    <div class="input-group-text">
                                        <i class="fas fa-gavel infotip" title="<?php echo $lang->get('password_strength'); ?>"></i>
                                    </div>
                                </div>
                                <select class="form-control form-control-sm" id="folders-complexity">
                                    <option value="all"><?php echo $lang->get('all'); ?></option>
                                </select>
                            </div>
                            <div class="input-group input-group-sm col-4">
                                <div class="input-group-prepend">
                                    <div class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </div>
                                </div>
                                <input type="text" class="form-control" placeholder="<?php echo $lang->get('find'); ?>" id="folders-search">
                            </div>
                        </div>
                    </div>

                <!-- Folder loading progress bar -->
                <div id="folders-load-progress" class="mt-2 mb-2" style="display:none">
                    <div class="progress" style="height:18px">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                            role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted folders-load-text mt-1 d-block"></small>
                </div>

                    <table id="table-folders" class="table table-hover table-striped table-responsive" style="width:100%">
                        <thead>
                            <tr>
                                <th scope="col" width="80px"></th>
                                <th scope="col" min-width="200px"><?php echo $lang->get('group'); ?></th>
                                <th scope="col" min-width="200px"><?php echo $lang->get('group_parent'); ?></th>
                                <th scope="col" width="50px"><i class="fas fa-gavel fa-lg infotip" title="<?php echo $lang->get('password_strength'); ?>"></i></th>
                                <th scope="col" width="50px"><i class="fas fa-recycle fa-lg infotip" title="<?php echo $lang->get('group_pw_duration') . ' ' . $lang->get('group_pw_duration_tip'); ?>"></i></th>
                                <th scope="col" width="50px"><i class="fas fa-pen fa-lg infotip" title="<?php echo $lang->get('auth_creation_without_complexity'); ?>"></i></th>
                                <th scope="col" width="50px"><i class="fas fa-edit fa-lg infotip" title="<?php echo $lang->get('auth_modification_without_complexity'); ?>"></i></th>
                                <th scope="col" width="50px"><i class="fas fa-folder fa-lg infotip" title="<?php echo $lang->get('icon'); ?>"></i></th>
                                <th scope="col" width="50px"><i class="fas fa-folder-open fa-lg infotip" title="<?php echo $lang->get('icon_on_selection'); ?>"></i></th>
                            </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modal: New folder -->
<div class="modal fade" id="modal-folder-new" tabindex="-1" role="dialog" aria-labelledby="modal-folder-new-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white" id="modal-folder-new-title">
                    <i class="fas fa-folder-plus mr-2"></i><?php echo $lang->get('add_new_folder'); ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="<?php echo $lang->get('close'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="new-title"><?php echo $lang->get('label'); ?></label>
                    <input type="text" class="form-control clear-me purify" id="new-title" data-field="title">
                </div>
                <div class="form-group">
                    <label for="new-parent"><?php echo $lang->get('parent'); ?></label>
                    <select id="new-parent" class="form-control form-item-control no-root" style="width:100%;">
                        <?php echo $droplist; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new-complexity"><?php echo $lang->get('password_minimal_complexity_target'); ?></label>
                    <select id="new-complexity" class="form-control form-item-control no-root" style="width:100%;">
                        <?php echo $complexitySelect; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new-access-right"><?php echo $lang->get('access_right_for_roles'); ?></label>
                    <select id="new-access-right" class="form-control form-item-control no-root" style="width:100%;">
                        <option value=""><?php echo $lang->get('no_access'); ?></option>
                        <option value="R"><?php echo $lang->get('read'); ?></option>
                        <option value="W"><?php echo $lang->get('write'); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new-renewal"><?php echo $lang->get('renewal_delay'); ?></label>
                    <input type="number" class="form-control clear-me" id="new-renewal" value="0" min="0">
                </div>
                <div class="form-group">
                    <label><?php echo $lang->get('icon'); ?></label>
                    <input type="text" class="form-control form-folder-control purify" id="new-folder-add-icon" data-field="icon">
                    <small class="form-text text-muted">
                        <?php echo $lang->get('fontawesome_icon_tip'); ?>
                        <a href="<?php echo FONTAWESOME_URL; ?>" target="_blank"><i class="fas fa-external-link-alt ml-1"></i></a>
                    </small>
                </div>
                <div class="form-group">
                    <label><?php echo $lang->get('icon_on_selection'); ?></label>
                    <input type="text" class="form-control form-folder-control purify" id="new-folder-add-icon-selected" data-field="iconSelected">
                    <small class="form-text text-muted">
                        <?php echo $lang->get('fontawesome_icon_tip'); ?>
                        <a href="<?php echo FONTAWESOME_URL; ?>" target="_blank"><i class="fas fa-external-link-alt ml-1"></i></a>
                    </small>
                </div>
                <div class="form-group">
                    <label><?php echo $lang->get('special'); ?></label>
                    <div class="d-flex align-items-start mb-1">
                        <input type="checkbox" class="form-check-input form-item-control" id="new-add-restriction">
                        <label for="new-add-restriction" class="mb-0 ml-3"><?php echo $lang->get('create_without_password_minimal_complexity_target'); ?></label>
                    </div>
                    <div class="d-flex align-items-start">
                        <input type="checkbox" class="form-check-input form-item-control" id="new-edit-restriction">
                        <label for="new-edit-restriction" class="mb-0 ml-3"><?php echo $lang->get('edit_without_password_minimal_complexity_target'); ?></label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary tp-action" data-action="new-submit">
                    <i class="fas fa-save mr-1"></i><?php echo $lang->get('submit'); ?>
                </button>
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Sidebar overlay -->
<div id="folder-edit-overlay"></div>

<!-- Sidebar: Edit folder -->
<div id="folder-edit-sidebar">
    <div class="sidebar-header d-flex align-items-center justify-content-between px-3 py-2">
        <span><i class="fas fa-folder-open mr-2"></i><strong id="sidebar-folder-name"></strong></span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="sidebar-close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="sidebar-body px-3 py-2">
        <div class="form-group">
            <label for="folder-edit-title"><?php echo $lang->get('label'); ?></label>
            <input type="text" class="form-control purify" id="folder-edit-title" data-field="title">
        </div>
        <div class="form-group">
            <label for="folder-edit-parent"><?php echo $lang->get('parent'); ?></label>
            <select id="folder-edit-parent" class="form-control select2" style="width:100%"></select>
        </div>
        <div class="form-group">
            <label for="folder-edit-complexity"><?php echo $lang->get('password_minimal_complexity_target'); ?></label>
            <select id="folder-edit-complexity" class="form-control select2" style="width:100%"></select>
        </div>
        <div class="form-group">
            <label for="folder-edit-renewal"><?php echo $lang->get('renewal_delay'); ?></label>
            <input type="number" class="form-control" id="folder-edit-renewal" min="0" value="0">
        </div>
        <div class="form-group">
            <label><?php echo $lang->get('icon'); ?></label>
            <input type="text" class="form-control purify" id="folder-edit-icon" data-field="icon">
            <small class="form-text text-muted">
                <?php echo $lang->get('fontawesome_icon_tip'); ?>
                <a href="<?php echo FONTAWESOME_URL; ?>" target="_blank"><i class="fas fa-external-link-alt ml-1"></i></a>
            </small>
        </div>
        <div class="form-group">
            <label><?php echo $lang->get('icon_on_selection'); ?></label>
            <input type="text" class="form-control purify" id="folder-edit-icon-selected" data-field="iconSelected">
            <small class="form-text text-muted">
                <?php echo $lang->get('fontawesome_icon_tip'); ?>
                <a href="<?php echo FONTAWESOME_URL; ?>" target="_blank"><i class="fas fa-external-link-alt ml-1"></i></a>
            </small>
        </div>
        <div class="form-group">
            <label><?php echo $lang->get('special'); ?></label>
            <div class="d-flex align-items-start mb-1">
                <input type="checkbox" class="form-check-input" id="folder-edit-add-restriction">
                <label class="mb-0 ml-3" for="folder-edit-add-restriction">
                    <?php echo $lang->get('create_without_password_minimal_complexity_target'); ?>
                </label>
            </div>
            <div class="d-flex align-items-start">
                <input type="checkbox" class="form-check-input" id="folder-edit-edit-restriction">
                <label class="mb-0 ml-3" for="folder-edit-edit-restriction">
                    <?php echo $lang->get('edit_without_password_minimal_complexity_target'); ?>
                </label>
            </div>
        </div>
    </div>
    <div class="sidebar-footer px-3 py-2 d-flex justify-content-between">
        <button type="button" class="btn btn-warning" id="sidebar-submit">
            <i class="fas fa-save mr-1"></i><?php echo $lang->get('submit'); ?>
        </button>
        <button type="button" class="btn btn-default" id="sidebar-cancel"><?php echo $lang->get('cancel'); ?></button>
    </div>
</div>

<!-- Modal: Delete folders -->
<div class="modal fade" id="modal-folder-delete" tabindex="-1" role="dialog" aria-labelledby="modal-folder-delete-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="modal-folder-delete-title">
                    <i class="fas fa-trash mr-2"></i><?php echo $lang->get('delete_folders'); ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo $lang->get('close'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h6><i class="fas fa-exclamation-triangle text-warning mr-2"></i><?php echo $lang->get('next_list_to_be_deleted'); ?></h6>
                <div id="delete-list" class="mb-3"></div>

                <div class="alert alert-danger mb-0">
                    <h6><i class="icon fa fa-warning mr-2"></i><?php echo $lang->get('caution'); ?></h6>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-red-input form-item-control flat-red" id="delete-confirm">
                        <label class="form-check-label ml-3" for="delete-confirm"><?php echo $lang->get('folder_delete_confirm'); ?></label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger disabled tp-action" data-action="delete-submit" id="delete-submit">
                    <i class="fas fa-trash mr-1"></i><?php echo $lang->get('confirm'); ?>
                </button>
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
            </div>
        </div>
    </div>
</div>

<style>
/* Progress bar: no transition so it stays in sync with the counter */
#folders-load-progress .progress-bar { transition: none; }

/* Sidebar */
#folder-edit-sidebar {
    position: fixed;
    top: 0; right: 0; bottom: 0;
    width: 400px;
    background: #fff;
    box-shadow: -4px 0 16px rgba(0,0,0,.15);
    z-index: 1055;
    display: flex;
    flex-direction: column;
    transform: translateX(100%);
    transition: transform .25s ease;
}
#folder-edit-sidebar.open { transform: translateX(0); }
#folder-edit-sidebar .sidebar-header { border-bottom: 1px solid #dee2e6; flex-shrink: 0; }
#folder-edit-sidebar .sidebar-body   { flex: 1; overflow-y: auto; }
#folder-edit-sidebar .sidebar-footer { border-top: 1px solid #dee2e6; flex-shrink: 0; }

#folder-edit-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.3);
    z-index: 1054;
}

/* Dark mode */
.dark-mode #folder-edit-sidebar { background: #343a40; color: #dee2e6; }
.dark-mode #folder-edit-sidebar .sidebar-header,
.dark-mode #folder-edit-sidebar .sidebar-footer { border-color: #495057; }

/* iCheck widget inside flex rows: prevent shrink so it never clips into the label */
#folder-edit-sidebar .d-flex > [class*="icheckbox"],
#folder-edit-sidebar .d-flex > [class*="iradio"],
#modal-folder-new .d-flex > [class*="icheckbox"],
#modal-folder-new .d-flex > [class*="iradio"] {
    flex-shrink: 0;
}

/* Edited row highlight */
#table-folders tbody tr.editing-active { background-color: #fffbf0 !important; }
.dark-mode #table-folders tbody tr.editing-active { background-color: #3d3a28 !important; }
</style>

<!-- hidden -->
<?php echo $complexityHtml; ?>

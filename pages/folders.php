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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('folders') === false) {
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

                <div class="card-body form hidden" id="folder-new">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $lang->get('add_new_folder'); ?></h3>
                        </div>
                        <!-- /.card-header -->
                        <!-- form start -->
                        <form role="form">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="new-title"><?php echo $lang->get('label'); ?></label>
                                    <input type="text" class="form-control clear-me purify" id="new-title" data-field="title">
                                </div>
                                <div class="form-group">
                                    <label for="new-parent"><?php echo $lang->get('parent'); ?></label>
                                    <select id="new-parent" class="form-control form-item-control select2 no-root" style="width:100%;">
                                        <?php echo $droplist; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="new-complexity"><?php echo $lang->get('password_minimal_complexity_target'); ?></label>
                                    <select id="new-complexity" class="form-control form-item-control select2 no-root" style="width:100%;">
                                        <?php echo $complexitySelect; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="new-access-right"><?php echo $lang->get('access_right_for_roles'); ?></label>
                                    <select id="new-access-right" class="form-control form-item-control select2 no-root" style="width:100%;">
                                        <option value=""><?php echo $lang->get('no_access'); ?></option>
                                        <option value="R"><?php echo $lang->get('read'); ?></option>
                                        <option value="W"><?php echo $lang->get('write'); ?></option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="new-renewal"><?php echo $lang->get('renewal_delay'); ?></label>
                                    <input type="number" class="form-control clear-me" id="new-renewal" value="0" min="0" data-bind="value:replyNumber">
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang->get('icon'); ?></label>
                                    <input type="text" class="form-control form-folder-control purify" id="new-folder-add-icon" data-field="icon">
                                    <small class='form-text text-muted'>
                                        <?php echo $lang->get('fontawesome_icon_tip'); ?><a href="<?php echo FONTAWESOME_URL;?>" target="_blank"><i class="fas fa-external-link-alt ml-1"></i></a>
                                    </small>
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang->get('icon_on_selection'); ?></label>
                                    <input type="text" class="form-control form-folder-control purify" id="new-folder-add-icon-selected" data-field="iconSelected">
                                    <small class='form-text text-muted'>
                                        <?php echo $lang->get('fontawesome_icon_tip'); ?><a href="<?php echo FONTAWESOME_URL;?>" target="_blank"><i class="fas fa-external-link-alt ml-1"></i></a>
                                    </small>
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang->get('special'); ?></label>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input form-control" id="new-add-restriction">
                                        <label for="new-add-restriction" class="form-check-label pointer ml-2"><?php echo $lang->get('create_without_password_minimal_complexity_target'); ?></label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input form-control" id="new-edit-restriction">
                                        <label for="new-edit-restriction" class="form-check-label pointer ml-2"><?php echo $lang->get('edit_without_password_minimal_complexity_target'); ?></label>
                                    </div>
                                </div>
                            </div>
                            <!-- /.card-body -->

                            <div class="card-footer">
                                <button type="button" class="btn btn-primary tp-action" data-action="new-submit"><?php echo $lang->get('submit'); ?></button>
                                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo $lang->get('cancel'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-body form hidden" id="folder-delete">
                    <div class="card card-warning">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $lang->get('delete_folders'); ?></h3>
                        </div>
                        <!-- /.card-header -->
                        <!-- form start -->
                        <form role="form">
                            <div class="card-body">
                                <div class="form-group">
                                    <h5><i class="fas fa-warning mr-2"></i><?php echo $lang->get('next_list_to_be_deleted'); ?></h5>
                                    <div id="delete-list" class="clear-me"></div>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="alert alert-danger">
                                    <h5><i class="icon fa fa-warning mr-2"></i><?php echo $lang->get('caution'); ?></h5>
                                    <div class="form-check mb-3">
                                        <input type="checkbox" class="form-check-red-input form-item-control flat-red required" id="delete-confirm">
                                        <label class="form-check-label ml-3" for="delete-confirm"><?php echo $lang->get('folder_delete_confirm'); ?></label>
                                    </div>
                                </div>
                            </div>
                            <!-- /.card-body -->

                            <div class="card-footer">
                                <button type="button" class="btn btn-danger disabled tp-action" data-action="delete-submit" id="delete-submit"><?php echo $lang->get('confirm'); ?></button>
                                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo $lang->get('cancel'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>

                <!--<div class="card-header">
                    <h3 class="card-title" id="folders-alphabet"></h3>
                </div>-->
                <!-- /.card-header -->

                <div class="card-body form table-responsive1" id="folders-list">
                    <div class="callout callout-info mt-3">
                        <div class="callout-body row">
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
                                        <i class="fas fa-search"></i>
                                    </div>
                                </div>
                                <input type="text" class="form-control" placeholder="<?php echo $lang->get('find'); ?>" id="folders-search">
                            </div>
                        </div>
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

<!-- hidden -->
<?php echo $complexityHtml; ?>

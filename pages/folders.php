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
 * @file      folders.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */


use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => returnIfSet($superGlobal->get('type', 'POST')),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($superGlobal->get('user_id', 'SESSION'), null),
        'user_key' => returnIfSet($superGlobal->get('key', 'SESSION'), null),
        'CPM' => returnIfSet($superGlobal->get('CPM', 'SESSION'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('folders') === false) {
    // Not allowed page
    $superGlobal->put('code', ERR_NOT_ALLOWED, 'SESSION', 'error');
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Load language file
require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$superGlobal->get('user_language', 'SESSION', 'user').'.php';

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
            TP_PW_STRENGTH_1 => [TP_PW_STRENGTH_1, langHdl('complex_level1'), 'fas fa-thermometer-empty text-danger'],
            TP_PW_STRENGTH_2 => [TP_PW_STRENGTH_2, langHdl('complex_level2'), 'fas fa-thermometer-quarter text-warning'],
            TP_PW_STRENGTH_3 => [TP_PW_STRENGTH_3, langHdl('complex_level3'), 'fas fa-thermometer-half text-warning'],
            TP_PW_STRENGTH_4 => [TP_PW_STRENGTH_4, langHdl('complex_level4'), 'fas fa-thermometer-three-quarters text-success'],
            TP_PW_STRENGTH_5 => [TP_PW_STRENGTH_5, langHdl('complex_level5'), 'fas fa-thermometer-full text-success'],
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
$droplist = '<option value="na">---' . langHdl('select') . '---</option>';
if ((int) $_SESSION['is_admin'] === 1 || (int) $_SESSION['user_manager'] === 1 || (int) $_SESSION['can_create_root_folder'] === 1) {
    $droplist .= '<option value="0">' . langHdl('root') . '</option>';
}
foreach ($tst as $t) {
    if (
        in_array($t->id, $_SESSION['groupes_visibles']) === true
        && in_array($t->id, $_SESSION['personal_visible_groups']) === false
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
                    <i class="fas fa-folder-open mr-2"></i><?php echo langHdl('folders'); ?>
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
                            <i class="fas fa-plus mr-2"></i><?php echo langHdl('new'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="delete">
                            <i class="fas fa-trash mr-2"></i><?php echo langHdl('delete'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="refresh">
                            <i class="fas fa-refresh mr-2"></i><?php echo langHdl('refresh'); ?>
                        </button>
                    </h3>
                </div>

                <div class="card-body form hidden" id="folder-new">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo langHdl('add_new_folder'); ?></h3>
                        </div>
                        <!-- /.card-header -->
                        <!-- form start -->
                        <form role="form">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="new-title"><?php echo langHdl('label'); ?></label>
                                    <input type="text" class="form-control clear-me purify" id="new-title" data-field="title">
                                </div>
                                <div class="form-group">
                                    <label for="new-parent"><?php echo langHdl('parent'); ?></label>
                                    <select id="new-parent" class="form-control form-item-control select2 no-root" style="width:100%;">
                                        <?php echo $droplist; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="new-complexity"><?php echo langHdl('password_minimal_complexity_target'); ?></label>
                                    <select id="new-complexity" class="form-control form-item-control select2 no-root" style="width:100%;">
                                        <?php echo $complexitySelect; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="new-access-right"><?php echo langHdl('access_right_for_roles'); ?></label>
                                    <select id="new-access-right" class="form-control form-item-control select2 no-root" style="width:100%;">
                                        <option value=""><?php echo langHdl('no_access'); ?></option>
                                        <option value="R"><?php echo langHdl('read'); ?></option>
                                        <option value="W"><?php echo langHdl('write'); ?></option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="new-renewal"><?php echo langHdl('renewal_delay'); ?></label>
                                    <input type="number" class="form-control clear-me" id="new-renewal" value="0" min="0" data-bind="value:replyNumber">
                                </div>
                                <div class="form-group">
                                    <label><?php echo langHdl('icon'); ?></label>
                                    <input type="text" class="form-control form-folder-control purify" id="new-folder-add-icon" data-field="icon">
                                    <small class='form-text text-muted'>
                                        <?php echo langHdl('fontawesome_icon_tip'); ?><a href="<?php echo FONTAWESOME_URL;?>" target="_blank"><i class="fas fa-external-link-alt ml-1"></i></a>
                                    </small>
                                </div>
                                <div class="form-group">
                                    <label><?php echo langHdl('icon_on_selection'); ?></label>
                                    <input type="text" class="form-control form-folder-control purify" id="new-folder-add-icon-selected" data-field="iconSelected">
                                    <small class='form-text text-muted'>
                                        <?php echo langHdl('fontawesome_icon_tip'); ?><a href="<?php echo FONTAWESOME_URL;?>" target="_blank"><i class="fas fa-external-link-alt ml-1"></i></a>
                                    </small>
                                </div>
                                <div class="form-group">
                                    <label><?php echo langHdl('special'); ?></label>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input form-control" id="new-add-restriction">
                                        <label for="new-add-restriction" class="form-check-label pointer ml-2"><?php echo langHdl('create_without_password_minimal_complexity_target'); ?></label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input form-control" id="new-edit-restriction">
                                        <label for="new-edit-restriction" class="form-check-label pointer ml-2"><?php echo langHdl('edit_without_password_minimal_complexity_target'); ?></label>
                                    </div>
                                </div>
                            </div>
                            <!-- /.card-body -->

                            <div class="card-footer">
                                <button type="button" class="btn btn-primary tp-action" data-action="new-submit"><?php echo langHdl('submit'); ?></button>
                                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo langHdl('cancel'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-body form hidden" id="folder-delete">
                    <div class="card card-warning">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo langHdl('delete_folders'); ?></h3>
                        </div>
                        <!-- /.card-header -->
                        <!-- form start -->
                        <form role="form">
                            <div class="card-body">
                                <div class="form-group">
                                    <h5><i class="fas fa-warning mr-2"></i><?php echo langHdl('next_list_to_be_deleted'); ?></h5>
                                    <div id="delete-list" class="clear-me"></div>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="alert alert-danger">
                                    <h5><i class="icon fa fa-warning mr-2"></i><?php echo langHdl('caution'); ?></h5>
                                    <div class="form-check mb-3">
                                        <input type="checkbox" class="form-check-red-input form-item-control flat-red required" id="delete-confirm">
                                        <label class="form-check-label ml-3" for="delete-confirm"><?php echo langHdl('folder_delete_confirm'); ?></label>
                                    </div>
                                </div>
                            </div>
                            <!-- /.card-body -->

                            <div class="card-footer">
                                <button type="button" class="btn btn-danger disabled tp-action" data-action="delete-submit" id="delete-submit"><?php echo langHdl('confirm'); ?></button>
                                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo langHdl('cancel'); ?></button>
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
                                    <div class="input-group-text"><?php echo langHdl('only_display_folders_to_depth'); ?></div>
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
                                <input type="text" class="form-control" placeholder="<?php echo langHdl('find'); ?>" id="folders-search">
                            </div>
                        </div>
                    </div>
                    <table id="table-folders" class="table table-hover table-striped table-responsive" style="width:100%">
                        <thead>
                            <tr>
                                <th scope="col" width="80px"></th>
                                <th scope="col" min-width="200px"><?php echo langHdl('group'); ?></th>
                                <th scope="col" min-width="200px"><?php echo langHdl('group_parent'); ?></th>
                                <th scope="col" width="50px"><i class="fas fa-gavel fa-lg infotip" title="<?php echo langHdl('password_strength'); ?>"></i></th>
                                <th scope="col" width="50px"><i class="fas fa-recycle fa-lg infotip" title="<?php echo langHdl('group_pw_duration') . ' ' . langHdl('group_pw_duration_tip'); ?>"></i></th>
                                <th scope="col" width="50px"><i class="fas fa-pen fa-lg infotip" title="<?php echo langHdl('auth_creation_without_complexity'); ?>"></i></th>
                                <th scope="col" width="50px"><i class="fas fa-edit fa-lg infotip" title="<?php echo langHdl('auth_modification_without_complexity'); ?>"></i></th>
                                <th scope="col" width="50px"><i class="fas fa-folder fa-lg infotip" title="<?php echo langHdl('icon'); ?>"></i></th>
                                <th scope="col" width="50px"><i class="fas fa-folder-open fa-lg infotip" title="<?php echo langHdl('icon_on_selection'); ?>"></i></th>
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

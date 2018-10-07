<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2018 Nils Laumaillé
* @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
*
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
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
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'folders', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

// Load template
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

// Ensure Complexity levels are translated
if (defined('TP_PW_COMPLEXITY') === false) {
    define(
        'TP_PW_COMPLEXITY',
        array(
            0 => array(0, langHdl('complex_level0'), '<i class="fa fa-bolt text-danger"></i>'),
            25 => array(25, langHdl('complex_level1'), '<i class="fa fa-thermometer-0 text-danger"></i>'),
            50 => array(50, langHdl('complex_level2'), '<i class="fa fa-thermometer-1 text-warning"></i>'),
            60 => array(60, langHdl('complex_level3'), '<i class="fa fa-thermometer-2 text-warning"></i>'),
            70 => array(70, langHdl('complex_level4'), '<i class="fa fa-thermometer-3 text-success"></i>'),
            80 => array(80, langHdl('complex_level5'), '<i class="fa fa-thermometer-4 text-success"></i>'),
            90 => array(90, langHdl('complex_level6'), '<i class="fa fa-diamond text-success"></i>'),
        )
    );
}

$complexityHtml = '<div id="hidden-select-complexity" class="hidden"><select id="select-complexity" class="form-control form-item-control save-me">';
$complexitySelect = '';
foreach (TP_PW_COMPLEXITY as $level) {
    $complexitySelect .= '<option value="'.$level[0].'">'.$level[1].'</option>';
}
$complexityHtml .= $complexitySelect.'</select></div>';

// Prepare folders
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

/* Get full tree structure */
$tst = $tree->getDescendants();

// prepare options list
$prev_level = 0;
$droplist = '<option value="na">---'.langHdl('select').'---</option>';
if ($_SESSION['is_admin'] === '1' || $_SESSION['user_manager'] === '1' || $_SESSION['can_create_root_folder'] === '1') {
    $droplist .= '<option value="0">'.langHdl('root').'</option>';
}
foreach ($tst as $t) {
    if (in_array($t->id, $_SESSION['groupes_visibles']) === true
        && in_array($t->id, $_SESSION['personal_visible_groups']) === false
    ) {
        $droplist .= '<option value="'.$t->id.'">'.addslashes($t->title);
        $text = '';
        foreach ($tree->getPath($t->id, false) as $fld) {
            $text .= empty($text) === true ? ' ['.$fld->title : ' > '.$fld->title;
        }
        $droplist .= (empty($text) === true ? '' : $text.'</i>]').'</option>';
    }
}

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6">
        <h1 class="m-0 text-dark">
        <i class="fa fa-folder-open-o mr-2"></i><?php echo langHdl('folders'); ?>
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
                            <i class="fa fa-plus mr-2"></i><?php echo langHdl('new'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="delete">
                            <i class="fa fa-trash mr-2"></i><?php echo langHdl('delete'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="refresh">
                            <i class="fa fa-refresh mr-2"></i><?php echo langHdl('refresh'); ?>
                        </button>
                    </h3>
                </div>

                <div class="card-body form hidden" id="folders-new">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo langHdl('add_new_folder'); ?></h3>
                        </div>
                        <!-- /.card-header -->
                        <!-- form start -->
                        <form role="form">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="new-label"><?php echo langHdl('label'); ?></label>
                                    <input type="text" class="form-control clear-me" id="new-label">
                                </div>
                                <div class="form-group">
                                    <label for="new-parent"><?php echo langHdl('parent'); ?></label>
                                    <select id="new-parent" class="form-control form-item-control select2 no-root" style="width:100%;">
                                    <?php echo $droplist; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="new-minimal-complexity"><?php echo langHdl('password_minimal_complexity_target'); ?></label>
                                    <select id="new-minimal-complexity" class="form-control form-item-control select2 no-root" style="width:100%;">
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
                                    <label for="new-duration"><?php echo langHdl('password_life_duration'); ?></label>
                                    <input type="number" class="form-control reset-me" id="new-duration" value="0" min="0" data-bind="value:replyNumber">
                                </div>
                                <div class="form-group">
                                    <label for="new-create-without"><?php echo langHdl('create_without_password_minimal_complexity_target'); ?></label>
                                    <select id="new-create-without" class="form-control form-item-control select2 no-root" style="width:100%;">
                                        <option value="0"><?php echo langHdl('no'); ?></option>
                                        <option value="1"><?php echo langHdl('yes'); ?></option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="new-edit-without"><?php echo langHdl('edit_without_password_minimal_complexity_target'); ?></label>
                                    <select id="new-edit-without" class="form-control form-item-control select2 no-root" style="width:100%;">
                                        <option value="0"><?php echo langHdl('no'); ?></option>
                                        <option value="1"><?php echo langHdl('yes'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <!-- /.card-body -->
                            
                            <div class="card-footer">
                                <button type="button" class="btn btn-primary" data-action="new-submit"><?php echo langHdl('submit'); ?></button>
                                <button type="button" class="btn btn-default float-right" data-action="cancel"><?php echo langHdl('cancel'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-body form hidden" id="folders-delete">
                    <div class="card card-warning">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo langHdl('delete_folders'); ?></h3>
                        </div>
                        <!-- /.card-header -->
                        <!-- form start -->
                        <form role="form">
                            <div class="card-body">
                                <div class="form-group">
                                    <h5><i class="fa fa-warning mr-2"></i><?php echo langHdl('next_list_to_be_deleted'); ?></h5>
                                    <div id="delete-list" class="clear-me"></div>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h5><i class="icon fa fa-warning mr-2"></i><?php echo langHdl('caution'); ?></h5>
                                    <div class="form-check mb-3">
                                        <input type="checkbox" class="form-check-input form-item-control flat-red required" id="delete-confirm">
                                        <label class="form-check-label ml-3" for="delete-confirm"><?php echo langHdl('folder_delete_confirm'); ?></label>
                                    </div>
                                </div>
                            </div>
                            <!-- /.card-body -->
                            
                            <div class="card-footer">
                                <button type="button" class="btn btn-warning disabled" data-action="delete-submit" id="delete-submit"><?php echo langHdl('confirm'); ?></button>
                                <button type="button" class="btn btn-default float-right" data-action="cancel"><?php echo langHdl('cancel'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>


                <!--<div class="card-header">
                    <h3 class="card-title" id="folders-alphabet"></h3>
                </div>-->
                <!-- /.card-header -->
                <div class="card-body form" id="folders-list">
                    <table id="table-folders" class="table table-bordered table-striped" style="width:100%">
                        <thead>
                        <tr>
                            <th></th>
                            <th><?php echo langHdl('group'); ?></th>
                            <th><?php echo langHdl('group_parent'); ?></th>
                            <th><i class="fa fa-gavel fa-lg infotip" title="<?php echo langHdl('password_strength'); ?>"></i></th>
                            <th><i class="fa fa-recycle fa-lg infotip" title="<?php echo langHdl('group_pw_duration').' '.langHdl('group_pw_duration_tip'); ?>"></i></th>
                            <th><i class="fa fa-pencil fa-lg infotip" title="<?php echo langHdl('auth_creation_without_complexity'); ?>"></i></th>
                            <th><i class="fa fa-pencil-square-o fa-lg infotip" title="<?php echo langHdl('auth_modification_without_complexity'); ?>"></i></th>
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

<?php
/**
 * Teampass - a collaborative passwords manager
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 * @package   Items.php
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
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], curPage())) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit();
}

// Load
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

// Prepare GET variables
$get_group = $superGlobal->get("group", "GET");
$get_id = $superGlobal->get("id", "GET");

// Prepare SESSION variables
$session_user_admin = $superGlobal->get("user_admin", "SESSION");


if ($session_user_admin === '1'
    && (null !== TP_ADMIN_FULL_RIGHT && TP_ADMIN_FULL_RIGHT === '1')
    || null === TP_ADMIN_FULL_RIGHT
) {
    $_SESSION['groupes_visibles'] = $_SESSION['personal_visible_groups'];
    $_SESSION['groupes_visibles_list'] = implode(',', $_SESSION['groupes_visibles']);
}


// Get list of users
$usersList = array();
$rows = DB::query("SELECT id,login,email FROM ".prefixTable('users')." ORDER BY login ASC");
foreach ($rows as $record) {
    $usersList[$record['login']] = array(
        "id" => $record['id'],
        "login" => $record['login'],
        "email" => $record['email'],
        );
}
// Get list of roles
$arrRoles = array();
$listRoles = "";
$rows = DB::query("SELECT id,title FROM ".prefixTable('roles_title')." ORDER BY title ASC");
foreach ($rows as $reccord) {
    $arrRoles[$reccord['title']] = array(
        'id' => $reccord['id'],
        'title' => $reccord['title']
        );
    if (empty($listRoles)) {
        $listRoles = $reccord['id'].'#'.$reccord['title'];
    } else {
        $listRoles .= ';'.$reccord['id'].'#'.$reccord['title'];
    }
}

// Hidden things
echo '
<input type="hidden" name="hid_cat" id="hid_cat" value="', $get_group !== null ? $get_group : "", '" />
<input type="hidden" id="complexite_groupe" value="" />
<input type="hidden" name="selected_items" id="selected_items" value="" />
<input type="hidden" id="bloquer_creation_complexite" value="" />
<input type="hidden" id="bloquer_modification_complexite" value="" />
<input type="hidden" id="error_detected" value="" />
<input type="hidden" name="random_id" id="random_id" value="" />
<input type="hidden" id="edit_wysiwyg_displayed" value="" />
<input type="hidden" id="richtext_on" value="1" />
<input type="hidden" id="query_next_start" value="0" />
<input type="hidden" id="display_categories" value="0" />
<input type="hidden" id="nb_items_to_display_once" value="', isset($SETTINGS['nb_items_by_query']) ? htmlspecialchars($SETTINGS['nb_items_by_query']) : 'auto', '" />
<input type="hidden" id="user_is_read_only" value="', isset($_SESSION['user_read_only']) && $_SESSION['user_read_only'] == 1 ? '1' : '', '" />
<input type="hidden" id="request_ongoing" value="" />
<input type="hidden" id="request_lastItem" value="" />
<input type="hidden" id="item_editable" value="" />
<input type="hidden" id="timestamp_item_displayed" value="" />
<input type="hidden" id="pf_selected" value="" />
<input type="hidden" id="user_ongoing_action" value="" />
<input type="hidden" id="input_list_roles" value="'.htmlentities($listRoles).'" />
<input type="hidden" id="path_fontsize" value="" />
<input type="hidden" id="access_level" value="" />
<input type="hidden" id="empty_clipboard" value="" />
<input type="hidden" id="selected_folder_is_personal" value="" />
<input type="hidden" id="personal_visible_groups_list" value="', isset($_SESSION['personal_visible_groups_list']) ? $_SESSION['personal_visible_groups_list'] : "", '" />
<input type="hidden" id="create_item_without_password" value="', isset($SETTINGS['create_item_without_password']) ? $SETTINGS['create_item_without_password'] : "0", '" />';
// Hidden objects for Item search
if ($get_group !== null && $get_id !== null) {
    echo '
    <input type="hidden" name="open_folder" id="open_folder" value="'.$get_group.'" />
    <input type="hidden" name="open_id" id="open_id" value="'.$get_id.'" />
    <input type="hidden" name="recherche_group_pf" id="recherche_group_pf" value="', in_array($get_group, $_SESSION['personal_visible_groups']) ? '1' : '0', '" />
    <input type="hidden" name="open_item_by_get" id="open_item_by_get" value="true" />';
} elseif ($get_group !== null && $get_id === null) {
    echo '<input type="hidden" name="open_folder" id="open_folder" value="'.$get_group.'" />';
    echo '<input type="hidden" name="open_id" id="open_id" value="" />';
    echo '<input type="hidden" name="recherche_group_pf" id="recherche_group_pf" value="', in_array($get_group, $_SESSION['personal_visible_groups']) ? '1' : '0', '" />';
    echo '<input type="hidden" name="open_item_by_get" id="open_item_by_get" value="" />';
} else {
    echo '<input type="hidden" name="open_folder" id="open_folder" value="" />';
    echo '<input type="hidden" name="open_id" id="open_id" value="" />';
    echo '<input type="hidden" name="recherche_group_pf" id="recherche_group_pf" value="" />';
    echo '<input type="hidden" name="open_item_by_get" id="open_item_by_get" value="" />';
}
// Is personal SK available
echo '
<input type="hidden" name="personal_sk_set" id="personal_sk_set" value="', isset($_SESSION['user_settings']['session_psk']) && !empty($_SESSION['user_settings']['session_psk']) ? '1' : '0', '" />
<input type="hidden" id="personal_upgrade_needed" value="', isset($SETTINGS['enable_pf_feature']) && $SETTINGS['enable_pf_feature'] == 1 && $session_user_admin !== '1' && isset($_SESSION['user_upgrade_needed']) && $_SESSION['user_upgrade_needed'] == 1 ? '1' : '0', '" />';
// define what group todisplay in Tree
if (isset($_COOKIE['jstree_select']) && !empty($_COOKIE['jstree_select'])) {
    $firstGroup = str_replace("#li_", "", $_COOKIE['jstree_select']);
} else {
    $firstGroup = "";
}

echo '
<input type="hidden" name="jstree_group_selected" id="jstree_group_selected" value="'.htmlspecialchars($firstGroup).'" />
<input type="hidden" id="item_user_token" value="" />
<input type="hidden" id="items_listing_should_stop" value="" />
<input type="hidden" id="new_listing_characteristics" value="" />
<input type="hidden" id="uniqueLoadData" value="" />';

?>

    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark"><?php echo langHdl('items'); ?></h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right" id="items_folder_path"></ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->


    <!-- Main content -->
    <section class="content">
        <!-- ITEM DETAILS -->
        <div class="row hidden item-details-card">
            <div class="col-12">
                <div class="callout callout-info align-middle">
                    <button type="button" class="btn btn-gray btn-lg but-back-to-list float-left">
                        <i class="fa fa-arrow-left"></i>
                    </button>
                    <div class="btn-group float-left">
                        <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                            <i class="fa fa-bars"></i>
                            <span class="caret"></span>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item tp-action" href="#" data-item-action="add"><i class="fa fa-plus"></i>&nbsp;<?php echo langHdl('item_menu_add_elem');?></a>
                            <a class="dropdown-item tp-action" href="#" data-item-action="edit"><i class="fa fa-pencil"></i>&nbsp;<?php echo langHdl('item_menu_edi_elem');?></a>
                            <a class="dropdown-item tp-action" href="#" data-item-action="delete"><i class="fa fa-trash"></i>&nbsp;<?php echo langHdl('item_menu_del_elem');?></a>
                            <a class="dropdown-item tp-action" href="#" data-item-action="copy"><i class="fa fa-copy"></i>&nbsp;<?php echo langHdl('item_menu_copy_elem');?></a>
                        </div>
                    </div>
                    <h1 class="ml-1 align-middle" id="card-item-label"></h1>
                </div>
            </div>
        </div>
        <div class="row hidden item-details-card">
            <div class="col-md-5">
                <div class="card card-primary card-outline">
                    <div class="card-body">
                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item">
                                <b>Followers</b> <a class="float-right">1,322</a>
                            </li>
                            <li class="list-group-item">
                                <b>Following</b> <a class="float-right">543</a>
                            </li>
                            <li class="list-group-item">
                                <b>Friends</b> <a class="float-right">13,287</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card">
                    <div class="card-body">
                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item">
                                <b>Followers</b> <a class="float-right">1,322</a>
                            </li>
                            <li class="list-group-item">
                                <b>Following</b> <a class="float-right">543</a>
                            </li>
                            <li class="list-group-item">
                                <b>Friends</b> <a class="float-right">13,287</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="row hidden item-details-card">
            <div class="col-12">                
                <div class="card card-default">
                    <div class="card-header bg-secondary-gradient">
                        
<h1 class="card-title"></h1>
                        <!-- tools box -->
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool btn-sm but-back-to-list">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                        <!-- /. tools -->
                    </div>

                    <div class="card-body">
                        <div class="callout callout-info visible" id="item-card-description">
                        </div>
                        
                        <p>
                            <div class="card card-default">
                                <div class="card-header bg-gray">
                                    <h3 class="card-title" id="card-item-label">Generalities</h3>

                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool bg-gray" data-widget="collapse"><i class="fa fa-plus"></i>
                                        </button>
                                    </div>
                                    <!-- /.card-tools -->
                                </div>
                                <!-- /.card-header -->
                                <div class="card-body">
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                                        </div>
                                        <input type="email" class="form-control" placeholder="Email">
                                    </div>
                                </div>
                                <!-- /.card-body -->
                            </div>
                        </p>
                        
                        <p>
                            <div class="card card-default collapsed-card">
                                <div class="card-header bg-gray">
                                    <h3 class="card-title" id="card-item-label">History</h3>

                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool bg-gray" data-widget="collapse"><i class="fa fa-minus"></i>
                                        </button>
                                    </div>
                                    <!-- /.card-tools -->
                                </div>
                                <!-- /.card-header -->
                                <div class="card-body">
                                    The body of the card
                                </div>
                                <!-- /.card-body -->
                            </div>
                        </p>

                        <p>
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <button type="button" class="btn btn-warning">Copy</button>
                                </div>
                                <!-- /btn-group -->
                                <input type="text" class="form-control" placeholder="OTV link">
                            </div>
                        </p>
                    </div>

                    <div class="card-footer">
                    </div>
                </div>
            </div>
        </div>

                        

        <div class="row h-25" id="folders-tree-card">
            <div class="col-md-3">
                <div class="card card-info card-outline">
                    <div class="card-header">
                        <div class="row justify-content-end">
                            <div class="col-6">
                                <h3 class="card-title">Folders
                            </div>
                            <div class="col-6">
                                <div class="btn-group float-right">
                                    <button type="button" class="btn btn-info btn-sm dropdown-toggle" data-toggle="dropdown">
                                        <i class="fa fa-bars"></i>
                                        <span class="caret"></span>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item tp-action" href="#" data-folder-action="refresh"><i class="fa fa-refresh"></i>&nbsp;<?php echo langHdl('refresh');?></a>
                                        <a class="dropdown-item tp-action" href="#" data-folder-action="add"><i class="fa fa-plus"></i>&nbsp;<?php echo langHdl('refresh');?></a>
                                        <a class="dropdown-item tp-action" href="#" data-folder-action="expand"><i class="fa fa-expand"></i>&nbsp;<?php echo langHdl('expand');?></a>
                                        <a class="dropdown-item tp-action" href="#" data-folder-action="collapse"><i class="fa fa-compress"></i>&nbsp;<?php echo langHdl('collapse');?></a>
                                        <a class="dropdown-item tp-action" href="#" data-folder-action="">
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control" placeholder="<?php echo langHdl('find');?>" id="jstree_search">
                                                <div class="input-group-append">
                                                    <div class="btn btn-primary">
                                                        <i class="fa fa-search"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0" style="">
                        <!-- FOLDERS PLACE -->
                        <div id="jstree" style="overflow:auto;"></div>
                    </div>
                </div><!-- /.card -->
            </div>
            <!-- /.col-md-6 -->
            <div class="col-md-9">
            <div class="card card-primary card-outline" id="items-list-card">
                <div class="card-header">
                    <div class="card-title">
                        <div class="row justify-content-start">
                            <div class="col">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-sm tp-action"
                                        data-folder-action="refresh">
                                        <i class="fa fa-plus"></i>&nbsp;<?php echo langHdl('refresh');?>
                                    </button>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" placeholder="<?php echo langHdl('find');?>" id="find_items">
                                    <div class="input-group-append">
                                        <div class="btn btn-primary" id="find_items_button">
                                            <i class="fa fa-search"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="table_teampass_items_list">
                        <tbody id="teampass_items_list">
                            <!-- ITEMS come here -->
                        </tbody>
                        </table>
                        <!-- /.table -->
                    </div>
                    
                    <div class="form-group row justify-content-md-center hidden" id="info_teampass_items_list"></div>
                <!-- /.mail-box-messages -->
                </div>
                <!-- /.card-body -->
                <div class="card-footer p-0">
                </div>
            </div>
            <!-- /. box -->
        </div>
        <!-- /.col -->
        
        
    </section>
    <!-- /.content -->






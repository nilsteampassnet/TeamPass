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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], curPage($SETTINGS), $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

// Load
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

// Prepare GET variables
$get_group = $superGlobal->get('group', 'GET');
$get_id = $superGlobal->get('id', 'GET');

// Prepare SESSION variables
$session_user_admin = $superGlobal->get('user_admin', 'SESSION');
$session_user_upgrade_needed = $superGlobal->get('user_upgrade_needed', 'SESSION');

// Prepare COOKIE variables
$cookie_jstree_select = $superGlobal->get('jstree_select', 'COOKIE');

if ($session_user_admin === '1'
    && (null !== TP_ADMIN_FULL_RIGHT && TP_ADMIN_FULL_RIGHT === true)
    || null === TP_ADMIN_FULL_RIGHT
) {
    $_SESSION['groupes_visibles'] = $_SESSION['personal_visible_groups'];
    $_SESSION['groupes_visibles_list'] = implode(',', $_SESSION['groupes_visibles']);
}

// Get list of users
$usersList = array();
$rows = DB::query('SELECT id,login,email FROM '.prefixTable('users').' ORDER BY login ASC');
foreach ($rows as $record) {
    $usersList[$record['login']] = array(
        'id' => $record['id'],
        'login' => $record['login'],
        'email' => $record['email'],
        );
}
// Get list of roles
$arrRoles = array();
$listRoles = '';
$rows = DB::query('SELECT id,title FROM '.prefixTable('roles_title').' ORDER BY title ASC');
foreach ($rows as $reccord) {
    $arrRoles[$reccord['title']] = array(
        'id' => $reccord['id'],
        'title' => $reccord['title'],
        );
    if (empty($listRoles)) {
        $listRoles = $reccord['id'].'#'.$reccord['title'];
    } else {
        $listRoles .= ';'.$reccord['id'].'#'.$reccord['title'];
    }
}

/*// Hidden objects for Item search
if ($get_group !== null && $get_id !== null) {
    echo '
    <input type="hidden" name="open_folder" id="open_folder" value="'.$get_group.'" />
    <input type="hidden" name="open_id" id="open_id" value="'.$get_id.'" />
    <input type="hidden" name="folder_requests_psk" id="folder_requests_psk" value="', in_array($get_group, $_SESSION['personal_visible_groups']) ? '1' : '0', '" />
    <input type="hidden" name="open_item_by_get" id="open_item_by_get" value="true" />';
} elseif ($get_group !== null && $get_id === null) {
    echo '<input type="hidden" name="open_folder" id="open_folder" value="'.$get_group.'" />';
    echo '<input type="hidden" name="open_id" id="open_id" value="" />';
    echo '<input type="hidden" name="folder_requests_psk" id="folder_requests_psk" value="', in_array($get_group, $_SESSION['personal_visible_groups']) ? '1' : '0', '" />';
    echo '<input type="hidden" name="open_item_by_get" id="open_item_by_get" value="" />';
} else {
    echo '<input type="hidden" name="open_folder" id="open_folder" value="" />';
    echo '<input type="hidden" name="open_id" id="open_id" value="" />';
    echo '<input type="hidden" name="open_item_by_get" id="open_item_by_get" value="" />';
}
*/
// Is personal SK available
echo '
<input type="hidden" id="personal_upgrade_needed" value="', isset($SETTINGS['enable_pf_feature']) && $SETTINGS['enable_pf_feature'] == 1 && $session_user_admin !== '1' && $session_user_upgrade_needed == 1 ? 1 : 0, '" />';

?>

    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-2">
                    <h1 class="m-0 text-dark"><?php echo langHdl('items'); ?></h1>
                </div><!-- /.col -->
                <div class="col-sm-10">
                    <ol class="breadcrumb float-sm-right" id="form-folder-path"></ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->


    <!-- Main content -->
    <section class="content">

        <!-- ITEM FORM -->
        <div class="row hidden form-item">
            <div class="col-12">

                <div class="card text-center">
                    <div class="card-header">
                        <h5 id="form-item-title" class="clear-me-html" style="min-height:23px;"></h5>
                    </div>
                    <div class="card-body">
                        <div>
                            <label><i class="fa fa-users mr-2"></i><?php echo langHdl('visible_by'); ?></label>
                            <span id="card-item-visibility" class="text-info font-weight-bold ml-2"></span>
                        </div>
                        <div>
                            <label><i class="fa fa-key mr-2"></i><?php echo langHdl('complex_asked'); ?></label>
                            <span id="card-item-minimum-complexity" class="text-info font-weight-bold ml-2"></span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex">
                        <span class="mr-3 align-middle">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-gray but-back-to-list">
                                    <i class="fa fa-arrow-left"></i>
                                </button>
                            </div>
                        </span>

                        <ul class="nav nav-pills ml-auto mr-3" id="form-item-nav-pills">
                            <li class="nav-item"><a class="nav-link active" href="#tab_1" data-toggle="tab"><?php echo langHdl('main'); ?></a></li>
                            <li class="nav-item"><a class="nav-link" href="#tab_2" data-toggle="tab"><?php echo langHdl('details'); ?></a></li>
                            <li class="nav-item"><a class="nav-link" href="#tab_3" data-toggle="tab"><?php echo langHdl('attachments'); ?></a></li>
                            <?php
                            echo isset($SETTINGS['item_extra_fields']) === true && $SETTINGS['item_extra_fields'] === '1' ? '
                            <li class="nav-item"><a class="nav-link" href="#tab_4" data-toggle="tab">'.langHdl('fields').'</a></li>' : '';
                            ?>
                        </ul>
                        
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool btn-sm but-back-to-list">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div><!-- /.card-header -->
                    <div class="card-body">
                        <form id="form-item" class="needs-validation" novalidate onsubmit="return false;">
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- LABEL -->
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo langHdl('label'); ?></span>
                                    </div>
                                    <input id="form-item-label" type="text" class="form-control form-item-control track-change" data-change-ongoing="" data-field-name="label">
                                </div>
                                <!-- DESCRIPTION -->
                                <div class="mb-3">
                                    <textarea id="form-item-description" class="form-item-control w-100 clear-me-html track-change" data-field-name="description" data-change-ongoing=""></textarea>
                                </div>
                                <!-- LOGIN -->
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo langHdl('login'); ?></span>
                                    </div>
                                    <input id="form-item-login" type="text" class="form-control form-item-control track-change" data-field-name="login" data-change-ongoing="">
                                </div>
                                <!-- PASSWORD -->
                                <div class="input-group mb-0">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text p-1"><div id="form-item-password-strength"></div></span>
                                    </div>
                                    <input id="form-item-password" type="password" class="form-control form-item-control track-change" placeholder="<?php echo langHdl('password'); ?>" data-field-name="pwd" data-change-ongoing="">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary btn-no-click infotip" id="item-button-password-generate" title="<?php echo langHdl('pw_generate'); ?>"><i class="fa fa-random"></i></button>
                                        <button class="btn btn-outline-secondary btn-no-click infotip" id="item-button-password-showOptions" title="<?php echo langHdl('options'); ?>"><i class="fa fa-sliders"></i></button>
                                        <button class="btn btn-outline-secondary btn-no-click infotip" id="item-button-password-show" title="<?php echo langHdl('mask_pw'); ?>"><i class="fa fa-low-vision"></i></button>
                                    </div>
                                </div>
                                <input type="hidden" id="form-item-password-complex" value="0">
                                <div class="mt-1 hidden" id="form-item-password-options">
                                    <div class="btn-toolbar justify-content-center" role="toolbar" aria-label="Toolbar with button groups">
                                        <div class="btn-group btn-group-sm btn-group-toggle mr-2" data-toggle="buttons">
                                            <label class="btn btn-outline-secondary btn-sm">
                                                <input type="checkbox" class="password-definition" id="pwd-definition-lcl">abc</label>
                                            <label class="btn btn-outline-secondary btn-sm">
                                                <input type="checkbox" class="password-definition" id="pwd-definition-ucl">ABC</label>
                                            <label class="btn btn-outline-secondary btn-sm">
                                                <input type="checkbox" class="password-definition" id="pwd-definition-numeric">123</label>
                                            <label class="btn btn-outline-secondary btn-sm">
                                                <input type="checkbox" class="password-definition" id="pwd-definition-symbols">@#&amp;</label>
                                            <label class="btn btn-outline-secondary btn-sm">
                                                <input type="checkbox" class="password-definition" id="pwd-definition-secure"><?php echo langHdl('secure'); ?></label>
                                        </div>

                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text"><?php echo langHdl('size'); ?></div>
                                            </div>
                                            <select class="form-control form-control-sm w-10" id="pwd-definition-size">
                                            <?php
                                            for ($i = 0; $i <= $SETTINGS['pwd_maximum_length']; ++$i) {
                                                echo '
                                                <option>'.$i.'</option>';
                                            }
                                            ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <!-- EMAIL -->
                                <div class="input-group mb-3 mt-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo langHdl('email'); ?></span>
                                    </div>
                                    <input id="form-item-email" type="email" class="form-control form-item-control track-change" data-field-name="email" data-change-ongoing="">
                                </div>
                                <!-- URL -->
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo langHdl('url'); ?></span>
                                    </div>
                                    <input id="form-item-url" type="url" class="form-control form-item-control track-change" data-field-name="url" data-change-ongoing="">
                                </div>
                            </div>

                            <div class="tab-pane" id="tab_2">
                                <!-- FOLDERS -->
                                <div class="form-group mb-3">
                                    <label><?php echo langHdl('folder'); ?></label>
                                    <select id="form-item-folder" class="form-control form-item-control select2 no-root" style="width:100%;"></select>
                                </div>

                                <!-- RESTRICTED TO -->
                                <div class="input-group mb-3">
                                    <label><?php echo langHdl('restricted_to'); ?></label>
                                    <select id="form-item-restrictedto" class="form-control form-item-control select2 track-change" style="width:100%;" multiple="multiple"></select>
                                    <input type="hidden" id="form-item-restrictedToUsers" class="form-item-control">
                                    <input type="hidden" id="form-item-restrictedToRoles" class="form-item-control">
                                </div>
                                <!-- TAGS -->
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo langHdl('tags'); ?></span>
                                    </div>
                                    <input id="form-item-tags" type="text" class="form-control form-item-control autocomplete track-change">
                                </div>
                                <!-- ANYONE CAN MODIFY -->
                                <?php
                                if (isset($SETTINGS['anyone_can_modify']) === true
                                    && $SETTINGS['anyone_can_modify'] === '1'
                                ) {
                                    ?>
                                <div class="form-check mb-3">
                                    <input type="checkbox" class="form-check-input form-item-control flat-blue track-change" id="form-item-anyoneCanModify"<?php
                                    echo isset($SETTINGS['anyone_can_modify_bydefault']) === true
                                        && $SETTINGS['anyone_can_modify_bydefault'] === '1' ? ' checked' : ''; ?>>
                                    <label class="form-check-label ml-3" for="form-item-anyoneCanModify"><?php echo langHdl('anyone_can_modify'); ?></label>
                                </div>
                                    <?php
                                }
                                ?>
                                <!-- DELETE AFTER CONSULTATION -->
                                <?php
                                if (isset($SETTINGS['enable_delete_after_consultation']) === true
                                    && $SETTINGS['enable_delete_after_consultation'] === '1'
                                ) {
                                    ?>
                                <div class="callout callout-primary mb-3">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                        <i class="fa fa-eraser"></i>
                                        <?php echo langHdl('allow_item_to_be_deleted'); ?>
                                        </h3>
                                    </div>
                                    <!-- /.card-header -->
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="d-inline p-2">
                                                <?php echo langHdl('item_deleted_after_being_viewed_x_times'); ?>
                                            </div>
                                            <div class="d-inline p-2">
                                                <input type="text" class="form-control form-item-control track-change" id="form-item-deleteAfterShown">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="d-inline p-2">
                                                <?php echo langHdl('item_deleted_after_date'); ?>
                                            </div>
                                            <div class="d-inline p-2">
                                                <div class="input-group date inline">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text">
                                                            <i class="fa fa-calendar"></i>
                                                        </span>
                                                    </div>
                                                    <input type="date" class="form-control float-right form-item-control track-change" id="form-item-deleteAfterDate">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                    <?php
                                }
                                ?>

                                <div class="callout callout-primary mb-3">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                        <i class="fa fa-bullhorn"></i>
                                        <?php echo langHdl('anounce_item_by_email'); ?>
                                        </h3>
                                    </div>
                                    <!-- /.card-header -->
                                    <div class="card-body">
                                        <select id="form-item-anounce" class="form-control form-item-control select2 track-change" style="width:100%;" multiple="multiple" data-placeholder="<?php echo langHdl('select_users_if_needed'); ?>"></select>
                                    </div>
                                </div>
                            </div>

                            <!-- ATTACHMENTS -->
                            <div class="tab-pane" id="tab_3">
                                <div class="callout callout-primary mb-3 hidden" id="form-item-attachments-zone">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                        <i class="fa fa-paperclip mr-3"></i>
                                        <?php echo langHdl('attached_files'); ?>
                                        </h3>
                                    </div>
                                    <!-- /.card-header -->
                                    <div class="card-body clear-me-html" id="form-item-attachments">
                                    </div>    
                                </div>
                                <div class="callout callout-primary mb-3">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                        <i class="fa fa-plus mr-3"></i>
                                        <?php echo langHdl('select_files'); ?>
                                        </h3>
                                    </div>
                                    <!-- /.card-header -->
                                    <div class="card-body">
                                        <div class="row" id="form-item-upload-zone">
                                            <div class="col-6">
                                                <a class="btn btn-app text-capitalize" id="form-item-attach-pickfiles">
                                                    <i class="fa fa-search mr-1"></i><?php echo langHdl('select'); ?>
                                                </a>
                                                <a class="btn btn-app" id="form-item-upload-pickfiles">
                                                    <i class="fa fa-upload mr-1"></i><?php echo langHdl('start_upload'); ?>
                                                </a>
                                                <input type="hidden" id="form-item-hidden-pickFilesNumber" value="0" />
                                                <small class="form-text text-muted">
                                                    <?php echo langHdl('add_files_and_click_start'); ?>
                                                </small>
                                            </div>
                                            <div class="col-6">
                                                <div class="callout callout-info hidden clear-me-html" id="form-item-upload-pickfilesList"></div>
                                            </div>
                                        </div>
                                    </div>    
                                </div>
                            </div>

                            <!-- CUSTOM FIELDS -->
                            <div class="tab-pane" id="tab_4">
                                <div id="form-item-field" class="hidden">
                                <?php
                                foreach ($_SESSION['item_fields'] as $category) {
                                    echo '
                                    <div class="callout callout-info form-item-category hidden" id="form-item-category-'.$category['id'].'">
                                        <h5>'.$category['title'].'</h5>
                                        <p>';
                                    foreach ($category['fields'] as $field) {
                                        if ($field['type'] === 'textarea') {
                                            echo '
                                            <div class="form-group mb-3 form-item-field" id="form-item-field-'.$field['id'].'" data-field-id="'.$field['id'].'">
                                                <label>'.$field['title']
                                                , $field['is_mandatory'] === '1' ?
                                                '<span class="fa fa-fire text-danger ml-1 infotip" title="'.langHdl('is_mandatory').'"></span>' : ''
                                                , '</label>
                                                <textarea class="form-control form-item-control form-item-field-custom track-change" rows="2" data-field-name="'.$field['id'].'" data-field-mandatory="'.$field['is_mandatory'].'"></textarea>
                                            </div>';
                                        } else {
                                            echo '
                                            <div class="input-group mb-3 form-item-field" id="form-item-field-'.$field['id'].'" data-field-id="'.$field['id'].'">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">'.$field['title']
                                                    , $field['is_mandatory'] === '1' ?
                                                    '<span class="fa fa-fire text-danger ml-1 infotip" title="'.langHdl('is_mandatory').'"></span>' : ''
                                                    , '</span>
                                                </div>
                                                <input type="'.$field['type'].'" class="form-control form-item-control form-item-field-custom track-change" data-field-name="'.$field['id'].'" data-field-mandatory="'.$field['is_mandatory'].'">
                                            </div>';
                                        }
                                    }
                                    // Manage template
                                    if (isset($SETTINGS['item_creation_templates']) === true
                                        && $SETTINGS['item_creation_templates'] === '1'
                                    ) {
                                        echo '
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input form-check-input-template" data-category-id="'.$category['id'].'" id="template_'.$category['id'].'">
                                                <label class="form-check-label ml-3" for="template_'.$category['id'].'">'.langHdl('main_template').'</label>
                                            </div>';
                                    }
                                    echo '
                                        </p>
                                    </div>';
                                } ?>
                                </div>
                                <div class="alert alert-info hidden no-item-fields">
                                    <h5><i class="icon fa fa-info mr-3"></i><?php echo langHdl('information'); ?></h5>
                                    <?php echo langHdl('no_fields'); ?>
                                </div>
                            </div>
                        </div>
                        </form>
                    </div>
                    <div class="card-footer" id="form-item-buttons">
                        <button type="button" class="btn btn-info" id="form-item-button-save" data-action=""><?php echo langHdl('save'); ?></button>
                        <button type="button" class="btn btn-default  but-back-to-list"><?php echo langHdl('cancel'); ?></button>
                    </div>
                    <!-- /.card-footer -->
                </div>
            </div>
        </div>


        <!-- ITEM DETAILS -->
        <div class="row hidden item-details-card item-details-card-menu">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <span class="mr-3 align-middle">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-gray but-back-to-list">
                                    <i class="fa fa-arrow-left"></i>
                                </button>
                                <button type="button" class="btn btn-gray dropdown-toggle" data-toggle="dropdown">
                                    <i class="fa fa-bars"></i>
                                    <span class="caret"></span>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item tp-action" href="#" data-item-action="new"><i class="fa fa-plus mr-2"></i><?php echo langHdl('new_item'); ?></a>
                                    <a class="dropdown-item tp-action" href="#" data-item-action="edit"><i class="fa fa-pencil mr-2"></i><?php echo langHdl('item_menu_edi_elem'); ?></a>
                                    <a class="dropdown-item tp-action" href="#" data-item-action="delete"><i class="fa fa-trash mr-2"></i><?php echo langHdl('item_menu_del_elem'); ?></a>
                                    <a class="dropdown-item tp-action" href="#" data-item-action="copy"><i class="fa fa-copy mr-2"></i><?php echo langHdl('item_menu_copy_elem'); ?></a>
                                    <a class="dropdown-item tp-action" href="#" data-item-action="share"><i class="fa fa-share-alt mr-2"></i><?php echo langHdl('share_item'); ?></a>
                                    <a class="dropdown-item tp-action" href="#" data-item-action="notify"><i class="fa fa-bullhorn mr-2"></i><?php echo langHdl('notification'); ?></a>
                                    <?php
                                    if (isset($SETTINGS['enable_email_notification_on_item_shown']) === true
                                        && $SETTINGS['enable_email_notification_on_item_shown'] === '1'
                                    ) {
                                        ?>
                                    <a class="dropdown-item tp-action" href="#" data-item-action="notify"><i class="fa fa-volume-up mr-2"></i><?php echo langHdl('item_menu_copy_elem'); ?></a>
                                    <?php
                                    }
                                    ?>
                                </div>
                            </div>
                        </span>
                        <h3 class="d-inline align-middle" id="card-item-label"></h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool btn-sm but-back-to-list">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- EXPIRED ITEM -->
        <div class="row hidden" id="card-item-expired">
            <div class="col-12">
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fa fa-warning"></i> <?php echo langHdl('warning'); ?></h5>
                    <?php echo langHdl('pw_is_expired_-_update_it'); ?>
                </div>
            </div>
        </div>
        
        <div class="row hidden item-details-card">
            <div class="col-md-7">
                <div class="card card-primary card-outline">
                    <div class="card-body" id="list-group-item-main">
                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item">
                                <b><?php echo langHdl('pw'); ?></b>
                                <button type="button" class="float-right btn btn-outline-info btn-sm btn-copy-clipboard" id="card-item-pwd-button">
                                    <i class="fa fa-copy"></i>
                                </button>
                                <span id="card-item-pwd" class="float-right unhide_masked_data pointer mr-2"></span>
                                <input id="hidden-item-pwd" type="hidden">
                                <input type="hidden" id="hid_pw_old" value="" />
                                <input type="hidden" id="pw_shown" value="0" />
                            </li>
                            <li class="list-group-item">
                                <b><?php echo langHdl('index_login'); ?></b>
                                <button type="button" class="float-right btn btn-outline-info btn-sm ml-1 btn-copy-clipboard-clear" data-clipboard-target="#card-item-login" id="card-item-login-btn">
                                    <i class="fa fa-copy"></i>
                                </button>
                                <span id="card-item-login" class="float-right"></span>
                            </li>
                            <li class="list-group-item">
                                <b><?php echo langHdl('email'); ?></b>
                                <button type="button" class="float-right btn btn-outline-info btn-sm ml-1 btn-copy-clipboard-clear" data-clipboard-target="#card-item-email" id="card-item-email-btn">
                                    <i class="fa fa-copy"></i>
                                </button>
                                <span id="card-item-email" class="float-right ml-1"></span>
                            </li>
                            <li class="list-group-item">
                                <b><?php echo langHdl('url'); ?></b>
                                <a id="card-item-url" class="float-right ml-1" href="#" target="_blank"></a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card">
                    <div class="card-body">
                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item">
                                <b><?php echo langHdl('restricted_to'); ?></b>
                                <a id="card-item-restrictedto" class="float-right ml-1"></a>
                            </li>
                            <li class="list-group-item">
                                <b><?php echo langHdl('tags'); ?></b>
                                <a id="card-item-tags" class="float-right ml-1"></a>
                            </li>
                            <li class="list-group-item">
                                <b><?php echo langHdl('kbs'); ?></b>
                                <a id="card-item-kbs" class="float-right ml-1"></a>
                            </li>
                            <li class="list-group-item" id="card-item-misc">
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row hidden item-details-card">
            <div class="col-12">
                <div class="callout callout-info visible" id="card-item-description">No description</div>
            </div>
        </div>

        
        <?php
        if (isset($SETTINGS['item_extra_fields']) === true
            && $SETTINGS['item_extra_fields'] === '1'
        ) {
            ?>
        <div class="row hidden item-details-card" id="item-details-card-categories">
            <div class="col-12">
                <div class="card card-default">
                    <div class="card-header bg-gray">
                        <h3 class="card-title pointer" data-widget="collapse">
                            <i class="fa fa-random mr-2"></i><?php echo langHdl('categories'); ?>
                        </h3>
                        <!-- /.card-tools -->
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body" id="card-item-fields">
                    <?php
                    foreach ($_SESSION['item_fields'] as $elem) {
                        echo '
                        <div class="callout callout-info card-item-category hidden" id="card-item-category-'.$elem['id'].'">
                            <h5>'.$elem['title'].'</h5>
                            <p>
                                <ul class="list-group list-group-unbordered mb-3">';
                        foreach ($elem['fields'] as $field) {
                            echo '
                                    <li class="list-group-item card-item-field hidden" id="card-item-field-'.$field['id'].'">
                                        <b>'.$field['title'].'</b>
                                        <button type="button" class="float-right btn btn-outline-info btn-sm ml-1 btn-copy-clipboard-clear"  data-clipboard-target="#card-item-field-value-'.$field['id'].'">
                                            <i class="fa fa-copy"></i>
                                        </button>
                                        <span class="card-item-field-value float-right ml-1" id="card-item-field-value-'.$field['id'].'"></span>
                                    </li>';
                        }
                        echo '
                                </ul>
                            </p>
                        </div>';
                    } ?>
                    <div class="hidden no-item-fields"><?php echo langHdl('no_custom_fields'); ?></div>
                    </div>
                    <!-- /.card-body -->
                </div>
            </div>
        </div>
        <?php
        }
        ?>

        <div class="row hidden item-details-card item-card-attachments">
            <div class="col-12">
                <div class="card card-default">
                    <div class="card-header bg-gray">
                        <h3 class="card-title pointer" data-widget="collapse">
                            <i class="fa fa-paperclip mr-2"></i><?php echo langHdl('attachments'); ?>
                        </h3>
                        <!-- /.card-tools -->
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body clear-me-html" id="card-item-attachments">
                    </div>
                    <!-- /.card-body -->
                    <div class="overlay">
                        <i class="fa fa-refresh fa-spin"></i>
                    </div>
                </div>
            </div>
        </div>        

        <div class="row hidden item-details-card">
            <div class="col-12">
                <div class="card card-default collapsed-card">
                    <div class="card-header bg-gray">
                        <h3 class="card-title pointer" data-widget="collapse">
                            <i class="fa fa-history mr-2"></i><?php echo langHdl('history'); ?>
                        </h3>
                        <!-- /.card-tools -->
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body" id="card-item-history">
                        <div class="direct-chat-messages clear-me-html">
                        </div>
                    </div>
                    <!-- /.card-body -->
                    <div class="overlay">
                        <i class="fa fa-refresh fa-spin"></i>
                    </div>
                </div>
            </div>
        </div>

        
        <?php
        if (isset($SETTINGS['enable_suggestion']) === true
            && $SETTINGS['enable_suggestion'] === '1'
        ) {
            ?>
        <div class="row hidden item-details-card">
            <div class="col-12">
                <div class="card card-default collapsed-card card-item-extra">
                    <div class="card-header bg-gray">
                        <h3 class="card-title pointer" data-widget="collapse">
                            <i class="fa fa-random mr-2"></i><?php echo langHdl('suggest_password_change'); ?>
                        </h3>
                        <!-- /.card-tools -->
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <form id="form-item-suggestion" class="needs-validation" novalidate onsubmit="return false;">
                            <div class="alert alert-info">
                                <h5><i class="icon fa fa-info mr-2"></i><?php echo langHdl('information'); ?></h5>
                                <?php echo langHdl('suggestion_information'); ?>
                            </div>
                            <!-- LABEL -->
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><?php echo langHdl('label'); ?></span>
                                </div>
                                <input id="form-item-suggestion-label" type="text" class="form-control form-item-control form-item-suggestion" data-change-ongoing="" data-field-name="label">
                            </div>
                            <!-- DESCRIPTION -->
                            <div class="mb-3">
                                <textarea id="form-item-suggestion-description" class="form-item-control form-item-suggestion w-100 clear-me-html" data-field-name="description" data-change-ongoing=""></textarea>
                            </div>
                            <!-- LOGIN -->
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><?php echo langHdl('login'); ?></span>
                                </div>
                                <input id="form-item-suggestion-login" type="text" class="form-control form-item-control form-item-suggestion" data-field-name="login" data-change-ongoing="">
                            </div>
                            <!-- PASSWORD -->
                            <div class="input-group mb-0">
                                <div class="input-group-prepend">
                                    <span class="input-group-text p-1"><div id="form-item-suggestion-password-strength"></div></span>
                                </div>
                                <input id="form-item-suggestion-password" type="password" class="form-control form-item-control form-item-suggestion" placeholder="<?php echo langHdl('password'); ?>" data-field-name="pwd" data-change-ongoing="">
                            </div>
                            <input type="hidden" id="form-item-suggestion-password-complex" value="0">
                            <!-- EMAIL -->
                            <div class="input-group mb-3 mt-3">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><?php echo langHdl('email'); ?></span>
                                </div>
                                <input id="form-item-suggestion-email" type="email" class="form-control form-item-control form-item-suggestion" data-field-name="email" data-change-ongoing="">
                            </div>
                            <!-- URL -->
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><?php echo langHdl('url'); ?></span>
                                </div>
                                <input id="form-item-suggestion-url" type="url" class="form-control form-item-control form-item-suggestion" data-field-name="url" data-change-ongoing="">
                            </div>
                            <!-- COMMENT -->
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><?php echo langHdl('comment'); ?></span>
                                </div>
                                <textarea id="form-item-suggestion-comment" class="form-control form-item-control form-item-suggestion" rows="2" data-field-name="comment" data-change-ongoing=""></textarea>
                            </div>
                        </form>
                    </div>
                    <!-- /.card-body -->
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary" id="form-item-suggestion-perform"><?php echo langHdl('perform'); ?></button>
                    </div>
                    <!-- /.card-footer -->
                </div>
            </div>
        </div>
        <?php
        }

        if (isset($SETTINGS['enable_server_password_change']) === true
            && $SETTINGS['enable_server_password_change'] === '1'
        ) {
            ?>
        <div class="row hidden item-details-card">
            <div class="col-12">
                <div class="card card-default collapsed-card">
                    <div class="card-header bg-gray">
                        <h3 class="card-title pointer" data-widget="collapse">
                            <i class="fa fa-server mr-2"></i><?php echo langHdl('update_server_password'); ?>
                        </h3>
                        <!-- /.card-tools -->
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        The body of the card
                    </div>
                    <!-- /.card-body -->
                </div>
            </div>
        </div>
        <?php
        }
        ?>

        <div class="row hidden item-details-card">
            <div class="col-12">
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <button type="button" class="btn btn-warning btn-copy-clipboard"  id="card-item-otv-generate-button"><?php echo langHdl('generate_otv_link'); ?></button>
                    </div>
                    <div class="input-group-prepend">
                        <button type="button" class="btn btn-warning btn-copy-clipboard"  id="card-item-otv-copy-button"><?php echo langHdl('copy'); ?></button>
                    </div>
                    <!-- /btn-group -->
                    <input type="text" class="form-control" placeholder="OTV link" id="card-item-otv">
                </div>
            </div>
        </div>


        <!-- COPY ITEM FORM -->
        <div class="row hidden form-item-copy form-item-action">
            <div class="col-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h5><i class="fa fa-copy mr-2"></i><?php echo langHdl('copy_item'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label><?php echo langHdl('select_destination_folder'); ?></label>
                            <select class="form-control form-item-control select2 no-root" style="width:100%;" id="form-item-copy-destination"></select>
                        </div>
                        <div class="form-group">
                            <label><?php echo langHdl('new_label'); ?></label>
                            <input type="text" class="form-control form-item-control" id="form-item-copy-new-label">
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary" id="form-item-copy-perform"><?php echo langHdl('perform'); ?></button>
                        <button type="submit" class="btn btn-default float-right but-back-to-item"><?php echo langHdl('cancel'); ?></button>
                    </div>
                </div>
            </div>
        </div>


        <!-- DELETE ITEM FORM -->
        <div class="row hidden form-item-delete form-item-action">
            <div class="col-12">

                <div class="card card-warning">
                    <div class="card-header">
                        <h5><i class="fa fa-trash mr-2"></i><?php echo langHdl('delete_item'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info alert-dismissible">
                            <h5><i class="icon fa fa-info mr-2"></i><?php echo langHdl('warning'); ?></h5>
                            <?php echo langHdl('delete_item_message'); ?>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-warning" id="form-item-delete-perform"><?php echo langHdl('perform'); ?></button>
                        <button type="submit" class="btn btn-default float-right but-back-to-item"><?php echo langHdl('cancel'); ?></button>
                    </div>
                </div>
                
            </div>
        </div>


        <!-- SHARE ITEM FORM -->
        <div class="row hidden form-item-share form-item-action">
            <div class="col-12">
                <form id="form-item-share needs-validation" novalidate onsubmit="return false;">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h5><i class="fa fa-share-alt mr-2"></i><?php echo langHdl('share_item'); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="callout callout-info">
                                <h5><i class="icon fa fa-info mr-2"></i><?php echo langHdl('information'); ?></h5>
                                <p><?php echo langHdl('share_item_message'); ?></p>
                            </div>
                            <div class="form-group">
                                <label for="form-item-share-email"><?php echo langHdl('email_address'); ?></label>
                                <input type="email" class="form-control clear-me-val" id="form-item-share-email" placeholder="<?php echo langHdl('enter_email'); ?>" required>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary" id="form-item-share-perform"><?php echo langHdl('perform'); ?></button>
                            <button type="submit" class="btn btn-default float-right but-back-to-item"><?php echo langHdl('cancel'); ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>


        <!-- NOTIFY ITEM FORM -->
        <div class="row hidden form-item-notify form-item-action">
            <div class="col-12">

                <div class="card card-primary">
                    <div class="card-header">
                        <h5><i class="fa fa-bullhorn mr-2"></i><?php echo langHdl('notification'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="callout callout-info">
                            <h5><i class="icon fa fa-info mr-2"></i><?php echo langHdl('information'); ?></h5>
                            <p><?php echo langHdl('notification_message'); ?></p>
                        </div>
                        <div class="form-group">
                        <input type="checkbox" class="flat-blue" id="form-item-notify-status"><label for="form-item-notify-status" class="ml-3"><?php echo langHdl('notify_on_change'); ?></label>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary"><?php echo langHdl('perform'); ?></button>
                        <button type="submit" class="btn btn-default float-right but-back-to-item"><?php echo langHdl('cancel'); ?></button>
                    </div>
                </div>
                
            </div>
        </div>


        <!-- ADD FOLDER FORM -->
        <div class="row hidden form-folder-add form-folder-action">
            <div class="col-12">
                <form id="form-folder-add" class="needs-validation" novalidate onsubmit="return false;" data-action="">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h5><i class="fa fa-plus mr-2"></i><?php echo langHdl('add_folder'); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label><?php echo langHdl('label'); ?></label>
                                <input type="text" class="form-control form-folder-control" id="form-folder-add-label">
                            </div>
                            <div class="form-group">
                                <label><?php echo langHdl('select_folder_parent'); ?></label>
                                <select class="form-control form-folder-control select2" style="width:100%;" id="form-folder-add-parent"></select>
                            </div>
                            <div class="form-group">
                                <label><?php echo langHdl('complex_asked'); ?></label>
                                <select class="form-control form-folder-control select2" style="width:100%;" id="form-folder-add-complexicity">
                                    <?php
                                    foreach (TP_PW_COMPLEXITY as $key => $value) {
                                        echo '<option value="'.$key.'">'.$value[1].'</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary" id="form-folder-add-perform"><?php echo langHdl('perform'); ?></button>
                            <button type="submit" class="btn btn-default float-right but-back-to-list"><?php echo langHdl('cancel'); ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>


        <!-- DELETE FOLDER FORM -->
        <div class="row hidden form-folder-delete form-folder-action">
            <div class="col-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h5><i class="fa fa-trash mr-2"></i><?php echo langHdl('delete_folder'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label><?php echo langHdl('select_folder_to_delete'); ?></label>
                            <select class="form-control form-folder-control select2" style="width:100%;" id="form-folder-delete-selection"></select>
                        </div>
                        <div class="form-check mb-3 alert alert-warning">
                            <input type="checkbox" class="form-check-input form-item-control flat-blue mr-2" id="form-folder-confirm-delete">
                            <label class="form-check-label ml-3" for="form-folder-confirm-delete"><i class="fa fa-info fa-lg mr-2"></i><?php echo langHdl('folder_delete_confirm'); ?></label>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary" id="form-folder-delete-perform"><?php echo langHdl('perform'); ?></button>
                        <button type="submit" class="btn btn-default float-right but-back-to-list"><?php echo langHdl('cancel'); ?></button>
                    </div>
                </div>
            </div>
        </div>


        <!-- COPY FOLDER FORM -->
        <div class="row hidden form-folder-copy form-folder-action">
            <div class="col-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h5><i class="fa fa-copy mr-2"></i><?php echo langHdl('copy_folder'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label><?php echo langHdl('select_source_folder'); ?></label>
                            <select class="form-control form-folder-control select2" style="width:100%;" id="form-folder-copy-source"></select>
                        </div>
                        <div class="form-group">
                            <label><?php echo langHdl('select_destination_folder'); ?></label>
                            <select class="form-control form-folder-control select2" style="width:100%;" id="form-folder-copy-destination">
                            </select>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary" id="form-folder-copy-perform"><?php echo langHdl('perform'); ?></button>
                        <button type="submit" class="btn btn-default float-right but-back-to-list"><?php echo langHdl('cancel'); ?></button>
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
                                        <a class="dropdown-item tp-action" href="#" data-folder-action="refresh"><i class="fa fa-refresh mr-2"></i><?php echo langHdl('refresh'); ?></a>
                                        <a class="dropdown-item tp-action" href="#" data-folder-action="expand"><i class="fa fa-expand mr-2"></i><?php echo langHdl('expand'); ?></a>
                                        <a class="dropdown-item tp-action" href="#" data-folder-action="collapse"><i class="fa fa-compress mr-2"></i><?php echo langHdl('collapse'); ?></a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item tp-action" href="#" data-folder-action="add"><i class="fa fa-plus mr-2"></i><?php echo langHdl('add'); ?></a>
                                        <a class="dropdown-item tp-action" href="#" data-folder-action="edit"><i class="fa fa-pencil mr-2"></i><?php echo langHdl('edit'); ?></a>
                                        <a class="dropdown-item tp-action" href="#" data-folder-action="copy"><i class="fa fa-copy mr-2"></i><?php echo langHdl('copy'); ?></a>
                                        <a class="dropdown-item tp-action" href="#" data-folder-action="delete"><i class="fa fa-trash mr-2"></i><?php echo langHdl('delete'); ?></a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item tp-action" href="#" data-folder-action="">
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control" placeholder="<?php echo langHdl('find'); ?>" id="jstree_search">
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
                                        data-item-action="new">
                                        <i class="fa fa-plus mr-2"></i><?php echo langHdl('new_item'); ?>
                                    </button>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" placeholder="<?php echo langHdl('find'); ?>" id="find_items">
                                    <div class="input-group-append">
                                        <div class="btn btn-primary" id="find_items_button">
                                            <i class="fa fa-search"></i>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <span class="sr-only">Toggle Dropdown</span>
                                        </button>
                                        <div class="dropdown-menu">
                                            <div class="dropdown-item">   
                                                <input type="checkbox" class=" mr-2" id="limited-search">
                                                <label class="form-check-label" for="limited-search"><?php echo langHdl('limited_search'); ?></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body p-1">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="table_teampass_items_list">
                            <tbody id="teampass_items_list"></tbody>
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






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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'roles', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

// Load template
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
$link->set_charset(DB_ENCODING);

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6">
        <h1 class="m-0 text-dark">
        <i class="fas fa-graduation-cap mr-2"></i><?php echo langHdl('roles'); ?>
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
                            <i class="fas fa-plus mr-2"></i><?php echo langHdl('new'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="edit">
                            <i class="fas fa-pen mr-2"></i><?php echo langHdl('edit'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="delete">
                            <i class="fas fa-trash mr-2"></i><?php echo langHdl('delete'); ?>
                        </button>
                    </div><!-- /.card-header -->
                    <div class="card-body">
                        <div class="form-group">
                            <select id="roles-list" class="form-control form-item-control select2" style="width:100%;">
                                <option></option>
                            <?php
                                $arrUserRoles = array_filter($_SESSION['user_roles']);
                                $where = '';
                            if (count($arrUserRoles) > 0 && (int) $_SESSION['is_admin'] !== 1) {
                                $where = ' WHERE id IN ('.implode(',', $arrUserRoles).')';
                            }
                            $rows = DB::query('SELECT * FROM '.prefixTable('roles_title').$where);

                            foreach ($rows as $reccord) {
                                echo '
                                <option value="'.$reccord['id'].'" '.
                                'data-complexity-text="'.addslashes(TP_PW_COMPLEXITY[$reccord['complexity']][1]).'" '.
                                'data-complexity-icon="'.TP_PW_COMPLEXITY[$reccord['complexity']][2].'" '.
                                'data-complexity="'.TP_PW_COMPLEXITY[$reccord['complexity']][0].'" '.
                                'data-allow-edit-all="'.$reccord['allow_pw_change'].'">'.
                                $reccord['title'].'</option>';
                            }
                            ?>
                            </select>
                        </div>

                        <div class="card hidden card-info" id="card-folder-definition">
                            <div class="card-header">
                                <h5><?php echo langHdl('folder_definition'); ?></h5>
                            </div><!-- /.card-header -->
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="form-folder-label"><?php echo langHdl('label'); ?></label>
                                    <input type="text" class="form-control" id="form-folder-label" required>
                                </div>
                                <div class="form-group">
                                    <label for="form-complexity-list"><?php echo langHdl('complexity'); ?></label>
                                    <select id="form-complexity-list" class="form-control form-item-control select2" style="width:100%;">
                                    <?php
                                    foreach (TP_PW_COMPLEXITY as $entry) {
                                        echo '
                                        <option value="'.$entry[0].'">'.addslashes($entry[1]).'</option>';
                                    }
                                    ?>
                                    </select>
                                </div>
                                <div class="form-group mt-2">
                                    <input type="checkbox" class="form-check-input form-item-control" id="form-folder-privilege">
                                    <label class="form-check-label ml-2" for="form-folder-privilege">
                                        <?php echo langHdl('role_can_edit_any_visible_item'); ?>
                                        <i class="mr-2 fas fa-question-circle infotip" title="<?php echo langHdl('role_can_edit_any_visible_item_tip'); ?>"></i>
                                    </label>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn btn-info tp-action" data-action="submit-edition"><?php echo langHdl('submit'); ?></button>
                                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel-edition"><?php echo langHdl('cancel'); ?></button>
                            </div>
                        </div>

                        <div class="card hidden card-danger" id="card-folder-deletion">
                            <div class="card-header">
                                <h5><?php echo langHdl('caution'); ?></h5>
                            </div><!-- /.card-header -->
                            <div class="card-body">
                                <div class="form-group mt-2">
                                    <input type="checkbox" class="form-check-input form-item-control" id="form-folder-delete">
                                    <label class="form-check-label ml-2" for="form-folder-delete">
                                        <?php echo langHdl('please_confirm_deletion'); ?><span class="ml-2" id="span-folder-delete"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn btn-danger tp-action" data-action="submit-deletion"><?php echo langHdl('submit'); ?></button>
                                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel-deletion"><?php echo langHdl('cancel'); ?></button>
                            </div>
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
                    </div>                   

                    <div class="table-responsive" id="role-details">
                        &nbsp;
                    </div>

                </div>
            </div>
            <!-- /.col -->
        </div>
    </div>
</section>



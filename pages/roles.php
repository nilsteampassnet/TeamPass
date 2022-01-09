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
 *
 * @file      roles.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

if (
    isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'roles', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Load template
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

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
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2 disabled" data-action="edit" id="button-edit">
                            <i class="fas fa-pen mr-2"></i><?php echo langHdl('edit'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2 disabled" data-action="delete" id="button-delete">
                            <i class="fas fa-trash mr-2"></i><?php echo langHdl('delete'); ?>
                        </button>
                    </div><!-- /.card-header -->
                    <div class="card-body">
                        <div class="form-group" id="card-role-selection">
                            <select id="roles-list" class="form-control form-item-control select2" style="width:100%;">
                                <option></option>
                                <?php
                                $arrUserRoles = array_filter($_SESSION['user_roles']);
                                $where = '';
                                if (count($arrUserRoles) > 0 && (int) $_SESSION['is_admin'] !== 1) {
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
                                <h5><?php echo langHdl('role_definition'); ?></h5>
                            </div><!-- /.card-header -->
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="form-role-label"><?php echo langHdl('label'); ?></label>
                                    <input type="text" class="form-control" id="form-role-label" required>
                                </div>
                                <div class="form-group">
                                    <label for="form-complexity-list"><?php echo langHdl('complexity'); ?></label>
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
                                        <?php echo langHdl('role_can_edit_any_visible_item'); ?>
                                    </label>
                                    <small class='form-text text-muted'>
                                        <?php echo langHdl('role_can_edit_any_visible_item_tip'); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="button" class="btn btn-info tp-action" data-action="submit-edition"><?php echo langHdl('submit'); ?></button>
                                <button type="button" class="btn btn-default float-right tp-action" data-action="cancel-edition"><?php echo langHdl('cancel'); ?></button>
                            </div>
                        </div>

                        <div class="card hidden card-danger" id="card-role-deletion">
                            <div class="card-header">
                                <h5><?php echo langHdl('caution'); ?></h5>
                            </div><!-- /.card-header -->
                            <div class="card-body">
                                <div class="form-group mt-2">
                                    <input type="checkbox" class="form-check-input form-item-control" id="form-role-delete">
                                    <label class="form-check-label ml-2" for="form-role-delete">
                                        <?php echo langHdl('please_confirm_deletion'); ?><span class="ml-2" id="span-role-delete"></span>
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
                        <div class="callout callout-info">
                            <div class="callout-body">
                                <div class="row">
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
                                <div class="row mt-2">
                                    <div class="input-group input-group-sm col-12">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text"><?php echo langHdl('compare_with_another_role'); ?></div>
                                        </div>
                                        <select class="form-control form-control-sm w-10" id="folders-compare">
                                        </select>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="input-group input-group-sm col-12">
                                        <input type="checkbox" id="cb-all-selection" class="folder-select mr-2">
                                        <span id="cb-all-selection-lang" class="ml-2"><?php echo langHdl('select_all'); ?></span>
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

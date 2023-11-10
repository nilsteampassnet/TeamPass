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
 * @file      users.php
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

Use TeampassClasses\SuperGlobal\SuperGlobal;
Use TeampassClasses\NestedTree\NestedTree;
Use TeampassClasses\PerformChecks\PerformChecks;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');

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
            'type' => isset($_POST['type']) === true ? $_POST['type'] : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => isset($_SESSION['user_id']) === false ? null : $_SESSION['user_id'],
        'user_key' => isset($_SESSION['key']) === false ? null : $_SESSION['key'],
        'CPM' => isset($_SESSION['CPM']) === false ? null : $_SESSION['CPM'],
    ]
);
// Handle the case
$checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('users') === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Load language file
require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user']['user_language'].'.php';

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// PREPARE LIST OF OPTIONS
$optionsManagedBy = '';
$optionsRoles = '';
$userRoles = explode(';', $_SESSION['fonction_id']);
// If administrator then all roles are shown
// else only the Roles the users is associated to.
if ((int) $_SESSION['is_admin'] === 1) {
    $optionsManagedBy .= '<option value="0">' . langHdl('administrators_only') . '</option>';
}

$rows = DB::query(
    'SELECT id, title, creator_id
    FROM ' . prefixTable('roles_title') . '
    ORDER BY title ASC'
);
foreach ($rows as $record) {
    if ((int) $_SESSION['is_admin'] === 1 || in_array($record['id'], $_SESSION['user_roles']) === true) {
        $optionsManagedBy .= '<option value="' . $record['id'] . '">' . langHdl('managers_of') . ' ' . addslashes($record['title']) . '</option>';
    }
    if (
        (int) $_SESSION['is_admin'] === 1
        || (((int) $superGlobal->get('user_manager', 'SESSION') === 1 || (int) $_SESSION['user_can_manage_all_users'] === 1)
            && (in_array($record['id'], $userRoles) === true) || (int) $record['creator_id'] === (int) $_SESSION['user_id'])
    ) {
        $optionsRoles .= '<option value="' . $record['id'] . '">' . addslashes($record['title']) . '</option>';
    }
}

$treeDesc = $tree->getDescendants();
$foldersList = '';
foreach ($treeDesc as $t) {
    if (
        in_array($t->id, $_SESSION['groupes_visibles']) === true
        && in_array($t->id, $_SESSION['personal_visible_groups']) === false
    ) {
        $ident = '';
        for ($y = 1; $y < $t->nlevel; ++$y) {
            $ident .= '&nbsp;&nbsp;';
        }
        $foldersList .= '<option value="' . $t->id . '">' . $ident . htmlspecialchars($t->title, ENT_COMPAT, 'UTF-8') . '</option>';
    }
}

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fa-solid fa-users mr-2"></i><?php echo langHdl('users'); ?>
                </h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<section class="content">
    <div class="row" id="row-list">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-middle">
                    <h3 class="card-title">
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="new">
                            <i class="fa-solid fa-plus mr-2"></i><?php echo langHdl('new'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="propagate">
                            <i class="fa-solid fa-share-alt mr-2"></i><?php echo langHdl('propagate'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="refresh">
                            <i class="fa-solid fa-sync-alt mr-2"></i><?php echo langHdl('refresh'); ?>
                        </button><?php
                                    echo isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 1 && (int) $_SESSION['is_admin'] === 1 ?
                                        '<button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="ldap-sync">
                            <i class="fa-solid fa-address-card mr-2"></i>' . langHdl('ldap_synchronization') . '
                        </button>' : '';
                                    ?>
                    </h3>
                </div>

                <!-- /.card-header -->
                <div class="card-body form" id="users-list">
                    <table id="table-users" class="table table-striped nowrap table-responsive-sm">
                        <thead>
                            <tr>
                                <th scope="col"></th>
                                <th scope="col"><?php echo langHdl('user_login'); ?></th>
                                <th scope="col"><?php echo langHdl('name'); ?></th>
                                <th scope="col"><?php echo langHdl('lastname'); ?></th>
                                <th scope="col"><?php echo langHdl('managed_by'); ?></th>
                                <th scope="col"><?php echo langHdl('functions'); ?></th>
                                <th scope="col"><i class="fa-solid fa-theater-masks fa-lg fa-fw infotip" title="<?php echo langHdl('privileges'); ?>"></i></th>
                                <th scope="col"><i class="fa-solid fa-code-branch fa-lg fa-fw infotip" title="<?php echo langHdl('can_create_root_folder'); ?>"></i></th>
                                <th scope="col"><i class="fa-solid fa-hand-holding-heart fa-lg fa-fw infotip" title="<?php echo langHdl('enable_personal_folder'); ?>"></i></th>
                            </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>



    <!-- USER LDAP SYNCHRONIZATION -->
    <div class="row hidden extra-form" id="row-ldap">
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><?php echo langHdl('ldap_synchronization'); ?> <span id="row-logs-title"></span></h3>
                </div>

                <!-- /.card-header -->
                <!-- table start -->
                <div class="card-body">
                    <div class="row col-12">
                        <button type="button" class="btn btn-secondary btn-sm tp-action mr-2" data-action="ldap-existing-users">
                            <i class="fa-solid fa-sync-alt mr-2"></i><?php echo langHdl('list_users'); ?>
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm tp-action mr-2" data-action="ldap-add-role">
                            <i class="fa-solid fa-graduation-cap mr-2"></i><?php echo langHdl('add_role_tip'); ?>
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="card hidden mt-4 mb-5 card-info" id="ldap-new-role">
                                <div class="card-header">
                                    <i class="fa-solid fa-graduation-cap mr-2"></i><?php echo langHdl('add_role_tip'); ?>
                                </div>
                                <div class="card-body">
                                    <div class="callout callout-info">
                                        <i class="fa-solid fa-info-circle text-info mr-2"></i><?php echo langHdl('adding_ldap_role_to_teampass'); ?>
                                    </div>
                                    <div class="form-group row">
                                        <label for="ldap-new-role-selection"><?php echo langHdl('select_role_to_create'); ?></label>
                                        <select class="form-control form-item-control" style="width:100%;" id="ldap-new-role-selection"></select>
                                    </div>
                                    <div class="form-group row">
                                        <label for="ldap-new-role-complexity"><?php echo langHdl('complexity'); ?></label>
                                        <select id="ldap-new-role-complexity" class="form-control form-item-control" style="width:100%;">
                                            <?php
                                            foreach (TP_PW_COMPLEXITY as $entry) {
                                                echo '
                                            <option value="' . $entry[0] . '">' . addslashes($entry[1]) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="button" class="btn btn-default float-left tp-action btn-info" data-action="add-new-role"><?php echo langHdl('submit'); ?></button>
                                    <button type="button" class="btn btn-default float-right tp-action" data-action="close-new-role"><?php echo langHdl('close'); ?></button>
                                </div>
                            </div>
                            <div class="card-body table-responsive p-0" id="ldap-users-table">
                                <table class="table table-hover table-responsive">
                                    <thead>
                                        <tr>
                                            <th style="width: 25%;"><i class="fa-solid fa-id-badge mr-1"></i><?php echo langHdl('login'); ?></th>
                                            <th style="width: 60px; text-align:center;"><i class="fa-solid fa-info infotip pointer" title="<?php echo langHdl('more_information'); ?>"></i></th>
                                            <th style="width: 60px;"><i class="fa-solid fa-sync-alt infotip pointer" title="<?php echo langHdl('synchronized'); ?>"></i></th>
                                            <th style=""><i class="fa-solid fa-graduation-cap mr-1"></i><?php echo langHdl('roles'); ?></th>
                                            <th style="width: 15%;"><i class="fa-solid fa-wrench mr-1"></i><?php echo langHdl('action'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="row-ldap-body">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="button" class="btn btn-default float-right tp-action" data-action="close"><?php echo langHdl('close'); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- USER FORM -->
    <div class="row hidden extra-form" id="row-form">
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><?php echo langHdl('user_definition'); ?></h3>
                </div>

                <!-- /.card-header -->
                <!-- form start -->
                <form role="form" id="form-user">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="form-name"><?php echo langHdl('name'); ?></label>
                                    <input type="text" class="form-control clear-me required track-change purify" id="form-name" data-field="name">
                                </div>
                                <div class="form-group">
                                    <label for="form-login"><?php echo langHdl('login'); ?></label>
                                    <input type="text" class="form-control clear-me required build-login track-change purify" id="form-login" data-field="login">
                                    <input type="hidden" id="form-login-conform" value="0">
                                </div>
                            </div>
                            
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="form-lastname"><?php echo langHdl('lastname'); ?></label>
                                    <input type="text" class="form-control clear-me required track-change purify" id="form-lastname" data-field="lastname">
                                </div>
                                <div class="form-group">
                                    <label for="form-login"><?php echo langHdl('email'); ?></label>
                                    <input type="email" class="form-control clear-me required track-change validate-email purify" id="form-email" data-field="email">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="form-login" class="mr-2"><?php echo langHdl('privileges'); ?></label>
                            <input type="radio" class="form-check-input form-control flat-blue only-admin track-change" name="privilege" id="privilege-admin">
                            <label class="form-check-label mr-2 pointer" for="privilege-admin"><?php echo langHdl('administrator'); ?></label>
                            <input type="radio" class="form-check-input form-control flat-blue only-admin track-change" name="privilege" id="privilege-hr">
                            <label class="form-check-label mr-2 pointer" for="privilege-hr"><?php echo langHdl('super_manager'); ?></label>
                            <input type="radio" class="form-check-input form-control flat-blue only-admin track-change" name="privilege" id="privilege-manager">
                            <label class="form-check-label mr-2 pointer" for="privilege-manager"><?php echo langHdl('manager'); ?></label>
                            <input type="radio" class="form-check-input form-control flat-blue track-change" name="privilege" id="privilege-user">
                            <label class="form-check-label mr-2 pointer" for="privilege-user"><?php echo langHdl('user'); ?></label>
                            <input type="radio" class="form-check-input form-control flat-blue track-change" name="privilege" id="privilege-ro">
                            <label class="form-check-label mr-2 pointer" for="privilege-ro"><?php echo langHdl('read_only'); ?></label>
                        </div>
                        <div class="form-group not-for-admin">
                            <label for="form-roles"><?php echo langHdl('roles'); ?></label>
                            <select id="form-roles" class="form-control form-item-control select2 no-root required track-change" style="width:100%;" multiple="multiple">
                                <?php echo $optionsRoles; ?>
                            </select>
                        </div>
                        <div class="form-group not-for-admin">
                            <label for="form-managedby"><?php echo langHdl('managed_by'); ?></label>
                            <select id="form-managedby" class="form-control form-item-control select2 no-root required track-change" style="width:100%;">
                                <?php echo $optionsManagedBy; ?>
                            </select>
                        </div>
                        <div class="form-group not-for-admin">
                            <label for="form-auth"><?php echo langHdl('authorized_groups'); ?></label>
                            <select id="form-auth" class="form-control form-item-control select2 no-root track-change" style="width:100%;" multiple="multiple">
                                <?php echo $foldersList; ?>
                            </select>
                        </div>
                        <div class="form-group not-for-admin">
                            <label for="form-forbid"><?php echo langHdl('forbidden_groups'); ?></label>
                            <select id="form-forbid" class="form-control form-item-control select2 no-root track-change" style="width:100%;" multiple="multiple">
                                <?php echo $foldersList; ?>
                            </select>
                        </div>
                        <div class="form-group not-for-admin">
                            <label for="form-forbid"><?php echo langHdl('special'); ?></label>
                        </div>
                        <div class="form-group not-for-admin">
                            <input type="checkbox" class="form-check-input form-control flat-blue track-change" id="form-create-root-folder">
                            <label class="form-check-label mr-2" for="form-create-root-folder"><?php echo langHdl('can_create_root_folder'); ?></label>
                        </div>
                        <div class="form-group not-for-admin">
                            <input type="checkbox" class="form-check-input form-control flat-blue track-change" id="form-create-personal-folder">
                            <label class="form-check-label mr-2" for="form-create-personal-folder"><?php echo langHdl('enable_personal_folder_for_this_user'); ?></label>
                        </div>
                        <div class="form-group not-for-admin" id="group-create-special-folder">
                            <input type="checkbox" class="form-check-input form-control flat-blue track-change" id="form-create-special-folder">
                            <label class="form-check-label mr-2" for="form-create-special-folder"><?php echo langHdl('auto_create_folder_role'); ?></label>
                            <input type="text" class="form-control clear-me mt-1 purify" id="form-special-folder" data-field="" disabled="true" placeholder="<?php echo langHdl('label'); ?>">
                        </div>
                        <div class="form-group not-for-admin" id="form-create-mfa-enabled-div">
                            <input type="checkbox" class="form-check-input form-control flat-blue track-change" id="form-create-mfa-enabled">
                            <label class="form-check-label mr-2" for="form-create-mfa-enabled"><?php echo langHdl('mfa_enabled'); ?></label>
                        </div>
                    </div>
                    <!-- /.card-body -->
                </form>

                <div class="card-footer">
                    <button type="button" class="btn btn-primary tp-action" data-action="submit"><?php echo langHdl('submit'); ?></button>
                    <button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo langHdl('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- USER LOGS -->
    <div class="row hidden extra-form" id="row-logs">
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><?php echo langHdl('logs_for_user'); ?> <span id="row-logs-title"></span></h3>
                </div>

                <!-- /.card-header -->
                <!-- table start -->
                <div class="card-body form" id="user-logs">
                    <table id="table-logs" class="table table-striped table-responsive" style="width:100%">
                        <thead>
                            <tr>
                                <th><?php echo langHdl('date'); ?></th>
                                <th><?php echo langHdl('activity'); ?></th>
                                <th><?php echo langHdl('label'); ?></th>
                            </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>

                <div class="card-footer">
                    <button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo langHdl('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- USER VISIBLE FOLDERS -->
    <div class="row hidden extra-form" id="row-folders">
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><?php echo langHdl('access_rights_for_user'); ?> <span id="row-folders-title"></span></h3>
                </div>

                <!-- /.card-header -->
                <!-- table start -->
                <div class="card-body" id="row-folders-results">

                </div>

                <div class="card-footer">
                    <button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo langHdl('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- PROPAGATE USER RIGHTS -->
    <div class="row hidden extra-form" id="row-propagate">
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><?php echo langHdl('propagate_user_rights'); ?></h3>
                </div>

                <!-- /.card-header -->
                <div class="card-body">
                    <div class="row">
                        <div class="callout callout-info col-12">
                            <i class="fa-solid fa-info fa-lg mr-2"></i><?php echo langHdl('share_rights_info'); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="propagate-from"><?php echo langHdl('share_rights_source'); ?></label>
                        <select id="propagate-from" class="form-control form-item-control select2" style="width:100%;">
                            <?php echo $optionsRoles; ?>
                        </select>
                    </div>

                    <div class="form-group ml-5">
                        <label><i class="far fa-hand-point-right fa-xs mr-2"></i><?php echo langHdl('functions'); ?></label>
                        <span id="propagate-user-roles"></span>
                    </div>

                    <div class="form-group ml-5">
                        <label><i class="far fa-hand-point-right fa-xs mr-2"></i><?php echo langHdl('managed_by'); ?></label>
                        <span id="propagate-user-managedby"></span>
                    </div>

                    <div class="form-group ml-5">
                        <label><i class="far fa-hand-point-right fa-xs mr-2"></i><?php echo langHdl('authorized_groups'); ?></label>
                        <span id="propagate-user-allowed"></span>
                    </div>

                    <div class="form-group ml-5">
                        <label><i class="far fa-hand-point-right fa-xs mr-2"></i><?php echo langHdl('forbidden_groups'); ?></label>
                        <span id="propagate-user-fordidden"></span>
                    </div>

                    <div class="form-group">
                        <label for="propagate-to"><?php echo langHdl('share_rights_destination'); ?></label>
                        <select id="propagate-to" class="form-control form-item-control select2" style="width:100%;" multiple="multiple">
                            <?php echo $optionsRoles; ?>
                        </select>
                    </div>

                </div>


                <div class="card-footer">
                    <button type="button" class="btn btn-primary tp-action" data-action="do-propagate"><?php echo langHdl('perform'); ?></button>
                    <button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo langHdl('cancel'); ?></button>
                </div>

            </div>
        </div>
    </div>

</section>

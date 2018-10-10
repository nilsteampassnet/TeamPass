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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'users', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
$link->set_charset(DB_ENCODING);

// PREPARE LIST OF OPTIONS
$optionsManagedBy = '';
$optionsRoles = '';
$userRoles = explode(';', $_SESSION['fonction_id']);
// If administrator then all roles are shown
// else only the Roles the users is associated to.
if ((int) $_SESSION['is_admin'] === 1) {
    $optionsManagedBy .= '<option value="0">'.langHdl('administrators_only').'</option>';
}

$rows = DB::query(
    'SELECT id, title, creator_id
    FROM '.prefixTable('roles_title').'
    ORDER BY title ASC'
);
foreach ($rows as $record) {
    if ((int) $_SESSION['is_admin'] === 1 || in_array($record['id'], $_SESSION['user_roles']) === true) {
        $optionsManagedBy .= '<option value="'.$record['id'].'">'.langHdl('managers_of').' '.addslashes($record['title']).'</option>';
    }
    if ((int) $_SESSION['is_admin'] === 1
        || ((int) $_SESSION['user_manager'] === 1
        && (in_array($record['id'], $userRoles) === true || (int) $record['creator_id'] === (int) $_SESSION['user_id']))
    ) {
        $optionsRoles .= '<option value="'.$record['id'].'">'.addslashes($record['title']).'</option>';
    }
}

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

$treeDesc = $tree->getDescendants();
$foldersList = '';
foreach ($treeDesc as $t) {
    if (in_array($t->id, $_SESSION['groupes_visibles']) === true
        && in_array($t->id, $_SESSION['personal_visible_groups']) === false
    ) {
        $ident = '';
        for ($y = 1; $y < $t->nlevel; ++$y) {
            $ident .= '&nbsp;&nbsp;';
        }
        $foldersList .= '<option value="'.$t->id.'">'.$ident.htmlspecialchars($t->title, ENT_COMPAT, 'UTF-8').'</option>';
        $prev_level = $t->nlevel;
    }
}

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6">
        <h1 class="m-0 text-dark">
        <i class="fas fa-users mr-2"></i><?php echo langHdl('users'); ?>
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
                            <i class="fas fa-plus mr-2"></i><?php echo langHdl('new'); ?>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm tp-action mr-2" data-action="refresh">
                            <i class="fas fa-refresh mr-2"></i><?php echo langHdl('refresh'); ?>
                        </button>
                    </h3>
                </div>

                <!-- /.card-header -->
                <div class="card-body form table-responsive" id="users-list">
                    <table id="table-users" class="table table-bordered table-striped dt-responsive nowrap" style="width:100%">
                        <thead>
                        <tr>
                            <th></th>
                            <th><?php echo langHdl('user_login'); ?></th>
                            <th><?php echo langHdl('name'); ?></th>
                            <th><?php echo langHdl('lastname'); ?></th>
                            <th><?php echo langHdl('managed_by'); ?></th>
                            <th><?php echo langHdl('functions'); ?></th>
                            <th><i class="fas fa-user-cog fa-lg fa-fw infotip" title="<?php echo langHdl('god'); ?>"></i></th>
                            <th><i class="fas fa-user-tie fa-lg fa-fw infotip" title="<?php echo langHdl('gestionnaire'); ?>"></i></th>
                            <th><i class="fas fa-blind fa-lg fa-fw infotip" title="<?php echo langHdl('read_only_account'); ?>"></i></th>
                            <th><i class="fas fa-user-friends fa-lg fa-fw infotip" title="<?php echo langHdl('can_manage_all_users'); ?>"></i></th>
                            <th><i class="fas fa-code-branch fa-lg fa-fw infotip" title="<?php echo langHdl('can_create_root_folder'); ?>"></i></th>
                            <th><i class="fas fa-low-vision fa-lg fa-fw infotip" title="<?php echo langHdl('enable_personal_folder'); ?>"></i></th>
                        </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row hidden" id="row-form">
        <div class="col-12">
            <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo langHdl('user_definition'); ?></h3>
                    </div>
                    
                    <!-- /.card-header -->
                    <!-- form start -->
                    <form role="form">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="form-login"><?php echo langHdl('login'); ?></label>
                                        <input type="text" class="form-control clear-me" id="form-login">
                                    </div>
                                    <div class="form-group">
                                        <label for="form-name"><?php echo langHdl('name'); ?></label>
                                        <input type="text" class="form-control clear-me" id="form-name">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="form-email"><?php echo langHdl('email'); ?></label>
                                        <input type="email" class="form-control clear-me" id="form-email">
                                    </div>
                                    <div class="form-group">
                                        <label for="form-lastname"><?php echo langHdl('lastname'); ?></label>
                                        <input type="text" class="form-control clear-me" id="form-lastname">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="form-roles"><?php echo langHdl('roles'); ?></label>
                                <select id="form-roles" class="form-control form-item-control select2 no-root" style="width:100%;" multiple="multiple">
                                    <?php echo $optionsRoles; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="form-managedby"><?php echo langHdl('managed_by'); ?></label>
                                <select id="form-managedby" class="form-control form-item-control select2 no-root" style="width:100%;">
                                    <?php echo $optionsManagedBy; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="form-auth"><?php echo langHdl('authorized_groups'); ?></label>
                                <select id="form-auth" class="form-control form-item-control select2 no-root" style="width:100%;" multiple="multiple">
                                    <?php echo $foldersList; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="form-forbid"><?php echo langHdl('forbidden_groups'); ?></label>
                                <select id="form-forbid" class="form-control form-item-control select2 no-root" style="width:100%;" multiple="multiple">
                                    <?php echo $foldersList; ?>
                                </select>
                            </div>
                        </div>
                        <!-- /.card-body -->
                    </form>
                        
                    <div class="card-footer">
                        <button type="button" class="btn btn-primary" data-action="submit"><?php echo langHdl('submit'); ?></button>
                        <button type="button" class="btn btn-default float-right tp-action" data-action="cancel"><?php echo langHdl('cancel'); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

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
 * @copyright 2009-2019 Nils Laumaillé
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'utilities.renewal', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

// Load template
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-calendar mr-2"></i><?php echo langHdl('renewal'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->


<!-- Main content -->
<div class='content'>
    <div class='container-fluid'>
        <div class='row'>
            <div class='col-md-12'>
                
            <?php
            if (null !== DEBUG && DEBUG === false) {
                ?>
                <div class='card card-primary'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo langHdl('backup_and_restore'); ?></h3>
                    </div>
                    
                    <div class='card-body'>

                    </div>
                </div>
                    
            <?php
            } else {
                ?>
                <div class="mt-4">
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle mr-2"></i><?php echo langHdl('not_yet_implemented'); ?>
                    </div>
                </div>

                <?php
            }
            ?>
            </div>
        </div>
    
    </div>
</div>

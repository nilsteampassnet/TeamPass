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
 * @file      admin.php
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

// Load template
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
/* do checks */
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'admin', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark"><?php echo langHdl('admin'); ?></h1>
            </div><!-- /.col -->
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php?page=admin"><?php echo langHdl('admin'); ?></a></li>
                    <li class="breadcrumb-item active"><?php echo langHdl('admin_main'); ?></li>
                </ol>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->


<!-- Main content -->
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <div class="card card-default">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bullhorn mr-2"></i>
                            <?php echo langHdl('communication_means'); ?>
                        </h3>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="callout callout-info">
                            <h5><i class="fas fa-globe fa-lg fa-fw mr-2"></i>
                                <a class="text-info" target="_blank" href="#" class="link"><?php echo langHdl('website_canal'); ?></a></h5>
                        </div>
                        <div class="callout callout-info">
                            <h5><i class="fas fa-book fa-lg fa-fw mr-2"></i><?php echo langHdl('documentation_canal'); ?>
                                <a class="text-info" target="_blank" href="https://teampass.readthedocs.org">ReadTheDoc</a></h5>
                        </div>
                        <div class="callout callout-info">
                            <h5><i class="fas fa-bug fa-lg fa-fw mr-2"></i><?php echo langHdl('bug_canal'); ?>
                                <a class="text-info" target="_blank" href="https://github.com/nilsteampassnet/TeamPass/issues">Github</a></h5>
                        </div>
                        <div class="callout callout-info">
                            <h5><i class="fas fa-lightbulb fa-lg fa-fw mr-2"></i><?php echo langHdl('feature_request_canal'); ?>
                                <a class="text-info" target="_blank" href="https://teampass.userecho.com">User Echo</a></h5>
                        </div>
                        <div class="callout callout-info">
                            <h5><i class="fas fa-hands-helping fa-lg fa-fw mr-2"></i><?php echo langHdl('feature_support_canal'); ?>
                                <a class="text-info" target="_blank" href="https://www.reddit.com/r/TeamPass">Reddit</a></h5>
                        </div>
                        <div class="callout callout-info">
                            <h5><i class="fas fa-donate fa-lg fa-fw mr-2"></i><?php echo langHdl('consider_a_donation'); ?>
                                <a class="text-info" target="_blank" href="https://teampass.net/donation"><?php echo langHdl('more_information'); ?></a></h5>
                        </div>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col -->
            <div class="col-md-6">
                <div class="card card-default">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-info-circle mr-2"></i>
                            <?php echo langHdl('changelog'); ?>
                        </h3>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <?php
                        // Display the readme file
                        $Fnm = 'changelog.txt';
if (file_exists($Fnm)) {
    $tab = file($Fnm);
    if ($tab !== false) {
        $cnt = 0;
        foreach ($tab as $cle => $val) {
            if (! (strpos(trim($val), '/*') === 0 || strpos(trim($val), '*') === 0)) {
                if ($cnt < 19) {
                    echo $val . '<br />';
                    ++$cnt;
                } elseif ($cnt === 19) {
                    echo '...<br /><br /><b><a href="changelog.txt" target="_blank"><span class="fa fa-book"></span>' . langHdl('readme_open') . '</a></b>';
                    break;
                }
            }
        }
    }
}
                        ?>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content -->

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
 * @file      admin.php
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
use TiBeN\CrontabManager\CrontabJob;
use TiBeN\CrontabManager\CrontabAdapter;
use TiBeN\CrontabManager\CrontabRepository;

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
                            <i class="fas fa-hand-holding-heart mr-2 text-danger"></i>Become a sponsor
                        </h3>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <div class="alert bg-olive disabled" role="alert">
                            <p>People sponsoring my open source work each month help motivate me to devote time and resources to producing the best quality work I can for everyone who uses Teampass.</p>
                            <p>Read my <a href="https://github.com/sponsors/nilsteampassnet" target="_blank">Github Sponsor page</a>.</p>
                        </div>
                    </div>
                </div>
                
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
                                <a class="text-info" target="_blank" href="https://documentation.teampass.net/#/">ReadTheDoc</a></h5>
                        </div>
                        <div class="callout callout-info">
                            <h5><i class="fas fa-bug fa-lg fa-fw mr-2"></i><?php echo langHdl('bug_canal'); ?>
                                <a class="text-info" target="_blank" href="https://github.com/nilsteampassnet/TeamPass/issues">Github</a></h5>
                        </div>
                        <!--
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
                        -->
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>


            <!-- /.col -->
            <div class="col-md-6">
                <?php

?>
                <!--
                <div class="alert bg-lightblue disabled" role="alert">
                    <p><i class="fa-regular fa-eye mr-2"></i><?php echo langHdl('currently_using_version')." <b>".TP_VERSION.".<i>".TP_VERSION_MINOR."</i></b>"; ?></p>
                    <p><i class="fa-solid fa-code-commit mr-2"></i>
                    <?php
                        //$version = file_get_contents('version.txt', false, null, 543);
                        //echo langHdl('git_commit_value')." <b>".$version.
                        //    '</b><href="'.GITHUB_COMMIT_URL.$version.'" target="_blank"><i class="fa-solid fa-up-right-from-square ml-2" pointer></i></a>'; 
                    ?></p>
                </div>
                -->

                <div class="card card-default">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa-solid fa-barcode mr-2"></i>
                            <?php echo langHdl('teampass_information'); ?>
                        </h3>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <p><i class="fa-regular fa-eye mr-2"></i><?php echo langHdl('currently_using_version')." <b>".TP_VERSION.".<i>".TP_VERSION_MINOR."</i></b>"; ?></p>
                        <?php   
                        if (isset($SETTINGS['enable_tasks_manager']) === true && (int) $SETTINGS['enable_tasks_manager'] === 0) {
                            echo '<div class="alert bg-orange disabled" role="alert">
                                <h5><i class="fa-solid fa-exclamation-triangle mr-2"></i>Since 3.0.0.23, TASKS manager is enabled by default and is mandatory.</h5>
                                <p>Please ensure that cron job is set and enabled.<br />Open Tasks page and check status.</p>
                                <p><a href="https://documentation.teampass.net/#/manage/tasks" target="_blank"><i class="fa-solid fa-book mr-2"></i>Check documentation</a>.</p>
                            </div>';
                        } else {
                            ?>
                            <div class="">
<?php
require_once __DIR__.'/../includes/libraries/TiBeN/CrontabManager/CrontabAdapter.php';
require_once __DIR__.'/../includes/libraries/TiBeN/CrontabManager/CrontabJob.php';
require_once __DIR__.'/../includes/libraries/TiBeN/CrontabManager/CrontabRepository.php';

// Instantiate the adapter and repository
try {
    $crontabRepository = new CrontabRepository(new CrontabAdapter());
    $results = $crontabRepository->findJobByRegex('/Teampass\ scheduler/');
    if (count($results) === 0) {
        ?>
                            <div class="callout callout-info alert-dismissible mt-3" role="alert">
                                <h5><i class="fa-solid fa-info mr-2"></i><?php echo langHdl('information'); ?></h5>
                                <?php echo str_replace("#teampass_path#", $SETTINGS['cpassman_dir'], langHdl('tasks_information')); ?>
                                <div class="mt-2">
                                    <a href="index.php?page=tasks#settings" class="btn btn-info" role="button"><i class="fa-solid fa-arrow-up-right-from-square mr-2"></i><?php echo langHdl('open_tasks_settings'); ?></a>
                                </div>
                            </div>
        <?php
    } else {
        $job = (array) $results[0];//print_r($job);
        ?>
                            <div>
                                <i class="fa-solid fa-circle-check text-success mr-2"></i><?php echo langHdl('tasks_cron_running'); ?>
                                <div class="ml-3 mt-1">
                                    <span class=""><code><?php echo $job['taskCommandLine']; ?></code></span>
                                </div>
                            </div>
        <?php
    }
}
catch (Exception $e) {
    echo $e->getMessage();
}
?>
                        </div>
<?php                        
                        }
?>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->

                <div class="card card-default">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa-solid fa-plug mr-2"></i>
                            <?php echo langHdl('server'); ?>
                        </h3>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <?php
                        // Display information about server
                        $dbSize = DB::queryFirstRow("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'size' FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "'");

                        // Get OS
                        $uname = php_uname('s');
                        if (strpos($uname, 'Linux') !== false) {
                            $os = 'Linux';
                            if (file_exists('/etc/os-release')) {
                                $lines = file('/etc/os-release');
                                foreach ($lines as $line) {
                                    if (strpos($line, 'PRETTY_NAME=') !== false) {
                                        $parts = explode('=', $line);
                                        $os = trim($parts[1], "\"\n");
                                        if (strpos($os, 'Ubuntu') !== false) {
                                            $os = '<i class="fa-brands fa-ubuntu mr-2"></i>'.$os;
                                        } else if (strpos($os, 'Suse') !== false) {
                                            $os = '<i class="fa-brands fa-suse mr-2"></i>'.$os;
                                        } else if (strpos($os, 'redhat') !== false) {
                                            $os = '<i class="fa-brands fa-redhat mr-2"></i>'.$os;
                                        } else if (strpos($os, 'fedora') !== false) {
                                            $os = '<i class="fa-brands fa-fedora mr-2"></i>'.$os;
                                        } else if (strpos($os, 'centos') !== false) {
                                            $os = '<i class="fa-brands fa-centos mr-2"></i>'.$os;
                                        } else {
                                            $os = '<i class="fa-brands fa-linux mr-2"></i>'.$os;
                                        }
                                        break;
                                    }
                                }
                            }
                        } elseif (strpos($uname, 'Darwin') !== false) {
                            $os = '<i class="fa-solid fa-apple mr-2"></i>macOS';
                        } elseif (strpos($uname, 'Windows') !== false) {
                            $os = '<i class="fa-solid fa-windows mr-2"></i>Windows';
                        } else {
                            $os = 'Unknown';
                        }

                        echo 
                        '<p>' . $os.
                            '<br><span class="ml-4"></span>'.
                        '</p>'.
                        '<p><i class="fa-brands fa-php mr-2"></i>PHP version: ' . phpversion().
                            '<br><span class="ml-4">Memory limit: '.(ini_get('memory_limit')).'</span>'.
                            '<br><span class="ml-4">Memory usage: '.formatSizeUnits(memory_get_usage()).'</span>'.
                            '<br><span class="ml-4">Maximum time execution: '.ini_get('max_execution_time').'</span>'.
                            '<br><span class="ml-4">Maximum file size upload: '.ini_get('upload_max_filesize').'</span>'.
                        '</p>'.
                        '<p><i class="fa-solid fa-server mr-2"></i>Server version: ' . DB::serverVersion().
                            '<br><span class="ml-4">Database size: '.($dbSize['size']).'MB</span>'.
                        '</p>';

                        // local time
                        $serverTime = localtime(time(), true);
                        echo '<div class="row">'.
                            '<div class="col-6"><i class="fa-solid fa-clock mr-2"></i>Server time:</div>'.
                            '<div class="col-6"><span class="badge badge-info">' . $serverTime['tm_hour'].':'.$serverTime['tm_min'].':'.$serverTime['tm_sec'].'</span></div>'.
                        '</div>'.
                        '<div class="row">'.
                            '<div class="col-6"><span class="ml-4">Timezone:</span></div>'.
                            '<div class="col-6"><span class="badge badge-info">'.date_default_timezone_get().'</span></div>'.
                        '</div>';

                        ?>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->

            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->

        <div class="row">
            <div class="col-md-12">
                <div class="card card-default">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-info-circle mr-2"></i>
                                <?php echo langHdl('information'); ?>
                            </h3>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <?php
                            // Display the readme file
                            $homepage = file_get_contents('changelog.txt', false, null, 543);
                            echo $homepage;
                            ?>
                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->
            </div>
        </div>
    </div><!-- /.container-fluid -->
</div>
<!-- /.content -->

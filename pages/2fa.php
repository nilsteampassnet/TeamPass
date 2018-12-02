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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], '2fa', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

// Load template
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

//get infos from SETTINGS.PHP file
$filename = $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
$events = '';
if (file_exists($filename)) {
    //copy some constants from this existing file
    $settingsFile = file($filename);
    if ($settingsFile !== false) {
        foreach ($settingsFile as $key => $val) {
            if (substr_count($val, "@define('SECUREPATH'")) {
                $skfile = substr($val, 23, strpos($val, "');") - 23).'/sk.php';
            }
        }
    }
}

// read SK.PHP file
$duoAkey = $duoIkey = $duoSkey = $duoHost = '';
$skFile = file($skfile);
if ($skFile !== false) {
    foreach ($skFile as $key => $val) {
        if (substr_count($val, "@define('AKEY'") > 0) {
            $duoAkey = substr($val, 21, strlen($val) - 26);
        } elseif (substr_count($val, "@define('IKEY'") > 0) {
            $duoIkey = substr($val, 21, strlen($val) - 26);
        } elseif (substr_count($val, "@define('SKEY'") > 0) {
            $duoSkey = substr($val, 21, strlen($val) - 26);
        } elseif (substr_count($val, "@define('HOST'") > 0) {
            $duoHost = substr($val, 21, strlen($val) - 26);
        }
    }
}

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                <i class="fas fa-qrcode mr-2"></i><?php echo langHdl('mfa'); ?>
                </h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs">
                            <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#google" aria-controls="google" aria-selected="true"><?php echo langHdl('google_2fa'); ?></a>
                            </li>
                            <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#duo" role="tab" aria-controls="duo" aria-selected="false"><?php echo langHdl('duo_security'); ?></a>
                            </li>
                            <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#yubico" role="tab" aria-controls="yubico" aria-selected="false"><?php echo langHdl('yubico'); ?></a>
                            </li>
                            <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#agses" role="tab" aria-controls="agses" aria-selected="false"><?php echo langHdl('agses'); ?></a>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body">
                        <div class="tab-content">

                            <div class="tab-pane fade show active" id="google" role="tabpanel" aria-labelledby="google-tab">
                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo langHdl('admin_2factors_authentication_setting'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('admin_2factors_authentication_setting_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3">
                                        <div class="toggle toggle-modern" id="google_authentication" data-toggle-on="<?php echo isset($SETTINGS['google_authentication']) && $SETTINGS['google_authentication'] == 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="google_authentication_input" value="<?php echo isset($SETTINGS['google_authentication']) && $SETTINGS['google_authentication'] == 1 ? '1' : '0'; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo langHdl('admin_ga_website_name'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('admin_ga_website_name_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3">
                                        <input type="text" class="form-control form-control-sm" id="ga_website_name" value="<?php echo isset($SETTINGS['ga_website_name']) === true ? $SETTINGS['ga_website_name'] : ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo langHdl('ga_reset_by_user'); ?>
                                        <small id='passwordHelpBlock' class='form-text text-muted'>
                                            <?php echo langHdl('ga_reset_by_user_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3">
                                        <div class="toggle toggle-modern" id="ga_reset_by_user" data-toggle-on="<?php echo isset($SETTINGS['ga_reset_by_user']) && $SETTINGS['ga_reset_by_user'] == 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="ga_reset_by_user_input" value="<?php echo isset($SETTINGS['ga_reset_by_user']) && $SETTINGS['ga_reset_by_user'] == 1 ? '1' : '0'; ?>">
                                    </div>
                                </div>

                            </div>

                            <div class="tab-pane" id="duo" role="tabpanel" aria-labelledby="duo-tab">
                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo langHdl('settings_duo'); ?>
                                        <small id="passwordHelpBlock" class="form-text text-muted">
                                            <?php echo langHdl('settings_duo_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3">
                                        <div class="toggle toggle-modern" id="duo" data-toggle-on="<?php echo isset($SETTINGS['duo']) && $SETTINGS['duo'] == 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="duo_input" value="<?php echo isset($SETTINGS['duo']) && $SETTINGS['duo'] == 1 ? '1' : '0'; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo langHdl('admin_duo_intro'); ?>
                                        <small id="passwordHelpBlock" class="form-text text-muted">
                                            <?php echo langHdl('settings_duo_explanation'); ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-5">
                                        <?php echo langHdl('admin_duo_akey'); ?>
                                    </div>
                                    <div class="col-7 input-group mb-0">
                                        <input type="text" class="form-control form-control-sm" id="duo_akey" value="<?php echo $duoAkey; ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary btn-no-click infotip generate-key" data-length="40" title="<?php echo langHdl('pw_generate'); ?>"><i class="fas fa-random"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5">
                                        <?php echo langHdl('admin_duo_ikey'); ?>
                                    </div>
                                    <div class="col-7">
                                        <input type="text" class="form-control form-control-sm" id="duo_ikey" value="<?php echo $duoIkey; ?>">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5">
                                        <?php echo langHdl('admin_duo_skey'); ?>
                                    </div>
                                    <div class="col-7">
                                        <input type="text" class="form-control form-control-sm" id="duo_skey" value="<?php echo $duoSkey; ?>">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5">
                                        <?php echo langHdl('admin_duo_host'); ?>
                                    </div>
                                    <div class="col-7">
                                        <input type="text" class="form-control form-control-sm" id="duo_host" value="<?php echo $duoHost; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <button class="btn btn-primary" id="button-duo-save">
                                        <?php echo langHdl('save'); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="tab-pane" id="yubico" role="tabpanel" aria-labelledby="yubico-tab">
                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo langHdl('admin_yubico_authentication_setting'); ?>
                                        <small id="passwordHelpBlock" class="form-text text-muted">
                                            <?php echo langHdl('yubico_authentication_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3">
                                        <div class="toggle toggle-modern" id="yubico_authentication" data-toggle-on="<?php echo isset($SETTINGS['yubico_authentication']) && $SETTINGS['yubico_authentication'] == 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="yubico_authentication_input" value="<?php echo isset($SETTINGS['yubico_authentication']) && $SETTINGS['yubico_authentication'] == 1 ? '1' : '0'; ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane" id="agses" role="tabpanel" aria-labelledby="agses-tab">
                                <div class="card-info">
                                    This is not yet implemented
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

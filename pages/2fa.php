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
 * @file      2fa.php
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], '2fa', $SETTINGS) === false) {
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
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class='card-title'><?php echo langHdl('mfa_configuration'); ?></h3>
                    </div>

                    <div class="card-body">

                        <div class="row mb-4">
                            <div class="col-9">
                                <?php echo langHdl('2factors_expected_for_admin'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo langHdl('2factors_expected_for_admin_tip'); ?>
                                </small>
                            </div>
                            <div class="col-3">
                                <div class="toggle toggle-modern" id="admin_2fa_required" data-toggle-on="<?php echo isset($SETTINGS['admin_2fa_required']) && (int) $SETTINGS['admin_2fa_required'] === 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="admin_2fa_required_input" value="<?php echo isset($SETTINGS['admin_2fa_required']) && (int) $SETTINGS['admin_2fa_required'] === 1 ? '1' : '0'; ?>">
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-6">
                                <?php echo langHdl('mfa_for_roles'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo langHdl('mfa_for_roles_tip'); ?>
                                </small>
                            </div>
                            <div class='col-6'>
                                <select class='form-control form-control-sm select2 disabled' id='mfa_for_roles' onchange='' multiple="multiple" style="width:100%;">
                                    <?php
                                    // Get selected groups
                                    $arrRolesMFA = json_decode($SETTINGS['mfa_for_roles'], true);
                                    if ($arrRolesMFA === 0 || empty($arrRolesMFA) === true) {
                                        $arrRolesMFA = [];
                                    }
                                    // Get full list
                                    $roles = performDBQuery(
                                        $SETTINGS,
                                        'id, title',
                                        'roles_title'
                                    );
                                    foreach ($roles as $role) {
                                        echo '
                                    <option value="' . $role['id'] . '"', in_array($role['id'], $arrRolesMFA) === true ? ' selected' : '', '>' . addslashes($role['title']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <ul class="nav nav-tabs mb-4">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#google" aria-controls="google" aria-selected="true"><?php echo langHdl('google_2fa'); ?></a>
                            </li>
                            <!--
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#duo" role="tab" aria-controls="duo" aria-selected="false"><?php echo langHdl('duo_security'); ?></a>
                            </li>
                                <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#yubico" role="tab" aria-controls="yubico" aria-selected="false"><?php echo langHdl('yubico'); ?></a>
                            </li>
                                -->
                        </ul>
                        <div class="tab-content">

                            <div class="tab-pane fade show active" id="google" role="tabpanel" aria-labelledby="google-tab">
                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo langHdl('admin_2factors_authentication_setting'); ?>
                                        <small class='form-text text-muted'>
                                            <?php echo langHdl('admin_2factors_authentication_setting_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3">
                                        <div class="toggle toggle-modern" id="google_authentication" data-toggle-on="<?php echo isset($SETTINGS['google_authentication']) && (int) $SETTINGS['google_authentication'] === 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="google_authentication_input" value="<?php echo isset($SETTINGS['google_authentication']) && (int) $SETTINGS['google_authentication'] === 1 ? '1' : '0'; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo langHdl('admin_ga_website_name'); ?>
                                        <small class='form-text text-muted'>
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
                                        <small class='form-text text-muted'>
                                            <?php echo langHdl('ga_reset_by_user_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3">
                                        <div class="toggle toggle-modern" id="ga_reset_by_user" data-toggle-on="<?php echo isset($SETTINGS['ga_reset_by_user']) && (int) $SETTINGS['ga_reset_by_user'] === 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="ga_reset_by_user_input" value="<?php echo isset($SETTINGS['ga_reset_by_user']) && (int) $SETTINGS['ga_reset_by_user'] === 1 ? '1' : '0'; ?>">
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
                                        <div>
                                            <small><a href="https://duo.com/docs/duoweb#overview" target="_blank"><?php echo langHdl('more_information'); ?></a></small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="toggle toggle-modern" id="duo" data-toggle-on="<?php echo isset($SETTINGS['duo']) && (int) $SETTINGS['duo'] === 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="duo_input" value="<?php echo isset($SETTINGS['duo']) && (int) $SETTINGS['duo'] === 1 ? '1' : '0'; ?>">
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

                                <!--
                                <div class="row mb-2">
                                    <div class="col-5">
                                        <?php echo langHdl('admin_duo_akey'); ?>
                                    </div>
                                    <div class="col-7 input-group mb-0">
                                        <input type="text" class="form-control form-control-sm" id="duo_akey" value="<?php echo isset($SETTINGS['duo_akey']) === true ? $SETTINGS['duo_akey'] : ''; ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary btn-no-click infotip generate-key" data-length="40" title="<?php echo langHdl('pw_generate'); ?>"><i class="fas fa-random"></i></button>
                                        </div>
                                    </div>
                                </div>
                                -->
                                <div class="row mb-2">
                                    <div class="col-5">
                                        <?php echo langHdl('admin_duo_ikey'); ?>
                                    </div>
                                    <div class="col-7">
                                        <input type="text" class="form-control form-control-sm" id="duo_ikey" value="<?php echo isset($SETTINGS['duo_ikey']) === true ? $SETTINGS['duo_ikey'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5">
                                        <?php echo langHdl('admin_duo_skey'); ?>
                                    </div>
                                    <div class="col-7">
                                        <input type="text" class="form-control form-control-sm" id="duo_skey" value="<?php echo isset($SETTINGS['duo_skey']) === true ? $SETTINGS['duo_skey'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5">
                                        <?php echo langHdl('admin_duo_host'); ?>
                                    </div>
                                    <div class="col-7">
                                        <input type="text" class="form-control form-control-sm" id="duo_host" value="<?php echo isset($SETTINGS['duo_host']) === true ? $SETTINGS['duo_host'] : ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <button class="btn btn-primary" id="button-duo-save">
                                        <?php echo langHdl('save'); ?>
                                    </button>
                                </div>
                            </div>

                            <!--
                            <div class="tab-pane" id="yubico" role="tabpanel" aria-labelledby="yubico-tab">
                                <div class="row mb-2">
                                    <div class="col-9">
                                        <?php echo langHdl('admin_yubico_authentication_setting'); ?>
                                        <small id="passwordHelpBlock" class="form-text text-muted">
                                            <?php echo langHdl('yubico_authentication_tip'); ?>
                                        </small>
                                    </div>
                                    <div class="col-3">
                                        <div class="toggle toggle-modern" id="yubico_authentication" data-toggle-on="<?php echo isset($SETTINGS['yubico_authentication']) && (int) $SETTINGS['yubico_authentication'] === 1 ? 'true' : 'false'; ?>"></div><input type="hidden" id="yubico_authentication_input" value="<?php echo isset($SETTINGS['yubico_authentication']) && (int) $SETTINGS['yubico_authentication'] === 1 ? '1' : '0'; ?>">
                                    </div>
                                </div>
                            </div>
                                -->

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    </div>

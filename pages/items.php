<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      items.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';
// init
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config if $SETTINGS not defined
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('items') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

// Prepare SESSION variables
$session_user_admin = $session->get('user-admin');

if ((int) $session_user_admin === 1) {
    $session->set('user-accessible_folders', $session->get('user-personal_visible_folders'));
}

// Get list of users
$usersList = [];
$rows = DB::query('SELECT id,login,email FROM ' . prefixTable('users') . ' ORDER BY login ASC');
foreach ($rows as $record) {
    $usersList[$record['login']] = [
        'id' => $record['id'],
        'login' => $record['login'],
        'email' => $record['email'],
    ];
}
// Get list of roles
$arrRoles = [];
$listRoles = '';
$rows = DB::query('SELECT id,title FROM ' . prefixTable('roles_title') . ' ORDER BY title ASC');
foreach ($rows as $reccord) {
    $arrRoles[$reccord['title']] = [
        'id' => $reccord['id'],
        'title' => $reccord['title'],
    ];
    if (empty($listRoles)) {
        $listRoles = $reccord['id'] . '#' . $reccord['title'];
    } else {
        $listRoles .= ';' . $reccord['id'] . '#' . $reccord['title'];
    }
}

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-2">
                <h1 class="m-0 text-dark"><i class="fa-solid fa-key mr-2"></i><?php echo $lang->get('items'); ?></h1>
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

    <!-- EXPIRED ITEM -->
    <div class="row hidden" id="card-item-expired">
        <div class="col-12">
            <div class="alert alert-danger">
                <h5><i class="fa-solid fa-exclamation-triangle mr-2"></i><?php echo $lang->get('warning'); ?></h5>
                <?php echo $lang->get('pw_is_expired_-_update_it'); ?>
            </div>
        </div>
    </div>

    <!-- ITEM FORM -->
    <div class="row hidden form-item">
        <div class="col-12">

            <div class="card text-center">
                <div class="card-header">
                    <div class="card-tools-left">
                        <button type="button" class="btn btn-secondary but-back" id="but_back_top_left">
                            <i class="fa-solid fa-arrow-left"></i>
                        </button>
                    </div>

                    <h5 id="form-item-title" class="clear-me-html" style="min-height:23px;"></h5>

                    <div class="card-tools">
                        <button type="button" class="btn btn-secondary btn-sm but-back" id="but_back_top_right">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div>
                        <label><i class="fa-solid fa-users mr-2"></i><?php echo $lang->get('visible_by'); ?></label>
                        <span id="card-item-visibility" class="text-info font-weight-bold ml-2"></span>
                    </div>
                    <div>
                        <label><i class="fa-solid fa-key mr-2"></i><?php echo $lang->get('complex_asked'); ?></label>
                        <span id="card-item-minimum-complexity" class="text-info font-weight-bold ml-2"></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex">
                    <ul class="nav nav-pills" id="form-item-nav-pills">
                        <li class="nav-item"><a class="nav-link active" href="#tab_1" data-toggle="tab"><i class="fa-solid fa-home mr-2"></i><?php echo $lang->get('main'); ?></a></li>
                        <li class="nav-item"><a class="nav-link" href="#tab_2" data-toggle="tab"><i class="fa-solid fa-list mr-2"></i><?php echo $lang->get('details'); ?></a></li>
                        <li class="nav-item"><a class="nav-link" href="#tab_3" data-toggle="tab"><i class="fa-solid fa-archive mr-2"></i><?php echo $lang->get('attachments'); ?></a></li>
                        <?php
                        echo isset($SETTINGS['item_extra_fields']) === true && (int) $SETTINGS['item_extra_fields'] === 1 ? '
                            <li class="nav-item"><a class="nav-link" href="#tab_4" data-toggle="tab"><i class="fa-solid fa-cubes mr-2"></i>' . $lang->get('fields') . '</a></li>' : '';
                        ?>
                    </ul>
                </div><!-- /.card-header -->
                <div class="card-body">
                    <form id="form-item" class="needs-validation" novalidate onsubmit="return false;">
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- LABEL -->
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('label'); ?></span>
                                    </div>
                                    <input id="form-item-label" type="text" class="form-control form-item-control purify" data-change-ongoing="" data-field-name="label">
                                </div>
                                <!-- DESCRIPTION -->
                                <div class="mb-3">
                                    <div id="form-item-description" class="form-item-control w-100 clear-me-html purify purifyHtml" data-field-name="description" data-change-ongoing=""></div>
                                </div>
                                <!-- LOGIN -->
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('login'); ?></span>
                                    </div>
                                    <input id="form-item-login" type="text" class="form-control form-item-control purify" data-field-name="login" data-change-ongoing="">
                                </div>
                                <!-- PASSWORD -->
                                <div class="input-group mb-2">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('password'); ?></span>
                                    </div>
                                    <input id="form-item-password" type="password" class="form-control form-item-control" placeholder="<?php echo $lang->get('password'); ?>" data-field-name="pwd" data-change-ongoing="">
                                    <div class="input-group-append">
                                        <button class="btn btn-secondary btn-no-click infotip password-generate" id="item-button-password-generate" title="<?php echo $lang->get('pw_generate'); ?>" data-id="form-item-password"><i class="fa-solid fa-random"></i></button>
                                        <button class="btn btn-secondary btn-no-click infotip" id="item-button-password-showOptions" title="<?php echo $lang->get('options'); ?>"><i class="fa-solid fa-sliders-h"></i></button>
                                        <button class="btn btn-secondary btn-no-click infotip" id="item-button-password-show" title="<?php echo $lang->get('mask_pw'); ?>"><i class="fa-solid fa-low-vision"></i></button>
                                    </div>
                                </div>
                                <div class="container-fluid mb-0">
                                    <div class="row">
                                        <div class="col-md-12 justify-content-center">
                                            <div id="form-item-password-strength" class="justify-content-center"></div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="form-item-password-complex" value="0">
                                <div class="mt-1 hidden" id="form-item-password-options">
                                    <div class="btn-toolbar justify-content-center" role="toolbar" aria-label="Toolbar with button groups">
                                        <div class="btn-group btn-group-sm btn-group-toggle mr-2" data-toggle="buttons">
                                            <label class="btn btn-secondary btn-sm">
                                                <input type="checkbox" class="password-definition" id="pwd-definition-lcl">abc</label>
                                            <label class="btn btn-secondary btn-sm">
                                                <input type="checkbox" class="password-definition" id="pwd-definition-ucl">ABC</label>
                                            <label class="btn btn-secondary btn-sm">
                                                <input type="checkbox" class="password-definition" id="pwd-definition-numeric">123</label>
                                            <label class="btn btn-secondary btn-sm">
                                                <input type="checkbox" class="password-definition" id="pwd-definition-symbols">@#&amp;</label>
                                            <label class="btn btn-secondary btn-sm">
                                                <input type="checkbox" class="password-definition" id="pwd-definition-secure"><?php echo $lang->get('secure'); ?></label>
                                        </div>

                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text"><?php echo $lang->get('size'); ?></div>
                                            </div>
                                            <select class="form-control form-control-sm w-10" id="pwd-definition-size">
                                                <?php
                                                for ($i = 4; $i <= $SETTINGS['pwd_maximum_length']; ++$i) {
                                                    echo '
                                                <option>' . $i . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <!-- EMAIL -->
                                <div class="input-group mb-3 mt-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('email'); ?></span>
                                    </div>
                                    <input id="form-item-email" type="email" class="form-control form-item-control purify" data-field-name="email" data-change-ongoing="">
                                </div>
                                <!-- URL -->
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('url'); ?></span>
                                    </div>
                                    <input id="form-item-url" type="url" class="form-control form-item-control purify" data-field-name="url" data-change-ongoing="">
                                </div>
                                <!-- ICON -->
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><?php echo $lang->get('icon'); ?></span>
                                    </div>
                                    <input id="form-item-icon" type="text" class="form-control form-item-control purify" data-field-name="icon" data-change-ongoing="">
                                    <span class="ml-2 col-md-1 clear-me-html" id="form-item-icon-show"></span>
                                </div>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('fontawesome_icon_tip'); ?><a href="<?php echo FONTAWESOME_URL;?>" target="_blank"><i class="fa-solid fa-external-link-alt ml-1"></i></a>
                                </small>
                            </div>

                            <div class="tab-pane" id="tab_2">
                                <!-- FOLDERS -->
                                <div class="form-group mb-3">
                                    <div class="input-group">
                                        <div class="input-group-prepend input-group-text"><?php echo $lang->get('folder'); ?></div>
                                        <select id="form-item-folder" class="form-control form-item-control select2-hidden-accessible select2 no-root" data-change-ongoing=""></select>
                                    </div>
                                </div>

                                <!-- RESTRICTED TO -->
                                <div class="input-group mb-3">
                                    <div class="input-group">
                                        <div class="input-group-prepend input-group-text"><?php echo $lang->get('restricted_to'); ?></div>
                                        <select id="form-item-restrictedto" class="form-control form-item-control select2-hidden-accessible select2" multiple="multiple" data-change-ongoing=""></select>
                                        <input type="hidden" id="form-item-restrictedToUsers" class="form-item-control">
                                        <input type="hidden" id="form-item-restrictedToRoles" class="form-item-control">
                                    </div>
                                </div>
                                <!-- TAGS -->
                                <div class="input-group mb-3">
                                    <div class="input-group">
                                        <div class="input-group-prepend input-group-text"><?php echo $lang->get('tags'); ?></div>
                                        <!--<select id="form-item-tags" class="form-control form-item-control select2-hidden-accessible select2 no-root" multiple="multiple" data-change-ongoing=""></select>-->
                                        <input id="form-item-tags" type="text" class="form-control form-item-control autocomplete purify" data-change-ongoing="" data-field-name="tags">
                                    </div>
                                    <!---->
                                </div>
                                <!-- ANYONE CAN MODIFY -->
                                <?php
                                if (
                                    isset($SETTINGS['anyone_can_modify']) === true
                                    && (int) $SETTINGS['anyone_can_modify'] === 1
                                ) {
                                    ?>
                                    <div class="form-check mb-3 icheck-blue">
                                        <input type="checkbox" class="form-check-input form-item-control flat-blue" id="form-item-anyoneCanModify" <?php
                                            echo isset($SETTINGS['anyone_can_modify_bydefault']) === true
                                            && (int) $SETTINGS['anyone_can_modify_bydefault'] === 1 ? ' checked' : ''; ?> data-change-ongoing="">
                                        <label class="form-check-label ml-3" for="form-item-anyoneCanModify"><?php echo $lang->get('anyone_can_modify'); ?></label>
                                    </div>
                                <?php
                                }
                                ?>

                                <!-- OTP FOR ITEM -->
                                <div class="callout callout-primary mb-3">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fa-solid fa-qrcode"></i>
                                            <?php echo $lang->get('otp'); ?>
                                        </h3>
                                    </div>
                                    <!-- /.card-header -->
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="d-inline p-2 col-4">
                                                <?php echo $lang->get('phone_number'); ?>
                                            </div>
                                            <div class="d-inline p-2 col-4">
                                                <input type="phone" class="form-control form-item-control purify" data-field-name="otpPhoneNumber" id="form-item-otpPhoneNumber" data-change-ongoing="">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="d-inline p-2 col-4">
                                                <?php echo $lang->get('otp_secret'); ?>
                                            </div>
                                            <div class="d-inline p-2 col-8">
                                                <input type="text" class="form-control form-item-control purify" data-field-name="otpSecret" id="form-item-otpSecret" data-change-ongoing="">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="form-check mb-3 icheck-blue">
                                                <input type="checkbox" class="form-check-input form-item-control flat-blue ml-0" id="form-item-otp" data-change-ongoing="">
                                                <label class="form-check-label ml-3" for="form-item-otp"><?php echo $lang->get('enabled_otp_for_item'); ?></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- DELETE AFTER CONSULTATION -->
                                <?php
                                if (
                                    isset($SETTINGS['enable_delete_after_consultation']) === true
                                    && (int) $SETTINGS['enable_delete_after_consultation'] === 1
                                ) {
                                    ?>
                                    <div class="callout callout-primary mb-3">
                                        <div class="card-header">
                                            <h3 class="card-title">
                                                <i class="fa-solid fa-eraser"></i>
                                                <?php echo $lang->get('allow_item_to_be_deleted'); ?>
                                            </h3>
                                        </div>
                                        <!-- /.card-header -->
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="d-inline p-2 col-4">
                                                    <?php echo $lang->get('item_deleted_after_being_viewed_x_times'); ?>
                                                </div>
                                                <div class="d-inline p-2 col-8">
                                                    <input type="number" class="form-control form-item-control purify" data-field-name="deleteAfterShown" id="form-item-deleteAfterShown" data-change-ongoing="">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="d-inline p-2 col-4">
                                                    <?php echo $lang->get('item_deleted_after_date'); ?>
                                                </div>
                                                <div class="d-inline p-2 col-8">
                                                    <div class="input-group date inline">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text">
                                                                <i class="fa-solid fa-calendar"></i>
                                                            </span>
                                                        </div>
                                                        <input type="text" class="form-control float-right form-item-control purify" data-field-name="deleteAfterDate" id="form-item-deleteAfterDate" data-change-ongoing="">
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
                                            <i class="fa-solid fa-bullhorn"></i>
                                            <?php echo $lang->get('anounce_item_by_email'); ?>
                                        </h3>
                                    </div>
                                    <!-- /.card-header -->
                                    <div class="card-body">
                                        <select id="form-item-anounce" class="form-control form-item-control select2" style="width:100%;" multiple="multiple" data-placeholder="<?php echo $lang->get('select_users_if_needed'); ?>" data-change-ongoing=""></select>
                                    </div>
                                </div>
                            </div>

                            <!-- ATTACHMENTS -->
                            <div class="tab-pane" id="tab_3">
                                <div class="callout callout-primary mb-3 hidden" id="form-item-attachments-zone">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fa-solid fa-paperclip mr-3"></i>
                                            <?php echo $lang->get('attached_files'); ?>
                                        </h3>
                                    </div>
                                    <!-- /.card-header -->
                                    <div class="card-body clear-me-html" id="form-item-attachments">
                                    </div>
                                </div>
                                <div class="callout callout-primary mb-3">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fa-solid fa-plus mr-3"></i>
                                            <?php echo $lang->get('select_files'); ?>
                                        </h3>
                                    </div>
                                    <!-- /.card-header -->
                                    <div class="card-body">
                                        <div class="row" id="form-item-upload-zone">
                                            <div class="col-6">
                                                <a class="btn btn-app text-capitalize" id="form-item-attach-pickfiles">
                                                    <i class="fa-solid fa-search mr-1"></i><?php echo $lang->get('select'); ?>
                                                </a>
                                                <a class="btn btn-app" id="form-item-upload-pickfiles">
                                                    <i class="fa-solid fa-upload mr-1"></i><?php echo $lang->get('start_upload'); ?>
                                                </a>
                                                <input type="hidden" id="form-item-hidden-pickFilesNumber" value="0" />
                                                <small class="form-text text-muted">
                                                    <?php echo $lang->get('add_files_and_click_start'); ?>
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
                                        $session_item_fields = $session->get('system-item_fields');//print_r($session_item_fields);
                                        if (isset($session_item_fields) === true) {
                                            foreach ($session_item_fields as $category) {
                                                //print_r($category);
                                                echo '
                                            <div class="callout callout-info form-item-category hidden" id="form-item-category-' . $category['id'] . '">
                                                <h5>' . $category['title'] . '</h5>
                                                <p>';
                                                foreach ($category['fields'] as $field) {
                                                    if ($field['type'] === 'textarea') {
                                                        echo '
                                                    <div class="form-group mb-3 form-item-field" id="form-item-field-' . $field['id'] . '" data-field-id="' . $field['id'] . '">
                                                        <label>' . $field['title'],
                                                            $field['is_mandatory'] === '1' ?
                                                                '<span class="fa-solid fa-fire text-danger ml-1 infotip" title="' . $lang->get('is_mandatory') . '"></span>' : '',
                                                            '</label>
                                                        <textarea class="form-control form-item-control form-item-field-custom" rows="2" data-field-name="' . $field['id'] . '" data-field-mandatory="' . $field['is_mandatory'] . '" data-field-regex="' . $field['regex'] . '" data-change-ongoing="0"></textarea>
                                                    </div>';
                                                    } else {
                                                        echo '
                                                    <div class="input-group mb-3 form-item-field" id="form-item-field-' . $field['id'] . '" data-field-id="' . $field['id'] . '">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text">' . $field['title'],
                                                            $field['is_mandatory'] === '1' ?
                                                                '<span class="fa-solid fa-fire text-danger ml-1 infotip" title="' . $lang->get('is_mandatory') . '"></span>' : '',
                                                            '</span>
                                                        </div>
                                                        <input type="' . $field['type'] . '" class="form-control form-item-control form-item-field-custom" data-field-name="' . $field['id'] . '" data-field-mandatory="' . $field['is_mandatory'] . '" data-field-regex="' . $field['regex'] . '" data-change-ongoing="0">
                                                    </div>';
                                                    }
                                                    if (!empty($field['regex'])) {
                                                        echo '
                                                    <div>Regex: '. $field['regex'] .'</div>
                                                        ';
                                                    }
                                                }
                                                // Manage template
                                                if (
                                                    isset($SETTINGS['item_creation_templates']) === true
                                                    && $SETTINGS['item_creation_templates'] === '1'
                                                ) {
                                                    echo '
                                                    <div class="form-check icheck-blue">
                                                        <input type="checkbox" class="form-check-input form-check-input-template form-item-control flat-blue" data-category-id="' . $category['id'] . '" data-change-ongoing="0" data-field-name="template" id="template_' . $category['id'] . '">
                                                        <label class="form-check-label ml-3" for="template_' . $category['id'] . '">' . $lang->get('main_template') . '</label>
                                                    </div>';
                                                }
                                                echo '
                                                </p>
                                            </div>';
                                            }
                                        } else {
                                            echo
                                            '<div class="alert alert-info">
                                                <h5><i class="icon fa fa-info mr-3"></i>' . $lang->get('information') . '</h5>
                                                ' . $lang->get('no_fields') . '
                                            </div>';
                                        } ?>
                                </div>
                                <div class="alert alert-info hidden no-item-fields">
                                    <h5><i class="icon fa fa-info mr-3"></i><?php echo $lang->get('information'); ?></h5>
                                    <?php echo $lang->get('no_fields'); ?>
                                </div>
                            </div>

                        </div>
                    </form>
                </div>
                <div class="card-footer" id="form-item-buttons">
                    <button type="button" class="btn btn-info mr-2" id="form-item-button-save" data-action=""><?php echo $lang->get('save'); ?></button>
                    <button type="button" class="btn btn-default but-back item-edit"><?php echo $lang->get('cancel'); ?></button>
                </div>
                <!-- /.card-footer -->
            </div>
        </div>
    </div>

    <!-- COPY ITEM FORM -->
    <div class="row hidden form-item-copy form-item-action">
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h5><i class="fa-solid fa-copy mr-2"></i><?php echo $lang->get('copy_item'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label><?php echo $lang->get('new_label'); ?></label>
                        <input type="text" class="form-control form-item-control" id="form-item-copy-new-label">
                    </div>
                    <div class="form-group">
                        <label><?php echo $lang->get('select_destination_folder'); ?></label>
                        <select class="form-control form-item-control select2 no-root" style="width:100%;" id="form-item-copy-destination"></select>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" id="form-item-copy-perform"><?php echo $lang->get('perform'); ?></button>
                    <button type="submit" class="btn btn-default float-right but-back"><?php echo $lang->get('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>


    <!-- DELETE ITEM FORM -->
    <div class="row hidden form-item-delete form-item-action">
        <div class="col-12">

            <div class="card card-warning">
                <div class="card-header">
                    <h5><i class="fa-solid fa-trash mr-2"></i><?php echo $lang->get('delete_item'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info alert-dismissible">
                        <h5><i class="icon fa fa-info mr-2"></i><?php echo $lang->get('warning'); ?></h5>
                        <?php echo $lang->get('delete_item_message'); ?>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-warning" id="form-item-delete-perform"><?php echo $lang->get('perform'); ?></button>
                    <button type="submit" class="btn btn-default float-right but-back"><?php echo $lang->get('cancel'); ?></button>
                </div>
            </div>

        </div>
    </div>


    <!-- SHARE ITEM FORM -->
    <div class="row hidden form-item-share form-item-action">
        <div class="col-12">
            <form id="form-item-share" class="needs-validation" novalidate onsubmit="return false;">
                <div class="card card-primary">
                    <div class="card-header">
                        <h5><i class="fa-solid fa-share-alt mr-2"></i><?php echo $lang->get('share_item'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="callout callout-info">
                            <h5><i class="icon fa fa-info mr-2"></i><?php echo $lang->get('information'); ?></h5>
                            <p><?php echo $lang->get('share_item_message'); ?></p>
                        </div>
                        <div class="form-group">
                            <label for="form-item-share-email"><?php echo $lang->get('email_address'); ?></label>
                            <input type="email" class="form-control clear-me-val" id="form-item-share-email" placeholder="<?php echo $lang->get('enter_email'); ?>" required>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary" id="form-item-share-perform"><?php echo $lang->get('perform'); ?></button>
                        <button type="submit" class="btn btn-default float-right but-back"><?php echo $lang->get('cancel'); ?></button>
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
                    <h5><i class="fa-solid fa-bullhorn mr-2"></i><?php echo $lang->get('notification'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="callout callout-info">
                        <h5><i class="icon fa fa-info mr-2"></i><?php echo $lang->get('information'); ?></h5>
                        <p><?php echo $lang->get('notification_message'); ?></p>
                    </div>
                    <div class="form-group">
                        <input type="checkbox" class="form-check-input form-item-control flat-blue" id="form-item-notify-checkbox"><label for="form-item-notify-checkbox" class="ml-3"><?php echo $lang->get('notify_on_change'); ?></label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" id="form-item-notify-perform"><?php echo $lang->get('confirm'); ?></button>
                    <button type="submit" class="btn btn-default float-right but-back"><?php echo $lang->get('cancel'); ?></button>
                </div>
            </div>

        </div>
    </div>


    <!-- OTV ITEM FORM -->
    <div class="row hidden form-item-otv form-item-action">
        <div class="col-12">

            <div class="card card-primary">
                <div class="card-header">
                    <h5><i class="fa-brands fa-slideshare mr-2"></i><?php echo $lang->get('one_time_view'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="callout callout-info">
                        <h5><i class="icon fa fa-info mr-2"></i><?php echo $lang->get('information'); ?></h5>
                        <p><?php
                            echo str_replace(
        ['##otv_expiration_period##', '. '],
        ['<span class="text-bold text-primary">' . $SETTINGS['otv_expiration_period'] . '</span>', '<br>'],
        $lang->get('otv_message')
    );
                            ?></p>
                    </div>

                    <div class="form-group">
                        <label for="form-item-otv-link"><i class="fa-solid fa-link mr-2"></i><?php echo $lang->get('otv_link'); ?></label>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control clear-me-val" disabled id="form-item-otv-link" data-otv-id="0">
                            <div class="input-group-prepend">
                                <button type="button" class="btn btn-warning btn-copy-clipboard" id="form-item-otv-copy-button"><?php echo $lang->get('copy'); ?></button>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-4">
                            <label for="form-item-otv-days"><i class="fa-regular fa-calendar-days mr-2"></i><?php echo $lang->get('number_of_days'); ?> (<?php echo $lang->get('maximum').': '.$SETTINGS['otv_expiration_period'];?>)</label>
                            <div class="input-group mb-3">
                                <input type="number" class="form-control clear-me-val" id="form-item-otv-days" min="0" max="<?php echo $SETTINGS['otv_expiration_period'];?>" value="<?php echo $SETTINGS['otv_expiration_period'];?>">
                            </div>
                        </div>

                        <div class="form-group col-4">
                            <label for="form-item-otv-views"><i class="fa-regular fa-hashtag mr-2"></i><?php echo $lang->get('number_of_times'); ?></label>
                            <div class="input-group mb-3">
                                <input type="number" class="form-control clear-me-val" id="form-item-otv-views" value="1" min="0">
                            </div>
                        </div>

                        <div class="form-group col-2">
                            <label for="form-item-otv-subdomain" class="infotip" title="<?php
                                echo isset($SETTINGS['otv_subdomain']) === true && empty($SETTINGS['otv_subdomain']) === false ? '' : $lang->get('feature_disabled_by_administrator'); ?>">
                                <i class="fa-solid fa-globe mr-2"></i><?php echo $lang->get('shared_globaly'); ?>
                            </label>
                            <div class="input-group mb-3">
                                <div class="form-check mb-3 icheck-blue">
                                    <input type="checkbox" class="form-check-input form-item-control flat-blue infotip" id="form-item-otv-subdomain" <?php
                                        echo isset($SETTINGS['otv_subdomain']) === true && empty($SETTINGS['otv_subdomain']) === false ? ' enabled' : ' disabled'; ?> data-change-ongoing="">
                                </div>
                            </div>
                        </div>

                        <div class="form-group col-2">
                            <a class="btn btn-app mt-3" id="form-item-otv-update">
                                <i class="fa-solid fa-save"></i><br><?php echo ucfirst($lang->get('update')); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-default float-right but-back"><?php echo $lang->get('close'); ?></button>
                </div>
            </div>

        </div>
    </div>


    <!-- REQUEST ACCESS TO ITEM FORM -->
    <div class="row hidden form-item-request-access form-item-action">
        <div class="col-12">
            <form id="form-item-request-access" class="needs-validation" novalidate onsubmit="return false;">
                <div class="card card-primary">
                    <div class="card-header">
                        <h5><i class="fa-solid fa-handshake mr-2"></i><?php echo $lang->get('request_access'); ?></h5>
                    </div>
                    <div class="card-body">
                        <h3 id="form-item-request-access-label" class="mb-5"></h3>
                        <div class="callout callout-info">
                            <h5><i class="icon fa fa-info mr-2"></i><?php echo $lang->get('information'); ?></h5>
                            <p><?php echo $lang->get('request_access_message'); ?></p>
                        </div>
                        <textarea class="form-control mt-4" rows="3" placeholder="<?php echo $lang->get('request_access_reason'); ?>" id="form-item-request-access-reason"></textarea>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary" id="form-item-request-access-perform"><?php echo $lang->get('confirm'); ?></button>
                        <button type="submit" class="btn btn-default float-right but-back"><?php echo $lang->get('cancel'); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <!-- ADD FOLDER FORM -->
    <div class="row hidden form-folder-add form-folder-action">
        <div class="col-12">
            <form id="form-folder-add" class="needs-validation" novalidate onsubmit="return false;" data-action="">
                <div class="card card-primary">
                    <div class="card-header">
                        <h5><i class="fa-solid fa-plus mr-2"></i><?php echo $lang->get('add_folder'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label><?php echo $lang->get('label'); ?></label>
                            <input type="text" class="form-control form-folder-control" id="form-folder-add-label" required>
                        </div>
                        <div class="form-group">
                            <label><?php echo $lang->get('select_folder_parent'); ?></label>
                            <select class="form-control form-folder-control select2" style="width:100%;" id="form-folder-add-parent" required></select>
                        </div>
                        <div class="form-group">
                            <label><?php echo $lang->get('complex_asked'); ?></label>
                            <select class="form-control form-folder-control select2" style="width:100%;" id="form-folder-add-complexicity" required>
                                <?php
                                foreach (TP_PW_COMPLEXITY as $key => $value) {
                                    echo '<option value="' . $key . '">' . $value[1] . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <!--
                        <div class="form-group">
                            <label><?php echo $lang->get('folder_creation_rights_strategy'); ?></label>
                            <select class="form-control form-folder-control select2" style="width:100%;" id="form-folder-add-rights" required>
                                <option value="folder_creation_rights_as_user_groups"><?php echo $lang->get('folder_creation_rights_as_user_groups').' ('.$lang->get('by_default').')'; ?></option>
                                <option value="folder_creation_rights_as_folder_groups"><?php echo $lang->get('folder_creation_rights_as_folder_groups'); ?></option>
                            </select>
                        </div>
                            -->
                        <div class="form-group">
                            <label><?php echo $lang->get('icon'); ?></label>
                            <input type="text" class="form-control form-folder-control" id="form-folder-add-icon">
                            <small class='form-text text-muted'>
                                <?php echo $lang->get('fontawesome_icon_tip'); ?><a href="<?php echo FONTAWESOME_URL;?>" target="_blank"><i class="fa-solid fa-external-link-alt ml-1"></i></a>
                            </small>
                        </div>
                        <div class="form-group">
                            <label><?php echo $lang->get('icon_on_selection'); ?></label>
                            <input type="text" class="form-control form-folder-control" id="form-folder-add-icon-selected">
                            <small class='form-text text-muted'>
                                <?php echo $lang->get('fontawesome_icon_tip'); ?><a href="<?php echo FONTAWESOME_URL;?>" target="_blank"><i class="fa-solid fa-external-link-alt ml-1"></i></a>
                            </small>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary" id="form-folder-add-perform"><?php echo $lang->get('perform'); ?></button>
                        <button type="submit" class="btn btn-default float-right but-back"><?php echo $lang->get('cancel'); ?></button>
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
                    <h5><i class="fa-solid fa-trash mr-2"></i><?php echo $lang->get('delete_folder'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label><?php echo $lang->get('select_folder_to_delete'); ?></label>
                        <select class="form-control form-folder-control select2" style="width:100%;" id="form-folder-delete-selection" required></select>
                    </div>
                    <div class="form-check mb-3 alert alert-warning icheck-red">
                        <input type="checkbox" class="form-check-input form-item-control flat-blue" id="form-folder-confirm-delete" required>
                        <label class="form-check-label ml-3" for="form-folder-confirm-delete"><i class="fa-solid fa-info fa-lg mr-2"></i><?php echo $lang->get('folder_delete_confirm'); ?></label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" id="form-folder-delete-perform"><?php echo $lang->get('perform'); ?></button>
                    <button type="submit" class="btn btn-default float-right but-back"><?php echo $lang->get('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>


    <!-- COPY FOLDER FORM -->
    <div class="row hidden form-folder-copy form-folder-action">
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h5><i class="fa-solid fa-copy mr-2"></i><?php echo $lang->get('copy_folder'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label><?php echo $lang->get('label'); ?></label>
                        <input type="text" class="form-control form-folder-control" id="form-folder-copy-label" required></select>
                    </div>
                    <div class="form-group">
                        <label><?php echo $lang->get('select_source_folder'); ?></label>
                        <select class="form-control form-folder-control select2 no-root" style="width:100%;" id="form-folder-copy-source" required></select>
                    </div>
                    <div class="form-group">
                        <label><?php echo $lang->get('select_destination_folder'); ?></label>
                        <select class="form-control form-folder-control select2" style="width:100%;" id="form-folder-copy-destination" required>
                        </select>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" id="form-folder-copy-perform"><?php echo $lang->get('perform'); ?></button>
                    <button type="submit" class="btn btn-default float-right but-back"><?php echo $lang->get('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>


    <!-- EXPORT FORM -->
    <div class="row hidden form-item-export form-item-action">
        <div class="col-12">

        </div>
    </div>

    <!-- OFFLINE FORM -->
    <div class="row hidden form-item-offline form-item-action">
        <div class="col-12">

        </div>
    </div>


    <div class="hidden" id="add_history_element">
            <div class="alert alert-info">
                <h5><i class="icon fa fa-info mr-3"></i><?php echo $lang->get('information'); ?></h5>
                <?php echo $lang->get('info_about_history_insertion'); ?>
            </div>
            <div class="row">
                <div class="col-12 input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><?php echo $lang->get('label'); ?></span>
                    </div>
                    <input id="add-history-label" type="text" class="form-control form-item-control history" data-field-name="history-label">
                </div>
            </div>
            <div class="row">
                <div class="col-6 input-group date inline">
                    <div class="input-group-prepend">
                        <span class="input-group-text">
                            <i class="fa-solid fa-calendar"></i>
                        </span>
                    </div>
                    <input type="text" class="form-control float-right form-item-control datepicker history" id="add-history-date" data-field-name="history-date">
                </div>
                <div class="col-6 input-group time inline">
                    <div class="input-group-prepend">
                        <span class="input-group-text">
                            <i class="fa-solid fa-clock"></i>
                        </span>
                    </div>
                    <input type="time" step="2" class="form-control float-right form-item-control timepicker history" id="add-history-time" data-field-name="history-time">
                </div>
            </div>
            <!--<div class="row col-12 mt-3">
                <button type="button" class="btn btn-default mr-2" id="form-item-history-insert" data-action=""><i class="fa-solid fa-broom mr-2"></i><?php echo $lang->get('history_insert_entry'); ?></button>
                <button type="button" class="btn btn-default" id="form-item-history-clear" data-action=""><i class="fa-solid fa-broom mr-2"></i><?php echo $lang->get('clear_form'); ?></button>
            </div>-->
    </div>


    <div class="row h-25" id="folders-tree-card">
        <div id="folder-tree-container" class="col-md-5 column-left">
            <div class="card card-info card-outline">
                <div class="card-header">
                    <div class="row justify-content-end">
                        <div class="col-6">
                            <h3 class="card-title"><i class="fa-regular fa-folder-open mr-2">
                                </i><span class=""><?php echo $lang->get('folders'); ?></span>
                        </div>
                        <div class="col-6">
                            <div class="btn-group float-right">
                                <button type="button" class="btn btn-info btn-sm dropdown-toggle" data-toggle="dropdown">
                                    <i class="fa-solid fa-bars"></i>
                                    <span class="caret"></span>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item tp-action" href="#" data-folder-action="refresh"><i class="fa-solid fa-sync-alt fa-fw mr-2"></i><?php echo $lang->get('refresh'); ?></a>
                                    <a class="dropdown-item tp-action" href="#" data-folder-action="expand"><i class="fa-solid fa-expand fa-fw mr-2"></i><?php echo $lang->get('expand'); ?></a>
                                    <a class="dropdown-item tp-action" href="#" data-folder-action="collapse"><i class="fa-solid fa-compress fa-fw mr-2"></i><?php echo $lang->get('collapse'); ?></a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item tp-action" href="#" data-folder-action="add"><i class="fa-regular fa-plus-square fa-fw mr-2"></i><?php echo $lang->get('add'); ?></a>
                                    <a class="dropdown-item tp-action" href="#" data-folder-action="edit"><i class="fa-regular fa-edit fa-fw mr-2"></i><?php echo $lang->get('edit'); ?></a>
                                    <a class="dropdown-item tp-action" href="#" data-folder-action="copy"><i class="fa-regular fa-copy fa-fw mr-2"></i><?php echo $lang->get('copy'); ?></a>
                                    <a class="dropdown-item tp-action" href="#" data-folder-action="delete"><i class="fa-regular fa-trash-alt fa-fw mr-2"></i><?php echo $lang->get('delete'); ?></a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item tp-action" href="#" data-folder-action="">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control" placeholder="<?php echo $lang->get('find'); ?>" id="jstree_search">
                                            <div class="input-group-append">
                                                <div class="btn btn-primary">
                                                    <i class="fa-solid fa-search"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <!-- FOLDERS PLACE -->
                    <div id="jstree" style="overflow:auto;"></div>
                </div>
            </div><!-- /.card -->
        </div>
        <!-- /.col-md-6 -->
        <div id="items-list-container" class="col-md-7">
            <div class="card card-primary card-outline" id="items-list-card">
                <div class="card-header">
                    <div class="card-title w-100">
                        <div class="row">
                            <div class="col">
                                <div class="btn-group" id="btn-new-item">
                                    <button type="button" class="btn btn-primary btn-sm tp-action" data-item-action="new">
                                        <i class="fa-solid fa-plus mr-2"></i><?php echo $lang->get('new_item'); ?>
                                    </button>
                                </div>
                            </div>
                            <div class="col text-right">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" placeholder="<?php echo $lang->get('find'); ?>" id="find_items">
                                    <div class="input-group-append">
                                        <div class="btn btn-primary" id="find_items_button">
                                            <i class="fa-solid fa-search"></i>
                                        </div>
                                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <span class="sr-only">Toggle Dropdown</span>
                                        </button>
                                        <div class="dropdown-menu">
                                            <div class="dropdown-item">
                                                <input type="checkbox" class=" mr-2" id="limited-search">
                                                <label class="form-check-label" for="limited-search"><?php echo $lang->get('limited_search'); ?></label>
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
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-truncated table-hover table-striped" id="table_teampass_items_list" style="width:100%;">
                            <tbody id="teampass_items_list"></tbody>
                        </table>
                        <!-- /.table -->
                        <div class="form-group row justify-content-md-center m-0" id="info_teampass_items_list">
                            <div class="alert alert-info text-center col col-10" role="alert">
                                <i class="fa-solid fa-info-circle mr-2"></i><?php echo $lang->get('please_select_a_folder'); ?></b>
                            </div>
                        </div>
                        <!-- /#info_teampass_items_list -->
                    </div>

                </div>
                <!-- /.card-body -->
                <div class="card-footer p-0">
                </div>
            </div>
            <!-- /. box -->
        </div>

        
        <div id="items-details-container" class="col-md-5 overflow-auto hidden">
            <!-- ITEM DETAILS -->
            <div class="row item-details-card item-details-card-menu">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <span class="mr-3 align-middle">
                                <button type="button" class="btn btn-secondary but-back-to-list">
                                    <i class="fa-solid fa-arrow-left"></i>
                                </button>
                            </span>
                            <h3 class="d-inline align-middle" id="card-item-label"></h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-secondary btn-sm but-back-to-list mt-2">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                                    <span class="navbar-toggler-icon"></span>
                                </button>
                                <div class="collapse navbar-collapse" id="navbarNav">
                                    <ul class="navbar-nav">
                                        <li class="nav-item" id="item-form-new-button">
                                            <a class="text-navy tp-action ml-3" href="#" data-item-action="new"><i class="fa-regular fa-plus-square mr-1"></i><small><?php echo $lang->get('new'); ?></small></a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="text-navy tp-action ml-3" href="#" data-item-action="edit"><i class="fa-regular fa-edit mr-1"></i><small><?php echo $lang->get('edit'); ?></small></a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="text-navy tp-action ml-3" href="#" data-item-action="delete"><i class="fa-regular fa-trash-alt mr-1"></i><small><?php echo $lang->get('delete'); ?></small></a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="text-navy tp-action ml-3" href="#" data-item-action="copy"><i class="fa-regular fa-copy mr-1"></i><small><?php echo $lang->get('copy'); ?></small></a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="text-navy tp-action ml-3" href="#" data-item-action="link" id="get_item_link"><i class="fa-solid fa-link mr-1"></i><small><?php echo $lang->get('link'); ?></small></a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="text-navy tp-action ml-3" href="#" data-item-action="share"><i class="fa-regular fa-share-square mr-1"></i><small><?php echo $lang->get('share'); ?></small></a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="text-navy tp-action ml-3" href="#" data-item-action="notify"><i class="fa-regular fa-bell mr-1"></i><small><?php echo $lang->get('notify'); ?></small></a>
                                        </li>
                                        <?php
                                        if (
                                            isset($SETTINGS['enable_server_password_change']) === true
                                            && (int) $SETTINGS['enable_server_password_change'] === 1
                                        ) {
                                            ?>
                                            <li class="nav-item">
                                                <a class="text-navy tp-action ml-3" href="#" data-item-action="server"><i class="fa-solid fa-server mr-1"></i><small><?php echo $lang->get('server'); ?></small></a>
                                            </li>
                                        <?php
                                        }
                                        if (
                                            isset($SETTINGS['otv_is_enabled']) === true
                                            && (int) $SETTINGS['otv_is_enabled'] === 1
                                        ) {
                                            ?>
                                            <li class="nav-item">
                                                <a class="text-navy tp-action ml-3" href="#" data-item-action="otv"><i class="fab fa-slideshare mr-1"></i><small><?php echo $lang->get('one_time_view'); ?></small></a>
                                            </li>
                                        <?php
                                        }
                                        ?>
                                        <li class="nav-item">
                                            <a class="text-navy tp-action ml-3" href="#" data-item-action="reload"><i class="fa-solid fa-rotate mr-1"></i><small><?php echo $lang->get('refresh'); ?></small></a>
                                        </li>
                                    </ul>
                                </div>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <div id="card-item-preview" class="hidden"></div>

            <div class="row item-details-card">
                <div class="col-md-7">
                    <div class="card card-primary card-outline">
                        <div class="card-body" id="list-group-item-main">
                            <ul class="list-group list-group-unbordered mb-3">
                                <li class="list-group-item">
                                    <b><?php echo $lang->get('pw'); ?></b>
                                    <button type="button" class="float-right btn btn-secondary btn-sm btn-copy-clipboard infotip" id="card-item-pwd-button" title="<?php echo $lang->get('copy'); ?>">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                    <button type="button" class="float-right btn btn-secondary btn-sm mr-1 hidden infotip" id="card-item-password-history-button" title="<?php echo $lang->get('history'); ?>">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                    </button>
                                    <button type="button" class="float-right btn btn-secondary btn-sm mr-1 infotip" id="card-item-pwd-show-button" title="<?php echo $lang->get('show'); ?>">
                                        <i class="fa-regular fa-eye pwd-show-spinner"></i>
                                    </button>
                                    <span id="card-item-pwd" class="float-right unhide_masked_data pointer mr-2"></span>
                                    <input id="hidden-item-pwd" type="hidden">
                                </li>
                                <li class="list-group-item">
                                    <b><?php echo $lang->get('index_login'); ?></b>
                                    <button type="button" class="float-right btn btn-secondary btn-sm ml-1 btn-copy-clipboard-clear infotip" data-clipboard-target="#card-item-login" id="card-item-login-btn" title="<?php echo $lang->get('copy'); ?>">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                    <span id="card-item-login" class="float-right"></span>
                                </li>
                                <li class="list-group-item">
                                    <b><?php echo $lang->get('email'); ?></b>
                                    <button type="button" class="float-right btn btn-secondary btn-sm ml-1 btn-copy-clipboard-clear" data-clipboard-target="#card-item-email" id="card-item-email-btn">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                    <span id="card-item-email" class="float-right ml-1"></span>
                                </li>
                                <li class="list-group-item">
                                    <b><?php echo $lang->get('url'); ?></b>
                                    <button type="button" class="float-right btn btn-secondary btn-sm ml-1 btn-copy-clipboard-clear" data-clipboard-target="#card-item-url-text" id="card-item-url-text-btn">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                    <a id="card-item-url" class="float-right ml-1 btn btn-secondary btn-sm" href="#" target="_blank">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                    <span id="card-item-url-text" class="float-right ml-1"></span>
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
                                    <b><?php echo $lang->get('restricted_to'); ?></b>
                                    <a id="card-item-restrictedto" class="float-right ml-1"></a>
                                </li>
                                <li class="list-group-item">
                                    <b><?php echo $lang->get('tags'); ?></b>
                                    <a id="card-item-tags" class="float-right ml-1"></a>
                                </li>
                                <li class="list-group-item">
                                    <b><?php echo $lang->get('otp_code'); ?></b>
                                    <a id="card-item-opt_code" class="float-right ml-1"></a>
                                    <span id="card-item-opt_code_error" class="float-right"></span>
                                </li>
                                <li class="list-group-item" id="card-item-misc">
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row item-details-card">
                <div class="col-12">
                    <div class="callout callout-info visible" id="card-item-description">No description</div>
                </div>
            </div>


            <?php
            if (
                isset($SETTINGS['item_extra_fields']) === true
                && (int) $SETTINGS['item_extra_fields'] === 1
            ) {
                ?>
                <div class="row hidden item-details-card" id="item-details-card-categories">
                    <div class="col-12">
                        <div class="card card-default">
                            <div class="card-header bg-gray-dark">
                                <h3 class="card-title pointer" data-toggle="collapse" data-target="#card-item-fields">
                                    <i class="fa-solid fa-random mr-2"></i><?php echo $lang->get('categories'); ?>
                                </h3>
                                <!-- /.card-tools -->
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body collapse show" id="card-item-fields">
                                <?php
                                    foreach ($session_item_fields as $elem) {
                                        echo '
                                <div class="callout callout-info card-item-category hidden" id="card-item-category-' . $elem['id'] . '">
                                    <h5>' . $elem['title'] . '</h5>
                                    <p>
                                        <ul class="list-group list-group-unbordered mb-3">';
                                        foreach ($elem['fields'] as $field) {
                                            echo '
                                            <li class="list-group-item card-item-field hidden" id="card-item-field-' . $field['id'] . '">
                                                <span id="card-item-field-title-' . $field['id'] . '"><b>' . $field['title'] . '</b></span>
                                                <button type="button" class="float-right btn btn-secondary btn-sm ml-1 btn-copy-clipboard-clear"  data-clipboard-target="#card-item-field-value-' . $field['id'] . '">
                                                    <i class="fa-solid fa-copy"></i>
                                                </button>
                                                <span class="card-item-field-value float-right ml-1" id="card-item-field-value-' . $field['id'] . '"></span>
                                            </li>';
                                        }
                                        echo '
                                        </ul>
                                    </p>
                                </div>';
                                    } ?>
                                <div class="hidden no-item-fields"><?php echo $lang->get('no_custom_fields'); ?></div>
                            </div>
                            <!-- /.card-body -->
                        </div>
                    </div>
                </div>
            <?php
            }
            ?>

            <div class="row item-details-card item-card-attachments">
                <div class="col-12">
                    <div class="card card-default collapsed">
                        <div class="card-header bg-gray-dark">
                            <h3 class="card-title pointer" id="add-item-attachment" data-toggle="collapse" data-target="#card-item-attachments">
                                <i class="fa-solid fa-paperclip mr-2"></i><?php echo $lang->get('attachments'); ?>
                                <span class="badge badge-secondary ml-2" id="card-item-attachments-badge"></span>
                            </h3>
                            <i class="fa-solid fa-square-plus fa-2x float-right text-info pointer infotip hidden add-button" title="<?php echo $lang->get('add_new_file'); ?>" data-add-type="attachment"></i>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body collapse clear-me-html" id="card-item-attachments">
                        </div>
                        <!-- /.card-body -->
                        <div class="overlay">
                            <i class="fa-solid fa-refresh fa-spin"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row item-details-card">
                <div class="col-12">
                    <div class="card card-default collapsed">
                        <div class="card-header bg-gray-dark">
                            <h3 class="card-title pointer" id="add-item-history" data-toggle="collapse" data-target="#card-item-history">
                                <i class="fa-solid fa-history mr-2"></i><?php echo $lang->get('history'); ?>
                                <span class="badge badge-secondary ml-2" id="card-item-history-badge"></span>
                            </h3>
                            <i class="fa-solid fa-square-plus fa-2x float-right text-info pointer infotip hidden add-button" title="<?php echo $lang->get('history_insert_entry'); ?>" data-add-type="history"></i>
                            <!-- /.card-tools -->
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body collapse" id="card-item-history">
                        </div>
                        <!-- /.card-body -->
                        <div class="overlay">
                            <i class="fa-solid fa-refresh fa-spin"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SERVER UPDATE --><?php
                                    if (DEBUG === true) {
                                        ?>
                <div class="row form-item-server form-item-action">
                    <div class="col-12">
                        <div class="card card-primary">
                            <div class="card-header bg-navy">
                                <h5>
                                    <i class="fa-solid fa-server mr-2"></i><?php echo $lang->get('update_server_password'); ?>
                                </h5>
                                <!-- /.card-tools -->
                            </div>
                            <!-- /.card-header -->
                            <div class="card-body">
                                <ul class="nav nav-tabs" id="server-tab">
                                    <li class="nav-item">
                                        <a class="nav-link active" href="#tab-one-shot" data-action="ssh-one-shot" data-toggle="tab"><?php echo $lang->get('ssh_one_shot_change'); ?></a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="#tab-scheduled" data-action="ssh-scheduled" data-toggle="tab"><?php echo $lang->get('ssh_scheduled_change'); ?></a>
                                    </li>
                                </ul>
                                <div class="tab-content" id="myTabContent">
                                    <div class="tab-pane fade show active tab-pane" id="tab-one-shot">
                                        <div class="alert alert-info mt-3 form-text text-muted">
                                            <?php echo $lang->get('auto_update_server_password_info'); ?>
                                        </div>
                                        <div class="input-group mb-3 mt-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><?php echo $lang->get('ssh_user'); ?></span>
                                            </div>
                                            <input id="form-item-server-login" type="text" class="form-control form-item-control form-item-server" data-field-name="login" data-change-ongoing="">
                                        </div>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><?php echo $lang->get('ssh_pwd'); ?></span>
                                            </div>
                                            <input id="form-item-server-old-password" type="password" class="form-control form-item-control form-item-server" data-field-name="old-password" data-change-ongoing="">
                                        </div>
                                        <div class="input-group mb-3">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><?php echo $lang->get('index_new_pw'); ?></span>
                                            </div>
                                            <input id="form-item-server-password" type="password" class="form-control form-item-control form-item-server" data-field-name="password" data-change-ongoing="">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary btn-no-click infotip password-generate" title="<?php echo $lang->get('pw_generate'); ?>" data-id="form-item-server-password"><i class="fa-solid fa-random"></i></button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade tab-pane" id="tab-scheduled">
                                        <div class="alert alert-info mt-3 form-text text-muted">
                                            <?php echo $lang->get('ssh_password_frequency_change_info'); ?>
                                        </div>
                                        <div class="form-group">
                                            <label><?php echo $lang->get('ssh_password_frequency_change'); ?></label>
                                            <select class="form-control form-item-control select2" style="width:100%;" id="form-item-server-cron-frequency">
                                                <option value="0">0</option>
                                                <option value="1">1</option>
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="callout callout-alert mt-3 hidden" id="form-item-server-status">

                                    </div>
                                </div>
                            </div>
                            <!-- /.card-body -->
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary" id="form-item-server-perform"><?php echo $lang->get('perform'); ?></button>
                                <button type="submit" class="btn btn-default float-right but-back"><?php echo $lang->get('cancel'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
                }
            ?>

            <!-- Bottom bar -->
            <div class="row item-details-card">
                <div class="col-12">
                    <div class="card">
                            <div class="card-footer">
                                <button type="button" class="btn btn-secondary but-navigate-item but-prev-item hidden" data-prev-item-id=""></button>
                                <button type="button" class="btn btn-secondary but-navigate-item but-next-item hidden" data-next-item-id=""></button>
                                <button type="button" class="btn btn-info float-right but-back-to-list"><?php echo $lang->get('close'); ?></button>
                            </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /#items-details-container -->

    </div>
    <!-- /.col -->

</section>
<!-- /.content -->

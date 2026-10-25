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
 * @copyright 2009-2026 Teampass.net
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

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');
$secureSendPublicEntity = brandingPublicEntityName((string) ($SETTINGS['public_entity_name'] ?? ''));
$secureSendShowSenderName = (int) ($SETTINGS['secure_send_show_sender_name'] ?? 0) === 1;
$decodeSecureSendProfileValue = static fn (mixed $value): string => trim(
    html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8')
);
$secureSendProfileName = trim(
    $decodeSecureSendProfileValue($session->get('user-name')) . ' '
    . $decodeSecureSendProfileValue($session->get('user-lastname'))
);

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

// Prepare SESSION variables
$session_user_admin = $session->get('user-admin');

if ((int) $session_user_admin === 1) {
    $session->set('user-accessible_folders', $session->get('user-personal_visible_folders'));
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

            <form id="form-item" class="needs-validation" novalidate onsubmit="return false;">
            <div class="card">
                <!-- Action bar: stays below the fixed navbar while the form scrolls -->
                <div class="card-header tp-item-form-bar">
                    <button type="button" class="btn btn-secondary but-back" id="but_back_top_left">
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>
                    <!-- ICON: shown in the title, picked from the Font Awesome Free icons on click -->
                    <div class="dropdown tp-item-form-icon">
                        <button type="button" class="tp-item-form-icon-button" id="form-item-icon-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="<?php echo $lang->get('icon'); ?>" aria-label="<?php echo $lang->get('icon'); ?>">
                            <span id="form-item-icon-show" class="clear-me-html"></span><i class="fa-solid fa-icons tp-item-form-icon-default" aria-hidden="true"></i>
                        </button>
                        <input id="form-item-icon" type="hidden" class="form-item-control purify" data-field-name="icon">
                        <!-- The icon picker (assets/js/icon-picker.js) moves in when the menu opens -->
                        <div class="dropdown-menu tp-item-form-icon-menu" aria-labelledby="form-item-icon-toggle"></div>
                    </div>
                    <!-- LABEL: edited in place as the form title -->
                    <div class="tp-item-form-title">
                        <i class="fa-solid fa-pen tp-item-form-title-icon" aria-hidden="true"></i>
                        <label for="form-item-label" class="sr-only"><?php echo $lang->get('label'); ?></label>
                        <input id="form-item-label" type="text" class="form-control form-item-control purify" placeholder="<?php echo $lang->get('label'); ?>" data-change-ongoing="" data-field-name="label">
                    </div>
                    <span id="form-item-unsaved" class="tp-item-form-unsaved hidden" role="status">
                        <span class="tp-item-form-unsaved-dot" aria-hidden="true"></span><?php echo $lang->get('item_form_unsaved_changes'); ?>
                    </span>
                    <div class="tp-item-form-buttons" id="form-item-buttons">
                        <button type="button" class="btn btn-default but-back item-edit"><?php echo $lang->get('cancel'); ?></button>
                        <button type="button" class="btn btn-info" id="form-item-button-save" data-action="" title="Ctrl+S" aria-keyshortcuts="Control+S"><i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i><?php echo $lang->get('save'); ?></button>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm but-back" id="but_back_top_right">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
                <div class="card-header d-flex flex-wrap align-items-center tp-item-form-subheader">
                    <!-- Section shortcuts: every section of the item sits on this page -->
                    <nav class="tp-item-form-jumps" id="form-item-jumps">
                        <a class="tp-item-form-jump" href="#form-item-section-main"><i class="fa-solid fa-home fa-fw mr-1" aria-hidden="true"></i><?php echo $lang->get('main'); ?></a>
                        <?php
                        echo isset($SETTINGS['item_extra_fields']) === true && (int) $SETTINGS['item_extra_fields'] === 1 ? '
                        <a class="tp-item-form-jump hidden" href="#form-item-section-fields" id="form-item-jump-fields"><i class="fa-solid fa-cubes fa-fw mr-1" aria-hidden="true"></i>' . $lang->get('fields') . '</a>' : '';
                        ?>
                        <a class="tp-item-form-jump" href="#form-item-section-attachments"><i class="fa-solid fa-paperclip fa-fw mr-1" aria-hidden="true"></i><?php echo $lang->get('attachments'); ?><span class="badge badge-pill badge-info ml-1 form-item-attachments-count hidden"></span></a>
                        <a class="tp-item-form-jump" href="#form-item-section-details"><i class="fa-solid fa-list fa-fw mr-1" aria-hidden="true"></i><?php echo $lang->get('details'); ?></a>
                    </nav>
                    <div class="tp-item-form-rules small">
                        <span><i class="fa-solid fa-users mr-1" aria-hidden="true"></i><?php echo $lang->get('visible_by'); ?>
                            <span id="card-item-visibility" class="text-info font-weight-bold ml-1">
                                <i class="fa-solid fa-ellipsis mr-2 fa-fade"></i>
                            </span>
                        </span>
                        <span><i class="fa-solid fa-key mr-1" aria-hidden="true"></i><?php echo $lang->get('complex_asked'); ?>
                            <span id="card-item-minimum-complexity" class="text-info font-weight-bold ml-1">
                                <i class="fa-solid fa-ellipsis mr-2 fa-fade"></i>
                            </span>
                        </span>
                    </div>
                </div><!-- /.card-header -->
                <div class="card-body">
                    <div id="form-item-lapr-notice" class="alert hidden text-left"></div>
                        <div class="tp-item-form-sections">
                            <!-- MAIN -->
                            <div class="tp-item-form-section" id="form-item-section-main">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <!-- LOGIN -->
                                        <div class="form-group">
                                            <label for="form-item-login" class="mb-1"><i class="fa-solid fa-user fa-fw mr-1 text-muted" aria-hidden="true"></i><?php echo $lang->get('login'); ?></label>
                                            <input id="form-item-login" type="text" class="form-control form-item-control purify" data-field-name="login" data-change-ongoing="">
                                        </div>
                                        <!-- PASSWORD -->
                                        <div class="form-group mb-2">
                                            <label for="form-item-password" class="mb-1"><i class="fa-solid fa-key fa-fw mr-1 text-muted" aria-hidden="true"></i><?php echo $lang->get('password'); ?></label>
                                            <div class="input-group">
                                                <!-- No spellcheck nor autocorrect: the field turns into a text input when revealed -->
                                                <input id="form-item-password" type="password" class="form-control form-item-control" placeholder="<?php echo $lang->get('password'); ?>" data-field-name="pwd" data-change-ongoing="" spellcheck="false" autocapitalize="off" autocorrect="off">
                                                <div class="input-group-append">
                                                    <span class="input-group-text hidden" id="form-item-password-loader"><i class="fa-solid fa-circle-notch fa-spin"></i></span>
                                                    <!-- Show/hide password -->
                                                    <button type="button" class="btn btn-secondary btn-no-click infotip" id="item-button-password-show" title="<?php echo $lang->get('mask_pw'); ?>" aria-label="<?php echo $lang->get('mask_pw'); ?>" aria-pressed="false"><i class="fa-regular fa-eye"></i></button>
                                                    <!-- Single generate button: dispatches to the mode chosen in the generator options -->
                                                    <button type="button" class="btn btn-primary" id="item-button-generate" data-id="form-item-password"><i class="fa-solid fa-rotate mr-1"></i><?php echo $lang->get('pw_generate'); ?></button>
                                                    <button type="button" class="btn btn-primary infotip" id="form-item-generator-options-toggle" data-toggle="collapse" data-target="#form-item-generator-panel" aria-controls="form-item-generator-panel" aria-expanded="false" title="<?php echo $lang->get('options'); ?>" aria-label="<?php echo $lang->get('options'); ?>"><i class="fa-solid fa-sliders"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="form-item-password-strength"></div>
                                        <div id="form-item-password-coach" class="small text-muted mt-1 tp-item-form-coach"></div>
                                        <input type="hidden" id="form-item-password-complex" value="0">
                                        <!-- Generator options: mode selector (drives which options show), folded by default -->
                                        <div class="collapse" id="form-item-generator-panel">
                                            <div class="tp-item-form-panel">
                                                <div class="btn-group btn-group-sm btn-group-toggle mb-2" id="form-item-generator-mode">
                                                    <label class="btn btn-primary active" data-mode="random">
                                                        <input type="radio" name="generator-mode" autocomplete="off" checked><i class="fa-solid fa-random mr-1"></i><?php echo $lang->get('generator_mode_random'); ?>
                                                    </label>
                                                    <label class="btn btn-outline-secondary" data-mode="passphrase">
                                                        <input type="radio" name="generator-mode" autocomplete="off"><i class="fa-solid fa-comment-dots mr-1"></i><?php echo $lang->get('generator_mode_passphrase'); ?>
                                                    </label>
                                                </div>
                                                <div id="form-item-password-options">
                                                    <div class="btn-toolbar" role="toolbar" aria-label="Toolbar with button groups">
                                                        <div class="btn-group btn-group-sm btn-group-toggle mr-2 mb-1" data-toggle="buttons">
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

                                                        <div class="input-group input-group-sm mb-1">
                                                            <div class="input-group-prepend">
                                                                <div class="input-group-text"><?php echo $lang->get('size'); ?></div>
                                                            </div>
                                                            <?php
                                                            $defaultPasswordLength = max(4, min((int) ($SETTINGS['pwd_default_length'] ?? 14), (int) $SETTINGS['pwd_maximum_length']));
                                                            ?>
                                                            <select class="form-control form-control-sm w-10" id="pwd-definition-size">
                                                                <?php
                                                                for ($i = 4; $i <= $SETTINGS['pwd_maximum_length']; ++$i) {
                                                                    echo '
                                                                <option' . ($i === $defaultPasswordLength ? ' selected' : '') . '>' . $i . '</option>';
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="hidden" id="form-item-passphrase-options">
                                                    <div class="btn-toolbar" role="toolbar" aria-label="Passphrase options">
                                                        <div class="input-group input-group-sm mr-2 mb-1">
                                                            <div class="input-group-prepend">
                                                                <div class="input-group-text"><?php echo $lang->get('passphrase_words'); ?></div>
                                                            </div>
                                                            <select class="form-control form-control-sm" id="passphrase-word-count">
                                                                <option value="3">3</option>
                                                                <option value="4" selected>4</option>
                                                                <option value="5">5</option>
                                                                <option value="6">6</option>
                                                                <option value="7">7</option>
                                                                <option value="8">8</option>
                                                            </select>
                                                        </div>
                                                        <div class="input-group input-group-sm mr-2 mb-1">
                                                            <div class="input-group-prepend">
                                                                <div class="input-group-text"><?php echo $lang->get('passphrase_separator'); ?></div>
                                                            </div>
                                                            <select class="form-control form-control-sm" id="passphrase-separator">
                                                                <option value="-" selected>-</option>
                                                                <option value=" "><?php echo $lang->get('passphrase_separator_space'); ?></option>
                                                                <option value="_">_</option>
                                                                <option value=".">.</option>
                                                                <option value=""><?php echo $lang->get('passphrase_separator_none'); ?></option>
                                                            </select>
                                                        </div>
                                                        <div class="btn-group btn-group-sm btn-group-toggle mb-1" data-toggle="buttons">
                                                            <label class="btn btn-secondary btn-sm active">
                                                                <input type="checkbox" id="passphrase-capitalize" checked><?php echo $lang->get('passphrase_capitalize'); ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- RENEWAL: one line, the period input is enabled with the checkbox -->
                                        <div class="form-group mt-2" id="form-item-renewal-settings">
                                            <div class="d-flex flex-wrap align-items-center">
                                                <div class="custom-control custom-checkbox mr-2">
                                                    <input type="checkbox" class="custom-control-input form-item-control" id="form-item-renewal-enabled">
                                                    <label class="custom-control-label font-weight-normal" for="form-item-renewal-enabled"><?php echo $lang->get('item_renewal_enable'); ?></label>
                                                </div>
                                                <div class="input-group input-group-sm tp-item-form-renewal-period">
                                                    <input type="number" class="form-control form-item-control" id="form-item-renewal-period" min="1" max="36500" step="1" value="90" disabled aria-label="<?php echo $lang->get('item_renewal_period'); ?>">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text"><?php echo $lang->get('days'); ?></span>
                                                    </div>
                                                </div>
                                                <i class="fa-solid fa-circle-question text-muted ml-2 infotip" title="<?php echo $lang->get('item_renewal_help'); ?>" aria-label="<?php echo $lang->get('item_renewal_help'); ?>" role="img"></i>
                                            </div>
                                            <!-- Deadline that applies to this item (folder or own policy), filled by assets/js/renewal-preview.js -->
                                            <div id="form-item-renewal-notice" class="tp-renewal-compact small hidden mt-1" role="status" aria-live="polite"></div>
                                        </div>
                                        <!-- OTP FOR ITEM: secret first, TOTP profile folded behind its summary -->
                                        <div class="form-group" role="group" aria-labelledby="form-item-otp-title">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-1">
                                                <label for="form-item-otpSecret" class="mb-0" id="form-item-otp-title"><i class="fa-solid fa-qrcode fa-fw mr-1 text-muted" aria-hidden="true"></i><?php echo $lang->get('otp_secret_or_uri'); ?></label>
                                                <div class="form-check icheck-blue mb-0">
                                                    <input type="checkbox" class="form-check-input form-item-control flat-blue ml-0" id="form-item-otp" data-change-ongoing="">
                                                    <label class="form-check-label ml-3" for="form-item-otp"><?php echo $lang->get('enabled_otp_for_item'); ?></label>
                                                </div>
                                            </div>
                                            <div class="input-group">
                                                <input type="text" class="form-control form-item-control purify" data-field-name="otpSecret" id="form-item-otpSecret" data-change-ongoing="">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-secondary" id="form-item-otp-settings-toggle" data-toggle="collapse" data-target="#form-item-otp-settings" aria-controls="form-item-otp-settings" aria-expanded="false" title="<?php echo $lang->get('settings'); ?>">
                                                        <i class="fa-solid fa-sliders mr-1" aria-hidden="true"></i><span class="sr-only"><?php echo $lang->get('settings'); ?> </span><span id="form-item-otp-summary">SHA-1 · 6 · 30 s</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted"><?php echo $lang->get('otp_uri_help'); ?></small>
                                            <div class="collapse" id="form-item-otp-settings">
                                                <div class="tp-item-form-panel">
                                                    <div class="form-row">
                                                        <div class="form-group col-sm-4 mb-2">
                                                            <label for="form-item-otpAlgorithm" class="mb-1"><?php echo $lang->get('otp_algorithm'); ?></label>
                                                            <select class="form-control form-item-control purify" data-field-name="otpAlgorithm" id="form-item-otpAlgorithm" data-change-ongoing="">
                                                                <option value="sha1">SHA-1</option>
                                                                <option value="sha256">SHA-256</option>
                                                                <option value="sha512">SHA-512</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-sm-4 mb-2">
                                                            <label for="form-item-otpDigits" class="mb-1"><?php echo $lang->get('otp_digits'); ?></label>
                                                            <select class="form-control form-item-control purify" data-field-name="otpDigits" id="form-item-otpDigits" data-change-ongoing="">
                                                                <option value="6">6</option>
                                                                <option value="8">8</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-sm-4 mb-2">
                                                            <label for="form-item-otpPeriod" class="mb-1"><?php echo $lang->get('otp_period'); ?></label>
                                                            <input type="number" min="1" max="<?php echo ITEM_TOTP_MAX_PERIOD; ?>" class="form-control form-item-control purify" data-field-name="otpPeriod" id="form-item-otpPeriod" value="30" data-change-ongoing="">
                                                        </div>
                                                    </div>
                                                    <div class="form-group mb-0">
                                                        <label for="form-item-otpPhoneNumber" class="mb-1"><?php echo $lang->get('phone_number'); ?></label>
                                                        <input type="phone" class="form-control form-item-control purify" data-field-name="otpPhoneNumber" id="form-item-otpPhoneNumber" data-change-ongoing="">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <!-- URL -->
                                        <div class="form-group">
                                            <label for="form-item-url" class="mb-1"><i class="fa-solid fa-link fa-fw mr-1 text-muted" aria-hidden="true"></i><?php echo $lang->get('url'); ?></label>
                                            <input id="form-item-url" type="url" class="form-control form-item-control purify" data-field-name="url" data-change-ongoing="">
                                        </div>
                                        <!-- EMAIL -->
                                        <div class="form-group">
                                            <label for="form-item-email" class="mb-1"><i class="fa-solid fa-envelope fa-fw mr-1 text-muted" aria-hidden="true"></i><?php echo $lang->get('email'); ?></label>
                                            <input id="form-item-email" type="email" class="form-control form-item-control purify" data-field-name="email" data-change-ongoing="">
                                        </div>
                                        <!-- FOLDERS -->
                                        <div class="form-group">
                                            <label for="form-item-folder" class="mb-1"><i class="fa-solid fa-folder fa-fw mr-1 text-muted" aria-hidden="true"></i><?php echo $lang->get('folder'); ?></label>
                                            <select id="form-item-folder" class="form-control form-item-control select2-hidden-accessible select2 no-root" style="width:100%;" data-change-ongoing=""></select>
                                        </div>
                                        <!-- TAGS: one chip per tag; the hidden field keeps the space separated list the server stores -->
                                        <div class="form-group">
                                            <label for="form-item-tags-entry" class="mb-1"><i class="fa-solid fa-tags fa-fw mr-1 text-muted" aria-hidden="true"></i><?php echo $lang->get('tags'); ?></label>
                                            <div class="form-control tp-tags-field" id="form-item-tags-field">
                                                <span class="tp-tags-list" id="form-item-tags-list"></span>
                                                <input id="form-item-tags-entry" type="text" class="tp-tags-entry" maxlength="30" autocomplete="off" spellcheck="false" placeholder="<?php echo $lang->get('item_tags_placeholder'); ?>">
                                            </div>
                                            <input id="form-item-tags" type="hidden" class="form-item-control purify" data-field-name="tags">
                                        </div>
                                    </div>
                                </div>

                                <!-- DESCRIPTION -->
                                <div class="mb-0">
                                    <div class="tp-item-form-label" id="form-item-description-label"><i class="fa-solid fa-align-left fa-fw mr-1 text-muted" aria-hidden="true"></i><?php echo $lang->get('description'); ?></div>
                                    <div id="form-item-description" class="form-item-control w-100 clear-me-html purify purifyHtml" data-field-name="description" data-change-ongoing="" aria-labelledby="form-item-description-label"></div>
                                </div>
                            </div>

                            <!-- CUSTOM FIELDS: shown while the folder holds field categories (observer in items.js.php) -->
                            <div class="tp-item-form-section tp-item-form-section-collapsible is-two-columns hidden" data-section="fields" id="form-item-section-fields">
                                <div class="tp-item-form-section-head">
                                    <h3 class="tp-item-form-section-heading">
                                        <button type="button" class="tp-item-form-section-toggle" aria-expanded="true" aria-controls="form-item-section-fields-body">
                                            <i class="fa-solid fa-chevron-down fa-fw tp-item-form-section-chevron" aria-hidden="true"></i>
                                            <i class="fa-solid fa-cubes fa-fw mr-2" aria-hidden="true"></i><?php echo $lang->get('fields'); ?>
                                        </button>
                                    </h3>
                                    <div class="tp-item-form-section-tools">
                                        <div class="tp-item-form-section-switch" role="group" aria-label="<?php echo $lang->get('fields'); ?>">
                                            <button type="button" class="tp-item-form-section-tool tp-item-form-fields-columns infotip" data-columns="1" aria-pressed="false" title="<?php echo $lang->get('item_form_fields_one_column'); ?>" aria-label="<?php echo $lang->get('item_form_fields_one_column'); ?>"><i class="fa-solid fa-grip-lines" aria-hidden="true"></i></button>
                                            <button type="button" class="tp-item-form-section-tool tp-item-form-fields-columns infotip" data-columns="2" aria-pressed="true" title="<?php echo $lang->get('item_form_fields_two_columns'); ?>" aria-label="<?php echo $lang->get('item_form_fields_two_columns'); ?>"><i class="fa-solid fa-table-columns" aria-hidden="true"></i></button>
                                        </div>
                                        <button type="button" class="tp-item-form-section-tool tp-item-form-to-top infotip" title="<?php echo $lang->get('item_form_back_to_top'); ?>" aria-label="<?php echo $lang->get('item_form_back_to_top'); ?>"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                                <div class="tp-item-form-section-body" id="form-item-section-fields-body">
                                    <div id="form-item-field" class="hidden">
                                        <?php
                                            $session_item_fields = $session->get('system-item_fields');//print_r($session_item_fields);
                                            if (isset($session_item_fields) === true) {
                                                foreach ($session_item_fields as $category) {
                                                    //print_r($category);
                                                    echo '
                                                <div class="callout callout-info form-item-category hidden" id="form-item-category-' . $category['id'] . '">
                                                    <h5>' . $category['title'] . '</h5>
                                                    <div class="tp-item-form-fields-grid">';
                                                    foreach ($category['fields'] as $field) {
                                                        // One grid cell per field, its regex hint included; a textarea spans both columns
                                                        echo '
                                                        <div class="tp-item-form-field-cell' . ($field['type'] === 'textarea' ? ' is-wide' : '') . '">';
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
                                                        <div class="small text-muted mb-2">Regex: '. $field['regex'] .'</div>
                                                            ';
                                                        }
                                                        echo '
                                                        </div>';
                                                    }
                                                    echo '
                                                    </div>';
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
                                </div>
                            </div>

                            <!-- ATTACHMENTS -->
                            <div class="tp-item-form-section tp-item-form-section-collapsible" data-section="attachments" id="form-item-section-attachments">
                                <div class="tp-item-form-section-head">
                                    <h3 class="tp-item-form-section-heading">
                                        <button type="button" class="tp-item-form-section-toggle" aria-expanded="true" aria-controls="form-item-section-attachments-body">
                                            <i class="fa-solid fa-chevron-down fa-fw tp-item-form-section-chevron" aria-hidden="true"></i>
                                            <i class="fa-solid fa-paperclip fa-fw mr-2" aria-hidden="true"></i><?php echo $lang->get('attachments'); ?>
                                            <span class="badge badge-pill badge-info ml-2 form-item-attachments-count hidden"></span>
                                        </button>
                                    </h3>
                                    <div class="tp-item-form-section-tools">
                                        <button type="button" class="tp-item-form-section-tool tp-item-form-to-top infotip" title="<?php echo $lang->get('item_form_back_to_top'); ?>" aria-label="<?php echo $lang->get('item_form_back_to_top'); ?>"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                                <div class="tp-item-form-section-body" id="form-item-section-attachments-body">
                                    <!-- Files already attached to the item -->
                                    <div class="hidden" id="form-item-attachments-zone">
                                        <div class="tp-item-files clear-me-html" id="form-item-attachments"></div>
                                    </div>
                                    <!-- Drop zone: plupload browse button and drop target, files upload as soon as they are added -->
                                    <div id="form-item-upload-zone">
                                        <div class="tp-item-dropzone" id="form-item-attach-pickfiles" role="button" tabindex="0" aria-describedby="form-item-dropzone-hint">
                                            <i class="fa-solid fa-cloud-arrow-up tp-item-dropzone-icon" aria-hidden="true"></i>
                                            <span class="font-weight-bold"><?php echo $lang->get('item_attachments_drop_hint'); ?></span>
                                            <small class="text-muted" id="form-item-dropzone-hint"><?php
                                                $attachmentMaxSize = (int) ($SETTINGS['upload_maxfilesize'] ?? 0);
                                                echo $attachmentMaxSize > 0 ? sprintf($lang->get('item_attachments_max_size'), $attachmentMaxSize . ' MB') : '';
                                            ?></small>
                                        </div>
                                        <input type="hidden" id="form-item-hidden-pickFilesNumber" value="0" />
                                        <div class="tp-item-files tp-item-files-uploads hidden clear-me-html" id="form-item-upload-pickfilesList"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- DETAILS -->
                            <div class="tp-item-form-section tp-item-form-section-collapsible" data-section="details" id="form-item-section-details">
                                <div class="tp-item-form-section-head">
                                    <h3 class="tp-item-form-section-heading">
                                        <button type="button" class="tp-item-form-section-toggle" aria-expanded="true" aria-controls="form-item-section-details-body">
                                            <i class="fa-solid fa-chevron-down fa-fw tp-item-form-section-chevron" aria-hidden="true"></i>
                                            <i class="fa-solid fa-list fa-fw mr-2" aria-hidden="true"></i><?php echo $lang->get('details'); ?>
                                        </button>
                                    </h3>
                                    <div class="tp-item-form-section-tools">
                                        <button type="button" class="tp-item-form-section-tool tp-item-form-to-top infotip" title="<?php echo $lang->get('item_form_back_to_top'); ?>" aria-label="<?php echo $lang->get('item_form_back_to_top'); ?>"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                                <div class="tp-item-form-section-body" id="form-item-section-details-body">
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <!-- RESTRICTED TO -->
                                            <div class="form-group">
                                                <label for="form-item-restrictedto" class="mb-1"><i class="fa-solid fa-user-lock fa-fw mr-1 text-muted" aria-hidden="true"></i><?php echo $lang->get('restricted_to'); ?></label>
                                                <select id="form-item-restrictedto" class="form-control form-item-control select2-hidden-accessible select2" style="width:100%;" multiple="multiple" data-change-ongoing=""></select>
                                                <input type="hidden" id="form-item-restrictedToUsers" class="form-item-control">
                                                <input type="hidden" id="form-item-restrictedToRoles" class="form-item-control">
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
                                        </div>
                                        <div class="col-lg-6">
                                            <!-- ANNOUNCE BY EMAIL -->
                                            <div class="form-group">
                                                <label for="form-item-anounce" class="mb-1"><i class="fa-solid fa-bullhorn fa-fw mr-1 text-muted" aria-hidden="true"></i><?php echo $lang->get('anounce_item_by_email'); ?></label>
                                                <select id="form-item-anounce" class="form-control form-item-control select2" style="width:100%;" multiple="multiple" data-placeholder="<?php echo $lang->get('select_users_if_needed'); ?>" data-change-ongoing=""></select>
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
                                        <div class="tp-item-form-panel" role="group" aria-labelledby="form-item-delete-after-title">
                                            <div class="tp-item-form-label" id="form-item-delete-after-title"><i class="fa-solid fa-eraser fa-fw mr-1 text-muted" aria-hidden="true"></i><?php echo $lang->get('allow_item_to_be_deleted'); ?></div>
                                            <div class="form-row">
                                                <div class="form-group col-sm-6 mb-2">
                                                    <label for="form-item-deleteAfterShown" class="mb-1 font-weight-normal"><?php echo $lang->get('item_deleted_after_being_viewed_x_times'); ?></label>
                                                    <input type="number" class="form-control form-item-control purify" data-field-name="deleteAfterShown" id="form-item-deleteAfterShown" data-change-ongoing="">
                                                </div>
                                                <div class="form-group col-sm-6 mb-2">
                                                    <label for="form-item-deleteAfterDate" class="mb-1 font-weight-normal"><?php echo $lang->get('item_deleted_after_date'); ?></label>
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
                                    <?php
                                    }
                                    ?>
                                </div>
                            </div>

                        </div>
                </div>
            </div>
            </form>
        </div>
    </div>

    <!-- COPY ITEM MODAL -->
    <div class="modal fade" id="modal-item-copy" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-copy mr-2"></i><?php echo $lang->get('copy_item'); ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label><?php echo $lang->get('new_label'); ?></label>
                        <input type="text" class="form-control form-item-control" id="form-item-copy-new-label">
                    </div>
                    <div class="form-group">
                        <label><?php echo $lang->get('select_destination_folder'); ?></label>
                        <select class="form-control form-item-control select2" style="width:100%;" id="form-item-copy-destination"></select>
                        <div id="copy-item-renewal-notice" class="alert alert-info hidden mt-2" role="status" aria-live="polite"></div>
                    </div>
                    <div class="alert alert-info mb-0 hidden" id="form-item-copy-webauthn-note">
                        <i class="fa-solid fa-fingerprint mr-2"></i><?php echo $lang->get('webauthn_copy_not_copied'); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="form-item-copy-perform"><?php echo $lang->get('perform'); ?></button>
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>


    <!-- DELETE ITEM MODAL -->
    <div class="modal fade" id="modal-item-delete" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-trash mr-2"></i><?php echo $lang->get('delete_item'); ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <h5><i class="icon fa fa-exclamation-triangle mr-2"></i><?php echo $lang->get('warning'); ?></h5>
                        <?php echo $lang->get('delete_item_message'); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" id="form-item-delete-perform"><?php echo $lang->get('perform'); ?></button>
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>


    <!-- SHARE ITEM MODAL -->
    <div class="modal fade" id="modal-item-share" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-share-alt mr-2"></i><?php echo $lang->get('share_item'); ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="form-item-share" class="needs-validation" novalidate onsubmit="return false;">
                        <div class="callout callout-info">
                            <h5><i class="icon fa fa-info mr-2"></i><?php echo $lang->get('information'); ?></h5>
                            <p><?php echo $lang->get('share_item_message'); ?></p>
                        </div>
                        <div class="form-group">
                            <label for="form-item-share-email"><?php echo $lang->get('email_address'); ?></label>
                            <input type="email" class="form-control clear-me-val" id="form-item-share-email"
                                   placeholder="<?php echo $lang->get('enter_email'); ?>" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="form-item-share-perform"><?php echo $lang->get('perform'); ?></button>
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>


    <!-- NOTIFY ITEM MODAL -->
    <div class="modal fade" id="modal-item-notify" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-bullhorn mr-2"></i><?php echo $lang->get('notification'); ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="callout callout-info">
                        <h5><i class="icon fa fa-info mr-2"></i><?php echo $lang->get('information'); ?></h5>
                        <p><?php echo $lang->get('notification_message'); ?></p>
                    </div>
                    <div class="form-group">
                        <input type="checkbox" class="form-check-input form-item-control flat-blue" id="form-item-notify-checkbox"><label for="form-item-notify-checkbox" class="ml-3"><?php echo $lang->get('notify_on_change'); ?></label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="form-item-notify-perform"><?php echo $lang->get('confirm'); ?></button>
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>


    <!-- OTV ITEM MODAL -->
    <div class="modal fade" id="modal-item-otv" tabindex="-1" role="dialog" aria-labelledby="secure-send-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-shield-halved mr-2" aria-hidden="true"></i><span id="secure-send-modal-title"><?php echo $lang->get('secure_send'); ?></span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="<?php echo htmlspecialchars($lang->get('close'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="form-secure-send-mode" value="item">

                    <div class="callout callout-info py-3 mb-4">
                        <p class="mb-0"><?php echo $lang->get('secure_send_intro'); ?></p>
                    </div>

                    <div class="alert alert-light border py-2 mb-4" role="note">
                        <small class="text-muted"><i class="fa-solid fa-circle-info mr-2" aria-hidden="true"></i><?php
                            if ($secureSendShowSenderName === false) {
                                echo htmlspecialchars(
                                    str_replace(
                                        '#ENTITY#',
                                        $secureSendPublicEntity,
                                        $lang->get($secureSendPublicEntity === ''
                                            ? 'secure_send_public_identity_disabled'
                                            : 'secure_send_public_identity_disabled_entity')
                                    ),
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                );
                            } elseif ($secureSendProfileName !== '') {
                                echo htmlspecialchars(
                                    str_replace(
                                        ['#NAME#', '#ENTITY#'],
                                        [$secureSendProfileName, $secureSendPublicEntity],
                                        $lang->get($secureSendPublicEntity === ''
                                            ? 'secure_send_public_identity_notice'
                                            : 'secure_send_public_identity_notice_entity')
                                    ),
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                );
                            } elseif ($secureSendPublicEntity !== '') {
                                echo htmlspecialchars(
                                    str_replace('#ENTITY#', $secureSendPublicEntity, $lang->get('secure_send_public_identity_entity_only')),
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                );
                            } else {
                                echo htmlspecialchars($lang->get('secure_send_public_identity_missing'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            }
                        ?></small>
                    </div>

                    <!-- NOTE MODE FIELDS (ad-hoc secret/note) -->
                    <fieldset id="secure-send-note-fields" class="hidden mb-4">
                        <legend class="h6 border-bottom pb-2 mb-3"><i class="fa-regular fa-note-sticky mr-2" aria-hidden="true"></i><?php echo $lang->get('secure_send_note'); ?></legend>
                        <div class="form-row">
                            <div class="form-group col-sm-6">
                                <label for="form-secure-send-title"><?php echo $lang->get('label'); ?></label>
                                <input type="text" class="form-control clear-me-val" id="form-secure-send-title">
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="form-secure-send-secret"><?php echo $lang->get('password'); ?></label>
                                <input type="text" class="form-control clear-me-val" id="form-secure-send-secret" autocomplete="off">
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="form-secure-send-login"><?php echo $lang->get('login'); ?></label>
                                <input type="text" class="form-control clear-me-val" id="form-secure-send-login">
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="form-secure-send-url"><?php echo $lang->get('url'); ?></label>
                                <input type="text" class="form-control clear-me-val" id="form-secure-send-url">
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label for="form-secure-send-note"><?php echo $lang->get('description'); ?></label>
                            <textarea class="form-control clear-me-val" id="form-secure-send-note" rows="3"></textarea>
                        </div>
                    </fieldset>

                    <fieldset class="mb-4">
                        <legend class="h6 border-bottom pb-2 mb-3"><i class="fa-solid fa-sliders mr-2" aria-hidden="true"></i><?php echo $lang->get('secure_send_access_settings'); ?></legend>
                        <div class="form-row align-items-end">
                            <div class="form-group col-sm-6">
                                <label for="form-item-otv-days"><?php echo $lang->get('number_of_days'); ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa-regular fa-calendar-days" aria-hidden="true"></i></span></div>
                                    <input type="number" class="form-control clear-me-val" id="form-item-otv-days" min="1" max="<?php echo (int) $SETTINGS['otv_expiration_period']; ?>" value="<?php echo (int) $SETTINGS['otv_expiration_period']; ?>" aria-describedby="secure-send-days-limit">
                                </div>
                                <small id="secure-send-days-limit" class="form-text text-muted"><?php echo ucfirst($lang->get('maximum')) . ': ' . (int) $SETTINGS['otv_expiration_period']; ?></small>
                            </div>
                            <div class="form-group col-sm-6">
                                <label for="form-item-otv-views"><?php echo $lang->get('number_of_times'); ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa-regular fa-eye" aria-hidden="true"></i></span></div>
                                    <input type="number" class="form-control clear-me-val" id="form-item-otv-views" value="1" min="1" max="<?php echo (int) ($SETTINGS['secure_send_max_views'] ?? 5); ?>" aria-describedby="secure-send-views-limit">
                                </div>
                                <small id="secure-send-views-limit" class="form-text text-muted"><?php echo ucfirst($lang->get('maximum')) . ': ' . (int) ($SETTINGS['secure_send_max_views'] ?? 5); ?></small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="form-secure-send-passphrase"><i class="fa-solid fa-lock mr-2" aria-hidden="true"></i><?php echo $lang->get((int) ($SETTINGS['secure_send_require_passphrase'] ?? 0) === 1 ? 'secure_send_passphrase_required_label' : 'secure_send_passphrase'); ?></label>
                            <input type="text" class="form-control clear-me-val" id="form-secure-send-passphrase" autocomplete="off" maxlength="1024" aria-describedby="secure-send-passphrase-hint" <?php echo (int) ($SETTINGS['secure_send_require_passphrase'] ?? 0) === 1 ? 'required' : ''; ?>>
                            <small id="secure-send-passphrase-hint" class="form-text text-muted"><?php echo $lang->get('secure_send_passphrase_hint'); ?></small>
                        </div>
                        <?php if ((int) $session->get('user-read_only') !== 1) { ?>
                            <div class="form-group hidden" id="secure-send-totp-option">
                                <div class="d-flex align-items-center">
                                    <input type="checkbox" class="form-item-control flat-blue" id="form-secure-send-include-totp" aria-describedby="secure-send-include-totp-hint">
                                    <label for="form-secure-send-include-totp" class="mb-0 ml-2"><?php echo $lang->get('secure_send_include_totp'); ?></label>
                                </div>
                                <small id="secure-send-include-totp-hint" class="form-text text-warning"><?php echo $lang->get('secure_send_include_totp_hint'); ?></small>
                            </div>
                        <?php } ?>
                        <div class="secure-send-panel">
                            <div class="d-flex align-items-center">
                                <input type="checkbox" class="form-item-control flat-blue" id="form-item-otv-subdomain" aria-describedby="secure-send-address-preview" <?php
                                    echo trim((string) ($SETTINGS['otv_subdomain'] ?? '')) === '' ? ' disabled' : ''; ?> data-change-ongoing="">
                                <label for="form-item-otv-subdomain" class="mb-0 ml-2"><?php echo $lang->get('secure_send_use_public_url'); ?></label>
                            </div>
                            <p class="text-muted small mt-2 mb-0" id="secure-send-address-preview" aria-live="polite"></p>
                            <?php if (trim((string) ($SETTINGS['otv_subdomain'] ?? '')) === '') { ?>
                                <small class="d-block text-muted mt-1"><?php echo $lang->get('feature_disabled_by_administrator'); ?></small>
                            <?php } ?>
                        </div>
                    </fieldset>

                    <div class="secure-send-panel mb-4">
                        <div class="secure-send-result-heading d-flex flex-column flex-sm-row align-items-sm-center justify-content-sm-between mb-3">
                            <label for="form-item-otv-link" class="mb-2 mb-sm-0"><i class="fa-solid fa-link mr-2" aria-hidden="true"></i><?php echo $lang->get('secure_send_sharing_link'); ?></label>
                            <button type="button" class="btn btn-primary" id="form-secure-send-generate"><i class="fa-solid fa-link mr-2" aria-hidden="true"></i><?php echo $lang->get('secure_send_generate_link'); ?></button>
                        </div>
                        <div class="input-group">
                            <input type="text" class="form-control clear-me-val" readonly id="form-item-otv-link" data-otv-id="0" autocomplete="off" spellcheck="false" placeholder="<?php echo htmlspecialchars($lang->get('secure_send_link_placeholder'), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-primary btn-copy-clipboard" disabled id="form-item-otv-copy-button"><i class="fa-regular fa-copy mr-2" aria-hidden="true"></i><?php echo $lang->get('copy'); ?></button>
                            </div>
                        </div>
                        <small id="form-secure-send-passphrase-reminder" class="d-block mt-2 text-info hidden"><i class="fa-solid fa-circle-info mr-1" aria-hidden="true"></i><?php echo $lang->get('secure_send_passphrase_reminder'); ?></small>
                        <details id="secure-send-snapshot-hint" class="text-muted small mt-3">
                            <summary><?php echo $lang->get('secure_send_snapshot_details'); ?></summary>
                            <p class="mt-2 mb-0"><?php echo $lang->get('secure_send_snapshot_hint'); ?></p>
                        </details>
                    </div>

                    <!-- MY SECURE SENDS -->
                    <section aria-labelledby="secure-send-list-title">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 id="secure-send-list-title" class="mb-0"><i class="fa-regular fa-paper-plane mr-2" aria-hidden="true"></i><?php echo $lang->get('secure_send_my_sends'); ?></h6>
                            <button type="button" class="btn btn-sm btn-default" id="secure-send-list-refresh" title="<?php echo htmlspecialchars($lang->get('refresh'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-rotate" aria-hidden="true"></i><span class="sr-only"><?php echo $lang->get('refresh'); ?></span></button>
                        </div>
                        <div id="secure-send-list" class="small" aria-live="polite"><p class="text-muted mb-0 py-3"><?php echo $lang->get('secure_send_no_active'); ?></p></div>
                    </section>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $lang->get('close'); ?></button>
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
                        <!-- Folder icons: the icon picker adds its button and area around each field -->
                        <div class="form-group">
                            <label for="form-folder-add-icon"><?php echo $lang->get('icon'); ?></label>
                            <input type="text" class="form-control form-folder-control" id="form-folder-add-icon">
                        </div>
                        <div class="form-group">
                            <label for="form-folder-add-icon-selected"><?php echo $lang->get('icon_on_selection'); ?></label>
                            <input type="text" class="form-control form-folder-control" id="form-folder-add-icon-selected">
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
                    <div class="form-group">
                        <label><?php echo $lang->get('options'); ?></label>
                        <div class="input-group input-group-sm">
                            <input type="checkbox" class="flat-blue ml-2" id="form-folder-copy-sub-directories">
                            <label for="form-folder-copy-sub-directories" class="ml-2"><?php echo $lang->get('copy_sub_directories'); ?></label>
                        </div>    
                        <div class="input-group input-group-sm">
                            <input type="checkbox" class="flat-blue ml-2" id="form-folder-copy-items">
                            <label for="form-folder-copy-items" class="ml-2"><?php echo $lang->get('copy_items'); ?></label>
                        </div> 
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
                <div class="card-header align-items-center py-2">
                    <div class="row">
                        <div class="col-6">
                            <h3 class="card-title mb-0 mt-2"><i class="fa-regular fa-folder-open mr-2 text-info">
                                </i><span class=""><?php echo $lang->get('folders'); ?></span>
                            </h3>
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
                                <div class="btn-group btn-group-sm float-left" role="group">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fa-solid fa-bars"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item tp-action" href="#" data-folder-action="items-checkbox"><i class="fa-regular fa-square-check mr-2"></i><?php echo $lang->get('item_checkbox'); ?></a>
                                            <a class="dropdown-item tp-action" href="#" data-folder-action="items-delete"><i class="fa-regular fa-trash-can mr-2"></i><?php echo $lang->get('delete_items'); ?></a>
                                            <a class="dropdown-item tp-action" href="#" data-folder-action="subfolders-show-hide"><i class="fa-regular fa-eye-slash mr-2"></i><?php echo $lang->get('show_hide_folders'); ?></a>
                                            <div class="dropdown-divider show-delete-checkbox hidden"></div>
                                            <a class="dropdown-item tp-action show-delete-checkbox hidden" href="#" data-folder-action="">
                                                <div class="input-group input-group-sm">
                                                    <input type="checkbox" class="flat-blue mr-2" id="items-selection-checkbox">
                                                    <label for="import-keepass-edit-all-checkbox" class="ml-2"><?php echo $lang->get('select_all'); ?></label>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-primary tp-action" id="btn-new-item" data-item-action="new">
                                        <i class="fa-solid fa-plus mr-2"></i><?php echo $lang->get('new'); ?>
                                    </button>
                                    <?php
                                    if (
                                        isset($SETTINGS['otv_is_enabled']) === true && (int) $SETTINGS['otv_is_enabled'] === 1
                                        && (int) ($SETTINGS['secure_send_allow_notes'] ?? 0) === 1
                                    ) {
                                        ?>
                                        <button type="button" class="btn btn-primary" id="secure-send-note-open" title="<?php echo $lang->get('secure_send_send_a_secret'); ?>">
                                            <i class="fa-solid fa-paper-plane mr-2"></i><?php echo $lang->get('secure_send_send'); ?>
                                        </button>
                                        <?php
                                    }
                                    ?>
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
                                                <input type="checkbox" class="mr-2" id="limited-search">
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
                <div id="tp-folder-progress-wrap"><div id="tp-folder-progress-bar"></div></div>
                <div id="folder-renewal-notice" class="alert alert-info hidden m-2" role="status" aria-live="polite"></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-striped hidden" id="table_teampass_subfolders_list" style="width:100%;">
                            <thead><tr><th><i class="fa-solid fa-folder mr-1"></i><?php echo $lang->get('sub-folders'); ?><span class="badge badge-secondary ml-2" id="count-subfolders-badge"></span></th></tr></thead>
                            <tbody id="teampass_subfolders_list">
                                <tr class="tp-skeleton-row"><td><span class="skeleton-line mr-2" style="width:14px;height:14px;border-radius:3px;"></span><span class="skeleton-line skeleton-lg"></span></td></tr>
                                <tr class="tp-skeleton-row"><td><span class="skeleton-line mr-2" style="width:14px;height:14px;border-radius:3px;"></span><span class="skeleton-line skeleton-md"></span></td></tr>
                                <tr class="tp-skeleton-row"><td><span class="skeleton-line mr-2" style="width:14px;height:14px;border-radius:3px;"></span><span class="skeleton-line skeleton-sm"></span></td></tr>
                            </tbody>
                        </table>

                        <table class="table table-truncated table-hover table-striped" id="table_teampass_items_list" style="width:100%;">
                            <thead><tr><th><i class="fa-solid fa-key mr-1"></i><?php echo $lang->get('items'); ?><span class="badge badge-secondary ml-2" id="count-items-badge"></span></th></tr></thead>
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

        <!-- DELETE ITEMS CONFIRM -->
        <div class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" id="items-delete-user-confirm">
            <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                <h4 class="modal-title"><?php echo $lang->get('please_confirm_deletion'); ?></h4>
                </div>
                <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="modal-btn-items-delete-launch" data-id=""><?php echo $lang->get('yes'); ?></button>
                <button type="button" class="btn btn-default" id="modal-btn-items-delete-cancel"><?php echo $lang->get('cancel'); ?></button>
                </div>
            </div>
            </div>
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
                            <h3 class="d-inline align-middle" id="card-item-label"><span class="skeleton-line skeleton-title"></span></h3>
                            <span id="card-item-webauthn-title-badge" class="badge badge-info ml-2 infotip hidden" title="<?php echo $lang->get('webauthn_list_marker'); ?>">
                                <i class="fa-solid fa-fingerprint"></i>
                            </span>
                            <span id="card-item-readonly-badge" class="badge badge-warning ml-2 hidden" title="<?php echo $lang->get('read_only'); ?>">
                                <i class="fa-solid fa-lock mr-1"></i><small><?php echo $lang->get('read_only'); ?></small>
                            </span>
                            <span id="card-item-lapr-badges" class="ml-2 hidden"></span>
                            <span id="card-item-renewal-badge" class="hidden"></span>
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
                                            <a class="text-navy tp-action ml-3" href="#" data-item-action="notify" id="item-action-notify"><i class="fa-regular fa-bell mr-1" id="item-action-notify-icon"></i><small><?php echo $lang->get('notify'); ?></small></a>
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
                                                <a class="text-navy tp-action ml-3" href="#" data-item-action="otv"><i class="fa-solid fa-paper-plane mr-1"></i><small><?php echo $lang->get('secure_send'); ?></small></a>
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
                                    <span id="item-hibp-badge" class="ml-2 d-inline-block align-middle hidden"></span>
                                    <span id="card-item-pwd-security-badge" class="badge ml-2 hidden"></span>
                                    <button type="button" class="float-right btn btn-secondary btn-sm btn-copy-clipboard" id="card-item-pwd-button">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                    <button type="button" class="float-right btn btn-secondary btn-sm mr-1 hidden" id="card-item-password-history-button">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                    </button>
                                    <button type="button" class="float-right btn btn-secondary btn-sm mr-1" id="card-item-pwd-toggle-button">
                                        <i class="fa-regular fa-eye pwd-toggle-icon"></i>
                                    </button>
                                    <span id="card-item-pwd" class="float-right pointer mr-2"><span class="skeleton-line skeleton-md"></span></span>
                                    <input id="hidden-item-pwd" type="hidden">
                                </li>
                                <li class="list-group-item">
                                    <b><?php echo $lang->get('index_login'); ?></b>
                                    <button type="button" class="float-right btn btn-secondary btn-sm ml-1 btn-copy-clipboard-clear" data-clipboard-target="card-item-login" id="card-item-login-btn">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                    <span id="card-item-login" class="float-right"><span class="skeleton-line skeleton-sm"></span></span>
                                </li>
                                <li class="list-group-item">
                                    <b><?php echo $lang->get('email'); ?></b>
                                    <button type="button" class="float-right btn btn-secondary btn-sm ml-1 btn-copy-clipboard-clear" data-clipboard-target="card-item-email" id="card-item-email-btn">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                    <span id="card-item-email" class="float-right ml-1"><span class="skeleton-line skeleton-sm"></span></span>
                                </li>
                                <li class="list-group-item item-url-row">
                                    <b><?php echo $lang->get('url'); ?></b>
                                    <button type="button" class="float-right btn btn-secondary btn-sm ml-1 btn-copy-clipboard-clear" data-clipboard-target="card-item-url-text" id="card-item-url-text-btn">
                                        <i class="fa-regular fa-copy"></i>
                                    </button>
                                    <a id="card-item-url" class="float-right ml-1 btn btn-secondary btn-sm" href="#" target="_blank">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                    <span id="card-item-url-text" class="float-right ml-1"><span class="skeleton-line skeleton-lg"></span></span>
                                </li>
                            </ul>
                            <div id="card-item-corrupted-warning" class="alert mt-3 mb-0 hidden"></div>
                            <div id="card-item-lapr-info" class="alert alert-info mt-3 mb-0 hidden"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="card">
                        <div class="card-body">
                            <ul class="list-group list-group-unbordered mb-3">
                                <li class="list-group-item">
                                    <b><?php echo $lang->get('restricted_to'); ?></b>
                                    <a id="card-item-restrictedto" class="float-right ml-1"><span class="skeleton-line skeleton-md"></span></a>
                                </li>
                                <li class="list-group-item">
                                    <b><?php echo $lang->get('tags'); ?></b>
                                    <a id="card-item-tags" class="float-right ml-1"><span class="skeleton-line skeleton-sm"></span></a>
                                </li>
                                <?php if (isset($SETTINGS['enable_kb']) && (int) $SETTINGS['enable_kb'] === 1 && (int) $session->get('user-admin') !== 1) : ?>
                                <li class="list-group-item">
                                    <b><?php echo $lang->get('kb_menu'); ?></b>
                                    <span id="card-item-kbs" class="float-right ml-1"><span class="skeleton-line skeleton-sm"></span></span>
                                </li>
                                <?php endif; ?>
                                <li class="list-group-item">
                                    <b><?php echo $lang->get('otp_code'); ?></b>
                                    <a id="card-item-opt_code" class="float-right ml-1"><span class="skeleton-line skeleton-sm"></span></a>
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
                    <div class="callout callout-info visible" id="card-item-description"><span class="skeleton-line skeleton-xl"></span></div>
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
                                                <button type="button" class="float-right btn btn-secondary btn-sm ml-1 btn-copy-clipboard-clear"  data-clipboard-target="card-item-field-value-' . $field['id'] . '">
                                                    <i class="fa-solid fa-copy"></i>
                                                </button>
                                                <button type="button" class="float-right btn btn-secondary btn-sm ml-1 btn-reveal-field hidden" data-field-id="' . $field['id'] . '">
                                                    <i class="fa-regular fa-eye"></i>
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
                <!-- Visibility driven by the passkey list only: .item-details-card is toggled globally -->
                <div class="col-12 hidden" id="item-card-webauthn">
                    <div class="card card-default collapsed">
                        <div class="card-header bg-gray-dark">
                            <h3 class="card-title pointer" data-toggle="collapse" data-target="#card-item-webauthn">
                                <i class="fa-solid fa-fingerprint mr-2"></i><?php echo $lang->get('webauthn_passkeys'); ?>
                                <span class="badge badge-secondary ml-2" id="card-item-webauthn-badge"></span>
                            </h3>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body collapse" id="card-item-webauthn">
                            <p class="text-muted small mb-2"><?php echo $lang->get('webauthn_card_info'); ?></p>
                            <ul class="list-group list-group-unbordered" id="card-item-webauthn-list"></ul>
                        </div>
                        <!-- /.card-body -->
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

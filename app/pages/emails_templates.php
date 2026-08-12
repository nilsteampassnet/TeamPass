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
 * @file      emails_templates.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Email templates customization: master/detail editor over the catalog
 * declared in app/config/emails_templates.php. The list is filled by
 * emails_templates.js.php, so the markup here is only the shell.
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
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
if (
    $checkUserAccess->checkSession() === false
    || $checkUserAccess->userAccessPage('emails_templates') === false
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //
$templatesEnabled = isset($SETTINGS['emails_templates_enabled']) === false
    || (int) $SETTINGS['emails_templates_enabled'] === 1;

?>
<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-envelope-open-text mr-2"></i><?php echo $lang->get('emails_templates'); ?>
                </h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<section class="content">
    <div class="container-fluid">

        <?php if ($templatesEnabled === false) { ?>
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation mr-2"></i>
            <?php echo $lang->get('emails_templates_disabled_warning'); ?>
        </div>
        <?php } ?>

        <div class="alert alert-info">
            <i class="fas fa-circle-info mr-2"></i>
            <?php echo $lang->get('emails_templates_intro'); ?>
        </div>

        <div class="row">
            <!-- MASTER: language selector + templates list -->
            <div class="col-md-4">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo $lang->get('emails_templates_list'); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="emails-templates-language"><?php echo $lang->get('language'); ?></label>
                            <select class="form-control form-control-sm" id="emails-templates-language"></select>
                        </div>
                        <div id="emails-templates-list">
                            <i class="fas fa-circle-notch fa-spin mr-2"></i><?php echo $lang->get('loading'); ?>
                        </div>
                        <small class="form-text text-muted mt-2">
                            <i class="fas fa-circle text-primary mr-1" style="font-size:.6rem;"></i>
                            <?php echo $lang->get('emails_templates_customized_marker'); ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- DETAIL: editor -->
            <div class="col-md-8">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title" id="emails-templates-title">
                            <?php echo $lang->get('emails_templates_select_one'); ?>
                        </h3>
                    </div>
                    <div class="card-body">

                        <div id="emails-templates-editor" class="hidden">
                            <p class="text-muted" id="emails-templates-description"></p>

                            <div class="alert alert-warning hidden" id="emails-templates-shared-subject"></div>
                            <div class="alert alert-info hidden" id="emails-templates-inherited"></div>

                            <div class="form-group" id="emails-templates-subject-group">
                                <label for="emails-templates-subject"><?php echo $lang->get('emails_templates_subject'); ?></label>
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend hidden" id="emails-templates-subject-prefix-group">
                                        <span class="input-group-text" id="emails-templates-subject-prefix"></span>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" id="emails-templates-subject">
                                </div>
                                <small class="form-text text-muted"><?php echo $lang->get('emails_templates_subject_tip'); ?></small>
                            </div>

                            <div class="form-group">
                                <label for="emails-templates-body"><?php echo $lang->get('emails_templates_body'); ?></label>
                                <div id="emails-templates-body"></div>
                            </div>

                            <div class="form-group">
                                <label><?php echo $lang->get('emails_templates_tokens'); ?></label>
                                <div id="emails-templates-tokens"></div>
                                <small class="form-text text-muted"><?php echo $lang->get('emails_templates_tokens_tip'); ?></small>
                            </div>

                            <div class="form-group">
                                <a href="#" id="emails-templates-show-default" class="text-muted">
                                    <i class="fas fa-angle-right mr-1"></i><?php echo $lang->get('emails_templates_show_default'); ?>
                                </a>
                                <div class="hidden mt-2" id="emails-templates-default">
                                    <div class="callout callout-secondary">
                                        <strong id="emails-templates-default-subject" class="d-block mb-2"></strong>
                                        <div id="emails-templates-default-body"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-muted mb-3 hidden" id="emails-templates-audit"></div>

                            <button type="button" class="btn btn-primary btn-sm mr-2" id="emails-templates-save">
                                <i class="fas fa-save mr-2"></i><?php echo $lang->get('save'); ?>
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" id="emails-templates-reset">
                                <i class="fas fa-rotate-left mr-2"></i><?php echo $lang->get('emails_templates_reset'); ?>
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

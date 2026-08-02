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
 * @file      search.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('search') === false) {
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

// Feature gates. A facet section is not rendered at all when its feature is
// off — the server strips the matching filter too, so hiding it here is a
// presentation choice, never the security boundary.
$featureClassification = (int) ($SETTINGS['data_classification_enabled'] ?? 0) === 1;
$featureHealth = (int) ($SETTINGS['security_dashboard_enabled'] ?? 0) === 1;
$featureRotation = (int) ($SETTINGS['leaver_risk_enabled'] ?? 0) === 1
    || (int) ($SETTINGS['rotation_tracking_enabled'] ?? 0) === 1;
$featureCustomFields = (int) ($SETTINGS['item_extra_fields'] ?? 0) === 1;
$featureFavourites = (int) ($SETTINGS['enable_favourites'] ?? 0) === 1;

?>

<style>
    /* The facet panel is deliberately narrow: keep every control inside it. */
    #search-filters-panel .form-control {
        min-width: 0;
    }
    #search-filters-panel .custom-control-label {
        font-size: .875rem;
        line-height: 1.35;
    }
</style>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark"><i class="fas fa-search mr-2"></i><?php echo $lang->get('find'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<!-- MASS OPERATION -->
<div class="card card-warning m-2 hidden" id="dialog-mass-operation">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-bug mr-2"></i>
            <?php echo $lang->get('mass_operation'); ?>
        </h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-sm-12 col-md-12" id="dialog-mass-operation-html">

            </div>
        </div>
    </div>
    <div class="card-footer">
        <button class="btn btn-primary mr-2" id="dialog-mass-operation-button"><?php echo $lang->get('perform'); ?></button>
        <button class="btn btn-default float-right close-element"><?php echo $lang->get('cancel'); ?></button>
    </div>
</div>
<!-- /.MASS OPERATION -->

<!-- Main content -->
<section class="content">
    <!-- SEARCH BAR -->
    <div class="row">
        <div class="col-12">
            <div class="card card-outline card-primary">
                <div class="card-body pb-2">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" class="form-control" id="search-term"
                            placeholder="<?php echo $lang->get('search_term_placeholder'); ?>"
                            aria-label="<?php echo $lang->get('find'); ?>">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="search-toggle-filters"
                                aria-expanded="false" aria-controls="search-filters-panel">
                                <i class="fas fa-sliders-h mr-1"></i><?php echo $lang->get('search_filters'); ?>
                                <span class="badge badge-primary ml-1 hidden" id="search-filters-count">0</span>
                            </button>
                        </div>
                    </div>
                    <!-- ACTIVE FILTER CHIPS -->
                    <div class="mt-2 hidden" id="search-chips-row">
                        <span id="search-chips"></span>
                        <button type="button" class="btn btn-link btn-sm text-danger" id="search-clear-all">
                            <?php echo $lang->get('search_clear_all'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- FILTER PANEL -->
        <div class="col-md-3 col-xl-2 hidden" id="search-filters-panel">
            <div class="card">
                <div class="card-body p-2" id="search-filters-body">

                    <!-- Search in -->
                    <div class="search-facet-group">
                        <h6 class="text-muted text-uppercase small mb-2"><?php echo $lang->get('search_search_in'); ?></h6>
                        <?php foreach (['label' => 'label', 'login' => 'login', 'url' => 'url', 'tags' => 'tags', 'description' => 'description'] as $key => $langKey) : ?>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input search-field-cb" id="search-field-<?php echo $key; ?>"
                                    value="<?php echo $key; ?>" <?php echo $key === 'description' ? '' : 'checked'; ?>>
                                <label class="custom-control-label" for="search-field-<?php echo $key; ?>"><?php echo $lang->get($langKey); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($featureClassification === true) : ?>
                    <!-- Classification -->
                    <div class="search-facet-group mt-3">
                        <h6 class="text-muted text-uppercase small mb-2"><?php echo $lang->get('search_facet_classification'); ?></h6>
                        <?php foreach ([0 => 'unclassified', 1 => 'public', 2 => 'internal', 3 => 'confidential', 4 => 'restricted'] as $level => $slug) : ?>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input search-facet" data-facet="classification"
                                    id="search-cls-<?php echo $level; ?>" value="<?php echo $level; ?>">
                                <label class="custom-control-label" for="search-cls-<?php echo $level; ?>"><?php echo $lang->get('classification_level_' . $slug); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($featureHealth === true) : ?>
                    <!-- Security health -->
                    <div class="search-facet-group mt-3">
                        <h6 class="text-muted text-uppercase small mb-2">
                            <?php echo $lang->get('search_facet_health'); ?>
                            <i class="fas fa-info-circle text-muted ml-1" data-toggle="tooltip" data-placement="top"
                                title="<?php echo $lang->get('search_scan_required'); ?>"></i>
                        </h6>
                        <?php foreach (['weak', 'breached', 'overdue', 'no_expiry', 'overshared'] as $flag) : ?>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input search-facet" data-facet="health"
                                    id="search-health-<?php echo $flag; ?>" value="<?php echo $flag; ?>">
                                <label class="custom-control-label" for="search-health-<?php echo $flag; ?>"><?php echo $lang->get('security_dashboard_' . $flag); ?></label>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach (['reused', 'orphaned'] as $flag) : ?>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input search-facet" data-facet="health_scan"
                                    id="search-health-<?php echo $flag; ?>" value="<?php echo $flag; ?>">
                                <label class="custom-control-label" for="search-health-<?php echo $flag; ?>"><?php echo $lang->get('security_dashboard_' . $flag); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Attachments -->
                    <div class="search-facet-group mt-3">
                        <h6 class="text-muted text-uppercase small mb-2"><?php echo $lang->get('search_facet_attachments'); ?></h6>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input search-facet-bool" data-facet="attachment_has"
                                id="search-attachment-has">
                            <label class="custom-control-label" for="search-attachment-has"><?php echo $lang->get('search_attachment_has'); ?></label>
                        </div>
                        <input type="text" class="form-control form-control-sm mt-2 search-facet-text" data-facet="attachment_name"
                            id="search-attachment-name" placeholder="<?php echo $lang->get('search_attachment_name'); ?>">
                        <input type="text" class="form-control form-control-sm mt-2 search-facet-csv" data-facet="attachment_extensions"
                            id="search-attachment-ext" placeholder="<?php echo $lang->get('search_attachment_extension'); ?>">
                    </div>

                    <!-- Dates -->
                    <div class="search-facet-group mt-3">
                        <h6 class="text-muted text-uppercase small mb-2"><?php echo $lang->get('search_facet_dates'); ?></h6>
                        <label class="small mb-0" for="search-created-from"><?php echo $lang->get('search_created_between'); ?></label>
                        <input type="date" class="form-control form-control-sm search-facet-date" data-facet="created_from" id="search-created-from">
                        <input type="date" class="form-control form-control-sm mt-1 search-facet-date" data-facet="created_to" id="search-created-to">
                        <label class="small mb-0 mt-2" for="search-updated-from"><?php echo $lang->get('search_updated_between'); ?></label>
                        <input type="date" class="form-control form-control-sm search-facet-date" data-facet="updated_from" id="search-updated-from">
                        <input type="date" class="form-control form-control-sm mt-1 search-facet-date" data-facet="updated_to" id="search-updated-to">
                        <?php if ($featureRotation === true) : ?>
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input search-facet" data-facet="rotation_status"
                                    id="search-rotation-pending" value="pending">
                                <label class="custom-control-label" for="search-rotation-pending"><?php echo $lang->get('search_rotation_flagged'); ?></label>
                            </div>
                        <?php endif; ?>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input search-facet-bool" data-facet="rotation_auto"
                                id="search-rotation-auto">
                            <label class="custom-control-label" for="search-rotation-auto"><?php echo $lang->get('search_rotation_auto'); ?></label>
                        </div>
                    </div>

                    <!-- Content & scope -->
                    <div class="search-facet-group mt-3">
                        <h6 class="text-muted text-uppercase small mb-2"><?php echo $lang->get('search_facet_content'); ?></h6>
                        <select class="form-control form-control-sm search-facet-select" data-facet="tags" id="search-tags" multiple size="4">
                        </select>
                        <select class="form-control form-control-sm mt-2 search-facet-single" data-facet="scope_perso" id="search-scope-perso">
                            <option value=""><?php echo $lang->get('search_scope_any'); ?></option>
                            <option value="shared"><?php echo $lang->get('search_scope_shared'); ?></option>
                            <option value="personal"><?php echo $lang->get('search_scope_personal'); ?></option>
                        </select>
                        <?php if ($featureFavourites === true) : ?>
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input search-facet-bool" data-facet="favourites"
                                    id="search-favourites">
                                <label class="custom-control-label" for="search-favourites"><?php echo $lang->get('search_favourites'); ?></label>
                            </div>
                        <?php endif; ?>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input search-facet-bool" data-facet="recent"
                                id="search-recent">
                            <label class="custom-control-label" for="search-recent"><?php echo $lang->get('search_recent'); ?></label>
                        </div>
                        <?php if ($featureCustomFields === true) : ?>
                            <select class="form-control form-control-sm mt-2 search-facet-single" data-facet="custom_field_id" id="search-custom-field">
                                <option value=""><?php echo $lang->get('search_custom_field_any'); ?></option>
                            </select>
                            <input type="text" class="form-control form-control-sm mt-1 search-facet-text" data-facet="custom_field_value"
                                id="search-custom-field-value" placeholder="<?php echo $lang->get('search_custom_field_value'); ?>">
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

        <!-- RESULTS -->
        <div class="col-12" id="search-results-column">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mr-2" id="search-select"></h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <div class="alert alert-info" id="search-empty-hint">
                        <i class="fas fa-info-circle mr-2"></i><?php echo $lang->get('search_no_criteria'); ?>
                    </div>
                    <table id="search-results-items" class="table table-bordered table-striped" style="width:100%">
                        <thead>
                            <tr>
                                <th></th>
                                <th><?php echo $lang->get('label'); ?></th>
                                <th><?php echo $lang->get('login'); ?></th>
                                <th><?php echo $lang->get('description'); ?></th>
                                <th><?php echo $lang->get('tags'); ?></th>
                                <th><?php echo $lang->get('url'); ?></th>
                                <th><?php echo $lang->get('group'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

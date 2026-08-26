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

    /* ---- Folder results ----------------------------------------------- */

    .search-folder-result {
        display: flex;
        align-items: center;
        gap: .75rem;
    }
    .search-folder-icon {
        width: 2.25rem;
        height: 2.25rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border-radius: .3rem;
        color: #856404;
        background-color: rgba(255, 193, 7, .18);
    }
    .dark-mode .search-folder-icon {
        color: #ffd454;
        background-color: rgba(255, 193, 7, .14);
    }
    .search-folder-content {
        min-width: 0;
        flex: 1 1 auto;
    }
    .search-folder-title,
    .search-folder-path {
        display: block;
        overflow-wrap: anywhere;
    }
    .search-folder-title {
        font-weight: 600;
    }

    /* ---- Result rows -------------------------------------------------- */

    /* The whole row opens the detail modal. */
    #search-results-items tbody tr {
        cursor: pointer;
    }
    #search-results-items tbody tr:hover {
        background-color: rgba(0, 123, 255, .06);
    }
    .dark-mode #search-results-items tbody tr:hover {
        background-color: rgba(255, 255, 255, .05);
    }
    /* The label is a button so the detail modal has a keyboard entry point;
       it must still read as plain text in the table. */
    .search-label-btn {
        border: 0;
        background: transparent;
        padding: 0;
        color: inherit;
        font: inherit;
        text-align: left;
    }
    .search-label-btn:hover,
    .search-label-btn:focus {
        text-decoration: underline;
    }
    #search-results-items td.search-col-select {
        width: 34px;
        text-align: center;
    }

    /* The actions do not deserve a column of their own: a fixed one wastes
       width on every row and the buttons spill out of it. The cell is reduced
       to nothing and its content taken out of the flow, so the buttons float
       over the right edge of the row they belong to. */
    #search-results-items td.search-col-actions {
        width: 1px;
        padding: 0;
        position: relative;
        border-left: 0;
    }

    /* Quiet until the row is hovered, so the table stays readable. Keyboard
       focus reveals them too, otherwise they would be unreachable without a
       mouse. pointer-events follows the opacity: an invisible button must not
       swallow a click meant for the row underneath. */
    .search-row-actions {
        position: absolute;
        top: 50%;
        right: .5rem;
        transform: translateY(-50%);
        z-index: 2;
        display: inline-flex;
        gap: .25rem;
        padding: .15rem .25rem;
        border-radius: .25rem;
        background-color: #fff;
        box-shadow: 0 1px 5px rgba(0, 0, 0, .2);
        opacity: 0;
        pointer-events: none;
        transition: opacity .12s ease-in-out;
    }
    #search-results-items tbody tr:hover .search-row-actions,
    .search-row-actions:focus-within {
        opacity: 1;
        pointer-events: auto;
    }
    .dark-mode .search-row-actions {
        background-color: #454d55;
        box-shadow: 0 1px 5px rgba(0, 0, 0, .5);
    }
    .search-row-action {
        border: 0;
        background: transparent;
        color: #6c757d;
        padding: .2rem .4rem;
        border-radius: .2rem;
        line-height: 1;
    }
    .search-row-action:hover,
    .search-row-action:focus {
        color: #007bff;
        background-color: rgba(0, 123, 255, .12);
    }
    .dark-mode .search-row-action {
        color: #adb5bd;
    }
    .dark-mode .search-row-action:hover,
    .dark-mode .search-row-action:focus {
        color: #4dabf7;
        background-color: rgba(77, 171, 247, .16);
    }

    /* Touch devices have no hover: keep the actions visible there. */
    @media (hover: none) {
        .search-row-actions {
            opacity: 1;
            pointer-events: auto;
        }
    }

    /* ---- Detail modal -------------------------------------------------- */

    #search-item-modal .search-item-icon {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: .35rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(0, 123, 255, .12);
        color: #007bff;
        flex: 0 0 auto;
    }
    .dark-mode #search-item-modal .search-item-icon {
        background-color: rgba(77, 171, 247, .16);
        color: #4dabf7;
    }
    /* Without this a long label stretches the flex item instead of wrapping. */
    #search-item-modal .search-item-head {
        min-width: 0;
    }
    #search-item-modal .search-item-path {
        font-size: .8125rem;
    }
    /* Two-column definition layout: label column fixed so every value aligns. */
    #search-item-modal .search-item-field {
        display: flex;
        align-items: flex-start;
        padding: .4rem 0;
    }
    #search-item-modal .search-item-field + .search-item-field {
        border-top: 1px solid rgba(0, 0, 0, .05);
    }
    .dark-mode #search-item-modal .search-item-field + .search-item-field {
        border-top-color: rgba(255, 255, 255, .08);
    }
    #search-item-modal .search-item-field-label {
        flex: 0 0 8.5rem;
        color: #6c757d;
        font-size: .8125rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        padding-top: .15rem;
    }
    #search-item-modal .search-item-field-value {
        flex: 1 1 auto;
        min-width: 0;
        word-break: break-word;
    }
    #search-item-modal .search-item-field-actions {
        flex: 0 0 auto;
        white-space: nowrap;
    }
    #search-item-modal .search-item-description {
        max-height: 12rem;
        overflow-y: auto;
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
                            <button class="btn btn-outline-secondary" type="button" id="search-reset"
                                title="<?php echo $lang->get('search_reset'); ?>"
                                aria-label="<?php echo $lang->get('search_reset'); ?>">
                                <i class="fas fa-undo-alt" aria-hidden="true"></i>
                            </button>
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
                        <?php foreach (['label' => 'label', 'login' => 'login', 'url' => 'url', 'tags' => 'tags', 'folder' => 'folder', 'description' => 'description'] as $key => $langKey) : ?>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input search-field-cb" id="search-field-<?php echo $key; ?>"
                                    value="<?php echo $key; ?>"
                                    data-default-checked="<?php echo $key === 'description' ? 'false' : 'true'; ?>"
                                    <?php echo $key === 'description' ? '' : 'checked'; ?>>
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
                        <select class="form-control form-control-sm search-facet-single" data-facet="folder" id="search-folder"
                            aria-label="<?php echo $lang->get('search_folder_filter'); ?>">
                            <option value=""><?php echo $lang->get('search_folder_any'); ?></option>
                        </select>
                        <select class="form-control form-control-sm mt-2 search-facet-select" data-facet="tags" id="search-tags" multiple size="4">
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
                    <div class="hidden mb-4" id="search-folder-results" aria-live="polite">
                        <h4 class="h6 text-muted text-uppercase mb-2">
                            <i class="fa-solid fa-folder text-warning mr-2" aria-hidden="true"></i><?php echo $lang->get('folders'); ?>
                            <span class="badge badge-secondary ml-1" id="search-folder-count">0</span>
                        </h4>
                        <div class="list-group" id="search-folder-list"></div>
                        <p class="small text-muted mt-2 mb-0 hidden" id="search-folder-more">
                            <?php echo $lang->get('search_folder_results_more'); ?>
                        </p>
                    </div>
                    <h4 class="h6 text-muted text-uppercase mb-2">
                        <i class="fa-solid fa-key text-primary mr-2" aria-hidden="true"></i><?php echo $lang->get('items'); ?>
                    </h4>
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
                                <th></th>
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

<!-- ITEM DETAIL MODAL
     Replaces the former DataTables child row: that one collided with the
     Responsive extension (both drive row.child()) and pushed every row below
     it out of place. -->
<div class="modal fade" id="search-item-modal" tabindex="-1" role="dialog"
    aria-labelledby="search-item-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">

            <div class="modal-header align-items-start">
                <span class="search-item-icon mr-3"><i class="fa-solid fa-key" id="search-item-glyph" aria-hidden="true"></i></span>
                <div class="flex-grow-1 search-item-head">
                    <h5 class="modal-title mb-0 d-inline-block mr-2" id="search-item-modal-label"></h5><span id="search-item-badge"></span>
                    <div class="text-muted search-item-path" id="search-item-path"></div>
                </div>
                <button type="button" class="close ml-2" data-dismiss="modal"
                    aria-label="<?php echo $lang->get('close'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body" id="search-item-modal-body">

                <!-- Loading state: the payload needs a server round-trip and a
                     decryption, so show the shape of the answer meanwhile. -->
                <div id="search-item-loading">
                    <div class="search-item-field">
                        <div class="search-item-field-label"><span class="skeleton-line skeleton-sm"></span></div>
                        <div class="search-item-field-value"><span class="skeleton-line skeleton-lg"></span></div>
                    </div>
                    <div class="search-item-field">
                        <div class="search-item-field-label"><span class="skeleton-line skeleton-sm"></span></div>
                        <div class="search-item-field-value"><span class="skeleton-line skeleton-md"></span></div>
                    </div>
                    <div class="search-item-field">
                        <div class="search-item-field-label"><span class="skeleton-line skeleton-sm"></span></div>
                        <div class="search-item-field-value"><span class="skeleton-line skeleton-xl"></span></div>
                    </div>
                </div>

                <!-- Refusal / error state (expired item, no rights, ...) -->
                <div class="alert alert-warning hidden" id="search-item-message"></div>

                <div class="hidden" id="search-item-content">

                    <div class="search-item-field hidden" id="search-item-login-row">
                        <div class="search-item-field-label"><?php echo $lang->get('index_login'); ?></div>
                        <div class="search-item-field-value" id="search-item-login"></div>
                        <div class="search-item-field-actions">
                            <button type="button" class="btn btn-sm btn-outline-secondary infotip"
                                id="search-item-copy-login"
                                title="<?php echo $lang->get('favorites_copy_login'); ?>">
                                <i class="fa-regular fa-clone" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="search-item-field" id="search-item-pwd-row">
                        <div class="search-item-field-label"><?php echo $lang->get('pw'); ?></div>
                        <!-- Filled by the JS with a span carrying the per-item id
                             (pwd-show_<id>) the existing .btn-show-pwd reveal and
                             long-press handlers expect. -->
                        <div class="search-item-field-value" id="search-item-pwd-holder"></div>
                        <div class="search-item-field-actions">
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-show-pwd infotip"
                                id="search-item-show-pwd" title="<?php echo $lang->get('mask_pw'); ?>">
                                <i class="fa-regular fa-eye pwd-show-spinner" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary infotip ml-1"
                                id="search-item-copy-pwd"
                                title="<?php echo $lang->get('favorites_copy_password'); ?>">
                                <i class="fa-regular fa-clone" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="search-item-field hidden" id="search-item-url-row">
                        <div class="search-item-field-label"><?php echo $lang->get('url'); ?></div>
                        <div class="search-item-field-value" id="search-item-url"></div>
                        <div class="search-item-field-actions">
                            <a class="btn btn-sm btn-outline-secondary infotip" id="search-item-open-url"
                                href="#" target="_blank" rel="noopener noreferrer"
                                title="<?php echo $lang->get('open_url_link'); ?>">
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary infotip ml-1"
                                id="search-item-copy-url"
                                title="<?php echo $lang->get('copy'); ?>">
                                <i class="fa-regular fa-clone" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="search-item-field hidden" id="search-item-tags-row">
                        <div class="search-item-field-label"><?php echo $lang->get('tags'); ?></div>
                        <div class="search-item-field-value" id="search-item-tags"></div>
                    </div>

                    <div class="search-item-field hidden" id="search-item-description-row">
                        <div class="search-item-field-label"><?php echo $lang->get('description'); ?></div>
                        <div class="search-item-field-value search-item-description" id="search-item-description"></div>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary mr-auto" id="search-item-open">
                    <i class="fa-solid fa-arrow-up-right-from-square mr-2" aria-hidden="true"></i><?php echo $lang->get('favorites_open_item'); ?>
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $lang->get('close'); ?></button>
            </div>

        </div>
    </div>
</div>

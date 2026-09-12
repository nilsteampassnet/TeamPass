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
 * @file      utilities.logs.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('utilities.logs') === false) {
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

// KB feature toggle
// Knowledge base logs are an administrator only maintenance area (see kb.queries.php), while the
// logs page itself is also granted to managers.
$isAdmin = $session->has('user-admin') && (int) $session->get('user-admin') === 1;
$kbEnabled = isset($SETTINGS['enable_kb']) === true && (int) $SETTINGS['enable_kb'] === 1 && $isAdmin === true;

// The facet panel is rendered once and its groups are shown or hidden by the selected source.
// Same pattern as the search page's feature gates, driven by the source instead of a setting.
require_once __DIR__ . '/../sources/logs_filter_logic.php';

// Facet options are listed in the reader's alphabetical order, not in the order the codes happen
// to be declared in: fourteen actions are too many to scan otherwise.
$logTypeLabels = [];
foreach (logsAllowedSystemTypes() as $type) {
    $typeLangKey = 'logs_type_' . $type;
    $logTypeLabels[$type] = (string) $lang->get($typeLangKey);
}
$logActionLabels = [];
foreach (logsAllowedItemActions() as $logAction) {
    $logActionLabels[$logAction] = (string) $lang->get($logAction);
}
$logSortedTypes = logsSortByLabel(array_keys($logTypeLabels), $logTypeLabels);
$logSortedActions = logsSortByLabel(logsAllowedItemActions(), $logActionLabels);


?>

<style>
    /* The facet panel is deliberately narrow: keep every control inside it. */
    #logs-filters-panel .form-control {
        min-width: 0;
    }
    #logs-filters-panel .custom-control-label {
        font-size: .875rem;
        line-height: 1.35;
    }
    /* A facet, or a single option, that does not apply to the selected source is removed from
       the flow, not just dimmed: a disabled control the administrator can still read reads as a
       bug. */
    #logs-filters-panel .logs-facet-group.hidden,
    #logs-filters-panel .logs-facet-option.hidden {
        display: none;
    }
    #table-logs tbody td {
        vertical-align: middle;
    }
</style>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-history mr-2"></i><?php echo $lang->get('logs'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<!-- Main content -->
<div class="content">
    <div class="container-fluid">

        <ul class="nav nav-tabs mb-2" id="logs-main-tabs">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#journals" role="tab"
                    aria-controls="journals" aria-selected="true"><?php echo $lang->get('logs_journals'); ?></a>
            </li>
            <?php if ($isAdmin === true) { ?>
            <li class="nav-item">
                <a class="nav-link" id="authentication-lockouts-tab" data-toggle="tab" href="#authentication-lockouts"
                    role="tab" aria-controls="authentication-lockouts" aria-selected="false"><?php echo $lang->get('authentication_lockouts'); ?></a>
            </li>
            <?php } ?>
        </ul>

        <div class="tab-content" id="logs-main-tab-content">
            <div class="tab-pane fade show active" id="journals" role="tabpanel" aria-labelledby="journals-tab">

                <!-- SEARCH BAR -->
                <div class="row">
                    <div class="col-12">
                        <div class="card card-outline card-primary">
                            <div class="card-body pb-2">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="logs-term"
                                        placeholder="<?php echo $lang->get('logs_search_placeholder'); ?>"
                                        aria-label="<?php echo $lang->get('find'); ?>">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" id="logs-reset"
                                            title="<?php echo $lang->get('search_reset'); ?>"
                                            aria-label="<?php echo $lang->get('search_reset'); ?>">
                                            <i class="fas fa-undo-alt" aria-hidden="true"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary" type="button" id="logs-toggle-filters"
                                            aria-expanded="false" aria-controls="logs-filters-panel">
                                            <i class="fas fa-sliders-h mr-1"></i><?php echo $lang->get('search_filters'); ?>
                                            <span class="badge badge-primary ml-1 hidden" id="logs-filters-count">0</span>
                                        </button>
                                    </div>
                                </div>
                                <!-- ACTIVE FILTER CHIPS -->
                                <div class="mt-2 hidden" id="logs-chips-row">
                                    <span id="logs-chips"></span>
                                    <button type="button" class="btn btn-link btn-sm text-danger" id="logs-clear-all">
                                        <?php echo $lang->get('search_clear_all'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- FILTER PANEL -->
                    <div class="col-md-3 col-xl-2 hidden" id="logs-filters-panel">
                        <div class="card">
                            <div class="card-body p-2">

                                <!-- Source: a radio, never a checkbox. The column set depends on it,
                                     so two sources at once would have no table to render into. -->
                                <div class="logs-facet-group">
                                    <h6 class="text-muted text-uppercase small mb-2"><?php echo $lang->get('logs_source'); ?></h6>
                                    <?php foreach (['system' => $lang->get('logs_source_system'), 'items' => $lang->get('logs_source_items')] + ($kbEnabled === true ? ['kb' => $lang->get('logs_source_kb')] : []) as $value => $label) : ?>
                                        <div class="custom-control custom-radio">
                                            <input type="radio" name="logs-source" class="custom-control-input logs-source"
                                                id="logs-source-<?php echo $value; ?>" value="<?php echo $value; ?>"
                                                <?php echo $value === 'system' ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="logs-source-<?php echo $value; ?>"><?php echo $label; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Type: what used to be four tabs -->
                                <div class="logs-facet-group mt-3" data-source="system">
                                    <h6 class="text-muted text-uppercase small mb-2"><?php echo $lang->get('logs_facet_type'); ?></h6>
                                    <?php foreach ($logSortedTypes as $value) : ?>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input logs-facet" data-facet="types"
                                                id="logs-type-<?php echo $value; ?>" value="<?php echo $value; ?>">
                                            <label class="custom-control-label" for="logs-type-<?php echo $value; ?>"><?php echo $logTypeLabels[$value]; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Action: one list for both action-bearing sources. Two lists would
                                     repeat the five actions the knowledge base shares with the items,
                                     under the same heading; each option declares the sources it
                                     belongs to instead. The former Copy tab is one value here. -->
                                <div class="logs-facet-group mt-3" data-source="items kb">
                                    <h6 class="text-muted text-uppercase small mb-2"><?php echo $lang->get('logs_facet_action'); ?></h6>
                                    <?php foreach ($logSortedActions as $action) : ?>
                                        <?php $actionSources = 'items' . (in_array($action, logsAllowedKbActions(), true) === true ? ' kb' : ''); ?>
                                        <div class="custom-control custom-checkbox logs-facet-option" data-source="<?php echo $actionSources; ?>">
                                            <input type="checkbox" class="custom-control-input logs-facet" data-facet="actions"
                                                id="logs-action-<?php echo $action; ?>" value="<?php echo $action; ?>">
                                            <label class="custom-control-label" for="logs-action-<?php echo $action; ?>"><?php echo $logActionLabels[$action]; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Dates -->
                                <div class="logs-facet-group mt-3">
                                    <h6 class="text-muted text-uppercase small mb-2"><?php echo $lang->get('date_range'); ?></h6>
                                    <label class="small mb-0" for="logs-date-from"><?php echo $lang->get('from'); ?></label>
                                    <input type="date" class="form-control form-control-sm logs-facet-date" data-facet="date_from" id="logs-date-from">
                                    <label class="small mb-0 mt-1" for="logs-date-to"><?php echo $lang->get('to'); ?></label>
                                    <input type="date" class="form-control form-control-sm logs-facet-date" data-facet="date_to" id="logs-date-to">
                                </div>

                                <!-- User: loaded on demand. Rendering every account inline made the
                                     page weigh proportionally to the number of users. -->
                                <div class="logs-facet-group mt-3">
                                    <h6 class="text-muted text-uppercase small mb-2"><?php echo $lang->get('user'); ?></h6>
                                    <select class="form-control form-control-sm logs-facet-single" data-facet="user_id" id="logs-user" style="width:100%;"
                                        aria-label="<?php echo $lang->get('user'); ?>">
                                        <option value=""></option>
                                    </select>
                                </div>

                                <!-- Folder and personal scope: item logs only -->
                                <div class="logs-facet-group mt-3" data-source="items">
                                    <h6 class="text-muted text-uppercase small mb-2"><?php echo $lang->get('folder'); ?></h6>
                                    <select class="form-control form-control-sm logs-facet-single" data-facet="folder_id" id="logs-folder" style="width:100%;"
                                        aria-label="<?php echo $lang->get('folder'); ?>">
                                        <option value=""></option>
                                    </select>
                                    <select class="form-control form-control-sm mt-2 logs-facet-single" data-facet="scope" id="logs-scope">
                                        <option value=""><?php echo $lang->get('logs_scope_any'); ?></option>
                                        <option value="shared"><?php echo $lang->get('logs_scope_shared'); ?></option>
                                        <option value="personal"><?php echo $lang->get('logs_scope_personal'); ?></option>
                                    </select>
                                </div>

                                <!-- Channel -->
                                <div class="logs-facet-group mt-3" data-source="system items">
                                    <h6 class="text-muted text-uppercase small mb-2"><?php echo $lang->get('logs_facet_channel'); ?></h6>
                                    <select class="form-control form-control-sm logs-facet-single" data-facet="channel" id="logs-channel">
                                        <option value=""><?php echo $lang->get('all'); ?></option>
                                        <option value="web"><?php echo $lang->get('logs_channel_web'); ?></option>
                                        <option value="api"><?php echo $lang->get('logs_channel_api'); ?></option>
                                    </select>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- RESULTS -->
                    <div class="col-12" id="logs-results-column">
                        <div class="card">
                            <div class="card-body">
                                <table class="table table-striped nowrap table-responsive-sm" id="table-logs" style="width:100%;">
                                    <thead>
                                        <tr></tr>
                                    </thead>
                                </table>
                            </div>

                            <?php if ($isAdmin === true) { ?>
                            <div id="logs-purge-footer" class="card-footer">
                                <h5><i class="fas fa-broom mr-2"></i><?php echo $lang->get('logs_purge_title'); ?></h5>
                                <p class="text-muted mb-2" id="logs-purge-help"><?php echo $lang->get('logs_purge_help_filtered'); ?></p>

                                <!-- What the purge will delete, stated from the active filters, so the
                                     scope announced is the scope applied. -->
                                <div class="alert alert-secondary mb-2" id="logs-purge-scope"></div>
                                <div class="alert alert-warning hidden mb-2" id="logs-purge-blocked"></div>

                                <div class="form-group mt-2 group-confirm-purge hidden">
                                    <input type="checkbox" class="form-check-input form-item-control" id="checkbox-purge-confirm">
                                    <label class="form-check-label ml-2" for="checkbox-purge-confirm">
                                        <?php echo $lang->get('logs_purge_confirm_filtered'); ?>
                                    </label>
                                </div>
                                <div class="form-group mt-2 group-confirm-purge hidden">
                                    <button class="btn btn-danger" id="button-perform-purge"><?php echo $lang->get('logs_purge_submit'); ?></button>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($isAdmin === true) { ?>
            <!-- Not a journal: live state with unlock actions, so it stays out of the facet system. -->
            <div class="tab-pane fade" id="authentication-lockouts" role="tabpanel" aria-labelledby="authentication-lockouts-tab">
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                            <?php echo $lang->get('authentication_lockouts_tip'); ?>
                        </div>
                        <table class="table table-striped nowrap table-responsive-sm" id="table-authentication-lockouts" style="width:100%;">
                            <thead>
                                <tr>
                                    <th><?php echo $lang->get('authentication_lockout_scope'); ?></th>
                                    <th><?php echo $lang->get('authentication_lockout_target'); ?></th>
                                    <th><?php echo $lang->get('user'); ?></th>
                                    <th><?php echo $lang->get('authentication_lockout_failures'); ?></th>
                                    <th><?php echo $lang->get('authentication_lockout_first_failure'); ?></th>
                                    <th><?php echo $lang->get('authentication_lockout_last_failure'); ?></th>
                                    <th><?php echo $lang->get('authentication_lockout_until'); ?></th>
                                    <th><?php echo $lang->get('action'); ?></th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

    </div><!-- /.container-fluid -->
</div>
<!-- /.content -->

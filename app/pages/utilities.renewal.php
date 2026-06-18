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
 * @file      utilities.renewal.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('utilities.renewal') === false) {
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


?>

<style>
    #renewal-page .renewal-card {
        border-radius: .35rem;
        overflow: hidden;
    }

    #renewal-page .renewal-toolbar {
        align-items: center;
        background-color: #ffffff;
        border-bottom: 1px solid #e5e9ef;
        display: flex;
        gap: 1rem;
        justify-content: space-between;
        padding: 1rem;
    }

    #renewal-page .renewal-info {
        align-items: center;
        color: #3f4b57;
        display: flex;
        min-width: 0;
    }

    #renewal-page .renewal-info-icon {
        align-items: center;
        background-color: #e8f6f9;
        border-radius: 50%;
        color: #138496;
        display: inline-flex;
        flex: 0 0 32px;
        height: 32px;
        justify-content: center;
        margin-right: .75rem;
        width: 32px;
    }

    #renewal-page .renewal-date-filter {
        flex: 0 0 360px;
        max-width: 100%;
    }

    #renewal-page .renewal-date-label {
        color: #3f4b57;
        font-weight: 600;
        margin-bottom: .35rem;
    }

    #renewal-page .renewal-date-filter .input-group-text,
    #renewal-page .renewal-date-filter .btn,
    #renewal-page .renewal-date-filter .form-control {
        height: calc(1.8125rem + 2px);
    }

    #renewal-page .renewal-date-filter .btn {
        align-items: center;
        display: inline-flex;
        justify-content: center;
        min-width: 36px;
    }

    #renewal-page .renewal-table-section {
        padding: 1rem;
    }

    #renewal-page .renewal-table-controls {
        background-color: #f8fafc;
        border: 1px solid #e5e9ef;
        border-bottom: 0;
        border-radius: .35rem .35rem 0 0;
        margin: 0;
        padding: .75rem 1rem;
    }

    #renewal-page .renewal-table-shell {
        border: 1px solid #e5e9ef;
        border-radius: 0;
        overflow: hidden;
    }

    #renewal-page .renewal-table-footer {
        background-color: #ffffff;
        border: 1px solid #e5e9ef;
        border-radius: 0 0 .35rem .35rem;
        border-top: 0;
        margin: 0;
        padding: .75rem 1rem;
    }

    #renewal-page #table-renewal {
        margin-bottom: 0 !important;
    }

    #renewal-page #table-renewal thead th {
        background-color: #f3f5f7;
        border-bottom: 1px solid #d9dee5;
        border-top: 0;
        color: #343a40;
        font-weight: 600;
        padding: .75rem;
    }

    #renewal-page #table-renewal tbody td {
        padding: .85rem .75rem;
        vertical-align: middle;
    }

    #renewal-page .dataTables_length label,
    #renewal-page .dataTables_filter label {
        align-items: center;
        color: #3f4b57;
        display: flex;
        font-weight: 600;
        margin-bottom: 0;
    }

    #renewal-page .dataTables_length select,
    #renewal-page .dataTables_filter input {
        border: 1px solid #ced4da;
        border-radius: .2rem;
        display: inline-block;
        font-size: .875rem;
        height: calc(1.8125rem + 2px);
        padding: .25rem .5rem;
        width: auto !important;
    }

    #renewal-page .dataTables_length select {
        margin: 0 .5rem;
        min-width: 72px;
    }

    #renewal-page .dataTables_filter {
        text-align: right;
    }

    #renewal-page .dataTables_filter label {
        justify-content: flex-end;
    }

    #renewal-page .dataTables_filter input {
        margin-left: .5rem;
        min-width: 220px;
    }

    #renewal-page .dataTables_info {
        color: #6c757d;
        padding-top: .25rem;
    }

    #renewal-page .dataTables_paginate {
        text-align: right;
    }

    #renewal-page .dataTables_paginate .pagination {
        justify-content: flex-end;
        margin-bottom: 0;
    }

    #renewal-page .dataTables_empty {
        background-color: #fbfcfd;
        color: #6c757d;
        padding: 1.5rem .75rem !important;
    }

    @media (max-width: 991.98px) {
        #renewal-page .renewal-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        #renewal-page .renewal-date-filter {
            flex-basis: auto;
        }
    }

    @media (max-width: 767.98px) {
        #renewal-page .dataTables_filter {
            margin-top: .75rem;
            text-align: left;
        }

        #renewal-page .dataTables_filter label {
            align-items: flex-start;
            flex-direction: column;
            justify-content: flex-start;
        }

        #renewal-page .dataTables_filter input {
            margin-left: 0;
            margin-top: .35rem;
            min-width: 0;
            width: 100% !important;
        }

        #renewal-page .dataTables_paginate {
            margin-top: .75rem;
            text-align: left;
        }

        #renewal-page .dataTables_paginate .pagination {
            justify-content: flex-start;
        }
    }
</style>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-calendar-check mr-2"></i><?php echo $lang->get('renewal'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->


<!-- Main content -->
<div class="content" id="renewal-page">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card card-outline card-primary shadow-sm renewal-card">
                    <div class="renewal-toolbar">
                        <div class="renewal-info">
                            <span class="renewal-info-icon">
                                <i class="fas fa-lightbulb"></i>
                            </span>
                            <span><?php echo $lang->get('renewal_page_info'); ?></span>
                        </div>
                        <div class="renewal-date-filter">
                            <label class="renewal-date-label" for="renewal-date"><?php echo $lang->get('select_date_showing_items_expiration'); ?></label>
                            <div class="input-group input-group-sm date">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-calendar-day"></i></span>
                                </div>
                                <input type="text" class="form-control" id="renewal-date" autocomplete="off">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary" id="clear-renewal-date" aria-label="Clear date">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="renewal-table-section">
                        <table class="table table-striped table-hover nowrap" id="table-renewal" style="width:100%;">
                            <thead>
                                <tr>
                                    <th><?php echo $lang->get('label'); ?></th>
                                    <th><?php echo $lang->get('expiration_date'); ?></th>
                                    <th><?php echo $lang->get('folder'); ?></th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

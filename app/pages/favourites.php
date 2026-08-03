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
 * @file      favourites.php
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

// Check user access and favourites enabled
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('favourites') === false
    || isset($SETTINGS['enable_favourites']) === false || (int) $SETTINGS['enable_favourites'] === 0
    || (isset($session_user_admin) && (int) $session_user_admin === 1)) {
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
$lang = new Language($session->get('user-language') ?? 'english');

// --------------------------------- //

// Sort options, rendered once here and referenced by value in the JS comparator.
$sortOptions = [
    'recent' => 'favorites_sort_recent',
    'label' => 'favorites_sort_label',
    'folder' => 'favorites_sort_folder',
    'used' => 'favorites_sort_used',
];

// Availability filter. Default is "available" so the list keeps showing only what
// can actually be opened; the other values surface the broken bookmarks.
$filterOptions = [
    'available' => 'favorites_filter_available',
    'all' => 'favorites_filter_all',
    'unavailable' => 'favorites_filter_unavailable',
];

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-star mr-2"></i><?php echo $lang->get('favorites'); ?>
                    <span class="badge badge-secondary align-middle ml-2" id="favorites-count" style="display:none;">0</span>
                </h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<style>
    /* Toolbar: search grows, the controls on the right keep their natural size. */
    .fav-toolbar .fav-search { flex: 1 1 260px; min-width: 200px; }
    .fav-toolbar .fav-control { flex: 0 0 auto; }

    /* Grid cards -------------------------------------------------------- */
    .fav-card { transition: box-shadow .15s ease, transform .15s ease; height: 100%; }
    .fav-card:hover { box-shadow: 0 .25rem .75rem rgba(0, 0, 0, .12); transform: translateY(-2px); }
    .fav-card .card-body { padding: .85rem; }
    .fav-card-head { display: flex; align-items: flex-start; }
    .fav-card-icon {
        flex: 0 0 34px; height: 34px; border-radius: 6px; margin-right: .6rem;
        display: flex; align-items: center; justify-content: center;
        background-color: rgba(0, 123, 255, .1); color: #007bff;
    }
    .fav-card-titles { flex: 1 1 auto; min-width: 0; }
    /* Long labels and deep folder paths must never widen the card. */
    .fav-label { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fav-folder, .fav-login, .fav-desc, .fav-meta {
        font-size: .82rem; color: #6c757d;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .fav-desc { white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .fav-tags .badge { font-weight: 400; }
    .fav-card .card-footer { padding: .35rem .5rem; background-color: rgba(0, 0, 0, .02); }

    /* Action buttons: spaced, and allowed to wrap on a narrow card. */
    .fav-actions { display: flex; flex-wrap: wrap; gap: .35rem; }
    .fav-card .fav-actions { justify-content: center; }
    .fav-list-actions { justify-content: flex-end; }
    /* The star is the only destructive control, hence its own colour. */
    .fav-actions .fav-unstar { color: #f0ad4e; }
    .fav-actions .fav-unstar:hover { color: #dc3545; border-color: #dc3545; }
    .fav-card-head .fav-unstar { color: #f0ad4e; background: none; border: 0; padding: 0 0 0 .4rem; line-height: 1; }
    .fav-card-head .fav-unstar:hover { color: #dc3545; }

    /* List rows ---------------------------------------------------------- */
    #favorites-table td { vertical-align: middle; }
    #favorites-table .fav-label { white-space: normal; }

    /* Broken bookmarks: nothing about the item can be shown, only its state. */
    .fav-broken { opacity: .8; }
    .fav-broken .fav-label { color: #6c757d; font-style: italic; }
    .fav-card.fav-broken { border-left: 3px solid #ffc107; }

    /* Folder grouping ---------------------------------------------------- */
    .fav-group-title {
        font-size: .78rem; text-transform: uppercase; letter-spacing: .04em;
        color: #6c757d; border-bottom: 1px solid rgba(0, 0, 0, .08);
        padding-bottom: .25rem; margin: 1rem 0 .6rem;
    }
    .fav-group-title:first-child { margin-top: 0; }

    /* Empty states ------------------------------------------------------- */
    .fav-placeholder { padding: 2.5rem 1rem; text-align: center; color: #6c757d; }
    .fav-placeholder i.fav-placeholder-icon { font-size: 2.6rem; opacity: .35; display: block; margin-bottom: .75rem; }

    /* Password revealed in place, monospace so look-alike characters stay readable. */
    .fav-pwd { font-family: monospace; word-break: break-all; }
</style>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">

        <!-- TOOLBAR -->
        <div class="card card-outline card-primary">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap align-items-center fav-toolbar" style="gap:.5rem;">

                    <div class="input-group input-group-sm fav-search">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" class="form-control form-control-sm no-save" id="favorites-search"
                            autocomplete="off" placeholder="<?php echo $lang->get('favorites_search_placeholder'); ?>">
                        <div class="input-group-append" id="favorites-search-clear-wrap" style="display:none;">
                            <button type="button" class="btn btn-default" id="favorites-search-clear"
                                title="<?php echo $lang->get('favorites_clear_search'); ?>">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="fav-control">
                        <select class="form-control form-control-sm no-save" id="favorites-sort" style="width:auto;">
                            <?php foreach ($sortOptions as $value => $langKey) : ?>
                                <option value="<?php echo $value; ?>"><?php echo $lang->get($langKey); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fav-control" id="favorites-filter-wrap" style="display:none;">
                        <select class="form-control form-control-sm no-save" id="favorites-filter" style="width:auto;"
                            title="<?php echo $lang->get('favorites_filter'); ?>">
                            <?php foreach ($filterOptions as $value => $langKey) : ?>
                                <option value="<?php echo $value; ?>"><?php echo $lang->get($langKey); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fav-control">
                        <button type="button" class="btn btn-sm btn-default" id="favorites-group-toggle"
                            aria-pressed="false" title="<?php echo $lang->get('favorites_group_by_folder'); ?>">
                            <i class="fas fa-layer-group mr-1"></i><?php echo $lang->get('favorites_group_by_folder'); ?>
                        </button>
                    </div>

                    <div class="btn-group btn-group-sm fav-control" role="group" aria-label="<?php echo $lang->get('favorites_view_mode'); ?>">
                        <button type="button" class="btn btn-default active" id="favorites-view-grid"
                            title="<?php echo $lang->get('favorites_view_grid'); ?>"><i class="fas fa-th-large"></i></button>
                        <button type="button" class="btn btn-default" id="favorites-view-list"
                            title="<?php echo $lang->get('favorites_view_list'); ?>"><i class="fas fa-list"></i></button>
                    </div>

                </div>
            </div>
        </div>

        <!-- Favourites pointing at a deleted item, or at one this user may no longer read. -->
        <div class="alert alert-warning alert-dismissible" id="favorites-unavailable" style="display:none;">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-unlink mr-2"></i>
            <span id="favorites-unavailable-text"></span>
            <button type="button" class="btn btn-sm btn-outline-dark ml-2" id="favorites-show-unavailable"
                aria-pressed="false" title="<?php echo $lang->get('favorites_filter_unavailable'); ?>">
                <i class="fas fa-filter mr-1"></i><?php echo $lang->get('favorites_show_unavailable'); ?>
            </button>
            <button type="button" class="btn btn-sm btn-warning ml-2" id="favorites-cleanup">
                <i class="fas fa-broom mr-1"></i><?php echo $lang->get('favorites_cleanup'); ?>
            </button>
        </div>

        <!-- LOADING -->
        <div class="fav-placeholder" id="favorites-loading">
            <i class="fas fa-circle-notch fa-spin fav-placeholder-icon"></i>
            <?php echo $lang->get('please_wait_while_loading'); ?>
        </div>

        <!-- GRID VIEW -->
        <div id="favorites-grid" style="display:none;"></div>

        <!-- LIST VIEW -->
        <div class="card" id="favorites-list" style="display:none;">
            <div class="card-body p-0">
                <table class="table table-hover mb-0" id="favorites-table">
                    <thead>
                        <tr>
                            <th style="width:40%;"><?php echo $lang->get('label'); ?></th>
                            <th style="width:15%;"><?php echo $lang->get('login'); ?></th>
                            <th style="width:25%;"><?php echo $lang->get('group'); ?></th>
                            <th style="width:20%;" class="text-right"><?php echo $lang->get('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="favorites-tbody"></tbody>
                </table>
            </div>
        </div>

        <!-- NO FAVOURITE AT ALL -->
        <div class="card" id="favorites-empty" style="display:none;">
            <div class="card-body fav-placeholder">
                <i class="far fa-star fav-placeholder-icon"></i>
                <h5 class="mb-2"><?php echo $lang->get('currently_no_favorites'); ?></h5>
                <p class="mb-3"><?php echo $lang->get('favorites_empty_help'); ?></p>
                <a href="index.php?page=items" class="btn btn-sm btn-primary">
                    <i class="fas fa-folder-open mr-1"></i><?php echo $lang->get('favorites_browse_items'); ?>
                </a>
            </div>
        </div>

        <!-- SEARCH WITHOUT RESULT -->
        <div class="card" id="favorites-no-match" style="display:none;">
            <div class="card-body fav-placeholder">
                <i class="fas fa-search fav-placeholder-icon"></i>
                <?php echo $lang->get('favorites_no_match'); ?>
            </div>
        </div>

    </div>
</section>

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
 * @file      kb.php
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
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->checkSession() === false ||
    $checkUserAccess->userAccessPage('kb') === false ||
    isset($SETTINGS['enable_kb']) === false ||
    (int) $SETTINGS['enable_kb'] === 0
) {
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$isAdmin = (int) $session->get('user-admin') === 1;

// --------------------------------- //
?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark">
                    <i class="fas fa-book mr-2"></i><?php echo $lang->get('kb_menu'); ?>
                </h1>
            </div>
        </div>
    </div>
</div>
<!-- /.content-header -->

<!-- Main content -->
<div class="content">
    <div class="container-fluid">

        <!-- Toolbar -->
        <div class="row mb-3">
            <div class="col-md-4">
                <select id="kb-category-filter" class="form-control form-control-sm">
                    <option value="0"><?php echo $lang->get('kb_all_categories'); ?></option>
                </select>
            </div>
            <div class="col-md-8 text-right">
                <button type="button" class="btn btn-sm btn-primary" id="kb-btn-new">
                    <i class="fas fa-plus mr-1"></i><?php echo $lang->get('kb_new_article'); ?>
                </button>
                <?php if ($isAdmin): ?>
                <button type="button" class="btn btn-sm btn-secondary ml-2" id="kb-btn-trash">
                    <i class="fas fa-trash mr-1"></i><?php echo $lang->get('kb_deleted_articles'); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Articles list -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover table-striped" id="kb-articles-table">
                            <thead>
                                <tr>
                                    <th><?php echo $lang->get('label'); ?></th>
                                    <th><?php echo $lang->get('kb_category'); ?></th>
                                    <th><?php echo $lang->get('author'); ?></th>
                                    <th class="text-right" style="width:100px;"><?php echo $lang->get('actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="kb-articles-body">
                                <tr>
                                    <td colspan="4" class="text-center text-muted" id="kb-loading">
                                        <i class="fas fa-spinner fa-spin mr-1"></i><?php echo $lang->get('loading_wait'); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- View / Edit modal -->
<div class="modal fade" id="kb-modal-edit" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="kb-modal-edit-title"><?php echo $lang->get('kb_new_article'); ?></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="kb-edit-id" value="0">
                <div class="form-group">
                    <label for="kb-edit-label"><?php echo $lang->get('label'); ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="kb-edit-label" maxlength="255">
                </div>
                <div class="form-group">
                    <label for="kb-edit-category"><?php echo $lang->get('kb_category'); ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="kb-edit-category" maxlength="100"
                           placeholder="<?php echo $lang->get('kb_category_placeholder'); ?>">
                </div>
                <div class="form-group">
                    <label for="kb-edit-description"><?php echo $lang->get('description'); ?> <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="kb-edit-description" rows="10"></textarea>
                </div>
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="kb-edit-anyone-modify">
                        <label class="custom-control-label" for="kb-edit-anyone-modify">
                            <?php echo $lang->get('kb_anyone_can_modify'); ?>
                        </label>
                    </div>
                </div>
                <!-- Linked items (shown when editing an existing article) -->
                <div class="form-group d-none" id="kb-edit-items-section">
                    <label><?php echo $lang->get('kb_linked_items'); ?></label>
                    <div id="kb-edit-items-list" class="mb-2"></div>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="kb-item-search"
                               placeholder="<?php echo $lang->get('kb_search_item_placeholder'); ?>">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="kb-btn-item-search">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div id="kb-item-search-results" class="mt-1"></div>
                </div>
                <div class="alert alert-danger d-none" id="kb-edit-error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $lang->get('cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="kb-btn-save">
                    <i class="fas fa-save mr-1"></i><?php echo $lang->get('save'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View article modal -->
<div class="modal fade" id="kb-modal-view" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="kb-modal-view-title"></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="mb-2 text-muted small">
                    <span id="kb-view-category"></span> &mdash;
                    <span id="kb-view-author"></span>
                </div>
                <div id="kb-view-description" class="kb-description-content border p-3 rounded bg-light mb-3"></div>
                <div id="kb-view-items-section" class="d-none">
                    <label class="font-weight-bold"><?php echo $lang->get('kb_linked_items'); ?></label>
                    <div id="kb-view-items-list"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $lang->get('close'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Trash modal (admin only) -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="kb-modal-trash" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $lang->get('kb_deleted_articles'); ?></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th><?php echo $lang->get('label'); ?></th>
                            <th><?php echo $lang->get('kb_category'); ?></th>
                            <th><?php echo $lang->get('author'); ?></th>
                            <th><?php echo $lang->get('date'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="kb-trash-body">
                        <tr><td colspan="5" class="text-center text-muted"><?php echo $lang->get('loading_wait'); ?></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo $lang->get('close'); ?></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<link rel="stylesheet" href="./assets/css/kb.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">

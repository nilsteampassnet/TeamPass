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
 * @file      reviews.php
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
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('reviews') === false) {
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

$reviewsEnabled = (int) ($SETTINGS['access_reviews_enabled'] ?? 0) === 1;

// A manager is limited to the folders they can access; an admin sees all.
require_once __DIR__ . '/../sources/reviews.functions.php';
$isAdminReviewer = (int) $session->get('user-admin') === 1;
$accessibleFolders = $session->get('user-accessible_folders');
$personalFolders = $session->get('user-personal_visible_folders');
$reviewerScope = reviewsReviewerFolderScope(
    $isAdminReviewer,
    is_array($accessibleFolders) === true ? $accessibleFolders : [],
    is_array($personalFolders) === true ? $personalFolders : []
);

// Non-personal folders for the campaign scope selector
$scopeFolders = [];
if ($reviewsEnabled === true) {
    if ($isAdminReviewer === true) {
        $scopeFolders = DB::query(
            'SELECT id, title, nlevel FROM ' . prefixTable('nested_tree') . '
            WHERE personal_folder = %i
            ORDER BY nleft ASC',
            0
        );
    } elseif (count($reviewerScope) > 0) {
        // Manager: only the folders inside their perimeter
        $scopeFolders = DB::query(
            'SELECT id, title, nlevel FROM ' . prefixTable('nested_tree') . '
            WHERE personal_folder = %i AND id IN %li
            ORDER BY nleft ASC',
            0,
            $reviewerScope
        );
    }
}

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-clipboard-check mr-2"></i><?php echo $lang->get('access_reviews'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<!-- Main content -->
<div class='content'>
    <div class='container-fluid'>
<?php if ($reviewsEnabled === false) : ?>
        <div class='row'>
            <div class='col-12'>
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-triangle-exclamation mr-2"></i><?php echo $lang->get('access_reviews_disabled_tip'); ?>
                </div>
            </div>
        </div>
<?php else : ?>
        <div class='row'>
            <div class='col-md-12'>
                <!-- LAUNCH A CAMPAIGN -->
                <div class='card card-primary'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo $lang->get('access_reviews_launch'); ?></h3>
                    </div>
                    <div class='card-body'>
<?php if ($isAdminReviewer === false) : ?>
                        <div class='alert alert-info py-2'>
                            <i class='fas fa-user-shield mr-2'></i><?php echo $lang->get('access_reviews_manager_scope_note'); ?>
                        </div>
<?php endif; ?>
                        <div class='row'>
                            <div class='col-md-5'>
                                <label for='review-label'><?php echo $lang->get('label'); ?></label>
                                <input type='text' class='form-control' id='review-label' maxlength='255'
                                    placeholder='<?php echo $lang->get('access_reviews_label_placeholder'); ?>'>
                            </div>
                            <div class='col-md-5'>
                                <label for='review-folder'><?php echo $lang->get('access_reviews_scope'); ?></label>
                                <select class='form-control' id='review-folder'>
                                    <option value='0'><?php echo $isAdminReviewer === true ? $lang->get('access_reviews_all_folders') : $lang->get('access_reviews_all_my_folders'); ?></option>
<?php foreach ($scopeFolders as $scopeFolder) : ?>
                                    <option value='<?php echo (int) $scopeFolder['id']; ?>'><?php echo str_repeat('&nbsp;&nbsp;', max(0, (int) $scopeFolder['nlevel'] - 1)) . htmlspecialchars((string) $scopeFolder['title']); ?></option>
<?php endforeach; ?>
                                </select>
                                <small class='form-text text-muted'><?php echo $lang->get('access_reviews_scope_tip'); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class='card-footer'>
                        <button class='btn btn-primary' id='review-start'>
                            <i class='fas fa-play mr-2'></i><?php echo $lang->get('access_reviews_launch_btn'); ?>
                        </button>
                    </div>
                </div>

                <!-- CAMPAIGNS LIST -->
                <div class='card card-default'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo $lang->get('access_reviews_list'); ?></h3>
                    </div>
                    <div class='card-body table-responsive p-0' id='reviews-list'>
                    </div>
                </div>

                <!-- CAMPAIGN DETAIL -->
                <div class='card card-default' id='review-detail-card' style='display:none'>
                    <div class='card-header'>
                        <h3 class='card-title' id='review-detail-title'></h3>
                        <div class='card-tools'>
                            <button class='btn btn-tool' id='review-detail-close-panel'><i class='fas fa-times'></i></button>
                        </div>
                    </div>
                    <div class='card-body table-responsive p-0' id='review-detail'>
                    </div>
                </div>
            </div>
        </div>
<?php endif; ?>
    </div>
</div>

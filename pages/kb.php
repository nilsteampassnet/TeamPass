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

require_once __DIR__ . '/../sources/main.functions.php';

loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars((string) $request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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
    $checkUserAccess->checkSession() === false
    || !isset($SETTINGS['enable_kb'])
    || (int) $SETTINGS['enable_kb'] !== 1
    || (int) $session->get('user-admin') === 1
) {
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');


function kbPageNormalizeExtensionsList(string $extensions): array
{
    $normalized = [];
    foreach (explode(',', strtolower($extensions)) as $extension) {
        $extension = trim($extension);
        $extension = ltrim($extension, '.');
        if ($extension !== '') {
            $normalized[$extension] = $extension;
        }
    }

    return array_values($normalized);
}

function kbPageAttachmentAcceptAttribute(array $settings): string
{
    if ((int) ($settings['upload_all_extensions_file'] ?? 0) === 1) {
        return '';
    }

    $extensions = [];
    foreach (['upload_docext', 'upload_imagesext', 'upload_pkgext', 'upload_otherext'] as $settingKey) {
        if (isset($settings[$settingKey]) === true) {
            foreach (kbPageNormalizeExtensionsList((string) $settings[$settingKey]) as $extension) {
                $extensions[$extension] = $extension;
            }
        }
    }

    if (empty($extensions) === true) {
        return '';
    }

    return implode(',', array_map(static function (string $extension): string {
        return '.' . $extension;
    }, array_values($extensions)));
}

$directKbId = (int) $request->query->get('id', 0);
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-8">
                <h1 class="m-0 text-dark"><i class="fa-solid fa-map-signs mr-2"></i><?php echo $lang->get('kb_page_title'); ?></h1>
                <p class="text-muted mb-0 mt-2"><?php echo $lang->get('kb_page_subtitle'); ?></p>
            </div>
            <div class="col-sm-4 text-right">
                <button type="button" class="btn btn-primary" id="button-kb-new">
                    <i class="fa-solid fa-plus mr-1"></i><?php echo $lang->get('kb_add_entry'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="card card-info hidden" id="kb-viewer-card">
                <div class="card-header">
                    <h3 class="card-title" id="kb-viewer-title"><?php echo $lang->get('kb_menu'); ?></h3>
                    <div class="card-tools tp-kb-card-actions">
                        <button type="button" class="btn btn-sm btn-outline-light hidden" id="button-kb-edit-from-view" title="<?php echo $lang->get('edit'); ?>">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-light hidden" id="button-kb-delete-from-view" title="<?php echo $lang->get('delete'); ?>">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-light" id="button-kb-close-view" title="<?php echo $lang->get('close'); ?>">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="tp-kb-viewer-meta mb-4">
                        <span class="badge badge-info mr-2" id="kb-viewer-category"></span>
                        <span class="badge badge-light border hidden" id="kb-viewer-comments-badge"></span>
                        <span class="text-muted small" id="kb-viewer-author"></span>
                    </div>
                    <div class="tp-kb-article-surface">
                        <div id="kb-viewer-description" class="tp-kb-content"></div>
                    </div>
                    <div class="mt-4 hidden" id="kb-viewer-items-zone">
                        <h5 class="mb-2"><?php echo $lang->get('kb_associated_items'); ?></h5>
                        <div id="kb-viewer-items"></div>
                    </div>
                    <div class="mt-4 hidden" id="kb-viewer-attachments-zone">
                        <h5 class="mb-2"><?php echo $lang->get('attached_files'); ?></h5>
                        <div id="kb-viewer-attachments"></div>
                    </div>
                    <div class="mt-4 hidden" id="kb-viewer-comments-zone">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                            <h5 class="mb-0"><?php echo $lang->get('kb_comments'); ?></h5>
                            <span class="badge badge-light border" id="kb-viewer-comments-count">0</span>
                        </div>
                        <div class="alert alert-light border hidden" id="kb-viewer-comments-disabled">
                            <?php echo $lang->get('kb_comments_closed'); ?>
                        </div>
                        <div id="kb-viewer-comments-list" class="tp-kb-comments-list"></div>
                        <div class="tp-kb-comment-form hidden mt-3" id="kb-viewer-comment-form">
                            <label for="kb-comment-text"><?php echo $lang->get('kb_add_comment'); ?></label>
                            <textarea class="form-control" id="kb-comment-text" rows="4" maxlength="3000" placeholder="<?php echo htmlspecialchars($lang->get('kb_comment_placeholder'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
                            <div class="d-flex flex-wrap justify-content-end align-items-center mt-2">
                                <button type="button" class="btn btn-primary mt-2 mt-sm-0" id="button-kb-comment-save">
                                    <i class="fa-solid fa-paper-plane mr-1"></i><?php echo $lang->get('kb_add_comment'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-primary hidden" id="kb-editor-card">
                <div class="card-header">
                    <h3 class="card-title" id="kb-editor-title"><?php echo $lang->get('kb_add_entry'); ?></h3>
                </div>
                <div class="card-body">
                    <input type="hidden" id="kb-id" value="0">
                    <div class="form-group">
                        <label for="kb-label"><?php echo $lang->get('label'); ?></label>
                        <input type="text" class="form-control" id="kb-label" maxlength="200">
                    </div>
                    <div class="form-group">
                        <label for="kb-category"><?php echo $lang->get('category'); ?></label>
                        <input type="text" class="form-control" id="kb-category" maxlength="50" placeholder="<?php echo htmlspecialchars($lang->get('kb_category_placeholder'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="kb-description"><?php echo $lang->get('description'); ?></label>
                        <textarea class="form-control" id="kb-description" rows="12" placeholder="<?php echo htmlspecialchars($lang->get('kb_description_placeholder'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="kb-anyone-can-modify">
                            <label class="custom-control-label" for="kb-anyone-can-modify"><?php echo $lang->get('anyone_can_modify'); ?></label>
                        </div>
                        <small class="form-text text-muted"><?php echo $lang->get('kb_anyone_can_modify_help'); ?></small>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="kb-allow-comments">
                            <label class="custom-control-label" for="kb-allow-comments"><?php echo $lang->get('kb_allow_comments'); ?></label>
                        </div>
                        <small class="form-text text-muted"><?php echo $lang->get('kb_allow_comments_help'); ?></small>
                    </div>
                    <div class="form-group">
                        <label for="kb-associated-items"><?php echo $lang->get('kb_associated_items'); ?></label>
                        <select class="form-control" id="kb-associated-items" multiple="multiple" style="width: 100%;"></select>
                        <small class="form-text text-muted"><?php echo $lang->get('kb_search_items_placeholder'); ?></small>
                    </div>
                    <div class="form-group">
                        <label><?php echo $lang->get('attached_files'); ?></label>
                        <div id="kb-editor-attachments-list" class="mb-2"></div>
                        <div class="input-group">
                            <div class="custom-file">
                                <?php $kbAttachmentAccept = kbPageAttachmentAcceptAttribute($SETTINGS); ?>
                                <input type="file" class="custom-file-input" id="kb-attachments-input" multiple<?php echo $kbAttachmentAccept !== '' ? ' accept="' . htmlspecialchars($kbAttachmentAccept, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                                <label class="custom-file-label" for="kb-attachments-input"><?php echo $lang->get('select_files'); ?></label>
                            </div>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-primary" id="button-kb-upload-attachments"><?php echo $lang->get('start_upload'); ?></button>
                            </div>
                        </div>
                        <small class="form-text text-muted" id="kb-attachments-help"><?php echo $lang->get('kb_save_before_attachments'); ?></small>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-primary" id="button-kb-save"><?php echo $lang->get('save'); ?></button>
                    <button type="button" class="btn btn-default float-right" id="button-kb-cancel"><?php echo $lang->get('cancel'); ?></button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?php echo $lang->get('kb_menu'); ?></h3>
                </div>
                <div class="card-body">
                    <table id="table-kb-list" class="table table-bordered table-striped" style="width: 100%;">
                        <thead>
                            <tr>
                                <th class="kb-col-label"><?php echo $lang->get('label'); ?></th>
                                <th class="kb-col-category"><?php echo $lang->get('category'); ?></th>
                                <th class="kb-col-author"><?php echo $lang->get('author'); ?></th>
                                <th class="kb-col-items text-center"><?php echo $lang->get('items'); ?></th>
                                <th class="kb-col-actions text-center"><?php echo $lang->get('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    #kb-viewer-card .card-header,
    #kb-editor-card .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    #kb-viewer-card .card-title,
    #kb-editor-card .card-title {
        flex: 1 1 auto;
        margin: 0;
    }

    .tp-kb-card-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        margin-left: auto;
        float: none;
    }

    .tp-kb-content {
        font-size: 1rem;
        line-height: 1.85;
        color: #2f3b52;
    }

    .tp-kb-content > :last-child {
        margin-bottom: 0;
    }

    .tp-kb-content p,
    .tp-kb-content ul,
    .tp-kb-content ol,
    .tp-kb-content blockquote {
        margin-bottom: 1rem;
    }

    .tp-kb-content ul,
    .tp-kb-content ol {
        padding-left: 1.35rem;
    }

    .tp-kb-content blockquote {
        margin-left: 0;
        padding: 0.85rem 1rem;
        border-left: 4px solid #17a2b8;
        background: #f6fbfd;
        border-radius: 0.45rem;
        color: #41526b;
    }

    .tp-kb-content a.tp-kb-link {
        color: #117a8b;
        font-weight: 600;
        text-decoration: underline;
        text-decoration-thickness: 1px;
        text-underline-offset: 2px;
    }

    .tp-kb-viewer-meta {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .tp-kb-article-surface {
        background: linear-gradient(180deg, rgba(248, 250, 252, 0.95) 0%, rgba(255, 255, 255, 1) 100%);
        border: 1px solid rgba(23, 162, 184, 0.15);
        border-radius: 0.9rem;
        padding: 1.5rem 1.65rem;
        box-shadow: 0 0.6rem 1.4rem rgba(15, 23, 42, 0.04);
        width: 100%;
    }

    #kb-viewer-items-zone,
    #kb-viewer-attachments-zone,
    #kb-viewer-comments-zone {
        width: 100%;
    }

    .tp-kb-comments-list:empty {
        display: none;
    }

    .tp-kb-comment {
        background: #ffffff;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 0.75rem;
        padding: 1rem 1rem 0.85rem;
        box-shadow: 0 0.35rem 0.85rem rgba(15, 23, 42, 0.04);
        margin-bottom: 0.8rem;
    }

    .tp-kb-comment-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.7rem;
    }

    .tp-kb-comment-meta {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.35rem;
    }

    .tp-kb-comment-author {
        font-weight: 600;
        color: #23324d;
    }

    .tp-kb-comment-date {
        display: inline-block;
        margin-top: 0;
        white-space: nowrap;
    }

    .tp-kb-comment-body {
        color: #32415c;
    }

    .tp-kb-comment-form {
        background: #f9fbfc;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 0.8rem;
        padding: 1rem;
    }

    .tp-kb-list-entry-title {
        display: inline-block;
        font-weight: 600;
        font-size: 1rem;
        color: #1f2d3d;
        margin-bottom: 0.35rem;
    }

    .tp-kb-list-entry-excerpt {
        color: #6c757d;
        line-height: 1.5;
        margin-bottom: 0.55rem;
    }

    .tp-kb-list-entry-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }

    .tp-kb-list-entry-meta .badge {
        font-weight: 500;
    }

    .tp-kb-associated-item,
    .tp-kb-attachment-item {
        display: inline-block;
        margin: 0 0.35rem 0.35rem 0;
    }

    .tp-kb-attachment-list .list-group-item {
        padding: 0.55rem 0.75rem;
    }

    .tp-kb-card-actions .btn {
        min-width: 2.25rem;
        margin-left: 0.35rem;
        box-shadow: none;
    }

    .tp-kb-card-actions .btn i {
        pointer-events: none;
    }

    #table-kb-list th,
    #table-kb-list td {
        vertical-align: middle;
    }

    #table-kb-list th.kb-col-items,
    #table-kb-list td.kb-col-items,
    #table-kb-list th.kb-col-actions,
    #table-kb-list td.kb-col-actions {
        white-space: nowrap;
    }

    #table-kb-list td.kb-col-actions .btn {
        margin: 0 0.15rem;
    }

    #table-kb-list td.kb-col-label a {
        font-weight: 600;
    }

    @media (max-width: 767.98px) {
        .tp-kb-article-surface {
            padding: 1rem;
        }

        .tp-kb-comment-header {
            flex-direction: column;
        }
    }
</style>

<input type="hidden" id="kb-direct-id" value="<?php echo $directKbId; ?>">

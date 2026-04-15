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
 * @file      kb.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/../sources/main.functions.php';

loadClasses();
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

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
?>

<script type="text/javascript">
    const kbSessionKey = <?php echo json_encode((string) $session->get('key')); ?>;
    const kbTranslations = {
        server_answer_error: <?php echo json_encode($lang->get('server_answer_error')); ?>,
        kb_saved: <?php echo json_encode($lang->get('kb_saved')); ?>,
        kb_deleted: <?php echo json_encode($lang->get('kb_deleted')); ?>,
        kb_delete_confirm: <?php echo json_encode($lang->get('kb_delete_confirm')); ?>,
        kb_no_entries: <?php echo json_encode($lang->get('kb_no_entries')); ?>,
        kb_direct_link_not_found: <?php echo json_encode($lang->get('kb_direct_link_not_found')); ?>,
        kb_add_entry: <?php echo json_encode($lang->get('kb_add_entry')); ?>,
        kb_edit_entry: <?php echo json_encode($lang->get('kb_edit_entry')); ?>,
        kb_page_subtitle: <?php echo json_encode($lang->get('kb_page_subtitle')); ?>,
        kb_created_by: <?php echo json_encode($lang->get('kb_created_by')); ?>,
        kb_empty_description: <?php echo json_encode($lang->get('kb_empty_description')); ?>,
        kb_allow_comments: <?php echo json_encode($lang->get('kb_allow_comments')); ?>,
        kb_comments: <?php echo json_encode($lang->get('kb_comments')); ?>,
        kb_no_comments: <?php echo json_encode($lang->get('kb_no_comments')); ?>,
        kb_comments_closed: <?php echo json_encode($lang->get('kb_comments_closed')); ?>,
        kb_comments_disabled: <?php echo json_encode($lang->get('kb_comments_disabled')); ?>,
        kb_add_comment: <?php echo json_encode($lang->get('kb_add_comment')); ?>,
        kb_comment_placeholder: <?php echo json_encode($lang->get('kb_comment_placeholder')); ?>,
        kb_comment_help: <?php echo json_encode($lang->get('kb_comment_help')); ?>,
        kb_comment_added: <?php echo json_encode($lang->get('kb_comment_added')); ?>,
        kb_comment_deleted: <?php echo json_encode($lang->get('kb_comment_deleted')); ?>,
        kb_comment_required: <?php echo json_encode($lang->get('kb_comment_required')); ?>,
        kb_comment_too_long: <?php echo json_encode($lang->get('kb_comment_too_long')); ?>,
        kb_comment_delete_confirm: <?php echo json_encode($lang->get('kb_comment_delete_confirm')); ?>,
        kb_attachment_deleted: <?php echo json_encode($lang->get('kb_attachment_deleted')); ?>,
        kb_attachment_uploaded: <?php echo json_encode($lang->get('kb_attachment_uploaded')); ?>,
        kb_delete_attachment_confirm: <?php echo json_encode($lang->get('kb_delete_attachment_confirm')); ?>,
        kb_save_before_attachments: <?php echo json_encode($lang->get('kb_save_before_attachments')); ?>,
        kb_pending_attachments_after_save: <?php echo json_encode($lang->get('kb_pending_attachments_after_save')); ?>,
        kb_no_attachments: <?php echo json_encode($lang->get('kb_no_attachments')); ?>,
        close: <?php echo json_encode($lang->get('close')); ?>,
        edit: <?php echo json_encode($lang->get('edit')); ?>,
        delete: <?php echo json_encode($lang->get('delete')); ?>,
        open: <?php echo json_encode($lang->get('open')); ?>,
        all_fields_are_required: <?php echo json_encode($lang->get('all_fields_are_required')); ?>,
        search_items_placeholder: <?php echo json_encode($lang->get('kb_search_items_placeholder')); ?>,
        no_data_to_display: <?php echo json_encode($lang->get('no_data_to_display')); ?>,
        select_files: <?php echo json_encode($lang->get('select_files')); ?>,
        start_upload: <?php echo json_encode($lang->get('start_upload')); ?>,
        attached_files: <?php echo json_encode($lang->get('attached_files')); ?>
    };

    const kbDirectId = parseInt($('#kb-direct-id').val(), 10) || 0;
    let kbTable = null;
    let kbLoadedDirectId = false;

    function kbEncodePayload(payload) {
        return prepareExchangedData(JSON.stringify(payload), 'encode', kbSessionKey, 'kb.queries.php', 'encode', false);
    }

    function kbDecodeResponse(response, actionName) {
        return prepareExchangedData(response, 'decode', kbSessionKey, 'kb.queries.php', actionName, false);
    }

    function kbToastError(message) {
        toastr.remove();
        toastr.error(message || kbTranslations.server_answer_error, '', {
            timeOut: 5000,
            progressBar: true
        });
    }

    function kbToastSuccess(message) {
        toastr.remove();
        toastr.success(message, '', {
            timeOut: 1800,
            progressBar: true
        });
    }

    function kbSanitizeHtml(html) {
        return DOMPurify.sanitize(html || '', {
            ADD_ATTR: ['target', 'rel']
        });
    }

    function kbFormatBytes(bytes) {
        const size = parseInt(bytes || 0, 10);
        if (!Number.isFinite(size) || size <= 0) {
            return '';
        }

        const units = ['B', 'KB', 'MB', 'GB'];
        let value = size;
        let unitIndex = 0;
        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex += 1;
        }

        return value.toFixed(value >= 10 || unitIndex === 0 ? 0 : 1) + ' ' + units[unitIndex];
    }

    function kbGetSelectedFiles() {
        const input = $('#kb-attachments-input')[0];
        if (!input || !input.files) {
            return [];
        }

        return Array.from(input.files);
    }

    function kbHasPendingAttachments() {
        return kbGetSelectedFiles().length > 0;
    }

    function kbRefreshAttachmentsHelp() {
        const kbId = parseInt($('#kb-id').val(), 10) || 0;
        const helpText = kbHasPendingAttachments() === true && kbId === 0
            ? kbTranslations.kb_pending_attachments_after_save
            : kbTranslations.kb_save_before_attachments;

        $('#kb-attachments-help').text(helpText);
    }

    function kbResetForm() {
        $('#kb-id').val('0');
        $('#kb-label').val('');
        $('#kb-category').val('');
        $('#kb-description').val('');
        $('#kb-anyone-can-modify').prop('checked', false);
        $('#kb-allow-comments').prop('checked', false);
        $('#kb-associated-items').empty().trigger('change');
        $('#kb-editor-title').text(kbTranslations.kb_add_entry);
        $('#kb-editor-attachments-list').html('<div class="text-muted">' + DOMPurify.sanitize(kbTranslations.kb_no_attachments) + '</div>');
        $('#kb-attachments-input').val('');
        $('.custom-file-label[for="kb-attachments-input"]').text(kbTranslations.select_files);
        kbRefreshAttachmentsHelp();
    }

    function kbHideEditor() {
        kbResetForm();
        $('#kb-editor-card').addClass('hidden');
    }

    function kbHideViewer() {
        $('#kb-viewer-card').addClass('hidden');
        $('#kb-viewer-card').data('id', 0);
        $('#button-kb-edit-from-view').addClass('hidden').data('id', 0);
        $('#button-kb-delete-from-view').addClass('hidden').data('id', 0);
        $('#kb-viewer-title').text('');
        $('#kb-viewer-category').text('');
        $('#kb-viewer-author').text('');
        $('#kb-viewer-comments-badge').addClass('hidden').text('');
        $('#kb-viewer-description').html('');
        $('#kb-viewer-comments-count').text('0');
        $('#kb-viewer-comments-list').html('');
        $('#kb-viewer-comments-zone').addClass('hidden');
        $('#kb-viewer-comments-disabled').addClass('hidden');
        $('#kb-viewer-comment-form').addClass('hidden');
        $('#kb-comment-text').val('');
    }

    function kbAttachmentDownloadUrl(attachmentId) {
        return 'sources/kb.queries.php?type=download_attachment&attachment_id=' + encodeURIComponent(attachmentId) + '&key=' + encodeURIComponent(kbSessionKey);
    }

    function kbRenderAssociatedItems(items) {
        const $zone = $('#kb-viewer-items-zone');
        const $container = $('#kb-viewer-items');
        $container.html('');

        if (!Array.isArray(items) || items.length === 0) {
            $zone.addClass('hidden');
            return;
        }

        items.forEach(function(item) {
            const safeText = DOMPurify.sanitize(item.label || '', {USE_PROFILES: {html: false}});
            const safeLink = DOMPurify.sanitize(item.link || '#', {USE_PROFILES: {html: false}});
            $container.append('<a class="badge badge-primary tp-kb-associated-item" href="' + safeLink + '">' + safeText + '</a>');
        });

        $zone.removeClass('hidden');
    }

    function kbRenderViewerAttachments(attachments) {
        const $zone = $('#kb-viewer-attachments-zone');
        const $container = $('#kb-viewer-attachments');
        $container.html('');

        if (!Array.isArray(attachments) || attachments.length === 0) {
            $zone.addClass('hidden');
            return;
        }

        attachments.forEach(function(attachment) {
            const safeName = DOMPurify.sanitize(attachment.name || '', {USE_PROFILES: {html: false}});
            const safeSize = DOMPurify.sanitize(kbFormatBytes(attachment.size), {USE_PROFILES: {html: false}});
            const safeUrl = DOMPurify.sanitize(kbAttachmentDownloadUrl(attachment.id), {USE_PROFILES: {html: false}});
            $container.append('<a class="badge badge-secondary tp-kb-attachment-item" href="' + safeUrl + '"><i class="fa-solid fa-paperclip mr-1"></i>' + safeName + (safeSize !== '' ? ' (' + safeSize + ')' : '') + '</a>');
        });

        $zone.removeClass('hidden');
    }

    function kbRenderEditorAttachments(attachments) {
        const kbId = parseInt($('#kb-id').val(), 10) || 0;
        const $container = $('#kb-editor-attachments-list');
        $container.html('');

        if (!kbId) {
            $container.html('<div class="text-muted">' + DOMPurify.sanitize(kbHasPendingAttachments() === true ? kbTranslations.kb_pending_attachments_after_save : kbTranslations.kb_save_before_attachments) + '</div>');
            kbRefreshAttachmentsHelp();
            return;
        }

        if (!Array.isArray(attachments) || attachments.length === 0) {
            $container.html('<div class="text-muted">' + DOMPurify.sanitize(kbTranslations.kb_no_attachments) + '</div>');
            kbRefreshAttachmentsHelp();
            return;
        }

        const $list = $('<div class="list-group tp-kb-attachment-list"></div>');
        attachments.forEach(function(attachment) {
            const safeName = DOMPurify.sanitize(attachment.name || '', {USE_PROFILES: {html: false}});
            const safeSize = DOMPurify.sanitize(kbFormatBytes(attachment.size), {USE_PROFILES: {html: false}});
            const safeUrl = DOMPurify.sanitize(kbAttachmentDownloadUrl(attachment.id), {USE_PROFILES: {html: false}});
            const $item = $(
                '<div class="list-group-item d-flex justify-content-between align-items-center">' +
                    '<div><a href="' + safeUrl + '"><i class="fa-solid fa-paperclip mr-1"></i>' + safeName + '</a>' + (safeSize !== '' ? '<small class="text-muted ml-2">' + safeSize + '</small>' : '') + '</div>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger kb-action-delete-attachment" data-id="' + attachment.id + '" data-kb-id="' + kbId + '"><i class="fa-solid fa-trash"></i></button>' +
                '</div>'
            );
            $list.append($item);
        });

        $container.append($list);
        kbRefreshAttachmentsHelp();
    }

    function kbRenderComments(entry) {
        const comments = Array.isArray(entry.comments) ? entry.comments : [];
        const allowComments = parseInt(entry.allow_comments || 0, 10) === 1;
        const canComment = entry.can_comment === true;
        const commentsCount = parseInt(entry.comments_count || comments.length || 0, 10) || 0;
        const $zone = $('#kb-viewer-comments-zone');
        const $list = $('#kb-viewer-comments-list');
        const $disabled = $('#kb-viewer-comments-disabled');
        const $form = $('#kb-viewer-comment-form');
        const $badge = $('#kb-viewer-comments-badge');

        $('#kb-viewer-comments-count').text(String(commentsCount));

        if (commentsCount > 0 || allowComments === true) {
            $badge.text(String(commentsCount) + ' ' + kbTranslations.kb_comments.toLowerCase()).removeClass('hidden');
        } else {
            $badge.addClass('hidden').text('');
        }

        if (comments.length === 0 && allowComments === false) {
            $zone.addClass('hidden');
            $list.html('');
            $disabled.addClass('hidden');
            $form.addClass('hidden');
            return;
        }

        $zone.removeClass('hidden');
        $list.html('');

        if (comments.length === 0) {
            $list.html('<div class="text-muted small">' + DOMPurify.sanitize(kbTranslations.kb_no_comments) + '</div>');
        } else {
            comments.forEach(function(comment) {
                const safeAuthor = DOMPurify.sanitize(comment.author || '', {USE_PROFILES: {html: false}});
                const safeDate = DOMPurify.sanitize(comment.created_at_label || '', {USE_PROFILES: {html: false}});
                const safeBody = kbSanitizeHtml(comment.content_html || '');
                const deleteButton = comment.can_delete === true
                    ? '<button type="button" class="btn btn-sm btn-outline-danger kb-action-delete-comment" data-id="' + comment.id + '" data-kb-id="' + entry.id + '" title="' + kbTranslations.delete + '"><i class="fa-solid fa-trash"></i></button>'
                    : '';

                $list.append(
                    '<div class="tp-kb-comment">' +
                        '<div class="tp-kb-comment-header">' +
                            '<div class="tp-kb-comment-meta">' +
                                '<span class="tp-kb-comment-author">' + safeAuthor + '</span>' +
                                (safeDate !== '' ? '<small class="text-muted tp-kb-comment-date">- ' + safeDate + '</small>' : '') +
                            '</div>' +
                            deleteButton +
                        '</div>' +
                        '<div class="tp-kb-comment-body tp-kb-content">' + safeBody + '</div>' +
                    '</div>'
                );
            });
        }

        if (allowComments === true && canComment === true) {
            $form.removeClass('hidden');
            $disabled.addClass('hidden');
        } else {
            $form.addClass('hidden');
            if (comments.length > 0) {
                $disabled.removeClass('hidden');
            } else {
                $disabled.addClass('hidden');
            }
        }
    }

    function kbRenderViewer(entry) {
        const commentsCount = parseInt(entry.comments_count || 0, 10) || 0;
        $('#kb-viewer-title').text(entry.label || '');
        $('#kb-viewer-category').text(entry.category || '');
        $('#kb-viewer-author').text(kbTranslations.kb_created_by + ' ' + (entry.author || ''));
        $('#kb-viewer-description').html(entry.description_html ? kbSanitizeHtml(entry.description_html) : DOMPurify.sanitize(kbTranslations.kb_empty_description));
        kbRenderAssociatedItems(entry.associated_items || []);
        kbRenderViewerAttachments(entry.attachments || []);
        kbRenderComments(entry);
        $('#kb-viewer-card').data('id', entry.id || 0);
        if (commentsCount > 0 || parseInt(entry.allow_comments || 0, 10) === 1) {
            $('#kb-viewer-comments-badge').text(String(commentsCount) + ' ' + kbTranslations.kb_comments.toLowerCase()).removeClass('hidden');
        } else {
            $('#kb-viewer-comments-badge').addClass('hidden').text('');
        }

        if (entry.can_edit === true) {
            $('#button-kb-edit-from-view').removeClass('hidden').data('id', entry.id);
        } else {
            $('#button-kb-edit-from-view').addClass('hidden').data('id', 0);
        }

        if (entry.can_delete === true) {
            $('#button-kb-delete-from-view').removeClass('hidden').data('id', entry.id);
        } else {
            $('#button-kb-delete-from-view').addClass('hidden').data('id', 0);
        }

        $('#kb-viewer-card').removeClass('hidden');
    }

    function kbOpenViewer(id) {
        if (!id || id < 1) {
            return;
        }

        $.post(
            'sources/kb.queries.php',
            {
                type: 'get_kb',
                data: kbEncodePayload({id: id, log_view: 1}),
                key: kbSessionKey
            },
            function(response) {
                const data = kbDecodeResponse(response, 'get_kb');
                if (data.error === true) {
                    kbToastError(data.message || kbTranslations.kb_direct_link_not_found);
                    return;
                }

                kbHideEditor();
                kbRenderViewer(data.entry || {});
                kbLoadedDirectId = true;
            }
        ).fail(function() {
            kbToastError(kbTranslations.server_answer_error);
        });
    }

    function kbFillEditor(entry) {
        kbResetForm();
        $('#kb-id').val(String(entry.id || 0));
        $('#kb-label').val(entry.label || '');
        $('#kb-category').val(entry.category || '');
        $('#kb-description').val(entry.description || '');
        $('#kb-anyone-can-modify').prop('checked', parseInt(entry.anyone_can_modify || 0, 10) === 1);
        $('#kb-allow-comments').prop('checked', parseInt(entry.allow_comments || 0, 10) === 1);
        $('#kb-editor-title').text((entry.id || 0) > 0 ? kbTranslations.kb_edit_entry : kbTranslations.kb_add_entry);

        if (Array.isArray(entry.associated_items)) {
            entry.associated_items.forEach(function(item) {
                const option = new Option(item.label, item.id, true, true);
                $('#kb-associated-items').append(option);
            });
        }
        $('#kb-associated-items').trigger('change');
        kbRenderEditorAttachments(entry.attachments || []);
    }

    function kbOpenEditor(id) {
        kbHideViewer();

        if (!id || id < 1) {
            kbResetForm();
            $('#kb-editor-card').removeClass('hidden');
            return;
        }

        $.post(
            'sources/kb.queries.php',
            {
                type: 'get_kb',
                data: kbEncodePayload({id: id, log_view: 0}),
                key: kbSessionKey
            },
            function(response) {
                const data = kbDecodeResponse(response, 'get_kb');
                if (data.error === true) {
                    kbToastError(data.message);
                    return;
                }

                kbFillEditor(data.entry || {});
                $('#kb-editor-card').removeClass('hidden');
            }
        ).fail(function() {
            kbToastError(kbTranslations.server_answer_error);
        });
    }

    function kbDeleteEntry(id) {
        if (!id || id < 1) {
            return;
        }

        if (window.confirm(kbTranslations.kb_delete_confirm) !== true) {
            return;
        }

        $.post(
            'sources/kb.queries.php',
            {
                type: 'delete_kb',
                data: kbEncodePayload({id: id}),
                key: kbSessionKey
            },
            function(response) {
                const data = kbDecodeResponse(response, 'delete_kb');
                if (data.error === true) {
                    kbToastError(data.message);
                    return;
                }

                kbHideViewer();
                kbHideEditor();
                loadKbList();
                kbToastSuccess(kbTranslations.kb_deleted);
            }
        ).fail(function() {
            kbToastError(kbTranslations.server_answer_error);
        });
    }

    function kbUploadAttachments(kbId, onSuccess) {
        if (!kbId || kbId < 1) {
            kbToastError(kbTranslations.kb_save_before_attachments);
            return;
        }

        const files = kbGetSelectedFiles();
        if (!files.length) {
            kbToastError(kbTranslations.no_data_to_display);
            return;
        }

        const formData = new FormData();
        formData.append('type', 'upload_attachment');
        formData.append('kb_id', kbId);
        formData.append('key', kbSessionKey);
        files.forEach(function(file) {
            formData.append('attachments[]', file, file.name);
        });

        $.ajax({
            url: 'sources/kb.queries.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                const data = kbDecodeResponse(response, 'upload_attachment');
                if (data.error === true) {
                    kbToastError(data.message);
                    return;
                }

                $('#kb-attachments-input').val('');
                $('.custom-file-label[for="kb-attachments-input"]').text(kbTranslations.select_files);
                kbRefreshAttachmentsHelp();

                if (data.entry && Array.isArray(data.entry.attachments)) {
                    kbRenderEditorAttachments(data.entry.attachments);
                } else {
                    kbRenderEditorAttachments([]);
                }

                if (typeof onSuccess === 'function') {
                    onSuccess(data);
                    return;
                }

                kbToastSuccess(kbTranslations.kb_attachment_uploaded);
            },
            error: function() {
                kbToastError(kbTranslations.server_answer_error);
            }
        });
    }

    function kbSaveEntry() {
        const label = ($('#kb-label').val() || '').toString().trim();
        const category = ($('#kb-category').val() || '').toString().trim();
        const description = ($('#kb-description').val() || '').toString().trim();

        if (label === '' || category === '' || description === '') {
            kbToastError(kbTranslations.all_fields_are_required);
            return false;
        }

        const associatedItems = ($('#kb-associated-items').val() || []).map(function(itemId) {
            return parseInt(itemId, 10);
        }).filter(function(itemId) {
            return Number.isInteger(itemId) && itemId > 0;
        });

        const payload = {
            id: parseInt($('#kb-id').val(), 10) || 0,
            label: DOMPurify.sanitize(label, {USE_PROFILES: {html: false}}),
            category: DOMPurify.sanitize(category, {USE_PROFILES: {html: false}}),
            description: description,
            anyone_can_modify: $('#kb-anyone-can-modify').is(':checked') ? 1 : 0,
            allow_comments: $('#kb-allow-comments').is(':checked') ? 1 : 0,
            associated_items: associatedItems
        };

        $.post(
            'sources/kb.queries.php',
            {
                type: 'save_kb',
                data: kbEncodePayload(payload),
                key: kbSessionKey
            },
            function(response) {
                const data = kbDecodeResponse(response, 'save_kb');
                if (data.error === true) {
                    kbToastError(data.message);
                    return;
                }

                const entryId = parseInt((data.entry && data.entry.id) ? data.entry.id : (data.id || 0), 10) || 0;
                const finalizeSave = function(message) {
                    kbHideEditor();
                    loadKbList();
                    if (entryId > 0) {
                        kbOpenViewer(entryId);
                    }
                    kbToastSuccess(message || kbTranslations.kb_saved);
                };

                if (entryId > 0 && kbHasPendingAttachments() === true) {
                    $('#kb-id').val(entryId);
                    kbUploadAttachments(entryId, function() {
                        finalizeSave(kbTranslations.kb_attachment_uploaded);
                    });
                    return;
                }

                finalizeSave(kbTranslations.kb_saved);
            }
        ).fail(function() {
            kbToastError(kbTranslations.server_answer_error);
        });

        return false;
    }

    function kbBuildActions(entry) {
        let html = '<button type="button" class="btn btn-sm btn-default mr-1 kb-action-view" data-id="' + entry.id + '" title="' + kbTranslations.open + '"><i class="fa-solid fa-eye"></i></button>';

        if (entry.can_edit === true) {
            html += '<button type="button" class="btn btn-sm btn-default mr-1 kb-action-edit" data-id="' + entry.id + '" title="' + kbTranslations.edit + '"><i class="fa-solid fa-pen"></i></button>';
        }

        if (entry.can_delete === true) {
            html += '<button type="button" class="btn btn-sm btn-default kb-action-delete" data-id="' + entry.id + '" title="' + kbTranslations.delete + '"><i class="fa-solid fa-trash"></i></button>';
        }

        return html;
    }

    function loadKbList() {
        $.post(
            'sources/kb.queries.php',
            {
                type: 'list_kbs',
                data: kbEncodePayload({}),
                key: kbSessionKey
            },
            function(response) {
                const data = kbDecodeResponse(response, 'list_kbs');
                if (data.error === true) {
                    kbToastError(data.message);
                    return;
                }

                const entries = Array.isArray(data.entries) ? data.entries : [];
                kbTable.clear();

                entries.forEach(function(entry) {
                    const safeLabel = DOMPurify.sanitize(entry.label || '', {USE_PROFILES: {html: false}});
                    const safeExcerpt = DOMPurify.sanitize(entry.description_excerpt || '', {USE_PROFILES: {html: false}});
                    const safeCategory = DOMPurify.sanitize(entry.category || '', {USE_PROFILES: {html: false}});
                    const safeAuthor = DOMPurify.sanitize(entry.author || '', {USE_PROFILES: {html: false}});
                    const itemsCount = parseInt(entry.items_count || 0, 10) || 0;
                    const commentsCount = parseInt(entry.comments_count || 0, 10) || 0;
                    const metaBadges = [];

                    if (commentsCount > 0 || parseInt(entry.allow_comments || 0, 10) === 1) {
                        metaBadges.push('<span class="badge badge-light border"><i class="fa-solid fa-comments mr-1"></i>' + commentsCount + '</span>');
                    }

                    if (itemsCount > 0) {
                        metaBadges.push('<span class="badge badge-light border"><i class="fa-solid fa-link mr-1"></i>' + itemsCount + '</span>');
                    }

                    kbTable.row.add([
                        '<div class="tp-kb-list-entry">' +
                            '<a href="#" class="kb-action-view tp-kb-list-entry-title" data-id="' + entry.id + '">' + safeLabel + '</a>' +
                            (safeExcerpt !== '' ? '<div class="small tp-kb-list-entry-excerpt">' + safeExcerpt + '</div>' : '') +
                            (metaBadges.length > 0 ? '<div class="tp-kb-list-entry-meta">' + metaBadges.join('') + '</div>' : '') +
                        '</div>',
                        safeCategory !== '' ? '<span class="badge badge-info">' + safeCategory + '</span>' : '',
                        safeAuthor,
                        itemsCount,
                        kbBuildActions(entry)
                    ]);
                });

                kbTable.draw();

                if (kbDirectId > 0 && kbLoadedDirectId === false) {
                    kbOpenViewer(kbDirectId);
                }
            }
        ).fail(function() {
            kbToastError(kbTranslations.server_answer_error);
        });
    }

    $(document).ready(function() {
        kbTable = $('#table-kb-list').DataTable({
            paging: true,
            pageLength: 25,
            searching: true,
            info: true,
            responsive: false,
            scrollX: true,
            autoWidth: false,
            order: [[0, 'asc']],
            language: {
                url: '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $session->get('user-language'); ?>.txt'
            },
            columnDefs: [
                {targets: 0, className: 'kb-col-label', width: '38%'},
                {targets: 1, className: 'kb-col-category', width: '22%'},
                {targets: 2, className: 'kb-col-author', width: '20%'},
                {targets: 3, className: 'kb-col-items text-center text-nowrap', width: '90px'},
                {targets: 4, orderable: false, searchable: false, className: 'kb-col-actions text-center text-nowrap', width: '150px'}
            ]
        });

        $('#kb-associated-items').select2({
            width: '100%',
            multiple: true,
            ajax: {
                transport: function(params, success, failure) {
                    $.post(
                        'sources/kb.queries.php',
                        {
                            type: 'search_items',
                            data: kbEncodePayload({term: params.data.term || ''}),
                            key: kbSessionKey
                        },
                        function(response) {
                            const data = kbDecodeResponse(response, 'search_items');
                            success({results: data.results || []});
                        }
                    ).fail(failure);
                },
                delay: 250,
                processResults: function(data) {
                    return data;
                }
            },
            placeholder: kbTranslations.search_items_placeholder,
            minimumInputLength: 0
        });

        $('#kb-category').autocomplete({
            minLength: 0,
            source: function(request, response) {
                $.post(
                    'sources/kb.queries.php',
                    {
                        type: 'search_categories',
                        data: kbEncodePayload({term: request.term || ''}),
                        key: kbSessionKey
                    },
                    function(result) {
                        const data = kbDecodeResponse(result, 'search_categories');
                        response(data.categories || []);
                    }
                ).fail(function() {
                    response([]);
                });
            }
        }).focus(function() {
            if ($(this).val() === '') {
                $(this).autocomplete('search', '');
            }
        });

        $('#kb-attachments-input').on('change', function() {
            const files = this.files || [];
            if (!files.length) {
                $('.custom-file-label[for="kb-attachments-input"]').text(kbTranslations.select_files);
                kbRefreshAttachmentsHelp();
                return;
            }

            if (files.length === 1) {
                $('.custom-file-label[for="kb-attachments-input"]').text(files[0].name);
            } else {
                $('.custom-file-label[for="kb-attachments-input"]').text(files.length + ' ' + kbTranslations.attached_files);
            }

            kbRefreshAttachmentsHelp();
        });

        kbRefreshAttachmentsHelp();
        loadKbList();

        $('#button-kb-new').on('click', function() {
            kbOpenEditor(0);
        });

        $('#button-kb-cancel').on('click', function() {
            kbHideEditor();
        });

        $('#button-kb-close-view').on('click', function() {
            kbHideViewer();
        });

        $('#button-kb-edit-from-view').on('click', function() {
            kbOpenEditor(parseInt($(this).data('id'), 10) || 0);
        });

        $('#button-kb-delete-from-view').on('click', function() {
            kbDeleteEntry(parseInt($(this).data('id'), 10) || 0);
        });

        $('#button-kb-upload-attachments').on('click', function() {
            const kbId = parseInt($('#kb-id').val(), 10) || 0;
            if (kbId > 0) {
                kbUploadAttachments(kbId);
                return;
            }

            kbSaveEntry();
        });

        $('#button-kb-save').on('click', function() {
            kbSaveEntry();
        });

        $('#button-kb-comment-save').on('click', function() {
            kbAddComment();
        });

        $(document).on('click', '.kb-action-view', function(event) {
            event.preventDefault();
            kbOpenViewer(parseInt($(this).data('id'), 10) || 0);
        });

        $(document).on('click', '.kb-action-edit', function(event) {
            event.preventDefault();
            kbOpenEditor(parseInt($(this).data('id'), 10) || 0);
        });

        $(document).on('click', '.kb-action-delete', function(event) {
            event.preventDefault();
            kbDeleteEntry(parseInt($(this).data('id'), 10) || 0);
        });

        $(document).on('click', '.kb-action-delete-attachment', function(event) {
            event.preventDefault();
            kbDeleteAttachment(($(this).data('id') || '').toString(), parseInt($(this).data('kb-id'), 10) || 0);
        });

        $(document).on('click', '.kb-action-delete-comment', function(event) {
            event.preventDefault();
            kbDeleteComment(parseInt($(this).data('id'), 10) || 0, parseInt($(this).data('kb-id'), 10) || 0);
        });
    });

    function kbDeleteAttachment(attachmentId, kbId) {
        if (!attachmentId || !kbId) {
            return;
        }

        if (window.confirm(kbTranslations.kb_delete_attachment_confirm) !== true) {
            return;
        }

        $.post(
            'sources/kb.queries.php',
            {
                type: 'delete_attachment',
                data: kbEncodePayload({attachment_id: attachmentId, kb_id: kbId}),
                key: kbSessionKey
            },
            function(response) {
                const data = kbDecodeResponse(response, 'delete_attachment');
                if (data.error === true) {
                    kbToastError(data.message);
                    return;
                }

                kbRenderEditorAttachments((data.entry && data.entry.attachments) ? data.entry.attachments : []);
                kbToastSuccess(kbTranslations.kb_attachment_deleted);
            }
        ).fail(function() {
            kbToastError(kbTranslations.server_answer_error);
        });
    }

    function kbAddComment() {
        const kbId = parseInt($('#kb-viewer-card').data('id'), 10) || 0;
        const comment = ($('#kb-comment-text').val() || '').toString().trim();

        if (!kbId) {
            return;
        }
        if (comment === '') {
            kbToastError(kbTranslations.kb_comment_required);
            return;
        }
        if (comment.length > 3000) {
            kbToastError(kbTranslations.kb_comment_too_long);
            return;
        }

        $.post(
            'sources/kb.queries.php',
            {
                type: 'add_comment',
                data: kbEncodePayload({
                    kb_id: kbId,
                    comment: comment
                }),
                key: kbSessionKey
            },
            function(response) {
                const data = kbDecodeResponse(response, 'add_comment');
                if (data.error === true) {
                    kbToastError(data.message);
                    return;
                }

                $('#kb-comment-text').val('');
                kbRenderViewer(data.entry || {});
                kbToastSuccess(kbTranslations.kb_comment_added);
            }
        ).fail(function() {
            kbToastError(kbTranslations.server_answer_error);
        });
    }

    function kbDeleteComment(commentId, kbId) {
        if (!commentId || !kbId) {
            return;
        }

        if (window.confirm(kbTranslations.kb_comment_delete_confirm) !== true) {
            return;
        }

        $.post(
            'sources/kb.queries.php',
            {
                type: 'delete_comment',
                data: kbEncodePayload({comment_id: commentId, kb_id: kbId}),
                key: kbSessionKey
            },
            function(response) {
                const data = kbDecodeResponse(response, 'delete_comment');
                if (data.error === true) {
                    kbToastError(data.message);
                    return;
                }

                kbRenderViewer(data.entry || {});
                kbToastSuccess(kbTranslations.kb_comment_deleted);
            }
        ).fail(function() {
            kbToastError(kbTranslations.server_answer_error);
        });
    }
</script>

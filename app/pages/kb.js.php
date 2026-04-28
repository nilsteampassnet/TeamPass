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

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

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
    $checkUserAccess->userAccessPage('kb') === false
) {
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
?>
<script type='text/javascript'>
var userIsAdmin = <?php echo (int) $session->get('user-admin') === 1 ? 'true' : 'false'; ?>

// Load article list and category filter on page ready
$(function() {
    loadKbCategories()
    loadKbList(0)

    // Category filter change
    $('#kb-category-filter').on('change', function() {
        loadKbList(parseInt($(this).val()) || 0)
    })

    // New article button
    $('#kb-btn-new').on('click', function() {
        openEditModal(0)
    })

    // Trash button (admin only)
    $('#kb-btn-trash').on('click', function() {
        loadKbTrash()
        $('#kb-modal-trash').modal('show')
    })

    // Save button
    $('#kb-btn-save').on('click', function() {
        saveArticle()
    })

    // Item search button
    $('#kb-btn-item-search').on('click', function() {
        searchItemsForKb()
    })
    $('#kb-item-search').on('keypress', function(e) {
        if (e.which === 13) searchItemsForKb()
    })
})

/**
 * Load category list into the filter dropdown.
 */
function loadKbCategories() {
    $.post('sources/kb.queries.php', {
        type: 'get_categories',
        key: '<?php echo $session->get('key'); ?>'
    }, function(data) {
        const resp = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
        if (resp.error === true) return

        const $select = $('#kb-category-filter')
        $select.find('option:not(:first)').remove()

        $.each(resp.categories, function(i, cat) {
            $select.append($('<option>', { value: cat.id, text: cat.category }))
        })
    })
}

/**
 * Load article list, optionally filtered by category.
 *
 * @param {number} categoryId  0 = all categories
 */
function loadKbList(categoryId) {
    $('#kb-loading').show()
    $('#kb-articles-body').html(
        '<tr><td colspan="4" class="text-center text-muted" id="kb-loading"><i class="fas fa-spinner fa-spin mr-1"></i><?php echo $lang->get('loading_wait'); ?></td></tr>'
    )

    $.post('sources/kb.queries.php', {
        type: 'get_list',
        data: JSON.stringify({ category_id: categoryId }),
        key: '<?php echo $session->get('key'); ?>'
    }, function(data) {
        const resp = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
        const $body = $('#kb-articles-body')
        $body.empty()

        if (resp.error === true) {
            $body.html('<tr><td colspan="4" class="text-center text-danger"><?php echo $lang->get('error_could_not_load'); ?></td></tr>')
            return
        }

        if (resp.articles.length === 0) {
            $body.html('<tr><td colspan="4" class="text-center text-muted"><?php echo $lang->get('kb_no_articles'); ?></td></tr>')
            return
        }

        $.each(resp.articles, function(i, art) {
            let actions = '<button class="btn btn-xs btn-info mr-1 kb-btn-view" data-id="' + art.id + '" title="<?php echo $lang->get('view'); ?>"><i class="fas fa-eye"></i></button>'
            if (art.can_edit) {
                actions += '<button class="btn btn-xs btn-warning mr-1 kb-btn-edit" data-id="' + art.id + '" title="<?php echo $lang->get('edit'); ?>"><i class="fas fa-edit"></i></button>'
            }
            if (art.can_delete) {
                actions += '<button class="btn btn-xs btn-danger kb-btn-delete" data-id="' + art.id + '" title="<?php echo $lang->get('delete'); ?>"><i class="fas fa-trash"></i></button>'
            }

            $body.append(
                '<tr>' +
                '<td><a href="#" class="kb-btn-view" data-id="' + art.id + '">' + art.label + '</a></td>' +
                '<td>' + art.category + '</td>' +
                '<td>' + art.author + '</td>' +
                '<td class="text-right">' + actions + '</td>' +
                '</tr>'
            )
        })

        // Bind row actions
        $body.find('.kb-btn-view').on('click', function(e) {
            e.preventDefault()
            viewArticle($(this).data('id'))
        })
        $body.find('.kb-btn-edit').on('click', function() {
            openEditModal($(this).data('id'))
        })
        $body.find('.kb-btn-delete').on('click', function() {
            deleteArticle($(this).data('id'))
        })
    })
}

/**
 * Open the edit modal. id=0 for a new article.
 *
 * @param {number} id
 */
function openEditModal(id) {
    $('#kb-edit-error').addClass('d-none').text('')
    $('#kb-edit-id').val(0)
    $('#kb-edit-label').val('')
    $('#kb-edit-category').val('')
    $('#kb-edit-description').val('')
    $('#kb-edit-anyone-modify').prop('checked', false)

    if (id === 0) {
        $('#kb-modal-edit-title').text('<?php echo $lang->get('kb_new_article'); ?>')
        $('#kb-modal-edit').modal('show')
        return
    }

    $.post('sources/kb.queries.php', {
        type: 'get_entry',
        data: JSON.stringify({ id: id }),
        key: '<?php echo $session->get('key'); ?>'
    }, function(data) {
        const resp = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
        if (resp.error === true) return

        $('#kb-modal-edit-title').text('<?php echo $lang->get('kb_edit_article'); ?>')
        $('#kb-edit-id').val(resp.id)
        $('#kb-edit-label').val(resp.label)
        $('#kb-edit-category').val(resp.category)
        $('#kb-edit-description').val(resp.description)
        $('#kb-edit-anyone-modify').prop('checked', resp.anyone_can_modify === 1)
        loadLinkedItemsForEdit(resp.id, resp.can_edit)
        $('#kb-modal-edit').modal('show')
    })
}

/**
 * Submit article create/update.
 */
function saveArticle() {
    const id          = parseInt($('#kb-edit-id').val()) || 0
    const label       = $('#kb-edit-label').val().trim()
    const category    = $('#kb-edit-category').val().trim()
    const description = $('#kb-edit-description').val().trim()
    const anyoneModify= $('#kb-edit-anyone-modify').is(':checked') ? 1 : 0

    if (!label || !category || !description) {
        $('#kb-edit-error').removeClass('d-none').text('<?php echo $lang->get('missing_fields'); ?>')
        return
    }

    $('#kb-btn-save').prop('disabled', true)

    $.post('sources/kb.queries.php', {
        type: 'save_entry',
        data: JSON.stringify({
            id: id,
            label: label,
            category: category,
            description: description,
            anyone_can_modify: anyoneModify
        }),
        key: '<?php echo $session->get('key'); ?>'
    }, function(data) {
        $('#kb-btn-save').prop('disabled', false)
        const resp = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
        if (resp.error === true) {
            $('#kb-edit-error').removeClass('d-none').text('<?php echo $lang->get('error_occurred'); ?>')
            return
        }
        $('#kb-modal-edit').modal('hide')
        toastr.success('<?php echo $lang->get('kb_article_saved'); ?>')
        const categoryId = parseInt($('#kb-category-filter').val()) || 0
        loadKbCategories()
        loadKbList(categoryId)
    })
}

/**
 * View an article in a read-only modal.
 *
 * @param {number} id
 */
function viewArticle(id) {
    $.post('sources/kb.queries.php', {
        type: 'get_entry',
        data: JSON.stringify({ id: id }),
        key: '<?php echo $session->get('key'); ?>'
    }, function(data) {
        const resp = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
        if (resp.error === true) return

        $('#kb-modal-view-title').text(resp.label)
        $('#kb-view-category').text(resp.category)
        $('#kb-view-author').text(resp.author)
        // Render description — use text() to avoid XSS; replace newlines for readability
        $('#kb-view-description').text(resp.description)
        $('#kb-view-items-section').addClass('d-none')
        $('#kb-view-items-list').empty()
        loadLinkedItemsForView(resp.id)
        $('#kb-modal-view').modal('show')
    })
}

/**
 * Soft-delete an article.
 *
 * @param {number} id
 */
function deleteArticle(id) {
    if (!confirm('<?php echo $lang->get('confirm_deletion'); ?>')) return

    $.post('sources/kb.queries.php', {
        type: 'delete_entry',
        data: JSON.stringify({ id: id }),
        key: '<?php echo $session->get('key'); ?>'
    }, function(data) {
        const resp = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
        if (resp.error === true) {
            toastr.error('<?php echo $lang->get('error_occurred'); ?>')
            return
        }
        toastr.success('<?php echo $lang->get('kb_article_deleted'); ?>')
        const categoryId = parseInt($('#kb-category-filter').val()) || 0
        loadKbCategories()
        loadKbList(categoryId)
    })
}

/**
 * Load deleted articles into the trash modal.
 */
function loadKbTrash() {
    $('#kb-trash-body').html('<tr><td colspan="5" class="text-center text-muted"><?php echo $lang->get('loading_wait'); ?></td></tr>')

    $.post('sources/kb.queries.php', {
        type: 'get_deleted_list',
        key: '<?php echo $session->get('key'); ?>'
    }, function(data) {
        const resp = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
        const $body = $('#kb-trash-body')
        $body.empty()

        if (resp.error === true || resp.articles.length === 0) {
            $body.html('<tr><td colspan="5" class="text-center text-muted"><?php echo $lang->get('kb_no_articles'); ?></td></tr>')
            return
        }

        $.each(resp.articles, function(i, art) {
            $body.append(
                '<tr>' +
                '<td>' + art.label + '</td>' +
                '<td>' + art.category + '</td>' +
                '<td>' + art.author + '</td>' +
                '<td>' + art.deleted_at + '</td>' +
                '<td><button class="btn btn-xs btn-success kb-btn-restore" data-id="' + art.id + '">' +
                '<i class="fas fa-undo mr-1"></i><?php echo $lang->get('kb_restore_article'); ?></button></td>' +
                '</tr>'
            )
        })

        $body.find('.kb-btn-restore').on('click', function() {
            restoreArticle($(this).data('id'))
        })
    })
}

/**
 * Restore a soft-deleted article (admin only).
 *
 * @param {number} id
 */
function restoreArticle(id) {
    $.post('sources/kb.queries.php', {
        type: 'restore_entry',
        data: JSON.stringify({ id: id }),
        key: '<?php echo $session->get('key'); ?>'
    }, function(data) {
        const resp = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
        if (resp.error === true) {
            toastr.error('<?php echo $lang->get('error_occurred'); ?>')
            return
        }
        toastr.success('<?php echo $lang->get('kb_article_restored'); ?>')
        loadKbTrash()
        loadKbList(0)
        loadKbCategories()
        $('#kb-category-filter').val('0')
    })
}

/**
 * Load linked items into the edit modal (called when opening an existing article).
 *
 * @param {number} kbId
 * @param {boolean} canEdit
 */
function loadLinkedItemsForEdit(kbId, canEdit) {
    $.post('sources/kb.queries.php', {
        type: 'get_linked_items',
        data: JSON.stringify({ id: kbId }),
        key: '<?php echo $session->get('key'); ?>'
    }, function(data) {
        const resp = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
        const $list = $('#kb-edit-items-list')
        $list.empty()

        if (resp.error === true) return

        if (resp.items.length === 0) {
            $list.html('<span class="text-muted small"><?php echo $lang->get('kb_no_linked_items'); ?></span>')
        } else {
            $.each(resp.items, function(i, item) {
                let badge = '<span class="badge badge-info mr-1 mb-1">' +
                    '<i class="fas fa-key fa-sm mr-1"></i>' + item.label +
                    (item.folder ? ' <small class="text-light">(' + item.folder + ')</small>' : '')
                if (canEdit) {
                    badge += ' <a href="#" class="text-white ml-1 kb-item-remove" data-kb="' + kbId +
                        '" data-item="' + item.item_id + '" title="<?php echo $lang->get('delete'); ?>">' +
                        '<i class="fas fa-times"></i></a>'
                }
                badge += '</span>'
                $list.append(badge)
            })

            $list.find('.kb-item-remove').on('click', function(e) {
                e.preventDefault()
                removeItemLink($(this).data('kb'), $(this).data('item'))
            })
        }

        if (canEdit) {
            $('#kb-edit-items-section').removeClass('d-none')
        } else {
            $('#kb-edit-items-section').addClass('d-none')
        }
    })
}

/**
 * Load linked items for the view modal.
 *
 * @param {number} kbId
 */
function loadLinkedItemsForView(kbId) {
    $.post('sources/kb.queries.php', {
        type: 'get_linked_items',
        data: JSON.stringify({ id: kbId }),
        key: '<?php echo $session->get('key'); ?>'
    }, function(data) {
        const resp = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
        if (resp.error === true || resp.items.length === 0) {
            $('#kb-view-items-section').addClass('d-none')
            return
        }

        const $list = $('#kb-view-items-list')
        $list.empty()
        $.each(resp.items, function(i, item) {
            $list.append(
                '<a class="badge badge-info mr-1 mb-1" href="<?php echo $SETTINGS['cpassman_url']; ?>/index.php?page=items&id=' + item.item_id + '">' +
                '<i class="fas fa-key fa-sm mr-1"></i>' + item.label +
                (item.folder ? ' <small>(' + item.folder + ')</small>' : '') +
                '</a>'
            )
        })
        $('#kb-view-items-section').removeClass('d-none')
    })
}

/**
 * Search TeamPass items by label for the KB link picker.
 */
function searchItemsForKb() {
    const search = $('#kb-item-search').val().trim()
    if (search.length < 2) return

    const kbId = parseInt($('#kb-edit-id').val()) || 0

    $.post('sources/kb.queries.php', {
        type: 'search_items_for_kb',
        data: JSON.stringify({ search: search }),
        key: '<?php echo $session->get('key'); ?>'
    }, function(data) {
        const resp = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
        const $results = $('#kb-item-search-results')
        $results.empty()

        if (resp.error === true || resp.items.length === 0) {
            $results.html('<span class="text-muted small"><?php echo $lang->get('kb_no_items_found'); ?></span>')
            return
        }

        $.each(resp.items, function(i, item) {
            $results.append(
                '<button type="button" class="btn btn-xs btn-outline-info mr-1 mb-1 kb-item-add-btn" ' +
                'data-kb="' + kbId + '" data-item="' + item.item_id + '">' +
                '<i class="fas fa-plus fa-sm mr-1"></i>' + item.label +
                (item.folder ? ' <small>(' + item.folder + ')</small>' : '') +
                '</button>'
            )
        })

        $results.find('.kb-item-add-btn').on('click', function() {
            addItemLink($(this).data('kb'), $(this).data('item'))
        })
    })
}

/**
 * Link a TeamPass item to a KB article.
 *
 * @param {number} kbId
 * @param {number} itemId
 */
function addItemLink(kbId, itemId) {
    $.post('sources/kb.queries.php', {
        type: 'add_item_link',
        data: JSON.stringify({ kb_id: kbId, item_id: itemId }),
        key: '<?php echo $session->get('key'); ?>'
    }, function(data) {
        const resp = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
        if (resp.error === true) {
            toastr.error('<?php echo $lang->get('error_occurred'); ?>')
            return
        }
        $('#kb-item-search').val('')
        $('#kb-item-search-results').empty()
        loadLinkedItemsForEdit(kbId, true)
    })
}

/**
 * Remove a link between a KB article and an item.
 *
 * @param {number} kbId
 * @param {number} itemId
 */
function removeItemLink(kbId, itemId) {
    $.post('sources/kb.queries.php', {
        type: 'remove_item_link',
        data: JSON.stringify({ kb_id: kbId, item_id: itemId }),
        key: '<?php echo $session->get('key'); ?>'
    }, function(data) {
        const resp = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
        if (resp.error === true) {
            toastr.error('<?php echo $lang->get('error_occurred'); ?>')
            return
        }
        loadLinkedItemsForEdit(kbId, true)
    })
}
</script>

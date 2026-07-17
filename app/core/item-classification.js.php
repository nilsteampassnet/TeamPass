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
 * @file      item-classification.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;

$session = SessionManager::getSession();
$lang = new Language($session->get('user-language') ?? 'english');
?>
<script type="text/javascript">
    //<![CDATA[
    /**
     * Data Classification (F4) — item-card badge + selector.
     *
     * When an item is opened, fetches its classification label and injects a
     * colour-coded badge next to the item title. Clicking the badge (unless
     * the user is read-only) opens a small level menu; the choice is saved
     * server-side and written to the item history. Metadata only.
     */
    (function () {
        'use strict'

        var sessionKey = '<?php echo $session->get('key'); ?>'
        var isReadOnly = <?php echo (int) $session->get('user-read_only') === 1 ? 'true' : 'false'; ?>

        // Whether the currently opened item can be modified by this user.
        // Blocks classification changes when the user is globally read-only
        // or has read-only access to the item (folder-level or item-level).
        function itemIsModifiable() {
            if (isReadOnly === true) return false
            var item = store.get('teampassItem')
            return !!(item && parseInt(item.user_can_modify) === 1)
        }

        var LEVELS = {
            0: { label: <?php echo json_encode($lang->get('classification_level_unclassified'), JSON_UNESCAPED_UNICODE); ?>, color: '#6c757d' },
            1: { label: <?php echo json_encode($lang->get('classification_level_public'), JSON_UNESCAPED_UNICODE); ?>, color: '#28a745' },
            2: { label: <?php echo json_encode($lang->get('classification_level_internal'), JSON_UNESCAPED_UNICODE); ?>, color: '#17a2b8' },
            3: { label: <?php echo json_encode($lang->get('classification_level_confidential'), JSON_UNESCAPED_UNICODE); ?>, color: '#fd7e14' },
            4: { label: <?php echo json_encode($lang->get('classification_level_restricted'), JSON_UNESCAPED_UNICODE); ?>, color: '#dc3545' }
        }
        var TITLE = <?php echo json_encode($lang->get('classification'), JSON_UNESCAPED_UNICODE); ?>

        // Render (or update) the badge next to the item title.
        function renderBadge(itemId, level) {
            var $anchor = $('#card-item-label')
            if ($anchor.length === 0) return

            var info = LEVELS[level] || LEVELS[0]
            var $badge = $('#tp-classification-badge')
            if ($badge.length === 0) {
                $badge = $('<span class="badge ml-2" id="tp-classification-badge"></span>')
                $anchor.after($badge)
            }
            $badge
                .toggleClass('pointer', itemIsModifiable())
                .css({ 'background-color': info.color, 'color': '#fff' })
                .attr('title', TITLE)
                .data('item-id', itemId)
                .data('level', level)
                .html('<i class="fa-solid fa-tag mr-1"></i>' + info.label)
            $('#tp-classification-menu').remove()
        }

        // Fetch and display the classification of the currently opened item.
        function refreshClassification() {
            var item = store.get('teampassItem')
            if (!item || !item.id || parseInt(item.id) <= 0) return
            var itemId = parseInt(item.id)

            $.post('sources/classification.queries.php', {
                type: 'get_item_classification',
                item_id: itemId,
                key: sessionKey
            }, function (response) {
                var data
                try {
                    data = prepareExchangedData(response, 'decode', sessionKey)
                } catch (e) {
                    return
                }
                if (!data || data.error === true) return
                // The user may have navigated meanwhile
                var current = store.get('teampassItem')
                if (!current || parseInt(current.id) !== parseInt(data.item_id)) return
                renderBadge(parseInt(data.item_id), parseInt(data.level))
            })
        }

        // Save a new level for the current item.
        function saveClassification(itemId, level) {
            $.post('sources/classification.queries.php', {
                type: 'set_item_classification',
                item_id: itemId,
                level: level,
                key: sessionKey
            }, function (response) {
                var data
                try {
                    data = prepareExchangedData(response, 'decode', sessionKey)
                } catch (e) {
                    return
                }
                if (!data || data.error === true) {
                    toastr.remove()
                    toastr.error(data && data.message ? data.message : '', '', { timeOut: 4000 })
                    return
                }
                renderBadge(itemId, level)
                toastr.remove()
                toastr.success('<?php echo $lang->get('done'); ?>', '', { timeOut: 1000 })
            })
        }

        // Level menu on badge click.
        $(document).on('click', '#tp-classification-badge', function (e) {
            e.stopPropagation()
            if (itemIsModifiable() === false) return

            var $badge = $(this)
            var itemId = parseInt($badge.data('item-id'))
            var current = parseInt($badge.data('level'))

            var $menu = $('#tp-classification-menu')
            if ($menu.length > 0) {
                $menu.remove()
                return
            }

            var html = '<div id="tp-classification-menu" class="dropdown-menu show" style="position:absolute;z-index:1060">'
            Object.keys(LEVELS).forEach(function (level) {
                level = parseInt(level)
                html += '<a href="#" class="dropdown-item tp-classification-choice" data-level="' + level + '">' +
                    '<span class="badge mr-2" style="background-color:' + LEVELS[level].color + ';color:#fff">&nbsp;</span>' +
                    LEVELS[level].label + (level === current ? ' <i class="fa-solid fa-check ml-1"></i>' : '') +
                    '</a>'
            })
            html += '</div>'

            var $m = $(html)
            $('body').append($m)
            var offset = $badge.offset()
            $m.css({ top: offset.top + $badge.outerHeight() + 4, left: offset.left })
            $m.data('item-id', itemId)
        })

        $(document).on('click', '.tp-classification-choice', function (e) {
            e.preventDefault()
            var level = parseInt($(this).data('level'))
            var itemId = parseInt($('#tp-classification-menu').data('item-id'))
            $('#tp-classification-menu').remove()
            if (itemId > 0) saveClassification(itemId, level)
        })

        // Close the menu on any outside click.
        $(document).on('click', function () {
            $('#tp-classification-menu').remove()
        })

        // Refresh the badge every time an item detail is loaded.
        $(document).ajaxComplete(function (event, xhr, settings) {
            if (settings && settings.url && settings.url.indexOf('items.queries.php') !== -1
                && typeof settings.data === 'string'
                && settings.data.indexOf('type=show_details_item') !== -1
            ) {
                setTimeout(refreshClassification, 400)
            }
        })
    })()
    //]]>
</script>

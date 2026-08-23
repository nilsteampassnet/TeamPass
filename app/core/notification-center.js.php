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
 * @file      notification-center.js.php
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
<style>
    #tp-notification-menu {
        width: 360px;
        max-width: 92vw;
        /* Bound the whole dropdown so the footer stays reachable on short viewports. */
        max-height: calc(100vh - 70px);
    }
    /* Scoped to .show on purpose: an unscoped display would outweigh Bootstrap's
       .dropdown-menu { display: none } and leave the dropdown permanently open. */
    #tp-notification-menu.show {
        display: flex;
        flex-direction: column;
    }
    /* Header, divider and footer keep their height — only the list absorbs the constraint. */
    #tp-notification-menu > .dropdown-header,
    #tp-notification-menu > .dropdown-divider,
    #tp-notification-menu > .dropdown-footer { flex: 0 0 auto; }
    /* Keep the header and footer visible while long notification lists scroll. */
    #tp-notification-menu #tp-notification-list {
        flex: 1 1 auto;
        min-height: 0;
        max-height: 60vh;
        max-height: min(60vh, 30rem);
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-width: thin;
        scrollbar-color: rgba(108, 117, 125, 0.55) transparent;
    }
    .dark-mode #tp-notification-menu #tp-notification-list {
        scrollbar-color: rgba(255, 255, 255, 0.35) transparent;
    }
    /* WebKit rules cover Safari and Chromium < 121, which ignore scrollbar-color. */
    #tp-notification-menu #tp-notification-list::-webkit-scrollbar { width: 6px; }
    #tp-notification-menu #tp-notification-list::-webkit-scrollbar-thumb {
        background-color: rgba(108, 117, 125, 0.55);
        border-radius: 3px;
    }
    .dark-mode #tp-notification-menu #tp-notification-list::-webkit-scrollbar-thumb {
        background-color: rgba(255, 255, 255, 0.35);
    }
    #tp-notification-menu #tp-notification-list::-webkit-scrollbar-track { background: transparent; }
    #tp-notification-menu .tp-notification-row { white-space: normal; }
    #tp-notification-menu .tp-notification-when { white-space: nowrap; flex: 0 0 auto; }
    #tp-notification-menu .tp-notification-detail { word-break: break-word; }
</style>
<script type="text/javascript">
    //<![CDATA[
    /**
     * In-app Notification Centre (D2) — navbar bell + inbox.
     *
     * Injects a bell with an unread badge in the top navbar. The inbox is
     * fed server-side from whitelisted user-target events (scan results,
     * password expiry, knowledge-base publications, backup failures, task
     * completions, permission changes, keys ready) so it works with or without the
     * WebSocket daemon; when WebSocket is up, the same events refresh the
     * inbox live.
     */
    (function () {
        'use strict'

        var sessionKey = '<?php echo $session->get('key'); ?>'
        var userId = '<?php echo (int) $session->get('user-id'); ?>'

        // Stale-while-revalidate cache (per user, per tab): show the last known unread
        // count instantly on navigation, then refresh the inbox in the background. The
        // unread count only is cached — notification content is refetched, not stored.
        var CACHE_KEY = 'tp_notif_v1_' + userId
        function readNotifCache() {
            try {
                var raw = sessionStorage.getItem(CACHE_KEY)
                return raw ? JSON.parse(raw) : null
            } catch (e) { return null }
        }
        function writeNotifCache(unread) {
            try {
                sessionStorage.setItem(CACHE_KEY, JSON.stringify({ unread: parseInt(unread, 10) || 0 }))
            } catch (e) { /* ignore quota / serialization errors */ }
        }

        var L = {
            title: <?php echo json_encode($lang->get('notification_center'), JSON_UNESCAPED_UNICODE); ?>,
            empty: <?php echo json_encode($lang->get('notification_center_empty'), JSON_UNESCAPED_UNICODE); ?>,
            markAll: <?php echo json_encode($lang->get('notification_center_mark_all'), JSON_UNESCAPED_UNICODE); ?>,
            themeSecurity: <?php echo json_encode($lang->get('notification_theme_security'), JSON_UNESCAPED_UNICODE); ?>,
            themeKeys: <?php echo json_encode($lang->get('notification_theme_keys'), JSON_UNESCAPED_UNICODE); ?>,
            themeTask: <?php echo json_encode($lang->get('notification_theme_task'), JSON_UNESCAPED_UNICODE); ?>,
            themeItemEncryption: <?php echo json_encode($lang->get('notification_theme_item_encryption'), JSON_UNESCAPED_UNICODE); ?>,
            themePermission: <?php echo json_encode($lang->get('notification_theme_permission'), JSON_UNESCAPED_UNICODE); ?>,
            themePassword: <?php echo json_encode($lang->get('notification_theme_password'), JSON_UNESCAPED_UNICODE); ?>,
            themeKb: <?php echo json_encode($lang->get('notification_theme_kb'), JSON_UNESCAPED_UNICODE); ?>,
            themeBackup: <?php echo json_encode($lang->get('notification_theme_backup'), JSON_UNESCAPED_UNICODE); ?>,
            securityNudge: <?php echo json_encode($lang->get('notification_type_security_nudge'), JSON_UNESCAPED_UNICODE); ?>,
            keysReady: <?php echo json_encode($lang->get('notification_type_user_keys_ready'), JSON_UNESCAPED_UNICODE); ?>,
            statusCompleted: <?php echo json_encode($lang->get('notification_type_task_completed'), JSON_UNESCAPED_UNICODE); ?>,
            statusFailed: <?php echo json_encode($lang->get('notification_type_task_failed'), JSON_UNESCAPED_UNICODE); ?>,
            permissionChanged: <?php echo json_encode($lang->get('notification_type_folder_permission_changed'), JSON_UNESCAPED_UNICODE); ?>,
            passwordExpired: <?php echo json_encode($lang->get('notification_type_local_password_expired'), JSON_UNESCAPED_UNICODE); ?>,
            passwordExpiresTomorrow: <?php echo json_encode($lang->get('notification_type_local_password_expires_tomorrow'), JSON_UNESCAPED_UNICODE); ?>,
            passwordExpiring: <?php echo json_encode($lang->get('notification_type_local_password_expiring'), JSON_UNESCAPED_UNICODE); ?>,
            kbArticleCreated: <?php echo json_encode($lang->get('notification_type_kb_article_created'), JSON_UNESCAPED_UNICODE); ?>,
            scheduledBackupFailed: <?php echo json_encode($lang->get('notification_type_scheduled_backup_failed'), JSON_UNESCAPED_UNICODE); ?>,
            externalizedBackupFailed: <?php echo json_encode($lang->get('notification_type_externalized_backup_failed'), JSON_UNESCAPED_UNICODE); ?>
        }

        var EVENT_TYPES = [
            'security_nudge',
            'user_keys_ready',
            'task_completed',
            'folder_permission_changed',
            'local_password_expiring',
            'kb_article_created',
            'backup_failed'
        ]

        // Type -> { theme(payload), detail(payload), link } presentation map.
        // theme  = short category shown on line 1 (next to the timestamp)
        // detail = the fine-grained outcome shown on line 2
        var RENDERERS = {
            'security_nudge': {
                theme: function () { return L.themeSecurity },
                detail: function (p) { return L.securityNudge.replace('%s', String(parseInt(p.total, 10) || 0)) },
                link: 'index.php?page=dashboard'
            },
            'user_keys_ready': {
                theme: function () { return L.themeKeys },
                detail: function () { return L.keysReady },
                link: null
            },
            'task_completed': {
                theme: function (p) { return p.item_label ? L.themeItemEncryption : L.themeTask },
                detail: function (p) {
                    var status = (String(p.status) === 'failed') ? L.statusFailed : L.statusCompleted
                    var subject = p.item_label ? String(p.item_label) : (p.task_type ? String(p.task_type) : '')
                    return subject ? subject + ' — ' + status : status
                },
                link: null
            },
            'folder_permission_changed': {
                theme: function () { return L.themePermission },
                detail: function () { return L.permissionChanged },
                link: null
            },
            'local_password_expiring': {
                theme: function () { return L.themePassword },
                detail: function (p) {
                    var days = parseInt(p.days_remaining, 10) || 0
                    if (days <= 0) return L.passwordExpired
                    if (days === 1) return L.passwordExpiresTomorrow
                    return L.passwordExpiring.replace('%s', String(days))
                },
                link: 'index.php?page=profile'
            },
            'kb_article_created': {
                theme: function () { return L.themeKb },
                detail: function (p) { return L.kbArticleCreated.replace('%s', String(p.label || '')) },
                link: function (p) {
                    var kbId = parseInt(p.kb_id, 10) || 0
                    return kbId > 0 ? 'index.php?page=kb&id=' + kbId : 'index.php?page=kb'
                }
            },
            'backup_failed': {
                theme: function () { return L.themeBackup },
                detail: function (p) {
                    var template = String(p.backup_type) === 'externalized'
                        ? L.externalizedBackupFailed
                        : L.scheduledBackupFailed
                    return template.replace('%s', String(p.message || ''))
                },
                link: 'index.php?page=backups'
            }
        }

        function escapeText(value) {
            return $('<div>').text(String(value)).html()
        }

        function ensureBell() {
            var $item = $('#tp-notification-center')
            if ($item.length > 0) return $item
            var innerHtml =
                '<a class="nav-link tp-topbar-btn" data-toggle="dropdown" href="#" role="button" aria-label="' + escapeText(L.title) + '" aria-haspopup="true" aria-expanded="false">' +
                '<i class="far fa-bell"></i>' +
                '<span class="badge badge-warning navbar-badge" id="tp-notification-count" style="display:none;" aria-live="polite"></span>' +
                '</a>' +
                '<div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" id="tp-notification-menu">' +
                '<span class="dropdown-item dropdown-header"><i class="far fa-bell mr-2"></i>' + escapeText(L.title) + '</span>' +
                '<div id="tp-notification-list"></div>' +
                '<div class="dropdown-divider"></div>' +
                '<a href="#" class="dropdown-item dropdown-footer" id="tp-notification-mark-all">' + escapeText(L.markAll) + '</a>' +
                '</div>'
            // Preferred: fill the fixed server-rendered slot (deterministic order, no reflow).
            var $slot = $('#tp-slot-bell')
            if ($slot.length > 0) {
                return $slot.attr('id', 'tp-notification-center').html(innerHtml)
            }
            // Fallback: legacy prepend when the fixed slot is absent.
            var $nav = $('.main-header .navbar-nav.ml-auto').first()
            if ($nav.length === 0) return $()
            $item = $('<li class="nav-item dropdown" id="tp-notification-center">' + innerHtml + '</li>')
            $nav.prepend($item)
            return $item
        }

        function renderBadge(unread) {
            var $badge = $('#tp-notification-count')
            if (unread > 0) {
                $badge.text(unread > 99 ? '99+' : String(unread)).show()
            } else {
                $badge.hide()
            }
        }

        function renderList(rows) {
            var $list = $('#tp-notification-list')
            if (!rows || rows.length === 0) {
                $list.html('<span class="dropdown-item text-muted">' + escapeText(L.empty) + '</span>')
                return
            }
            var html = ''
            rows.forEach(function (row) {
                var renderer = RENDERERS[row.type]
                if (!renderer) return
                var payload = row.payload || {}
                var theme = escapeText(renderer.theme(payload))
                var detail = escapeText(renderer.detail(payload))
                var when = row.created_at > 0 ? new Date(row.created_at * 1000).toLocaleString() : ''
                var weight = row.is_read === 1 ? '' : ' font-weight-bold'
                var rendererLink = typeof renderer.link === 'function' ? renderer.link(payload) : renderer.link
                var href = escapeText(rendererLink || '#')
                html += '<div class="dropdown-divider"></div>' +
                    '<a href="' + href + '" class="dropdown-item tp-notification-row' + weight + '" data-id="' + parseInt(row.id, 10) + '">' +
                    '<div class="d-flex justify-content-between align-items-baseline">' +
                    '<span class="tp-notification-theme text-sm">' + theme + '</span>' +
                    '<span class="tp-notification-when text-muted text-xs ml-2">' + escapeText(when) + '</span>' +
                    '</div>' +
                    '<div class="tp-notification-detail text-sm text-muted">' + detail + '</div>' +
                    '</a>'
            })
            $list.html(html || '<span class="dropdown-item text-muted">' + escapeText(L.empty) + '</span>')
        }

        function loadInbox() {
            $.post('sources/main.queries.php', {
                type: 'notifications_list',
                type_category: 'action_user',
                key: sessionKey
            }, function (response) {
                var data
                try {
                    data = prepareExchangedData(response, 'decode', sessionKey)
                } catch (e) {
                    return
                }
                if (!data || data.error === true) return
                if (ensureBell().length === 0) return
                renderBadge(parseInt(data.unread, 10) || 0)
                renderList(data.rows)
                writeNotifCache(data.unread)
            })
        }

        function markRead(payload) {
            $.post('sources/main.queries.php', {
                type: 'notifications_mark_read',
                type_category: 'action_user',
                data: prepareExchangedData(JSON.stringify(payload), 'encode', sessionKey),
                key: sessionKey
            }, function (response) {
                var data
                try {
                    data = prepareExchangedData(response, 'decode', sessionKey)
                } catch (e) {
                    return
                }
                if (!data || data.error === true) return
                renderBadge(parseInt(data.unread, 10) || 0)
                $('.tp-notification-row').removeClass('font-weight-bold')
                writeNotifCache(data.unread)
            })
        }

        $(document).on('click', '#tp-notification-mark-all', function (e) {
            e.preventDefault()
            e.stopPropagation()
            markRead({ all: true })
        })

        $(document).on('click', '.tp-notification-row', function (e) {
            var id = parseInt($(this).data('id'), 10)
            if (id > 0) {
                markRead({ ids: [id] })
            }
            if ($(this).attr('href') === '#') {
                e.preventDefault()
            }
        })

        // Refresh the inbox live when a persistable event reaches this user.
        function wireLiveRefresh() {
            if (typeof window.tpWebSocket === 'undefined' || !window.tpWebSocket) return
            EVENT_TYPES.forEach(function (eventType) {
                window.tpWebSocket.on(eventType, function () {
                    loadInbox()
                })
            })
        }

        $(function () {
            // Show the bell immediately (server-rendered slot) and paint the last known
            // unread count from cache, so the cluster no longer pops in after each page.
            ensureBell()
            var cached = readNotifCache()
            if (cached) renderBadge(parseInt(cached.unread, 10) || 0)
            // Refresh the inbox in the background — quickly when a count was already shown.
            setTimeout(loadInbox, cached ? 250 : 1100)
            setTimeout(wireLiveRefresh, 1600)
        })
    })()
    //]]>
</script>

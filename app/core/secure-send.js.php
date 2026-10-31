<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of the TeamPass project, distributed under the GNU GPL-3.0.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 */

use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;

$session = SessionManager::getSession();
$lang = new Language($session->get('user-language') ?? 'english');
?>
<style>
    #tp-secure-send-modal .tp-my-sends-list { max-height: 60vh; overflow-y: auto; }
    #tp-secure-send-modal .tp-my-sends-label { overflow-wrap: anywhere; }
    #tp-secure-send-modal .tp-my-sends-details { min-width: 0; }
    #tp-secure-send-modal .tp-my-sends-entry { gap: .75rem; }
    @media (max-width: 575.98px) {
        #tp-secure-send-modal .tp-my-sends-entry { flex-wrap: wrap; }
        #tp-secure-send-modal .tp-my-sends-revoke { margin-left: auto; }
    }
</style>
<div class="modal fade" id="tp-secure-send-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="tp-my-sends-title">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tp-my-sends-title"><i class="fa-regular fa-paper-plane mr-2" aria-hidden="true"></i><?php echo htmlspecialchars($lang->get('secure_send_my_sends'), ENT_QUOTES, 'UTF-8'); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo htmlspecialchars($lang->get('close'), ENT_QUOTES, 'UTF-8'); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small" id="tp-my-sends-summary" aria-live="polite"></span>
                    <button type="button" class="btn btn-sm btn-default" id="tp-my-sends-refresh" title="<?php echo htmlspecialchars($lang->get('refresh'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-rotate" aria-hidden="true"></i><span class="sr-only"><?php echo htmlspecialchars($lang->get('refresh'), ENT_QUOTES, 'UTF-8'); ?></span></button>
                </div>
                <p id="tp-my-sends-status" class="text-muted mb-0" role="status" aria-live="polite"></p>
                <ul id="tp-my-sends-list" class="list-group list-group-flush tp-my-sends-list mb-0" aria-label="<?php echo htmlspecialchars($lang->get('secure_send_my_sends'), ENT_QUOTES, 'UTF-8'); ?>"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo htmlspecialchars($lang->get('close'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </div>
</div>
<script>
$(function () {
    'use strict'

    const sessionKey = <?php echo json_encode($session->get('key'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>
    const labels = {
        title: <?php echo json_encode($lang->get('secure_send_my_sends'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
        count: <?php echo json_encode($lang->get('secure_send_active_count'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
        loading: <?php echo json_encode($lang->get('secure_send_inventory_loading'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
        unavailable: <?php echo json_encode($lang->get('secure_send_inventory_unavailable'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
        empty: <?php echo json_encode($lang->get('secure_send_no_active'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
        expires: <?php echo json_encode($lang->get('secure_send_expires_on'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
        remaining: <?php echo json_encode($lang->get('secure_send_remaining_views_count'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
        protected: <?php echo json_encode($lang->get('secure_send_protected'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
        revoke: <?php echo json_encode($lang->get('secure_send_revoke'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
        confirm: <?php echo json_encode($lang->get('secure_send_revoke_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>
    }
    const $modal = $('#tp-secure-send-modal')
    const $list = $('#tp-my-sends-list')
    const $status = $('#tp-my-sends-status')
    const $refresh = $('#tp-my-sends-refresh')
    let version = 0
    let opened = false
    let disabled = false
    let mutating = false
    let lastRefresh = 0

    function updateCount(count) {
        const valid = Number.isSafeInteger(count) && count >= 0
        $('#tp-secure-send-badge').text(valid ? (count > 99 ? '99+' : String(count)) : '—').prop('hidden', valid && count === 0)
        const description = valid ? labels.count.replace('#COUNT#', String(count)) : labels.unavailable
        $('#tp-navbar-secure-send').attr('aria-label', labels.title + ' — ' + description).attr('title', labels.title + ' — ' + description)
        $('#tp-my-sends-summary').text(valid ? description : '')
    }

    function disable() {
        disabled = true
        ++version
        $('#tp-secure-send-nav').prop('hidden', true)
        $modal.modal('hide')
        $list.empty()
    }

    function showError() {
        updateCount(null)
        $list.empty()
        $status.text(labels.unavailable).removeClass('text-muted').addClass('text-danger')
    }

    function request(type, data) {
        return $.ajax({
            url: 'sources/items.queries.php', type: 'POST', dataType: 'json', timeout: 15000,
            data: { type, key: sessionKey, ...data }
        })
    }

    function revoke(send, $button) {
        if (mutating || !window.confirm(labels.confirm)) return
        mutating = true
        ++version // A preceding list/count response cannot undo the pending revocation.
        $refresh.prop('disabled', true)
        $button.prop('disabled', true)
        let succeeded = false
        request('revoke_secure_send', {
            data: prepareExchangedData(JSON.stringify({ id: send.id }), 'encode', sessionKey)
        }).done(function (data) {
            if (data && data.enabled === false) { disable(); return }
            succeeded = data && data.error === '' && data.id === send.id
            if (!succeeded) showError()
        }).fail(showError).always(function () {
            mutating = false
            $refresh.prop('disabled', false)
            $button.prop('disabled', false)
            if (succeeded) {
                $(document).trigger('tp-secure-send-changed', ['global', send.id])
                refresh(true)
            }
        })
    }

    function validSend(send) {
        return send && Number.isSafeInteger(send.id) && send.id > 0
            && typeof send.label === 'string' && typeof send.expires_label === 'string'
            && Number.isSafeInteger(send.expires_at) && send.expires_at > 0
            && Number.isSafeInteger(send.remaining_views) && send.remaining_views > 0
            && ['item', 'note'].includes(send.send_type) && [0, 1].includes(send.has_passphrase)
    }

    function render(sends) {
        $list.empty()
        updateCount(sends.length)
        $status.text(sends.length === 0 ? labels.empty : '').removeClass('text-danger').addClass('text-muted')
        sends.forEach(function (send) {
            const $entry = $('<li>', { class: 'list-group-item px-0 d-flex align-items-center tp-my-sends-entry' })
            $('<i>', { class: 'fa-solid ' + (send.send_type === 'note' ? 'fa-note-sticky' : 'fa-key') + ' text-muted', 'aria-hidden': 'true' }).appendTo($entry)
            const $details = $('<div>', { class: 'flex-grow-1 tp-my-sends-details' }).appendTo($entry)
            $('<span>', { class: 'd-block font-weight-bold tp-my-sends-label' }).text(send.label).appendTo($details)
            $('<span>', { class: 'd-block small text-muted mt-1' }).text(labels.remaining.replace('#VIEWS#', String(send.remaining_views))).appendTo($details)
            $('<span>', { class: 'd-block small text-muted' }).text(labels.expires.replace('#DATE#', send.expires_label)).appendTo($details)
            if (send.has_passphrase === 1) {
                $('<span>', { class: 'badge badge-light mt-1' }).text(labels.protected).appendTo($details)
            }
            const $button = $('<button>', { type: 'button', class: 'btn btn-sm btn-outline-danger flex-shrink-0 tp-my-sends-revoke' })
                .text(labels.revoke).attr('aria-label', labels.revoke + ' — ' + send.label).appendTo($entry)
            $button.on('click', function () { revoke(send, $button) })
            $entry.appendTo($list)
        })
    }

    function refresh(force) {
        if (disabled || mutating || document.hidden || (!force && Date.now() - lastRefresh < 5000)) return
        lastRefresh = Date.now()
        const current = ++version
        const listing = opened
        if (listing) {
            $list.empty()
            $status.text(labels.loading).removeClass('text-danger').addClass('text-muted')
            $refresh.prop('disabled', true)
        }
        request(listing ? 'list_secure_sends' : 'count_secure_sends', {}).done(function (data) {
            if (current !== version) return
            if (data && data.enabled === false) { disable(); return }
            if (!data || data.error !== '' || data.enabled !== true) { showError(); return }
            if (listing) {
                if (!Array.isArray(data.sends) || !data.sends.every(validSend)) { showError(); return }
                render(data.sends)
            } else {
                if (!Number.isSafeInteger(data.count) || data.count < 0) { showError(); return }
                updateCount(data.count)
            }
        }).fail(function () {
            if (current === version) showError()
        }).always(function () {
            if (current === version) $refresh.prop('disabled', false)
        })
    }

    $modal.on('shown.bs.modal', function () { opened = true; refresh(true) })
    $modal.on('hidden.bs.modal', function () { opened = false; ++version; $list.empty(); $status.text(''); $refresh.prop('disabled', false) })
    $refresh.on('click', function () { refresh(true) })
    $(document).on('tp-secure-send-changed', function (event, source) { if (source !== 'global') refresh(true) })
    $(document).on('visibilitychange', function () { refresh(false) })
    $(window).on('focus', function () { refresh(false) })
    window.setInterval(function () { refresh(false) }, 60000)
    refresh(true)
})
</script>

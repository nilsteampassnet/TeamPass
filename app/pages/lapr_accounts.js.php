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
 * @file      lapr_accounts.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

$session = SessionManager::getSession();
$lang = new Language($session->get('user-language') ?? 'english');
$laprSettings = (new ConfigManager())->getAllSettings();
$laprRetentionDays = (int) ($laprSettings['lapr_audit_retention_days'] ?? 365);
$laprJsJsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
$laprAccDataTablesLang = teampassDataTablesLanguage(
    (string) ($session->get('user-language') ?? 'english'),
    $lang->get('lapr_no_accounts')
);
$laprAccountTranslations = [
    'confirmDelete' => $lang->get('please_confirm_deletion'),
    'caution' => $lang->get('caution'),
    'deleteLabel' => $lang->get('delete'),
    'closeLabel' => $lang->get('close'),
    'discoverInProgress' => $lang->get('lapr_discover_in_progress'),
    'errorGeneric' => $lang->get('error'),
    'addAccount' => $lang->get('lapr_add_account'),
    'rotateConfirm' => $lang->get('lapr_rotate_confirm'),
    'rotationInProgress' => $lang->get('lapr_rotation_in_progress'),
    'rotationSuccess' => $lang->get('lapr_rotation_success'),
    'rotationFailed' => $lang->get('lapr_rotation_failed'),
    'rotationTimeout' => $lang->get('lapr_rotation_status_timeout'),
    'selfRotationWarning' => $lang->get('lapr_self_management_rotation_warning'),
    'manualResync' => $lang->get('lapr_manual_resync_required'),
    'credentialResync' => $lang->get('lapr_ssh_credential_resync_required'),
    'hostkeyMismatch' => $lang->get('lapr_hostkey_mismatch_blocked'),
    'rotateTitle' => $lang->get('lapr_rotate_now'),
    'resetTitle' => $lang->get('lapr_reset_resume'),
    'historyTitle' => $lang->get('lapr_history'),
    'historyEmpty' => $lang->get('lapr_history_empty'),
    'system' => $lang->get('lapr_system_scheduler'),
    'unknownUser' => $lang->get('lapr_unknown_user'),
    'manageDiscovered' => $lang->get('lapr_manage_discovered_account'),
    'discoveredAccountContext' => $lang->get('lapr_discovered_account_context'),
    'noMatchingItem' => $lang->get('lapr_no_matching_item'),
    'multipleMatchingItems' => $lang->get('lapr_multiple_matching_items'),
    'searchItemPlaceholder' => $lang->get('lapr_search_item_placeholder'),
    'eventRotation' => $lang->get('lapr_event_rotation'),
    'eventRotationRetry' => $lang->get('lapr_event_rotation_retry_scheduled'),
    'eventRotationSuspended' => $lang->get('lapr_event_rotation_suspended'),
    'eventAccountAdded' => $lang->get('lapr_event_account_added'),
    'eventAccountReset' => $lang->get('lapr_event_account_reset'),
    'eventCredentialSync' => $lang->get('lapr_event_ssh_credential_sync'),
    'eventHostkeyMismatch' => $lang->get('lapr_event_hostkey_mismatch'),
    'triggerManual' => $lang->get('lapr_trigger_manual'),
    'triggerScheduler' => $lang->get('lapr_trigger_scheduler'),
    'triggerEnroll' => $lang->get('lapr_trigger_enroll'),
    'resultSuccess' => $lang->get('lapr_result_success'),
    'resultFailure' => $lang->get('lapr_result_failure'),
    'resultWarning' => $lang->get('lapr_result_warning'),
    'historyRange' => $lang->get('lapr_history_range'),
    'statusActive' => $lang->get('lapr_status_active'),
    'statusPaused' => $lang->get('lapr_status_paused'),
    'statusError' => $lang->get('lapr_status_error'),
    'statusDeleted' => $lang->get('lapr_status_deleted'),
    'discoveredUser' => $lang->get('lapr_discovered_user'),
    'discoveredUid' => $lang->get('lapr_discovered_uid'),
    'discoveredShell' => $lang->get('lapr_discovered_shell'),
    'discoveryEmpty' => $lang->get('lapr_discovery_empty'),
];
?>
<script>
'use strict'

const laprAccSessionKey = <?php echo json_encode((string) $session->get('key'), $laprJsJsonFlags); ?>;
const laprAccountsUrl = 'sources/lapr_accounts.queries.php'
const LAPR_ROTATION_POLL_MAX_ATTEMPTS = 300
let laprAccountsTable = null
let laprDiscoverPollTimer = null
let laprAccountSaveInProgress = false

const laprAccLang = <?php echo json_encode($laprAccountTranslations, $laprJsJsonFlags); ?>;
const laprAccDataTablesLang = <?php echo json_encode($laprAccDataTablesLang, $laprJsJsonFlags); ?>;

const laprRetentionDays = <?php echo (int) $laprRetentionDays; ?>;
const laprRetentionNote = <?php echo json_encode($lang->get('lapr_history_retention_note'), $laprJsJsonFlags); ?>;
let laprHistoryState = { accountId: null, offset: 0, limit: 20, total: 0 }

const laprEventLabels = {
  rotation: laprAccLang.eventRotation,
  rotation_retry_scheduled: laprAccLang.eventRotationRetry,
  rotation_suspended: laprAccLang.eventRotationSuspended,
  account_add: laprAccLang.eventAccountAdded,
  account_reset: laprAccLang.eventAccountReset,
  ssh_credential_sync: laprAccLang.eventCredentialSync,
  hostkey_mismatch: laprAccLang.eventHostkeyMismatch
}
const laprTriggerLabels = {
  manual: laprAccLang.triggerManual,
  scheduler: laprAccLang.triggerScheduler,
  enroll: laprAccLang.triggerEnroll
}
const laprResultLabels = {
  success: laprAccLang.resultSuccess,
  failure: laprAccLang.resultFailure,
  warning: laprAccLang.resultWarning
}
const laprAccountStatusLabels = {
  active: laprAccLang.statusActive,
  paused: laprAccLang.statusPaused,
  error: laprAccLang.statusError,
  deleted: laprAccLang.statusDeleted
}

function laprOpenHistory(accountId) {
  laprHistoryState = { accountId: accountId, offset: 0, limit: 20, total: 0 }
  $('#modal_lapr_account_history').modal('show')
  laprLoadHistoryPage()
}

function laprLoadHistoryPage() {
  laprAccPost('list_account_history', {
    account_id: laprHistoryState.accountId,
    limit: laprHistoryState.limit,
    offset: laprHistoryState.offset
  }, function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprAccLang.errorGeneric)
      return
    }
    laprHistoryState.total = data.total
    laprRenderHistory(data)
  })
}

function laprRenderHistory(data) {
  $('#lapr_history_title').text(
    DOMPurify.sanitize(data.account.username) + ' @ ' + DOMPurify.sanitize(data.account.ep_label)
  )
  let html = ''
  if (!data.rows.length) {
    html = '<tr><td colspan="5" class="text-center text-muted">' + DOMPurify.sanitize(laprAccLang.historyEmpty) + '</td></tr>'
  } else {
    data.rows.forEach(function (r) {
      const badge = r.result === 'success' ? 'success' : (r.result === 'warning' ? 'warning' : 'danger')
      const by = r.is_system ? laprAccLang.system : (r.user_login ? DOMPurify.sanitize(r.user_login) : laprAccLang.unknownUser)
      const trigger = laprTriggerLabels[r.trigger] || r.trigger || '—'
      const result = laprResultLabels[r.result] || r.result
      html += '<tr>' +
        '<td>' + DOMPurify.sanitize(r.created_at) + '</td>' +
        '<td>' + DOMPurify.sanitize(laprEventLabels[r.action_type] || r.action_type) + '</td>' +
        '<td>' + DOMPurify.sanitize(trigger) + '</td>' +
        '<td><span class="badge badge-' + badge + '">' + DOMPurify.sanitize(result) + '</span></td>' +
        '<td>' + by + '</td>' +
        '</tr>'
      if (r.error) {
        html += '<tr><td colspan="5" class="text-danger small">↳ ' + DOMPurify.sanitize(r.error) + '</td></tr>'
      }
    })
  }
  $('#lapr_history_tbody').html(html)

  const from = data.total === 0 ? 0 : data.offset + 1
  const to = Math.min(data.offset + data.limit, data.total)
  const page = Math.floor(data.offset / data.limit) + 1
  const pages = Math.max(1, Math.ceil(data.total / data.limit))
  $('#lapr_history_range').text(
    laprAccLang.historyRange
      .replace('#start#', String(from))
      .replace('#end#', String(to))
      .replace('#total#', String(data.total))
  )
  $('#lapr_history_page').text(page + '/' + pages)
  $('#lapr_history_prev').prop('disabled', data.offset <= 0)
  $('#lapr_history_next').prop('disabled', to >= data.total)
  $('#lapr_history_retention_note').text(laprRetentionDays > 0 ? laprRetentionNote.replace('%s', laprRetentionDays) : '')
}

function laprAccPost(type, payload, onDone) {
  return $.post(laprAccountsUrl, {
    type: type,
    key: laprAccSessionKey,
    data: prepareExchangedData(JSON.stringify(payload || {}), 'encode', laprAccSessionKey)
  }, function (resp) {
    let data
    try {
      data = prepareExchangedData(resp, 'decode', laprAccSessionKey)
    } catch (e) {
      toastr.error(laprAccLang.errorGeneric)
      return
    }
    onDone(data || {})
  })
}

function laprLoadAccounts() {
  laprAccPost('list_accounts', {}, function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprAccLang.errorGeneric)
      return
    }
    const rows = (data.data || []).map(function (a) {
      return [
        DOMPurify.sanitize(a.username),
        DOMPurify.sanitize(a.endpoint),
        DOMPurify.sanitize(a.policy || '—'),
        {
          display: (a.last_rotation_at ? DOMPurify.sanitize(a.last_rotation_at) : '—') + ' ' + laprRotationBadge(a.last_rotation_status),
          timestamp: Number(a.last_rotation_at_ts || 0)
        },
        {
          display: DOMPurify.sanitize(a.next_rotation_at || '—'),
          timestamp: Number(a.next_rotation_at_ts || 0)
        },
        laprAccStatusBadge(a.status),
        laprAccountActions(a)
      ]
    })
    if (laprAccountsTable) {
      laprAccountsTable.clear().rows.add(rows).draw()
    } else {
      laprAccountsTable = $('#lapr-accounts-table').DataTable({
        data: rows,
        columnDefs: [
          {
            targets: [3, 4],
            render: function (data, type) {
              return type === 'sort' || type === 'type' ? data.timestamp : data.display
            }
          },
          { orderable: false, targets: 6 }
        ],
        language: laprAccDataTablesLang
      })
    }
  })
}

function laprRotationBadge(status) {
  if (status === 'success') { return '<span class="badge badge-success">OK</span>' }
  if (status === 'failure') {
    return '<span class="badge badge-danger">' + DOMPurify.sanitize(laprAccLang.resultFailure) + '</span>'
  }
  return ''
}

function laprAccStatusBadge(status) {
  const map = { active: 'success', paused: 'secondary', error: 'danger', deleted: 'dark' }
  return '<span class="badge badge-' + (map[status] || 'secondary') + '">' +
    DOMPurify.sanitize(laprAccountStatusLabels[status] || status) + '</span>'
}

function laprAccountActions(a) {
  const canRotate = a.status === 'active' || a.status === 'error'
  const canReset = a.status === 'paused' || a.status === 'error'
  return (canRotate ? '<button class="btn btn-xs btn-success lapr-rotate-acc" data-id="' + a.id + '" data-self-target="' + (a.is_self_target ? '1' : '0') + '" title="' + laprAccLang.rotateTitle + '"><i class="fas fa-rotate"></i></button> ' : '') +
    (canReset ? '<button class="btn btn-xs btn-warning lapr-reset-acc" data-id="' + a.id + '" title="' + laprAccLang.resetTitle + '"><i class="fas fa-rotate-left"></i></button> ' : '') +
    '<button class="btn btn-xs btn-info lapr-history-acc" data-id="' + a.id + '" title="' + laprAccLang.historyTitle + '"><i class="fas fa-clock-rotate-left"></i></button> ' +
    '<button class="btn btn-xs btn-secondary lapr-editpolicy" data-id="' + a.id + '" data-policy="' + a.policy_id + '"><i class="fas fa-scroll"></i></button> ' +
    '<button class="btn btn-xs btn-danger lapr-delete-acc" data-id="' + a.id + '"><i class="fas fa-trash"></i></button>'
}

function laprResetAccount(id) {
  laprAccPost('reset_account', { id: id }, function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprAccLang.errorGeneric)
      return
    }
    toastr.success(data.message)
    laprLoadAccounts()
  })
}

function laprRotateAccount(id, isSelfTarget) {
  const confirmation = isSelfTarget
    ? DOMPurify.sanitize(laprAccLang.selfRotationWarning) + '<br><br>' + DOMPurify.sanitize(laprAccLang.rotateConfirm)
    : laprAccLang.rotateConfirm
  launchConfirmDialog(
    laprAccLang.rotateTitle,
    DOMPurify.sanitize(confirmation),
    function () { laprStartAccountRotation(id) },
    laprAccLang.rotateTitle
  )
}

function laprStartAccountRotation(id) {
  toastr.info('<i class="fas fa-circle-notch fa-spin mr-1"></i>' + laprAccLang.rotationInProgress)
  laprAccPost('start_rotation', { id: id }, function (data) {
    if (data.error === true) {
      toastr.clear()
      toastr.error(data.message || laprAccLang.errorGeneric)
      return
    }
    laprPollRotation(data.task_id, 0)
  })
}

// Bounded polling: a background task that never reports back must not leave an
// endless request loop behind (LAPR_ROTATION_POLL_MAX_ATTEMPTS × 2 s ≈ 10 min).
function laprPollRotation(taskId, attempt) {
  laprAccPost('rotation_status', { task_id: taskId }, function (data) {
    if (data.error === true) {
      toastr.clear()
      toastr.error(data.message || laprAccLang.errorGeneric)
      return
    }
    if (data.finished !== true) {
      if (attempt + 1 >= LAPR_ROTATION_POLL_MAX_ATTEMPTS) {
        toastr.clear()
        toastr.warning(laprAccLang.rotationTimeout)
        laprLoadAccounts()
        return
      }
      setTimeout(function () { laprPollRotation(taskId, attempt + 1) }, 2000)
      return
    }
    toastr.clear()
    if (data.success === true) {
      toastr.success(laprAccLang.rotationSuccess)
    } else if (data.message_code === 'MANUAL_RESYNC_REQUIRED') {
      toastr.error(laprAccLang.manualResync)
    } else if (data.message_code === 'SSH_CREDENTIAL_RESYNC_REQUIRED') {
      toastr.error(laprAccLang.credentialResync)
    } else if (data.error_code === 'ERR_HOSTKEY_MISMATCH') {
      toastr.error(laprAccLang.hostkeyMismatch)
    } else {
      toastr.error(laprAccLang.rotationFailed + ' (' + DOMPurify.sanitize(data.error_code || '') + ')')
    }
    laprLoadAccounts()
  })
}

function laprFillSelect(sel, items, valueKey, labelKey) {
  sel.empty()
  items.forEach(function (it) {
    sel.append($('<option>').val(it[valueKey]).text(it[labelKey]))
  })
}

/**
 * Initialize the managed-item picker with the same server-side search used by
 * endpoint enrollment. An optional discovery context restricts results to the
 * exact Linux login that was discovered.
 */
function laprInitAccountItemPicker(context, onSelectionChange) {
  const sel = $('#lapr-acc-item')
  if (sel.hasClass('select2-hidden-accessible')) {
    sel.select2('destroy')
  }
  sel.off('change.laprAccount').empty().append($('<option>')).val(null)

  sel.select2({
    width: '100%',
    dropdownParent: $('#modal_lapr_account'),
    placeholder: laprAccLang.searchItemPlaceholder,
    minimumInputLength: 0,
    ajax: {
      delay: 250,
      transport: function (params, success, failure) {
        laprAccPost('search_manageable_items', {
          term: params.data.term || '',
          login: context ? context.username : ''
        }, function (data) {
          if (data.error === true) {
            toastr.error(data.message || laprAccLang.errorGeneric)
            success({ results: [] })
            return
          }
          success({ results: data.results || [] })
        }).fail(failure)
      },
      processResults: function (data) {
        return data
      }
    },
    templateResult: laprFormatAccountItemResult,
    templateSelection: laprFormatAccountItemSelection
  }).on('change.laprAccount', onSelectionChange)
}

function laprFormatAccountItemResult(item) {
  if (!item.id) { return item.text }
  const title = DOMPurify.sanitize(item.text) +
    (item.login ? ' <span class="text-muted">(' + DOMPurify.sanitize(item.login) + ')</span>' : '')
  const path = item.path
    ? '<div class="small text-muted"><i class="fas fa-folder-open mr-1"></i>' + DOMPurify.sanitize(item.path) + '</div>'
    : ''
  return $('<div>' + title + path + '</div>')
}

function laprFormatAccountItemSelection(item) {
  if (!item.id) { return item.text }
  return item.text + (item.login ? ' (' + item.login + ')' : '')
}

function laprOpenAddAccount(options) {
  const context = options && options.endpointId && options.username
    ? { endpointId: parseInt(options.endpointId, 10), username: String(options.username) }
    : null

  $('#lapr-acc-discovered-context,#lapr-acc-no-matching-item,#lapr-acc-multiple-matching-items')
    .addClass('hidden').text('')
  let endpointReady = false
  let itemReady = false
  function refreshSaveState() {
    $('#lapr-acc-save-btn').prop('disabled', endpointReady === false || itemReady === false)
  }

  $('#lapr-acc-endpoint').prop('disabled', false)
  laprAccountSaveInProgress = false
  $('#lapr-acc-save-btn').prop('disabled', true)
  laprInitAccountItemPicker(context, function () {
    itemReady = (parseInt($('#lapr-acc-item').val(), 10) || 0) > 0
    refreshSaveState()
  })

  if (context) {
    $('#lapr-acc-discovered-context')
      .removeClass('hidden')
      .text(laprAccLang.discoveredAccountContext.replace('%s', context.username))
  }

  laprAccPost('list_endpoints_options', {}, function (d) {
    if (d.error === true) {
      toastr.error(d.message || laprAccLang.errorGeneric)
      return
    }
    laprFillSelect($('#lapr-acc-endpoint'), d.data || [], 'id', 'label')
    if (context) {
      $('#lapr-acc-endpoint').val(String(context.endpointId)).prop('disabled', true)
    }
    endpointReady = (parseInt($('#lapr-acc-endpoint').val(), 10) || 0) > 0
    refreshSaveState()
  })
  if (context) {
    laprAccPost('list_manageable_items', { login: context.username }, function (d) {
      if (d.error === true) {
        toastr.error(d.message || laprAccLang.errorGeneric)
        return
      }
      const items = d.data || []
      if (!items.length) {
        $('#lapr-acc-no-matching-item').removeClass('hidden').text(laprAccLang.noMatchingItem)
        return
      }
      const first = items[0]
      const option = new Option(first.label + ' (' + first.login + ')', first.id, true, true)
      $('#lapr-acc-item').append(option).trigger('change')
      // Preselecting the first match must not hide the others: say so explicitly,
      // the picker still lets the operator search the remaining candidates.
      if (items.length > 1) {
        $('#lapr-acc-multiple-matching-items')
          .removeClass('hidden')
          .text(laprAccLang.multipleMatchingItems.replace('%d', String(items.length)))
      }
    })
  }
  laprAccPost('list_policies_options', {}, function (d) {
    if (d.error === true) {
      toastr.error(d.message || laprAccLang.errorGeneric)
      return
    }
    const sel = $('#lapr-acc-policy')
    sel.find('option:gt(0)').remove()
    ;(d.data || []).forEach(function (p) {
      sel.append($('<option>').val(p.id).text(p.label))
    })
  })
  $('#modal_lapr_account').modal('show')
}

function laprSaveAccount() {
  if (laprAccountSaveInProgress === true) { return }

  const payload = {
    endpoint_id: parseInt($('#lapr-acc-endpoint').val(), 10) || 0,
    item_id: parseInt($('#lapr-acc-item').val(), 10) || 0,
    policy_id: parseInt($('#lapr-acc-policy').val(), 10) || 0
  }
  if (payload.endpoint_id <= 0 || payload.item_id <= 0) { return }

  laprAccountSaveInProgress = true
  $('#lapr-acc-save-btn').prop('disabled', true)
  laprAccPost('add_account', payload, function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprAccLang.errorGeneric)
      return
    }
    toastr.success(data.message)
    $('#modal_lapr_account').modal('hide')
    laprLoadAccounts()
    const taskId = parseInt(data.task_id, 10) || 0
    if (taskId > 0) {
      toastr.info('<i class="fas fa-circle-notch fa-spin mr-1"></i>' + laprAccLang.rotationInProgress)
      laprPollRotation(taskId, 0)
    } else if (data.rotation_skipped_manual_only === true) {
      toastr.warning(laprAccLang.selfRotationWarning)
    }
  }).always(function () {
    laprAccountSaveInProgress = false
    if ($('#modal_lapr_account').hasClass('show')) {
      const canSave = (parseInt($('#lapr-acc-endpoint').val(), 10) || 0) > 0 &&
        (parseInt($('#lapr-acc-item').val(), 10) || 0) > 0
      $('#lapr-acc-save-btn').prop('disabled', canSave === false)
    }
  })
}

function laprOpenEditPolicy(accountId, currentPolicyId) {
  $('#lapr-editpolicy-account-id').val(accountId)
  laprAccPost('list_policies_options', {}, function (d) {
    const sel = $('#lapr-editpolicy-policy')
    sel.find('option:gt(0)').remove()
    ;(d.data || []).forEach(function (p) {
      sel.append($('<option>').val(p.id).text(p.label))
    })
    sel.val(String(currentPolicyId || 0))
    $('#modal_lapr_account_policy').modal('show')
  })
}

function laprSaveAccountPolicy() {
  const payload = {
    id: parseInt($('#lapr-editpolicy-account-id').val(), 10) || 0,
    policy_id: parseInt($('#lapr-editpolicy-policy').val(), 10) || 0
  }
  laprAccPost('update_account_policy', payload, function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprAccLang.errorGeneric)
      return
    }
    toastr.success(data.message)
    $('#modal_lapr_account_policy').modal('hide')
    laprLoadAccounts()
  })
}

function laprDeleteAccount(id) {
  launchConfirmDialog(
    '<i class="fa-solid fa-triangle-exclamation mr-2 text-warning"></i>' + laprAccLang.caution,
    DOMPurify.sanitize(laprAccLang.confirmDelete),
    function () {
      laprAccPost('delete_account', { id: id }, function (data) {
        if (data.error === true) {
          toastr.error(data.message || laprAccLang.errorGeneric)
          return
        }
        toastr.success(data.message)
        laprLoadAccounts()
      })
    },
    laprAccLang.deleteLabel,
    laprAccLang.closeLabel
  )
}

function laprOpenDiscover() {
  $('#lapr-discover-result').html('')
  laprAccPost('list_endpoints_options', {}, function (d) {
    laprFillSelect($('#lapr-discover-endpoint'), d.data || [], 'id', 'label')
  })
  $('#modal_lapr_discover').modal('show')
}

function laprStartDiscover() {
  const endpointId = parseInt($('#lapr-discover-endpoint').val(), 10) || 0
  if (!endpointId) { return }
  $('#lapr-discover-result').html('<i class="fas fa-circle-notch fa-spin mr-1"></i>' + laprAccLang.discoverInProgress)
  laprAccPost('start_discover', { endpoint_id: endpointId }, function (data) {
    if (data.error === true) {
      $('#lapr-discover-result').html('<span class="text-danger">' + DOMPurify.sanitize(data.message || laprAccLang.errorGeneric) + '</span>')
      return
    }
    laprPollDiscover(data.task_id, endpointId)
  })
}

function laprPollDiscover(taskId, endpointId) {
  if (laprDiscoverPollTimer) { clearTimeout(laprDiscoverPollTimer) }
  laprAccPost('discover_status', { task_id: taskId }, function (data) {
    if (data.error === true) {
      $('#lapr-discover-result').html('<span class="text-danger">' + DOMPurify.sanitize(data.message || laprAccLang.errorGeneric) + '</span>')
      return
    }
    if (data.finished !== true) {
      laprDiscoverPollTimer = setTimeout(function () { laprPollDiscover(taskId, endpointId) }, 1500)
      return
    }
    if (data.success !== true) {
      $('#lapr-discover-result').html('<span class="text-danger">' + DOMPurify.sanitize(data.error_code || 'error') + '</span>')
      return
    }
    const accounts = data.accounts || []
    if (!accounts.length) {
      $('#lapr-discover-result').html('<div class="text-muted">' + DOMPurify.sanitize(laprAccLang.discoveryEmpty) + '</div>')
      return
    }

    const $table = $('<table>', { class: 'table table-sm table-striped' })
    const $header = $('<tr>')
      .append($('<th>').text(laprAccLang.discoveredUser))
      .append($('<th>').text(laprAccLang.discoveredUid))
      .append($('<th>').text(laprAccLang.discoveredShell))
      .append($('<th>'))
    const $tbody = $('<tbody>')

    accounts.forEach(function (acc) {
      const $manageButton = $('<button>', {
        type: 'button',
        class: 'btn btn-xs btn-primary lapr-manage-discovered',
        title: laprAccLang.manageDiscovered,
        'data-endpoint-id': endpointId,
        'data-username': acc.username
      }).append($('<i>', { class: 'fas fa-plus mr-1' })).append(document.createTextNode(laprAccLang.manageDiscovered))
      $tbody.append(
        $('<tr>')
          .append($('<td>').text(acc.username || ''))
          .append($('<td>').text(parseInt(acc.uid, 10)))
          .append($('<td>').text(acc.shell || ''))
          .append($('<td>', { class: 'text-right' }).append($manageButton))
      )
    })
    $table.append($('<thead>').append($header)).append($tbody)
    $('#lapr-discover-result').empty().append($table)
  })
}

$(document).ready(function () {
  laprLoadAccounts()
  $('#lapr-add-account-btn').on('click', laprOpenAddAccount)
  $('#lapr-acc-save-btn').on('click', laprSaveAccount)
  $('#lapr-editpolicy-save-btn').on('click', laprSaveAccountPolicy)
  $('#lapr-discover-btn').on('click', laprOpenDiscover)
  $('#lapr-discover-start-btn').on('click', laprStartDiscover)
  $('#lapr-discover-result').on('click', '.lapr-manage-discovered', function () {
    const context = {
      endpointId: parseInt($(this).attr('data-endpoint-id'), 10),
      username: $(this).attr('data-username') || ''
    }
    $('#modal_lapr_discover').one('hidden.bs.modal', function () {
      laprOpenAddAccount(context)
    }).modal('hide')
  })
  $('#lapr-accounts-table').on('click', '.lapr-delete-acc', function () {
    laprDeleteAccount($(this).data('id'))
  })
  $('#lapr-accounts-table').on('click', '.lapr-rotate-acc', function () {
    laprRotateAccount($(this).data('id'), String($(this).attr('data-self-target')) === '1')
  })
  $('#lapr-accounts-table').on('click', '.lapr-reset-acc', function () {
    laprResetAccount($(this).data('id'))
  })
  $('#lapr-accounts-table').on('click', '.lapr-history-acc', function () {
    laprOpenHistory($(this).data('id'))
  })
  $('#lapr_history_prev').on('click', function () {
    laprHistoryState.offset = Math.max(0, laprHistoryState.offset - laprHistoryState.limit)
    laprLoadHistoryPage()
  })
  $('#lapr_history_next').on('click', function () {
    if (laprHistoryState.offset + laprHistoryState.limit < laprHistoryState.total) {
      laprHistoryState.offset += laprHistoryState.limit
      laprLoadHistoryPage()
    }
  })
  $('#lapr-accounts-table').on('click', '.lapr-editpolicy', function () {
    laprOpenEditPolicy($(this).data('id'), $(this).data('policy'))
  })
})
</script>

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

$session = SessionManager::getSession();
$lang = new Language($session->get('user-language') ?? 'english');
?>
<script>
'use strict'

const laprAccSessionKey = '<?php echo $session->get('key'); ?>'
const laprAccountsUrl = 'sources/lapr_accounts.queries.php'
let laprAccountsTable = null
let laprDiscoverPollTimer = null

const laprAccLang = {
  confirmDelete: '<?php echo addslashes($lang->get('please_confirm_deletion')); ?>',
  discoverInProgress: '<?php echo addslashes($lang->get('lapr_discover_in_progress')); ?>',
  errorGeneric: '<?php echo addslashes($lang->get('error')); ?>',
  addAccount: '<?php echo addslashes($lang->get('lapr_add_account')); ?>',
  never: '<?php echo addslashes($lang->get('lapr_last_rotation')); ?>'
}

function laprAccPost(type, payload, onDone) {
  $.post(laprAccountsUrl, {
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
        (a.last_rotation_at ? DOMPurify.sanitize(a.last_rotation_at) : '—') + ' ' + laprRotationBadge(a.last_rotation_status),
        DOMPurify.sanitize(a.next_rotation_at || '—'),
        laprAccStatusBadge(a.status),
        laprAccountActions(a)
      ]
    })
    if (laprAccountsTable) {
      laprAccountsTable.clear().rows.add(rows).draw()
    } else {
      laprAccountsTable = $('#lapr-accounts-table').DataTable({
        data: rows,
        columnDefs: [{ orderable: false, targets: 6 }],
        language: { emptyTable: 'No managed accounts yet' }
      })
    }
  })
}

function laprRotationBadge(status) {
  if (status === 'success') { return '<span class="badge badge-success">OK</span>' }
  if (status === 'failure') { return '<span class="badge badge-danger">fail</span>' }
  return ''
}

function laprAccStatusBadge(status) {
  const map = { active: 'success', paused: 'secondary', error: 'danger', deleted: 'dark' }
  return '<span class="badge badge-' + (map[status] || 'secondary') + '">' + DOMPurify.sanitize(status) + '</span>'
}

function laprAccountActions(a) {
  return '<button class="btn btn-xs btn-secondary lapr-editpolicy" data-id="' + a.id + '" data-policy="' + a.policy_id + '"><i class="fas fa-scroll"></i></button> ' +
    '<button class="btn btn-xs btn-danger lapr-delete-acc" data-id="' + a.id + '"><i class="fas fa-trash"></i></button>'
}

function laprFillSelect(sel, items, valueKey, labelKey) {
  sel.empty()
  items.forEach(function (it) {
    sel.append($('<option>').val(it[valueKey]).text(it[labelKey]))
  })
}

function laprOpenAddAccount() {
  laprAccPost('list_endpoints_options', {}, function (d) {
    laprFillSelect($('#lapr-acc-endpoint'), d.data || [], 'id', 'label')
  })
  laprAccPost('list_manageable_items', {}, function (d) {
    const sel = $('#lapr-acc-item').empty()
    ;(d.data || []).forEach(function (it) {
      sel.append($('<option>').val(it.id).text(it.label + ' (' + it.login + ')'))
    })
  })
  laprAccPost('list_policies_options', {}, function (d) {
    const sel = $('#lapr-acc-policy')
    sel.find('option:gt(0)').remove()
    ;(d.data || []).forEach(function (p) {
      sel.append($('<option>').val(p.id).text(p.label))
    })
  })
  $('#modal_lapr_account').modal('show')
}

function laprSaveAccount() {
  const payload = {
    endpoint_id: parseInt($('#lapr-acc-endpoint').val(), 10) || 0,
    item_id: parseInt($('#lapr-acc-item').val(), 10) || 0,
    policy_id: parseInt($('#lapr-acc-policy').val(), 10) || 0
  }
  laprAccPost('add_account', payload, function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprAccLang.errorGeneric)
      return
    }
    toastr.success(data.message)
    $('#modal_lapr_account').modal('hide')
    laprLoadAccounts()
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
  if (!window.confirm(laprAccLang.confirmDelete)) { return }
  laprAccPost('delete_account', { id: id }, function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprAccLang.errorGeneric)
      return
    }
    toastr.success(data.message)
    laprLoadAccounts()
  })
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
      $('#lapr-discover-result').html('<span class="text-danger">' + DOMPurify.sanitize(data.message || '') + '</span>')
      return
    }
    laprPollDiscover(data.task_id)
  })
}

function laprPollDiscover(taskId) {
  if (laprDiscoverPollTimer) { clearTimeout(laprDiscoverPollTimer) }
  laprAccPost('discover_status', { task_id: taskId }, function (data) {
    if (data.error === true) {
      $('#lapr-discover-result').html('<span class="text-danger">' + DOMPurify.sanitize(data.message || '') + '</span>')
      return
    }
    if (data.finished !== true) {
      laprDiscoverPollTimer = setTimeout(function () { laprPollDiscover(taskId) }, 1500)
      return
    }
    if (data.success !== true) {
      $('#lapr-discover-result').html('<span class="text-danger">' + DOMPurify.sanitize(data.error_code || 'error') + '</span>')
      return
    }
    let html = '<table class="table table-sm table-striped"><thead><tr><th>User</th><th>UID</th><th>Shell</th></tr></thead><tbody>'
    ;(data.accounts || []).forEach(function (acc) {
      html += '<tr><td>' + DOMPurify.sanitize(acc.username) + '</td><td>' + acc.uid + '</td><td>' + DOMPurify.sanitize(acc.shell) + '</td></tr>'
    })
    html += '</tbody></table>'
    if (!(data.accounts || []).length) {
      html = '<div class="text-muted">' + DOMPurify.sanitize(laprAccLang.errorGeneric) + '</div>'
    }
    $('#lapr-discover-result').html(html)
  })
}

$(document).ready(function () {
  laprLoadAccounts()
  $('#lapr-add-account-btn').on('click', laprOpenAddAccount)
  $('#lapr-acc-save-btn').on('click', laprSaveAccount)
  $('#lapr-editpolicy-save-btn').on('click', laprSaveAccountPolicy)
  $('#lapr-discover-btn').on('click', laprOpenDiscover)
  $('#lapr-discover-start-btn').on('click', laprStartDiscover)
  $('#lapr-accounts-table').on('click', '.lapr-delete-acc', function () {
    laprDeleteAccount($(this).data('id'))
  })
  $('#lapr-accounts-table').on('click', '.lapr-editpolicy', function () {
    laprOpenEditPolicy($(this).data('id'), $(this).data('policy'))
  })
})
</script>

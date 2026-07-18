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
 * @file      lapr_endpoints.js.php
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

const laprSessionKey = '<?php echo $session->get('key'); ?>'
const laprEndpointsUrl = 'sources/lapr_endpoints.queries.php'
let laprEndpointsTable = null
let laprTestPollTimer = null
let laprVerifiedSnapshot = null

const laprLang = {
  testInProgress: '<?php echo addslashes($lang->get('lapr_test_in_progress')); ?>',
  testSuccess: '<?php echo addslashes($lang->get('lapr_test_success')); ?>',
  testFailed: '<?php echo addslashes($lang->get('lapr_test_failed')); ?>',
  testRequired: '<?php echo addslashes($lang->get('lapr_test_required_before_save')); ?>',
  cannotRotate: '<?php echo addslashes($lang->get('lapr_endpoint_cannot_rotate')); ?>',
  confirmDelete: '<?php echo addslashes($lang->get('please_confirm_deletion')); ?>',
  hostkeyFingerprint: '<?php echo addslashes($lang->get('lapr_hostkey_fingerprint')); ?>',
  hostkeyTofu: '<?php echo addslashes($lang->get('lapr_hostkey_tofu_note')); ?>',
  errorGeneric: '<?php echo addslashes($lang->get('error')); ?>',
  searchItemPlaceholder: '<?php echo addslashes($lang->get('lapr_search_item_placeholder')); ?>'
}

// Returns the jqXHR so callers can chain .fail() (select2 transport needs it).
function laprPost(type, payload, onDone) {
  return $.post(laprEndpointsUrl, {
    type: type,
    key: laprSessionKey,
    data: prepareExchangedData(JSON.stringify(payload || {}), 'encode', laprSessionKey)
  }, function (resp) {
    let data
    try {
      data = prepareExchangedData(resp, 'decode', laprSessionKey)
    } catch (e) {
      toastr.error(laprLang.errorGeneric)
      return
    }
    onDone(data || {})
  })
}

function laprLoadEndpoints() {
  laprPost('list_endpoints', {}, function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprLang.errorGeneric)
      return
    }
    const rows = (data.data || []).map(function (ep) {
      return [
        DOMPurify.sanitize(ep.label),
        DOMPurify.sanitize(ep.hostname) + ':' + ep.port,
        DOMPurify.sanitize(ep.ssh_username),
        DOMPurify.sanitize(ep.os_name || '') + (ep.is_root ? ' <span class="badge badge-secondary">root</span>' : (ep.has_sudo ? ' <span class="badge badge-secondary">sudo</span>' : '')),
        laprStatusBadge(ep.status) + (ep.hostkey_verified === 0 ? ' <span class="badge badge-warning">no host key check</span>' : ''),
        DOMPurify.sanitize(ep.last_check_at || '—'),
        laprEndpointActions(ep)
      ]
    })
    if (laprEndpointsTable) {
      laprEndpointsTable.clear().rows.add(rows).draw()
    } else {
      laprEndpointsTable = $('#lapr-endpoints-table').DataTable({
        data: rows,
        columnDefs: [{ orderable: false, targets: 6 }],
        language: { emptyTable: 'No endpoints yet' }
      })
    }
  })
}

function laprStatusBadge(status) {
  const map = { active: 'success', disabled: 'secondary', error: 'danger', unreachable: 'warning', deleted: 'dark' }
  return '<span class="badge badge-' + (map[status] || 'secondary') + '">' + DOMPurify.sanitize(status) + '</span>'
}

function laprEndpointActions(ep) {
  return '<button class="btn btn-xs btn-danger lapr-delete-ep" data-id="' + ep.id + '"><i class="fas fa-trash"></i></button>'
}

function laprOpenEndpointModal() {
  laprVerifiedSnapshot = null
  $('#lapr-ep-label,#lapr-ep-hostname,#lapr-ep-username').val('')
  $('#lapr-ep-port').val('22')
  $('#lapr-ep-auth-method').val('password')
  $('#lapr-ep-skip-hostkey').prop('checked', false)
  $('#lapr-ep-skip-hostkey-warning').hide()
  $('#lapr-ep-test-result').hide().html('')
  $('#lapr-ep-save-btn').prop('disabled', true)
  laprInitCredentialPicker()
  $('#modal_lapr_endpoint').modal('show')
}

/**
 * Wire the credential item picker on a server-side search. The vault can hold
 * tens of thousands of items, so the list is never preloaded: select2 queries
 * the handler on each keystroke and the server returns a bounded page.
 */
function laprInitCredentialPicker() {
  const sel = $('#lapr-ep-credential-item')

  // Rebuilt on every open, so the previous selection never leaks into a new enroll.
  if (sel.hasClass('select2-hidden-accessible')) {
    sel.select2('destroy')
  }
  // select2 needs a blank first option for the placeholder to show on a single select.
  sel.empty().append($('<option>')).val(null)

  sel.select2({
    width: '100%',
    // The modal owns the stacking context, otherwise the dropdown renders behind it.
    dropdownParent: $('#modal_lapr_endpoint'),
    placeholder: laprLang.searchItemPlaceholder,
    minimumInputLength: 0,
    ajax: {
      delay: 250,
      // Custom transport: TeamPass encrypts the exchange envelope, so select2
      // cannot post to a plain url. Mirrors the #kb-associated-items picker.
      transport: function (params, success, failure) {
        laprPost('search_credential_items', { term: params.data.term || '' }, function (data) {
          if (data.error === true) {
            toastr.error(data.message || laprLang.errorGeneric)
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
    templateResult: laprFormatCredentialResult,
    templateSelection: laprFormatCredentialSelection
  })
}

/**
 * Render one search result: label + login on the first line, folder path below.
 * The path disambiguates items sharing the same label across folders.
 */
function laprFormatCredentialResult(item) {
  if (!item.id) { return item.text }
  const title = DOMPurify.sanitize(item.text) +
    (item.login ? ' <span class="text-muted">(' + DOMPurify.sanitize(item.login) + ')</span>' : '')
  const path = item.path
    ? '<div class="small text-muted"><i class="fas fa-folder-open mr-1"></i>' + DOMPurify.sanitize(item.path) + '</div>'
    : ''
  return $('<div>' + title + path + '</div>')
}

/**
 * Render the collapsed selection: label + login, no path (single line).
 */
function laprFormatCredentialSelection(item) {
  if (!item.id) { return item.text }
  return item.text + (item.login ? ' (' + item.login + ')' : '')
}

function laprCollectEndpointForm() {
  return {
    label: $('#lapr-ep-label').val().trim(),
    hostname: $('#lapr-ep-hostname').val().trim(),
    port: parseInt($('#lapr-ep-port').val(), 10) || 22,
    ssh_username: $('#lapr-ep-username').val().trim(),
    ssh_auth_method: $('#lapr-ep-auth-method').val(),
    credential_item_id: parseInt($('#lapr-ep-credential-item').val(), 10) || 0
  }
}

function laprStartTest() {
  laprVerifiedSnapshot = null
  $('#lapr-ep-save-btn').prop('disabled', true)
  const form = laprCollectEndpointForm()
  if (!form.hostname || !form.ssh_username || !form.credential_item_id) {
    toastr.warning(laprLang.errorGeneric)
    return
  }
  $('#lapr-ep-test-result').show().html('<i class="fas fa-circle-notch fa-spin mr-1"></i>' + laprLang.testInProgress)
  laprPost('start_test', form, function (data) {
    if (data.error === true) {
      $('#lapr-ep-test-result').html('<span class="text-danger">' + DOMPurify.sanitize(data.message || laprLang.testFailed) + '</span>')
      return
    }
    laprPollTest(data.task_id)
  })
}

function laprPollTest(taskId) {
  if (laprTestPollTimer) { clearTimeout(laprTestPollTimer) }
  laprPost('test_status', { task_id: taskId }, function (data) {
    if (data.error === true) {
      $('#lapr-ep-test-result').html('<span class="text-danger">' + DOMPurify.sanitize(data.message || laprLang.testFailed) + '</span>')
      return
    }
    if (data.finished !== true) {
      laprTestPollTimer = setTimeout(function () { laprPollTest(taskId) }, 1500)
      return
    }
    if (data.success !== true) {
      $('#lapr-ep-test-result').html('<span class="text-danger"><i class="fas fa-times mr-1"></i>' + laprLang.testFailed + ' (' + DOMPurify.sanitize(data.error_code || '') + ')</span>')
      return
    }
    laprVerifiedSnapshot = { snapshot: data.snapshot, snapshot_sig: data.snapshot_sig }
    let html = '<div class="text-success"><i class="fas fa-check mr-1"></i>' + laprLang.testSuccess + '</div>'
    html += '<div class="small mt-1"><strong>' + laprLang.hostkeyFingerprint + ':</strong> <code>' + DOMPurify.sanitize(data.fingerprint || '') + '</code></div>'
    html += '<div class="small text-muted">' + laprLang.hostkeyTofu + '</div>'
    if (data.can_rotate !== true) {
      html += '<div class="text-danger small mt-1">' + laprLang.cannotRotate + '</div>'
    }
    $('#lapr-ep-test-result').html(html)
    $('#lapr-ep-save-btn').prop('disabled', data.can_rotate !== true)
  })
}

function laprSaveEndpoint() {
  if (!laprVerifiedSnapshot) {
    toastr.warning(laprLang.testRequired)
    return
  }
  const form = laprCollectEndpointForm()
  form.skip_hostkey_verification = $('#lapr-ep-skip-hostkey').is(':checked') ? 1 : 0
  form.snapshot = laprVerifiedSnapshot.snapshot
  form.snapshot_sig = laprVerifiedSnapshot.snapshot_sig
  laprPost('add_endpoint', form, function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprLang.errorGeneric)
      return
    }
    toastr.success(data.message)
    $('#modal_lapr_endpoint').modal('hide')
    laprLoadEndpoints()
  })
}

function laprDeleteEndpoint(id) {
  if (!window.confirm(laprLang.confirmDelete)) { return }
  laprPost('delete_endpoint', { id: id }, function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprLang.errorGeneric)
      return
    }
    toastr.success(data.message)
    laprLoadEndpoints()
  })
}

$(document).ready(function () {
  laprLoadEndpoints()
  $('#lapr-add-endpoint-btn').on('click', laprOpenEndpointModal)
  $('#lapr-ep-test-btn').on('click', laprStartTest)
  $('#lapr-ep-save-btn').on('click', laprSaveEndpoint)
  $('#lapr-ep-skip-hostkey').on('change', function () {
    $('#lapr-ep-skip-hostkey-warning').toggle($(this).is(':checked'))
  })
  $('#lapr-endpoints-table').on('click', '.lapr-delete-ep', function () {
    laprDeleteEndpoint($(this).data('id'))
  })
})
</script>

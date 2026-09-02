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
$laprEndpointJsJsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
$laprEndpointDataTablesLang = teampassDataTablesLanguage(
    (string) ($session->get('user-language') ?? 'english'),
    $lang->get('lapr_no_endpoints')
);
$laprEndpointTranslations = [
    'testInProgress' => $lang->get('lapr_test_in_progress'),
    'testSuccess' => $lang->get('lapr_test_success'),
    'testFailed' => $lang->get('lapr_test_failed'),
    'testRequired' => $lang->get('lapr_test_required_before_save'),
    'cannotRotate' => $lang->get('lapr_endpoint_cannot_rotate'),
    'confirmDelete' => $lang->get('please_confirm_deletion'),
    'caution' => $lang->get('caution'),
    'deleteLabel' => $lang->get('delete'),
    'closeLabel' => $lang->get('close'),
    'hostkeyFingerprint' => $lang->get('lapr_hostkey_fingerprint'),
    'hostkeyTofu' => $lang->get('lapr_hostkey_tofu_note'),
    'hostkeyUnchecked' => $lang->get('lapr_no_hostkey_check'),
    'errorGeneric' => $lang->get('error'),
    'searchItemPlaceholder' => $lang->get('lapr_search_item_placeholder'),
    'remediationTitle' => $lang->get('lapr_remediation_title'),
    'remediationIntro' => $lang->get('lapr_remediation_intro'),
    'remediationUnknownOs' => $lang->get('lapr_remediation_unknown_os'),
    'remediationInstallPackages' => $lang->get('lapr_remediation_install_packages'),
    'remediationFromTeamPass' => $lang->get('lapr_remediation_from_teampass'),
    'remediationFromEndpoint' => $lang->get('lapr_remediation_from_endpoint'),
    'remediationCheckCommands' => $lang->get('lapr_remediation_check_commands'),
    'remediationPrerequisites' => $lang->get('lapr_remediation_prerequisites'),
    'remediationRootNoSudo' => $lang->get('lapr_remediation_root_no_sudo'),
    'remediationGrantPrivilege' => $lang->get('lapr_remediation_grant_privilege'),
    'detectedOs' => $lang->get('lapr_detected_os'),
    'copyCommands' => $lang->get('lapr_copy_commands'),
    'commandsCopied' => $lang->get('lapr_commands_copied'),
    'statusActive' => $lang->get('lapr_status_active'),
    'statusDisabled' => $lang->get('lapr_status_disabled'),
    'statusError' => $lang->get('lapr_status_error'),
    'statusUnreachable' => $lang->get('lapr_status_unreachable'),
    'statusDeleted' => $lang->get('lapr_status_deleted'),
    'selfManagementAckRequired' => $lang->get('lapr_self_management_ack_required'),
    'checkEndpoint' => $lang->get('lapr_check_endpoint'),
    'checkSuccess' => $lang->get('lapr_endpoint_check_success'),
    'checkReachableNoRights' => $lang->get('lapr_endpoint_check_reachable_no_rights'),
    'lastError' => $lang->get('lapr_last_error'),
    'pauseEndpoint' => $lang->get('lapr_pause_endpoint'),
    'resumeEndpoint' => $lang->get('lapr_resume_endpoint'),
    'resumeConfirm' => $lang->get('lapr_resume_endpoint_confirm'),
    'resumeSuccess' => $lang->get('lapr_resume_endpoint_success'),
    'resumeFailed' => $lang->get('lapr_resume_endpoint_failed'),
    'resumeDueAccounts' => $lang->get('lapr_resume_due_accounts'),
    'statusPaused' => $lang->get('lapr_endpoint_status_paused'),
];
?>
<script>
'use strict'

const laprSessionKey = <?php echo json_encode((string) $session->get('key'), $laprEndpointJsJsonFlags); ?>;
const laprEndpointsUrl = 'sources/lapr_endpoints.queries.php'
let laprEndpointsTable = null
let laprTestPollTimer = null
let laprVerifiedSnapshot = null
let laprRemediationCommands = ''
let laprEndpointRows = {}
let laprEndpointCheckTimer = null
let laprPauseEndpointId = 0

const laprLang = <?php echo json_encode($laprEndpointTranslations, $laprEndpointJsJsonFlags); ?>;
const laprEndpointDataTablesLang = <?php echo json_encode($laprEndpointDataTablesLang, $laprEndpointJsJsonFlags); ?>;

function laprHtml(value) {
  return $('<div>').text(value === null || value === undefined ? '' : String(value)).html()
}

function laprSafeSshUsername(value) {
  const username = (value || $('#lapr-ep-username').val() || '').toString().trim()
  return /^[a-z_][a-z0-9_.-]{0,63}\$?$/i.test(username) ? username : '<ssh-user>'
}

function laprPackageCommands(osFamily) {
  const commands = {
    debian: ['sudo apt-get update', 'sudo apt-get install -y passwd sudo'],
    rhel: ['sudo dnf install -y shadow-utils sudo'],
    suse: ['sudo zypper install -y shadow sudo'],
    arch: ['sudo pacman -S --needed shadow sudo'],
    alpine: ['sudo apk add shadow sudo'],
    generic: ['# ' + laprLang.remediationInstallPackages]
  }
  return commands[osFamily] || commands.generic
}

function laprRenderRemediation(commands, osName, connectionFailed) {
  laprRemediationCommands = commands.join('\n')
  let html = '<div class="alert alert-warning mt-2 mb-0">'
  html += '<div><strong><i class="fas fa-terminal mr-1"></i>' + laprHtml(laprLang.remediationTitle) + '</strong></div>'
  if (osName) {
    html += '<div class="small mt-1"><strong>' + laprHtml(laprLang.detectedOs) + ':</strong> ' + laprHtml(osName) + '</div>'
  }
  html += '<div class="small mt-1">' + laprHtml(connectionFailed ? laprLang.remediationUnknownOs : laprLang.remediationIntro) + '</div>'
  html += '<pre class="small bg-dark text-light p-2 mt-2 mb-2" style="white-space:pre-wrap"><code>' + laprHtml(laprRemediationCommands) + '</code></pre>'
  html += '<button type="button" class="btn btn-xs btn-outline-dark" id="lapr-copy-remediation"><i class="far fa-copy mr-1"></i>' + laprHtml(laprLang.copyCommands) + '</button>'
  html += '</div>'
  return html
}

function laprBuildConnectionRemediation(errorCode, endpoint) {
  const form = endpoint || laprCollectEndpointForm()
  const hostname = /^[a-z0-9_.:-]+$/i.test(form.hostname) ? form.hostname : '<hostname>'
  const username = laprSafeSshUsername(form.ssh_username)
  const commands = [
    '# ' + laprLang.remediationFromTeamPass,
    'getent hosts ' + hostname,
    'nc -vz ' + hostname + ' ' + form.port,
    '',
    '# ' + laprLang.remediationFromEndpoint,
    'sudo systemctl status ssh || sudo systemctl status sshd',
    'sudo ss -lntp',
    'id ' + username
  ]
  if (errorCode === 'ERR_AUTH_FAILED') {
    commands.push('sudo sshd -T | grep -E "^(passwordauthentication|pubkeyauthentication|permitrootlogin)"')
    commands.push('sudo journalctl -u ssh -u sshd -n 50 --no-pager')
  }
  return laprRenderRemediation(commands, '', true)
}

function laprBuildCapabilityRemediation(data, endpoint) {
  const osInfo = data.os_info || {}
  const username = laprSafeSshUsername(endpoint ? endpoint.ssh_username : '')
  const family = ['debian', 'rhel', 'suse', 'arch', 'alpine'].indexOf(osInfo.os_family) !== -1
    ? osInfo.os_family
    : 'generic'
  const familyLabels = {
    debian: 'Debian / Ubuntu',
    rhel: 'RHEL / Fedora',
    suse: 'SUSE',
    arch: 'Arch Linux',
    alpine: 'Alpine Linux',
    generic: 'Linux'
  }
  const commands = [
    '# ' + laprLang.remediationCheckCommands,
    'command -v chpasswd',
    'command -v sudo',
    '',
    '# ' + familyLabels[family] + ' — ' + laprLang.remediationPrerequisites
  ].concat(laprPackageCommands(family))

  if (osInfo.is_root === true) {
    commands.push('', '# ' + laprLang.remediationRootNoSudo, 'chpasswd </dev/null; echo $?')
  } else {
    commands.push(
      '',
      '# ' + laprLang.remediationGrantPrivilege,
      'CHPASSWD_PATH="$(command -v chpasswd)"',
      'test -n "$CHPASSWD_PATH" || { echo "chpasswd not found" >&2; exit 1; }',
      'printf \'%s\\n\' \'' + username + ' ALL=(root) NOPASSWD: \'"$CHPASSWD_PATH" | sudo tee /etc/sudoers.d/teampass-lapr >/dev/null',
      'sudo chmod 0440 /etc/sudoers.d/teampass-lapr',
      'sudo visudo -cf /etc/sudoers.d/teampass-lapr',
      'sudo -n chpasswd </dev/null; echo $?'
    )
  }
  return laprRenderRemediation(commands, osInfo.os_name || '', false)
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
    laprEndpointRows = {}
    ;(data.data || []).forEach(function (ep) {
      laprEndpointRows[ep.id] = ep
    })
    const rows = (data.data || []).map(function (ep) {
      return [
        DOMPurify.sanitize(ep.label),
        DOMPurify.sanitize(ep.hostname) + ':' + ep.port,
        DOMPurify.sanitize(ep.ssh_username),
        DOMPurify.sanitize(ep.os_name || '') + (ep.is_root ? ' <span class="badge badge-secondary">root</span>' : (ep.has_sudo ? ' <span class="badge badge-secondary">sudo</span>' : '')),
        laprStatusBadge(ep.status) + (ep.hostkey_verified === 0
          ? ' <span class="badge badge-warning">' + DOMPurify.sanitize(laprLang.hostkeyUnchecked) + '</span>'
          : '') + (ep.last_error
          ? '<div class="small ' + (ep.status === 'disabled' ? 'text-muted' : 'text-danger') + ' mt-1">' + laprHtml(laprLang.lastError) + ': <code>' + laprHtml(ep.last_error) + '</code></div>'
          : ''),
        {
          display: DOMPurify.sanitize(ep.last_check_at || '—'),
          timestamp: Number(ep.last_check_at_ts || 0)
        },
        laprEndpointActions(ep)
      ]
    })
    if (laprEndpointsTable) {
      laprEndpointsTable.clear().rows.add(rows).draw()
    } else {
      laprEndpointsTable = $('#lapr-endpoints-table').DataTable({
        data: rows,
        columnDefs: [
          {
            targets: 5,
            render: function (data, type) {
              return type === 'sort' || type === 'type' ? data.timestamp : data.display
            }
          },
          { orderable: false, targets: 6 }
        ],
        language: laprEndpointDataTablesLang
      })
    }
  })
}

function laprStatusBadge(status) {
  const map = { active: 'success', disabled: 'secondary', error: 'danger', unreachable: 'warning', deleted: 'dark' }
  const labels = {
    active: laprLang.statusActive,
    disabled: laprLang.statusPaused,
    error: laprLang.statusError,
    unreachable: laprLang.statusUnreachable,
    deleted: laprLang.statusDeleted
  }
  return '<span class="badge badge-' + (map[status] || 'secondary') + '">' +
    DOMPurify.sanitize(labels[status] || status) + '</span>'
}

function laprEndpointActions(ep) {
  const pauseAction = ep.status === 'disabled'
    ? '<button class="btn btn-xs btn-success lapr-resume-ep" data-id="' + ep.id + '" title="' + laprHtml(laprLang.resumeEndpoint) + '"><i class="fas fa-play"></i></button>'
    : '<button class="btn btn-xs btn-warning lapr-pause-ep" data-id="' + ep.id + '" title="' + laprHtml(laprLang.pauseEndpoint) + '"><i class="fas fa-pause"></i></button>'
  return '<div class="btn-group btn-group-sm" role="group">' +
    '<button class="btn btn-xs btn-info lapr-check-ep" data-id="' + ep.id + '" title="' + laprHtml(laprLang.checkEndpoint) + '"><i class="fas fa-sync-alt"></i></button>' +
    pauseAction +
    '<button class="btn btn-xs btn-danger lapr-delete-ep" data-id="' + ep.id + '" title="' + laprHtml(laprLang.deleteLabel) + '"><i class="fas fa-trash"></i></button>' +
    '</div>'
}

function laprStartEndpointCheck(id, resume) {
  const endpoint = laprEndpointRows[id]
  if (!endpoint) { return }

  if (laprEndpointCheckTimer) { clearTimeout(laprEndpointCheckTimer) }
  $('.lapr-check-ep[data-id="' + id + '"],.lapr-resume-ep[data-id="' + id + '"]').prop('disabled', true)
  $('#lapr-endpoint-check-result').html('<div class="text-info"><i class="fas fa-circle-notch fa-spin mr-1"></i>' + laprHtml(laprLang.testInProgress) + '</div>')
  $('#modal_lapr_endpoint_check').modal('show')

  laprPost(resume ? 'resume_endpoint' : 'start_check', { id: id }, function (data) {
    if (data.error === true) {
      $('.lapr-check-ep[data-id="' + id + '"],.lapr-resume-ep[data-id="' + id + '"]').prop('disabled', false)
      $('#lapr-endpoint-check-result').html('<div class="text-danger">' + laprHtml(data.message || laprLang.testFailed) + '</div>')
      return
    }
    laprPollEndpointCheck(data.task_id, id)
  })
}

function laprPollEndpointCheck(taskId, endpointId) {
  laprPost('check_status', { task_id: taskId }, function (data) {
    if (data.error === true) {
      $('.lapr-check-ep[data-id="' + endpointId + '"],.lapr-resume-ep[data-id="' + endpointId + '"]').prop('disabled', false)
      $('#lapr-endpoint-check-result').html('<div class="text-danger">' + laprHtml(data.message || laprLang.testFailed) + '</div>')
      return
    }
    if (data.finished !== true) {
      laprEndpointCheckTimer = setTimeout(function () {
        laprPollEndpointCheck(taskId, endpointId)
      }, 1500)
      return
    }

    $('.lapr-check-ep[data-id="' + endpointId + '"],.lapr-resume-ep[data-id="' + endpointId + '"]').prop('disabled', false)
    const endpoint = laprEndpointRows[endpointId] || {}
    let html = ''
    if (data.success === true) {
      const successMessage = data.resumed === true ? laprLang.resumeSuccess : laprLang.checkSuccess
      html = '<div class="alert alert-success mb-0"><i class="fas fa-check mr-1"></i>' + laprHtml(successMessage) + '</div>'
      if (data.os_info && data.os_info.os_name) {
        html += '<div class="small mt-2"><strong>' + laprHtml(laprLang.detectedOs) + ':</strong> ' + laprHtml(data.os_info.os_name) + '</div>'
      }
      if (data.resumed === true && Number(data.due_accounts || 0) > 0) {
        html += '<div class="alert alert-info py-2 mt-2 mb-0">' + laprHtml(
          laprLang.resumeDueAccounts.replace('#count#', String(data.due_accounts))
        ) + '</div>'
      }
    } else if (data.reachable === true) {
      html = '<div class="alert alert-danger"><i class="fas fa-user-shield mr-1"></i>' + laprHtml(laprLang.checkReachableNoRights) + '</div>'
      if (data.resume_requested === true) {
        html += '<div class="alert alert-warning py-2">' + laprHtml(laprLang.resumeFailed) + '</div>'
      }
      html += laprBuildCapabilityRemediation(data, endpoint)
    } else {
      html = '<div class="alert alert-danger"><i class="fas fa-times mr-1"></i>' + laprHtml(laprLang.testFailed) +
        (data.error_code ? ' (' + laprHtml(data.error_code) + ')' : '') + '</div>'
      if (data.resume_requested === true) {
        html += '<div class="alert alert-warning py-2">' + laprHtml(laprLang.resumeFailed) + '</div>'
      }
      html += laprBuildConnectionRemediation(data.error_code || '', endpoint)
    }
    $('#lapr-endpoint-check-result').html(html)
    laprLoadEndpoints()
  })
}

function laprOpenPauseEndpoint(id) {
  if (!laprEndpointRows[id]) { return }
  laprPauseEndpointId = Number(id)
  $('#lapr-endpoint-pause-reason').val('maintenance')
  $('#modal_lapr_endpoint_pause').modal('show')
}

function laprConfirmPauseEndpoint() {
  if (laprPauseEndpointId <= 0) { return }
  const id = laprPauseEndpointId
  $('#lapr-endpoint-pause-confirm').prop('disabled', true)
  laprPost('pause_endpoint', {
    id: id,
    reason: $('#lapr-endpoint-pause-reason').val()
  }, function (data) {
    $('#lapr-endpoint-pause-confirm').prop('disabled', false)
    if (data.error === true) {
      toastr.error(data.message || laprLang.errorGeneric)
      return
    }
    $('#modal_lapr_endpoint_pause').modal('hide')
    toastr.success(data.message)
    laprLoadEndpoints()
  })
}

function laprResumeEndpoint(id) {
  launchConfirmDialog(
    laprLang.resumeEndpoint,
    DOMPurify.sanitize(laprLang.resumeConfirm),
    function () { laprStartEndpointCheck(id, true) },
    laprLang.resumeEndpoint,
    laprLang.closeLabel
  )
}

function laprOpenEndpointModal() {
  laprVerifiedSnapshot = null
  $('#lapr-ep-label,#lapr-ep-hostname,#lapr-ep-username').val('')
  $('#lapr-ep-port').val('22')
  $('#lapr-ep-auth-method').val('password')
  $('#lapr-ep-skip-hostkey').prop('checked', false)
  $('#lapr-ep-skip-hostkey-warning').hide()
  $('#lapr-ep-self-management-ack').prop('checked', false)
  $('#lapr-ep-self-management-warning').hide()
  $('#lapr-ep-test-result').hide().html('')
  $('#lapr-ep-save-btn').prop('disabled', true)
  laprInitCredentialPicker()
  $('#modal_lapr_endpoint').modal('show')
}

function laprRefreshEndpointSaveState() {
  const snapshot = laprVerifiedSnapshot && laprVerifiedSnapshot.snapshot
    ? laprVerifiedSnapshot.snapshot
    : null
  const canRotate = !!(snapshot && snapshot.can_rotate === true)
  const isSelfTarget = !!(snapshot && snapshot.self_target && snapshot.self_target.is_self === true)
  const selfTargetAccepted = !isSelfTarget || $('#lapr-ep-self-management-ack').is(':checked')
  $('#lapr-ep-save-btn').prop('disabled', !canRotate || !selfTargetAccepted)
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

  sel.off('select2:open.laprEndpoint').on('select2:open.laprEndpoint', function () {
    window.setTimeout(function () {
      const searchField = document.querySelector('.select2-container--open .select2-search__field')
      if (searchField) { searchField.focus() }
    }, 0)
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
      let html = '<span class="text-danger"><i class="fas fa-times mr-1"></i>' + laprHtml(laprLang.testFailed) + ' (' + laprHtml(data.error_code || '') + ')</span>'
      html += laprBuildConnectionRemediation(data.error_code || '')
      $('#lapr-ep-test-result').html(html)
      return
    }
    laprVerifiedSnapshot = { snapshot: data.snapshot, snapshot_sig: data.snapshot_sig }
    const isSelfTarget = !!(data.self_target && data.self_target.is_self === true)
    $('#lapr-ep-self-management-ack').prop('checked', false)
    $('#lapr-ep-self-management-warning').toggle(isSelfTarget)
    let html = '<div class="text-success"><i class="fas fa-check mr-1"></i>' + laprLang.testSuccess + '</div>'
    html += '<div class="small mt-1"><strong>' + laprLang.hostkeyFingerprint + ':</strong> <code>' + DOMPurify.sanitize(data.fingerprint || '') + '</code></div>'
    html += '<div class="small text-muted">' + laprLang.hostkeyTofu + '</div>'
    if (data.can_rotate !== true) {
      html += '<div class="text-danger small mt-1">' + laprHtml(laprLang.cannotRotate) + '</div>'
      html += laprBuildCapabilityRemediation(data)
    }
    $('#lapr-ep-test-result').html(html)
    laprRefreshEndpointSaveState()
  })
}

function laprSaveEndpoint() {
  if (!laprVerifiedSnapshot) {
    toastr.warning(laprLang.testRequired)
    return
  }
  const form = laprCollectEndpointForm()
  const isSelfTarget = !!(laprVerifiedSnapshot.snapshot.self_target && laprVerifiedSnapshot.snapshot.self_target.is_self === true)
  if (isSelfTarget && !$('#lapr-ep-self-management-ack').is(':checked')) {
    toastr.warning(laprLang.selfManagementAckRequired)
    return
  }
  form.skip_hostkey_verification = $('#lapr-ep-skip-hostkey').is(':checked') ? 1 : 0
  form.self_management_ack = $('#lapr-ep-self-management-ack').is(':checked') ? 1 : 0
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
  launchConfirmDialog(
    '<i class="fa-solid fa-triangle-exclamation mr-2 text-warning"></i>' + laprLang.caution,
    DOMPurify.sanitize(laprLang.confirmDelete),
    function () {
      laprPost('delete_endpoint', { id: id }, function (data) {
        if (data.error === true) {
          toastr.error(data.message || laprLang.errorGeneric)
          return
        }
        toastr.success(data.message)
        laprLoadEndpoints()
      })
    },
    laprLang.deleteLabel,
    laprLang.closeLabel
  )
}

$(document).ready(function () {
  laprLoadEndpoints()
  $('#lapr-add-endpoint-btn').on('click', laprOpenEndpointModal)
  $('#lapr-ep-test-btn').on('click', laprStartTest)
  $('#lapr-ep-save-btn').on('click', laprSaveEndpoint)
  $('#lapr-ep-skip-hostkey').on('change', function () {
    $('#lapr-ep-skip-hostkey-warning').toggle($(this).is(':checked'))
  })
  $('#lapr-ep-self-management-ack').on('change', laprRefreshEndpointSaveState)
  $(document).on('click', '#lapr-copy-remediation', function () {
    if (!laprRemediationCommands) { return }
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(laprRemediationCommands).then(function () {
        toastr.success(laprLang.commandsCopied)
      })
      return
    }
    const $copyArea = $('<textarea>').val(laprRemediationCommands).appendTo('body').select()
    document.execCommand('copy')
    $copyArea.remove()
    toastr.success(laprLang.commandsCopied)
  })
  $('#lapr-endpoints-table').on('click', '.lapr-delete-ep', function () {
    laprDeleteEndpoint($(this).data('id'))
  })
  $('#lapr-endpoints-table').on('click', '.lapr-check-ep', function () {
    laprStartEndpointCheck($(this).data('id'), false)
  })
  $('#lapr-endpoints-table').on('click', '.lapr-pause-ep', function () {
    laprOpenPauseEndpoint($(this).data('id'))
  })
  $('#lapr-endpoints-table').on('click', '.lapr-resume-ep', function () {
    laprResumeEndpoint($(this).data('id'))
  })
  $('#lapr-endpoint-pause-confirm').on('click', laprConfirmPauseEndpoint)
  $('#modal_lapr_endpoint_pause').on('hidden.bs.modal', function () {
    laprPauseEndpointId = 0
    $('#lapr-endpoint-pause-confirm').prop('disabled', false)
  })
})
</script>

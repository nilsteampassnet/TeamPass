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
$laprEndpointDataTablesLanguage = basename(strtolower((string) ($session->get('user-language') ?? 'english')));
$laprEndpointDataTablesLanguageFile = TEAMPASS_PUBLIC
    . '/includes/language/datatables.'
    . $laprEndpointDataTablesLanguage
    . '.txt';
if (is_file($laprEndpointDataTablesLanguageFile) === false) {
    $laprEndpointDataTablesLanguageFile = TEAMPASS_PUBLIC . '/includes/language/datatables.english.txt';
}
$laprEndpointDataTablesLang = json_decode(
    (string) file_get_contents($laprEndpointDataTablesLanguageFile),
    true
);
if (is_array($laprEndpointDataTablesLang) === false) {
    $laprEndpointDataTablesLang = [];
}
$laprEndpointDataTablesLang['sEmptyTable'] = $lang->get('lapr_no_endpoints');
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

const laprLang = <?php echo json_encode($laprEndpointTranslations, $laprEndpointJsJsonFlags); ?>;
const laprEndpointDataTablesLang = <?php echo json_encode($laprEndpointDataTablesLang, $laprEndpointJsJsonFlags); ?>;

function laprHtml(value) {
  return $('<div>').text(value === null || value === undefined ? '' : String(value)).html()
}

function laprSafeSshUsername() {
  const username = ($('#lapr-ep-username').val() || '').toString().trim()
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

function laprBuildConnectionRemediation(errorCode) {
  const form = laprCollectEndpointForm()
  const hostname = /^[a-z0-9_.:-]+$/i.test(form.hostname) ? form.hostname : '<hostname>'
  const username = laprSafeSshUsername()
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

function laprBuildCapabilityRemediation(data) {
  const osInfo = data.os_info || {}
  const username = laprSafeSshUsername()
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
    const rows = (data.data || []).map(function (ep) {
      return [
        DOMPurify.sanitize(ep.label),
        DOMPurify.sanitize(ep.hostname) + ':' + ep.port,
        DOMPurify.sanitize(ep.ssh_username),
        DOMPurify.sanitize(ep.os_name || '') + (ep.is_root ? ' <span class="badge badge-secondary">root</span>' : (ep.has_sudo ? ' <span class="badge badge-secondary">sudo</span>' : '')),
        laprStatusBadge(ep.status) + (ep.hostkey_verified === 0
          ? ' <span class="badge badge-warning">' + DOMPurify.sanitize(laprLang.hostkeyUnchecked) + '</span>'
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
    disabled: laprLang.statusDisabled,
    error: laprLang.statusError,
    unreachable: laprLang.statusUnreachable,
    deleted: laprLang.statusDeleted
  }
  return '<span class="badge badge-' + (map[status] || 'secondary') + '">' +
    DOMPurify.sanitize(labels[status] || status) + '</span>'
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
    let html = '<div class="text-success"><i class="fas fa-check mr-1"></i>' + laprLang.testSuccess + '</div>'
    html += '<div class="small mt-1"><strong>' + laprLang.hostkeyFingerprint + ':</strong> <code>' + DOMPurify.sanitize(data.fingerprint || '') + '</code></div>'
    html += '<div class="small text-muted">' + laprLang.hostkeyTofu + '</div>'
    if (data.can_rotate !== true) {
      html += '<div class="text-danger small mt-1">' + laprHtml(laprLang.cannotRotate) + '</div>'
      html += laprBuildCapabilityRemediation(data)
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
})
</script>

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
 * @file      lapr_policies.js.php
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

const laprPolSessionKey = '<?php echo $session->get('key'); ?>'
const laprPoliciesUrl = 'sources/lapr_policies.queries.php'
let laprPoliciesTable = null

const laprPolLang = {
  confirmDelete: '<?php echo addslashes($lang->get('please_confirm_deletion')); ?>',
  addPolicy: '<?php echo addslashes($lang->get('lapr_add_policy')); ?>',
  editPolicy: '<?php echo addslashes($lang->get('lapr_edit_policy')); ?>',
  preset: '<?php echo addslashes($lang->get('lapr_preset')); ?>',
  errorGeneric: '<?php echo addslashes($lang->get('error')); ?>'
}

function laprPolPost(type, payload, onDone) {
  $.post(laprPoliciesUrl, {
    type: type,
    key: laprPolSessionKey,
    data: prepareExchangedData(JSON.stringify(payload || {}), 'encode', laprPolSessionKey)
  }, function (resp) {
    let data
    try {
      data = prepareExchangedData(resp, 'decode', laprPolSessionKey)
    } catch (e) {
      toastr.error(laprPolLang.errorGeneric)
      return
    }
    onDone(data || {})
  })
}

function laprLoadPolicies() {
  laprPolPost('list_policies', {}, function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprPolLang.errorGeneric)
      return
    }
    const rows = (data.data || []).map(function (p) {
      const charsets = [
        p.use_uppercase ? 'A-Z' : '',
        p.use_lowercase ? 'a-z' : '',
        p.use_digits ? '0-9' : '',
        p.use_symbols ? '#!@' : ''
      ].filter(Boolean).join(' ')
      const label = DOMPurify.sanitize(p.label) + (p.is_preset ? ' <span class="badge badge-info">' + laprPolLang.preset + '</span>' : '')
      return [
        label,
        p.frequency_days,
        p.password_length,
        DOMPurify.sanitize(charsets),
        p.rotate_on_enroll ? '<i class="fas fa-check text-success"></i>' : '—',
        laprPolicyActions(p)
      ]
    })
    if (laprPoliciesTable) {
      laprPoliciesTable.clear().rows.add(rows).draw()
    } else {
      laprPoliciesTable = $('#lapr-policies-table').DataTable({
        data: rows,
        columnDefs: [{ orderable: false, targets: 5 }],
        language: { emptyTable: 'No policies yet' }
      })
    }
  })
}

function laprPolicyActions(p) {
  if (p.is_preset) {
    return '<span class="text-muted small">read-only</span>'
  }
  return '<button class="btn btn-xs btn-secondary lapr-edit-policy" data-id="' + p.id + '"><i class="fas fa-pen"></i></button> ' +
    '<button class="btn btn-xs btn-danger lapr-delete-policy" data-id="' + p.id + '"><i class="fas fa-trash"></i></button>'
}

function laprPolicyFormFromData(p) {
  $('#lapr-policy-id').val(p ? p.id : 0)
  $('#lapr-policy-label').val(p ? p.label : '')
  $('#lapr-policy-frequency').val(p ? p.frequency_days : 30)
  $('#lapr-policy-length').val(p ? p.password_length : 24)
  $('#lapr-policy-upper').prop('checked', p ? !!p.use_uppercase : true)
  $('#lapr-policy-lower').prop('checked', p ? !!p.use_lowercase : true)
  $('#lapr-policy-digits').prop('checked', p ? !!p.use_digits : true)
  $('#lapr-policy-symbols').prop('checked', p ? !!p.use_symbols : true)
  $('#lapr-policy-onenroll').prop('checked', p ? !!p.rotate_on_enroll : false)
  $('#lapr-policy-preview').text('')
  $('#lapr-policy-modal-title').text(p ? laprPolLang.editPolicy : laprPolLang.addPolicy)
  $('#modal_lapr_policy').modal('show')
}

function laprCollectPolicyForm() {
  return {
    id: parseInt($('#lapr-policy-id').val(), 10) || 0,
    label: $('#lapr-policy-label').val().trim(),
    frequency_days: parseInt($('#lapr-policy-frequency').val(), 10) || 30,
    password_length: parseInt($('#lapr-policy-length').val(), 10) || 24,
    use_uppercase: $('#lapr-policy-upper').is(':checked') ? 1 : 0,
    use_lowercase: $('#lapr-policy-lower').is(':checked') ? 1 : 0,
    use_digits: $('#lapr-policy-digits').is(':checked') ? 1 : 0,
    use_symbols: $('#lapr-policy-symbols').is(':checked') ? 1 : 0,
    rotate_on_enroll: $('#lapr-policy-onenroll').is(':checked') ? 1 : 0
  }
}

function laprSavePolicy() {
  const form = laprCollectPolicyForm()
  const type = form.id > 0 ? 'update_policy' : 'create_policy'
  laprPolPost(type, form, function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprPolLang.errorGeneric)
      return
    }
    toastr.success(data.message)
    $('#modal_lapr_policy').modal('hide')
    laprLoadPolicies()
  })
}

function laprPreviewPassword() {
  laprPolPost('preview_password', laprCollectPolicyForm(), function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprPolLang.errorGeneric)
      return
    }
    $('#lapr-policy-preview').text(data.password || '')
  })
}

function laprDeletePolicy(id) {
  if (!window.confirm(laprPolLang.confirmDelete)) { return }
  laprPolPost('delete_policy', { id: id }, function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprPolLang.errorGeneric)
      return
    }
    toastr.success(data.message)
    laprLoadPolicies()
  })
}

$(document).ready(function () {
  laprLoadPolicies()
  $('#lapr-add-policy-btn').on('click', function () { laprPolicyFormFromData(null) })
  $('#lapr-policy-save-btn').on('click', laprSavePolicy)
  $('#lapr-policy-preview-btn').on('click', laprPreviewPassword)
  $('#lapr-policies-table').on('click', '.lapr-delete-policy', function () {
    laprDeletePolicy($(this).data('id'))
  })
  $('#lapr-policies-table').on('click', '.lapr-edit-policy', function () {
    const id = $(this).data('id')
    laprPolPost('list_policies', {}, function (data) {
      const p = (data.data || []).find(function (x) { return x.id === id })
      if (p) { laprPolicyFormFromData(p) }
    })
  })
})
</script>

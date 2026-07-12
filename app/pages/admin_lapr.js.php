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
 * @file      admin_lapr.js.php
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

const laprAdminSessionKey = '<?php echo $session->get('key'); ?>'
const laprAdminUrl = 'sources/admin.queries.php'
const laprAdminLang = {
  errorGeneric: '<?php echo addslashes($lang->get('error')); ?>',
  saved: '<?php echo addslashes($lang->get('saved')); ?>'
}

function laprAdminPost(type, payload, onDone) {
  $.post(laprAdminUrl, {
    type: type,
    key: laprAdminSessionKey,
    data: prepareExchangedData(JSON.stringify(payload || {}), 'encode', laprAdminSessionKey)
  }, function (resp) {
    let data
    try {
      data = prepareExchangedData(resp, 'decode', laprAdminSessionKey)
    } catch (e) {
      toastr.error(laprAdminLang.errorGeneric)
      return
    }
    onDone(data || {})
  })
}

function laprLoadPermissionUsers() {
  laprAdminPost('lapr_list_users', {}, function (data) {
    if (data.error === true) {
      toastr.error(data.message || laprAdminLang.errorGeneric)
      return
    }
    let html = ''
    ;(data.data || []).forEach(function (u) {
      const checked = u.can_manage_lapr === 1 ? 'checked' : ''
      html += '<tr>' +
        '<td>' + DOMPurify.sanitize(u.login) + '</td>' +
        '<td>' + DOMPurify.sanitize(u.name || '') + '</td>' +
        '<td><input type="checkbox" class="lapr-perm-toggle" data-id="' + u.id + '" ' + checked + '></td>' +
        '</tr>'
    })
    if (!(data.data || []).length) {
      html = '<tr><td colspan="3" class="text-muted text-center">—</td></tr>'
    }
    $('#lapr-permissions-table tbody').html(html)
  })
}

$(document).ready(function () {
  laprLoadPermissionUsers()
  $('#lapr-permissions-table').on('change', '.lapr-perm-toggle', function () {
    const userId = $(this).data('id')
    const granted = $(this).is(':checked') ? 1 : 0
    laprAdminPost('set_user_lapr_permission', { user_id: userId, granted: granted }, function (data) {
      if (data.error === true) {
        toastr.error(data.message || laprAdminLang.errorGeneric)
        laprLoadPermissionUsers()
        return
      }
      toastr.success(laprAdminLang.saved)
    })
  })
})
</script>

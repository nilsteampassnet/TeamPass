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

const laprAdminSessionKey = <?php echo json_encode((string) $session->get('key'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const laprAdminUrl = 'sources/admin.queries.php'
const laprAdminLang = {
  errorGeneric: <?php echo json_encode($lang->get('error'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
  saved: <?php echo json_encode($lang->get('saved'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
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
    const users = data.data || []
    const $tbody = $('#lapr-permissions-table tbody').empty()

    users.forEach(function (u) {
      const searchText = [u.login || '', u.name || ''].join(' ').toLowerCase()
      const $row = $('<tr>', {
        class: 'lapr-permission-user-row',
        'data-search': searchText
      })
      const $toggle = $('<input>', {
        type: 'checkbox',
        class: 'lapr-perm-toggle',
        'data-id': parseInt(u.id, 10)
      }).prop('checked', u.can_manage_lapr === 1)

      $row.append($('<td>').text(u.login || ''))
      $row.append($('<td>').text(u.name || ''))
      $row.append($('<td>').append($toggle))
      $tbody.append($row)
    })

    if (!users.length) {
      $tbody.append($('<tr>').append($('<td>', {
        colspan: 3,
        class: 'text-muted text-center',
        text: '—'
      })))
    }

    laprFilterPermissionUsers()
  })
}

function laprFilterPermissionUsers() {
  const criteria = ($('#lapr-permissions-search').val() || '').toString().trim().toLowerCase()
  let visibleCount = 0

  $('.lapr-permission-user-row').each(function () {
    const searchText = ($(this).attr('data-search') || '').toLowerCase()
    const visible = criteria === '' || searchText.indexOf(criteria) !== -1
    $(this).toggleClass('hidden', !visible)
    if (visible) { visibleCount += 1 }
  })

  $('#lapr-permissions-search-no-results').toggleClass(
    'hidden',
    criteria === '' || visibleCount > 0
  )
}

$(document).ready(function () {
  laprLoadPermissionUsers()
  $(document).on('input keyup search', '#lapr-permissions-search', laprFilterPermissionUsers)
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

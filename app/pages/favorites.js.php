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
 * @file      favorites.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('favourites') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
?>


<script type='text/javascript'>
$(function() {
  'use strict'

  const sessionKey = '<?php echo $session->get('key'); ?>'
  const storeViewKey = 'teampassFavoritesView'
  const storeSortKey = 'teampassFavoritesSort'
  const storeGroupKey = 'teampassFavoritesGroup'

  // Full list as returned by the server. Favourites are a short, personal list,
  // so it is loaded once and filtered/sorted in the browser: no round-trip on
  // every keystroke, and the search stays instant.
  let favorites = []
  // Bookmarks whose item was deleted or is no longer readable. The server only
  // returns their id and the date they were added: everything else belongs to an
  // item this user is not allowed to read.
  let unavailableItems = []
  let loaded = false

  const state = {
    search: '',
    sort: localStorage.getItem(storeSortKey) || 'recent',
    view: localStorage.getItem(storeViewKey) === 'list' ? 'list' : 'grid',
    grouped: localStorage.getItem(storeGroupKey) === '1',
    // Deliberately not persisted: landing on "unavailable only" days later, after a
    // cleanup, would show an empty page for no obvious reason.
    filter: 'available'
  }

  const lang = {
    copied: '<?php echo $lang->get('copy_to_clipboard'); ?>',
    clipboardError: '<?php echo $lang->get('clipboard_error'); ?>',
    clipboardWillClear: '<?php echo $lang->get('clipboard_will_be_cleared'); ?>',
    clipboardCleared: '<?php echo $lang->get('clipboard_cleared'); ?>',
    error: '<?php echo $lang->get('an_error_occurred'); ?>',
    removed: '<?php echo $lang->get('favorites_removed'); ?>',
    restored: '<?php echo $lang->get('favorites_restored'); ?>',
    undo: '<?php echo $lang->get('favorites_undo'); ?>',
    unavailable: '<?php echo $lang->get('favorites_unavailable'); ?>',
    cleanupDone: '<?php echo $lang->get('favorites_cleanup_done'); ?>',
    copyPassword: '<?php echo $lang->get('favorites_copy_password'); ?>',
    showPassword: '<?php echo $lang->get('favorites_show_password'); ?>',
    copyLogin: '<?php echo $lang->get('favorites_copy_login'); ?>',
    openUrl: '<?php echo $lang->get('favorites_open_url'); ?>',
    openItem: '<?php echo $lang->get('favorites_open_item'); ?>',
    remove: '<?php echo $lang->get('item_menu_del_from_fav'); ?>',
    lastUsed: '<?php echo $lang->get('favorites_last_used'); ?>',
    never: '<?php echo $lang->get('never'); ?>',
    personal: '<?php echo $lang->get('personal'); ?>',
    expired: '<?php echo $lang->get('favorites_expired'); ?>',
    noLogin: '<?php echo $lang->get('favorites_no_login'); ?>',
    unavailableItem: '<?php echo $lang->get('favorites_unavailable_item'); ?>',
    unavailableHint: '<?php echo $lang->get('favorites_unavailable_hint'); ?>',
    addedOn: '<?php echo $lang->get('favorites_added_on'); ?>'
  }

  /**
   * Escape a value for safe injection in HTML text and attributes.
   * Server-side data is returned raw on purpose so the clipboard and the search
   * work on the real value; every insertion in the DOM goes through here.
   */
  const esc = (value) => String(value === null || value === undefined ? '' : value)
    .replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c])

  const byId = (id) => favorites.find((item) => item.id === id)
    || unavailableItems.find((item) => item.id === id)

  // -----------------------------------------------------------------
  // Clipboard
  // -----------------------------------------------------------------

  /**
   * Copy a value and warn the user, clearing the clipboard afterwards when the
   * instance is configured to do so (same behaviour as the item card).
   */
  const copyValue = async (value, isSecret) => {
    try {
      await navigator.clipboard.writeText(value)
    } catch (error) {
      toastr.error(lang.clipboardError, '', {timeOut: 3000, positionClass: 'toast-bottom-right'})
      return
    }

    const settings = store.get('teampassSettings') || {}
    const clipboardDuration = parseInt(settings.clipboard_life_duration) || 0
    toastr.remove()
    if (isSecret !== true || clipboardDuration === 0) {
      toastr.info(lang.copied, '', {timeOut: 2000, positionClass: 'toast-bottom-right', progressBar: true})
      return
    }

    toastr.warning(lang.clipboardWillClear, '', {timeOut: clipboardDuration * 1000, progressBar: true})
    const cleaner = new ClipboardCleaner(clipboardDuration)
    cleaner.scheduleClearing(
      () => {
        const clipboardStatus = JSON.parse(localStorage.getItem('clipboardStatus'))
        if (clipboardStatus.status === 'unsafe') {
          return
        }
        toastr.success(lang.clipboardCleared, '', {timeOut: 2000, positionClass: 'toast-bottom-right'})
      },
      (error) => console.error('Error clearing clipboard:', error)
    )
  }

  // -----------------------------------------------------------------
  // Rendering
  // -----------------------------------------------------------------

  const formatDate = (timestamp) => timestamp > 0
    ? new Date(timestamp * 1000).toLocaleDateString()
    : lang.never

  const unstarButton = (item) => '<button type="button" class="btn btn-sm btn-default fav-action fav-unstar"'
    + ' data-id="' + item.id + '" title="' + esc(lang.remove) + '"><i class="fas fa-star"></i></button>'

  /**
   * Small action buttons shared by both views. A broken bookmark has nothing to
   * act on, so only the removal stays available.
   */
  const actionButtons = (item, size) => {
    const cls = 'btn ' + size + ' btn-default fav-action'
    if (item.unavailable === true) {
      return ''
    }

    let html = '<button type="button" class="' + cls + ' fav-copy-pwd" data-id="' + item.id
      + '" title="' + esc(lang.copyPassword) + '"><i class="fas fa-key"></i></button>'
      + '<button type="button" class="' + cls + ' fav-show-pwd" data-id="' + item.id
      + '" title="' + esc(lang.showPassword) + '"><i class="fas fa-eye"></i></button>'

    if (item.login !== '') {
      html += '<button type="button" class="' + cls + ' fav-copy-login" data-id="' + item.id
        + '" title="' + esc(lang.copyLogin) + '"><i class="fas fa-user"></i></button>'
    }
    if (item.url !== '') {
      html += '<button type="button" class="' + cls + ' fav-open-url" data-id="' + item.id
        + '" title="' + esc(lang.openUrl) + '"><i class="fas fa-globe"></i></button>'
    }

    html += '<button type="button" class="' + cls + ' fav-open-item" data-id="' + item.id
      + '" title="' + esc(lang.openItem) + '"><i class="fas fa-external-link-alt"></i></button>'

    return html
  }

  const badges = (item) => {
    let html = ''
    if (item.perso === 1) {
      html += '<span class="badge badge-info ml-1" title="' + esc(lang.personal) + '"><i class="fas fa-user-lock"></i></span>'
    }
    if (item.expired === 1) {
      html += '<span class="badge badge-danger ml-1">' + esc(lang.expired) + '</span>'
    }
    return html
  }

  /**
   * Card for a bookmark whose item cannot be read anymore.
   */
  const brokenCardHtml = (item) => '<div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">'
    + '<div class="card fav-card fav-broken mb-0" data-id="' + item.id + '">'
    + '<div class="card-body">'
    + '<div class="fav-card-head">'
    + '<div class="fav-card-titles">'
    + '<div class="fav-label"><i class="fas fa-unlink mr-1"></i>' + esc(lang.unavailableItem)
    + ' <span class="text-muted">#' + item.id + '</span></div>'
    + '<div class="fav-desc">' + esc(lang.unavailableHint) + '</div>'
    + '</div>'
    + unstarButton(item)
    + '</div>'
    + '<div class="fav-meta mt-2"><i class="fas fa-clock mr-1"></i>'
    + esc(lang.addedOn) + ': ' + esc(formatDate(item.added_at)) + '</div>'
    + '</div></div></div>'

  const cardHtml = (item) => {
    if (item.unavailable === true) {
      return brokenCardHtml(item)
    }

    // The item icon is shown only when one was actually chosen: a default key icon
    // repeated on every card carries no information.
    const icon = item.icon !== '' ? '<span class="fav-card-icon"><i class="' + esc(item.icon) + '"></i></span>' : ''
    let html = '<div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">'
      + '<div class="card fav-card mb-0" data-id="' + item.id + '">'
      + '<div class="card-body">'
      + '<div class="fav-card-head">'
      + icon
      + '<div class="fav-card-titles">'
      + '<div class="fav-label" title="' + esc(item.label) + '">' + esc(item.label) + badges(item) + '</div>'
      + '<div class="fav-folder" title="' + esc(item.folder) + '"><i class="fas fa-folder mr-1"></i>'
      + esc(item.folder) + '</div>'
      + '</div>'
      + '<button type="button" class="fav-unstar" data-id="' + item.id + '" title="'
      + esc(lang.remove) + '"><i class="fas fa-star"></i></button>'
      + '</div>'
      + '<div class="fav-login mt-2"><i class="fas fa-user mr-1"></i>'
      + (item.login !== '' ? esc(item.login) : '<em>' + esc(lang.noLogin) + '</em>') + '</div>'

    if (item.description !== '') {
      html += '<div class="fav-desc mt-1">' + esc(item.description) + '</div>'
    }
    if (item.tags.length > 0) {
      html += '<div class="fav-tags mt-2">'
        + item.tags.map((tag) => '<span class="badge badge-light border mr-1">' + esc(tag) + '</span>').join('')
        + '</div>'
    }

    html += '<div class="fav-pwd small mt-2" id="fav-pwd-' + item.id + '" style="display:none;"></div>'
      + '<div class="fav-meta mt-2"><i class="fas fa-clock mr-1"></i>'
      + esc(lang.lastUsed) + ': ' + esc(formatDate(item.last_used)) + '</div>'
      + '</div>'
      + '<div class="card-footer fav-actions">' + actionButtons(item, 'btn-sm') + '</div>'
      + '</div></div>'

    return html
  }

  const rowHtml = (item) => {
    if (item.unavailable === true) {
      return '<tr class="fav-broken" data-id="' + item.id + '">'
        + '<td><span class="fav-label d-inline"><i class="fas fa-unlink mr-1"></i>'
        + esc(lang.unavailableItem) + ' <span class="text-muted">#' + item.id + '</span></span>'
        + '<div class="fav-desc">' + esc(lang.unavailableHint) + ' &middot; '
        + esc(lang.addedOn) + ' ' + esc(formatDate(item.added_at)) + '</div></td>'
        + '<td class="text-muted">-</td>'
        + '<td class="text-muted">-</td>'
        + '<td><div class="fav-actions fav-list-actions">' + unstarButton(item) + '</div></td>'
        + '</tr>'
    }

    return '<tr data-id="' + item.id + '">'
      + '<td>'
      + '<span class="fav-label d-inline">' + esc(item.label) + badges(item) + '</span>'
      + (item.description !== '' ? '<div class="fav-desc">' + esc(item.description) + '</div>' : '')
      + '<div class="fav-pwd small" id="fav-pwd-' + item.id + '" style="display:none;"></div>'
      + '</td>'
      + '<td class="fav-login">' + esc(item.login) + '</td>'
      + '<td class="fav-folder" title="' + esc(item.folder) + '">' + esc(item.folder) + '</td>'
      // The star closes the row: destructive, so it sits away from the copy buttons.
      + '<td><div class="fav-actions fav-list-actions">' + actionButtons(item, 'btn-sm')
      + unstarButton(item) + '</div></td>'
      + '</tr>'
  }

  /**
   * Apply the availability filter, then the search term, then the selected order.
   */
  const visibleItems = () => {
    const term = state.search.trim().toLowerCase()
    let list = favorites
    if (state.filter === 'unavailable') {
      list = unavailableItems
    } else if (state.filter === 'all') {
      list = favorites.concat(unavailableItems)
    }

    if (term !== '') {
      list = list.filter((item) => (
        item.label + ' ' + item.login + ' ' + item.url + ' '
        + item.description + ' ' + item.folder + ' ' + item.tags.join(' ')
      ).toLowerCase().indexOf(term) !== -1)
    }

    const comparators = {
      recent: (a, b) => b.added_at - a.added_at,
      label: (a, b) => a.label.localeCompare(b.label, undefined, {sensitivity: 'base'}),
      folder: (a, b) => a.folder.localeCompare(b.folder, undefined, {sensitivity: 'base'})
        || a.label.localeCompare(b.label, undefined, {sensitivity: 'base'}),
      used: (a, b) => b.last_used - a.last_used || b.views - a.views
    }

    return list.slice().sort(comparators[state.sort] || comparators.recent)
  }

  /**
   * Split the list into folder buckets, keeping the order the comparator produced.
   */
  const groupByFolder = (list) => {
    const groups = []
    // A Map, not an object literal: a folder named "constructor" or "__proto__"
    // would collide with Object.prototype and silently merge two groups.
    const index = new Map()
    list.forEach((item) => {
      const key = item.folder === '' ? '—' : item.folder
      if (index.has(key) === false) {
        index.set(key, groups.length)
        groups.push({folder: key, items: []})
      }
      groups[index.get(key)].items.push(item)
    })
    return groups
  }

  const render = () => {
    // A toolbar action fired before the first response must not flash the empty state.
    if (loaded === false) {
      return
    }
    const list = visibleItems()
    const total = favorites.length + unavailableItems.length

    $('#favorites-count').text(favorites.length).toggle(favorites.length > 0)
    $('#favorites-loading').hide()
    $('#favorites-empty').toggle(total === 0)
    $('#favorites-no-match').toggle(total > 0 && list.length === 0)

    const showGrid = list.length > 0 && state.view === 'grid'
    const showList = list.length > 0 && state.view === 'list'
    $('#favorites-grid').toggle(showGrid)
    $('#favorites-list').toggle(showList)

    // Only one view is ever populated: both templates use the same
    // `fav-pwd-<id>` anchors, so leaving stale markup behind would give
    // getElementById two candidates and reveal a password in a hidden node.
    if (showGrid === false) {
      $('#favorites-grid').empty()
    }
    if (showList === false) {
      $('#favorites-tbody').empty()
    }

    if (showGrid === false && showList === false) {
      return
    }

    const buckets = state.grouped === true
      ? groupByFolder(list)
      : [{folder: null, items: list}]

    if (showGrid === true) {
      const html = buckets.map((bucket) => (
        (bucket.folder === null ? '' : '<div class="fav-group-title">' + esc(bucket.folder) + '</div>')
        + '<div class="row">' + bucket.items.map(cardHtml).join('') + '</div>'
      )).join('')
      $('#favorites-grid').html(html)
    } else {
      const html = buckets.map((bucket) => (
        (bucket.folder === null ? '' : '<tr class="bg-light"><td colspan="4" class="fav-group-title py-1 mb-0">'
          + esc(bucket.folder) + '</td></tr>')
        + bucket.items.map(rowHtml).join('')
      )).join('')
      $('#favorites-tbody').html(html)
    }
  }

  const renderUnavailable = () => {
    // The availability filter only makes sense while a broken bookmark exists.
    $('#favorites-filter-wrap').toggle(unavailableItems.length > 0)

    if (unavailableItems.length === 0) {
      $('#favorites-unavailable').hide()
      return
    }
    $('#favorites-unavailable-text').text(lang.unavailable.replace('%d', unavailableItems.length))
    $('#favorites-unavailable').show()
  }

  // -----------------------------------------------------------------
  // Data
  // -----------------------------------------------------------------

  /**
   * Give a broken bookmark the same shape as a regular one, so the search, the
   * comparators and the grouping keep working without a special case each time.
   */
  const normalizeUnavailable = (entry) => ({
    id: parseInt(entry.id, 10),
    unavailable: true,
    label: lang.unavailableItem,
    description: '',
    login: '',
    url: '',
    folder_id: 0,
    folder: '',
    icon: '',
    perso: 0,
    tags: [],
    expired: 0,
    added_at: parseInt(entry.added_at, 10) || 0,
    last_used: 0,
    views: 0
  })

  const loadFavorites = () => {
    $.post(
      'sources/favourites.queries.php', {
        type: 'get_favorites',
        key: sessionKey
      },
      function(data) {
        if (data === null || data.error === true) {
          $('#favorites-loading').hide()
          toastr.error((data && data.message) || lang.error, '', {timeOut: 5000})
          return
        }
        favorites = data.items
        unavailableItems = (data.unavailable_items || []).map(normalizeUnavailable)
        loaded = true
        if (unavailableItems.length === 0 && state.filter !== 'all') {
          setFilter('available')
        }
        render()
        renderUnavailable()
      },
      'json'
    ).fail(() => {
      $('#favorites-loading').hide()
      toastr.error(lang.error, '', {timeOut: 5000})
    })
  }

  // -----------------------------------------------------------------
  // Toolbar
  // -----------------------------------------------------------------

  let searchDebounce = null
  $('#favorites-search').on('keyup', function() {
    state.search = $(this).val()
    $('#favorites-search-clear-wrap').toggle(state.search !== '')
    clearTimeout(searchDebounce)
    searchDebounce = setTimeout(render, 120)
  })

  $('#favorites-search-clear').on('click', () => {
    state.search = ''
    $('#favorites-search').val('').focus()
    $('#favorites-search-clear-wrap').hide()
    render()
  })

  $('#favorites-sort').val(state.sort).on('change', function() {
    state.sort = $(this).val()
    localStorage.setItem(storeSortKey, state.sort)
    render()
  })

  /**
   * Single entry point for the availability filter: the select, the banner button
   * and the automatic resets must never disagree on the current value.
   */
  const setFilter = (value) => {
    state.filter = value
    $('#favorites-filter').val(value)
    $('#favorites-show-unavailable')
      .toggleClass('btn-dark', value === 'unavailable')
      .toggleClass('btn-outline-dark', value !== 'unavailable')
      .attr('aria-pressed', value === 'unavailable' ? 'true' : 'false')
  }

  $('#favorites-filter').val(state.filter).on('change', function() {
    setFilter($(this).val())
    render()
  })

  // Shortcut from the warning banner: show exactly what the cleanup would remove.
  // Clicking again goes back to the full list, so the button is never a one-way trip.
  $('#favorites-show-unavailable').on('click', () => {
    setFilter(state.filter === 'unavailable' ? 'available' : 'unavailable')
    render()
  })

  const applyViewButtons = () => {
    $('#favorites-view-grid').toggleClass('active', state.view === 'grid')
    $('#favorites-view-list').toggleClass('active', state.view === 'list')
  }

  $('#favorites-view-grid').on('click', () => {
    state.view = 'grid'
    localStorage.setItem(storeViewKey, 'grid')
    applyViewButtons()
    render()
  })

  $('#favorites-view-list').on('click', () => {
    state.view = 'list'
    localStorage.setItem(storeViewKey, 'list')
    applyViewButtons()
    render()
  })

  const applyGroupButton = () => {
    $('#favorites-group-toggle')
      .toggleClass('btn-primary', state.grouped)
      .toggleClass('btn-default', state.grouped === false)
      .attr('aria-pressed', state.grouped ? 'true' : 'false')
  }

  $('#favorites-group-toggle').on('click', () => {
    state.grouped = state.grouped === false
    localStorage.setItem(storeGroupKey, state.grouped ? '1' : '0')
    applyGroupButton()
    render()
  })

  // "/" jumps to the search field, Escape clears it - the shortcut users of
  // other password managers expect on a quick-access list.
  $(document).on('keydown', function(event) {
    const tag = (event.target.tagName || '').toLowerCase()
    if (tag === 'input' || tag === 'textarea' || tag === 'select') {
      if (event.key === 'Escape' && event.target.id === 'favorites-search') {
        $('#favorites-search-clear').trigger('click')
      }
      return
    }
    if (event.key === '/') {
      event.preventDefault()
      $('#favorites-search').focus()
    }
  })

  // -----------------------------------------------------------------
  // Item actions
  // -----------------------------------------------------------------

  $(document).on('click', '.fav-open-item', function() {
    const item = byId($(this).data('id'))
    if (item !== undefined) {
      document.location.href = 'index.php?page=items&group=' + item.folder_id + '&id=' + item.id
    }
  })

  $(document).on('click', '.fav-open-url', function() {
    const item = byId($(this).data('id'))
    if (item === undefined || item.url === '') {
      return
    }
    // Only http(s) is opened; anything else is copied so a stored javascript:
    // or data: value can never be navigated to from here.
    if (/^https?:\/\//i.test(item.url) === true) {
      window.open(item.url, '_blank', 'noopener,noreferrer')
    } else {
      copyValue(item.url, false)
    }
  })

  $(document).on('click', '.fav-copy-login', function() {
    const item = byId($(this).data('id'))
    if (item !== undefined && item.login !== '') {
      copyValue(item.login, false)
    }
  })

  $(document).on('click', '.fav-copy-pwd', async function() {
    const password = await getItemPassword('at_password_copied', 'item_id', $(this).data('id'))
    if (password) {
      copyValue(password, true)
    }
  })

  // One auto-hide timer per item, so re-showing a password restarts its own delay
  // instead of being cut short by the previous one.
  const hideTimers = {}

  const hidePassword = (button, holder, itemId) => {
    clearTimeout(hideTimers[itemId])
    holder.hide().text('')
    button.find('i').removeClass('fa-eye-slash text-warning fa-circle-notch fa-spin').addClass('fa-eye')
  }

  $(document).on('click', '.fav-show-pwd', async function() {
    const button = $(this)
    const itemId = button.data('id')
    const holder = $('#fav-pwd-' + itemId)

    if (button.hasClass('is-loading') === true) {
      return
    }
    if (holder.is(':visible') === true) {
      hidePassword(button, holder, itemId)
      return
    }

    button.addClass('is-loading').find('i').removeClass('fa-eye').addClass('fa-circle-notch fa-spin')
    const password = await getItemPassword('at_password_shown', 'item_id', itemId)
    button.removeClass('is-loading').find('i').removeClass('fa-circle-notch fa-spin')

    if (!password) {
      button.find('i').addClass('fa-eye')
      return
    }

    button.find('i').addClass('fa-eye-slash text-warning')
    holder.text(password).show()

    // Auto-hide, so a password never stays on screen on an unattended session.
    clearTimeout(hideTimers[itemId])
    hideTimers[itemId] = setTimeout(() => hidePassword(button, holder, itemId), 10000)
  })

  // -----------------------------------------------------------------
  // Add / remove
  // -----------------------------------------------------------------

  $(document).on('click', '.fav-unstar', function() {
    const itemId = $(this).data('id')
    const item = byId(itemId)
    if (item === undefined) {
      return
    }

    $.post(
      'sources/favourites.queries.php', {
        type: 'del_fav',
        id: itemId,
        key: sessionKey
      },
      function(data) {
        if (data === null || data.error === true) {
          toastr.error((data && data.message) || lang.error, '', {timeOut: 5000})
          return
        }

        favorites = favorites.filter((element) => element.id !== itemId)
        unavailableItems = unavailableItems.filter((element) => element.id !== itemId)

        // Removing the last broken bookmark also removes the reason to stay on that
        // filter - and the control that would let the user leave it.
        if (unavailableItems.length === 0 && state.filter === 'unavailable') {
          setFilter('available')
        }

        render()
        renderUnavailable()

        toastr.remove()

        // A broken bookmark cannot be restored: the server would refuse to
        // re-add an item this user is not allowed to read.
        if (item.unavailable === true) {
          toastr.info(lang.removed, '', {timeOut: 2000, positionClass: 'toast-bottom-right'})
          return
        }

        // Undo instead of a confirmation dialog: removing a favourite is a
        // one-click, reversible action, not a destructive one.
        toastr.info(
          esc(lang.removed) + ' <a href="#" class="fav-undo ml-2" data-id="' + itemId + '">'
          + '<i class="fas fa-rotate-left mr-1"></i>' + esc(lang.undo) + '</a>',
          '', {
            timeOut: 8000,
            extendedTimeOut: 8000,
            positionClass: 'toast-bottom-right',
            progressBar: true,
            tapToDismiss: false
          }
        )
      },
      'json'
    )
  })

  $(document).on('click', '.fav-undo', function(event) {
    event.preventDefault()
    const itemId = $(this).data('id')

    $.post(
      'sources/favourites.queries.php', {
        type: 'add_fav',
        id: itemId,
        key: sessionKey
      },
      function(data) {
        toastr.remove()
        if (data === null || data.error === true) {
          toastr.error((data && data.message) || lang.error, '', {timeOut: 5000})
          return
        }
        toastr.success(lang.restored, '', {timeOut: 2000, positionClass: 'toast-bottom-right'})
        loadFavorites()
      },
      'json'
    )
  })

  $('#favorites-cleanup').on('click', function() {
    const button = $(this)
    button.prop('disabled', true)

    $.post(
      'sources/favourites.queries.php', {
        type: 'cleanup_unavailable',
        key: sessionKey
      },
      function(data) {
        button.prop('disabled', false)
        if (data === null || data.error === true) {
          toastr.error((data && data.message) || lang.error, '', {timeOut: 5000})
          return
        }
        unavailableItems = []
        setFilter('available')
        render()
        renderUnavailable()
        toastr.success(lang.cleanupDone, '', {timeOut: 2000, positionClass: 'toast-bottom-right'})
      },
      'json'
    )
  })

  // -----------------------------------------------------------------

  applyViewButtons()
  applyGroupButton()
  setFilter(state.filter)
  loadFavorites()
})
</script>

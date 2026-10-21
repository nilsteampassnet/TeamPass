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
 * @file      device-pairing.js
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory(root)
  else root.tpDevicePairing = factory(root)
})(typeof globalThis !== 'undefined' ? globalThis : this, function (root) {
  'use strict'

  const ranks = { awaiting_scan: 0, awaiting_approval: 1, approved: 2, delivered: 3, completed: 4, rejected: 4, cancelled: 4, expired: 4 }
  const terminal = state => ['completed', 'rejected', 'cancelled', 'expired'].includes(state)
  const canAdvance = (current, next) => Object.prototype.hasOwnProperty.call(ranks, next) && (!current || (terminal(current) ? current === next : ranks[next] >= ranks[current]))
  const matchesLabel = (label, query) => String(label || '').toLocaleLowerCase().includes(String(query || '').trim().toLocaleLowerCase())
  const remainingSeconds = (deadline, now) => Math.max(0, Math.ceil((deadline - now) / 1000))

  // Responses from a closed or replaced invitation can never update its successor's controls.
  function createAttemptGuard () {
    let generation = 0
    let id = null
    return {
      next () { id = null; return ++generation },
      bind (version, value) { if (version !== generation) return false; id = value; return true },
      current (version, value) { return version === generation && (value === undefined || id === value) },
      version () { return generation },
      id () { return id }
    }
  }

  function mount (options) {
    const $ = root.jQuery
    if (!$ || !$('#external-devices-block').length) return
    const m = options.messages
    const guard = createAttemptGuard()
    let latest = null
    let deadline = 0
    let busy = false
    let pollTimer = null
    let clockTimer = null
    let devices = []
    let listGeneration = 0
    let pollDelay = 2000
    const request = (type, data = {}) => $.ajax({
      url: 'sources/device_pairing.queries.php', type: 'POST', dataType: 'json', timeout: 15000,
      data: { type, key: options.key, data: root.prepareExchangedData(JSON.stringify(data), 'encode', options.key) }
    })
    const showError = message => $('#device-pairing-error').removeClass('d-none').text(message || m.network)
    const clearTimers = () => { clearTimeout(pollTimer); clearInterval(clockTimer) }
    const syncButtons = () => {
      $('#device-pairing-new').prop('disabled', busy).toggleClass('d-none', Boolean(latest && latest.state === 'completed'))
      $('#device-pairing-approve, #device-pairing-reject').prop('disabled', busy || !latest || latest.state !== 'awaiting_approval')
    }
    const tick = () => {
      const seconds = remainingSeconds(deadline, Date.now())
      $('#device-pairing-expiry').text(m.expires.replace('#time#', Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0')))
      if (!seconds && latest && !terminal(latest.state)) {
        $('#device-pairing-qr-wrap').addClass('d-none')
        $('#device-pairing-approve, #device-pairing-reject').prop('disabled', true)
      }
    }
    function renderState (state) {
      if (!canAdvance(latest && latest.state, state.state)) return
      latest = state
      deadline = Date.now() + Math.max(0, state.expires_at - state.server_time) * 1000
      $('#device-pairing-status').text(m[state.state] || m.network)
      $('#device-pairing-expiry').toggleClass('d-none', terminal(state.state))
      $('#device-pairing-decisions').toggleClass('d-none', state.state !== 'awaiting_approval')
      $('#device-pairing-claim').toggleClass('d-none', !['awaiting_approval', 'approved', 'delivered'].includes(state.state))
      $('#device-pairing-device-name').text(state.device_label || '')
      $('#device-pairing-comparison').text(state.comparison_code || '')
      $('#device-pairing-cancel').text(terminal(state.state) ? m.close : m.cancel)
      if (state.state !== 'awaiting_scan') {
        $('#device-pairing-qr-wrap').addClass('d-none')
        $('#device-pairing-qr').empty()
      }
      if (state.state !== 'awaiting_approval') $('#device-pairing-password-group').addClass('d-none')
      if (terminal(state.state)) {
        clearTimers()
        $('#device-pairing-password').val('')
        if (state.state === 'completed') loadDevices()
      }
      syncButtons()
      tick()
    }
    function handleFailure (xhr) {
      showError(xhr.responseJSON && xhr.responseJSON.message)
      if ((xhr.status === 401 || xhr.status === 403) && xhr.responseJSON && xhr.responseJSON.reason === 'invalid_proof') {
        clearTimers()
        guard.next()
        busy = false
        $('#device-pairing-password').val('')
        $('#device-pairing-new, #device-pairing-open').prop('disabled', true)
        $('#device-pairing-qr-wrap, #device-pairing-decisions').addClass('d-none')
        showError(m.session_ended)
      }
      if (xhr.status === 429) pollDelay = Math.max(2000, Math.min(60000, Number(xhr.getResponseHeader('Retry-After') || 2) * 1000))
    }
    function schedulePoll (version, id) {
      clearTimeout(pollTimer)
      if (!guard.current(version, id) || !latest || terminal(latest.state)) return
      pollTimer = setTimeout(() => {
        request('status', { pairing_id: id }).done(result => {
          if (guard.current(version, id) && !result.error && result.pairing_id === id) { pollDelay = 2000; renderState(result) }
        }).fail(xhr => {
          if (guard.current(version, id) && latest && !terminal(latest.state)) handleFailure(xhr)
        }).always(() => {
          if (guard.current(version, id)) schedulePoll(version, id)
        })
      }, pollDelay)
    }
    function start () {
      const version = guard.next()
      clearTimers()
      latest = null
      busy = true
      pollDelay = 2000
      $('#device-pairing-error, #device-pairing-qr-wrap, #device-pairing-claim, #device-pairing-password-group, #device-pairing-decisions').addClass('d-none')
      $('#device-pairing-password').val('')
      $('#device-pairing-qr').empty()
      $('#device-pairing-status').text(m.loading)
      $('#device-pairing-expiry').text('')
      syncButtons()
      request('create').done(result => {
        if (!guard.current(version)) {
          if (!result.error && result.pairing_id) request('cancel', { pairing_id: result.pairing_id })
          return
        }
        if (result.error || !result.qr || !guard.bind(version, result.pairing_id)) {
          showError(result.message)
          $('#device-pairing-status').text('')
          return
        }
        renderState(result)
        try {
          new root.QRCode(document.getElementById('device-pairing-qr'), {
            text: JSON.stringify(result.qr), width: 240, height: 240,
            colorDark: '#000000', colorLight: '#ffffff', correctLevel: root.QRCode.CorrectLevel.M
          })
          $('#device-pairing-qr-wrap').removeClass('d-none')
        } catch (error) {
          showError(m.qr_error)
          $('#device-pairing-status').text('')
          request('cancel', { pairing_id: result.pairing_id })
          return
        }
        clockTimer = setInterval(tick, 1000)
        schedulePoll(version, result.pairing_id)
      }).fail(xhr => {
        if (guard.current(version)) handleFailure(xhr)
      }).always(() => {
        if (guard.current(version)) { busy = false; syncButtons() }
      })
    }
    function decide (action) {
      if (!latest || latest.state !== 'awaiting_approval' || busy) return
      const id = guard.id()
      const version = guard.version()
      const input = { pairing_id: id, claim_id: latest.claim_id, key_fingerprint: latest.key_fingerprint }
      if (action === 'approve') {
        input.current_password = $('#device-pairing-password').val()
        $('#device-pairing-password').val('')
      }
      busy = true
      syncButtons()
      request(action, input).done(result => {
        if (!guard.current(version, id)) return
        if (result.error) {
          if (latest && terminal(latest.state)) return
          showError(result.message)
          if (result.stepup === 'password') $('#device-pairing-password-group').removeClass('d-none').find('input').trigger('focus')
          return
        }
        $('#device-pairing-error').addClass('d-none')
        renderState(result)
      }).fail(xhr => {
        if (guard.current(version, id) && latest && !terminal(latest.state)) handleFailure(xhr)
      }).always(() => {
        if (guard.current(version, id)) { busy = false; syncButtons() }
      })
    }
    function renderDevices () {
      const list = $('#device-pairing-list').empty()
      const matching = devices.filter(device => matchesLabel(device.label || m.unnamed, $('#device-pairing-search').val()))
      if (!matching.length) {
        $('<p>').addClass('text-muted mb-0').text(devices.length ? m.no_results : m.no_devices).appendTo(list)
        return
      }
      const date = value => value ? new Date(value * 1000).toLocaleString() : m.never
      matching.forEach(device => {
        const row = $('<div>').addClass('d-sm-flex align-items-center justify-content-between border rounded p-3 mb-2')
        const text = $('<div>').addClass('mr-sm-3 mb-2 mb-sm-0').css('min-width', 0).appendTo(row)
        $('<div>').addClass('device-entry-name font-weight-bold').text(device.label || m.unnamed).appendTo(text)
        $('<div>').addClass('text-muted small').text(m.created + ': ' + date(device.created_at) + ' · ' + m.last_used + ': ' + date(device.last_used_at)).appendTo(text)
        $('<div>').addClass('text-muted small').text(m.valid_until + ': ' + date(device.expires_at)).appendTo(text)
        $('<button>').attr('type', 'button').addClass('btn btn-sm btn-outline-danger').text(m.revoke).on('click', function () {
          const button = $(this)
          button.prop('disabled', true)
          const confirmation = $('<div>').append(
            $('<p>').addClass('font-weight-bold text-break').text(device.label || m.unnamed),
            $('<p>').text(m.revoke_confirm)
          ).prop('outerHTML')
          root.alertify.confirm(m.revoke_title, confirmation, function () {
            request('revoke', { id: Number(device.id) }).done(result => {
              if (!result.error) loadDevices()
            }).fail(() => root.toastr.error(m.network)).always(() => button.prop('disabled', false))
          }, function () { button.prop('disabled', false) }).set('labels', { ok: m.revoke, cancel: m.cancel })
        }).appendTo(row)
        list.append(row)
      })
    }
    function loadDevices () {
      const version = ++listGeneration
      request('list').done(result => {
        if (version === listGeneration && !result.error) { devices = result.devices || []; renderDevices() }
      }).fail(() => {
        if (version === listGeneration) $('#device-pairing-list').empty().append($('<p>').addClass('text-warning mb-0').text(m.network))
      })
    }
    $('#device-pairing-open').on('click', () => { $('#device-pairing-modal').modal('show'); start() })
    $('#device-pairing-new').on('click', start)
    $('#device-pairing-approve').on('click', () => decide('approve'))
    $('#device-pairing-reject').on('click', () => decide('reject'))
    $('#device-pairing-password').on('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); decide('approve') } })
    $('#device-pairing-modal').on('hidden.bs.modal', () => {
      const id = guard.id()
      const finished = latest && terminal(latest.state)
      guard.next()
      clearTimers()
      $('#device-pairing-password').val('')
      $('#device-pairing-qr').empty()
      latest = null
      if (id && !finished) request('cancel', { pairing_id: id }).done(result => { if (result.state === 'completed') loadDevices() })
    })
    $('#device-pairing-refresh').on('click', loadDevices)
    $('#device-pairing-search').on('input search', renderDevices)
    loadDevices()
  }
  /** Manage a bounded metadata inventory without exposing credentials or decrypted vault data. */
  function mountAdmin (options) {
    const $ = root.jQuery
    if (!$ || !$('#external-device-admin').length) return
    const m = options.messages
    let offset = 0
    let hasMore = false
    let generation = 0
    const owner = () => Number($('#device-admin-owner').val())
    const request = (type, data) => $.ajax({
      url: 'sources/device_pairing.queries.php', type: 'POST', dataType: 'json', timeout: 15000,
      data: { type, key: options.key, data: root.prepareExchangedData(JSON.stringify(data), 'encode', options.key) }
    })
    const buttons = busy => {
      $('#device-admin-previous').prop('disabled', busy || !offset)
      $('#device-admin-next').prop('disabled', busy || !hasMore)
    }
    function load () {
      const version = ++generation
      const selectedOwner = owner()
      hasMore = false
      buttons(true)
      $('#device-admin-list').empty().append($('<p>').addClass('text-muted').text(m.loading))
      request('admin_list', { user_id: selectedOwner, offset }).done(result => {
        if (version !== generation || selectedOwner !== owner()) return
        if (result.error) { root.toastr.error(result.message || m.network); return }
        hasMore = Boolean(result.has_more)
        const list = $('#device-admin-list').empty()
        const date = value => value ? new Date(value * 1000).toLocaleString() : m.never
        const entries = result.devices || []
        if (!entries.length) $('<p>').addClass('text-muted').text(m.no_devices).appendTo(list)
        entries.forEach(device => {
          const row = $('<div>').addClass('d-sm-flex align-items-center justify-content-between border rounded p-3 mb-2')
          const text = $('<div>').addClass('mr-sm-3 mb-2 mb-sm-0 text-break').css('min-width', 0).appendTo(row)
          $('<div>').addClass('font-weight-bold').text(device.login + ' · ' + (device.label || m.unnamed)).appendTo(text)
          $('<div>').addClass('small text-muted').text(m.created + ': ' + date(device.created_at) + ' · ' + m.last_used + ': ' + date(device.last_used_at)).appendTo(text)
          $('<div>').addClass('small text-muted').text(m.valid_until + ': ' + date(device.expires_at)).appendTo(text)
          $('<button>').attr('type', 'button').addClass('btn btn-sm btn-outline-danger').text(m.revoke).on('click', function () {
            const button = $(this).prop('disabled', true)
            const confirmation = $('<div>').append(
              $('<p>').addClass('font-weight-bold text-break').text(device.login + ' · ' + (device.label || m.unnamed)),
              $('<p>').text(m.revoke_confirm)
            ).prop('outerHTML')
            root.alertify.confirm(m.revoke_title, confirmation, function () {
              request('admin_revoke', { user_id: Number(device.user_id), id: Number(device.id) }).done(result => {
                if (!result.error) load()
              }).fail(xhr => root.toastr.error((xhr.responseJSON && xhr.responseJSON.message) || m.network)).always(() => button.prop('disabled', false))
            }, function () { button.prop('disabled', false) }).set('labels', { ok: m.revoke, cancel: m.cancel })
          }).appendTo(row)
          list.append(row)
        })
      }).fail(xhr => {
        if (version === generation) $('#device-admin-list').empty().append($('<p>').addClass('text-warning').text((xhr.responseJSON && xhr.responseJSON.message) || m.network))
      }).always(() => { if (version === generation) buttons(false) })
    }
    $('#device-admin-owner, #device-admin-refresh').on('change click', function (event) {
      if (this.id === 'device-admin-owner' && event.type !== 'change') return
      offset = 0
      load()
    })
    $('#device-admin-previous').on('click', () => { offset = Math.max(0, offset - 30); load() })
    $('#device-admin-next').on('click', () => { if (hasMore) { offset += 30; load() } })
    load()
  }
  return { mount, mountAdmin, createAttemptGuard, canAdvance, matchesLabel, remainingSeconds }
})

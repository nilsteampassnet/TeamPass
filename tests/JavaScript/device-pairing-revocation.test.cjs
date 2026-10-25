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
 * @file      device-pairing-revocation.test.cjs
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */
'use strict'

const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')

const source = fs.readFileSync(path.join(__dirname, '../../public/assets/js/device-pairing.js'), 'utf8')
const escapeText = value => String(value).replace(/[&<>]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[character]))

// Model the DOM operations used by the real mount handlers and settle AJAX explicitly.
// Alertify is deliberately absent, matching the scripts loaded by public/index.php.
function harness (admin) {
  const elements = new Map()
  const requests = []
  const dialogs = []
  const errors = []
  let activeDialog = null
  class Element {
    constructor (tag = 'div') {
      this.tag = tag
      this.length = 1
      this.children = []
      this.properties = { disabled: false }
      this.handlers = new Map()
      this.value = ''
      this.content = ''
    }

    text (value) { if (value === undefined) return this.content; this.content = String(value); this.children = []; return this }
    val (value) { if (value === undefined) return this.value; this.value = value; return this }
    empty () { this.children = []; this.content = ''; return this }
    append (...children) { this.children.push(...children); return this }
    appendTo (target) { target.append(this); return this }
    attr () { return this }
    addClass () { return this }
    removeClass () { return this }
    toggleClass () { return this }
    css () { return this }
    on (events, handler) { for (const event of events.split(' ')) this.handlers.set(event, handler); return this }
    prop (name, value) {
      if (name === 'outerHTML' && value === undefined) return this.outerHTML()
      if (value === undefined) return this.properties[name]
      this.properties[name] = value
      return this
    }

    outerHTML () { return '<' + this.tag + '>' + escapeText(this.content) + this.children.map(child => child.outerHTML()).join('') + '</' + this.tag + '>' }
    click () { if (!this.properties.disabled) this.handlers.get('click')?.call(this) }
  }
  const $ = selector => {
    if (selector instanceof Element) return selector
    if (selector.startsWith('<')) return new Element(selector.slice(1, -1))
    if (!elements.has(selector)) elements.set(selector, new Element())
    return elements.get(selector)
  }
  $.ajax = options => {
    const callbacks = { done: [], fail: [], always: [] }
    const request = {
      options,
      payload: JSON.parse(options.data.data),
      done (callback) { callbacks.done.push(callback); return this },
      fail (callback) { callbacks.fail.push(callback); return this },
      always (callback) { callbacks.always.push(callback); return this },
      resolve (response) { for (const callback of callbacks.done) callback(response); for (const callback of callbacks.always) callback() },
      reject (response = {}) { for (const callback of callbacks.fail) callback(response); for (const callback of callbacks.always) callback() }
    }
    requests.push(request)
    return request
  }
  const context = vm.createContext({
    jQuery: $, clearTimeout, clearInterval, setTimeout, setInterval,
    prepareExchangedData: (data, operation, key) => {
      assert.equal(operation, 'encode')
      assert.equal(key, 'session-csrf-key')
      return data
    },
    launchConfirmDialog: (title, body, confirm, action, cancel) => {
      activeDialog = { title, body, confirm, action, cancel }
      dialogs.push(activeDialog)
    },
    toastr: { error: message => errors.push(message) }
  })
  vm.runInContext(source, context)
  const messages = {
    revoke_title: 'Révoquer cet appareil', revoke: 'Révoquer', cancel: 'Annuler',
    revoke_confirm: 'Confirmer la révocation ?', network: 'Connexion impossible',
    unnamed: 'Sans nom', never: 'Jamais', created: 'Création', last_used: 'Dernier accès',
    valid_until: 'Expiration', loading: 'Chargement', no_devices: 'Aucun appareil'
  }
  const device = {
    id: '42', user_id: '7', login: '<svg/onload=steal()> O\'Brien & sons',
    label: '<img src=x onerror="steal()"> R&D',
    created_at: 100, last_used_at: 200, expires_at: 300
  }
  const listType = admin ? 'admin_list' : 'list'
  context.tpDevicePairing[admin ? 'mountAdmin' : 'mount']({ key: 'session-csrf-key', messages })
  assert.equal(requests[0].options.data.type, listType)
  requests[0].resolve({ error: false, devices: [device], has_more: false })
  const inventory = $(admin ? '#device-admin-rows' : '#device-pairing-list')
  const descendants = element => [element, ...element.children.flatMap(descendants)]
  const button = descendants(inventory).find(element => element.tag === 'button')
  assert.ok(button, 'the actual inventory renderer creates the revoke button')
  return {
    button, requests, dialogs, errors, messages, device, listType,
    mutationType: admin ? 'admin_revoke' : 'revoke',
    dismiss () { activeDialog = null },
    confirm () { assert.ok(activeDialog, 'a confirmation dialog is open'); activeDialog.confirm() }
  }
}

for (const admin of [false, true]) {
  const role = admin ? 'administrator' : 'profile owner'

  test(role + ' can cancel and reopen the standard escaped revocation confirmation', () => {
    const h = harness(admin)
    h.button.click()
    assert.equal(h.requests.length, 1, 'opening the dialog never revokes anything')
    const dialog = h.dialogs[0]
    assert.equal(dialog.title, h.messages.revoke_title)
    assert.equal(dialog.action, h.messages.revoke)
    assert.equal(dialog.cancel, h.messages.cancel)
    assert.ok(dialog.body.includes(escapeText(h.device.label)))
    assert.ok(!dialog.body.includes(h.device.label), 'device markup cannot become HTML')
    assert.ok(dialog.body.includes(h.messages.revoke_confirm))
    if (admin) {
      assert.ok(dialog.body.includes(escapeText(h.device.login)))
      assert.ok(!dialog.body.includes(h.device.login), 'account markup cannot become HTML')
    }
    h.dismiss() // Cancel and the close cross both skip the confirm callback.
    assert.equal(h.button.prop('disabled'), false)
    assert.equal(h.requests.length, 1)
    h.button.click()
    assert.equal(h.dialogs.length, 2)
    assert.equal(h.requests.length, 1)
  })

  test(role + ' revokes once after confirmation and can retry a failed request', () => {
    const h = harness(admin)
    h.button.click()
    h.confirm()
    assert.equal(h.button.prop('disabled'), true)
    assert.equal(h.requests.length, 2)
    const mutation = h.requests[1]
    assert.equal(mutation.options.url, 'sources/device_pairing.queries.php')
    assert.equal(mutation.options.type, 'POST')
    assert.equal(mutation.options.data.type, h.mutationType)
    assert.equal(mutation.options.data.key, 'session-csrf-key')
    assert.deepEqual(mutation.payload, admin ? { user_id: 7, id: 42 } : { id: 42 })
    h.confirm() // A second action before the shared modal finishes hiding is harmless.
    h.button.click()
    assert.equal(h.requests.length, 2)
    mutation.reject({ status: 503 })
    assert.equal(h.button.prop('disabled'), false)
    assert.deepEqual(h.errors, [h.messages.network])
    assert.equal(h.requests.length, 2, 'failure leaves the current inventory available')
    h.dismiss()
    h.button.click()
    h.confirm()
    assert.equal(h.requests.length, 3)
    assert.deepEqual(h.requests[2].payload, mutation.payload)
    h.requests[2].resolve({ error: false, revoked: true })
    assert.equal(h.button.prop('disabled'), false)
    assert.equal(h.requests.length, 4)
    assert.equal(h.requests[3].options.data.type, h.listType)
    h.requests[3].resolve({ error: false, devices: [], has_more: false })
  })
}

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
 * @file      secure-send.test.cjs
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const { TextEncoder } = require('node:util')
const vm = require('node:vm')
const { test } = require('node:test')

const template = readFileSync(join(__dirname, '../../app/pages/items.js.php'), 'utf8')
const from = template.indexOf('function bindSecureSendClipboard(')
const to = template.indexOf('// Handle max value for OTV', from)
assert.ok(from >= 0 && to > from)
const source = template.slice(from, to).replace(/<\?php[\s\S]*?\?>/g, php => {
  if (php.includes('$secureSendUrls')) return JSON.stringify({ internal: 'https://vault.example.com', public: 'https://share.example.com/team' })
  const key = php.match(/\$lang->get\(['"]([^'"]+)['"]\)/)?.[1] || 'test-key'
  const value = key === 'secure_send_address_preview' ? 'Address: #URL#' : key
  return php.includes('json_encode') ? JSON.stringify(value) : value
})

function harness(item = { id: 123 }) {
  const nodes = new Map()
  const handlers = new Map()
  const requests = []
  const copied = []
  const notices = []
  let listLoads = 0
  const document = {}
  const $ = selector => {
    if (selector?.node) return selector
    if (!nodes.has(selector)) nodes.set(selector, {
      node: true, value: '', properties: {}, attributes: {}, dataValues: {}, content: '', classes: new Set(),
      val(value) { if (!arguments.length) return this.value; this.value = value; return this },
      prop(name, value) { if (arguments.length === 1) return this.properties[name]; this.properties[name] = value; return this },
      attr(name, value) { if (arguments.length === 1) return this.attributes[name]; this.attributes[name] = value; return this },
      data(name, value) { if (arguments.length === 1) return this.dataValues[name]; this.dataValues[name] = value; return this },
      is() { return this.properties.checked === true },
      iCheck(action) { this.properties.checked = action === 'check'; return this },
      text(value) { this.content = value; return this }, html(value) { this.content = value; return this },
      addClass(value) { String(value).split(/\s+/).filter(Boolean).forEach(name => this.classes.add(name)); return this },
      removeClass(value) { String(value).split(/\s+/).filter(Boolean).forEach(name => this.classes.delete(name)); return this },
      hasClass(value) { return this.classes.has(value) }, modal() { return this }, off() { return this },
      on(events, target, fn) { handlers.set(`${selector === document ? target : selector}|${events}`, fn || target); return this }
    })
    return nodes.get(selector)
  }
  $.post = (url, data, success) => {
    const request = {
      data, success,
      fail(fn) { this.failed = fn; return this }, always(fn) { this.finished = fn; return this },
      resolve(value) { this.success?.(value); this.finished?.() },
      reject() { this.failed?.(); this.finished?.() }
    }
    requests.push(request)
    return request
  }
  $('#form-item-otv-days').attr('max', '7')
  const context = {
    $, document, TextEncoder, store: { get: () => item },
    htmlEncode: value => String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;'),
    prepareExchangedData: value => value, copyToClipboard: async value => copied.push(value),
    toastr: { error: value => notices.push(value), warning: value => notices.push(value), info() {} }
  }
  vm.runInNewContext(source, context)
  const loadList = context.loadSecureSendsList
  context.loadSecureSendsList = () => { listLoads += 1 }
  const click = selector => handlers.get(`${selector}|click`).call($(selector))
  const edit = () => handlers.get('#modal-item-otv input:not(#form-item-otv-link), #modal-item-otv textarea|input change ifChanged')()
  const generate = () => click('#form-secure-send-generate')
  return { $, context, handlers, requests, copied, notices, click, edit, generate, loadList,
    get listLoads() { return listLoads } }
}

test('Each new item or note form starts on the internal address', () => {
  const h = harness()
  h.context.openSecureSendModal('item')
  assert.equal(h.$('#form-item-otv-subdomain').is(':checked'), false)
  assert.equal(h.$('#secure-send-address-preview').content, 'Address: https://vault.example.com')
  h.$('#form-item-otv-subdomain').prop('checked', true)
  h.context.openSecureSendModal('note')
  assert.equal(h.$('#form-item-otv-subdomain').is(':checked'), false)
  assert.equal(h.$('#secure-send-address-preview').content, 'Address: https://vault.example.com')
})

test('TOTP sharing is offered only for eligible items and stays opt-in', () => {
  const withoutTotp = harness({ id: 123, otp_for_item_enabled: 0 })
  withoutTotp.context.openSecureSendModal('item')
  assert.equal(withoutTotp.$('#secure-send-totp-option').hasClass('hidden'), true)
  withoutTotp.generate()
  assert.equal(JSON.parse(withoutTotp.requests[0].data.data).include_totp, 0)

  const withTotp = harness({ id: 123, otp_for_item_enabled: 1 })
  withTotp.context.openSecureSendModal('item')
  assert.equal(withTotp.$('#secure-send-totp-option').hasClass('hidden'), false)
  assert.equal(withTotp.$('#form-secure-send-include-totp').is(':checked'), false)
  withTotp.$('#form-secure-send-include-totp').prop('checked', true)
  withTotp.generate()
  assert.equal(JSON.parse(withTotp.requests[0].data.data).include_totp, 1)

  const note = harness({ id: 123, otp_for_item_enabled: 1 })
  note.context.openSecureSendModal('note')
  note.$('#form-secure-send-secret').val('synthetic secret')
  note.$('#form-secure-send-include-totp').prop('checked', true)
  note.generate()
  assert.equal(note.$('#secure-send-totp-option').hasClass('hidden'), true)
  assert.equal(JSON.parse(note.requests[0].data.data).include_totp, 0)
})

test('Rapid generation submits once and restores the button after network failure', () => {
  const h = harness()
  h.context.openSecureSendModal('item')
  h.generate(); h.generate()
  assert.equal(h.requests.length, 1)
  assert.equal(h.$('#form-secure-send-generate').prop('disabled'), true)
  h.requests[0].reject()
  assert.equal(h.$('#form-secure-send-generate').prop('disabled'), false)
  assert.equal(h.notices.length, 1)
  h.generate()
  assert.equal(h.requests.length, 2)
})

test('Editing the form clears the displayed URL and revokes a late generated link', async () => {
  const h = harness()
  h.context.openSecureSendModal('item')
  h.generate()
  h.requests[0].resolve({ error: '', url: 'https://share.example.com/link-one', otv_id: 1, has_passphrase: 0 })
  assert.equal(h.$('#form-item-otv-copy-button').prop('disabled'), false)
  await h.handlers.get('#form-item-otv-copy-button|click.securesend')()
  assert.deepEqual(h.copied, ['https://share.example.com/link-one'])
  h.edit()
  assert.equal(h.$('#form-item-otv-link').val(), '')
  assert.equal(h.$('#form-item-otv-copy-button').prop('disabled'), true)
  await h.handlers.get('#form-item-otv-copy-button|click.securesend')()
  assert.equal(h.copied.length, 1)
  h.generate()
  h.edit()
  h.requests[1].resolve({ error: '', url: 'stale', otv_id: 2, has_passphrase: 1 })
  assert.equal(h.$('#form-item-otv-link').val(), '')
  assert.equal(h.$('#form-item-otv-copy-button').prop('disabled'), true)
  assert.equal(h.requests.length, 3)
  assert.equal(h.requests[2].data.type, 'revoke_secure_send')
  assert.deepEqual(JSON.parse(h.requests[2].data.data), { id: 2 })
  const listLoadsBeforeRevoke = h.listLoads
  h.requests[2].reject()
  assert.equal(h.listLoads, listLoadsBeforeRevoke + 1)
  assert.deepEqual(h.notices, [])
})

test('Standalone notes also send the public-address choice', () => {
  const h = harness()
  h.context.openSecureSendModal('note')
  h.$('#form-item-otv-subdomain').prop('checked', true)
  h.$('#form-secure-send-secret').val('synthetic secret')
  h.$('#form-secure-send-note').val('')
  h.generate()
  const data = JSON.parse(h.requests[0].data.data)
  assert.equal(data.send_type, 'note')
  assert.equal(data.shared_globaly, 1)
})

test('A required whitespace-only passphrase is rejected before creating a link', () => {
  const h = harness()
  h.context.openSecureSendModal('item')
  h.$('#form-secure-send-passphrase').prop('required', true)
  h.$('#form-secure-send-passphrase').val('   ')
  h.generate()
  assert.equal(h.requests.length, 0)
  assert.deepEqual(h.notices, ['secure_send_passphrase_required_error'])
})

test('Passphrase byte limits are checked before submission without changing the value', () => {
  const h = harness()
  h.context.openSecureSendModal('item')
  h.$('#form-secure-send-passphrase').val('é'.repeat(513))
  h.generate()
  assert.equal(h.requests.length, 0)
  assert.deepEqual(h.notices, ['secure_send_invalid_payload'])

  const boundary = 'é'.repeat(512)
  h.$('#form-secure-send-passphrase').val(boundary)
  h.generate()
  assert.equal(h.requests.length, 1)
  assert.equal(JSON.parse(h.requests[0].data.data).passphrase, boundary)
})

test('Closing the form makes a late response uncopyable', () => {
  const h = harness()
  h.context.openSecureSendModal('item')
  h.generate()
  h.handlers.get('#modal-item-otv|hidden.bs.modal')()
  h.requests[0].resolve({ error: '', url: 'late', otv_id: 1, has_passphrase: 0 })
  assert.equal(h.$('#form-item-otv-link').val(), '')
  assert.equal(h.$('#form-item-otv-copy-button').prop('disabled'), true)
})

test('Editing the form suppresses a stale network error', () => {
  const h = harness()
  h.context.openSecureSendModal('item')
  h.generate()
  h.edit()
  h.requests[0].reject()
  assert.deepEqual(h.notices, [])
  assert.equal(h.$('#form-secure-send-generate').prop('disabled'), false)
})

test('Malformed generation responses never enable copying', () => {
  const h = harness()
  h.context.openSecureSendModal('item')
  h.generate()
  h.requests[0].resolve(null)
  assert.deepEqual(h.notices, ['server_answer_error'])
  assert.equal(h.$('#form-item-otv-copy-button').prop('disabled'), true)
})

test('A successful-looking response still requires a valid link identity', () => {
  const h = harness()
  h.context.openSecureSendModal('item')
  h.generate()
  h.requests[0].resolve({ error: '', url: 'https://share.example.com/orphan', otv_id: null, has_passphrase: 0 })
  assert.deepEqual(h.notices, ['server_answer_error'])
  assert.equal(h.$('#form-item-otv-link').val(), '')
  assert.equal(h.$('#form-item-otv-copy-button').prop('disabled'), true)
})

test('An older list response cannot replace a newer Secure Send list', () => {
  const h = harness()
  h.loadList()
  h.loadList()
  h.requests[1].resolve({ error: '', sends: [{ id: 2, send_type: 'item', label: 'Current', has_passphrase: 0, remaining_views: 1, expires_label: 'later' }] })
  assert.match(h.$('#secure-send-list').content, /Current/)
  h.requests[0].resolve({ error: '', sends: [{ id: 1, send_type: 'item', label: 'Stale', has_passphrase: 0, remaining_views: 1, expires_label: 'earlier' }] })
  assert.match(h.$('#secure-send-list').content, /Current/)
  assert.doesNotMatch(h.$('#secure-send-list').content, /Stale/)
})

test('My secure sends escapes decoded item labels exactly once', () => {
  const h = harness()
  h.loadList()
  h.requests[0].resolve({ error: '', sends: [{
    id: 1, send_type: 'item', label: 'R&D O\'Brien', has_passphrase: 0,
    remaining_views: 1, expires_label: 'later'
  }] })
  assert.match(h.$('#secure-send-list').content, /R&amp;D O&#039;Brien/)
  assert.doesNotMatch(h.$('#secure-send-list').content, /&amp;amp;|&amp;#039;/)
})

test('Malformed list responses report an error instead of an empty list', () => {
  const h = harness()
  h.loadList()
  h.requests[0].resolve([{ error: 'key_not_conform' }])
  assert.deepEqual(h.notices, ['server_answer_error'])
})

test('A revoke action cannot be submitted twice while pending', () => {
  const h = harness()
  const button = h.$('.secure-send-revoke').data('id', 7)
  const revoke = () => h.handlers.get('.secure-send-revoke|click').call(button)
  revoke()
  revoke()
  assert.equal(h.requests.length, 1)
  assert.equal(button.prop('disabled'), true)
  h.requests[0].resolve({ error: '', id: 7 })
  assert.equal(button.prop('disabled'), false)
})

test('A truncated snapshot warns the sender without discarding its usable link', () => {
  const h = harness()
  h.context.openSecureSendModal('item')
  h.generate()
  h.requests[0].resolve({ error: '', url: 'https://share.example.com/copy', otv_id: 1, has_passphrase: 0, description_truncated: true })
  assert.deepEqual(h.notices, ['secure_send_description_truncated'])
  assert.equal(h.$('#form-item-otv-copy-button').prop('disabled'), false)
})

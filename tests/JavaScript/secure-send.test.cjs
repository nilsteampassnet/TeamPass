const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
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

function harness() {
  const nodes = new Map()
  const handlers = new Map()
  const requests = []
  const copied = []
  const notices = []
  const document = {}
  const $ = selector => {
    if (selector?.node) return selector
    if (!nodes.has(selector)) nodes.set(selector, {
      node: true, value: '', properties: {}, attributes: {}, dataValues: {}, content: '',
      val(value) { if (!arguments.length) return this.value; this.value = value; return this },
      prop(name, value) { if (arguments.length === 1) return this.properties[name]; this.properties[name] = value; return this },
      attr(name, value) { if (arguments.length === 1) return this.attributes[name]; this.attributes[name] = value; return this },
      data(name, value) { if (arguments.length === 1) return this.dataValues[name]; this.dataValues[name] = value; return this },
      is() { return this.properties.checked === true },
      iCheck(action) { this.properties.checked = action === 'check'; return this },
      text(value) { this.content = value; return this }, html(value) { this.content = value; return this },
      addClass() { return this }, removeClass() { return this }, modal() { return this }, off() { return this },
      on(events, target, fn) { handlers.set(`${selector === document ? target : selector}|${events}`, fn || target); return this }
    })
    return nodes.get(selector)
  }
  $.post = (url, data, success) => {
    const request = {
      data, success,
      fail(fn) { this.failed = fn; return this }, always(fn) { this.finished = fn; return this },
      resolve(value) { this.success(value); this.finished?.() },
      reject() { this.failed?.(); this.finished?.() }
    }
    requests.push(request)
    return request
  }
  $('#form-item-otv-days').attr('max', '7')
  const context = {
    $, document, store: { get: () => ({ id: 123 }) }, htmlEncode: value => value,
    prepareExchangedData: value => value, copyToClipboard: async value => copied.push(value),
    toastr: { error: value => notices.push(value), warning: value => notices.push(value), info() {} }
  }
  vm.runInNewContext(source, context)
  const loadList = context.loadSecureSendsList
  context.loadSecureSendsList = () => {}
  const click = selector => handlers.get(`${selector}|click`).call($(selector))
  const edit = () => handlers.get('#modal-item-otv input:not(#form-item-otv-link), #modal-item-otv textarea|input change ifChanged')()
  const generate = () => click('#form-secure-send-generate')
  return { $, context, handlers, requests, copied, notices, click, edit, generate, loadList }
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

test('Editing the form discards both the displayed URL and a stale generation response', async () => {
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

test('A required empty passphrase is rejected before creating a link', () => {
  const h = harness()
  h.context.openSecureSendModal('item')
  h.$('#form-secure-send-passphrase').prop('required', true)
  h.generate()
  assert.equal(h.requests.length, 0)
  assert.deepEqual(h.notices, ['secure_send_passphrase_required_error'])
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

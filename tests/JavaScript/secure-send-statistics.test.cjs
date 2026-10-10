/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (C) 2009-2026 Teampass.net
 * This file is part of TeamPass, distributed under the GNU GPL version 3.
 * See <https://www.gnu.org/licenses/>. Provided WITHOUT ANY WARRANTY.
 */
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

const source = readFileSync(join(__dirname, '../../app/pages/statistics.js.php'), 'utf8').replace(/\r\n/g, '\n')
function section(start, end) {
  const first = source.indexOf('    function ' + start)
  const last = source.indexOf('    function ' + end, first)
  assert.ok(first >= 0 && last > first, 'Missing production function: ' + start)
  return source.slice(first, last)
}
const renderer = section('tpOpsSecureSendText(', 'renderUserRanking(').replace(
  /<\?php echo json_encode\(\$lang->get\('(\w+)'\), JSON_HEX_TAG \| JSON_HEX_AMP \| JSON_HEX_APOS \| JSON_HEX_QUOT\); \?>/g,
  (_, key) => JSON.stringify('[' + key + ']')
)
assert.ok(!renderer.includes('<?php'))
const encoder = source.slice(source.indexOf('    function escapeHtml('), source.indexOf('</script>'))
const loader = section('loadOperationalStatistics(', 'renderOperationalStatistics(')
  .replace(/<\?php[\s\S]*?\?>/g, 'inert-test-value')
const fields = {
  totals: ['created', 'revealed', 'reveal_failed', 'senders', 'sends_revealed', 'revoked', 'invalidated', 'expired'],
  creations: ['items', 'notes', 'unknown', 'protected', 'unprotected', 'public_links', 'internal_links']
}

function payload() {
  return { enabled: true, available: true, error: false, top_senders: [],
    totals: Object.fromEntries(fields.totals.map(key => [key, 0])),
    creations: Object.fromEntries(fields.creations.map(key => [key, 0])) }
}

function harness() {
  const elements = new Map()
  const requests = []
  function element(selector) {
    if (!elements.has(selector)) {
      const state = { selector, value: '', properties: {}, attributes: {} }
      const adapter = {
        state,
        text(value) { if (value === undefined) return state.value; state.value = value; return adapter },
        html(value) { state.html = value; return adapter },
        prop(key, value) { state.properties[key] = value; return adapter },
        attr(key, value) { if (value === undefined) return state.attributes[key]; state.attributes[key] = value; return adapter },
        addClass() { return adapter }, removeClass() { return adapter },
        val() { return '30d' }, is() { return true }
      }
      elements.set(selector, adapter)
    }
    return elements.get(selector)
  }
  const counters = Object.entries(fields).flatMap(([group, keys]) => keys.map(key => {
    const node = element(group + '.' + key)
    node.attr('data-tp-secure-send-count', group + '.' + key)
    return node
  }))
  function $(selector) {
    if (typeof selector === 'object') return selector
    if (selector === '#tp-secure-send-card [data-tp-secure-send-count]') {
      return { each(callback) { counters.forEach(node => callback.call(node)); return this } }
    }
    return element(selector)
  }
  $.each = (list, callback) => list.forEach((value, index) => callback(index, value))
  $.post = (url, data, callback) => {
    const request = { url, data, callback }
    requests.push(request)
    return { fail(handler) { request.fail = handler } }
  }
  const context = { $, tpOpsRequestId: 0, tpOpsLastData: null,
    prepareExchangedData(value) { if (value === 'decode-failure') throw new Error('decode'); return value },
    toastr: { remove() {}, error() {} } }
  vm.createContext(context)
  vm.runInContext(encoder + renderer + loader + section('tpOpsRerenderFromCache(', 'tpOpsEnsureLoadedOrRerender('), context)
  context.renderOperationalStatistics = data => context.renderSecureSendStatistics(data.users.secure_send)
  return { context, requests, node: selector => element(selector).state, render: (data, state) => context.renderSecureSendStatistics(data, state) }
}

test('valid zero counts are visible; missing/failed/null data is unavailable, never zero', () => {
  const h = harness()
  h.render(payload())
  assert.equal(h.node('#tp-secure-send-content').properties.hidden, false)
  assert.equal(h.node('totals.created').value, 0)
  assert.match(h.node('#tp-secure-send-senders').html, /ops_secure_send_no_creations/)
  for (const unavailable of [undefined, { available: false, error: true }, { ...payload(), totals: null }, { ...payload(), error: true }, { ...payload(), top_senders: [null] }]) {
    h.render(unavailable)
    assert.equal(h.node('#tp-secure-send-content').properties.hidden, true)
    assert.equal(h.node('totals.created').value, '—')
    assert.match(h.node('#tp-secure-send-status').value, /ops_secure_send_unavailable/)
    assert.equal(h.node('#tp-secure-send-senders').html, '')
  }
})

test('loading clears previous activity and exposes an accessible busy state', () => {
  const h = harness()
  const data = payload(); data.totals.created = 9
  h.render(data)
  h.render(null, 'loading')
  assert.equal(h.node('totals.created').value, '—')
  assert.equal(h.node('#tp-secure-send-card').attributes['aria-busy'], 'true')
  assert.match(h.node('#tp-secure-send-status').value, /ops_secure_send_loading/)
  h.render(data)
  assert.equal(h.node('#tp-secure-send-card').attributes['aria-busy'], 'false')
})

test('disabling Secure Send preserves recorded history and adds its notice', () => {
  const h = harness()
  const data = payload(); data.enabled = false; data.totals.created = 42
  h.render(data)
  assert.equal(h.node('totals.created').value, 42)
  assert.equal(h.node('#tp-secure-send-disabled').properties.hidden, false)
  h.render(null)
  assert.equal(h.node('#tp-secure-send-disabled').properties.hidden, true)
})

test('all breakdowns use actual event counts; legacy reveals do not invent creations', () => {
  const h = harness()
  const data = payload(); data.totals.revealed = 8; data.totals.sends_revealed = 2
  h.render(data)
  assert.equal(h.node('totals.created').value, 0)
  assert.equal(h.node('totals.revealed').value, 8)
  assert.equal(h.node('totals.sends_revealed').value, 2)
  assert.match(h.node('#tp-secure-send-senders').html, /ops_secure_send_no_creations/)
  for (const [group, keys] of Object.entries(fields)) {
    keys.forEach((key, index) => { data[group][key] = index + 11 })
  }
  h.render(data)
  for (const [group, keys] of Object.entries(fields)) {
    keys.forEach((key, index) => assert.equal(h.node(group + '.' + key).value, index + 11))
  }
})

test('sender identities are escaped, removed accounts have a fallback, and only five rows render', () => {
  const h = harness()
  const data = payload()
  data.top_senders = Array.from({ length: 6 }, (_, index) => ({ id: index + 1, name: '<img onerror="alert(1)">', login: '</script>&\'"',
    account_state: ['active', 'disabled', 'deleted', 'missing'][index % 4], created: index + 1, revealed: 2, reveal_failed: 3 }))
  data.top_senders[3].name = ''; data.top_senders[3].login = ''
  h.render(data)
  const html = h.node('#tp-secure-send-senders').html
  assert.equal((html.match(/<tr>/g) || []).length, 5)
  assert.ok(!/<img|<script|<\/script>/i.test(html))
  assert.match(html, /&lt;img/)
  assert.match(html, /&lt;\/script&gt;&amp;&#039;&quot;/)
  for (const status of ['disabled', 'deleted', 'missing']) assert.ok(html.includes('ops_secure_send_account_' + status))
  assert.match(html, /ops_secure_send_sender\] #4/)
})

test('malformed counts cannot inject markup or masquerade as valid zero totals', () => {
  const h = harness()
  const data = payload(); data.totals.created = '<img onerror=alert(1)>'
  h.render(data)
  assert.equal(h.node('totals.created').value, '—')
  assert.equal(h.node('#tp-secure-send-content').properties.hidden, true)
  const valid = payload()
  valid.top_senders = [{ id: 1, name: 'sender', created: '<img>', revealed: -1, reveal_failed: null }]
  h.render(valid)
  assert.ok(!h.node('#tp-secure-send-senders').html.includes('<img>'))
  assert.equal((h.node('#tp-secure-send-senders').html.match(/>—</g) || []).length, 3)
})

test('latest request wins; late success/failure cannot restore a previous period', () => {
  const h = harness()
  h.context.loadOperationalStatistics()
  h.context.loadOperationalStatistics()
  const current = payload(); current.totals.created = 17
  h.requests[1].callback({ users: { secure_send: current } })
  h.requests[0].callback({ users: { secure_send: payload() } })
  h.requests[0].fail()
  assert.equal(h.node('totals.created').value, 17)
  assert.equal(h.context.tpOpsLastData.users.secure_send.totals.created, 17)
  assert.equal(h.node('#tp-ops-refresh').properties.disabled, false)
})

test('network, decoding and server failures clear cached activity even across tab switches', () => {
  for (const failure of ['network', 'decode', 'server', 'empty']) {
    const h = harness()
    h.context.loadOperationalStatistics()
    h.requests[0].callback({ users: { secure_send: payload() } })
    h.context.loadOperationalStatistics()
    if (failure === 'network') h.requests[1].fail()
    if (failure === 'decode') h.requests[1].callback('decode-failure')
    if (failure === 'server') h.requests[1].callback({ error: true })
    if (failure === 'empty') h.requests[1].callback(null)
    h.context.tpOpsRerenderFromCache()
    assert.equal(h.context.tpOpsLastData, null)
    assert.equal(h.node('totals.created').value, '—')
    assert.match(h.node('#tp-secure-send-status').value, /ops_secure_send_unavailable/)
    assert.equal(h.node('#tp-ops-refresh').properties.disabled, false)
  }
})

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of the TeamPass project, distributed under the GNU GPL-3.0.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 */
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const { test } = require('node:test')
const vm = require('node:vm')

const template = readFileSync(join(__dirname, '../../app/core/secure-send.js.php'), 'utf8')
const source = template.slice(template.indexOf('<script>') + 8, template.lastIndexOf('</script>'))
  .replace(/<\?php[\s\S]*?\?>/g, php => {
    const key = php.match(/\$lang->get\('([^']+)'\)/)?.[1]
    return JSON.stringify(key === 'secure_send_active_count' ? 'Active: #COUNT#' : key || 'session-key')
  })

function harness() {
  const nodes = new Map()
  const requests = []
  const timers = []
  const document = { hidden: false }
  const window = { confirm() { throw new Error('Native confirmation must not be used') }, setInterval(fn) { timers.push(fn) } }
  let now = 100000
  let focused = null
  function node(key, attrs = {}) {
    if (!nodes.has(key)) {
      const entry = {
        key, attrs, value: '', properties: {}, children: [], handlers: new Map(), classes: new Set(), scroll: 0,
        text(value) { this.value = value; return this },
        attr(name, value) { this.attrs[name] = value; return this },
        prop(name, value) { this.properties[name] = value; return this },
        empty() { this.children = []; return this },
        appendTo(parent) { this.parent = parent; parent.children.push(this); return this },
        remove() { this.parent.children = this.parent.children.filter(child => child !== this); return this },
        scrollTop(value) { if (value === undefined) return this.scroll; this.scroll = value; return this },
        addClass(name) { this.classes.add(name); return this },
        removeClass(name) { this.classes.delete(name); return this },
        on(name, callback) { this.handlers.set(name, callback); return this },
        trigger(name, args = []) { if (name === 'focus') focused = this; this.handlers.get(name)?.({}, ...args); return this },
        modal(action) { if (action === 'hide') this.trigger('hide.bs.modal').trigger('hidden.bs.modal'); return this }
      }
      nodes.set(key, entry)
    }
    return nodes.get(key)
  }
  let nextId = 0
  function $(selector, attrs) {
    if (typeof selector === 'function') { selector(); return }
    if (typeof selector === 'string' && selector.startsWith('<')) return node('created-' + ++nextId, { tag: selector, ...attrs })
    return node(selector)
  }
  $.ajax = options => {
    const request = { options,
      done(fn) { this.success = fn; return this }, fail(fn) { this.failure = fn; return this },
      always(fn) { this.finished = fn; return this } }
    requests.push(request)
    return request
  }
  vm.runInNewContext(source, { $, window, document, Date: { now: () => now }, prepareExchangedData: value => value })
  return { requests, window, document, node, timers,
    open() { node('#tp-secure-send-modal').trigger('show.bs.modal') },
    close() { node('#tp-secure-send-modal').trigger('hide.bs.modal').trigger('hidden.bs.modal') },
    reply(index, data, failed = false) {
      const request = requests[index]
      if (failed) request.failure?.(); else request.success?.(data)
      request.finished?.()
    },
    advance() { now += 60000; timers[0]() },
    revoke(index = 0) { node('#tp-my-sends-list').children[index].children.at(-1).trigger('click') },
    confirm() { node('#tp-my-sends-revoke-confirm').trigger('click') },
    cancel() { node('#tp-my-sends-revoke-cancel').trigger('click') },
    get focused() { return focused },
    createdNodes() { return [...nodes.values()].filter(n => n.attrs.tag) }
  }
}

function send(changes = {}) {
  return { id: 7, item_id: 123, folder_id: 7, label: 'R&D, O\'Brien, "<img src=x onerror=alert(1)>"', send_type: 'item',
    has_passphrase: 1, remaining_views: 2, expires_at: 2000, expires_label: '31/10/2026 18:00', ...changes }
}
const list = sends => ({ error: '', enabled: true, sends })
const count = value => ({ error: '', enabled: true, count: value })

test('only counts are loaded before opening; zero and large counts have accessible labels', () => {
  const h = harness()
  assert.equal(h.requests[0].options.data.type, 'count_secure_sends')
  assert.equal(h.requests[0].options.data.key, 'session-key')
  assert.equal(h.requests[0].options.timeout, 15000)
  h.reply(0, count(0))
  assert.equal(h.node('#tp-secure-send-badge').properties.hidden, true)
  assert.match(h.node('#tp-navbar-secure-send').attrs['aria-label'], /Active: 0/)
  h.advance()
  h.reply(1, count(150))
  assert.equal(h.node('#tp-secure-send-badge').value, '99+')
  assert.match(h.node('#tp-navbar-secure-send').attrs.title, /Active: 150/)
})

test('opening reloads the list and renders labels and metadata exclusively as text', () => {
  const h = harness()
  h.open()
  assert.equal(h.requests[1].options.data.type, 'list_secure_sends')
  h.reply(1, list([send()]))
  assert.equal(h.node('#tp-my-sends-list').children.length, 1)
  assert.equal(h.node('#tp-secure-send-badge').value, '1')
  assert.ok(h.createdNodes().some(n => n.value === send().label))
  assert.equal(h.node('#tp-my-sends-list').children[0].children.at(-1).attrs['aria-describedby'], 'tp-my-send-label-7')
  assert.ok(h.createdNodes().every(n => ['<li>', '<i>', '<div>', '<a>', '<span>', '<button>'].includes(n.attrs.tag)))
  h.close()
  assert.equal(h.node('#tp-my-sends-list').children.length, 0)
})

test('item labels link to the authenticated item page using the current folder, not the send ID', () => {
  const h = harness()
  h.open()
  h.reply(1, list([send()]))
  const label = h.node('#tp-my-sends-list').children[0].children[1].children[0]
  assert.equal(label.attrs.tag, '<a>')
  assert.equal(label.attrs.href, 'index.php?page=items&group=7&id=123')
  assert.equal(label.value, send().label)
  assert.equal(label.attrs.target, undefined)
  assert.equal(h.requests.length, 2)
  h.close()
  h.open()
  h.reply(h.requests.length - 1, list([send({ folder_id: 19 })]))
  assert.equal(h.node('#tp-my-sends-list').children[0].children[1].children[0].attrs.href,
    'index.php?page=items&group=19&id=123')
})

test('standalone notes never link to an item even if navigation fields are present', () => {
  const h = harness()
  h.open()
  h.reply(1, list([send({ send_type: 'note' }), send({ id: 8, send_type: 'note', item_id: null, folder_id: null })]))
  for (const row of h.node('#tp-my-sends-list').children) {
    const label = row.children[1].children[0]
    assert.equal(label.attrs.tag, '<span>')
    assert.equal(label.attrs.href, undefined)
  }
})

test('missing or malformed navigation IDs keep the link revocable without creating a URL', () => {
  for (const invalid of [undefined, null, 0, -1, '7', 'javascript:alert(1)', 1.5, Number.MAX_SAFE_INTEGER + 1]) {
    for (const field of ['item_id', 'folder_id']) {
      const h = harness()
      h.open()
      h.reply(1, list([send({ [field]: invalid })]))
      const row = h.node('#tp-my-sends-list').children[0]
      assert.equal(row.children[1].children[0].attrs.href, undefined)
      h.revoke()
      h.confirm()
      assert.equal(h.requests.at(-1).options.data.type, 'revoke_secure_send')
      assert.equal(h.requests.at(-1).options.data.data, '{"id":7}')
    }
  }
})

test('late count and list responses cannot overwrite a newer inventory or reopen private labels', () => {
  const h = harness()
  h.open()
  h.reply(1, list([send()]))
  h.reply(0, count(20))
  assert.equal(h.node('#tp-secure-send-badge').value, '1')
  h.close()
  h.open()
  const pendingList = h.requests.length - 1
  h.close()
  h.reply(pendingList, list([send()]))
  assert.equal(h.node('#tp-my-sends-list').children.length, 0)
})

test('empty inventory, malformed payloads and network errors remain distinct and retryable', () => {
  const h = harness()
  h.open()
  h.reply(1, list([]))
  assert.equal(h.node('#tp-my-sends-status').value, 'secure_send_no_active')
  for (const payload of [list([send({ remaining_views: 0 })]), list([send({ id: '7' })]), { error: 'server_error' }, null]) {
    h.close()
    h.open()
    h.reply(h.requests.length - 1, payload)
    assert.equal(h.node('#tp-my-sends-status').value, 'secure_send_inventory_unavailable')
    assert.equal(h.node('#tp-secure-send-badge').value, '—')
  }
  h.close()
  h.open()
  h.reply(h.requests.length - 1, null, true)
  assert.equal(h.node('#tp-my-sends-list').children.length, 0)
})

test('disabling the feature hides the entry, clears labels and stops polling', () => {
  const h = harness()
  h.open()
  h.reply(1, { error: '', enabled: false, sends: [] })
  assert.equal(h.node('#tp-secure-send-nav').properties.hidden, true)
  assert.equal(h.node('#tp-my-sends-list').children.length, 0)
  h.advance()
  assert.equal(h.requests.length, 2)
})

test('hidden pages do not poll; the closed badge refreshes and open dialogs stay stable', () => {
  const h = harness()
  h.document.hidden = true
  h.advance()
  assert.equal(h.requests.length, 1)
  h.document.hidden = false
  h.node(h.document).trigger('visibilitychange')
  assert.equal(h.requests.at(-1).options.data.type, 'count_secure_sends')
  h.node(h.document).trigger('tp-secure-send-changed', ['items'])
  assert.equal(h.requests.length, 3)
  h.open()
  h.reply(3, list([send()]))
  const entries = h.node('#tp-my-sends-list').children
  const requestCount = h.requests.length
  h.advance()
  h.node(h.window).trigger('focus')
  h.node(h.document).trigger('visibilitychange')
  h.node(h.document).trigger('tp-secure-send-changed', ['items'])
  assert.equal(h.requests.length, requestCount)
  assert.equal(h.node('#tp-my-sends-list').children, entries)
  h.close()
  assert.equal(h.requests.at(-1).options.data.type, 'count_secure_sends')
})

test('revocation uses the TeamPass confirmation view and cancel restores focus and scroll', () => {
  const h = harness()
  h.open()
  h.reply(1, list([send()]))
  const row = h.node('#tp-my-sends-list').children[0]
  const button = row.children.at(-1)
  h.node('#tp-my-sends-body').scrollTop(350)
  h.node('#tp-my-sends-list').scrollTop(700)
  h.revoke()
  assert.equal(h.requests.length, 2)
  assert.equal(h.node('#tp-my-sends-confirm-view').properties.hidden, false)
  assert.equal(h.node('#tp-my-sends-list-view').properties.hidden, true)
  assert.equal(h.node('#tp-my-sends-confirm-label').value, send().label)
  assert.equal(h.focused, h.node('#tp-my-sends-revoke-cancel'))
  h.cancel()
  assert.equal(h.requests.length, 2)
  assert.equal(h.node('#tp-my-sends-confirm-view').properties.hidden, true)
  assert.equal(h.node('#tp-my-sends-list').children[0], row)
  assert.equal(h.node('#tp-my-sends-body').scrollTop(), 350)
  assert.equal(h.node('#tp-my-sends-list').scrollTop(), 700)
  assert.equal(h.focused, button)
})

test('a long list remains stable and revocation removes only its row without reloading', () => {
  const h = harness()
  h.open()
  const sends = Array.from({ length: 250 }, (_, index) => send({ id: index + 1, label: send().label.repeat(5) }))
  h.reply(1, list(sends))
  const rows = [...h.node('#tp-my-sends-list').children]
  h.node('#tp-my-sends-body').scrollTop(18000)
  h.node('#tp-my-sends-list').scrollTop(19000)
  h.revoke(125)
  h.advance()
  assert.equal(h.requests.length, 2)
  h.confirm()
  h.confirm()
  h.revoke()
  h.advance()
  assert.equal(h.requests.length, 3)
  assert.equal(h.requests[2].options.data.type, 'revoke_secure_send')
  assert.equal(h.requests[2].options.data.data, '{"id":126}')
  h.reply(2, { error: '', id: 126 })
  const remaining = h.node('#tp-my-sends-list').children
  assert.equal(h.requests.length, 3)
  assert.equal(remaining.length, 249)
  assert.ok(!remaining.includes(rows[125]))
  assert.ok(remaining.every((row, index) => row === rows[index < 125 ? index : index + 1]))
  assert.equal(h.node('#tp-secure-send-badge').value, '99+')
  assert.equal(h.node('#tp-my-sends-body').scrollTop(), 18000)
  assert.equal(h.node('#tp-my-sends-list').scrollTop(), 19000)
  assert.equal(h.focused, rows[126].children.at(-1))
})

test('revoking the last link displays the empty state without a new list request', () => {
  const h = harness()
  h.open()
  h.reply(1, list([send()]))
  h.revoke()
  h.confirm()
  h.reply(2, { error: '', id: 7 })
  assert.equal(h.requests.length, 3)
  assert.equal(h.node('#tp-secure-send-badge').properties.hidden, true)
  assert.equal(h.node('#tp-my-sends-status').value, 'secure_send_no_active')
  assert.equal(h.focused, h.node('#tp-my-sends-close'))
})

test('a failed revocation preserves the list and leaves an enabled cancel action', () => {
  const h = harness()
  h.open()
  h.reply(1, list([send()]))
  const rows = h.node('#tp-my-sends-list').children
  h.revoke()
  h.confirm()
  h.reply(2, { error: 'not_allowed' })
  assert.equal(h.node('#tp-my-sends-list').children, rows)
  assert.equal(h.node('#tp-my-sends-revoke-status').value, 'secure_send_revoke_failed')
  assert.equal(h.node('#tp-my-sends-revoke-cancel').properties.disabled, false)
  h.cancel()
  assert.equal(h.node('#tp-my-sends-list-view').properties.hidden, false)
  assert.equal(h.requests.length, 3)
})

test('closing during a revocation does not reopen the dialog and refreshes only the badge', () => {
  const h = harness()
  h.open()
  h.reply(1, list([send()]))
  h.revoke()
  h.confirm()
  h.close()
  h.reply(2, { error: '', id: 7 })
  assert.equal(h.node('#tp-my-sends-list').children.length, 0)
  assert.equal(h.requests.at(-1).options.data.type, 'count_secure_sends')
})

test('closing a confirmation clears its label and cannot revoke a stale selection', () => {
  const h = harness()
  h.open()
  h.reply(1, list([send()]))
  h.revoke()
  h.close()
  const requestCount = h.requests.length
  h.confirm()
  assert.equal(h.node('#tp-my-sends-confirm-label').value, '')
  assert.equal(h.requests.length, requestCount)
  assert.equal(h.requests.at(-1).options.data.type, 'count_secure_sends')
})

test('a network failure during revocation keeps the snapshot and allows cancellation', () => {
  const h = harness()
  h.open()
  h.reply(1, list([send()]))
  const row = h.node('#tp-my-sends-list').children[0]
  h.revoke()
  h.confirm()
  h.reply(2, null, true)
  assert.equal(h.node('#tp-my-sends-revoke-status').value, 'secure_send_revoke_failed')
  assert.equal(h.node('#tp-my-sends-list').children[0], row)
  h.cancel()
  assert.equal(h.node('#tp-my-sends-confirm-view').properties.hidden, true)
})

test('reopening during a revocation loads the next snapshot once the write finishes', () => {
  const h = harness()
  h.open()
  h.reply(1, list([send()]))
  h.revoke()
  h.confirm()
  h.close()
  h.open()
  assert.equal(h.requests.length, 3)
  h.reply(2, { error: '', id: 7 })
  assert.equal(h.requests.at(-1).options.data.type, 'list_secure_sends')
  h.reply(3, list([]))
  assert.equal(h.node('#tp-my-sends-list').children.length, 0)
})

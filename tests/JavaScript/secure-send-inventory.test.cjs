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
  const window = { confirm: () => true, setInterval(fn) { timers.push(fn) } }
  let now = 100000
  function node(key, attrs = {}) {
    if (!nodes.has(key)) {
      const entry = {
        key, attrs, value: '', properties: {}, children: [], handlers: new Map(), classes: new Set(),
        text(value) { this.value = value; return this },
        attr(name, value) { this.attrs[name] = value; return this },
        prop(name, value) { this.properties[name] = value; return this },
        empty() { this.children = []; return this },
        appendTo(parent) { parent.children.push(this); return this },
        addClass(name) { this.classes.add(name); return this },
        removeClass(name) { this.classes.delete(name); return this },
        on(name, callback) { this.handlers.set(name, callback); return this },
        trigger(name, args = []) { this.handlers.get(name)?.({}, ...args); return this },
        modal(action) { if (action === 'hide') this.trigger('hidden.bs.modal'); return this }
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
    open() { node('#tp-secure-send-modal').trigger('shown.bs.modal') },
    close() { node('#tp-secure-send-modal').trigger('hidden.bs.modal') },
    reply(index, data, failed = false) {
      const request = requests[index]
      if (failed) request.failure?.(); else request.success?.(data)
      request.finished?.()
    },
    advance() { now += 60000; timers[0]() },
    revoke() { node('#tp-my-sends-list').children[0].children.at(-1).trigger('click') },
    createdNodes() { return [...nodes.values()].filter(n => n.attrs.tag) }
  }
}

function send(changes = {}) {
  return { id: 7, label: 'R&D, O\'Brien, "<img src=x onerror=alert(1)>"', send_type: 'item',
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
  assert.ok(h.createdNodes().every(n => ['<li>', '<i>', '<div>', '<span>', '<button>'].includes(n.attrs.tag)))
  h.close()
  assert.equal(h.node('#tp-my-sends-list').children.length, 0)
})

test('late count and list responses cannot overwrite a newer inventory or reopen private labels', () => {
  const h = harness()
  h.open()
  h.reply(1, list([send()]))
  h.reply(0, count(20))
  assert.equal(h.node('#tp-secure-send-badge').value, '1')
  h.node('#tp-my-sends-refresh').trigger('click')
  h.close()
  h.reply(2, list([send()]))
  assert.equal(h.node('#tp-my-sends-list').children.length, 0)
})

test('empty inventory, malformed payloads and network errors remain distinct and retryable', () => {
  const h = harness()
  h.open()
  h.reply(1, list([]))
  assert.equal(h.node('#tp-my-sends-status').value, 'secure_send_no_active')
  for (const payload of [list([send({ remaining_views: 0 })]), list([send({ id: '7' })]), { error: 'server_error' }, null]) {
    h.node('#tp-my-sends-refresh').trigger('click')
    h.reply(h.requests.length - 1, payload)
    assert.equal(h.node('#tp-my-sends-status').value, 'secure_send_inventory_unavailable')
    assert.equal(h.node('#tp-secure-send-badge').value, '—')
    assert.equal(h.node('#tp-my-sends-refresh').properties.disabled, false)
  }
  h.node('#tp-my-sends-refresh').trigger('click')
  h.reply(h.requests.length - 1, null, true)
  assert.equal(h.node('#tp-my-sends-list').children.length, 0)
})

test('disabling the feature hides the entry, clears labels and stops polling', () => {
  const h = harness()
  h.open()
  h.reply(1, list([send()]))
  h.advance()
  h.reply(2, { error: '', enabled: false, sends: [] })
  assert.equal(h.node('#tp-secure-send-nav').properties.hidden, true)
  assert.equal(h.node('#tp-my-sends-list').children.length, 0)
  h.advance()
  assert.equal(h.requests.length, 3)
})

test('hidden pages do not poll; changes refresh the count and open modals poll their list', () => {
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
  h.advance()
  assert.equal(h.requests.at(-1).options.data.type, 'list_secure_sends')
})

test('revocation requires confirmation, cannot be submitted twice, and refreshes on success', () => {
  const h = harness()
  h.open()
  h.reply(1, list([send()]))
  h.window.confirm = () => false
  h.revoke()
  assert.equal(h.requests.length, 2)
  h.window.confirm = () => true
  h.revoke()
  h.revoke()
  h.advance()
  assert.equal(h.requests.length, 3)
  assert.equal(h.requests[2].options.data.type, 'revoke_secure_send')
  assert.equal(h.requests[2].options.data.data, '{"id":7}')
  h.reply(2, { error: '', id: 7 })
  assert.equal(h.requests[3].options.data.type, 'list_secure_sends')
  h.reply(3, list([]))
  assert.equal(h.node('#tp-secure-send-badge').properties.hidden, true)
})

test('a failed revocation clears stale labels and leaves an enabled retry action', () => {
  const h = harness()
  h.open()
  h.reply(1, list([send()]))
  h.revoke()
  h.reply(2, { error: 'not_allowed' })
  assert.equal(h.node('#tp-my-sends-list').children.length, 0)
  assert.equal(h.node('#tp-my-sends-status').value, 'secure_send_inventory_unavailable')
  assert.equal(h.node('#tp-my-sends-refresh').properties.disabled, false)
})

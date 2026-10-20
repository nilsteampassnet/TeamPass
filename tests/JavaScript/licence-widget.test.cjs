/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (C) 2009-2026 Teampass.net
 * Distributed under the GNU GPL version 3, WITHOUT ANY WARRANTY.
 * See <https://www.gnu.org/licenses/>.
 */
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

const source = readFileSync(join(__dirname, '../../app/pages/admin.js.php'), 'utf8')
const start = source.indexOf('function loadExtensionLicenceInfo(')
const end = source.indexOf('function updateHealthBadge(', start)
assert.ok(start >= 0 && end > start, 'Missing licence widget renderer')
const renderer = source.slice(start, end).replace(/<\?php[\s\S]*?\?>/g, 'test-value')

function render(display) {
  const data = { server_online: true, max_users: 10, consumed: 1, licence_status: 'VALID',
    expiration_date: '2027-06-16 23:59:59', expiration_display: display }
  let content = ''
  const block = { length: 1, html(value) { content = value; return block } }
  function $(selector) {
    if (selector === '#extension-licence-status') return block
    assert.equal(selector, '<span>')
    let text = ''
    const span = { text(value) { text = String(value); return span },
      html() { return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') } }
    return span
  }
  $.post = (_, body, callback) => callback(data)
  const context = vm.createContext({ $, prepareExchangedData: value => value })
  vm.runInContext(renderer, context)
  context.loadExtensionLicenceInfo()
  return content
}

test('the licence widget displays the configured label instead of the raw timestamp', () => {
  const html = render('16/06/2027')
  assert.ok(html.includes('16/06/2027'))
  assert.ok(!html.includes('2027-06-16'))
})

test('an unavailable display date does not fall back to the raw licence timestamp', () => {
  for (const display of ['', undefined]) {
    const html = render(display)
    assert.ok(!html.includes('2027-06-16'))
    assert.ok(!html.includes('fa-calendar-alt'))
  }
})

test('licence expiry display values are rendered as text', () => {
  const html = render('<img src=x onerror="alert(1)"> & date')
  assert.ok(!html.includes('<img'))
  assert.ok(html.includes('&lt;img'))
  assert.ok(html.includes('&amp; date'))
})

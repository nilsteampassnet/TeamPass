/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */
const assert = require('node:assert/strict')
const { test } = require('node:test')
const { readFileSync } = require('node:fs')
const vm = require('node:vm')

test('Associated item badges render decoded labels through a text sink and keep link attributes intact', () => {
  const source = readFileSync(require.resolve('../../app/pages/kb.js.php'), 'utf8').replace(/\r\n/g, '\n')
  const start = source.indexOf('    function kbRenderAssociatedItems(')
  const end = source.indexOf('\n    }', start)
  assert.ok(start >= 0 && end > start)
  const appended = []
  const container = { html() {}, append(value) { appended.push(value) } }
  const zone = { addClass() {}, removeClass() {} }
  const decoded = new Map([
    ['R&amp;D O&apos;Brien &lt;img src=x onerror=alert(1)&gt;', "R&D O'Brien <img src=x onerror=alert(1)>"],
    ['&amp;lt;literal&amp;gt;', '&lt;literal&gt;']
  ])
  const context = vm.createContext({
    $(selector, attributes) {
      if (selector === '#kb-viewer-items') return container
      if (selector === '#kb-viewer-items-zone') return zone
      assert.equal(selector, '<a>', 'Build a link element without interpolating labels into HTML')
      return { attributes, text(value) { this.textContent = value; return this } }
    },
    kbDecodeHtmlEntities: text => decoded.get(text),
    DOMPurify: { sanitize: value => value }
  })
  vm.runInContext(source.slice(start, end + 6), context)
  const link = '/index.php?page=items&group=7&id=1'
  context.kbRenderAssociatedItems(Array.from(decoded.keys(), label => ({ label, link })))
  assert.deepEqual(appended.map(element => element.textContent), Array.from(decoded.values()))
  assert.ok(appended.every(element => element.attributes.href === link))
})

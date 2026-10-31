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

const source = readFileSync(require.resolve('../../app/pages/items.js.php'), 'utf8')
const search = source.match(/'search_callback':\s*(function\(str, node\) \{[\s\S]*?\n {12}\})/)
assert.ok(search, 'The Items tree must search displayed names through a custom jsTree callback')

// htmlDecode is the DOMParser-backed display-text helper. Supply its browser result
// here so these tests exercise the shipped search callback without a DOM dependency.
const labels = [
  { html: '<i class="fas fa-folder"></i>Lab O&#039;Brien', displayed: "Lab O'Brien",
    matches: ["O'Brien", "o'brien", 'LAB'], misses: ['apos', '039', 'fa-folder'] },
  { html: '<i class="fas fa-folder"></i>R&amp;D', displayed: 'R&D',
    matches: ['R&D', 'r&d'], misses: ['amp', 'fa-folder'] },
  { html: '<i class="fas fa-folder"></i>Environnements &quot;test&quot; &lt;démo&gt;',
    displayed: 'Environnements "test" <démo>', matches: ['"test"', '<démo>', 'DÉMO'],
    misses: ['quot', '&lt;', 'fa-folder'] },
  { html: '<i class="fas fa-times"></i>Sécurité', displayed: 'Sécurité',
    matches: ['SÉCURITÉ'], misses: ['fa-times'] },
  { html: '&amp;lt;literal&amp;gt;', displayed: '&lt;literal&gt;',
    matches: ['&lt;literal&gt;'], misses: ['<literal>'] }
]

for (const label of labels) {
  test(`Items tree searches the displayed name: ${label.displayed}`, () => {
    const callback = vm.runInNewContext(`(${search[1]})`, {
      htmlDecode(html) {
        assert.equal(html, label.html)
        return label.displayed
      }
    })
    const node = { text: label.html }
    for (const term of label.matches) assert.equal(callback(term, node), true, term)
    for (const term of label.misses) assert.equal(callback(term, node), false, term)
  })
}

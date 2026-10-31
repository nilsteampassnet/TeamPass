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

test('Log facets pass plain displayed text to the default Select2 escaping without changing option identity', () => {
  const source = readFileSync(require.resolve('../../app/pages/utilities.logs.js.php'), 'utf8').replace(/\r\n/g, '\n')
  const start = source.indexOf('    const facetSelect2 =')
  const end = source.indexOf("    facetSelect2('#logs-user'", start)
  assert.ok(start >= 0 && end > start)
  let configuration
  const decoded = new Map([
    ['R&amp;D O&apos;Brien', "R&D O'Brien"],
    ['&lt;img src=x onerror=alert(1)&gt;', '<img src=x onerror=alert(1)>']
  ])
  const context = vm.createContext({
    $: () => ({ select2(options) { configuration = options } }),
    logsDataUrl: '/logs',
    decodeHtmlEntities: value => decoded.get(value)
  })
  vm.runInContext(source.slice(start, end) + '\nfacetSelect2("#test", "folder_options", "Folders")', context)
  const options = Array.from(decoded.keys(), (text, index) => ({ id: index + 7, text }))
  const result = configuration.ajax.processResults({ results: options, pagination: { more: true } }, {})
  assert.deepEqual(Array.from(result.results, option => option.id), [7, 8])
  assert.deepEqual(Array.from(result.results, option => option.text), Array.from(decoded.values()))
  assert.equal(result.pagination.more, true)
  assert.equal(configuration.escapeMarkup, undefined, 'Keep the default text escaping')
  assert.deepEqual(options.map(option => option.text), Array.from(decoded.keys()), 'Do not mutate the transport payload')
})

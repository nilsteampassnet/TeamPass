/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net.
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const { test } = require('node:test')
const vm = require('node:vm')

const source = readFileSync(join(__dirname, '../../app/pages/items.js.php'), 'utf8').replace(/\r\n/g, '\n')
const start = source.indexOf('function updateFolderTreeCounter(')
const end = source.indexOf('\n    /** List the items', start)
assert.ok(start >= 0 && end > start, 'Missing folder-counter updater')
const updater = source.slice(start, end)

/** Run the shipped updater with a small tree/badge adapter. */
function view({ enabled = true, badge = '2/5/1', hasNode = true, legacy = false } = {}) {
  const state = { badge, refreshes: 0, removals: 0, header: null }
  const tree = {
    get_node(id) {
      assert.equal(id, 'li_7')
      return hasNode ? { data: legacy ? undefined : { tree_counters: enabled } } : false
    },
    refresh() { state.refreshes++ }
  }
  const $ = selector => {
    if (selector === '#jstree') return { jstree: () => tree }
    if (selector === '#jstree .items_count') return { remove() { state.badge = null; state.removals++ } }
    if (selector === '#count-items-badge') return { text(value) { state.header = value } }
    assert.equal(selector, '#itcount_7', 'A folder click must never append a badge to the folder link')
    return { length: state.badge === null ? 0 : 1, text(value) {
      if (value === undefined) return state.badge
      state.badge = String(value)
    } }
  }
  const context = { $ }
  vm.createContext(context)
  vm.runInContext(updater, context)
  return { state, run(counter, option) { context.updateFolderTreeCounter(7, counter, option) }, context }
}

test('Disabled counters are removed and are not recreated by a folder click', () => {
  for (const badge of [null, '2/5/1', '2']) {
    const ui = view({ enabled: false, badge })
    ui.run(4, 0)
    ui.run(4, 0)
    assert.equal(ui.state.badge, null)
    assert.equal(ui.state.refreshes, 0)
  }
})

test('Enabled counters retain the two subfolder counts when the direct count changes', () => {
  const ui = view()
  ui.run(4, 1)
  assert.equal(ui.state.badge, '4/5/1')
  ui.run(0, 1)
  assert.equal(ui.state.badge, '0/5/1')
  assert.equal(ui.state.refreshes, 0)
})

test('Individual-item access retains its single counter', () => {
  const ui = view({ badge: '2' })
  ui.run(3, 1)
  assert.equal(ui.state.badge, '3')
})

test('A missing badge is left to the authorized tree renderer', () => {
  const ui = view({ badge: null })
  ui.run(4, 1)
  assert.equal(ui.state.badge, null)
  assert.equal(ui.state.refreshes, 0)
})

test('An open page reloads its tree after either admin setting change', () => {
  for (const enabled of [false, true]) {
    const ui = view({ enabled, badge: enabled ? '2/5/1' : null })
    ui.run(4, enabled ? 0 : 1)
    assert.equal(ui.state.refreshes, 1)
    if (enabled) assert.equal(ui.state.badge, null)
  }
})

test('A legacy open tree is refreshed and a missing node does not cause a refresh loop', () => {
  const legacy = view({ legacy: true })
  legacy.run(4, 1)
  assert.equal(legacy.state.refreshes, 1)
  const missing = view({ hasNode: false, badge: null })
  missing.run(4, 1)
  assert.equal(missing.state.refreshes, 0)
})

test('Invalid counts cannot inject markup or replace a badge', () => {
  for (const value of ['<img src=x>', -1, NaN, undefined, 2.5]) {
    const ui = view()
    ui.run(value, 1)
    assert.equal(ui.state.badge, '2/5/1')
  }
})

// Execute both actual folder-load branches, including their independent list header count.
const blocks = [...source.matchAll(/if \(data\.counter_full !== undefined\) \{\n\s*\$\('#count-items-badge'\)[\s\S]+?\n\s*\}/g)]
assert.equal(blocks.length, 2, 'Both standard and read-only item loaders must update counters')
blocks.forEach((match, index) => {
  test(`${index === 0 ? 'Read-only' : 'Standard'} folder loads keep the list count when tree counters are disabled`, () => {
    const ui = view({ enabled: false })
    ui.context.data = { counter_full: 4, tree_counters: 0 }
    ui.context.groupe_id = 7
    vm.runInContext(match[0], ui.context)
    assert.equal(ui.state.header, 4)
    assert.equal(ui.state.badge, null)
  })
})

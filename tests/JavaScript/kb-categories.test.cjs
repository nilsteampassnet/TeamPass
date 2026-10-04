/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License v3.0.
 * See <https://www.gnu.org/licenses/>.
 */
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

function browser(entries = []) {
  const context = vm.createContext({})
  vm.runInContext(readFileSync(join(__dirname, '../../public/assets/js/kb-categories.js'), 'utf8'), context)
  const navigation = vm.runInContext('createKbCategoryBrowser()', context)
  navigation.refresh(entries)
  return navigation
}

const article = (id, categoryId, category) => ({ id, category_id: categoryId, category })
const ids = navigation => Array.from(navigation.getEntries(), entry => entry.id)

test('Initial list includes every active article; categories count articles and sort naturally', () => {
  const navigation = browser([article(1, 20, 'Network 10'), article(2, 10, 'Network 2'), article(3, 10, 'Network 2')])
  assert.equal(navigation.getState().view, 'list')
  assert.equal(navigation.getState().selected, null)
  assert.deepEqual(ids(navigation), [1, 2, 3])
  assert.deepEqual(Array.from(navigation.getCategories(), category => [category.id, category.count]), [[10, 2], [20, 1]])
})

test('Category selection uses identity even for identical labels and labels with regex characters', () => {
  const navigation = browser([article(1, 1, 'VPN'), article(2, 2, 'VPN clients'), article(3, 3, 'VPN'), article(4, 4, 'C++ [prod].*')])
  assert.equal(navigation.select('1'), true)
  assert.deepEqual(ids(navigation), [1])
  navigation.select(4)
  assert.deepEqual(ids(navigation), [4])
  assert.equal(navigation.select('missing'), false)
  assert.deepEqual(ids(navigation), [4])
})

test('Returning to the overview or list clears the category selection', () => {
  const navigation = browser([article(1, 1, 'VPN'), article(2, 2, 'Network')])
  navigation.select(1)
  navigation.setView('categories')
  assert.equal(navigation.getState().selected, null)
  navigation.select(2)
  navigation.setView('list')
  assert.equal(navigation.getState().view, 'list')
  assert.deepEqual(ids(navigation), [1, 2])
})

test('Refresh preserves the selected category, updates its label and counts, and includes new articles', () => {
  const navigation = browser([article(1, 1, 'VPN')])
  navigation.select(1)
  navigation.refresh([article(1, 1, 'Connections'), article(2, 1, 'Connections'), article(3, 2, 'Servers')])
  assert.equal(navigation.getState().selected.label, 'Connections')
  assert.equal(navigation.getState().selected.count, 2)
  assert.deepEqual(ids(navigation), [1, 2])
})

test('Moving or deleting the last article returns to the overview and removes the empty category', () => {
  const navigation = browser([article(1, 1, 'VPN'), article(2, 2, 'Network')])
  navigation.select(1)
  navigation.refresh([article(1, 2, 'Network'), article(2, 2, 'Network')])
  assert.equal(navigation.getState().view, 'categories')
  assert.equal(navigation.getState().selected, null)
  assert.deepEqual(Array.from(navigation.getCategories(), category => category.id), [2])
  navigation.select(2)
  navigation.refresh([])
  assert.equal(navigation.getState().selected, null)
  assert.equal(navigation.getCategories().length, 0)
})

test('Missing, orphaned, and invalid legacy categories are grouped into a selectable fallback', () => {
  const navigation = browser([article(1, 0, ''), article(2, null, ''), article(3, 5, ' '), article(4, -1, 'Legacy'), article(5, 8, 'VPN')])
  assert.equal(navigation.select(0), true)
  assert.equal(navigation.getState().selected.count, 4)
  assert.deepEqual(ids(navigation), [1, 2, 3, 4])
  navigation.refresh([article(5, 8, 'VPN')])
  assert.equal(navigation.getState().selected, null)
})

test('Labels remain literal text, including markup, quotes, and object property names', () => {
  const labels = ['__proto__', 'constructor', '<img src=x onerror=alert(1)>', 'R&D "Ops"']
  const navigation = browser(labels.map((label, index) => article(index + 1, index + 1, label)))
  assert.equal(navigation.getCategories().length, 4)
  labels.forEach((label, index) => {
    navigation.select(index + 1)
    assert.equal(navigation.getState().selected.label, label)
    assert.deepEqual(ids(navigation), [index + 1])
  })
})

test('Empty or malformed responses have no populated categories and keep the current view', () => {
  const navigation = browser()
  navigation.setView('categories')
  navigation.refresh(null)
  assert.equal(navigation.getState().view, 'categories')
  assert.deepEqual(ids(navigation), [])
  assert.equal(navigation.select(0), false)
})

/** Execute the production list loader with controlled transport and the real category model. */
function loader(directId = 0) {
  const requests = []
  const rendered = []
  const errors = []
  const opened = []
  const navigation = browser()
  const context = vm.createContext({
    $: { post(url, payload, success) {
      const request = { success }
      requests.push(request)
      return { fail(callback) { request.fail = callback } }
    } },
    kbCategoryBrowser: navigation,
    kbRenderBrowser: reset => rendered.push(reset),
    kbSessionKey: 'test-session',
    kbTranslations: { server_answer_error: 'Transport error' },
    kbEncodePayload: value => value,
    kbDecodeResponse: value => value,
    kbToastError: message => errors.push(message),
    kbOpenViewer: id => opened.push(id),
    kbDirectId: directId
  })
  vm.runInContext('let kbListRequestId = 0; let kbLoadedDirectId = false;', context)
  const source = readFileSync(join(__dirname, '../../app/pages/kb.js.php'), 'utf8')
  const start = source.indexOf('    function loadKbList()')
  const end = source.indexOf('    $(document).ready', start)
  assert.ok(start > 0 && end > start)
  vm.runInContext(source.slice(start, end), context)
  return { requests, rendered, errors, opened, navigation, load: () => vm.runInContext('loadKbList()', context) }
}

test('A delayed list response or transport error cannot overwrite a newer category refresh', () => {
  const page = loader()
  page.load()
  page.load()
  page.requests[1].success({ error: false, entries: [article(2, 2, 'Network')] })
  page.requests[0].success({ error: false, entries: [article(1, 1, 'Old category')] })
  page.requests[0].fail()
  assert.deepEqual(ids(page.navigation), [2])
  assert.deepEqual(page.rendered, [false])
  assert.deepEqual(page.errors, [])
})

test('A failed current request reports its error and preserves the existing category selection', () => {
  const page = loader()
  page.navigation.refresh([article(1, 1, 'VPN')])
  page.navigation.select(1)
  page.load()
  page.requests[0].success({ error: true, message: 'Denied' })
  assert.deepEqual(ids(page.navigation), [1])
  assert.equal(page.navigation.getState().selected.id, 1)
  assert.deepEqual(page.rendered, [])
  assert.deepEqual(page.errors, ['Denied'])
  page.load()
  page.requests[1].fail()
  assert.deepEqual(page.errors, ['Denied', 'Transport error'])
})

test('A category chosen during a list request remains selected when that request completes', () => {
  const page = loader()
  page.navigation.refresh([article(1, 1, 'VPN'), article(2, 2, 'Network')])
  page.load()
  page.navigation.select(2)
  page.requests[0].success({ error: false, entries: [article(1, 1, 'VPN'), article(2, 2, 'Network'), article(3, 2, 'Network')] })
  assert.equal(page.navigation.getState().selected.id, 2)
  assert.deepEqual(ids(page.navigation), [2, 3])
})

test('Direct article links still open their viewer after loading the list', () => {
  const page = loader(42)
  page.load()
  page.requests[0].success({ error: false, entries: [article(42, 1, 'VPN')] })
  assert.deepEqual(page.opened, [42])
  assert.equal(page.navigation.getState().view, 'list')
})

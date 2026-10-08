/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { test } = require('node:test')
const vm = require('node:vm')
const FolderTree = require('../../public/assets/js/folders-tree.js')
const source = readFileSync(require.resolve('../../app/pages/folders.js.php'), 'utf8').replace(/\r\n/g, '\n')

function section(start, end) {
  const first = source.indexOf(start)
  const last = source.indexOf(end, first + start.length)
  assert.ok(first >= 0 && last > first)
  return source.slice(first, last)
    .replace(/\?>(?:\r?\n)/g, '?>')
    .replace(/<\?php echo json_encode\([\s\S]*?\?>/g, '"Translated"')
    .replace(/<\?php echo[\s\S]*?\?>/g, 'fixture')
    .replace(/<\?php[\s\S]*?\?>/g, '')
}

function view() {
  const tree = new FolderTree()
  const rows = Array.from({ length: 3161 }, (_, i) => ({ id: i + 1, parentId: 0, parents: [],
    path: [], title: `Folder ${i + 1}`, level: 1, folderComplexity: { value: 60, text: 'Strong', class: 'strong' },
    icon: '', iconSelected: '', renewalPeriod: 0, add_is_blocked: 0, edit_is_blocked: 0, deletionProtected: 0, nbItems: 0 }))
  tree.replace(rows)
  const stats = { widgets: 0, tips: 0, moves: 0 }
  const filters = { '#folders-depth': 'all', '#folders-complexity': 'all', '#folders-search': '' }
  const body = { children: [], get firstChild() { return this.children[0] || null }, insertBefore(node, before) {
    if (node.parent) node.parent.children.splice(node.parent.children.indexOf(node), 1)
    this.children.splice(before ? this.children.indexOf(before) : this.children.length, 0, node)
    node.parent = this
    stats.moves++
  } }
  function wrap(items) {
    const value = { length: items.length,
      each(fn) { items.slice().forEach((item, i) => fn.call(item, i, item)); return this },
      children() { return wrap(body.children) },
      find(selector) {
        return wrap(items.flatMap(node => selector === 'input.checkbox-folder'
          ? (node === body ? node.children.map(child => child.checkbox) : [node.checkbox]) : [{ tip: true }]))
      },
      tooltip(action) { if (action !== 'dispose') stats.tips += items.length; return this },
      iCheck(action) {
        if (typeof action === 'object') stats.widgets += items.length
        else items.forEach(input => { input.checked = action === 'check' })
        return this
      },
      remove() { items.forEach(node => { if (node.parent) node.parent.children.splice(node.parent.children.indexOf(node), 1) }); return this },
      prop() { return this }, text() { return this }
    }
    items.forEach((item, i) => { value[i] = item })
    return value
  }
  function $(target) {
    if (Array.isArray(target)) return wrap(target)
    if (typeof target !== 'string') return wrap([target])
    if (target === '#table-folders > tbody') return wrap([body])
    if (target.startsWith('<tr')) {
      const id = target.match(/data-id="(\d+)"/)[1]
      return wrap([{ dataset: { id }, checkbox: { dataset: { id }, checked: false },
        get nextSibling() { return this.parent ? this.parent.children[this.parent.children.indexOf(this) + 1] || null : null } }])
    }
    return { val: () => filters[target], prop() {}, text() {} }
  }
  const context = vm.createContext({ $, _folderTree: tree, _visibleLimit: 100,
    _userIsAdmin: 1, _userCanCreateRootFolder: 1, _syncingFolderSelection: false,
    _renderedFolderMarkup: new Map(), htmlEncode: text => String(text).replace(/</g, '&lt;').replace(/"/g, '&quot;')
  })
  vm.runInContext(section('    function renderFolderView()', "    $('#folders-show-more').on"), context)
  vm.runInContext(section('    function buildFolderRowHtml', '    /**'), context)
  return { context, tree, body, stats, filters, render() { context.renderFolderView() } }
}

test('3161 root folders render 100 rows and initialize widgets only for newly displayed rows', () => {
  const ui = view()
  ui.render()
  assert.equal(ui.body.children.length, 100)
  assert.equal(ui.stats.widgets, 100)
  assert.equal(ui.stats.moves, 100)
  const first = ui.body.children[0]
  ui.render()
  assert.equal(ui.body.children[0], first)
  assert.equal(ui.stats.widgets, 100)
  assert.equal(ui.stats.moves, 100)
  ui.context._visibleLimit = 200
  ui.render()
  assert.equal(ui.body.children.length, 200)
  assert.equal(ui.stats.widgets, 200)
})

test('Search reaches rows outside the DOM, and selection updates without rebuilding widgets', () => {
  const ui = view()
  ui.render()
  ui.tree.selectBranch(1, true)
  ui.render()
  assert.equal(ui.body.children[0].checkbox.checked, true)
  assert.equal(ui.stats.widgets, 100)
  ui.filters['#folders-search'] = 'Folder 3161'
  ui.render()
  assert.equal(ui.body.children.length, 1)
  assert.equal(ui.body.children[0].dataset.id, '3161')
  assert.deepEqual(ui.tree.selectedRows().map(row => row.id), [1])
  assert.equal(ui.stats.widgets, 101)
})

test('Editing one row rebuilds its widgets while retaining unrelated rows', () => {
  const ui = view()
  ui.render()
  const first = ui.body.children[0]
  const second = ui.body.children[1]
  ui.tree.upsert({ ...ui.tree.byId.get(1), title: 'Updated title' })
  ui.render()
  assert.notEqual(ui.body.children[0], first)
  assert.equal(ui.body.children[1], second)
  assert.equal(ui.stats.widgets, 101)
})

test('A root branch remains expandable when its deletion checkbox is unavailable', () => {
  const ui = view()
  const row = { ...ui.tree.byId.get(1), numOfChildren: 5 }
  const markup = ui.context.buildFolderRowHtml(row, 0, 0)
  assert.ok(markup.includes('icon-collapse'))
  assert.ok(!markup.includes('checkbox-folder'))
})

test('Typing, paste and clearing coalesce into one delayed search', () => {
  let handler
  let next = 0
  let renders = 0
  const queued = new Map()
  const context = { _folderSearchTimer: null,
    $: () => ({ on(event, fn) { assert.equal(event, 'input'); handler = fn } }),
    clearTimeout(id) { queued.delete(id) },
    setTimeout(fn, delay) { assert.equal(delay, 200); queued.set(++next, fn); return next },
    applyFilters() { renders++ }
  }
  vm.runInNewContext(section("    $('#folders-search').on", '    /**'), context)
  handler(); handler(); handler()
  assert.equal(queued.size, 1)
  assert.equal(renders, 0)
  queued.values().next().value()
  assert.equal(renders, 1)
})

test('A superseded load cannot replace the latest view or clear its loading state', () => {
  const requests = []
  let decoded = 0
  const $ = () => ({ show() { return this }, hide() { return this }, text() { return this } })
  $.post = () => {
    const request = { done(fn) { this.onDone = fn; return this }, fail(fn) { this.onFail = fn; return this },
      always(fn) { this.onAlways = fn; return this }, abort() { this.onFail({}, 'abort'); this.onAlways() } }
    requests.push(request)
    return request
  }
  const context = vm.createContext({ $, _buildGeneration: 0, _foldersRequest: null, _foldersLoading: false,
    toastr: { error() {} }, prepareExchangedData() { decoded++; return { error: true } } })
  vm.runInContext(section('    function buildTable()', '    /** Render a bounded view'), context)
  context.buildTable()
  context.buildTable()
  requests[0].onDone('old snapshot')
  requests[0].onAlways()
  assert.equal(decoded, 0)
  assert.equal(context._foldersLoading, true)
  requests[1].onDone('new failure')
  requests[1].onAlways()
  assert.equal(decoded, 1)
  assert.equal(context._foldersLoading, false)
})

test('Parent searches are paginated, initialized once, and ignore a response for a previous edited folder', () => {
  const responses = []
  const requests = []
  let options
  let initialized = false
  let edited = 2
  let decoded = 0
  const select = {
    hasClass() { return initialized },
    select2(value) { options = value; initialized = true }
  }
  const $ = { post(url, data) {
    const request = { data, done(fn) { this.respond = fn; return this }, fail() { return this } }
    requests.push(request)
    return request
  } }
  const context = vm.createContext({ $, _parentMetadata: new Map(),
    prepareExchangedData(response) { decoded++; return response } })
  vm.runInContext(section('    function initializeParentPicker', "    $('#modal-folder-new').on"), context)
  context.initializeParentPicker(select, {}, () => edited)
  context.initializeParentPicker(select, {}, () => edited)
  assert.equal(options.ajax.delay, 250)
  options.ajax.transport({ data: { term: 'Production', page: 2 } }, result => responses.push(result), () => assert.fail('unexpected failure'))
  assert.equal(requests[0].data.term, 'Production')
  assert.equal(requests[0].data.page, 2)
  assert.equal(requests[0].data.exclude_id, 2)
  edited = 3
  requests[0].respond({ error: false, results: [{ id: '1' }], pagination: { more: false } })
  assert.equal(decoded, 0)
  options.ajax.transport({ data: { term: 'Production', page: 1 } }, result => responses.push(result), () => assert.fail('unexpected failure'))
  requests[1].respond({ error: false, results: [{ id: '4', complexity: 60 }], pagination: { more: true } })
  assert.equal(responses.length, 1)
  assert.equal(context._parentMetadata.get(4).complexity, 60)
  assert.equal(options.ajax.processResults(responses[0]).pagination.more, true)
})

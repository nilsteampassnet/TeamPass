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
const source = readFileSync(require.resolve('../../app/pages/roles.js.php'), 'utf8').replace(/\r\n/g, '\n')

function section(start, end) {
  const first = source.indexOf(start)
  const last = source.indexOf(end, first + start.length)
  assert.ok(first >= 0 && last > first, start)
  return source.slice(first, last)
    .replace(/\?>(?:\r?\n)/g, '?>')
    .replace(/<\?php echo json_encode\([\s\S]*?\?>/g, '"Translated"')
    .replace(/<\?php echo[\s\S]*?\?>/g, 'fixture')
    .replace(/<\?php[\s\S]*?\?>/g, '')
}

function nodes(count = 3161) {
  return Array.from({ length: count }, (_, i) => ({ id: i + 1, parentId: 0, parents: [],
    path: [], title: `Folder ${i + 1}`, level: 1, ident: 1, access: 'W' }))
}

const encode = text => String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;').replace(/'/g, '&#039;')

function view(rows = nodes()) {
  const tree = new FolderTree()
  tree.replace(rows)
  const stats = { widgets: 0, tips: 0, moves: 0 }
  const filters = { '#folders-depth': 'all', '#folders-search': '', '#folders-compare': '' }
  const all = { checked: false }
  const controls = {}
  const handlers = {}
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
      find(selector) { return wrap(items.flatMap(node => selector === 'input.folder-select'
        ? (node === body ? node.children.map(child => child.checkbox) : [node.checkbox]) : [{ tip: true }])) },
      tooltip(action) { if (action !== 'dispose') stats.tips += items.length; return this },
      toggleClass() { return this },
      iCheck(action) {
        if (typeof action === 'object') stats.widgets += items.length
        else items.forEach(input => { input.checked = action === 'check' })
        return this
      },
      is() { return items[0].checked },
      data(key) { return items[0].dataset[key] },
      remove() { items.forEach(node => { if (node.parent) node.parent.children.splice(node.parent.children.indexOf(node), 1) }); return this }
    }
    items.forEach((item, i) => { value[i] = item })
    return value
  }
  const document = {}
  function $(target) {
    if (target === document) return { on(event, selector, fn) { handlers[selector] = fn } }
    if (Array.isArray(target)) return wrap(target)
    if (typeof target !== 'string') return wrap([target])
    if (target === '#table-role-details > tbody') return wrap([body])
    if (target === '#cb-all-selection') return wrap([all])
    if (target.startsWith('<tr')) {
      const id = target.match(/data-id="(\d+)"/)[1]
      return wrap([{ dataset: { id }, markup: target, checkbox: { dataset: { id }, checked: false },
        get nextSibling() { return this.parent ? this.parent.children[this.parent.children.indexOf(this) + 1] || null : null } }])
    }
    return { val: () => filters[target],
      prop(key, value) { controls[target] = { ...controls[target], [key]: value }; return this },
      text(value) { controls[target] = { ...controls[target], text: value }; return this },
      on(event, fn) { handlers[target] = fn } }
  }
  const context = vm.createContext({ $, document, _roleTree: tree, _visibleLimit: 100,
    _matrixLoading: false, _syncingRoleSelection: false, _sidebarFolderId: '',
    _renderedRoleMarkup: new Map(), _compareAccess: new Map(),
    _roleLabels: { add_allowed: 'Add', edit_allowed: 'Edit', delete_allowed: 'Delete',
      edit_not_allowed: 'No edit', delete_not_allowed: 'No delete', read_only: 'Read', no_access: 'None', collapse: 'Collapse' },
    htmlEncode: encode
  })
  vm.runInContext(section('    function buildMatrixAccessHtml', '    /** Render one folder'), context)
  vm.runInContext(section('    function buildMatrixRowHtml', '    /**'), context)
  vm.runInContext(section('    function renderRoleView()', '    /** Keep the complete'), context)
  vm.runInContext(section("    $(document).on('ifChecked ifUnchecked'", '    /**'), context)
  return { context, tree, body, stats, filters, controls, all, handlers, render() { context.renderRoleView() } }
}

test('3161 roots render 100 rows; unchanged rows keep their widgets and show-more adds 100', () => {
  const ui = view()
  ui.render()
  assert.equal(ui.body.children.length, 100)
  assert.equal(ui.stats.widgets, 100)
  const first = ui.body.children[0]
  ui.render()
  assert.equal(ui.body.children[0], first)
  assert.equal(ui.stats.widgets, 100)
  assert.equal(ui.stats.moves, 100)
  ui.handlers['#roles-show-more']()
  assert.equal(ui.body.children.length, 200)
  assert.equal(ui.stats.widgets, 200)
  assert.equal(ui.controls['#roles-show-more'].hidden, false)
})

test('Large branches start collapsed; combined decoded full-path search reaches hidden descendants', () => {
  const rows = nodes()
  rows[0].title = 'R&D'
  for (let i = 1; i < rows.length; i++) {
    rows[i] = { ...rows[i], parentId: 1, parents: [1], path: ['R&D'], level: 2, ident: 2 }
  }
  rows[3160].title = 'O\'Brien "Lab" <Test>'
  const ui = view(rows)
  ui.render()
  assert.equal(ui.body.children.length, 1)
  assert.ok(ui.body.children[0].markup.includes('role-collapse'))
  ui.filters['#folders-search'] = 'r&d / o\'brien "lab" <test>'
  ui.render()
  assert.equal(ui.body.children.length, 1)
  assert.equal(ui.body.children[0].dataset.id, '3161')
  assert.ok(ui.body.children[0].markup.includes('O&#039;Brien &quot;Lab&quot; &lt;Test&gt;'))
  assert.ok(!ui.body.children[0].markup.includes('role-collapse'))
  ui.filters['#folders-depth'] = '1'
  ui.render()
  assert.equal(ui.body.children.length, 0)
  ui.filters['#folders-depth'] = 'all'
  for (const term of ['amp', 'quot', '#039', '&lt;']) {
    ui.filters['#folders-search'] = term
    ui.render()
    assert.equal(ui.body.children.length, 0)
  }
})

test('Model selections include unrendered descendants; child uncheck clears selected ancestors', () => {
  const rows = nodes()
  rows.slice(1).forEach(row => { row.parentId = 1; row.parents = [1]; row.path = ['Folder 1']; row.level = 2; row.ident = 2 })
  const ui = view(rows)
  ui.render()
  ui.handlers['.folder-select'].call(ui.body.children[0].checkbox, { type: 'ifChecked' })
  assert.equal(ui.tree.selected.size, 3161)
  assert.equal(ui.all.checked, true)
  ui.filters['#folders-search'] = 'Folder 3161'
  ui.render()
  assert.equal(ui.body.children.length, 1)
  assert.equal(ui.body.children[0].checkbox.checked, true)
  ui.handlers['.folder-select'].call(ui.body.children[0].checkbox, { type: 'ifUnchecked' })
  assert.equal(ui.tree.selected.size, 3159)
  assert.equal(ui.tree.selected.has(1), false)
  assert.equal(ui.all.checked, false)
  ui.handlers['.folder-select'].call({ id: 'cb-all-selection' }, { type: 'ifChecked' })
  assert.equal(ui.tree.selected.size, 3161)
})

test('Comparison covers rows added later and clearing it restores the primary column', () => {
  const ui = view()
  ui.filters['#folders-compare'] = '7'
  ui.context._compareAccess.set(3161, 'NDNE')
  ui.render()
  ui.filters['#folders-search'] = 'Folder 3161'
  ui.render()
  const markup = ui.body.children[0].markup
  assert.ok(markup.includes('class="compare '))
  assert.ok(markup.includes('title="No edit"'))
  assert.ok(markup.includes('title="No delete"'))
  ui.filters['#folders-compare'] = ''
  ui.context._compareAccess.clear()
  ui.render()
  assert.ok(ui.body.children[0].markup.includes('class="hidden compare '))
  assert.ok(!ui.body.children[0].markup.includes('title="No delete"'))
})

test('Permission badges preserve all six access states and escape translated attributes', () => {
  const ui = view(nodes(2))
  for (const type of ['W', 'R', 'ND', 'NE', 'NDNE', 'none']) {
    const markup = ui.context.buildMatrixAccessHtml(type)
    assert.equal(markup.includes('title="No edit"'), type === 'NE' || type === 'NDNE')
    assert.equal(markup.includes('title="No delete"'), type === 'ND' || type === 'NDNE')
    assert.equal(markup.includes('title="Read"'), type === 'R')
    assert.equal(markup.includes('title="None"'), type === 'none')
  }
  ui.context._roleLabels.no_access = '\" onmouseover=\"alert(1)'
  assert.ok(ui.context.buildMatrixAccessHtml('none').includes('&quot;'))
  assert.ok(!ui.context.buildMatrixAccessHtml('none').includes('title="" onmouseover='))
})

test('The matrix decoder preserves encoded special characters and markup as escaped plain labels', () => {
  const helpers = readFileSync(require.resolve('../../app/includes/js/functions.js'), 'utf8')
  const first = helpers.indexOf('const storageEntities =')
  const last = helpers.indexOf('/**', helpers.indexOf('function decodeStorageEntities', first))
  let purifyArgument
  let encrypted = 0
  const context = vm.createContext({
    $: () => ({ val: () => encrypted }),
    Encryption: class { decrypt(text) { return text } },
    safeParseJSONMaybe(text) { return { ok: true, value: JSON.parse(text) } },
    purifyData() { assert.fail('Plain matrix labels must not pass through markup stripping') },
    purifyServerData(text) { return text }
  })
  vm.runInContext(helpers.slice(helpers.indexOf('function prepareExchangedData('), helpers.indexOf('function isJsonString(')), context)
  const prepare = context.prepareExchangedData
  context.prepareExchangedData = (response, mode, key, file, fn, purify) => {
    purifyArgument = purify
    return prepare(JSON.stringify(response), mode, key, file, fn, purify)
  }
  vm.runInContext(helpers.slice(first, last), context)
  vm.runInContext(section('    function decodeRoleMatrixResponse', '    /** Load the authorized snapshot'), context)
  const response = { error: false, matrix: [{ ...nodes(1)[0], title: 'O&#039;Brien &quot;Lab&quot; &lt;Test&gt;',
    path: ['R&amp;D'], access: '\" onclick=\"alert(1)' }] }
  const data = context.decodeRoleMatrixResponse(response)
  assert.equal(purifyArgument, false)
  assert.equal(data.matrix[0].title, 'O\'Brien "Lab" <Test>')
  assert.equal(data.matrix[0].path[0], 'R&D')
  assert.equal(data.matrix[0].access, 'none')
  encrypted = 1
  assert.equal(context.decodeRoleMatrixResponse(response).matrix[0].title, 'O\'Brien "Lab" <Test>')
  const ui = view(data.matrix)
  ui.render()
  assert.ok(ui.body.children[0].markup.includes('&lt;Test&gt;'))
  assert.ok(ui.body.children[0].markup.includes('R&amp;D'))
  const malicious = context.decodeRoleMatrixResponse({ error: false, matrix: [{ ...nodes(1)[0],
    title: '&lt;img src=x onerror=alert(1)&gt;' }] })
  assert.equal(malicious.matrix[0].title, '<img src=x onerror=alert(1)>')
  assert.ok(ui.context.buildMatrixRowHtml(malicious.matrix[0]).includes('&lt;img src=x'))
  assert.ok(!ui.context.buildMatrixRowHtml(malicious.matrix[0]).includes('<img src=x'))
})

test('Depth never expands a collapsed branch; small trees start expanded', () => {
  const rows = [{ ...nodes(1)[0] }, { ...nodes(2)[1], parentId: 1, parents: [1], path: ['Folder 1'], level: 2, ident: 2 }]
  const ui = view(rows)
  ui.render()
  assert.equal(ui.body.children.length, 2)
  ui.handlers['.role-collapse'].call({ dataset: { id: 1 } }, { stopPropagation() {} })
  assert.equal(ui.body.children.length, 1)
  ui.filters['#folders-depth'] = '2'
  ui.render()
  assert.equal(ui.body.children.length, 1)
})

function requestsContext() {
  const requests = []
  let decoded = 0
  const filters = { '#folders-compare': '7' }
  const options = []
  const $ = target => ({ show() { return this }, hide() { return this }, text() { return this },
    css() { return this }, attr() { return this }, html() { return this }, tooltip() { return this },
    empty() { options.length = 0; return this }, append(option) { options.push(option); return this },
    removeClass() { return this }, val(value) { if (value !== undefined) { filters[target] = value; return this }; return filters[target] } })
  $.post = (url, data) => {
    const request = { data, done(fn) { this.onDone = fn; return this }, fail(fn) { this.onFail = fn; return this },
      always(fn) { this.onAlways = fn; return this }, abort() { this.onFail({}, 'abort'); this.onAlways() } }
    requests.push(request)
    return request
  }
  const context = vm.createContext({ $, _matrixGeneration: 0, _matrixRequest: null, _matrixLoading: false,
    _matrixRoleId: '', _roleTree: new FolderTree(), TeampassFolderTree: FolderTree,
    _compareGeneration: 0, _compareRequest: null, _compareAccess: new Map(), _renderedRoleMarkup: new Map(),
    _sidebarFolderId: '', closeRightsSidebar() {}, renderRoleView() {}, toastr: { error() {} },
    Option: class { constructor(text, value) { this.text = text; this.value = value } },
    store: { get() { return { rolesDepthFilter: '2' } } },
    decodeRoleMatrixResponse(response) { decoded++; return response } })
  vm.runInContext(section('    function refreshMatrix(', '    /** Render at most'), context)
  vm.runInContext(section('    function refreshRoleComparison()', '</script>'), context)
  return { context, requests, filters, options, get decoded() { return decoded } }
}

test('Successful loads include the deepest level, retain same-role selections and reset on role switch', () => {
  const ui = requestsContext()
  ui.filters['#folders-compare'] = ''
  const rows = nodes(2)
  rows[1] = { ...rows[1], parentId: 1, parents: [1], path: ['Folder 1'], level: 2, ident: 2 }
  ui.context.refreshMatrix(7)
  ui.requests[0].onDone({ error: false, matrix: rows })
  ui.requests[0].onAlways()
  assert.deepEqual(ui.options.map(option => option.value), ['all', '1', '2'])
  assert.equal(ui.filters['#folders-depth'], '2')
  ui.context._roleTree.selectBranch(1, true)
  ui.context._roleTree.toggle(1)
  ui.context.refreshMatrix(7)
  ui.requests[1].onDone({ error: false, matrix: rows })
  ui.requests[1].onAlways()
  assert.equal(ui.context._roleTree.selected.size, 2)
  assert.equal(ui.context._roleTree.expanded.has(1), false)
  ui.context.refreshMatrix(8)
  assert.equal(ui.context._roleTree.selected.size, 0)
})

test('Same-role refresh keeps the show-more limit; changing roles or filters resets it', () => {
  const ui = requestsContext()
  ui.filters['#folders-compare'] = ''
  ui.context.refreshMatrix(7)
  ui.requests[0].onDone({ error: false, matrix: nodes() })
  ui.requests[0].onAlways()
  const rendered = view(ui.context._roleTree.rows)
  ui.context.renderRoleView = () => {
    rendered.context._visibleLimit = ui.context._visibleLimit
    rendered.render()
  }
  rendered.handlers['#roles-show-more']()
  ui.context._visibleLimit = rendered.context._visibleLimit
  ui.context.refreshMatrix(7)
  assert.equal(ui.context._visibleLimit, 200)
  ui.requests[1].onDone({ error: false, matrix: nodes() })
  ui.requests[1].onAlways()
  assert.equal(rendered.body.children.length, 200)
  assert.equal(rendered.body.children[149].dataset.id, '150')
  ui.context.refreshMatrix(8)
  assert.equal(ui.context._visibleLimit, 100)
  ui.requests[2].onDone({ error: false, matrix: nodes() })
  ui.requests[2].onAlways()
  assert.equal(rendered.body.children.length, 100)
  ui.context._visibleLimit = 300
  vm.runInContext(section('    function applyRoleFilters()', "    $('#folders-search').on"), ui.context)
  ui.context.applyRoleFilters()
  assert.equal(ui.context._visibleLimit, 100)
  assert.equal(rendered.body.children.length, 100)
})

test('Superseded matrix responses and role clear never decode or replace the current state', () => {
  const ui = requestsContext()
  ui.context.refreshMatrix(7)
  ui.context.refreshMatrix(8)
  ui.requests[0].onDone({ error: false, matrix: nodes() })
  ui.requests[0].onAlways()
  assert.equal(ui.decoded, 0)
  assert.equal(ui.context._matrixLoading, true)
  ui.requests[1].onDone({ error: true, message: 'fixture' })
  ui.requests[1].onAlways()
  assert.equal(ui.context._matrixLoading, false)
  ui.context.refreshMatrix(7)
  ui.context.cancelMatrixRequests()
  ui.requests[2].onDone({ error: false, matrix: nodes() })
  assert.equal(ui.decoded, 1)
})

test('Stale comparison responses are ignored when selection changes or the matrix refreshes', () => {
  const ui = requestsContext()
  ui.context._matrixRoleId = '7'
  ui.context.refreshRoleComparison()
  ui.filters['#folders-compare'] = '8'
  ui.context.refreshRoleComparison()
  ui.requests[0].onDone({ error: false, matrix: nodes() })
  assert.equal(ui.decoded, 0)
  ui.requests[1].onDone({ error: false, matrix: [{ id: 3161, access: 'R' }] })
  assert.equal(ui.context._compareAccess.get(3161), 'R')
  ui.context.refreshRoleComparison()
  ui.context.cancelMatrixRequests()
  ui.requests[2].onDone({ error: false, matrix: nodes() })
  assert.equal(ui.decoded, 1)
  assert.equal(ui.context._compareAccess.size, 0)
})

test('Input, paste and clear debounce full-path search', () => {
  let handler
  let next = 0
  let renders = 0
  const pending = new Map()
  vm.runInNewContext(section("    $('#folders-search').on('input'", "    $(document).on('change', '#folders-compare'"), {
    _roleSearchTimer: null, $: () => ({ on(event, fn) { assert.equal(event, 'input'); handler = fn } }),
    clearTimeout(id) { pending.delete(id) },
    setTimeout(fn, delay) { assert.equal(delay, 200); pending.set(++next, fn); return next },
    applyRoleFilters() { renders++ }
  })
  handler(); handler(); handler()
  assert.equal(pending.size, 1)
  pending.values().next().value()
  assert.equal(renders, 1)
})

function sidebarSaveContext() {
  let handler
  const submissions = []
  const refreshSelections = []
  const changes = []
  const tree = new FolderTree()
  const rows = nodes()
  rows[1] = { ...rows[1], parentId: 1, parents: [1], path: ['Folder 1'], level: 2, ident: 2 }
  tree.replace(rows)
  tree.expanded.add(1)
  tree.selected = new Set([1, 3161])
  const $ = target => ({ on(event, fn) { handler = fn }, data() { return 'R' }, is() { return false }, val() { return '7' },
    removeClass() { return this }, addClass() { return this }, fadeIn() { return this },
    text(value) { changes.push([target, value]); return this }, iCheck() { return this } })
  $.post = (url, data, callback) => { submissions.push({ data: JSON.parse(data.data), complete: callback }) }
  const context = vm.createContext({ $, _roleTree: tree, _matrixLoading: false, _matrixRoleId: '7',
    _matrixGeneration: 1, _sidebarFolderId: 1, closeRightsSidebar() {},
    prepareExchangedData(data) { return data }, decodeQueryReturn(data) { return data },
    toastr: { remove() {}, info() {}, error() {} }, refreshMatrix() {
      refreshSelections.push([...tree.selected])
      tree.replace(rows)
    } })
  vm.runInContext(section("    $('#sidebar-role-submit').on", '    // Focus label'), context)
  vm.runInContext(section('    function openRightsSidebar(', '    /**'), context)
  return { context, tree, submissions, refreshSelections, changes, submit() { handler() } }
}

test('Successful sidebar saves clear hidden selections before refresh, retain expansion and target the next clicked row', () => {
  const ui = sidebarSaveContext()
  ui.submit()
  assert.deepEqual(ui.submissions[0].data.selectedFolders, [1, 3161])
  assert.equal(ui.tree.selected.size, 2)
  ui.submissions[0].complete({ error: false })
  assert.deepEqual(ui.refreshSelections, [[]])
  assert.equal(ui.tree.selected.size, 0)
  assert.equal(ui.tree.expanded.has(1), true)
  ui.context.openRightsSidebar(2, 'W', 'Folder 2')
  assert.ok(ui.changes.some(([selector, value]) => selector === '#sidebar-role-info' && value === 'Folder 2'))
  ui.submit()
  assert.deepEqual(ui.submissions[1].data.selectedFolders, [2])
})

test('Failed or stale sidebar saves retain the selection and never refresh the matrix', () => {
  for (const result of ['error', 'generation', 'role']) {
    const ui = sidebarSaveContext()
    ui.submit()
    if (result === 'generation') ui.context._matrixGeneration++
    if (result === 'role') ui.context._matrixRoleId = '8'
    ui.submissions[0].complete({ error: result === 'error' })
    assert.deepEqual([...ui.tree.selected], [1, 3161])
    assert.equal(ui.tree.expanded.has(1), true)
    assert.equal(ui.refreshSelections.length, 0)
  }
})

test('A single hidden selection labels the sidebar with the actual target and its permission', () => {
  const tree = new FolderTree()
  const rows = nodes(3161)
  rows[3160].access = 'R'
  tree.replace(rows)
  tree.selected.add(3161)
  const changes = []
  const $ = selector => ({
    removeClass() { return this }, addClass() { return this }, fadeIn() { return this },
    text(value) { changes.push([selector, 'text', value]); return this },
    iCheck(value) { changes.push([selector, 'iCheck', value]); return this }
  })
  const context = vm.createContext({ $, _roleTree: tree, _sidebarFolderId: '' })
  vm.runInContext(section('    function openRightsSidebar(', '    /**'), context)
  context.openRightsSidebar(1, 'W', 'Folder 1')
  assert.ok(changes.some(([selector, method, value]) => selector === '#sidebar-role-info' && method === 'text' && value === 'Folder 3161'))
  assert.ok(changes.some(([selector, method, value]) => selector === '#sb-right-read' && method === 'iCheck' && value === 'check'))
})

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of the TeamPass project, distributed under the GNU GPL-3.0.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 */
const assert = require('node:assert/strict')
const { test } = require('node:test')
const { readFileSync } = require('node:fs')
const vm = require('node:vm')

const source = readFileSync(require.resolve('../../app/pages/search.js.php'), 'utf8').replace(/\r\n/g, '\n')
const begin = source.indexOf('    const setSearchFavourite =')
const end = source.indexOf('    // Row actions.', begin)
assert.ok(begin > 0 && end > begin, 'The shipped favorites handler must be present')
const handler = source.slice(begin, end).replace(/<\?php[\s\S]*?\?>/g, '"test-session-key"')

// Model only the jQuery/DataTables transport and cell boundary. PHP tests inspect
// the real HTML; these tests run the shipped async behavior and observe its effects.
const setup = ({ filtered = false, info = { start: 0, recordsDisplay: 2 }, focused = false } = {}) => {
  const pendingFavourites = new Set()
  const requests = []
  const errors = []
  const reloads = []
  const pages = []
  const rows = [{ id: 1, cells: Array(7).fill('').concat(JSON.stringify({
    id: 1, attributes: { 'aria-pressed': 'false' }, icon: 'fa-regular fa-star'
  })), disabled: false }]
  let responder = () => Promise.resolve({ error: false })
  let refocused = 0
  let writtenCells = 0
  const selection = {
    prop(name, value) { for (const row of rows) row.disabled = value; return this },
    tooltip() { return this },
    is() { return focused },
    trigger(event) { assert.equal(event, 'focus'); ++refocused; return this },
    find() { return this }
  }
  const $ = (value) => {
    if (typeof value !== 'string' || !value.startsWith('{')) return selection
    const holder = JSON.parse(value)
    const button = {
      attr(name, value) { holder.attributes[name] = value; return this },
      find(selector) {
        assert.equal(selector, 'i')
        return { attr(name, value) { assert.equal(name, 'class'); holder.icon = value } }
      }
    }
    return {
      data(name) { assert.equal(name, 'id'); return holder.id },
      find(selector) { assert.equal(selector, '[data-action="favourite"]'); return button },
      prop(name) { assert.equal(name, 'outerHTML'); return JSON.stringify(holder) }
    }
  }
  $.post = (url, data, callback, format) => {
    requests.push({ url, data, format })
    return responder()
  }
  const page = (target) => {
    pages.push(target)
    return { draw(mode) { assert.equal(mode, 'page') } }
  }
  page.info = () => info
  const oTable = {
    rows() {
      return { every(callback) {
        rows.forEach((row, index) => callback.call({
          data: () => row.cells, node: () => row, index: () => index
        }))
      } }
    },
    cell(index, column) {
      assert.equal(column, 7, 'Existing content column indexes must not move')
      return { data(html) { rows[index].cells[column] = html; ++writtenCells } }
    },
    ajax: { reload(callback, reset) { reloads.push(reset); callback() } },
    page
  }
  const context = vm.createContext({
    $, oTable, pendingFavourites,
    favouriteLabels: { add: 'Favorite', remove: 'Unfavorite', error: 'Request failed' },
    collectFilters: () => ({ favourites: filtered }),
    toastr: { error: (...args) => errors.push(args) }
  })
  vm.runInContext(handler + '\nglobalThis.changeFavourite = setSearchFavourite;', context)
  return {
    change: context.changeFavourite, pendingFavourites, requests, errors, rows, reloads, pages,
    respondWith(fn) { responder = fn },
    state: () => JSON.parse(rows[0].cells[7]),
    refocused: () => refocused,
    writtenCells: () => writtenCells
  }
}

test('add and remove persist explicit state and update DataTables data, labels and keyboard focus', async () => {
  const app = setup({ focused: true })
  await app.change(1, true)
  assert.deepEqual(JSON.parse(JSON.stringify(app.requests[0])), {
    url: 'sources/favourites.queries.php',
    data: { type: 'add_fav', id: 1, key: 'test-session-key' }, format: 'json'
  })
  assert.equal(app.state().attributes['aria-pressed'], 'true')
  assert.equal(app.state().attributes['aria-label'], 'Unfavorite')
  assert.equal(app.state().attributes.title, 'Unfavorite')
  assert.equal(app.state().icon, 'fa-solid fa-star text-warning')
  await app.change(1, false)
  assert.equal(app.requests[1].data.type, 'del_fav')
  assert.equal(app.state().attributes['aria-pressed'], 'false')
  assert.equal(app.state().icon, 'fa-regular fa-star')
  assert.equal(app.refocused(), 2)
  assert.equal(app.writtenCells(), 2)
  assert.deepEqual(app.reloads, [], 'Ordinary searches need no reload')
})

test('a duplicate click while saving sends only one request and changes no state before success', async () => {
  const app = setup()
  let resolve
  app.respondWith(() => new Promise((done) => { resolve = done }))
  const first = app.change(1, true)
  assert.equal(app.rows[0].disabled, true)
  assert.equal(app.pendingFavourites.has('1'), true)
  await app.change(1, true)
  assert.equal(app.requests.length, 1)
  assert.equal(app.state().attributes['aria-pressed'], 'false')
  resolve({ error: false })
  await first
  assert.equal(app.rows[0].disabled, false)
  assert.equal(app.pendingFavourites.size, 0)
  assert.equal(app.state().attributes['aria-pressed'], 'true')
})

for (const [name, response] of [
  ['server rejection', () => Promise.resolve({ error: true })],
  ['missing response', () => Promise.resolve(null)],
  ['malformed success', () => Promise.resolve({})],
  ['transport or JSON failure', () => Promise.reject(new Error('offline'))]
]) {
  test(name + ' leaves the bookmark unchanged and releases the button for retry', async () => {
    const app = setup()
    app.respondWith(response)
    await app.change(1, true)
    assert.equal(app.errors.length, 1)
    assert.equal(app.writtenCells(), 0)
    assert.equal(app.state().attributes['aria-pressed'], 'false')
    assert.equal(app.rows[0].disabled, false)
    assert.equal(app.pendingFavourites.size, 0)
    app.respondWith(() => Promise.resolve({ error: false }))
    await app.change(1, true)
    assert.equal(app.state().attributes['aria-pressed'], 'true')
  })
}

for (const info of [{ start: 10, recordsDisplay: 11 }, { start: 10, recordsDisplay: 10 }, { start: 10, recordsDisplay: 0 }]) {
  test('favorites filter refreshes counts and preserves or repairs the current page: ' + JSON.stringify(info), async () => {
    const app = setup({ filtered: true, info })
    await app.change(1, false)
    assert.deepEqual(app.reloads, [false], 'Preserve the filters, sorting and paging')
    assert.deepEqual(app.pages, info.start >= info.recordsDisplay ? ['last'] : [])
  })
}

test('changing the result page while saving does not update an unrelated row', async () => {
  const app = setup()
  let resolve
  app.respondWith(() => new Promise((done) => { resolve = done }))
  const first = app.change(1, true)
  app.rows[0].cells[7] = JSON.stringify({ id: 2, attributes: { 'aria-pressed': 'false' } })
  resolve({ error: false })
  await first
  assert.equal(app.writtenCells(), 0)
  assert.equal(app.state().attributes['aria-pressed'], 'false')
  assert.equal(app.pendingFavourites.size, 0)
})

test('the row action stops propagation and toggles the displayed state without opening the item', async () => {
  const start = source.indexOf(".on('click', '.search-row-action', ")
  const finish = source.indexOf('\n    });', start)
  assert.ok(start > 0 && finish > start)
  const callback = source.slice(start + ".on('click', '.search-row-action', ".length, finish) + '\n}'
  let toggles = 0
  let stopped = 0
  const button = {
    closest: () => ({}),
    data: () => 'favourite',
    attr: () => 'true'
  }
  const onClick = vm.runInNewContext('(' + callback + ')', {
    $: () => button,
    readRowMeta: () => ({ id: 1 }),
    async setSearchFavourite(id, state) { assert.equal(id, 1); assert.equal(state, false); ++toggles },
    goToItem() { assert.fail('The favorite action must not open the item') }
  })
  await onClick.call({}, {
    preventDefault() { ++stopped },
    stopPropagation() { ++stopped }
  })
  assert.equal(stopped, 2)
  assert.equal(toggles, 1)
})

const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

// Execute the shipped parent-selection handler with a small form/table adapter.
const source = readFileSync(join(__dirname, '../../app/pages/folders.js.php'), 'utf8').replace(/\r\n/g, '\n')
const start = source.indexOf("$('#new-parent').on('change', function() {")
const end = source.indexOf('\n    })', start)
assert.ok(start >= 0 && end > start, 'Missing folder parent-selection handler')
const handlerSource = source.slice(start, end + '\n    })'.length)

function form() {
  let onChange
  const fields = { '#new-add-restriction': false, '#new-edit-restriction': false, '#new-complexity': '0' }
  const parents = new Map()
  const parentSelect = { on(event, handler) { onChange = handler } }
  const $ = selector => {
    if (selector && typeof selector === 'object') return selector
    if (selector === '#new-parent') return parentSelect
    if (selector.startsWith('#table-folders tr')) {
      const parent = parents.get(Number(selector.match(/data-id="([^"]+)"/)[1])) || {}
      return {
        find(cell) { return { data() { return cell === 'td:eq(5)' ? parent.create : parent.edit } } },
        data() { return parent.complexity }
      }
    }
    return {
      iCheck(action) { fields[selector] = action === 'check' },
      val(value) { fields[selector] = value; return this },
      trigger() {}
    }
  }
  vm.runInNewContext(handlerSource, { $, _folderTree: { byId: { get(id) {
    const parent = parents.get(id)
    return parent && { add_is_blocked: parent.create, edit_is_blocked: parent.edit, folderComplexity: { value: parent.complexity } }
  } } } })
  return { fields, parents, select(id) { onChange.call({ val: () => String(id) }) } }
}

for (const create of [0, 1]) {
  for (const edit of [0, 1]) {
    test(`New folder prefills parent options ${create}/${edit} and keeps the complexity floor`, () => {
      const ui = form()
      ui.parents.set(7, { create: String(create), edit: String(edit), complexity: 60 })
      ui.select(7)
      assert.equal(ui.fields['#new-add-restriction'], create === 1)
      assert.equal(ui.fields['#new-edit-restriction'], edit === 1)
      assert.equal(ui.fields['#new-complexity'], '60')
    })
  }
}

test('Changing parent refreshes both defaults; choosing root clears them', () => {
  const ui = form()
  ui.parents.set(7, { create: 1, edit: 0, complexity: 60 })
  ui.parents.set(8, { create: 0, edit: 1, complexity: 60 })
  ui.select(7)
  ui.select(8)
  assert.equal(ui.fields['#new-add-restriction'], false)
  assert.equal(ui.fields['#new-edit-restriction'], true)
  ui.select(0)
  assert.equal(ui.fields['#new-add-restriction'], false)
  assert.equal(ui.fields['#new-edit-restriction'], false)
})

test('Submission sends explicit user overrides after prefilling the parent defaults', () => {
  const ui = form()
  ui.parents.set(7, { create: 1, edit: 1, complexity: 60 })
  ui.select(7)
  ui.fields['#new-add-restriction'] = false
  const declarationStart = source.indexOf('var data = {')
  const declarationEnd = source.indexOf('\n            }', declarationStart)
  assert.ok(declarationStart >= 0 && declarationEnd > declarationStart)
  const declaration = source.slice(declarationStart, declarationEnd + '\n            }'.length)
  const context = {
    purifyRes: { arrFields: { title: 'Child', icon: '', iconSelected: '' } },
    $: selector => ({
      val: () => ({ '#new-parent': '7', '#new-complexity': '60', '#new-access-right': 'W', '#new-renewal': '' })[selector],
      prop: () => ui.fields[selector]
    })
  }
  vm.runInNewContext(declaration, context)
  assert.equal(context.data.addRestriction, 0)
  assert.equal(context.data.editRestriction, 1)
})

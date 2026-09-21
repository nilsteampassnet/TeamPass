const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

test('Renewal opens on all deadlines, orders by date and restores the default after clearing the picker', () => {
  const template = readFileSync(join(__dirname, '../../app/pages/utilities.renewal.js.php'), 'utf8')
    .replace(/<\?php[\s\S]*?\?>/g, '')
  // Assert this repository-owned fixture's exact wrapper before executing its body.
  const scriptLines = template.trim().split(/\r?\n/)
  assert.equal(scriptLines.shift(), "<script type='text/javascript'>")
  assert.equal(scriptLines.pop(), '</script>')
  const handlers = new Map()
  const requests = []
  let selectedDate = null
  let pickerReady = false
  let options
  const reload = () => {
    const data = {}
    options.ajax.data(data)
    requests.push(data)
  }
  const $ = selector => ({
    tooltip() { return this },
    datepicker(action) {
      if (typeof action === 'object') pickerReady = true
      if (action === 'getDate') { assert.equal(pickerReady, true); return selectedDate }
      if (action === 'clearDates') selectedDate = null
      return this
    },
    val() { return this },
    on(event, callback) { handlers.set(selector + ':' + event, callback); return this },
    DataTable(config) {
      options = config
      reload()
      return { ajax: { reload } }
    }
  })
  vm.runInNewContext(scriptLines.join('\n'), { $, Date, toastr: { remove() {}, info() {} } })
  assert.equal(options.stateSave, false)
  assert.deepEqual(JSON.parse(JSON.stringify(options.order)), [[1, 'asc']])
  assert.equal(Object.hasOwn(requests[0], 'dateCriteria'), false)
  selectedDate = new Date(2026, 9, 1)
  handlers.get('#renewal-date:changeDate')()
  assert.equal(requests.at(-1).dateCriteria, selectedDate.valueOf())
  handlers.get('#clear-renewal-date:click')()
  assert.equal(Object.hasOwn(requests.at(-1), 'dateCriteria'), false)
})

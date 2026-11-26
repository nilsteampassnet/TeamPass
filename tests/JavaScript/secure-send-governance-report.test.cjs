/*
 * This file is part of TeamPass.
 * Copyright (C) 2009-2026 TeamPass.net
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE.md for details.
 */

const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

const source = readFileSync(join(__dirname, '../../app/pages/reports.js.php'), 'utf8')
const script = source.slice(source.indexOf('    // Column definitions'), source.lastIndexOf('    //]]>'))
  // PHP consumes a line break immediately following a closing tag.
  .replace(/<\?php echo json_encode\(\$lang->get\('([^']+)'\).*?\?>(?:\r?\n)?/g, (_, key) => JSON.stringify(key))
  .replace(/<\?php[\s\S]*?\?>(?:\r?\n)?/g, '')

function page() {
  const document = {}
  const nodes = new Map()
  const handlers = new Map()
  const requests = []
  const errors = []
  function node(selector) {
    if (!nodes.has(selector)) {
      const state = { visible: false, value: '', props: {} }
      nodes.set(selector, {
        state,
        val(value) { if (value === undefined) return state.value; state.value = value; return this },
        text(value) { if (value === undefined) return selector; state.text = value; return this },
        html(value) { state.html = value; return this },
        prop(key, value) { state.props[key] = value; return this },
        toggle(value) { state.visible = value; return this },
        hide() { return this.toggle(false) },
        show() { return this.toggle(true) },
        trigger(event) {
          if (event === 'focus') state.focused = true
          handlers.get(event + ' ' + selector)?.()
          return this
        },
      })
    }
    return nodes.get(selector)
  }
  function $(selector) {
    if (selector === document) {
      return { on(event, selectors, handler) {
        selectors.split(', ').forEach(selector => handlers.set(event + ' ' + selector, handler))
      } }
    }
    if (typeof selector === 'function') return selector()
    return node(selector)
  }
  $.post = (url, input, success) => {
    const request = { url, input, success }
    requests.push(request)
    return { fail(handler) { request.fail = handler; return this } }
  }
  const htmlEncode = text => String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const toastr = { remove() {}, info() {}, success() {}, error(message) { errors.push(message) } }
  vm.runInNewContext(script, { $, document, toastr, htmlEncode, debugJavascript: false, prepareExchangedData: value => value })
  return { node, requests, errors }
}

test('Secure Send uses calendar dates, groups the escaped evidence and enables its own CSV', () => {
  const ui = page()
  ui.node('#report-type').val('secure_send').trigger('change')
  assert.equal(ui.node('.report-period').state.visible, true)
  ui.node('#report-start').val('2026-03-29')
  ui.node('#report-end').val('2026-03-29')
  ui.node('#report-generate').trigger('click')
  const [request] = ui.requests
  assert.equal(request.url, 'sources/reports.queries.php')
  assert.equal(request.input.type, 'report_secure_send')
  assert.equal(request.input.start_date, '2026-03-29')
  assert.equal(request.input.end_date, '2026-03-29')
  assert.equal(request.input.start, undefined)

  request.success({
    error: false,
    csv: 'scope,indicator,value',
    rows: [
      { section: 'R&D', indicator: '<shared>', value: 3 },
      { section: 'Attachments', indicator: 'Files', value: 0 },
    ],
  })
  assert.equal(ui.node('#report-export-csv').state.props.disabled, false)
  assert.equal(ui.node('#report-results-card').state.visible, true)
  const html = ui.node('#report-results').state.html
  assert.match(html, /scope="rowgroup" colspan="2">R&amp;D/)
  assert.match(html, /&lt;shared&gt;/)
  assert.match(html, /<td>0<\/td>/)
})

test('the governance tile selects the Secure Send report and focuses the visible start date', () => {
  const ui = page()
  ui.node('#report-type').val('access_matrix').trigger('change')
  assert.equal(ui.node('.report-period').state.visible, false)
  ui.node('#report-secure-send').trigger('click')
  assert.equal(ui.node('#report-type').val(), 'secure_send')
  assert.equal(ui.node('.report-period').state.visible, true)
  assert.equal(ui.node('#report-start').state.focused, true)
})

test('switching dates or report type clears the CSV and ignores old results', () => {
  const ui = page()
  ui.node('#report-type').val('secure_send').trigger('change')
  ui.node('#report-generate').trigger('click')
  ui.requests[0].success({ error: false, csv: 'previous', rows: [] })
  assert.equal(ui.node('#report-export-csv').state.props.disabled, false)
  ui.node('#report-start').val('2026-11-01').trigger('change')
  assert.equal(ui.node('#report-export-csv').state.props.disabled, true)

  ui.node('#report-generate').trigger('click')
  ui.node('#report-type').val('access_matrix').trigger('change')
  ui.node('#report-generate').trigger('click')
  ui.requests[1].success({ error: false, csv: 'stale', rows: [] })
  assert.equal(ui.node('#report-export-csv').state.props.disabled, true)
  assert.equal(ui.node('#report-results-card').state.visible, false)
  ui.requests[2].fail()
  assert.deepEqual(ui.errors, ['compliance_report_load_error'])
})

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

// Execute the shipped cell renderers of Monitoring > Logs: the Secure Send source and the label of
// a view made through a Secure Send link. Every value comes from the database, so every renderer
// must escape.
const source = readFileSync(join(__dirname, '../../app/pages/utilities.logs.js.php'), 'utf8').replace(/\r\n/g, '\n')
const start = source.indexOf('    const logCellRenderers = {')
const end = source.indexOf('\n    }\n', start)
assert.ok(start >= 0 && end > start, 'Missing logCellRenderers')
const renderersSource = source.slice(start, end + 6)
  .replace("<?php echo logsSecureSendViewAction(); ?>", 'at_secure_send_view')
assert.ok(!renderersSource.includes('<?php'), 'Unexpected PHP left in the renderers')

const escape = value => String(value === null || value === undefined ? '' : value)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
const context = {
  escapeLogValue: escape,
  escapeLogAttribute: escape,
  renderLogText: escape,
  logTypeTitles: {},
  logActionTitles: { at_shown: 'Shown', at_secure_send_view: 'Viewed through Secure Send' },
  logSecureSendEventTitles: { invalidated: 'Invalidated', created: 'Link created' },
  logSecureSendReasonTitles: { item_access_lost: '(item deleted or no longer readable by the sender)' },
  logMessages: {
    yes: 'Yes', no: 'No',
    secureSendNote: 'Secure note',
    secureSendDeletedAccount: 'Deleted account #tp_id#',
    secureSendRemovedItem: 'Removed item #tp_id#',
    secureSendPassphrase: 'passphrase',
    secureSendPublic: 'public address',
    secureSendViews: '#tp_views#/#tp_max# view(s)',
    secureSendFailed: '#tp_count# failed attempt(s)',
    secureSendExpires: 'expires #tp_date#'
  }
}
vm.createContext(context)
vm.runInContext(renderersSource + '\nthis.renderers = logCellRenderers', context)
const r = context.renderers

const row = overrides => Object.assign({
  event: 'created', reason: '', user: 'Alice Sender [alice]', originator: 42, send_type: 'item',
  item: 'Router', item_id: 123, send: 9, has_passphrase: false, is_public: false, views: 0,
  max_views: 1, failed_attempts: 0, expires_at: ''
}, overrides)

test('an item-log view by the One-Time View account is labelled as a Secure Send view', () => {
  assert.equal(r.action({ action: 'at_shown', secure_send: true }), 'Viewed through Secure Send')
  assert.equal(r.action({ action: 'at_shown', secure_send: false }), 'Shown')
})

test('event and reason are translated, the badge carries no stored markup', () => {
  assert.equal(r.event(row({ event: 'invalidated', reason: 'item_access_lost' })),
    '<span class="badge badge-info">Invalidated</span> (item deleted or no longer readable by the sender)')
  assert.equal(r.event(row({ event: '<img src=x onerror=alert(1)>' })),
    '<span class="badge badge-info">&lt;img src=x onerror=alert(1)&gt;</span>')
})

test('vanished accounts and items are named, notes are labelled', () => {
  assert.equal(r.user(row({ user: '' })), 'Deleted account 42')
  assert.equal(r.user(row({ user: '<b>Mallory</b> [m]' })), '&lt;b&gt;Mallory&lt;/b&gt; [m]')
  // The other sources carry no originator: their user cell is rendered as before.
  assert.equal(r.user({ user: '' }), '')
  assert.equal(r.item(row({ item: '', item_id: 123 })), 'Removed item 123')
  assert.equal(r.item(row({ send_type: 'note', item: '', item_id: null })), 'Secure note')
  assert.equal(r.item(row({ item: '<script>x</script>' })), '&lt;script&gt;x&lt;/script&gt;')
  assert.equal(r.send(row({ send: 456 })), '#456')
})

test('details summarize the link policy and counters', () => {
  assert.equal(r.policy(row()), '0/1 view(s)')
  assert.equal(r.policy(row({ views: 1, max_views: 3, has_passphrase: true, is_public: true, failed_attempts: 2, expires_at: '2026-11-20 10:00' })),
    '1/3 view(s) · passphrase · public address · 2 failed attempt(s) · expires 2026-11-20 10:00')
})

// Capture the shipped table configuration without a DOM, then resolve cells through the same
// DataTables functions loaded by public/index.php. Testing the renderer alone misses warnings
// raised during type detection on an Ajax reload.
const dataTables = readFileSync(join(__dirname, '../../public/plugins/datatables/js/jquery.dataTables.js'), 'utf8')
  .replace(/\r\n/g, '\n')
const shippedFunction = name => {
  const start = dataTables.indexOf('\tfunction ' + name + '(')
  const end = dataTables.indexOf('\n\t}', start)
  assert.ok(start >= 0 && end > start, 'Missing DataTables function ' + name)
  return dataTables.slice(start, end + 3)
}
const tableStart = source.indexOf('    function buildLogsTable(')
const tableEnd = source.indexOf('\n    }\n', tableStart)
assert.ok(tableStart >= 0 && tableEnd > tableStart, 'Missing buildLogsTable')
const tableSource = source.slice(tableStart, tableEnd + 6).replace(/<\?php[\s\S]*?\?>/g, '')
const bindingStart = dataTables.indexOf('\t\tvar mDataSrc = oCol.mData;')
const bindingEnd = dataTables.indexOf('\n\t\toCol.fnSetData', bindingStart)
assert.ok(bindingStart >= 0 && bindingEnd > bindingStart, 'Missing DataTables column binding')

function tableHarness() {
  const warnings = []
  let options
  const chain = { empty() { return this }, text() { return this }, appendTo() { return this } }
  const jquery = () => ({
    ...chain,
    DataTable(value) { options = value; return { destroy() {} } }
  })
  jquery.isPlainObject = value => value !== null && typeof value === 'object'
  const tableContext = vm.createContext({
    ...context,
    $: jquery,
    oTableLogs: null,
    logCurrentSignature: '',
    logColumnTitles: {},
    logCellRenderers: r,
    logsDataUrl: 'sources/logs.datatables.php',
    _fnLog: (settings, level, message) => warnings.push(message)
  })
  vm.runInContext(tableSource + '\n' + shippedFunction('_fnGetObjectDataFn') + '\n'
    + shippedFunction('_fnGetCellData') + '\n'
    + 'this.bindColumn = function(oCol) {\n' + dataTables.slice(bindingStart, bindingEnd) + '\n}', tableContext)
  tableContext.buildLogsTable('secure_send', ['date', 'event', 'user', 'item', 'send', 'policy'])
  const settings = {
    iDraw: 1,
    aoColumns: options.columns.map(column => {
      const bound = { mData: column.data, mRender: column.render, sDefaultContent: column.defaultContent }
      tableContext.bindColumn(bound)
      return bound
    }),
    aoData: []
  }
  return {
    warnings,
    load(records) { settings.iDraw++; settings.aoData = records.map(record => ({ _aData: record })) },
    cell(rowIndex, columnIndex, type) { return tableContext._fnGetCellData(settings, rowIndex, columnIndex, type) }
  }
}

test('Secure Send details survive initial loading and filtered reloads without DataTables warnings', () => {
  const table = tableHarness()
  for (const record of [row(), row({ event: 'revoked', reason: 'sender_revoked', views: 2, max_views: 3 })]) {
    table.load([record])
    // Type detection asks for data before drawing the cell, including after event filtering.
    for (const type of ['type', 'sort', 'filter']) {
      const value = table.cell(0, 5, type)
      assert.deepEqual(table.warnings, [])
      assert.equal(value, '')
    }
    assert.equal(table.cell(0, 5, 'display'), r.policy(record))
    assert.equal(table.cell(0, 4, 'type'), record.send)
    assert.equal(table.cell(0, 4, 'display'), '#' + record.send)
  }
  assert.deepEqual(table.warnings, [])
})

test('computed Secure Send details still escape policy text when DataTables renders the cell', () => {
  const table = tableHarness()
  table.load([row({ expires_at: '<img src=x onerror=alert(1)>' })])
  assert.equal(table.cell(0, 5, 'display'), '0/1 view(s) · expires &lt;img src=x onerror=alert(1)&gt;')
  assert.deepEqual(table.warnings, [])
})

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

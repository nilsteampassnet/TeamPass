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

const source = readFileSync(join(__dirname, '../../app/pages/lapr_endpoints.js.php'), 'utf8').replace(/\r\n/g, '\n')
function section(name) {
  const start = source.indexOf('\nfunction ' + name + '(')
  const end = source.indexOf('\n}\n', start)
  assert.ok(start >= 0 && end > start, 'Missing production function: ' + name)
  return source.slice(start, end + 3)
}

const escape = text => String(text ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

// The list renderer and its action buttons run unchanged; jQuery, DataTables,
// DOMPurify and the HTTP layer are replaced by recording adapters.
function harness(endpoints) {
  const tables = []
  const context = {
    laprLang: {
      deleteLabel: 'Delete', checkEndpoint: 'Check', pauseEndpoint: 'Pause', resumeEndpoint: 'Resume',
      hostkeyUnchecked: 'no host key check', lastError: 'Last error', errorGeneric: 'Error',
      statusActive: 'Active', statusPaused: 'Paused', statusError: 'Error', statusUnreachable: 'Unreachable', statusDeleted: 'Deleted',
      credentialMissing: 'SSH credential unavailable',
      credentialMissingHint: 'Credential <img src=x onerror=alert(1)> deleted'
    },
    laprEndpointDataTablesLang: {},
    laprEndpointsTable: null,
    laprEndpointRows: {},
    DOMPurify: { sanitize: value => escape(value) },
    toastr: { error() {} },
    laprPost(type, payload, onDone) {
      assert.equal(type, 'list_endpoints')
      onDone({ error: false, data: endpoints })
    },
    $(selector) {
      if (selector === '<div>') {
        let text = ''
        return { text(value) { text = value; return this }, html() { return escape(text) } }
      }
      return { DataTable(options) { tables.push(options.data); return { clear() { return this } } } }
    }
  }
  vm.createContext(context)
  vm.runInContext(['laprHtml', 'laprLoadEndpoints', 'laprStatusBadge', 'laprEndpointActions'].map(section).join('\n'), context)
  context.laprLoadEndpoints()

  return { rows: tables[0], context }
}

const endpoint = (id, overrides = {}) => Object.assign({
  id, label: 'debian', hostname: 'debian.example', port: 22, ssh_username: 'root', status: 'active',
  hostkey_verified: 1, last_check_at: '', last_check_at_ts: 0, os_name: 'Debian', has_sudo: false,
  is_root: true, last_error: '', credential_missing: false
}, overrides)

test('an endpoint without a usable SSH credential can only be deleted', () => {
  const { rows } = harness([endpoint(7, { credential_missing: true })])
  const actions = rows[0][6]

  assert.match(actions, /lapr-delete-ep" data-id="7"/)
  assert.doesNotMatch(actions, /lapr-check-ep|lapr-pause-ep|lapr-resume-ep/)
})

test('a usable endpoint keeps every action', () => {
  const { rows } = harness([endpoint(8), endpoint(9, { status: 'disabled' })])

  assert.match(rows[0][6], /lapr-check-ep/)
  assert.match(rows[0][6], /lapr-pause-ep/)
  assert.match(rows[0][6], /lapr-delete-ep/)
  assert.match(rows[1][6], /lapr-resume-ep/)
  assert.match(rows[1][6], /lapr-delete-ep/)
})

test('the missing credential is flagged with an escaped explanation', () => {
  const { rows } = harness([endpoint(7, { credential_missing: true }), endpoint(8)])

  assert.match(rows[0][4], /badge-danger">SSH credential unavailable</)
  assert.match(rows[0][4], /Credential &lt;img src=x onerror=alert\(1\)&gt; deleted/)
  assert.doesNotMatch(rows[0][4], /<img/)
  assert.doesNotMatch(rows[1][4], /SSH credential unavailable/)
})

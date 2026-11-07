/* TeamPass - GPL-3.0-or-later. Copyright 2009-2026 Teampass.net. */
const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const path = require('node:path')
const source = fs.readFileSync(path.join(__dirname, '../../public/install/permissions.js'), 'utf8')
const flush = () => new Promise((resolve) => setImmediate(resolve))

function app(responses) {
  const elements = new Map()
  const element = (id) => {
    if (!elements.has(id)) elements.set(id, { hidden: ['corrections', 'findings', 'manual', 'complete'].includes(id), disabled: false, checked: false,
      textContent: '', handlers: {}, style: {},
      addEventListener(event, handler) { this.handlers[event] = handler },
      set innerHTML(value) { throw new Error('Unsafe HTML assignment') } })
    return elements.get(id)
  }
  element('data').textContent = JSON.stringify({ flow: 'install', csrf: 'fixture-csrf' })
  const requests = []
  const clipboard = []
  const context = { URLSearchParams, navigator: { clipboard: { async writeText(text) { clipboard.push(text) } } },
    document: { getElementById: (id) => element(id.replace('setup-permissions-', '')) },
    fetch: async (url, options) => {
      requests.push({ url, ...options })
      const response = responses.shift()
      if (response instanceof Error) throw response
      return { ok: response.success, json: async () => response }
    } }
  vm.runInNewContext(source, context)
  return { element, requests, clipboard }
}

test('Known findings block completion and command copying preserves quoted paths', async () => {
  const command = "sudo chmod 0750 -- '/srv/TeamPass O'\\''Brien'"
  const ui = app([{ success: true, review: { status: 'issues', issues: 2, commands: [command],
    findings: [{ path: '<img onerror=alert(1)>', reason: 'world_writable', actual_mode: '0777', expected: '0750' }] } }])
  await flush()
  assert.equal(ui.element('finalize').disabled, true)
  assert.equal(ui.element('corrections').hidden, false)
  assert.equal(ui.element('no-plan').hidden, true)
  assert.equal(ui.element('commands').textContent, `(\nset -e\n${command}\n)`)
  assert.match(ui.element('findings-text').textContent, /<img onerror=alert\(1\)>/)
  await ui.element('copy').handlers.click()
  assert.equal(ui.clipboard[0], ui.element('commands').textContent)
  assert.equal(ui.requests[0].headers['X-CSRF-Token'], 'fixture-csrf')
})

test('Manual review needs an explicit choice and is recorded as manual', async () => {
  const review = { status: 'manual', issues: null, commands: [] }
  const ui = app([{ success: true, review }, { success: true, review, finalized: true, cleanup: 'MANUAL CLEANUP' }])
  await flush()
  assert.equal(ui.element('finalize').disabled, true)
  ui.element('ack').checked = true
  ui.element('ack').handlers.change()
  assert.equal(ui.element('finalize').disabled, false)
  ui.element('finalize').handlers.click()
  await flush()
  assert.equal(ui.requests[1].body.get('acknowledge_manual'), '1')
  assert.match(ui.element('status').textContent, /Manual review acknowledged/)
  assert.equal(ui.element('complete').hidden, false)
  assert.equal(ui.element('cleanup').textContent, 'MANUAL CLEANUP')
})

test('A final server audit can reject a previous pass and keep setup blocked', async () => {
  const ui = app([{ success: true, review: { status: 'passed' } }, { success: false,
    error: 'PERMISSIONS_REVIEW_REQUIRED', review: { status: 'issues', issues: 1, commands: [] } }])
  await flush()
  assert.equal(ui.element('finalize').disabled, false)
  ui.element('finalize').handlers.click()
  await flush()
  assert.equal(ui.element('finalize').disabled, true)
  assert.equal(ui.element('complete').hidden, true)
  assert.equal(ui.element('no-plan').hidden, false)
  assert.match(ui.element('status').textContent, /Permissions changed/)
  assert.equal(ui.element('refresh').disabled, false)
})

test('A failed refresh invalidates a previous pass', async () => {
  const ui = app([{ success: true, review: { status: 'passed' } }, new Error('untrusted network detail')])
  await flush()
  ui.element('refresh').handlers.click()
  await flush()
  assert.equal(ui.element('finalize').disabled, true)
  assert.doesNotMatch(ui.element('status').textContent, /untrusted network detail/)
  assert.match(ui.element('status').textContent, /audit did not complete/i)
})

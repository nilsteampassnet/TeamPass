/* TeamPass - GPL-3.0-or-later. Copyright 2009-2026 Teampass.net. */
const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const path = require('node:path')

const source = fs.readFileSync(path.join(__dirname, '../../public/install/recovery.js'), 'utf8')
const job = (status) => ({ id: 'a'.repeat(32), status, error: '', tables: 80, documents: 3,
  plan: { source_version: '3.2.2.7', target_version: '3.2.3.2' } })

function app(initial = null) {
  const elements = new Map()
  const element = (id) => {
    if (!elements.has(id)) elements.set(id, { textContent: '', hidden: true, disabled: false,
      handlers: {}, addEventListener(name, handler) { this.handlers[name] = handler },
      set innerHTML(value) { throw new Error('Unsafe HTML assignment') } })
    return elements.get(id)
  }
  const button = { disabled: false }
  const password = { value: 'synthetic-only' }
  const form = element('recovery-prepare')
  form.hidden = false
  form.querySelector = () => button
  form.querySelectorAll = () => [password]
  element('recovery-data').textContent = JSON.stringify({ csrf: 'fixture-csrf', initial,
    ready: 'PREPARED', verified: 'VERIFIED', complete: 'COMPLETE', running: 'RUNNING',
    failed: 'FAILED', counts: '%s tables, %s documents', preparing: 'WAIT', error: 'ERROR' })
  const queue = []
  const requests = []
  const context = { document: { getElementById: element, querySelectorAll: () => [form] },
    FormData: class { constructor() { this.fields = {} } set(name, value) { this.fields[name] = value } },
    window: { location: { href: 'https://fixture.test/install/recovery.php', reload() {} } },
    fetch: async (url, options) => {
      requests.push(options)
      const response = queue.shift()
      if (response instanceof Error) throw response
      return { ok: true, json: async () => ({ success: true, result: response }) }
    } }
  vm.runInNewContext(source, context)
  return { element, form, button, password, queue, requests,
    submit: () => form.handlers.submit({ preventDefault() {} }),
    refresh: () => element('recovery-refresh').handlers.click() }
}

test('Recovery progresses from import to activation and operational resume without offering a second import', async () => {
  const ui = app()
  ui.queue.push(job('prepared'), job('verified'), job('complete'))
  await ui.submit()
  assert.equal(ui.requests[0].body.fields.csrf, 'fixture-csrf')
  assert.equal(ui.password.value, '')
  assert.equal(ui.form.hidden, true)
  assert.match(ui.element('recovery-command').textContent, /--run=/)
  assert.equal(ui.element('recovery-command').hidden, false)
  await ui.refresh()
  assert.equal(ui.element('recovery-command').hidden, true)
  assert.equal(ui.element('recovery-instruction').textContent, 'VERIFIED')
  assert.match(ui.element('recovery-activate').textContent, /--activate=/)
  await ui.refresh()
  assert.equal(ui.element('recovery-instruction').textContent, 'COMPLETE')
  assert.match(ui.element('recovery-activate').textContent, /--resume=/)
})

test('Reloading a prepared job restores its session-bound progress', () => {
  const ui = app(job('prepared'))
  assert.equal(ui.element('recovery-result').hidden, false)
  assert.match(ui.element('recovery-plan').textContent, /80 tables, 3 documents/)
})

test('Network failures restore the submit button and render untrusted diagnostics as text', async () => {
  const ui = app()
  ui.queue.push(new Error('<img src=x onerror=alert(1)>'))
  await ui.submit()
  assert.equal(ui.button.disabled, false)
  assert.equal(ui.form.hidden, false)
  assert.equal(ui.element('recovery-message').textContent, 'ERROR <img src=x onerror=alert(1)>')
})

test('Running and failed jobs do not offer an import or activation command', () => {
  for (const status of ['importing', 'upgrading', 'failed']) {
    const ui = app(job(status))
    assert.equal(ui.element('recovery-command').hidden, true)
    assert.equal(ui.element('recovery-activate').hidden, true)
  }
})

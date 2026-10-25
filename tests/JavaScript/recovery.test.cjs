/* TeamPass - GPL-3.0-or-later. Copyright 2009-2026 Teampass.net. */
const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const path = require('node:path')

const source = fs.readFileSync(path.join(__dirname, '../../public/install/recovery.js'), 'utf8')
const job = (status, permissions) => ({ id: 'a'.repeat(32), status, permissions, error: '', tables: 80, documents: 3,
  plan: { source_version: '3.2.2.7', target_version: '3.2.3.2' } })

function app(initial = null, command = "sudo -u 'www-data' -- '/usr/bin/php8.5' '/srv/TeamPass Lab/app/scripts/disaster-recovery.php'") {
  const elements = new Map()
  const element = (id) => {
    if (!elements.has(id)) elements.set(id, { id, dataset: {}, textContent: '', hidden: true, disabled: false,
      handlers: {}, addEventListener(name, handler) { this.handlers[name] = handler },
      attributes: {}, setAttribute(name, value) { this.attributes[name] = value },
      removeAttribute(name) { delete this.attributes[name] },
      focus() { this.focused = true }, scrollIntoView() { this.scrolled = true },
      classList: { add() {}, remove() {} },
      before(button) { this.copyButton = button }, select() {}, remove() { this.removed = true },
      set innerHTML(value) { throw new Error('Unsafe HTML assignment') } })
    return elements.get(id)
  }
  const button = { disabled: false }
  const password = { ...element('password-input'), name: 'kit_password', type: 'password', value: 'synthetic-only', validity: { valid: true } }
  const inputs = [password]
  const form = element('recovery-prepare')
  form.hidden = false
  form.querySelector = () => button
  form.querySelectorAll = (selector) => selector === 'input[type=password]' ? [password] : inputs
  element('recovery-data').textContent = JSON.stringify({ csrf: 'fixture-csrf', initial,
    ready: 'PREPARED', verified: 'VERIFIED', complete: 'COMPLETE', running: 'RUNNING',
    failed: 'FAILED', counts: '%s tables, %s documents', preparing: 'WAIT', error: 'ERROR',
    command,
    cleanup: "sudo rm -rf -- '/srv/TeamPass Lab/public/install'",
    resumed: 'RESUMED', resumedMaintenance: 'MAINTENANCE', permissionsNotChecked: 'AUDIT FIRST',
    permissionsPassed: 'PERMISSIONS PASS', permissionsIssues: '%s findings', permissionsManual: 'MANUAL ACL REVIEW',
    cleanupManual: 'MANUAL CLEANUP',
    fields: { kit: 'Recovery Package', backup: 'Complete backup', database: 'Database name', port: 'Port', url: 'Restored HTTPS address' },
    errors: { OPERATOR_CODE_UNREADABLE: 'Use the PHP service account', OPERATOR_CODE_REQUIRED: 'Enable recovery first' } })
  const queue = []
  const requests = []
  const clipboard = []
  const commands = ['recovery-enable-command', 'recovery-command', 'recovery-activate', 'recovery-permissions-command',
    'recovery-permissions-commands', 'recovery-resume-command', 'recovery-cleanup-command'].map(element)
  const context = { document: { getElementById: element,
    querySelectorAll: (selector) => selector === '[data-recovery-command]' ? commands : [form],
    createElement: (tag) => element(`${tag}-${elements.size}`), body: { appendChild() {} }, execCommand: () => true },
    navigator: { clipboard: { async writeText(text) { clipboard.push(text) } } },
    FormData: class { constructor() { this.fields = {} } set(name, value) { this.fields[name] = value } },
    window: { location: { href: 'https://fixture.test/install/recovery.php', reload() {} } },
    fetch: async (url, options) => {
      requests.push(options)
      const response = queue.shift()
      if (response instanceof Error) throw response
      if (response && response.httpError) return { ok: false, json: async () => ({ success: false, error: response.httpError }) }
      return { ok: true, json: async () => ({ success: true, result: response }) }
    } }
  vm.runInNewContext(source, context)
  return { element, form, button, password, inputs, queue, requests, clipboard, context,
    submit: () => form.handlers.submit({ preventDefault() {} }),
    refresh: () => element('recovery-refresh').handlers.click() }
}

test('Recovery progresses from import to activation and operational resume without offering a second import', async () => {
  const ui = app()
  ui.queue.push(job('prepared'), job('verified'), job('complete', { status: 'passed' }), job('resumed', { status: 'passed' }))
  await ui.submit()
  assert.equal(ui.requests[0].body.fields.csrf, 'fixture-csrf')
  assert.equal(ui.password.value, '')
  assert.equal(ui.form.hidden, true)
  assert.match(ui.element('recovery-command').textContent, /--run=/)
  assert.match(ui.element('recovery-command').textContent, /^sudo -u 'www-data' -- '\/usr\/bin\/php8\.5' '\/srv\/TeamPass Lab\//)
  assert.equal(ui.element('recovery-command').hidden, false)
  assert.equal(ui.element('recovery-activate').hidden, true)
  assert.equal(ui.element('recovery-activate').textContent, '')
  await ui.refresh()
  assert.equal(ui.element('recovery-command').hidden, true)
  assert.equal(ui.element('recovery-instruction').textContent, 'VERIFIED')
  assert.match(ui.element('recovery-activate').textContent, /--activate=/)
  assert.match(ui.element('recovery-activate').textContent, /^sudo -u 'www-data'/)
  await ui.refresh()
  assert.equal(ui.element('recovery-instruction').textContent, 'COMPLETE')
  assert.match(ui.element('recovery-resume-command').textContent, /--resume=/)
  assert.match(ui.element('recovery-resume-command').textContent, /^sudo -u 'www-data'/)
  assert.equal(ui.element('recovery-finished').hidden, true)
  await ui.refresh()
  assert.equal(ui.element('recovery-finished').hidden, false)
  assert.equal(ui.element('recovery-resume').hidden, true)
  assert.equal(ui.element('recovery-refresh').hidden, true)
  assert.equal(ui.element('recovery-step-5').attributes['aria-current'], 'step')
  assert.equal(ui.element('recovery-cleanup-command').textContent, "sudo rm -rf -- '/srv/TeamPass Lab/public/install'")
})

test('Copy buttons preserve complete commands and follow the visible phase', async () => {
  const ui = app(job('prepared'))
  const run = ui.element('recovery-command')
  assert.equal(run.copyButton.hidden, false)
  assert.equal(ui.element('recovery-activate').copyButton.hidden, true)
  await run.copyButton.handlers.click()
  assert.equal(ui.clipboard[0], run.textContent)
  assert.equal(run.copyButton.textContent, 'Copied')
  ui.queue.push(job('verified'))
  await ui.refresh()
  assert.equal(run.copyButton.hidden, true)
  assert.equal(ui.element('recovery-activate').copyButton.hidden, false)
})

test('Multiline copy preserves administrator commands without interpreting their content', async () => {
  const command = "sudo chown 'root:www-data' '/srv/O\\'Brien'\nsudo chmod 640 '<untrusted>'"
  const ui = app(job('complete', { status: 'issues', issues: 2, commands: [command] }))
  const block = ui.element('recovery-permissions-commands')
  assert.equal(block.copyButton.textContent, 'Copy commands')
  await block.copyButton.handlers.click()
  assert.equal(ui.clipboard[0], block.textContent)
  assert.ok(block.textContent.startsWith('(\nset -e\n'))
  assert.ok(block.textContent.includes(command))
  assert.match(block.textContent, /sudo -u 'www-data'.*--check-permissions=[a-f0-9]+\n\)$/)
})

test('Unavailable clipboard access falls back locally and reports failure without claiming success', async () => {
  const ui = app(job('prepared'))
  delete ui.context.navigator.clipboard
  const button = ui.element('recovery-command').copyButton
  await button.handlers.click()
  assert.equal(button.textContent, 'Copied')
  ui.context.document.execCommand = () => false
  await button.handlers.click()
  assert.match(button.textContent, /Copy unavailable/)
})

test('Missing fields produce an English red summary before any request', async () => {
  const ui = app()
  const input = { ...ui.element('database'), name: 'database', value: '', required: true, validity: { valid: false, valueMissing: true } }
  ui.inputs.push(input)
  await ui.submit()
  assert.equal(ui.requests.length, 0)
  assert.match(ui.element('recovery-message').className, /alert-danger/)
  assert.match(ui.element('recovery-message').textContent, /Database name is required/)
  assert.equal(ui.element('recovery-message').focused, true)
  assert.equal(input.attributes['aria-invalid'], 'true')
  assert.equal(ui.password.value, 'synthetic-only')
})

test('Archive selection accepts either upload or private incoming filename', async () => {
  const ui = app()
  const file = { ...ui.element('kit'), name: 'kit', type: 'file', value: '', files: [], validity: { valid: true } }
  const server = { ...ui.element('kit-server'), name: 'kit_server', value: '', validity: { valid: true } }
  ui.inputs.push(file, server)
  await ui.submit()
  assert.equal(ui.requests.length, 0)
  assert.match(ui.element('recovery-message').textContent, /select a file or enter its filename/)
  server.value = 'fixture.tprecovery'
  ui.queue.push(job('prepared'))
  await ui.submit()
  assert.equal(ui.requests.length, 1)
  assert.equal(file.attributes['aria-invalid'], undefined)
})

test('Invalid port and non-HTTPS addresses use English validation messages', async () => {
  const ui = app()
  ui.inputs.push({ ...ui.element('port'), name: 'port', value: '65536', validity: { valid: false, rangeOverflow: true } },
    { ...ui.element('url'), name: 'url', value: 'http://fixture.test', validity: { valid: true } })
  await ui.submit()
  assert.equal(ui.requests.length, 0)
  assert.match(ui.element('recovery-message').textContent, /between 1 and 65535/)
  assert.match(ui.element('recovery-message').textContent, /must start with https:\/\//)
})

test('Each stage preserves the complete server-provided PowerShell command', () => {
  const command = "& 'C:/PHP/php.exe' 'C:/TeamPass Lab/app/scripts/disaster-recovery.php'"
  for (const [status, action] of [['prepared', 'run'], ['verified', 'activate'], ['complete', 'resume']]) {
    const ui = app(job(status, { status: 'passed' }), command)
    const element = ui.element(status === 'prepared' ? 'recovery-command' : (status === 'complete' ? 'recovery-resume-command' : 'recovery-activate'))
    if (status !== 'complete') assert.equal(element.hidden, false)
    assert.equal(element.textContent, `${command} --${action}=${'a'.repeat(32)}`)
  }
})

test('Published legacy receipts require a permission review before offering resume or cleanup', () => {
  const ui = app(job('complete'))
  assert.equal(ui.element('recovery-permissions').hidden, false)
  assert.match(ui.element('recovery-permissions-command').textContent, /^sudo -u 'www-data'.*--check-permissions=/)
  assert.equal(ui.element('recovery-permissions-status').textContent, 'AUDIT FIRST')
  assert.equal(ui.element('recovery-resume').hidden, true)
  assert.equal(ui.element('recovery-finished').hidden, true)
})

test('Permission findings combine administrator corrections and the service account audit in one block', () => {
  const command = 'sudo chown root:www-data "<untrusted>"'
  const ui = app(job('complete', { status: 'issues', issues: 3, commands: [command] }))
  assert.ok(ui.element('recovery-permissions-commands').textContent.includes(command))
  assert.match(ui.element('recovery-permissions-commands').textContent, /--check-permissions=/)
  assert.equal(ui.element('recovery-permissions-audit').hidden, true)
  assert.equal(ui.element('recovery-permissions-status').textContent, '3 findings')
  assert.equal(ui.element('recovery-permissions-plan').hidden, false)
  assert.equal(ui.element('recovery-resume').hidden, true)
})

test('Unsupported permission platforms require manual review without claiming an audit pass', () => {
  const ui = app(job('complete', { status: 'manual' }))
  assert.equal(ui.element('recovery-permissions-status').textContent, 'MANUAL ACL REVIEW')
  assert.equal(ui.element('recovery-permissions-manual').hidden, false)
  assert.equal(ui.element('recovery-resume').hidden, false)
  assert.equal(ui.element('recovery-finished').hidden, true)
})

test('An already resumed legacy instance still offers the missing permission review', () => {
  const ui = app(job('resumed'))
  assert.equal(ui.element('recovery-permissions').hidden, false)
  assert.equal(ui.element('recovery-resume').hidden, true)
  assert.equal(ui.element('recovery-finished').hidden, true)
})

test('A correction failure stops the combined block before its verification command', { skip: process.platform !== 'linux' }, () => {
  const { spawnSync } = require('node:child_process')
  const ui = app(job('complete', { status: 'issues', issues: 1, commands: ['false'] }), 'audit')
  const block = ui.element('recovery-permissions-commands').textContent
  const result = spawnSync('sh', ['-c', 'audit() { echo verification-ran; };\n' + block], { encoding: 'utf8' })
  assert.equal(result.status, 1)
  assert.equal(result.stdout, '')
})

test('An unreadable operator file reports the service account remediation', async () => {
  const ui = app()
  ui.queue.push({ httpError: 'OPERATOR_CODE_UNREADABLE' })
  await ui.submit()
  assert.equal(ui.element('recovery-message').textContent, 'ERROR Use the PHP service account')
  assert.equal(ui.button.disabled, false)
})

test('An open prepare form returns to enable instructions after server authorization is removed or changed', async () => {
  for (const code of ['OPERATOR_CODE_REQUIRED', 'OPERATOR_CODE_EXPIRED', 'OPERATOR_AUTHORIZATION_REQUIRED']) {
    const ui = app()
    ui.queue.push({ httpError: code })
    await ui.submit()
    assert.equal(ui.form.hidden, true)
    assert.equal(ui.element('recovery-authorization-help').hidden, false)
    assert.equal(ui.button.disabled, false)
    assert.match(ui.element('recovery-message').className, /alert-danger/)
    if (code === 'OPERATOR_CODE_REQUIRED') assert.equal(ui.element('recovery-message').textContent, 'ERROR Enable recovery first')
  }
})

test('Wrong archive diagnostics leave the prepare form available for correction', async () => {
  const ui = app()
  ui.queue.push({ httpError: 'ARCHIVE_AUTHENTICATION_FAILED' })
  await ui.submit()
  assert.equal(ui.form.hidden, false)
  assert.equal(ui.element('recovery-authorization-help').hidden, true)
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

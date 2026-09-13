const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

const source = readFileSync(join(__dirname, '../../public/assets/js/renewal-preview.js'), 'utf8')

function harness(enabled = true) {
  const elements = new Map()
  const pending = []
  const prompts = []
  const errors = []
  let answer = true
  function element() {
    return {
      classes: new Set(['hidden']), children: [], value: '',
      empty() { this.children = []; this.value = ''; return this },
      text(value) { this.value = String(value); return this },
      addClass(names) { names.split(' ').forEach(name => this.classes.add(name)); return this },
      removeClass(names) { names.split(' ').forEach(name => this.classes.delete(name)); return this },
      toggleClass(name, on) { if (on) this.classes.add(name); else this.classes.delete(name); return this },
      appendTo(target) { target.children.push(this); return this }
    }
  }
  const $ = selector => {
    if (selector === '<div>') return element()
    if (!elements.has(selector)) elements.set(selector, element())
    return elements.get(selector)
  }
  $.post = (url, data) => new Promise((resolve, reject) => pending.push({ url, data, resolve, reject }))
  $.Deferred = () => {
    let value
    return { resolve(data) { value = Promise.resolve(data); return this }, reject() { value = Promise.reject(); return this }, promise() { return value } }
  }
  const context = {
    $, decodeQueryReturn: data => data, toastr: { error: text => errors.push(text) },
    window: { confirm(text) { prompts.push(text); return answer } }
  }
  vm.runInNewContext(source, context)
  const preview = context.createRenewalPreview({ enabled, key: 'session-key', messages: {
    period: 'Every #days# days', none: 'No renewal', explanation: 'No deletion',
    due: 'Due #date#', estimate: 'Estimate', existing: 'Password age preserved', expired: 'Already expired',
    unknown: 'Unknown date', unavailable: 'Unavailable', loading: 'Loading', move_confirm: 'Move?'
  } })
  return { preview, pending, prompts, errors, field: $, answer(value) { answer = value } }
}

function response(days = 90, items = [], creation = false) {
  return { error: false, enabled: true, days, items, creation }
}

test('Folder policies include empty folders and explicit zero; expiration off stays hidden without a request', async () => {
  const ui = harness()
  const first = ui.preview.update('#folder', 11)
  ui.pending[0].resolve(response())
  await first
  assert.equal(ui.field('#folder').children[0].value, 'Every 90 days')
  const second = ui.preview.update('#folder', 12)
  ui.pending[1].resolve(response(0))
  await second
  assert.equal(ui.field('#folder').children[0].value, 'No renewal')
  const disabled = harness(false)
  await disabled.preview.update('#folder', 11)
  assert.equal(disabled.pending.length, 0)
  assert.ok(disabled.field('#folder').classes.has('hidden'))
})

test('Rapid folder changes ignore both stale successful responses and stale errors', async () => {
  for (const fail of [false, true]) {
    const ui = harness()
    const old = ui.preview.update('#form', 11)
    const latest = ui.preview.update('#form', 12)
    ui.pending[1].resolve(response(30))
    await latest
    if (fail) ui.pending[0].reject(new Error('Offline'))
    else ui.pending[0].resolve(response(90))
    await old
    assert.equal(ui.field('#form').children[0].value, 'Every 30 days')
  }
})

test('Leaving a folder or clearing the form prevents an in-flight response from restoring the notice', async () => {
  const ui = harness()
  const request = ui.preview.update('#form', 11)
  ui.preview.clear('#form')
  ui.pending[0].resolve(response())
  await request
  assert.ok(ui.field('#form').classes.has('hidden'))
  assert.equal(ui.field('#form').children.length, 0)
})

test('Creation estimates and existing expired dates are distinguished; labels render as text', async () => {
  const ui = harness()
  const create = ui.preview.update('#form', 11, [], true)
  assert.equal(ui.pending[0].data.context, 'create')
  ui.pending[0].resolve(response(90, [{ due_date: '2026-12-01', expired: false }], true))
  await create
  assert.equal(ui.field('#form').children.at(-1).value, 'Estimate')
  const move = ui.preview.update('#form', 12, [1, 2])
  ui.pending[1].resolve(response(10, [
    { label: '<img src=x onerror=alert(1)>', due_date: '2020-01-01', expired: true },
    { label: 'Missing history', due_date: '', expired: false }
  ]))
  await move
  assert.ok(ui.field('#form').classes.has('alert-warning'))
  assert.equal(ui.field('#form').children[2].value, '<img src=x onerror=alert(1)> — Due 2020-01-01 Already expired')
  assert.equal(ui.field('#form').children[3].value, 'Missing history — Unknown date')
  assert.equal(ui.field('#form').children.at(-1).value, 'Password age preserved')
})

test('Moves wait for the policy, allow cancellation, and stop if the preview fails', async () => {
  const ui = harness()
  ui.answer(false)
  const cancelled = ui.preview.confirmMove(11, [1])
  assert.equal(ui.prompts.length, 0)
  ui.pending[0].resolve(response(90, [{ due_date: '2020-01-01', expired: true }]))
  assert.equal(await cancelled, false)
  assert.match(ui.prompts[0], /Already expired/)
  const failed = ui.preview.confirmMove(11, [1])
  ui.pending[1].reject(new Error('Offline'))
  assert.equal(await failed, false)
  assert.deepEqual(ui.errors, ['Unavailable'])
  const denied = ui.preview.confirmMove(12, [1])
  ui.pending[2].resolve({ error: true })
  assert.equal(await denied, false)
})

test('Moving to a folder without a renewal period adds no confirmation', async () => {
  const ui = harness()
  const moved = ui.preview.confirmMove(11, [1])
  ui.pending[0].resolve(response(0))
  assert.equal(await moved, true)
  assert.equal(ui.prompts.length, 0)
  const disabled = harness(false)
  assert.equal(await disabled.preview.confirmMove(11, [1]), true)
  assert.equal(disabled.pending.length, 0)
})

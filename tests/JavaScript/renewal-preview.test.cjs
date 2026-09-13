const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

const source = readFileSync(join(__dirname, '../../public/assets/js/renewal-preview.js'), 'utf8')

test('Item policy controls reset between items and preview unsaved enabled and disabled values', () => {
  const template = readFileSync(join(__dirname, '../../app/pages/items.js.php'), 'utf8')
  const start = template.indexOf('function setItemRenewalPeriod(')
  const end = template.indexOf('function refreshItemFolderTopRules(', start)
  assert.ok(start >= 0 && end > start)
  const fields = new Map()
  const calls = []
  let handler
  let timer
  let itemId = 0
  const $ = selector => {
    if (!fields.has(selector)) fields.set(selector, {
      value: '', properties: {},
      val(value) { if (arguments.length === 0) return this.value; this.value = value; return this },
      prop(name, value) { if (arguments.length === 1) return this.properties[name]; this.properties[name] = value; return this },
      on(events, callback) { handler = callback; return this }
    })
    return fields.get(selector)
  }
  const context = {
    $, store: { get: () => ({ id: itemId }) }, userDidAChange: false,
    tpRenewal: { update: (...args) => calls.push(args) },
    clearTimeout: () => { timer = null }, setTimeout: callback => { timer = callback }
  }
  vm.runInNewContext(template.slice(start, end), context)
  const enabled = $('#form-item-renewal-enabled')
  const period = $('#form-item-renewal-period')
  $('#form-item-folder').val(11)
  context.setItemRenewalPeriod(30)
  assert.equal(enabled.prop('checked'), true)
  assert.equal(period.val(), 30)
  assert.equal(period.prop('disabled'), false)
  context.setItemRenewalPeriod(0)
  assert.equal(enabled.prop('checked'), false)
  assert.equal(period.val(), 90)
  assert.equal(period.prop('disabled'), true)
  enabled.prop('checked', true)
  period.val('45')
  handler()
  assert.equal(period.prop('required'), true)
  assert.equal(context.userDidAChange, true)
  timer()
  assert.deepEqual(JSON.parse(JSON.stringify(calls.pop())), ['#form-item-renewal-notice', 11, [], true, '45'])
  itemId = 21
  enabled.prop('checked', false)
  handler()
  timer()
  assert.equal(period.prop('required'), false)
  assert.equal(period.prop('disabled'), true)
  assert.deepEqual(JSON.parse(JSON.stringify(calls.pop())), ['#form-item-renewal-notice', 11, [21], false, 0])
})

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
    effective: 'Effective #days# days', source_item: 'Individual policy', source_folder: 'Folder policy', source_none: 'No item deadline',
    due: 'Due #date#', estimate: 'Estimate', existing: 'Password age preserved', expired: 'Already expired',
    unknown: 'Unknown date', unavailable: 'Unavailable', loading: 'Loading', move_confirm: 'Move?',
    badge_scheduled: 'Expiration', badge_soon: 'Expiring soon', badge_expired: 'Expired', badge_unknown: 'Renewal enabled'
  } })
  return { preview, pending, prompts, errors, field: $, answer(value) { answer = value } }
}

function response(days = 90, items = [], creation = false) {
  return { error: false, enabled: true, days, items: items.map(item => ({ days, source: days ? 'folder' : 'none', ...item })), creation }
}

test('Renewal badges distinguish all active states and keep lists focused on urgent deadlines', () => {
  const ui = harness()
  const policy = { days: 30, due_date: '2026-10-01' }
  for (const [state, colour] of [['scheduled', 'info'], ['soon', 'warning'], ['expired', 'danger'], ['unknown', 'secondary']]) {
    const badge = ui.preview.badgeHtml({ ...policy, state })
    assert.ok(badge.includes('badge-' + colour))
    assert.ok(badge.includes('2026-10-01'))
    assert.ok(badge.includes('Effective 30 days'))
    const marker = ui.preview.badgeHtml({ ...policy, state }, true)
    assert.equal(marker !== '', state === 'soon' || state === 'expired')
    if (marker) {
      assert.ok(marker.startsWith('<i class="fa-solid '))
      assert.ok(marker.includes(state === 'soon' ? 'fa-hourglass-half' : 'fa-calendar-xmark'))
      assert.ok(marker.includes('mr-1 infotip tp-item-renewal-marker text-' + colour))
      assert.ok(marker.includes('title="' + (state === 'soon' ? 'Expiring soon' : 'Expired') + ' — Due 2026-10-01 Effective 30 days"'))
      assert.equal(marker.includes('<span'), false)
    }
  }
  assert.equal(ui.preview.badgeHtml(null), '')
  assert.equal(ui.preview.badgeHtml({ ...policy, state: 'none' }), '')
  assert.equal(ui.preview.badgeHtml({ ...policy, state: '__proto__' }), '')
  const unknown = ui.preview.badgeHtml({ days: 30, due_date: '', state: 'unknown' })
  assert.ok(unknown.includes('Renewal enabled'))
  assert.ok(unknown.includes('Unknown date'))
  for (const listMarker of [false, true]) {
    const hostile = ui.preview.badgeHtml({ days: '\"><img src=x>', due_date: '<script>alert(1)</script>', state: 'soon' }, listMarker)
    assert.equal(hostile.includes('<img'), false)
    assert.equal(hostile.includes('<script>'), false)
  }
})

test('Opening another item clears the previous renewal badge while its details load', () => {
  const template = readFileSync(join(__dirname, '../../app/pages/items.js.php'), 'utf8')
  const start = template.indexOf('function resetItemDetailSkeleton(')
  const end = template.indexOf('function resetEditFormSkeleton(', start)
  assert.ok(start >= 0 && end > start)
  const ui = harness()
  const field = ui.field('#card-item-renewal-badge')
  field.removeClass('hidden').text('Old deadline')
  const $ = selector => selector === '#card-item-renewal-badge' ? field : {
    html() { return this }, addClass() { return this }, removeClass() { return this },
    removeAttr() { return this }, remove() { return this }, empty() { return this }
  }
  const context = { $ }
  vm.runInNewContext(template.slice(start, end), context)
  context.resetItemDetailSkeleton()
  assert.equal(field.value, '')
  assert.equal(field.classes.has('hidden'), true)
})

test('Folder policies include empty folders and explicit zero; individual policies work with folder expiration off', async () => {
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
  const individual = disabled.preview.update('#form', 11, [1])
  disabled.pending[0].resolve(response(0, [{ days: 30, source: 'item', due_date: '2026-12-01', expired: false }]))
  await individual
  assert.ok(disabled.field('#form').children.some(line => line.value.includes('Effective 30 days')))
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
  assert.ok(ui.field('#form').children.some(line => line.value === 'Missing history — Unknown date'))
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
  const individual = disabled.preview.confirmMove(11, [1])
  disabled.pending[0].resolve(response(0, [{ days: 30, source: 'item', expired: false, due_date: '2026-12-01' }]))
  assert.equal(await individual, true)
  assert.equal(disabled.prompts.length, 1)
})

test('Unsaved policy changes and copy previews send the intended values independently of folder settings', async () => {
  const ui = harness(false)
  const draft = ui.preview.update('#form', 11, [1], false, 30)
  assert.equal(ui.pending[0].data.renewal_period, 30)
  ui.pending[0].resolve(response(0, [{ days: 30, source: 'item', due_date: '2026-12-01' }]))
  await draft
  const copy = ui.preview.update('#copy', 11, [1], false, null, true)
  assert.equal(ui.pending[1].data.context, 'copy')
  assert.equal(Object.hasOwn(ui.pending[1].data, 'renewal_period'), false)
  ui.pending[1].resolve(response(0, [{ days: 30, source: 'item', due_date: '2026-12-01' }], true))
  await copy
  assert.equal(ui.field('#copy').children.at(-1).value, 'Estimate')
})

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      file-integrity-health.test.cjs
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

const source = readFileSync(join(__dirname, '../../app/pages/utilities.health.js.php'), 'utf8')
const start = source.indexOf('function tpRenderFileIntegrity(')
const end = source.indexOf('function tpStartFileIntegrityScan(', start)
assert.ok(start >= 0 && end > start, 'Missing shipped file integrity renderer')

function harness() {
  const elements = new Map()
  const calls = { issues: 0, polls: 0 }
  const translations = {
    file_integrity_scan_failed: 'Le scan a échoué.',
    file_integrity_lock_probe_failed: 'Impossible de vérifier le verrou. <em>Diagnostic</em>',
    file_integrity_reference_missing: 'Référence absente.',
    file_integrity_reference_unreadable: 'Référence illisible.',
    file_integrity_report_invalid: 'Rapport invalide.',
    file_integrity_never_run: 'Pas encore exécuté.',
    seconds: 'secondes'
  }
  const $ = selector => {
    if (!elements.has(selector)) {
      elements.set(selector, {
        visible: false, textValue: '', htmlValue: '', properties: {},
        toggle(value) { this.visible = Boolean(value); return this },
        text(value) { this.textValue = String(value); return this },
        html(value) { this.htmlValue = String(value); return this },
        prop(name, value) { this.properties[name] = value; return this },
        removeClass() { return this },
        addClass() { return this }
      })
    }
    return elements.get(selector)
  }
  const context = {
    $, TP_HEALTH_L10N: translations, tpFileIntegrityPollTimer: null,
    tpFileIntegrityStatusBadge: () => 'status',
    tpLaprFormatTimestamp: () => 'timestamp',
    tpLoadFileIntegrityIssues: () => { calls.issues++ },
    tpScheduleFileIntegrityPoll: () => { calls.polls++ }
  }
  vm.runInNewContext(source.slice(start, end), context)
  return {
    $, calls, translations,
    render: summary => context.tpRenderFileIntegrity({ overview: { file_integrity: summary } })
  }
}

test('A failed lock probe shows its translated message without claiming the saved scan failed', () => {
  const h = harness()
  h.render({
    status: 'error', lock_probe_failed: true, running: false, has_result: true,
    counts: { checked: 42 }, last_error: 'An older scanner diagnostic'
  })
  const error = h.$('#health-file-integrity-error')
  assert.equal(error.visible, true)
  assert.equal(error.textValue, h.translations.file_integrity_lock_probe_failed)
  assert.equal(error.htmlValue, '', 'Translated diagnostic is inserted as text, not HTML')
  assert.equal(h.$('#health-file-integrity-results').visible, true)
  assert.equal(h.$('#health-file-integrity-checked').textValue, '42')
  assert.equal(h.$('#health-file-integrity-scan-btn').properties.disabled, false)
  assert.equal(h.calls.issues, 1, 'The saved report remains available')
})

test('The probe diagnostic also works before the first scan, without inventing results', () => {
  const h = harness()
  h.render({ status: 'error', lock_probe_failed: true, has_result: false })
  assert.equal(h.$('#health-file-integrity-error').textValue, h.translations.file_integrity_lock_probe_failed)
  assert.equal(h.$('#health-file-integrity-results').visible, false)
  assert.equal(h.calls.issues, 0)
})

test('A recovered probe removes its transient banner on the next refresh', () => {
  const h = harness()
  h.render({ status: 'error', lock_probe_failed: true, has_result: true })
  h.render({ status: 'success', lock_probe_failed: false, has_result: true })
  assert.equal(h.$('#health-file-integrity-error').visible, false)
  assert.equal(h.$('#health-file-integrity-error').textValue, '')
  assert.equal(h.$('#health-file-integrity-results').visible, true)
})

test('An active scanner keeps the button disabled and the error banner hidden', () => {
  const h = harness()
  h.render({ status: 'running', running: true, has_result: true, lock_probe_failed: false })
  assert.equal(h.$('#health-file-integrity-error').visible, false)
  assert.equal(h.$('#health-file-integrity-running').visible, true)
  assert.equal(h.$('#health-file-integrity-scan-btn').properties.disabled, true)
  assert.equal(h.calls.polls, 1)
})

for (const [flag, key] of [
  ['reference_missing', 'file_integrity_reference_missing'],
  ['reference_unreadable', 'file_integrity_reference_unreadable'],
  ['report_invalid', 'file_integrity_report_invalid']
]) {
  test(`${flag} keeps its existing translated scan-error presentation`, () => {
    const h = harness()
    h.render({ status: 'error', [flag]: true, last_error: 'Ignored fallback' })
    assert.equal(h.$('#health-file-integrity-error').visible, true)
    assert.equal(h.$('#health-file-integrity-error').textValue,
      h.translations.file_integrity_scan_failed + ' ' + h.translations[key])
  })
}

test('Real scanner failures retain their prefix and saved diagnostic', () => {
  const h = harness()
  h.render({ status: 'error', lock_probe_failed: false, last_error: 'Scanner diagnostic' })
  assert.equal(h.$('#health-file-integrity-error').visible, true)
  assert.equal(h.$('#health-file-integrity-error').textValue,
    h.translations.file_integrity_scan_failed + ' Scanner diagnostic')
})

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (C) 2009-2026 Teampass.net
 * This file is part of TeamPass, distributed under the GNU GPL version 3.
 * See <https://www.gnu.org/licenses/>. Provided WITHOUT ANY WARRANTY.
 */
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

const source = readFileSync(join(__dirname, '../../app/pages/statistics.js.php'), 'utf8')
const start = source.indexOf('    function renderLicenceStatistics(')
const end = source.indexOf('    // Honour a #hash', start)
assert.ok(start >= 0 && end > start, 'Missing licence statistics renderer')
const renderer = source.slice(start, end)
assert.ok(!renderer.includes('<?php'))

const formats = {
  'd/m/Y': '16/06/2027', 'm/d/Y': '06/16/2027', 'd-M-Y': '16-Jun-2027',
  'd/m/y': '16/06/27', 'm/d/y': '06/16/27', 'd-M-y': '16-Jun-27',
  'd-m-y': '16-06-27', 'Y-m-d': '2027-06-16'
}
const shortFormats = {
  'd/m/Y': '16/06', 'm/d/Y': '06/16', 'd-M-Y': '16-Jun',
  'd/m/y': '16/06', 'm/d/y': '06/16', 'd-M-y': '16-Jun',
  'd-m-y': '16-06', 'Y-m-d': '06-16'
}

function harness(format = 'd/m/Y') {
  const nodes = new Map()
  function element(selector) {
    const state = { text: '', children: [] }
    const adapter = {
      state,
      text(value) { state.text = value; return adapter },
      append(child) { state.children.push(child.state); return adapter },
      appendTo(parent) { parent.state.children.push(state); return adapter },
      empty() { state.children = []; return adapter },
      hide() { return adapter }, show() { return adapter }, toggle() { return adapter },
      addClass() { return adapter }, removeClass() { return adapter }, toggleClass() { return adapter },
      hasClass() { return false }
    }
    if (!selector.startsWith('<')) nodes.set(selector, adapter)
    return adapter
  }
  const canvas = { parentElement: { style: { height: '260px' } }, getContext() { return {} }, setAttribute() {} }
  const context = {
    $: selector => nodes.get(selector) || element(selector),
    document: { getElementById() { return canvas } },
    tpLicenceDateFormat: format, tpLicenceChart: null,
    tpLicenceText: { since: 'History since #date#', live: '(live)', aria: '#used# / #quota#', quotaShare: '#percent#' },
    tpOpsPercent: (used, quota) => used / quota * 100,
    ensureChartJs: callback => callback(),
    Chart: function (_, config) { this.config = config; this.destroy = () => {} }
  }
  vm.createContext(context)
  vm.runInContext(renderer, context)
  // Date rendering must not delegate the instance's setting to the browser's locale.
  vm.runInContext("Date.prototype.toLocaleDateString = function() { throw new Error('Browser locale used') }", context)
  return { context, node: selector => nodes.get(selector).state }
}

function payload(expiration = '2027-06-16 23:59:59') {
  return {
    server_online: true, licence_status: 'VALID', expiration_date: expiration,
    consumed: 1, max_users: 10, refused: 0,
    history: { today: '2027-06-16', first_snapshot: '2027-06-16', points: [
      { date: '2027-06-15', seats_used: null, seats_quota: null, active_users: null, live: false },
      { date: '2027-06-16', seats_used: 1, seats_quota: 10, active_users: 1, live: true }
    ] }
  }
}

test('every date format offered in Settings is covered', () => {
  const options = readFileSync(join(__dirname, '../../app/pages/options.php'), 'utf8')
    .match(/<select[^>]+id='date_format'>[\s\S]*?<\/select>/)[0]
  const offered = [...options.matchAll(/<option value="([^"]+)"/g)].map(match => match[1])
  assert.deepEqual(offered.sort(), Object.keys(formats).sort())
})

for (const [format, expected] of Object.entries(formats)) {
  test(format + ' applies to full-date labels and compact chart ticks', () => {
    const h = harness(format)
    const data = payload()
    const original = JSON.stringify(data)
    h.context.renderLicenceStatistics(data)
    assert.equal(h.node('#tp-licence-kpi-expiry').text, expected)
    const chart = h.context.tpLicenceChart.config
    assert.equal(chart.options.scales.xAxes[0].ticks.callback('2027-06-16'), shortFormats[format])
    assert.equal(chart.options.tooltips.callbacks.title([{ index: 1 }]), expected + ' (live)')
    assert.equal(h.node('#tp-licence-seats-table tbody').children[1].children[0].text, expected)
    assert.equal(h.node('#tp-licence-since').text, 'History since ' + expected)
    assert.equal(JSON.stringify(data), original, 'Display formatting must preserve raw server dates')
    assert.deepEqual(Array.from(chart.data.labels), ['2027-06-15', '2027-06-16'])
  })
}

test('missing or invalid expiry dates render an unavailable value', () => {
  const h = harness()
  for (const value of [null, undefined, '', 'invalid', '2027-02-29', '2027-13-16', '2027-06-00', '2027-06-31', '<img>']) {
    const data = payload()
    data.expiration_date = value
    h.context.renderLicenceStatistics(data)
    assert.equal(h.node('#tp-licence-kpi-expiry').text, '—', String(value))
  }
})

test('date-only and timestamp expiries retain their calendar day across browser timezones and DST', () => {
  const previous = process.env.TZ
  try {
    for (const zone of ['Europe/Paris', 'America/Los_Angeles', 'Pacific/Kiritimati', 'UTC']) {
      process.env.TZ = zone
      const h = harness()
      for (const [value, expected] of [
        ['2027-06-16', '16/06/2027'], ['2027-06-16 23:59:59', '16/06/2027'],
        ['2027-06-16T23:59:59Z', '16/06/2027'], ['2028-02-29', '29/02/2028'],
        ['2026-03-29', '29/03/2026'], ['2026-10-25', '25/10/2026']
      ]) assert.equal(h.context.tpLicenceFormatDate(value), expected, zone + ': ' + value)
    }
  } finally {
    if (previous === undefined) delete process.env.TZ
    else process.env.TZ = previous
  }
})

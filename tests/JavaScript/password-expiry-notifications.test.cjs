/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (C) 2009-2026 Teampass.net
 * Distributed under the GNU GPL version 3, WITHOUT ANY WARRANTY.
 * See <https://www.gnu.org/licenses/>.
 */
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

const source = readFileSync(join(__dirname, '../../app/core/notification-center.js.php'), 'utf8')
const start = source.indexOf('var RENDERERS = {')
const end = source.indexOf('function escapeText(', start)
assert.ok(start >= 0 && end > start, 'Missing notification renderers')

function renderer(now) {
  const context = vm.createContext({
    L: {
      passwordExpired: 'Your TeamPass password has expired',
      passwordExpiresTomorrow: 'Your TeamPass password expires tomorrow',
      passwordExpiring: 'Your TeamPass password expires in %s days'
    }
  })
  vm.runInContext('Date.now = () => ' + Date.parse(now), context)
  vm.runInContext(source.slice(start, end), context)
  return context.RENDERERS.local_password_expiring.detail
}

const expiresAt = Date.parse('2026-11-15T03:15:00Z') / 1000

test('weekly warnings for the same expiry keep their original distinct countdowns', () => {
  const detail = renderer('2026-11-08T03:15:00Z')
  const firstWarning = { days_remaining: 14, threshold: 14, expires_at: expiresAt }
  const secondWarning = { days_remaining: 7, threshold: 7, expires_at: expiresAt }

  assert.equal(detail(firstWarning), 'Your TeamPass password expires in 14 days')
  assert.equal(detail(secondWarning), 'Your TeamPass password expires in 7 days')
})

test('reading a notification later does not rewrite its historical message', () => {
  const warning = { days_remaining: 7, threshold: 7, expires_at: expiresAt }
  for (const now of ['2026-11-08T03:15:00Z', '2026-11-14T03:15:00Z', '2026-11-16T03:15:00Z']) {
    assert.equal(renderer(now)(warning), 'Your TeamPass password expires in 7 days', now)
  }
})

test('a warning delivered between milestones uses the actual days at delivery', () => {
  const detail = renderer('2026-11-08T03:15:00Z')
  assert.equal(detail({ days_remaining: 8, threshold: 14, expires_at: expiresAt }),
    'Your TeamPass password expires in 8 days')
})

test('tomorrow and expired messages use the stored countdown with or without an expiry timestamp', () => {
  const detail = renderer('2026-11-08T03:15:00Z')
  for (const expiry of [expiresAt, undefined]) {
    assert.equal(detail({ days_remaining: 1, expires_at: expiry }), 'Your TeamPass password expires tomorrow')
    assert.equal(detail({ days_remaining: 0, expires_at: expiry }), 'Your TeamPass password has expired')
  }
})

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

// PHP blocks echo JSON values: `null` keeps the JavaScript parseable.
const source = readFileSync(join(__dirname, '../../app/core/notification-center.js.php'), 'utf8')
  .replace(/<\?php[\s\S]*?\?>/g, 'null')

/**
 * Extract the RENDERERS declaration, whatever its keyword (var, let or const)
 * and whatever follows it. The first closing brace at which the slice parses
 * is the end of the object literal: braces inside strings, comments or nested
 * objects leave the slice unparseable, so they cannot end it early.
 */
function renderersDeclaration() {
  const match = /\b(?:var|let|const)\s+RENDERERS\s*=\s*\{/.exec(source)
  assert.ok(match, 'Missing RENDERERS declaration in notification-center.js.php')
  for (let end = source.indexOf('}', match.index); end !== -1; end = source.indexOf('}', end + 1)) {
    const candidate = source.slice(match.index, end + 1)
    try {
      new vm.Script(candidate)
      return candidate
    } catch (error) {
      if (!(error instanceof SyntaxError)) throw error
    }
  }
  assert.fail('Cannot find the end of the RENDERERS declaration')
}

const declaration = renderersDeclaration()

/**
 * Run the shipped renderer in a sandbox whose clock is frozen at `now`, for
 * Date.now() and new Date() alike, so a renderer reading the time in any way
 * gets the test's clock and never the machine's.
 */
function renderer(now) {
  const fixed = Date.parse(now)
  const context = vm.createContext({
    L: {
      passwordExpired: 'Your TeamPass password has expired',
      passwordExpiresTomorrow: 'Your TeamPass password expires tomorrow',
      passwordExpiring: 'Your TeamPass password expires in %s days'
    }
  })
  vm.runInContext(`(() => {
    const RealDate = Date
    class FixedDate extends RealDate {
      constructor (...args) { super(...(args.length > 0 ? args : [${fixed}])) }
      static now () { return ${fixed} }
    }
    globalThis.Date = FixedDate
  })()`, context)
  assert.equal(vm.runInContext('Date.now()', context), fixed)
  assert.equal(vm.runInContext('new Date().getTime()', context), fixed)

  // The completion value reaches a let/const binding, which never becomes a
  // property of the sandbox global.
  const renderers = vm.runInContext(declaration + '\nRENDERERS', context)
  assert.ok(renderers.local_password_expiring, 'Missing local_password_expiring renderer')
  return renderers.local_password_expiring.detail
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

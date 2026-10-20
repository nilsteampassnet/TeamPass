/* TeamPass - Copyright (c) 2009-2026 TeamPass.net. SPDX-License-Identifier: GPL-3.0-only. */
'use strict'

const test = require('node:test')
const assert = require('node:assert/strict')
const pairing = require('../../public/js/device-pairing.js')

test('a response from a replaced or closed invitation cannot update the current device controls', () => {
  const guard = pairing.createAttemptGuard()
  const first = guard.next()
  assert.equal(guard.bind(first, 'first-invitation'), true)
  const next = guard.next()
  assert.equal(guard.bind(next, 'new-invitation'), true)
  assert.equal(guard.current(first, 'first-invitation'), false)
  assert.equal(guard.bind(first, 'late-created-invitation'), false)
  assert.equal(guard.current(next, 'new-invitation'), true)
  assert.equal(guard.current(next, 'different-invitation'), false)
  guard.next()
  assert.equal(guard.current(next, 'new-invitation'), false)
})

test('late polling cannot undo approval, delivery or a terminal outcome', () => {
  assert.equal(pairing.canAdvance('approved', 'awaiting_approval'), false)
  assert.equal(pairing.canAdvance('delivered', 'approved'), false)
  assert.equal(pairing.canAdvance('awaiting_approval', 'completed'), true)
  assert.equal(pairing.canAdvance('completed', 'expired'), false)
  assert.equal(pairing.canAdvance('cancelled', 'approved'), false)
  assert.equal(pairing.canAdvance('expired', 'completed'), false)
  assert.equal(pairing.canAdvance('awaiting_scan', 'invented-state'), false)
})

test('device search compares the raw label actually rendered with text(), including punctuation', () => {
  for (const [label, query] of [['R&D', 'r&d'], ["O'Brien", "o'brien"], ['A "quoted" <device>', '"quoted"'], ['A "quoted" <device>', '<device>']]) {
    assert.equal(pairing.matchesLabel(label, query), true)
  }
  assert.equal(pairing.matchesLabel('R&D', 'amp'), false)
  assert.equal(pairing.matchesLabel('A "quoted" <device>', 'quot;'), false)
  assert.equal(pairing.matchesLabel('A "quoted" <device>', 'lt;'), false)
  assert.equal(pairing.matchesLabel('R&D', '  '), true)
})

test('countdown rounds up the remaining second and never displays a negative duration', () => {
  assert.equal(pairing.remainingSeconds(180000, 0), 180)
  assert.equal(pairing.remainingSeconds(180000, 179001), 1)
  assert.equal(pairing.remainingSeconds(180000, 180000), 0)
  assert.equal(pairing.remainingSeconds(180000, 999999), 0)
})

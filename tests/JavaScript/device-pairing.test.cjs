/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      device-pairing.test.cjs
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */
'use strict'

const test = require('node:test')
const assert = require('node:assert/strict')
const pairing = require('../../public/assets/js/device-pairing.js')

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

test('repeating an inventory search cannot accept a response from its earlier occurrence', () => {
  const guard = pairing.createAttemptGuard()
  const original = guard.next()
  guard.bind(original, 'R&D')
  guard.next() // Typing invalidates the in-flight request before the debounce expires.
  assert.equal(guard.current(original, 'R&D'), false)
  const repeated = guard.next()
  guard.bind(repeated, 'R&D')
  assert.equal(guard.current(original, 'R&D'), false)
  assert.equal(guard.current(repeated, 'R&D'), true)
  assert.equal(guard.current(repeated, 'different search'), false)
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

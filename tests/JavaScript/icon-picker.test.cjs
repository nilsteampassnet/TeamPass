/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */
const assert = require('node:assert/strict')
const { test } = require('node:test')
const { IconCatalog } = require('../../public/assets/js/icon-picker.js')

// Rows as get_icon_catalog sends them: [name, free style codes, search terms]
const rows = [
  ['envelope', 'sr', 'e-mail email letter mail message'],
  ['key', 's', 'access lock password secret'],
  ['lock', 's', 'password protect secure'],
  ['mailchimp', 'b', ''],
  ['voicemail', 's', 'message phone'],
  ['github', 'b', 'octocat'],
  ['credit-card', 'sr', 'buy payment'],
  ['mastodon', 'b', 'message'],
  ['bad" onmouseover="x', 's', '']
]
const entries = IconCatalog.entries(rows)
const search = query => IconCatalog.search(entries, IconCatalog.tokens(query)).map(entry => entry.classes)

test('one entry per free style, solid then regular then brands, unsafe names dropped', () => {
  assert.deepEqual(entries.map(entry => entry.classes), [
    'fa-solid fa-envelope', 'fa-solid fa-key', 'fa-solid fa-lock', 'fa-solid fa-voicemail', 'fa-solid fa-credit-card',
    'fa-regular fa-envelope', 'fa-regular fa-credit-card',
    'fa-brands fa-mailchimp', 'fa-brands fa-github', 'fa-brands fa-mastodon'
  ])
})

test('stored values designate the same tile whatever their spelling', () => {
  assert.equal(IconCatalog.normalize('fas fa-key'), 'fa-solid fa-key')
  assert.equal(IconCatalog.normalize('fa-key'), 'fa-solid fa-key')
  assert.equal(IconCatalog.normalize('fab fa-github fa-fw'), 'fa-brands fa-github')
  assert.equal(IconCatalog.normalize('fa-regular fa-envelope'), 'fa-regular fa-envelope')
  assert.equal(IconCatalog.normalize(''), '')
  assert.equal(IconCatalog.normalize('fas'), '')
})

test('values without a style class render as solid, as they did before the picker', () => {
  assert.equal(IconCatalog.renderClasses('fa-key'), 'fas fa-key')
  assert.equal(IconCatalog.renderClasses('fa-brands fa-github'), 'fa-brands fa-github')
  assert.equal(IconCatalog.renderClasses('far fa-envelope'), 'far fa-envelope')
  assert.equal(IconCatalog.renderClasses('  '), '')
})

test('the Font Awesome prefixes typed by those who know them are not search words', () => {
  assert.deepEqual(IconCatalog.tokens('fa-solid fa-key'), ['key'])
  assert.deepEqual(IconCatalog.tokens('  Credit   CARD '), ['credit', 'card'])
  assert.deepEqual(IconCatalog.tokens('fas brands'), [])
})

test('a Font Awesome term finds the icons named otherwise', () => {
  assert.deepEqual(search('password'), ['fa-solid fa-key', 'fa-solid fa-lock'])
})

test('an exact term ranks above a name that merely contains the word', () => {
  const results = search('mail')
  assert.equal(results[0], 'fa-brands fa-mailchimp')
  assert.ok(results.indexOf('fa-solid fa-envelope') < results.indexOf('fa-solid fa-voicemail'))
})

test('every word must match, and a word of a compound name counts as a name match', () => {
  assert.deepEqual(search('card buy'), ['fa-solid fa-credit-card', 'fa-regular fa-credit-card'])
  assert.deepEqual(search('card octocat'), [])
})

test('icons come before brands on equal scores, then the shortest name', () => {
  assert.deepEqual(search('message'), [
    'fa-solid fa-envelope', 'fa-regular fa-envelope', 'fa-solid fa-voicemail', 'fa-brands fa-mastodon'
  ])
})

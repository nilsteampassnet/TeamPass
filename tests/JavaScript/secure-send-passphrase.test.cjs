/**
 * Teampass - a collaborative passwords manager.
 * Copyright 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See https://www.gnu.org/licenses/ and the licenses directory.
 */
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const { test } = require('node:test')
const vm = require('node:vm')

const template = readFileSync(join(__dirname, '../../app/core/otv.php'), 'utf8')
// The script lives in a nowdoc so that otv.php can authorize it by its SHA-256 digest.
const script = template.match(/\$passphraseToggleScript = [^\n]*<<<'JS'\n([\s\S]*?)\nJS\)/)
assert.ok(script, 'Missing recipient passphrase script')

function recipient({ value = '', hasPassphrase = true } = {}) {
  const input = { type: 'password', value }
  const classes = new Set(['fa-solid', 'fa-eye'])
  const attributes = { 'aria-pressed': 'false' }
  const handlers = new Map()
  const button = {
    hidden: true,
    querySelector: () => ({ classList: { toggle(name, enabled) {
      if (enabled) classes.add(name)
      else classes.delete(name)
    } } }),
    setAttribute: (name, value) => { attributes[name] = value },
    addEventListener: (name, handler) => handlers.set(name, handler)
  }
  // The script gets only its DOM controls: network, storage and submission are unavailable.
  vm.runInNewContext(script[1], {
    document: { getElementById: id => hasPassphrase
      ? { passphrase: input, 'toggle-passphrase': button }[id] ?? null
      : null }
  })
  return { input, button, attributes, classes, handlers, click: () => handlers.get('click')() }
}

test('the phrase stays masked until the recipient explicitly reveals it', () => {
  const h = recipient({ value: 'A test phrase' })
  assert.equal(h.button.hidden, false)
  assert.equal(h.input.type, 'password')
  assert.equal(h.attributes['aria-pressed'], 'false')
  h.click()
  assert.equal(h.input.type, 'text')
  assert.equal(h.attributes['aria-pressed'], 'true')
  assert.equal(h.classes.has('fa-eye-slash'), true)
  assert.equal(h.classes.has('fa-eye'), false)
  h.click()
  assert.equal(h.input.type, 'password')
  assert.equal(h.attributes['aria-pressed'], 'false')
  assert.equal(h.classes.has('fa-eye'), true)
  assert.equal(h.classes.has('fa-eye-slash'), false)
})

test('toggling preserves spaces, Unicode, quotes, angle brackets and literal entities', () => {
  for (const value of ['', '  phrase with spaces  ', 'été 中文 🔒', 'R&D O\'Brien "<secret>" &amp; &quot;']) {
    const h = recipient({ value })
    for (let i = 0; i < 4; i++) {
      h.click()
      assert.equal(h.input.value, value)
    }
  }
})

test('the recipient can edit a visible phrase and mask the updated value', () => {
  const h = recipient({ value: 'before' })
  h.click()
  h.input.value = 'after correction'
  h.click()
  assert.equal(h.input.type, 'password')
  assert.equal(h.input.value, 'after correction')
})

test('unprotected, unavailable and already revealed pages need no passphrase controls', () => {
  const h = recipient({ hasPassphrase: false })
  assert.equal(h.handlers.size, 0)
  assert.equal(h.button.hidden, true)
})

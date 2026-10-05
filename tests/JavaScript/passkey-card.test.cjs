const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

// Execute the shipped passkey card renderer. Its labels come from third-party sites (relying
// party name, account name), so it is an XSS sink by construction.
const source = readFileSync(join(__dirname, '../../app/pages/items.js.php'), 'utf8').replace(/\r\n/g, '\n')
const start = source.indexOf('    function renderItemWebauthn(credentials) {')
const end = source.indexOf('    function laprItemListMarkersHtml(lapr) {', start)
assert.ok(start >= 0 && end > start, 'Missing renderItemWebauthn()')
const rendererSource = source
  .slice(start, end)
  .replace(/<\?php echo json_encode\(\$lang->get\('(\w+)'\), JSON_UNESCAPED_UNICODE\); \?>/g, (match, key) => JSON.stringify('[' + key + ']'))
assert.ok(!rendererSource.includes('<?php'), 'Unexpected PHP left in the renderer')

const functions = readFileSync(join(__dirname, '../../app/includes/js/functions.js'), 'utf8')
const encoderSource = functions.slice(functions.indexOf('const HTML_ESCAPES'), functions.indexOf('/* Extend String object'))

function render(credentials, canModify = 1) {
  const captured = {}
  const $ = selector => {
    const element = {
      empty() { return element },
      text(value) { captured[selector + ':text'] = value; return element },
      toggleClass(name, state) { captured[selector + ':hidden'] = state; return element },
      html(value) { captured[selector + ':html'] = value; return element },
    }
    return element
  }
  $.each = (list, callback) => list.forEach((value, index) => callback(index, value))
  const context = { $, store: { get: () => ({ user_can_modify: canModify }) }, captured }
  vm.createContext(context)
  vm.runInContext(encoderSource + rendererSource + '\nrenderItemWebauthn(credentials)', Object.assign(context, { credentials }))
  return captured
}

const hostile = {
  id: 11,
  rp_id: 'gitlab.com',
  rp_name: '<img src=x onerror=alert(1)>',
  user_name: 'svc-<b>deploy</b>',
  user_display_name: '"quoted" & \'apos\'',
  created_at: '13/09/2026 18:34',
  created_by: '<script>',
  last_used_at: '',
  last_used_by: '',
}

test('site-provided labels are escaped', () => {
  const html = render([hostile])['#card-item-webauthn-list:html']
  assert.ok(!/<img|<b>|<script/i.test(html))
  assert.ok(html.includes('&lt;img src=x onerror=alert(1)&gt;'))
  assert.ok(html.includes('&quot;quoted&quot; &amp; &#39;apos&#39;'))
  assert.ok(html.includes('[webauthn_never_used]'))
})

test('the card, the copy note and the title badge follow the list', () => {
  const key = '#item-card-webauthn, #form-item-copy-webauthn-note, #card-item-webauthn-title-badge:hidden'
  const shown = render([hostile])
  assert.equal(shown['#card-item-webauthn-badge:text'], 1)
  assert.equal(shown[key], false)

  const hidden = render([])
  assert.equal(hidden[key], true)
})

test('the delete button needs the right to modify the item', () => {
  assert.equal((render([hostile], 1)['#card-item-webauthn-list:html'].match(/delete-webauthn-credential/g) || []).length, 1)
  assert.equal((render([hostile], 0)['#card-item-webauthn-list:html'].match(/delete-webauthn-credential/g) || []).length, 0)
})

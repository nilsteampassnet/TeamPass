const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

// Execute the shipped helper: API clients store plain-text descriptions with real line breaks,
// which collapsed into spaces on the item card and were lost when the editor saved them back.
// The helper lives in items.js.php, generated per request, so no cached functions.js can lack it.
const items = readFileSync(join(__dirname, '../../app/pages/items.js.php'), 'utf8').replace(/\r\n/g, '\n')
const extract = name => {
  const start = items.indexOf('    function ' + name + '(')
  assert.ok(start >= 0, 'Missing ' + name + '()')
  return items.slice(start, items.indexOf('\n    }\n', start) + 7)
}
const encoderSource = extract('tpEscapeHtmlAttribute')
const helperSource = extract('descriptionLineBreaksToHtml')

const context = {}
vm.createContext(context)
vm.runInContext(encoderSource + helperSource + '\nthis.toHtml = descriptionLineBreaksToHtml', context)
const toHtml = context.toHtml

test('plain-text line breaks become <br>', () => {
  assert.equal(toHtml('Test TOTP\nAjout 1\r\nNew password\r\rEnd'), 'Test TOTP<br>Ajout 1<br>New password<br><br>End')
})

test('plain text is escaped, never parsed', () => {
  assert.equal(toHtml('I <3 R&D\n"quoted" \'apos\''), 'I &lt;3 R&amp;D<br>&quot;quoted&quot; &#039;apos&#039;')
})

test('editor markup and single-line text are unchanged', () => {
  assert.equal(toHtml('<p>a</p>\n<p>b</p>'), '<p>a</p>\n<p>b</p>')
  assert.equal(toHtml('x\n<img src=x onerror=alert(1)>'), 'x\n<img src=x onerror=alert(1)>', 'left to DOMPurify, as before')
  assert.equal(toHtml('one line'), 'one line')
  assert.equal(toHtml(''), '')
  assert.equal(toHtml(undefined), '')
})

test('a literal backslash-n is data, not a line break', () => {
  assert.equal(toHtml('DOMAIN\\nicolas'), 'DOMAIN\\nicolas')
})

test('the card and both editors render through the helper, before DOMPurify', () => {
  assert.equal((items.match(/DOMPurify\.sanitize\(\s*descriptionLineBreaksToHtml\(htmlDecode\(data\.description/g) || []).length, 3)
  assert.ok(!/DOMPurify\.sanitize\(\s*htmlDecode\(data\.description/.test(items), 'a description sink skips the helper')
})

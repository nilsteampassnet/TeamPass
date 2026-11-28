/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { test } = require('node:test')
const vm = require('node:vm')

const items = readFileSync(require.resolve('../../app/pages/items.js.php'), 'utf8')
const functions = readFileSync(require.resolve('../../app/includes/js/functions.js'), 'utf8')
const start = items.indexOf("$(document).on('click', '.preview-image'")
const end = items.indexOf('function loadItemHistory(', start)
assert.ok(start >= 0 && end > start, 'The attachment preview click handler must be present')
const preview = items.slice(start, end)
  .replace(/<\?php echo \$session->get\('key'\); \?>/g, 'session-key')
  .replace(/<\?php echo \$lang->get\('([a-z_]+)'\); \?>/g, '$1')
  .replace(/<\?php echo json_encode\(\$lang->get\('close'\), [^\n]+?\?>/g, '"Close"')
const encoder = functions.match(/const HTML_ESCAPES = \{[\s\S]*?function htmlEncode\(str\)\{[\s\S]*?\n\}/)[0]

function clickPreview(response, fileLabel) {
  const modalCalls = []
  const errors = []
  const button = {
    data: (name) => name === 'file-id' ? 42 : undefined,
    attr: (name) => name === 'data-file-label' ? fileLabel : undefined
  }
  const image = { tagName: 'IMG', remove() { this.removed = true } }
  const document = { createElement: (tagName) => {
    assert.equal(tagName, 'img')
    return image
  } }
  let onClick
  let onHidden

  function $(target) {
    if (target === document) return { on: (event, selector, handler) => {
      assert.equal(event, 'click')
      assert.equal(selector, '.preview-image')
      onClick = handler
    } }
    if (target === button) return button
    if (target === '#warningModal') return { one: (event, handler) => {
      assert.equal(event, 'hidden.bs.modal')
      onHidden = handler
    } }
    // jQuery's empty collection represents the absent legacy preview container.
    return { html() {}, attr() {} }
  }

  $.post = (url, request, callback) => {
    assert.equal(url, 'sources/items.queries.php')
    assert.equal(request.type, 'image_preview_preparation')
    assert.equal(request.id, 42)
    assert.equal(request.key, 'session-key')
    callback(response)
  }

  vm.runInNewContext(encoder + '\n' + preview, {
    $,
    document,
    debugJavascript: false,
    prepareExchangedData: (data) => data,
    showModalDialogBox: (...args) => modalCalls.push(args),
    toastr: { remove() {}, info() {}, error: (message) => errors.push(message) }
  })
  onClick.call(button, { preventDefault() {} })
  return { modalCalls, errors, image, onHidden }
}

test('the eye button puts the attachment image directly in the preview dialog', () => {
  const filename = `R&D O'Brien "large" <demo>.png`
  const { modalCalls, image, onHidden } = clickPreview({
    error: false, filename: 'b64:dGVzdA==.png', file_type: 'image/png', file_content: 'aW1hZ2U='
  }, filename)

  assert.equal(modalCalls.length, 1)
  const [modal, title, body] = modalCalls[0]
  assert.equal(modal, '#warningModal')
  assert.equal(body, image)
  assert.equal(title, 'R&amp;D O&#39;Brien &quot;large&quot; &lt;demo&gt;.png')
  assert.equal(image.src, 'data:image/png;base64,aW1hZ2U=')
  assert.equal(image.className, 'img-fluid')
  assert.equal(image.alt, filename)
  onHidden()
  assert.equal(image.removed, true)
})

test('a preview error is reported without opening an image dialog', () => {
  const { modalCalls, errors } = clickPreview({ error: true, message: 'Access denied' })
  assert.deepEqual(modalCalls, [])
  assert.deepEqual(errors, ['Access denied'])
})

test('a preview without a card label uses the filename from the response', () => {
  const { modalCalls, image } = clickPreview({
    error: false, filename: 'legacy.png', file_type: 'image/png', file_content: 'aW1hZ2U='
  })
  assert.equal(modalCalls[0][1], 'legacy.png')
  assert.equal(image.alt, 'legacy.png')
})

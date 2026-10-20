/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License v3.0.
 * See <https://www.gnu.org/licenses/>.
 */
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

const source = readFileSync(join(__dirname, '../../app/pages/kb.js.php'), 'utf8').replace(/\r\n/g, '\n')
function section(name) {
  const start = source.indexOf('    function ' + name + '(')
  const end = source.indexOf('\n    }', start)
  assert.ok(start >= 0 && end > start, 'Missing production function: ' + name)
  return source.slice(start, end + 6)
}

// The browser's text/HTML boundary is mocked; date selection, markup, list data and
// viewer transitions execute the production functions unchanged.
const escape = text => String(text ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')
const decode = text => String(text).replace(/&(?:amp|lt|gt|quot|apos|#(?:x[\da-f]+|\d+));/gi, entity => {
  const named = { '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&apos;': "'" }
  return named[entity.toLowerCase()] ?? String.fromCodePoint(entity[2].toLowerCase() === 'x' ? parseInt(entity.slice(3), 16) : parseInt(entity.slice(2), 10))
})

function harness() {
  const nodes = new Map()
  const requests = []
  let editorContent = ''
  function $(selector) {
    if (selector === '<div>') {
      let text = ''
      return { text(value) { text = value; return this }, html() { return escape(text) } }
    }
    if (!nodes.has(selector)) {
      const state = { value: '', classes: new Set(), data: {}, properties: {} }
      const adapter = {
        text(value) { state.value = value; return this }, html(value) { state.value = value; return this },
        val(value) { if (value === undefined) return state.value; state.value = value; return this },
        prop(key, value) { state.properties[key] = value; return this },
        is() { return state.properties.checked === true }, trigger() { return this },
        empty() { state.value = ''; return this },
        data(key, value) { if (value === undefined) return state.data[key]; state.data[key] = value; return this },
        addClass(name) { state.classes.add(name); return this }, removeClass(name) { state.classes.delete(name); return this },
        toggleClass(name, present) { if (present) state.classes.add(name); else state.classes.delete(name); return this }
      }
      nodes.set(selector, { state, adapter })
    }
    return nodes.get(selector).adapter
  }
  $.post = (url, payload) => { requests.push(payload); return { fail() {} } }
  const context = vm.createContext({ $, window: {},
    document: { createElement() { return { set innerHTML(value) { this.value = decode(value) } } } },
    DOMPurify: { sanitize: text => text },
    kbTranslations: { kb_created_on: 'Created on %s', kb_modified_on: 'Modified on %s',
      kb_date_unavailable: 'Date unavailable', kb_creation_date_unavailable: 'Creation date unavailable',
      kb_created_by: 'Created by', kb_empty_description: 'Empty', kb_comments: 'Comments' },
    kbRenderAssociatedItems() {}, kbRenderViewerAttachments() {}, kbRenderComments() {}, kbStopConsultationPresence() {},
    kbResetForm() { $('#kb-associated-items').val([]) },
    kbInitDescriptionEditor(html) { editorContent = html }, kbGetEditorContent() { return editorContent },
    kbRenderEditorAttachments() {}, kbFormatPlainText(text) { return text },
    kbByteLength(text) { return Buffer.byteLength(text) }, kbHasPendingAttachments() { return false },
    kbEditorOptions: { maxDescriptionBytes: 4194304 }, kbSessionKey: 'test-session', kbEncodePayload: value => value
  })
  vm.runInContext(['kbEscapeHtml', 'kbDecodeHtmlEntities', 'kbArticleDateHtml', 'kbBuildListEntry', 'kbRenderViewer', 'kbHideViewer', 'kbFillEditor', 'kbSaveEntry'].map(section).join('\n'), context)
  return { context, requests, node: selector => nodes.get(selector).state,
    list: entry => context.kbBuildListEntry(entry), view: entry => context.kbRenderViewer(entry) }
}

const dates = { created_at: 100, created_at_label: '10/10/2026 09:15', created_date_label: '10/10/2026', created_at_iso: '2026-10-10T07:15:00Z',
  updated_at: 200, updated_at_label: '16/10/2026 14:32', updated_date_label: '16/10/2026', updated_at_iso: '2026-10-16T12:32:00Z' }

test('opening and saving an encoded title/category repeatedly preserves the stored values', () => {
  const h = harness()
  // Model the HTML text serialization used by the sanitizer at the transport boundary.
  h.context.DOMPurify.sanitize = text => String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const entry = { id: 1, label: 'R&amp;D O\'Brien "VPN" &lt; 5 &gt; 1', category: 'R&amp;D &lt; 5 &gt; 1',
    description_html: '<p>Unchanged body</p>', associated_items: [] }
  for (let round = 0; round < 3; round++) {
    h.context.kbFillEditor(entry)
    assert.equal(h.node('#kb-label').value, 'R&D O\'Brien "VPN" < 5 > 1')
    assert.equal(h.node('#kb-category').value, 'R&D < 5 > 1')
    h.context.kbSaveEntry()
    const saved = h.requests.at(-1).data
    assert.equal(saved.label, entry.label)
    assert.equal(saved.category, entry.category)
    assert.equal(saved.description, entry.description_html)
  }
})

test('list prefers the last modification, falls back to creation, and preserves unknown dates', () => {
  const h = harness()
  const updated = h.list({ id: 1, label: 'Article', ...dates }).display
  assert.match(updated, />Modified on 16\/10\/2026<\/time>/)
  assert.match(updated, /title="Modified on 16\/10\/2026 14:32"/)
  assert.match(updated, /aria-label="Modified on 16\/10\/2026 14:32"/)
  assert.ok(!updated.includes('Created on'))
  const created = h.list({ id: 1, label: 'Article', ...dates, updated_at: null }).display
  assert.match(created, />Created on 10\/10\/2026<\/time>/)
  assert.match(h.list({ id: 1 }).display, /Date unavailable/)
  assert.match(h.list({ id: 1, created_at: 0, created_date_label: '1970-01-01' }).display, /Date unavailable/)
})

test('viewer shows both known dates and clears the previous article modification', () => {
  const h = harness()
  h.view({ id: 1, label: 'R&amp;D', ...dates })
  assert.equal(h.node('#kb-viewer-title').value, 'R&D')
  assert.match(h.node('#kb-viewer-created').value, /Created on 10\/10\/2026 09:15/)
  assert.match(h.node('#kb-viewer-updated').value, /Modified on 16\/10\/2026 14:32/)
  assert.equal(h.node('#kb-viewer-updated').classes.has('hidden'), false)
  h.view({ id: 2, ...dates, updated_at: null })
  assert.equal(h.node('#kb-viewer-updated').value, '')
  assert.equal(h.node('#kb-viewer-updated').classes.has('hidden'), true)
  h.view({ id: 3 })
  assert.equal(h.node('#kb-viewer-created').value, 'Creation date unavailable')
  h.context.kbHideViewer()
  assert.equal(h.node('#kb-viewer-created').value, '')
  assert.equal(h.node('#kb-viewer-updated').value, '')
})

test('same-second edits and edits with unknown creation dates remain visible', () => {
  const h = harness()
  h.view({ id: 1, ...dates, updated_at: dates.created_at })
  assert.equal(h.node('#kb-viewer-updated').classes.has('hidden'), false)
  h.view({ id: 2, ...dates, created_at: null })
  assert.equal(h.node('#kb-viewer-created').value, 'Creation date unavailable')
  assert.equal(h.node('#kb-viewer-updated').classes.has('hidden'), false)
})

test('formatted dates and translations cannot inject markup or tooltip attributes', () => {
  const h = harness()
  h.context.kbTranslations.kb_modified_on = 'O\'Brien "%s" <img onerror=alert(1)>'
  const html = h.list({ id: 1, ...dates, updated_at_label: '"><svg onload=alert(1)>', updated_date_label: '<script>x</script>', updated_at_iso: '" onmouseover="alert(1)' }).display
  assert.ok(!html.includes('<script>') && !html.includes('<img') && !html.includes('<svg'))
  assert.ok(!html.includes('datetime="" onmouseover='))
  assert.match(html, /&lt;script&gt;/)
})

test('date metadata never changes title sorting or searches on displayed encoded names', () => {
  const h = harness()
  const entry = { id: 1, label: 'R&amp;D O&#039;Brien &quot;VPN&quot; &lt;Guide&gt;', description_excerpt: 'Read this procedure', comments_count: 2 }
  const first = h.list({ ...entry, ...dates })
  const second = h.list({ ...entry, ...dates, updated_date_label: '01/01/2000' })
  const expected = 'R&D O\'Brien "VPN" <Guide>'
  assert.equal(first.sort, expected)
  assert.equal(first.sort, second.sort)
  assert.equal(first.filter, second.filter)
  const searchCache = decode(first.filter)
  for (const term of ['R&D', "O'Brien", '"VPN"', '<Guide>', 'Read this']) assert.ok(searchCache.includes(term))
  for (const fragment of ['amp', 'quot', '039', '16/10/2026']) assert.ok(!searchCache.includes(fragment))
  assert.ok(!first.display.includes('<Guide>'))
  assert.match(first.display, /&lt;Guide&gt;/)
})

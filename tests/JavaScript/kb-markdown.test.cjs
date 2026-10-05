const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

const ROOT = join(__dirname, '../..')

/** Run the shipped markdown-it build and the KB module in one context, without a DOM. */
function load() {
  // The browser build decodes its entity table with atob() at load time.
  const context = vm.createContext({ atob })
  vm.runInContext(readFileSync(join(ROOT, 'public/plugins/markdown-it/markdown-it.umd.min.js'), 'utf8'), context)
  vm.runInContext(readFileSync(join(ROOT, 'public/assets/js/kb-markdown.js'), 'utf8'), context)
  return vm.runInContext('createKbMarkdown({ markdownit: markdownit })', context)
}

const kb = load()

test('Pasted AI answers are detected, prose and code are left alone', () => {
  const detected = [
    '# Title\n\nSome **bold** text.\n\n- one\n- two',
    'Steps:\n\n1. Open the console\n2. Run `systemctl restart nginx`',
    '## Context\n\nThe server is down.\n\n## Fix\n\nRestart it.',
    '**Important**:\n- first\n- second',
    'Intro\n\n```bash\nls -la\n```',
    '| Env | Host |\n|---|---|\n| Prod | web01 |'
  ]
  const ignored = [
    'Hello,\n\nPlease restart the server. Thanks for letting us know beforehand.',
    'To check:\n- backups\n- certificates',
    'https://example.com/doc',
    '#!/bin/bash\n# stop\nsystemctl stop nginx\n\n# start\nsystemctl start nginx',
    '# stop service\nsystemctl stop nginx\n# start again\nsystemctl start nginx',
    'import os\n\n# Load config\ncfg = load()\n\n# Run\nrun(cfg)',
    '# Service\nservices:\n  web:\n    image: nginx\n\n# Ports\n    ports:\n      - "80:80"\n      - "443:443"',
    '[database]\nhost = db01\nport = 3306\nuser = teampass',
    '{\n  "name": "x",\n  "port": 80\n}',
    ''
  ]
  detected.forEach(text => assert.equal(kb.looksLikeMarkdown(text), true, text))
  ignored.forEach(text => assert.equal(kb.looksLikeMarkdown(text), false, text))
})

test('Headings start at h2 and keep their hierarchy up to h4', () => {
  assert.match(kb.renderMarkdown('# A\n\n## B\n\n### C\n\n#### D'), /<h2>A<\/h2>\s*<h3>B<\/h3>\s*<h4>C<\/h4>\s*<h4>D<\/h4>/)
  assert.match(kb.renderMarkdown('## A\n\n### B'), /<h2>A<\/h2>\s*<h3>B<\/h3>/)
  assert.match(kb.renderMarkdown('### Only'), /<h2>Only<\/h2>/)
})

test('Rendering escapes raw HTML and refuses script links', () => {
  const html = kb.renderMarkdown('<img src=x onerror=alert(1)>\n\n[x](javascript:alert(1))\n\n<script>alert(1)</script>')
  assert.doesNotMatch(html, /<img|<script|href="javascript/i)
  assert.match(html, /&lt;script&gt;/)
})

test('GFM output used by AI answers maps to tags the KB sanitizers allow', () => {
  const html = kb.renderMarkdown('~~old~~ and https://example.com\nnext line\n\n| A | B |\n|---|---|\n| 1 | 2 |')
  assert.match(html, /<s>old<\/s>/)
  assert.match(html, /<a href="https:\/\/example.com">/)
  assert.match(html, /<br>\s*next line/)
  assert.match(html, /<table>[\s\S]*<th>A<\/th>[\s\S]*<td>2<\/td>/)
})

test('Image placeholders are restored, unknown ones and ordinary links are kept', () => {
  const images = { 'tp-kb-image-1': 'data:image/png;base64,AAAA' }
  assert.equal(kb.restoreImages('![a](tp-kb-image-1) ![b](tp-kb-image-2)', images),
    '![a](data:image/png;base64,AAAA) ![b](tp-kb-image-2)')
  assert.equal(kb.restoreImages('[doc](https://example.com)', images), '[doc](https://example.com)')
  assert.equal(kb.restoreImages('![a](tp-kb-image-1)', null), '![a](tp-kb-image-1)')
})

test('Keeping plain text escapes everything and preserves line breaks', () => {
  assert.equal(kb.plainTextToHtml('## Title\n**x** <b>\n\nnext'), '<p>## Title<br>**x** &lt;b&gt;</p><p>next</p>')
  assert.equal(kb.plainTextToHtml('  \n '), '')
})

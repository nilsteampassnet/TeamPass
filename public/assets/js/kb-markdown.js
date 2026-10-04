/**
 * Markdown support for the knowledge base editor.
 *
 * Shared by app/pages/kb.js.php and tests/JavaScript/kb-markdown.test.cjs: paste detection,
 * Markdown rendering, and the HTML <-> Markdown conversions behind the "Edit as Markdown" view.
 * Every helper returning HTML returns untrusted markup: the caller sanitizes it (kbSanitizeHtml).
 *
 * @param {object} deps markdownit, TurndownService, turndownPluginGfm and parseHtml(html): Document.
 */
function createKbMarkdown(deps) {
  const imagePlaceholder = /\]\((tp-kb-image-\d+)\)/g
  let renderer = null
  let converter = null

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, character => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character])
  }

  /** Decide whether pasted plain text is Markdown worth rendering. Scripts and configuration files never are. */
  function looksLikeMarkdown(text) {
    const value = String(text || '').replace(/\r\n?/g, '\n')
    const lines = value.split('\n')
    const nonEmpty = lines.filter(line => line.trim() !== '')
    if (nonEmpty.length === 0 || /^#!/.test(value.trimStart())) return false

    const configLike = nonEmpty.filter(line => /^\s*[\w.-]+\s*[:=]\s*\S*$/.test(line) || /[;{}]\s*$/.test(line)).length
    if (configLike / nonEmpty.length >= 0.4) return false

    if (/^\s*```/m.test(value)) return true
    if (/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/m.test(value)) return true

    const blank = index => index < 0 || index >= lines.length || lines[index].trim() === ''
    const headingAt = lines.map((line, index) => /^#{1,6}\s+\S/.test(line) && blank(index - 1))
    // A heading followed by a blank line structures prose; a code comment is followed by code.
    if (headingAt.filter((isHeading, index) => isHeading && blank(index + 1)).length >= 2) return true

    let score = headingAt.includes(true) ? 1 : 0
    if (/\*\*[^*\n]+\*\*|__[^_\n]+__/.test(value)) score += 1
    if (lines.filter(line => /^\s*[-*+]\s+\S/.test(line)).length >= 2) score += 1
    if (lines.filter(line => /^\s*\d+[.)]\s+\S/.test(line)).length >= 2) score += 1
    if (/\[[^\]\n]+\]\((?:https?:\/\/|mailto:)[^)\s]+\)/.test(value)) score += 1
    if (/^>\s+\S/m.test(value)) score += 1
    if (/`[^`\n]+`/.test(value)) score += 1
    return score >= 2
  }

  /** Map the highest heading of the document to h2 (the article title is the page's h1) and cap at h4. */
  function normalizeTokenHeadings(state) {
    const headings = state.tokens.filter(token => token.type === 'heading_open' || token.type === 'heading_close')
    if (headings.length === 0) return
    const offset = 2 - Math.min(...headings.map(token => Number(token.tag.substring(1))))
    headings.forEach(token => {
      token.tag = 'h' + Math.min(4, Math.max(2, Number(token.tag.substring(1)) + offset))
    })
  }

  function getRenderer() {
    if (renderer === null) {
      renderer = deps.markdownit({ html: false, linkify: true, breaks: true })
      renderer.core.ruler.push('tp_kb_heading_levels', normalizeTokenHeadings)
    }
    return renderer
  }

  function renderMarkdown(markdown) {
    return getRenderer().render(String(markdown || ''))
  }

  /** Literal text as paragraphs, used by "Keep plain text". */
  function plainTextToHtml(text) {
    const value = String(text || '').replace(/\r\n?/g, '\n').trim()
    if (value === '') return ''
    return value.split(/\n{2,}/)
      .map(block => '<p>' + block.split('\n').map(escapeHtml).join('<br>') + '</p>')
      .join('')
  }

  /** Put back the embedded images that the Markdown view replaced by short placeholders. */
  function restoreImages(markdown, images) {
    const known = images || {}
    return String(markdown || '').replace(imagePlaceholder, (match, key) =>
      Object.prototype.hasOwnProperty.call(known, key) ? '](' + known[key] + ')' : match)
  }

  /** h1, h5 and h6 are outside both KB sanitizer allowlists: keep them as headings instead of losing them. */
  function clampHtmlHeadings(html) {
    const value = String(html || '')
    const doc = deps.parseHtml(value)
    const headings = doc.body.querySelectorAll('h1, h5, h6')
    if (headings.length === 0) return value
    headings.forEach(heading => {
      const replacement = doc.createElement(heading.nodeName === 'H1' ? 'h2' : 'h4')
      replacement.append(...heading.childNodes)
      heading.replaceWith(replacement)
    })
    return doc.body.innerHTML
  }

  function listItem(content, node, options) {
    let prefix = options.bulletListMarker + ' '
    const parent = node.parentNode
    if (parent.nodeName === 'OL') {
      const start = parent.getAttribute('start')
      const index = Array.prototype.indexOf.call(parent.children, node)
      prefix = (start ? Number(start) + index : index + 1) + '. '
    }
    const body = content.replace(/^\n+/, '').replace(/\n+$/, '\n').replace(/\n/gm, '\n    ')
    return prefix + body + (node.nextSibling && !/\n$/.test(body) ? '\n' : '')
  }

  function getConverter() {
    if (converter === null) {
      converter = new deps.TurndownService({
        headingStyle: 'atx', codeBlockStyle: 'fenced', bulletListMarker: '-', emDelimiter: '*', hr: '---'
      })
      converter.use(deps.turndownPluginGfm.gfm)
      // markdown-it only reads ~~double~~ tildes; the GFM plugin writes single ones.
      converter.addRule('tpKbStrikethrough', { filter: ['del', 's', 'strike'], replacement: content => '~~' + content + '~~' })
      // One space after the marker, instead of Turndown's column alignment.
      converter.addRule('tpKbListItem', { filter: 'li', replacement: listItem })
      // A raw pipe or line break inside a cell would split or end the Markdown row and lose text.
      converter.addRule('tpKbTableCell', {
        filter: ['th', 'td'],
        replacement: (content, node) => {
          const first = Array.prototype.indexOf.call(node.parentNode.childNodes, node) === 0
          return (first ? '| ' : ' ') + content.trim().replace(/\s*\n+\s*/g, ' ').replace(/\|/g, '\\|') + ' |'
        }
      })
    }
    return converter
  }

  /**
   * Convert sanitized editor HTML for the Markdown view.
   * Returns the Markdown, the embedded images keyed by placeholder, and what Markdown cannot carry.
   */
  function htmlToMarkdown(html) {
    const doc = deps.parseHtml(String(html || ''))
    const lossy = new Set()
    const images = {}
    let imageCount = 0

    doc.body.querySelectorAll('u').forEach(element => {
      lossy.add('underline')
      element.replaceWith(...element.childNodes)
    })
    doc.body.querySelectorAll('img').forEach(image => {
      if (image.hasAttribute('width') || image.hasAttribute('height')) lossy.add('image_size')
      const source = image.getAttribute('src') || ''
      if (/^data:/i.test(source)) {
        imageCount += 1
        const key = 'tp-kb-image-' + imageCount
        images[key] = source
        image.setAttribute('src', key)
      }
    })
    doc.body.querySelectorAll('table').forEach(table => {
      table.querySelectorAll('[colspan], [rowspan]').forEach(cell => {
        const colspan = parseInt(cell.getAttribute('colspan') || '1', 10) || 1
        const rowspan = parseInt(cell.getAttribute('rowspan') || '1', 10) || 1
        if (colspan > 1 || rowspan > 1) lossy.add('merged_cells')
        cell.removeAttribute('colspan')
        cell.removeAttribute('rowspan')
        for (let column = 1; column < colspan; column += 1) {
          cell.after(doc.createElement(cell.nodeName.toLowerCase()))
        }
      })
      // A Markdown table needs a header row; Summernote's table button creates none.
      const firstRow = table.querySelector('tr')
      if (firstRow !== null && firstRow.querySelector('th') === null) {
        lossy.add('table_header')
        firstRow.querySelectorAll('td').forEach(cell => {
          const header = doc.createElement('th')
          header.append(...cell.childNodes)
          cell.replaceWith(header)
        })
      }
    })

    return { markdown: getConverter().turndown(doc.body.innerHTML), images, lossy: Array.from(lossy) }
  }

  return { looksLikeMarkdown, renderMarkdown, plainTextToHtml, restoreImages, clampHtmlHeadings, htmlToMarkdown }
}

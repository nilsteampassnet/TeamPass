/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */
(function(root) {
  'use strict'

  const styleClasses = { s: 'fa-solid', r: 'fa-regular', b: 'fa-brands' }
  const styleAliases = { fas: 'fa-solid', far: 'fa-regular', fab: 'fa-brands' }
  // Words of a search that only repeat the Font Awesome prefixes
  const ignoredWords = ['fa', 'fas', 'far', 'fab', 'solid', 'regular', 'brands']

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, character => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character])
  }

  /** Catalogue, search and value helpers, free of the DOM so the node tests can run them. */
  const IconCatalog = {
    /** One entry per icon and free style, from the server rows [name, style codes, terms]. */
    entries(rows) {
      const entries = []
      for (const style of ['s', 'r', 'b']) {
        rows.forEach(row => {
          const name = String(row[0] || '')
          if (/^[a-z0-9-]+$/.test(name) === false || String(row[1] || '').indexOf(style) === -1) return
          entries.push({
            classes: styleClasses[style] + ' fa-' + name,
            name,
            words: name.split('-'),
            style,
            terms: String(row[2] || '').split(' '),
            order: entries.length
          })
        })
      }
      return entries
    },

    /** Canonical "fa-<style> fa-<name>" of a stored value, '' without an icon name: "fas fa-key" is the key tile. */
    normalize(value) {
      let style = 'fa-solid'
      let name = ''
      String(value || '').trim().split(/\s+/).forEach(token => {
        if (styleAliases[token] !== undefined) {
          style = styleAliases[token]
        } else if (token === 'fa-solid' || token === 'fa-regular' || token === 'fa-brands') {
          style = token
        } else if (name === '' && /^fa-[a-z0-9-]+$/.test(token)) {
          name = token
        }
      })
      return name === '' ? '' : style + ' ' + name
    },

    /** Classes rendering a stored value: values typed before the picker may lack the style class. */
    renderClasses(value) {
      const icon = String(value || '').trim()
      if (icon === '') return ''
      return /(^|\s)(fa-solid|fa-regular|fa-brands|fas|far|fab)(\s|$)/.test(icon) ? icon : 'fas ' + icon
    },

    /** Search words, without the Font Awesome prefixes typed by those who know them. */
    tokens(query) {
      return String(query || '').toLowerCase().split(/\s+/)
        .map(token => token.replace(/^fa-/, ''))
        .filter(token => token !== '' && ignoredWords.indexOf(token) === -1)
    },

    /**
     * Entries matching every word, best first: name equal, name starting with the word or holding
     * it as a word, a Font Awesome term equal to it, name containing it, a term starting with it.
     * Equal scores put icons before brands, then the shortest name.
     */
    search(entries, tokens) {
      const matches = []
      entries.forEach(entry => {
        let score = 0
        for (const token of tokens) {
          let tokenScore = 0
          if (entry.name === token) tokenScore = 5
          else if (entry.name.indexOf(token) === 0 || entry.words.indexOf(token) > -1) tokenScore = 4
          else if (entry.terms.indexOf(token) > -1) tokenScore = 3
          else if (entry.name.indexOf(token) > -1) tokenScore = 2
          else if (entry.terms.some(term => term.indexOf(token) === 0)) tokenScore = 1
          if (tokenScore === 0) return
          score += tokenScore
        }
        matches.push({ entry, score })
      })
      matches.sort((a, b) => b.score - a.score
        || (a.entry.style === 'b' ? 1 : 0) - (b.entry.style === 'b' ? 1 : 0)
        || a.entry.name.length - b.entry.name.length
        || a.entry.order - b.entry.order)
      return matches.map(match => match.entry)
    }
  }

  /**
   * Icon picker of the inputs holding Font Awesome classes (item icon, folder icons): a search
   * field over a grid of the free icons. One panel serves every picker of the page and moves into
   * the one being opened, a Bootstrap dropdown menu or an area unfolded below the field.
   *
   * @param {Object} config {key: session key, messages: labels}
   * @return {{attach: Function, attachField: Function, refresh: Function}}
   */
  function createIconPicker(config) {
    const messages = config.messages
    // Search results shown at most: beyond that the user refines the search
    const searchLimit = 240
    const pickers = []
    const defaultHtml = {}
    let entries = null
    let suggested = {}
    let request = null
    let active = null

    const panel = $('<div class="tp-icon-picker-panel">'
      + '<label class="sr-only" for="tp-icon-picker-search">' + escapeHtml(messages.search) + '</label>'
      + '<input type="search" class="form-control form-control-sm" id="tp-icon-picker-search" autocomplete="off" spellcheck="false" placeholder="' + escapeHtml(messages.search_placeholder) + '">'
      + '<div class="tp-icon-picker"></div>'
      + '<div class="tp-icon-picker-foot">'
      + '<code class="tp-icon-picker-value"></code>'
      + '<button type="button" class="tp-icon-picker-reset">' + escapeHtml(messages.default) + '</button>'
      + '</div></div>')
    const search = panel.find('#tp-icon-picker-search')
    const grid = panel.find('.tp-icon-picker')

    function gridHtml(list, title) {
      let html = title === '' ? '' : '<div class="tp-icon-picker-title">' + escapeHtml(title) + '</div>'
      html += '<div class="tp-icon-picker-grid">'
      list.forEach(entry => {
        html += '<button type="button" class="tp-icon-picker-tile" data-icon="' + escapeHtml(entry.classes) + '"'
          + ' title="' + escapeHtml(entry.name) + '" aria-label="' + escapeHtml(entry.name) + '" aria-pressed="false">'
          + '<i class="' + escapeHtml(entry.classes) + '" aria-hidden="true"></i></button>'
      })
      return html + '</div>'
    }

    function showMessage(text, isError) {
      grid.html('<div class="tp-icon-picker-empty' + (isError ? ' text-danger' : '') + '">'
        + (isError ? '' : '<i class="fa-solid fa-circle-notch fa-spin mr-2" aria-hidden="true"></i>')
        + escapeHtml(text) + '</div>')
    }

    /** Highlight the icon of the open picker and show its value under the grid. */
    function markSelected() {
      const value = active === null ? '' : String(active.input.val() || '').trim()
      const selected = IconCatalog.normalize(value)
      grid.find('.tp-icon-picker-tile').each(function() {
        const isSelected = this.getAttribute('data-icon') === selected
        this.classList.toggle('is-selected', isSelected)
        this.setAttribute('aria-pressed', isSelected ? 'true' : 'false')
      })
      panel.find('.tp-icon-picker-value').text(value)
      panel.find('.tp-icon-picker-reset').prop('disabled', value === '')
    }

    /** Suggestions, icons and brands without a search; the best matches with one. */
    function render() {
      if (entries === null || active === null) return
      const tokens = IconCatalog.tokens(search.val())
      let html = ''
      if (tokens.length === 0) {
        const kind = active.suggestions
        if (defaultHtml[kind] === undefined) {
          defaultHtml[kind] = gridHtml(suggested[kind] || [], messages.suggested)
            + gridHtml(entries.filter(entry => entry.style !== 'b'), messages.all)
            + gridHtml(entries.filter(entry => entry.style === 'b'), messages.brands)
        }
        html = defaultHtml[kind]
      } else {
        const matches = IconCatalog.search(entries, tokens)
        html = matches.length === 0
          ? '<div class="tp-icon-picker-empty">' + escapeHtml(messages.no_result) + '</div>'
          : gridHtml(matches.slice(0, searchLimit), '')
      }
      grid.html(html).scrollTop(0)
      markSelected()
    }

    /** Load the catalogue once; a failure is shown and the next opening tries again. */
    function load() {
      if (entries !== null || request !== null) return
      showMessage(messages.loading, false)
      request = $.post('sources/main.queries.php', {
        type: 'get_icon_catalog',
        type_category: 'action_utils',
        key: config.key
      }, data => {
        // Font Awesome metadata, not user data: purifying its ~6000 strings would only cost time.
        // Names are checked again in IconCatalog.entries() and every value is encoded where rendered.
        try {
          data = prepareExchangedData(data, 'decode', config.key, 'main.queries.php', 'get_icon_catalog', false)
        } catch (e) {
          data = false
        }
        if (!data || data.error !== false || Array.isArray(data.icons) === false) {
          showMessage(messages.error, true)
          return
        }
        entries = IconCatalog.entries(data.icons)
        suggested = {}
        Object.keys(data.suggested || {}).forEach(kind => {
          const list = Array.isArray(data.suggested[kind]) ? data.suggested[kind] : []
          suggested[kind] = list.map(classes => entries.find(entry => entry.classes === classes)).filter(Boolean)
        })
        render()
      }).fail(() => {
        showMessage(messages.error, true)
      }).always(() => {
        request = null
      })
    }

    function refreshPreview(picker) {
      const classes = IconCatalog.renderClasses(picker.input.val())
      picker.preview.html(classes === '' ? '' : '<i class="' + escapeHtml(classes) + '" aria-hidden="true"></i>')
      if (picker === active) markSelected()
    }

    function close(picker) {
      if (picker.dropdown) {
        if (picker.host.hasClass('show')) picker.toggle.dropdown('hide')
      } else {
        picker.host.addClass('hidden')
        picker.toggle.attr('aria-expanded', 'false')
      }
    }

    function open(picker) {
      if (active !== null && active !== picker) close(active)
      active = picker
      picker.host.append(panel)
      search.val('')
      load()
      render()
      markSelected()
    }

    function focusPanel() {
      search.trigger('focus')
      // Bring the current icon into view
      const selected = grid.find('.tp-icon-picker-tile.is-selected').first()
      if (selected.length > 0) grid.scrollTop(selected.position().top + grid.scrollTop() - grid.height() / 2)
    }

    function pick(value) {
      const picker = active
      if (picker === null) return
      picker.input.val(value).trigger('input').trigger('change')
      if (typeof picker.onChange === 'function') picker.onChange(value)
      close(picker)
      picker.toggle.trigger('focus')
    }

    search.on('input', render).on('keydown', event => {
      // Enter takes the best match instead of submitting the form; Down enters the grid
      if (event.key === 'Enter') {
        event.preventDefault()
        event.stopPropagation()
        if (IconCatalog.tokens(search.val()).length > 0) grid.find('.tp-icon-picker-tile').first().trigger('click')
      } else if (event.key === 'ArrowDown') {
        event.preventDefault()
        grid.find('.tp-icon-picker-tile').first().trigger('focus')
      } else if (event.key === 'Escape' && active !== null && active.dropdown === false) {
        // An unfolded picker closes alone, without closing the modal around it
        event.preventDefault()
        event.stopPropagation()
        close(active)
        active.toggle.trigger('focus')
      }
    })
    grid.on('click', '.tp-icon-picker-tile', function() {
      pick(this.getAttribute('data-icon'))
    }).on('keydown', '.tp-icon-picker-tile', function(event) {
      if (event.key === 'Escape' && active !== null && active.dropdown === false) {
        event.preventDefault()
        event.stopPropagation()
        close(active)
        active.toggle.trigger('focus')
        return
      }
      // Arrow keys move in the grid; Bootstrap would otherwise take Up and Down for its menu
      const offsets = { ArrowLeft: -1, ArrowRight: 1 }
      if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
        const columns = window.getComputedStyle(this.parentNode).gridTemplateColumns.split(' ').length
        offsets[event.key] = event.key === 'ArrowUp' ? -columns : columns
      }
      if (offsets[event.key] === undefined) return
      event.preventDefault()
      event.stopPropagation()
      const tiles = grid.find('.tp-icon-picker-tile')
      const target = tiles.index(this) + offsets[event.key]
      if (target < 0) search.trigger('focus')
      else if (target < tiles.length) tiles.eq(target).trigger('focus')
    })
    panel.find('.tp-icon-picker-reset').on('click', () => pick(''))

    /**
     * Attach a picker to an input.
     *
     * @param {Object} options input: the input holding the classes; toggle: the button opening the
     *   picker; host: a .dropdown-menu, or an area shown below the field; preview: the element
     *   showing the icon; suggestions: 'item' or 'folder'; onChange: called with the picked value
     * @return {Object}
     */
    function attach(options) {
      const picker = {
        input: $(options.input),
        toggle: $(options.toggle),
        host: $(options.host),
        preview: $(options.preview || []),
        suggestions: options.suggestions || 'item',
        onChange: options.onChange || null
      }
      picker.dropdown = picker.host.hasClass('dropdown-menu')
      pickers.push(picker)
      picker.input.on('input change', () => refreshPreview(picker))
      if (picker.dropdown) {
        // Keep the menu open while the user works in it
        picker.host.on('click', event => event.stopPropagation())
        picker.toggle.parent()
          .on('show.bs.dropdown', () => open(picker))
          .on('shown.bs.dropdown', focusPanel)
      } else {
        picker.toggle.on('click', () => {
          if (active === picker && picker.host.hasClass('hidden') === false) {
            close(picker)
            return
          }
          open(picker)
          picker.host.removeClass('hidden')
          picker.toggle.attr('aria-expanded', 'true')
          focusPanel()
        })
      }
      refreshPreview(picker)
      return picker
    }

    /**
     * Give a text input a button showing its icon, which unfolds the picker below the field.
     * The input stays editable for those who type the classes.
     *
     * @param {string} input Selector of the input
     * @param {string} suggestions 'item' or 'folder'
     * @return {Object}
     */
    function attachField(input, suggestions) {
      const field = $(input)
      const id = field.attr('id')
      const toggle = $('<button type="button" class="input-group-text tp-icon-picker-toggle" aria-expanded="false">'
        + '<span class="tp-icon-picker-preview"></span><i class="fa-solid fa-icons tp-icon-picker-placeholder" aria-hidden="true"></i></button>')
        .attr({ id: id + '-picker-toggle', 'aria-controls': id + '-picker', title: messages.choose, 'aria-label': messages.choose })
      const host = $('<div class="tp-icon-picker-inline hidden"></div>').attr('id', id + '-picker')
      field.wrap('<div class="input-group"></div>')
        .before($('<div class="input-group-prepend"></div>').append(toggle))
      field.parent().after(host)
      return attach({ input: field, toggle, host, preview: toggle.find('.tp-icon-picker-preview'), suggestions })
    }

    /** Show again the icon of the inputs whose value was set by code. */
    function refresh(input) {
      const element = input === undefined ? null : $(input)[0]
      pickers.forEach(picker => {
        if (element === null || picker.input[0] === element) refreshPreview(picker)
      })
    }

    return { attach, attachField, refresh }
  }

  if (typeof module === 'object' && module.exports) module.exports = { IconCatalog, createIconPicker }
  else {
    root.TeampassIconCatalog = IconCatalog
    root.createIconPicker = createIconPicker
  }
})(typeof window === 'undefined' ? globalThis : window)

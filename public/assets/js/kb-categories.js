/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License v3.0.
 * See <https://www.gnu.org/licenses/>.
 */

/**
 * Build the category navigation state from the active articles returned by list_kbs.
 * Category labels are untrusted text; the UI must escape them when rendering.
 * @returns {object} Category navigation and article selection helpers.
 */
function createKbCategoryBrowser() {
  let entries = []
  let categories = []
  let view = 'list'
  let selectedId = null

  function categoryId(entry) {
    const id = Number(entry.category_id)
    return Number.isSafeInteger(id) && id > 0 && String(entry.category || '').trim() !== '' ? id : 0
  }

  return {
    /** Refresh counts and preserve the selection only while its category still has articles. */
    refresh(nextEntries) {
      entries = Array.isArray(nextEntries) ? nextEntries : []
      const groups = new Map()
      entries.forEach(entry => {
        const id = categoryId(entry)
        if (!groups.has(id)) groups.set(id, { id, label: id === 0 ? '' : String(entry.category), count: 0 })
        groups.get(id).count += 1
      })
      categories = Array.from(groups.values()).sort((left, right) =>
        left.label.localeCompare(right.label, undefined, { numeric: true, sensitivity: 'base' }) || left.id - right.id)
      if (!groups.has(selectedId)) selectedId = null
    },

    /** Switch to the complete list or to the category overview. */
    setView(nextView) {
      view = nextView === 'categories' ? 'categories' : 'list'
      selectedId = null
    },

    /** Select an existing category by its numeric identity, never by a partial label match. */
    select(id) {
      const category = categories.find(candidate => candidate.id === Number(id))
      if (!category) return false
      view = 'categories'
      selectedId = category.id
      return true
    },

    /** Return the current navigation state. */
    getState() {
      return { view, selected: categories.find(category => category.id === selectedId) || null }
    },

    /** Return categories populated by active articles. */
    getCategories() {
      return categories
    },

    /** Return all articles or the exact selected category, before table text search and paging. */
    getEntries() {
      return selectedId === null ? entries : entries.filter(entry => categoryId(entry) === selectedId)
    }
  }
}

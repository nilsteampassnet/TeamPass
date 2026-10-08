/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */
(function(root) {
  'use strict'

  /** Keep selection and expansion independent of the rows currently rendered. */
  class FolderTree {
    constructor() {
      this.rows = []
      this.byId = new Map()
      this.expanded = new Set()
      this.selected = new Set()
      this.initialized = false
    }

    /** Replace a server snapshot while retaining valid selections and expanded branches. */
    replace(rows) {
      const counts = new Map()
      rows.forEach(row => row.parents.forEach(id => counts.set(Number(id), (counts.get(Number(id)) || 0) + 1)))
      this.rows = rows.map(row => ({ ...row, numOfChildren: counts.get(Number(row.id)) || 0 }))
      this.byId = new Map(this.rows.map(row => [Number(row.id), row]))
      this.expanded = new Set([...this.expanded].filter(id => this.byId.has(id)))
      this.selected = new Set([...this.selected].filter(id => this.byId.has(id)))
      if (!this.initialized && rows.length <= 100) {
        this.expanded = new Set(this.byId.keys())
      }
      this.initialized = true
      this.searchText = new Map(rows.map(row => [Number(row.id),
        [...row.path, row.title].join(' / ').toLocaleLowerCase()]))
    }

    /** Return matching rows across the entire snapshot, including collapsed branches on search. */
    visible({ depth = 'all', complexity = 'all', term = '' } = {}) {
      const needle = term.trim().toLocaleLowerCase()
      const filtering = needle !== '' || complexity !== 'all'
      return this.rows.filter(row => {
        if (depth !== 'all' && Number(row.level) > Number(depth)) return false
        if (complexity !== 'all' && String(row.folderComplexity.value) !== String(complexity)) return false
        if (needle && !this.searchText.get(Number(row.id)).includes(needle)) return false
        return filtering || row.parents.every(id => !this.byId.has(Number(id)) || this.expanded.has(Number(id)))
      })
    }

    /** Toggle one branch without discarding its descendants' expansion state. */
    toggle(id) {
      id = Number(id)
      if (this.expanded.has(id)) this.expanded.delete(id)
      else this.expanded.add(id)
    }

    /** Select every authorized descendant, even when it has no DOM row. */
    selectBranch(id, checked) {
      id = Number(id)
      this.rows.forEach(row => {
        if (Number(row.id) === id || row.parents.map(Number).includes(id)) {
          if (checked) this.selected.add(Number(row.id))
          else this.selected.delete(Number(row.id))
        }
      })
      // An unchecked child must not remain selected implicitly through a checked ancestor.
      if (!checked && this.byId.has(id)) {
        this.byId.get(id).parents.forEach(parent => this.selected.delete(Number(parent)))
      }
    }

    /** Resolve selected rows in tree order for the confirmation and server request. */
    selectedRows() {
      return this.rows.filter(row => this.selected.has(Number(row.id)))
    }

    /** Update one row and the displayed paths of its descendants after a rename. */
    upsert(row) {
      const id = Number(row.id)
      const index = this.rows.findIndex(value => Number(value.id) === id)
      const rows = this.rows.slice()
      if (index >= 0) {
        rows[index] = row
        rows.forEach((child, childIndex) => {
          const pathIndex = child.parents.map(Number).indexOf(id)
          if (pathIndex >= 0) {
            const path = child.path.slice()
            path[pathIndex] = row.title
            rows[childIndex] = { ...child, path }
          }
        })
      } else {
        let insertAt = rows.length
        rows.forEach((candidate, candidateIndex) => {
          if (Number(candidate.id) === Number(row.parentId) || candidate.parents.map(Number).includes(Number(row.parentId))) {
            insertAt = candidateIndex + 1
          }
        })
        rows.splice(insertAt, 0, row)
        if (this.selected.has(Number(row.parentId))) this.selected.add(id)
      }
      this.replace(rows)
    }

    /** Remove successfully deleted branches from the snapshot and selection. */
    removeBranches(ids) {
      const removed = new Set(ids.map(Number))
      this.replace(this.rows.filter(row => !removed.has(Number(row.id))
        && !row.parents.some(id => removed.has(Number(id)))))
    }
  }

  if (typeof module === 'object' && module.exports) module.exports = FolderTree
  else root.TeampassFolderTree = FolderTree
})(typeof window === 'undefined' ? globalThis : window)

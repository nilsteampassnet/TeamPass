/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */
const assert = require('node:assert/strict')
const { test } = require('node:test')
const FolderTree = require('../../public/assets/js/folders-tree.js')
const { readFileSync } = require('node:fs')
const vm = require('node:vm')

function row(id, parents = [], extra = {}) {
  return { id, parentId: parents.at(-1) || 0, level: parents.length + 1,
    parents, path: parents.map(id => `Folder ${id}`), title: `Folder ${id}`,
    folderComplexity: { value: 60 }, ...extra }
}

test('3161 folders initially expose only roots, with search covering collapsed descendants', () => {
  const tree = new FolderTree()
  tree.replace([row(1), ...Array.from({ length: 3160 }, (_, i) => row(i + 2, [1]))])
  assert.deepEqual(tree.visible().map(row => row.id), [1])
  assert.equal(tree.visible({ term: 'Folder 3161' })[0].id, 3161)
  tree.toggle(1)
  assert.equal(tree.visible().length, 3161)
  tree.toggle(1)
  assert.equal(tree.visible().length, 1)
})

test('Small trees remain expanded, and inaccessible ancestors do not hide authorized rows', () => {
  const tree = new FolderTree()
  tree.replace([row(1), row(2, [1]), row(3, [99])])
  assert.equal(tree.visible().length, 3)
  tree.toggle(1)
  assert.deepEqual(tree.visible().map(row => row.id), [1, 3])
})

test('Depth, complexity and full-path search combine without requiring a visible parent', () => {
  const tree = new FolderTree()
  tree.replace([row(1), row(2, [1], { title: 'Équipe', folderComplexity: { value: 20 } }), row(3, [1, 2])])
  tree.expanded.clear()
  assert.deepEqual(tree.visible({ complexity: '20', depth: '2', term: 'équi' }).map(row => row.id), [2])
  assert.deepEqual(tree.visible({ complexity: '60', depth: '2', term: 'Équipe' }), [])
  assert.equal(tree.visible({ term: 'Folder 1 / Équipe' }).length, 1)
})

test('Selecting a collapsed branch includes every descendant and survives filters and refresh', () => {
  const tree = new FolderTree()
  const rows = [row(1), row(2, [1]), row(3, [1, 2]), row(4)]
  tree.replace(rows)
  tree.expanded.clear()
  tree.selectBranch(1, true)
  tree.visible({ term: 'Folder 4' })
  tree.replace(rows)
  assert.deepEqual(tree.selectedRows().map(row => row.id), [1, 2, 3])
  tree.selectBranch(2, false)
  assert.deepEqual(tree.selectedRows(), [])
})

test('Moving an unchecked subtree under checked ancestors invalidates only those ancestors', () => {
  const tree = new FolderTree()
  tree.replace([row(1), row(2, [1]), row(3, [1, 2]), row(4), row(5, [4]), row(6)])
  tree.selectBranch(1, true)
  tree.selectBranch(6, true)
  tree.replace([row(1), row(2, [1]), row(3, [1, 2]), row(4, [1, 2]), row(5, [1, 2, 4]), row(6)])
  assert.deepEqual(tree.selectedRows().map(row => row.id), [3, 6])
  assert.equal(tree.selected.has(4), false)
  assert.equal(tree.selected.has(5), false)
  tree.selectBranch(2, true)
  assert.deepEqual(tree.selectedRows().map(row => row.id), [2, 3, 4, 5, 6])
})

test('New hidden descendants invalidate ancestor selection until the branch is explicitly reselected', () => {
  const tree = new FolderTree()
  tree.replace([row(1), row(2, [1]), row(3, [1, 2])])
  tree.selectBranch(1, true)
  tree.expanded.clear()
  tree.replace([row(1), row(2, [1]), row(3, [1, 2]),
    ...Array.from({ length: 3158 }, (_, i) => row(i + 4, [1, 2]))])
  assert.deepEqual(tree.visible().map(row => row.id), [1])
  assert.deepEqual(tree.selectedRows().map(row => row.id), [3])
  tree.visible({ term: 'Folder 3161' })
  assert.deepEqual(tree.selectedRows().map(row => row.id), [3])
  tree.selectBranch(1, true)
  assert.equal(tree.selectedRows().length, 3161)
  tree.replace(tree.rows)
  assert.equal(tree.selectedRows().length, 3161)
})

test('Moving an already checked subtree retains complete and unrelated selections', () => {
  const tree = new FolderTree()
  tree.replace([row(1), row(2, [1]), row(3), row(4, [3]), row(5)])
  tree.selectBranch(1, true)
  tree.selectBranch(3, true)
  tree.selectBranch(5, true)
  tree.replace([row(1), row(2, [1]), row(3, [1]), row(4, [1, 3]), row(5)])
  assert.deepEqual(tree.selectedRows().map(row => row.id), [1, 2, 3, 4, 5])
})

test('Rename, insertion and deletion keep paths, selection and child counts consistent', () => {
  const tree = new FolderTree()
  tree.replace([row(1), row(2, [1]), row(3, [1, 2]), row(4)])
  tree.selectBranch(1, true)
  tree.upsert(row(2, [1], { title: 'Renamed' }))
  assert.equal(tree.byId.get(3).path[1], 'Renamed')
  assert.equal(tree.visible({ term: 'Renamed' }).length, 2)
  tree.upsert(row(5, [1, 2]))
  assert.deepEqual(tree.rows.map(row => row.id), [1, 2, 3, 5, 4])
  assert.equal(tree.selected.has(5), true)
  assert.equal(tree.byId.get(2).numOfChildren, 2)
  tree.removeBranches([2])
  assert.deepEqual(tree.rows.map(row => row.id), [1, 4])
  assert.equal(tree.byId.get(1).numOfChildren, 0)
  assert.deepEqual(tree.selectedRows().map(row => row.id), [1])
})

test('The shipped page script compiles with translated text and server-side branches', () => {
  const page = readFileSync(require.resolve('../../app/pages/folders.js.php'), 'utf8')
  const script = page.slice(page.indexOf("<script type='text/javascript'>") + "<script type='text/javascript'>".length, page.lastIndexOf('</script>'))
    // PHP consumes the newline immediately following a closing tag.
    .replace(/\?>(?:\r?\n)/g, '?>')
    .replace(/<\?php echo json_encode\([\s\S]*?\?>/g, '"Translated"')
    .replace(/<\?php echo[\s\S]*?\?>/g, 'fixture')
    .replace(/<\?php[\s\S]*?\?>/g, '')
  assert.doesNotThrow(() => new vm.Script(script))
})

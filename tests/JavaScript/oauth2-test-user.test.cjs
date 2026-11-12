const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

// Execute the shipped renderers of the OAuth page "Test a user" panel with a small DOM adapter.
const source = readFileSync(join(__dirname, '../../app/pages/oauth.js.php'), 'utf8').replace(/\r\n/g, '\n')
const start = source.indexOf('    const oauth2TestLabels = {')
const end = source.indexOf("    $(document).on('click', '#oauth2-test-user',", start)
assert.ok(start >= 0 && end > start, 'Missing the Test a user renderers')
const renderers = source.slice(start, end)
  .replace(/<\?php echo json_encode\(\$lang->get\('([a-z_0-9]+)'\)[^?]*\?>/g, (match, key) => JSON.stringify('[' + key + ']'))
  .replace(/<\?php echo \$session->get\('key'\); \?>/g, 'key')
assert.ok(renderers.includes('<?php') === false, 'Unresolved PHP tag in the renderers')

class Node {
  constructor (tag, classes) {
    this.tag = tag
    this.classes = new Set(classes)
    this.attributes = {}
    this.children = []
  }

  text (value) {
    this.children = [{ text: String(value) }]
    return this
  }

  append (...nodes) {
    for (const node of nodes.flat()) {
      assert.ok(node instanceof Node || typeof node.text === 'string', 'Only nodes and text nodes are appended')
      this.children.push(node)
    }
    return this
  }

  addClass (names) {
    names.split(' ').filter(Boolean).forEach(name => this.classes.add(name))
    return this
  }

  removeClass (name) {
    this.classes.delete(name)
    return this
  }

  attr (name, value) {
    this.attributes[name] = String(value)
    return this
  }

  empty () {
    this.children = []
    return this
  }

  get textContent () {
    return this.children.map(child => child instanceof Node ? child.textContent : child.text).join('')
  }

  find (predicate) {
    const found = []
    for (const child of this.children) {
      if (child instanceof Node) {
        if (predicate(child)) found.push(child)
        found.push(...child.find(predicate))
      }
    }
    return found
  }
}

function page () {
  const panel = new Node('div', ['card', 'hidden'])
  const body = new Node('div', ['card-body'])
  const created = []
  const $ = selector => {
    if (selector === '#oauth2-test-results') return panel
    if (selector === '#oauth2-test-results-body') return body
    // Markup is only ever a constant empty element: any value would be text
    const template = /^<([a-z]+)(?: class="([^"<>]*)")?><\/\1>$/.exec(selector)
    assert.ok(template !== null, 'Markup built from a value: ' + selector)
    const node = new Node(template[1], (template[2] || '').split(' ').filter(Boolean))
    created.push(node)
    return node
  }
  const context = {
    $,
    document: { createTextNode: value => ({ text: String(value) }) },
    toastr: {},
    prepareExchangedData: () => { throw new Error('No request in these tests') }
  }
  vm.createContext(context)
  vm.runInContext(renderers + '\nthis.renderUsers = oauth2TestRenderUsers; this.renderGroups = oauth2TestRenderGroups;', context)

  return { context, panel, body, created }
}

const hostile = '<img src=x onerror=alert(1)>'

test('directory users are rendered as text, with their outcome, groups and roles', () => {
  const { context, panel, body } = page()

  context.renderUsers({
    notice: '2 users ' + hostile,
    users: [
      {
        userPrincipalName: 'jdoe@a.example',
        displayName: hostile,
        accepted: true,
        outcome: 'Accepted ' + hostile,
        groups: [
          { name: 'TeamPass-Users', id: 'g1', allowed: true },
          { name: '', id: 'g2', allowed: false }
        ],
        roles: ['Sales', hostile],
        fallbackRole: false
      },
      {
        userPrincipalName: 'jdoe@b.example',
        displayName: '',
        accepted: false,
        outcome: 'Refused',
        groups: [],
        roles: null,
        fallbackRole: false
      }
    ]
  })

  assert.equal(panel.classes.has('hidden'), false)
  assert.equal(body.children.length, 3)
  assert.equal(body.children[0].textContent, '2 users ' + hostile)

  const [first, second] = [body.children[1], body.children[2]]
  assert.equal(first.textContent.includes(hostile + ' - jdoe@a.example'), true)
  assert.equal(first.find(node => node.classes.has('text-success') && node.classes.has('font-weight-bold') === false).length, 1)
  assert.equal(first.find(node => node.classes.has('fa-check-circle')).length, 1)

  const allowed = first.find(node => node.attributes.title === 'g1')[0]
  assert.equal(allowed.classes.has('font-weight-bold'), true)
  assert.equal(allowed.textContent, 'TeamPass-Users ([oauth2_test_allowed_group])')
  assert.equal(first.find(node => node.attributes.title === 'g2')[0].textContent, 'g2 ([oauth2_test_group_without_name])')
  assert.equal(first.textContent.includes('[oauth2_test_roles_at_creation]: Sales, ' + hostile), true)

  // No display name: the UPN alone; refused; no group; no role line for an existing account
  assert.equal(second.children[0].textContent, 'jdoe@b.example')
  assert.equal(second.find(node => node.classes.has('fa-times-circle')).length, 1)
  assert.equal(second.textContent.includes('[oauth2_test_groups]: [none]'), true)
  assert.equal(second.textContent.includes('[oauth2_test_roles_at_creation]'), false)
})

test('the fallback role is named as such', () => {
  const { context, body } = page()

  context.renderUsers({
    notice: '',
    users: [{ userPrincipalName: 'j@a', displayName: '', accepted: true, outcome: 'ok', groups: [], roles: ['Fallback'], fallbackRole: true }]
  })

  assert.equal(body.children.length, 1)
  assert.equal(body.textContent.includes('[oauth2_test_roles_at_creation]: Fallback [oauth2_test_fallback_role]'), true)
})

test('group entries show found, not found and ambiguous names as text', () => {
  const { context, body } = page()

  context.renderGroups({
    notice: '',
    groups: [
      { entry: 'TeamPass-Users', found: true, message: '', matches: [{ id: 'g1', name: 'TeamPass-Users' }] },
      { entry: hostile, found: false, message: 'No group ' + hostile, matches: [] },
      { entry: 'Sales', found: true, message: '2 groups', matches: [{ id: 'g2', name: 'Sales' }, { id: 'g3', name: '' }] }
    ]
  })

  const items = body.children[0].children
  assert.equal(items.length, 3)
  assert.equal(items[0].find(node => node.classes.has('fa-check-circle')).length, 1)
  assert.equal(items[1].find(node => node.classes.has('fa-times-circle')).length, 1)
  assert.equal(items[1].textContent, hostile + 'No group ' + hostile)
  assert.equal(items[2].find(node => node.classes.has('fa-exclamation-triangle')).length, 1)
  assert.equal(items[2].textContent, 'Sales : Sales (g2), g32 groups')
})

test('an answer with only a notice shows the notice', () => {
  const { context, body } = page()

  context.renderGroups({ notice: 'Empty list', groups: [] })

  assert.equal(body.children.length, 1)
  assert.equal(body.textContent, 'Empty list')
})

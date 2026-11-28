/**
 * Teampass - a collaborative passwords manager.
 *
 * This file is part of the TeamPass project and is licensed under GPL-3.0.
 * See the LICENSE file distributed with this project.
 */

const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const { test } = require('node:test')
const vm = require('node:vm')

const users = readFileSync(join(__dirname, '../../app/pages/users.js.php'), 'utf8').replace(/\r\n/g, '\n')
const helpers = readFileSync(join(__dirname, '../../public/assets/js/functions.js'), 'utf8').replace(/\r\n/g, '\n')

function section (source, from, to) {
  const start = source.indexOf(from)
  const end = source.indexOf(to, start)
  assert.ok(start >= 0 && end > start)
  return source.slice(start, end)
}

function harness (directoryUsers, defer = false) {
  const elements = new Map()
  const events = new Map()
  const requests = []
  const document = { on: (event, selector, handler) => events.set(event + selector, handler) }
  let rows = []

  function $ (selector) {
    if (selector === document || typeof selector === 'object') return selector
    if (selector === '#row-oauth2-body .oauth2-user-row') {
      return { length: rows.length, each: callback => rows.forEach((row, i) => callback(i, row)) }
    }
    if (selector === '#row-ldap-body .ldap-user-row') return { length: 0, each: () => {} }
    if (!elements.has(selector)) elements.set(selector, { value: '', hidden: true })
    const state = elements.get(selector)
    return {
      val (value) {
        if (value === undefined) return state.value
        state.value = value
        return this
      },
      html (value) {
        state.html = value
        if (selector === '#row-oauth2-body') {
          rows = Array.from(value.matchAll(/<tr class="oauth2-user-row" data-search="([^"]*)">/g), match => ({
            hidden: false,
            attr: () => match[1].replace(/&(?:amp|lt|gt|quot|#39);/g, entity => ({
              '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&#39;': "'"
            })[entity]),
            toggleClass (name, hidden) {
              assert.equal(name, 'hidden')
              this.hidden = hidden
            }
          }))
        }
        return this
      },
      prop (name, value) {
        if (value === undefined) return state[name]
        state[name] = value
        return this
      },
      text (value) { state.text = value; return this },
      toggleClass (name, hidden) { state[name] = hidden },
      trigger (event) {
        events.get(event + selector)()
        return this
      },
      focus () { state.focused = true },
      addClass (name) { state[name] = true; return this },
      removeClass (name) { state[name] = false; return this },
      closest () { return this },
      remove () {},
      tooltip () {},
      empty () { return this },
      append () { return this }
    }
  }

  $.each = (entries, callback) => entries.forEach((entry, i) => callback(i, entry))
  $.post = (url, payload, done) => {
    assert.equal(payload.type, 'get_list_of_users_in_oauth2')
    const request = {
      payload,
      pending: true,
      fail (callback) { this.onFail = callback; return this },
      always (callback) {
        this.onComplete = callback
        if (!this.pending) callback()
        return this
      },
      resolve (data = {}) {
        if (!this.pending) return
        this.pending = false
        done({ error: false, ad_users: directoryUsers, ad_groups: [], teampass_groups: [], ...data })
        this.onComplete?.()
      },
      abort () {
        if (!this.pending) return
        this.pending = false
        this.aborted = true
        this.onFail(this, 'abort')
        this.onComplete()
      }
    }
    requests.push(request)
    if (!defer) request.resolve()
    return request
  }

  const render = section(users, 'function refreshListUsersOAuth2()', '     * Permits to add a role')
    .replace(/\n\s*\/\*\*\s*$/, '')
    .replace(/<\?php echo \$SETTINGS\['oauth2_enabled'\]; \?>/g, '1')
    .replace(/<\?php echo \$session->get\('key'\); \?>/g, 'key')
    .replace(/<\?php echo json_encode\(\$lang->get\('(\w+)'\)[^?]*\?>/g, (_, key) => JSON.stringify(key))
    .replace(/<\?php echo \$lang->get\('(\w+)'\); \?>/g, '$1')

  const context = vm.createContext({
    $, document,
    prepareExchangedData: (response, type, key, file, func, purify) => {
      assert.equal(purify, false)
      return response
    },
    purifyData: data => data,
    toastr: { info () {}, success () {}, error: message => assert.fail(message) }
  })
  vm.runInContext(section(helpers, 'const HTML_ESCAPES =', '/* Extend String') +
    section(helpers, 'const storageEntities =', '/**\n * Run DOMPurify') +
    section(users, 'function filterDirectoryUsersTable(', '//Launch the datatables pluggin') + render, context)

  return {
    elements,
    requests,
    refresh: context.refreshListUsersOAuth2,
    filterGroups: checked => $('#oauth2-users-allowed-groups').prop('checked', checked).trigger('change'),
    search: value => $('#oauth2-users-search').val(value).trigger('input'),
    clear: () => events.get('click#oauth2-users-search-clear')(),
    hidden: () => rows.map(row => row.hidden)
  }
}

test('OAuth2 searches the readable directory fields and reapplies the query after refresh', () => {
  const page = harness([
    {
      login: 'obrien', userInTeampass: 0, userPrincipalName: 'obrien@entra.test',
      displayName: 'Zoë O&#039;Brien &quot;Lead&quot; &lt;Ops&gt;',
      givenName: 'Zoë', surname: 'O&#039;Brien', mail: 'zoe@inbox.test',
      groups: [{ name: 'Équipe R&amp;D', id: null }]
    },
    {
      login: 'sam', userInTeampass: 0, userPrincipalName: 'sam@second.test',
      displayName: 'Sam', givenName: 'Sam', surname: null, mail: null, groups: []
    }
  ])
  page.refresh()

  for (const query of ['  zoË ', 'OBRIEN', "O'Brien", '"Lead"', '<Ops>', 'R&D', 'zoe@inbox.test', 'obrien@entra.test']) {
    page.search(query)
    assert.deepEqual(page.hidden(), [false, true], query)
  }
  page.refresh()
  assert.deepEqual(page.hidden(), [false, true])
  assert.match(page.elements.get('#row-oauth2-body').html, /&amp;lt;Ops&amp;gt;/)

  for (const entityFragment of ['amp', 'quot', '&#039;', '&lt;']) {
    page.search(entityFragment)
    assert.deepEqual(page.hidden(), [true, true])
    assert.equal(page.elements.get('#oauth2-users-search-no-results').hidden, false)
  }

  page.clear()
  assert.deepEqual(page.hidden(), [false, false])
  assert.equal(page.elements.get('#oauth2-users-search-clear').disabled, true)
  assert.equal(page.elements.get('#oauth2-users-search').focused, true)
})

test('OAuth2 group filtering replaces the previous request and keeps the scope on refresh', () => {
  const page = harness([], true)
  page.refresh()
  assert.equal(page.requests[0].payload.only_allowed_groups, '0')

  page.filterGroups(true)
  assert.equal(page.requests[0].aborted, true)
  assert.equal(page.requests[1].payload.only_allowed_groups, '1')
  page.requests[1].resolve({ group_status: 'Group R&D <Ops> was not found.' })
  page.requests[0].resolve({ group_status: 'Old result' })

  const status = page.elements.get('#oauth2-users-group-status')
  assert.equal(status.text, 'Group R&D <Ops> was not found.')
  assert.equal(status.hidden, false)

  page.refresh()
  assert.equal(page.requests[2].payload.only_allowed_groups, '1')
  page.requests[2].resolve()
  assert.equal(status.hidden, true)

  page.filterGroups(false)
  assert.equal(page.requests[3].payload.only_allowed_groups, '0')
})

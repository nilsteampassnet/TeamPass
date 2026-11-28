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

function harness (directoryUsers) {
  const elements = new Map()
  const events = new Map()
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
      prop (name, value) { state[name] = value },
      toggleClass (name, hidden) { state[name] = hidden },
      trigger (event) {
        events.get(event + selector)()
        return this
      },
      focus () { state.focused = true },
      addClass () { return this },
      removeClass () {},
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
    done({ error: false, ad_users: directoryUsers, ad_groups: [], teampass_groups: [] })
    return { fail () { return this }, always (callback) { callback() } }
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
    refresh: context.refreshListUsersOAuth2,
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

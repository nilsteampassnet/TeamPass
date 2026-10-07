/**
 * Teampass - a collaborative passwords manager.
 * Copyright 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See https://www.gnu.org/licenses/ and the licenses directory.
 */
const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const source = fs.readFileSync('app/pages/admin.js.php', 'utf8')
const controller = source.split('// ADMIN ACTIVITY CONTROLLER')[1].split('// END ADMIN ACTIVITY CONTROLLER')[0]

function harness(stored = null) {
    const elements = new Map()
    const handlers = new Map()
    const requests = []
    let persisted = stored
    const document = {}
    function $(selector) {
        if (selector === document) return {on(event, target, handler) { handlers.set(event + ':' + target, handler) }}
        if (typeof selector === 'object') return selector
        if (!elements.has(selector)) {
            elements.set(selector, {
                content: '', visible: true, position: 0, value: '5', properties: {},
                each() { return this }, html(value) { this.content = value; return this },
                append(value) { this.content += value; return this }, empty() { this.content = ''; return this },
                text(value) { this.content = String(value); return this },
                show() { this.visible = true; return this }, hide() { this.visible = false; return this },
                toggle(value) { this.visible = value; return this },
                prop(key, value) { if (value === undefined) return this.properties[key]; this.properties[key] = value; return this },
                val() { return this.value },
                scrollTop(value) { if (value === undefined) return this.position; this.position = value; return this },
                modal() { return this }
            })
        }
        return elements.get(selector)
    }
    $.post = (url, body) => {
        const callbacks = {}
        const request = {
            body, options: JSON.parse(body.data),
            done(fn) { callbacks.done = fn; return this },
            fail(fn) { callbacks.fail = fn; return this },
            always(fn) { callbacks.always = fn; return this },
            resolve(data) { callbacks.done(data); callbacks.always() },
            reject() { callbacks.fail({}, 'error'); callbacks.always() },
            abort() { callbacks.fail({}, 'abort'); callbacks.always() }
        }
        requests.push(request)
        return request
    }
    const context = vm.createContext({$, document, Set, JSON, Array, Number,
        localStorage: {getItem: () => persisted, setItem: (key, value) => { persisted = value }},
        adminActivityMessages: {kbEnabled: true, storageKey: 'user-1', key: 'session', empty: 'Empty',
            noCategories: 'Choose categories', error: 'Retry', failures: '#count# failures / #minutes# minutes',
            newEvents: '#count# new events', kb: 'KB', authentication: 'Auth', items: 'Items'},
        prepareExchangedData: raw => raw,
        escapeHtml: value => String(value).replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character])),
        formatTimeAgo: () => '1s', getActivityIcon: () => 'fas fa-eye',
        getActivitySourceHint: source => source === 'kb' ? '(KB)' : ''
    })
    vm.runInContext(controller, context)
    return {$, requests, handlers, run: code => vm.runInContext(code, context), persisted: () => persisted}
}
const row = (id, overrides = {}) => ({id: '1:' + id, cursor: [1000, 1, id], timestamp: 1000,
    user_login: 'alice', action: 'at_shown', action_text: 'viewed', source_type: 'item', channel: 'web', ...overrides})
const response = (rows, overrides = {}) => ({error: false, activities: rows, failed_count: 27,
    new_count: 0, since: 700, until: 1000, next_cursor: rows.at(-1)?.cursor || null, has_more: true, ...overrides})

test('defaults and saved categories are allow-listed, with empty selections preserved', () => {
    const defaults = harness()
    defaults.run('loadLiveActivity()')
    assert.deepEqual(defaults.requests[0].options.categories, ['changes', 'accesses', 'kb'])
    for (const stored of ['[]', '["failed","invalid",{}]', 'malformed']) {
        const h = harness(stored)
        h.run('loadLiveActivity()')
        const expected = stored === '[]' ? [] : stored === 'malformed'
            ? ['changes', 'accesses', 'kb'] : ['failed']
        assert.deepEqual(h.requests[0].options.categories, expected)
    }
})

test('compact failures count is independent of the ten displayed rows and text is escaped', () => {
    const h = harness('["failed"]')
    h.run('loadLiveActivity()')
    h.requests[0].resolve(response([row(1, {source_type: 'failed_auth', user_login: '<img onerror="bad">', reason: '<script>bad</script>'})]))
    assert.equal(h.$('#activity-failed-count').content, '27 failures / 5 minutes')
    assert.match(h.$('#live-activity-list').content, /&lt;img/)
    assert.match(h.$('#live-activity-list').content, /&lt;script/)
    assert.doesNotMatch(h.$('#live-activity-list').content, /<script>|<img/i)
})

test('compact rows distinguish API events while preserving Web and KB presentation', () => {
    const h = harness()
    const render = (overrides, expanded = false) => h.run('renderActivityRows(' + JSON.stringify([row(1, overrides)]) + ', ' + expanded + ')')
    const api = render({channel: 'api', item_label: '<Item>'})
    assert.match(api, /"<em>&lt;Item&gt;<\/em>"<small class="text-muted ml-1">\(API\)<\/small>/)
    assert.doesNotMatch(api, /Items ·|tp_src=api/)
    assert.doesNotMatch(render({channel: 'web'}), /\(API\)|Web|Items ·/)
    assert.doesNotMatch(render({channel: undefined}), /\(API\)/)
    assert.match(render({source_type: 'kb'}), /\(KB\)/)
    assert.match(render({channel: 'api', source_type: 'failed_auth', reason: 'Denied'}), /\(API\)/)
    assert.match(render({channel: 'api'}, true), /Items · API/)
    assert.doesNotMatch(render({channel: 'api'}, true), /\(API\)/)
})

test('expanded pagination uses the snapshot bounds and deduplicates equal-second rows', () => {
    const h = harness()
    h.run('initActivityPreferences(); adminActivityState.open = true; resetExpandedActivity()')
    h.requests[0].resolve(response([row(3), row(2)]))
    h.run('loadExpandedActivity("older")')
    assert.deepEqual(h.requests[1].options.before, [1000, 1, 2])
    assert.equal(h.requests[1].options.since, 700)
    assert.equal(h.requests[1].options.until, 1000)
    h.$('#activity-modal-scroll').position = 150
    h.requests[1].resolve(response([row(2), row(1)], {has_more: false, failed_count: 1}))
    assert.equal(h.run('adminActivityState.rows.length'), 3)
    assert.equal(h.$('#activity-modal-scroll').position, 150)
    assert.equal(h.$('#activity-modal-failed-count').content, '27 failures / 5 minutes')
    assert.equal(h.$('#activity-load-older').visible, false)
})

test('refresh announces new events while reading without replacing rows or moving scroll', () => {
    const h = harness()
    h.run('initActivityPreferences(); adminActivityState.open = true; resetExpandedActivity()')
    h.requests[0].resolve(response([row(2), row(1)]))
    const previous = h.$('#activity-modal-list').content
    h.$('#activity-modal-scroll').position = 250
    h.run('loadExpandedActivity("refresh")')
    assert.deepEqual(h.requests[1].options.after, [1000, 1, 2])
    h.requests[1].resolve(response([row(3), row(2)], {new_count: 1}))
    assert.equal(h.$('#activity-modal-list').content, previous)
    assert.equal(h.$('#activity-modal-scroll').position, 250)
    assert.equal(h.$('#activity-new-events').content, '1 new events')
    h.run('resetExpandedActivity()')
    assert.equal(h.$('#activity-modal-scroll').position, 0)
    h.requests[2].resolve(response([row(3)]))
    assert.equal(h.run('adminActivityState.rows[0].id'), '1:3')
})

test('top-of-list refresh rolls the time window; changing filters rejects stale responses', () => {
    const h = harness()
    h.run('initActivityPreferences(); adminActivityState.open = true; resetExpandedActivity()')
    h.requests[0].resolve(response([row(1)]))
    h.run('loadExpandedActivity("refresh")')
    assert.equal(h.requests[1].options.since, undefined)
    assert.equal(h.requests[1].options.after, undefined)
    h.run('adminActivityState.categories = ["failed"]; resetExpandedActivity()')
    h.requests[1].resolve(response([row(999)]))
    assert.equal(h.run('adminActivityState.rows.length'), 0)
    assert.equal(h.run('adminActivityState.busy'), true)
    h.requests[2].resolve(response([row(2, {source_type: 'failed_auth'})]))
    assert.equal(h.run('adminActivityState.rows[0].id'), '1:2')
    assert.equal(h.run('adminActivityState.busy'), false)
})

test('refresh counts only while reading history, including scrolling during a request', () => {
    const h = harness()
    h.run('initActivityPreferences(); adminActivityState.open = true; resetExpandedActivity()')
    h.requests[0].resolve(response([row(2), row(1)]))
    const previous = h.$('#activity-modal-list').content
    h.run('loadExpandedActivity("refresh")')
    assert.equal(h.requests[1].options.after, undefined)
    h.$('#activity-modal-scroll').position = 200
    h.requests[1].resolve(response([row(3)]))
    assert.equal(h.$('#activity-modal-list').content, previous)
    assert.equal(h.$('#activity-modal-scroll').position, 200)
    h.run('loadExpandedActivity("refresh")')
    assert.deepEqual(h.requests[2].options.after, [1000, 1, 2])
    h.requests[2].resolve(response([row(3)], {new_count: 1}))
    assert.equal(h.$('#activity-new-events').visible, true)
})

test('closing the modal rejects late responses and failures allow retry', () => {
    const h = harness()
    h.run('initActivityPreferences(); adminActivityState.open = true; resetExpandedActivity()')
    h.handlers.get('hidden.bs.modal:#activity-modal')()
    h.requests[0].resolve(response([row(999)]))
    assert.equal(h.run('adminActivityState.rows.length'), 0)
    h.run('adminActivityState.open = true; resetExpandedActivity()')
    h.requests[1].reject()
    assert.equal(h.run('adminActivityState.busy'), false)
    assert.equal(h.$('#activity-modal-error').visible, true)
    h.run('loadExpandedActivity("refresh")')
    h.requests[2].resolve(response([]))
    assert.equal(h.$('#activity-modal-error').visible, false)
})

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

const source = fs.readFileSync('app/pages/emails.js.php', 'utf8')
const controller = source.slice(source.indexOf('function updateEmailTransportPanels()'),
    source.indexOf("$(document).on('click', '.button'"))
const admin = fs.readFileSync('app/pages/admin.js.php', 'utf8')
const autosave = admin.slice(admin.indexOf("$(document).on('change', '.form-control-sm:not(.select2[multiple])"),
    admin.indexOf('function saveFieldValue('))

function harness() {
    const handlers = []
    const saves = []
    const requests = []
    const document = {}
    const elements = new Map()
    const markup = fs.readFileSync('app/pages/emails.php', 'utf8')
    const debugTag = markup.match(/<select[^>]*id='email_debug_level'[^>]*>/)[0]
    const debugClasses = debugTag.match(/class='([^']*)'/)[1].split(/\s+/)
    const field = (id, value, profile, classes = []) => {
        const element = {id, value, profile, type: 'select-one', classes}
        elements.set('#' + id, element)
        return element
    }
    const transport = field('email_transport', 'gmail_api', true, ['no-save'])
    field('email_smtp_auth_mode', 'none', true, ['no-save'])
    const debug = field('email_debug_level', '0', false, debugClasses)
    function $(selector) {
        if (selector === document) return {on(event, target, callback) { handlers.push({event, target, callback}) }}
        let list = []
        if (typeof selector === 'object') list = [selector]
        else if (selector.includes('#email-transport-profile ') && selector.includes('input')) {
            list = [...elements.values()].filter(element => element.profile)
            if (selector.includes('#email_debug_level')) list.push(debug)
        } else if (elements.has(selector)) list = [elements.get(selector)]
        return {
            on(event, callback) { handlers.push({event, target: selector, callback}); return this },
            val(value) { if (value === undefined) return list[0]?.value; list.forEach(element => { element.value = value }); return this },
            prop(key, value) { list.forEach(element => { element[key] = value }); return this },
            attr(key) { return key === 'id' ? list[0]?.id : 'saved-revision' },
            hasClass(name) { return list[0]?.classes?.includes(name) || false },
            each(callback) { list.forEach(element => callback.call(element)); return this },
            text() { return this }, data() {}, filter() { return this }
        }
    }
    $.post = (url, body) => {
        requests.push({url, payload: JSON.parse(body.data)})
        const pending = {done() { return pending }, fail() { return pending }, always() { return pending }}
        return pending
    }
    const context = vm.createContext({$, document, JSON, String,
        emailProfileText: {}, emailProfileKey: 'session',
        saveFieldValue: ($field, id) => saves.push({id, value: $field.val()}),
        prepareExchangedData: value => value})
    vm.runInContext('let emailProfileDirty = false;\n' + controller + '\n' + autosave, context)
    function change(element) {
        for (const handler of handlers) {
            if (!handler.event.includes('change')) continue
            const matches = handler.target.split(',').some(selector => {
                selector = selector.trim()
                return selector === '#' + element.id
                    || (selector === '#email-transport-profile :input' && element.profile)
                    || (selector.startsWith('.form-control-sm:') && element.classes.includes('form-control-sm'))
            })
            if (matches) handler.callback.call(element)
        }
    }
    return {change, debug, transport, saves, requests,
        dirty: () => vm.runInContext('emailProfileDirty', context),
        save: () => handlers.find(handler => handler.target === '#email-profile-save').callback()}
}

test('changing debug autosaves without marking Gmail configuration dirty or requesting consent', () => {
    const page = harness()
    page.debug.value = '3'
    page.change(page.debug)
    assert.deepEqual(page.saves, [{id: 'email_debug_level', value: '3'}])
    assert.equal(page.dirty(), false)
    assert.equal(page.requests.length, 0)
})

test('profile changes still require saving and exclude the independently saved debug level', () => {
    const page = harness()
    page.change(page.transport)
    assert.equal(page.dirty(), true)
    assert.equal(page.saves.length, 0)
    page.save()
    assert.equal(page.requests.length, 1)
    assert.equal(page.requests[0].url, 'sources/emails_transport.queries.php')
    assert.equal(page.requests[0].payload.profile.email_transport, 'gmail_api')
    assert.equal(Object.hasOwn(page.requests[0].payload.profile, 'email_debug_level'), false)
    assert.equal(page.debug.disabled, undefined)
})

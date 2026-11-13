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

function harness(hostLabel = 'SMTP host R&D <label>') {
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
    const host = field('email_smtp_server', 'tls://private-host', true, ['no-save'])
    const status = field('email-profile-status', '', false)
    const labels = [{attributes: {for: 'email_smtp_server'}, content: hostLabel}]
    const debug = field('email_debug_level', '0', false, debugClasses)
    function $(selector) {
        if (selector === document) return {on(event, target, callback) { handlers.push({event, target, callback}) }}
        let list = []
        if (typeof selector === 'object') list = [selector]
        else if (selector === '#email-transport-profile label[for]') list = labels
        else if (selector.includes('#email-transport-profile ') && selector.includes('input')) {
            list = [...elements.values()].filter(element => element.profile)
            if (selector.includes('#email_debug_level')) list.push(debug)
        } else if (elements.has(selector)) list = [elements.get(selector)]
        return {
            on(event, callback) { handlers.push({event, target: selector, callback}); return this },
            val(value) { if (value === undefined) return list[0]?.value; list.forEach(element => { element.value = value }); return this },
            prop(key, value) { list.forEach(element => { element[key] = value }); return this },
            attr(key, value) {
                if (value !== undefined) { list.forEach(element => { (element.attributes ||= {})[key] = value }); return this }
                return key === 'id' ? list[0]?.id : key === 'data-revision' ? 'saved-revision' : list[0]?.attributes?.[key]
            },
            removeAttr(key) { list.forEach(element => { if (element.attributes) delete element.attributes[key] }); return this },
            addClass(name) { list.forEach(element => { element.classes.push(name) }); return this },
            removeClass(name) { list.forEach(element => { element.classes = element.classes.filter(value => value !== name) }); return this },
            hasClass(name) { return list[0]?.classes?.includes(name) || false },
            each(callback) { list.forEach(element => callback.call(element)); return this },
            text(value) { if (value === undefined) return list[0]?.content || ''; list.forEach(element => { element.content = value }); return this },
            data() {}, filter() { return this }
        }
    }
    $.post = (url, body) => {
        const callbacks = {}
        const pending = {
            done(callback) { callbacks.done = callback; return pending },
            fail(callback) { callbacks.fail = callback; return pending },
            always(callback) { callbacks.always = callback; return pending },
            resolve(response) { callbacks.done(response); callbacks.always() }
        }
        requests.push({url, payload: JSON.parse(body.data), pending})
        return pending
    }
    const context = vm.createContext({$, document, JSON, String,
        emailProfileText: {failed: 'Save failed', invalidField: 'Check field: #field#'}, emailProfileKey: 'session',
        decodeQueryReturn: response => response,
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
    return {change, debug, transport, host, status, saves, requests,
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

test('a validation error names and highlights the field without displaying its value or raw error', () => {
    const page = harness()
    page.change(page.host)
    page.save()
    page.requests[0].pending.resolve({error: true, field: 'email_smtp_server', message: 'private-error'})
    assert.equal(page.status.content, 'Check field: SMTP host R&D <label>')
    assert.equal(page.host.attributes['aria-invalid'], 'true')
    assert.ok(page.host.classes.includes('is-invalid'))
    assert.equal(page.host.disabled, false)
    assert.equal(page.dirty(), true)
    page.change(page.host)
    assert.equal(page.host.classes.includes('is-invalid'), false)
    assert.equal(Object.hasOwn(page.host.attributes, 'aria-invalid'), false)
})

test('unknown fields and undecodable responses keep the generic error without exposing raw text', () => {
    for (const response of [false, {error: true, field: 'private-secret', message: 'private-error'},
        {error: true, field: 'email_smtp_server\"]<img src=x>', message: 'private-error'}]) {
        const page = harness()
        page.save()
        page.requests[0].pending.resolve(response)
        assert.equal(page.status.content, 'Save failed')
        assert.equal(page.host.classes.includes('is-invalid'), false)
    }
})

test('field labels keep dollar replacement patterns and punctuation literally', () => {
    const label = "SMTP $& $1 $$ R&D O'Brien <label>"
    const page = harness(label)
    page.save()
    page.requests[0].pending.resolve({error: true, field: 'email_smtp_server'})
    assert.equal(page.status.content, 'Check field: ' + label)
})

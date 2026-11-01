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
const source = fs.readFileSync('app/pages/backups.js.php', 'utf8')
const controller = source.match(/function openRecoveryPackageTab\(\) \{[\s\S]*?\n    \}/)[0]

test('the admin action opens the Recovery Package tab after the document loads', () => {
    const opened = []
    const context = {window: {location: {hash: '#recovery-package'}},
        $: selector => ({tab: action => opened.push([selector, action])})}
    vm.runInNewContext(controller + '\nopenRecoveryPackageTab()', context)
    assert.deepEqual(opened, [['#recovery-package-tab', 'show']])
})

test('ordinary visits and unrecognized fragments preserve the default tab', () => {
    for (const hash of ['', '#scheduled', '#recovery-package-other', '#" onclick="alert(1)']) {
        vm.runInNewContext(controller + '\nopenRecoveryPackageTab()', {
            window: {location: {hash}}, $: () => assert.fail('unexpected tab selection')
        })
    }
})

const informationRenderer = source.match(/function renderRecoveryPackageInformation\(info\) \{[\s\S]*?\n    \}/)[0]
const refresher = source.match(/refreshRecoveryPackageInformation: function\(expectedAt, remaining\) \{[\s\S]*?\n  \},/)[0]
    .replace(/<\?php[\s\S]*?\?>/g, "'fixture-key'")

test('download information is inserted as text, including quotes and angle brackets', () => {
    const labels = new Map()
    const info = {download_label: 'R&D O\'Brien "<img src=x>"', status_label: '<script>unsafe()</script>'}
    vm.runInNewContext(informationRenderer + '\nrenderRecoveryPackageInformation(info)', {
        info, $: selector => ({text: value => labels.set(selector, value)})
    })
    assert.equal(labels.get('#recovery-package-download-date'), info.download_label)
    assert.equal(labels.get('#recovery-package-download-state'), info.status_label)
})

function refreshHarness(responses) {
    const labels = []
    const scheduled = []
    const requests = []
    const context = {
        $: {post(url, data, callback) { requests.push(data); callback(JSON.stringify(responses.shift())) }},
        tpSafePrepareDecode: JSON.parse,
        renderRecoveryPackageInformation: info => labels.push(info),
        setTimeout: callback => scheduled.push(callback)
    }
    vm.createContext(context)
    vm.runInContext('var tpExternalized = {' + refresher + '}', context)
    return {context, labels, scheduled, requests}
}

test('the date refresh waits for the actual download receipt and then stops', () => {
    const h = refreshHarness([{downloaded_at: 0}, {downloaded_at: 123}])
    h.context.tpExternalized.refreshRecoveryPackageInformation(123, 10)
    assert.equal(h.scheduled.length, 1)
    h.scheduled.shift()()
    assert.equal(h.scheduled.length, 0)
    assert.equal(h.labels.at(-1).downloaded_at, 123)
    assert.equal(h.requests[0].type, 'recovery_package_status')
    assert.equal(h.requests[0].key, 'fixture-key')
})

test('an unacknowledged download has a bounded refresh budget', () => {
    const h = refreshHarness([{downloaded_at: 0}, {downloaded_at: 0}])
    h.context.tpExternalized.refreshRecoveryPackageInformation(123, 1)
    h.scheduled.shift()()
    assert.equal(h.scheduled.length, 0)
    assert.equal(h.requests.length, 2)
})

test('authorization and malformed-response failures cannot replace the displayed date', () => {
    const h = refreshHarness([{error: true}])
    h.context.tpExternalized.refreshRecoveryPackageInformation(123, 10)
    assert.equal(h.labels.length, 0)
    assert.equal(h.scheduled.length, 0)
})

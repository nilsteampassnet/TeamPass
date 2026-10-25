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

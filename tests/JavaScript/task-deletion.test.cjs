/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const { test } = require('node:test')
const { runInNewContext } = require('node:vm')

const source = readFileSync(join(__dirname, '../../app/pages/tasks.js.php'), 'utf8')
const handler = source.slice(source.indexOf('// Handle delete task'), source.indexOf('$("#modal-btn-cancel")'))
    .replace(/<\?php echo json_encode\(\$lang->get\('([^']+)'\).*?\?>/g, (_, key) => JSON.stringify(key))
    .replace(/<\?php[\s\S]*?\?>/g, '')

function deleteTask(response, { networkError = false, decodeError = false } = {}) {
    const notifications = []
    let reloads = 0
    let click
    const $ = () => ({
        data() { return 123 },
        on(_event, callback) { click = callback },
        modal() {}
    })
    $.post = (_url, data, success) => {
        assert.equal(data.type, 'task_delete')
        assert.equal(data.id, 123)
        if (!networkError) success(response)
        return { fail(callback) { if (networkError) callback() } }
    }
    runInNewContext(handler, {
        $,
        toastr: {
            remove() {},
            info() {},
            success: message => notifications.push(['success', message]),
            error: message => notifications.push(['error', message])
        },
        oTableProcesses: { ajax: { reload() { reloads++ } } },
        prepareExchangedData(data) {
            if (decodeError) throw new Error('Malformed response')
            return data
        },
        htmlEncode: value => value.replaceAll('<', '&lt;').replaceAll('>', '&gt;')
    })
    click()
    assert.equal(reloads, 1)
    return notifications
}

test('a refused deletion displays the escaped reason and no success notification', () => {
    assert.deepEqual(deleteTask({ error: true, message: 'Invitation <already running>' }),
        [['error', 'Invitation &lt;already running&gt;']])
})

test('deletion shows success only after explicit confirmation', () => {
    assert.deepEqual(deleteTask({ error: false }), [['success', 'done']])
    assert.deepEqual(deleteTask({}), [['error', 'an_error_occurred']])
    assert.deepEqual(deleteTask(null, { decodeError: true }), [['error', 'an_error_occurred']])
    assert.deepEqual(deleteTask(null, { networkError: true }), [['error', 'an_error_occurred']])
})

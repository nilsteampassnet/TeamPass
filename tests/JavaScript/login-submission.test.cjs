const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

// Execute the shipped login functions and event handlers, with controlled form,
// transport and navigation adapters. No credentials leave this process.
const template = readFileSync(join(__dirname, '../../app/core/login.js.php'), 'utf8')
// The template currently has no ?> inside PHP string literals. This lightweight
// substitution is intentionally not a PHP parser and relies on that constraint.
const renderedTemplate = template.replace(/\r\n/g, '\n').replace(/<\?php[\s\S]*?\?>/g, php => {
  const translation = php.match(/\$lang->get\('([^']+)'\)/)
  if (translation) return php.includes('json_encode(') ? JSON.stringify(translation[1]) : translation[1]
  return php.includes('echo ') ? 'null' : ''
}).trim()

// Extract the known wrapper from this repository-owned template. This is not an
// HTML sanitizer: unexpected markup must fail the tests instead of being removed.
const scriptOpen = '<script type="text/javascript">'
const scriptClose = '</script>'
assert.ok(renderedTemplate.startsWith(scriptOpen), 'Unexpected login script opening tag')
assert.ok(renderedTemplate.endsWith(scriptClose), 'Unexpected login script closing tag')
const source = renderedTemplate.slice(scriptOpen.length, -scriptClose.length)

function section(start, end, scriptSource = source) {
  const from = scriptSource.indexOf(start)
  const to = scriptSource.indexOf(end, from)
  assert.ok(from >= 0 && to > from, `Missing login source section: ${start}`)
  return scriptSource.slice(from, to)
}

function createLogin(loginSource = source) {
  const nodes = new Map()
  const requests = []
  const notices = []
  const timers = []
  const storage = new Map()
  const sessionStorage = new Map()
  const navigation = { href: '', reloads: 0, reload() { this.reloads++ } }
  let focused = null
  let nonce = 0
  let elapsed = 0
  function field(id, value = '', tag = 'input', type = 'text', classes = []) {
    const node = { id, value, tag, type, readOnly: false, disabled: false, checked: false,
      attrs: {}, classes: new Set(classes), handlers: {}, dataset: {}, inLoginBox: true }
    nodes.set(id, node)
    return node
  }
  field('login', 'analysis5365')
  field('pw', 'Dummy-À<&"5365', 'input', 'password', ['submit-button'])
  field('session_duration', '60', 'input', 'text', ['submit-button'])
  for (const id of ['ga_code', 'yubico_key', 'yubico_user_id', 'yubico_user_key',
    '2fa_user_selection', 'duo_code', 'duo_state']) field(id, '', 'input', 'text', ['submit-button'])
  for (const id of ['but_identify_user', 'but_login_with_oauth2', 'forgot-local-password-link',
    'send-temporary-code']) field(id, '', 'button')
  for (const method of ['google', 'yubico', 'duo']) {
    const radio = field(`radio-${method}`, '', 'input', 'radio', ['2fa_selector_select'])
    radio.dataset.mfa = method
    // radiosforbuttons moves the original radios and creates visible spans.
    radio.inLoginBox = false
    field(`method-${method}`, method, 'span', '', ['btn', 'radiosforbuttons-2fa_selector_select'])
  }
  field('login-box', '', 'div', '', ['login-box'])

  function select(selector) {
    if (typeof selector === 'object') return [selector]
    return [...new Set(selector.split(',').flatMap(part => {
      const query = part.trim()
      if (query === 'body') return [fieldIfMissing('body')]
      if (query.startsWith('#')) return [fieldIfMissing(query.slice(1))]
      return [...nodes.values()].filter(node => {
        if (query.startsWith('.login-box ') && !node.inLoginBox) return false
        if (query.includes(':enabled') && node.disabled) return false
        if (query.includes(':checked') && !node.checked) return false
        if (query.includes('input[type="radio"]')) return node.type === 'radio'
        if (query === '.login-box button:enabled') return node.tag === 'button'
        if (query.startsWith('input[name=2fa_selector_select]')) return node.type === 'radio'
        const className = query.match(/^\.([\w-]+)/)
        return className && node.classes.has(className[1])
      })
    }))]
  }
  function fieldIfMissing(id) { return nodes.get(id) || field(id, '', 'div') }
  function collection(elements) {
    const api = {
      length: elements.length,
      val(value) {
        if (value === undefined) return elements[0]?.value
        elements.forEach(node => { node.value = value })
        return api
      },
      prop(name, value) {
        if (value === undefined) return elements[0]?.[name]
        elements.forEach(node => { node[name] = value })
        return api
      },
      attr(name, value) {
        elements.forEach(node => { node.attrs[name] = value })
        return api
      },
      filter(selector) {
        assert.equal(selector, ':not([readonly])')
        return collection(elements.filter(node => !node.readOnly))
      },
      on(event, callback) {
        elements.forEach(node => { node.handlers[event] = callback })
        return api
      },
      off(event) { elements.forEach(node => { delete node.handlers[event] }); return api },
      click(callback) { return api.on('click', callback) },
      keypress(callback) { return api.on('keypress', callback) },
      change(callback) { return api.on('change', callback) },
      focus() { if (elements[0] && !elements[0].disabled) focused = elements[0].id; return api },
      addClass(name) { elements.forEach(node => node.classes.add(name)); return api },
      removeClass(name) { elements.forEach(node => node.classes.delete(name)); return api },
      show() { elements.forEach(node => { node.hidden = false }); return api },
      hide() { elements.forEach(node => { node.hidden = true }); return api },
      html() { return api }, text() { return elements[0]?.value }, innerHeight() { return 900 },
      data(name) { return elements[0]?.dataset[name] }, is() { return !!elements[0]?.checked },
      radiosforbuttons() { return api }, index(node) { return elements.indexOf(node) },
      eq(index) { return collection(elements.slice(index, index + 1)) }
    }
    return api
  }
  const $ = selector => collection(select(selector))
  $.inArray = (value, array) => array.indexOf(value)
  $.when = value => Promise.resolve(value)
  function sendRequest(url, body, timeout = 0) {
    let resolve, reject
    const promise = new Promise((accept, fail) => { resolve = accept; reject = fail })
    const request = { url, body, timeout, deadline: elapsed + timeout, settled: false, aborted: false }
    const settle = callback => value => {
      if (request.settled) return
      request.settled = true
      callback(value)
    }
    request.resolve = settle(resolve)
    request.reject = settle(reject)
    requests.push(request)
    return promise
  }
  $.post = (url, body) => sendRequest(url, body)
  $.ajax = options => {
    assert.equal(options.type, 'POST')
    return sendRequest(options.url, options.data, options.timeout)
  }
  const window = { location: navigation, handlers: {} }
  const context = vm.createContext({ $, Date, Promise, encodeURIComponent, unescape,
    btoa: value => Buffer.from(value, 'binary').toString('base64'),
    store: {
      get: key => storage.get(key) ?? null,
      set: (key, value) => storage.set(key, value), remove: key => storage.delete(key),
      update(key, fallback, callback) { const value = storage.get(key) || fallback; callback(value); storage.set(key, value) }
    },
    sessionStorage: {
      getItem: key => sessionStorage.get(key) ?? null,
      setItem: (key, value) => sessionStorage.set(key, value), removeItem: key => sessionStorage.delete(key)
    },
    window, document: { location: navigation },
    toastr: Object.fromEntries(['remove', 'info', 'error', 'warning', 'success'].map(level =>
      [level, (...args) => notices.push({ level, args })])),
    CreateRandomString: () => `nonce-${++nonce}`,
    sanitizeString: value => value,
    safeParseJSONMaybe: value => {
      try { return { ok: true, value: JSON.parse(value) } } catch { return { ok: false, value } }
    },
    prepareExchangedData: (value, operation, key) => operation === 'encode'
      ? { payload: JSON.parse(value), key } : value,
    showModalDialogBox: () => notices.push({ level: 'refresh-dialog' }),
    setTimeout: callback => { timers.push(callback); return timers.length },
    setInterval: callback => { timers.push(callback); return timers.length }, clearInterval() {}
  })
  // Compile the complete template too, including code outside the exercised sections.
  new vm.Script(loginSource)
  vm.runInContext(section('var debugJavascript', '$(function() {', loginSource), context)
  vm.runInContext(section('function launchIdentify(', 'function renderTotpQrCode(', loginSource), context)
  vm.runInContext(loginSource.slice(loginSource.indexOf('function showMFAMethodForUser(')), context)
  context.renderTotpQrCode = () => {}
  vm.runInContext(section("$('#but_identify_user').click(", 'const storedOauth2Info =', loginSource), context)
  vm.runInContext(section("$('.submit-button').keypress", "$(document).on('click', '#register-yubiko-key'", loginSource), context)
  vm.runInContext(section("$(window).on('pageshow'", 'var userOauth2Info =', loginSource), context)

  return {
    context, nodes, requests, notices, timers, navigation, storage, sessionStorage,
    get focused() { return focused },
    launch: (...args) => context.launchIdentify(false, '', '', ...args),
    enter(keyCode = 13) {
      let prevented = false
      nodes.get('pw').handlers.keypress({ keyCode, preventDefault() { prevented = true } })
      assert.equal(prevented, keyCode === 10 || keyCode === 13)
    },
    click(id = 'but_identify_user') { nodes.get(id).handlers.click() },
    advanceTime(milliseconds) {
      elapsed += milliseconds
      for (const request of requests) {
        if (!request.settled && request.timeout > 0 && request.deadline <= elapsed) {
          request.aborted = true
          request.reject(new Error('timeout'))
        }
      }
    },
    busy(expected) {
      assert.equal(nodes.get('but_identify_user').disabled, expected)
      assert.equal(nodes.get('pw').readOnly, expected)
      assert.equal(context.loginInProgress, expected)
      assert.equal(nodes.get('login-box').attrs['aria-busy'], String(expected))
    }
  }
}

const flush = () => new Promise(resolve => setImmediate(resolve))
const refusal = { error: true, message: 'Denied', primary_auth_failed: true }
function success(request, overrides = {}) {
  return { error: false, value: request.body.data.payload.randomstring, user_admin: 0,
    initial_url: '', session_key: 'authenticated-key', ...overrides }
}
async function askForMfa(app, method) {
  app.launch()
  await flush()
  app.requests.at(-1).resolve({ value: '2fa_not_set', error: '2fa_not_set',
    mfa_methods: { mfa_required: true, [method]: true } })
  await flush()
  app.busy(false)
}

test('rapid Enter, click and YubiKey events share one pending submission', async () => {
  const app = createLogin()
  app.enter()
  app.busy(true)
  app.enter(10)
  app.click()
  app.nodes.get('yubico_key').handlers.change({ preventDefault() {} })
  app.click('but_login_with_oauth2')
  await flush()
  assert.equal(app.requests.length, 1)
  assert.equal(app.navigation.href, '')
  const payload = app.requests[0].body.data.payload
  assert.equal(payload.login, 'analysis5365')
  assert.equal(Buffer.from(payload.pw, 'base64').toString('utf8'), app.nodes.get('pw').value)
  assert.equal(app.nodes.get('session_duration').readOnly, true)
  app.requests[0].resolve(refusal)
  await flush()
  app.busy(false)
  app.enter()
  await flush()
  assert.equal(app.requests.length, 2)
})

test('empty credentials and non-Enter keys do not lock or submit the form', async () => {
  const app = createLogin()
  app.enter(65)
  app.nodes.get('pw').value = ''
  assert.equal(app.launch(), false)
  await flush()
  assert.equal(app.context.loginInProgress, false)
  assert.equal(app.nodes.get('but_identify_user').disabled, false)
  assert.equal(app.requests.length, 0)
})

test('controls already read-only or disabled retain their initial state', async () => {
  const app = createLogin()
  app.nodes.get('login').readOnly = true
  app.nodes.get('send-temporary-code').disabled = true
  app.launch()
  await flush()
  app.requests[0].resolve(refusal)
  await flush()
  app.busy(false)
  assert.equal(app.nodes.get('login').readOnly, true)
  assert.equal(app.nodes.get('send-temporary-code').disabled, true)
})

for (const error of ['network', 'HTTP 500', 'timeout']) {
  test(`${error} failure releases the form and allows an immediate retry`, async () => {
    const app = createLogin()
    app.launch()
    await flush()
    app.requests[0].reject(new Error(error))
    await flush()
    app.busy(false)
    assert.ok(app.notices.some(notice => notice.level === 'error'))
    app.launch()
    await flush()
    assert.equal(app.requests.length, 2)
  })
}

for (const response of [refusal, { error: 'maintenance_mode_enabled' },
  { error: true, extra: 'ad_user_created' }, { error: true, extra: 'oauth2_user_created' },
  { error: true, extra: 'oauth2_user_not_found', primary_auth_failed: true }, null]) {
  test(`terminal response releases the form: ${JSON.stringify(response)}`, async () => {
    const app = createLogin()
    app.launch()
    await flush()
    app.requests[0].resolve(response)
    await flush()
    app.busy(false)
  })
}

test('a synchronous encryption exception releases the form', async () => {
  const app = createLogin()
  app.context.prepareExchangedData = () => { throw new Error('Encode failure') }
  app.launch()
  await flush()
  app.busy(false)
  assert.equal(app.requests.length, 0)
  assert.ok(app.notices.some(notice => notice.level === 'error'))
})

for (const alreadyRetried of [false, true]) {
  test(`decode failure ${alreadyRetried ? 'reports an error' : 'keeps the reload locked'}`, async () => {
    const app = createLogin()
    if (alreadyRetried) app.sessionStorage.set('teampassKeyResyncDone', '1')
    const prepare = app.context.prepareExchangedData
    app.context.prepareExchangedData = (value, operation, key) => {
      if (operation === 'decode') throw new Error('Decode failure')
      return prepare(value, operation, key)
    }
    app.launch()
    await flush()
    app.requests[0].resolve('bad response')
    await flush()
    app.busy(!alreadyRetried)
    assert.equal(app.navigation.reloads, alreadyRetried ? 0 : 1)
    assert.ok(!JSON.stringify([...app.storage]).includes('Dummy-'))
  })
}

for (const [overrides, url] of [
  [{}, './index.php?page=items'], [{ user_admin: 1 }, './index.php?page=admin'],
  [{ initial_url: './index.php?page=items&group=1' }, './index.php?page=items&group=1']
]) {
  test(`successful login stays locked until navigation: ${url}`, async () => {
    const app = createLogin()
    app.launch()
    await flush()
    app.requests[0].resolve(success(app.requests[0], overrides))
    await flush()
    app.busy(true)
    app.enter()
    assert.equal(app.requests.length, 1)
    assert.equal(app.navigation.href, url)
    assert.ok(!JSON.stringify([...app.storage]).includes('Dummy-'))
  })
}

for (const method of ['google', 'yubico']) {
  test(`${method} challenge unlocks the next factor and guards its submission`, async () => {
    const app = createLogin()
    await askForMfa(app, method)
    assert.equal(app.nodes.get('2fa_user_selection').value, method)
    assert.equal(app.focused, method === 'google' ? 'ga_code' : 'yubico_key')
    app.nodes.get('ga_code').value = '123456'
    app.nodes.get('yubico_key').value = 'dummy-otp'
    app.enter()
    app.click()
    await flush()
    assert.equal(app.requests.length, 2)
    assert.equal(app.requests[1].body.data.payload.user_2fa_selection, method)
    app.busy(true)
    app.requests[1].resolve({ error: true, ga_bad_code: true, message: 'Wrong second factor' })
    await flush()
    app.busy(false)
    app.nodes.get('yubico_key').value = 'next-dummy-otp'
    app.enter()
    await flush()
    assert.equal(app.requests[2].body.data.payload.user_2fa_selection, method)
  })
}

test('missing YubiKey input releases a locally rejected attempt', async () => {
  const app = createLogin()
  await askForMfa(app, 'yubico')
  app.launch()
  await flush()
  app.busy(false)
  assert.equal(app.requests.length, 1)
})

test('MFA method buttons cannot change the selected factor during submission', async () => {
  const app = createLogin()
  app.launch()
  await flush()
  app.requests[0].resolve({ error: '2fa_not_set', mfa_methods: { mfa_required: true, google: true, duo: true } })
  await flush()
  const googleButton = app.nodes.get('method-google')
  googleButton.handlers.click.call(googleButton)
  app.nodes.get('ga_code').value = '123456'
  app.launch()
  const duoButton = app.nodes.get('method-duo')
  assert.equal(duoButton.tag, 'span')
  app.busy(true)
  duoButton.handlers.click.call(duoButton)
  await flush()
  assert.equal(app.requests[1].body.data.payload.user_2fa_selection, 'google')
  app.requests[1].resolve({ error: true, message: 'Wrong second factor' })
  await flush()
  app.busy(false)
  duoButton.handlers.click.call(duoButton)
  assert.equal(app.nodes.get('2fa_user_selection').value, 'duo')
})

test('first Google enrollment leaves the code input usable', async () => {
  const app = createLogin()
  await askForMfa(app, 'google')
  app.nodes.get('ga_code').value = 'temporary-code'
  app.launch()
  await flush()
  app.requests[1].resolve({ error: false, mfaStatus: 'ga_temporary_code_correct', qr_text: 'dummy-uri' })
  await flush()
  app.busy(false)
  assert.equal(app.nodes.get('ga_code').value, '')
  assert.equal(app.focused, 'ga_code')
})

// Issue #5401: Duo needs no input on the login page, so a single click must reach it.
async function askForSingleDuo(app) {
  app.launch()
  await flush()
  app.requests[0].resolve({ value: '2fa_not_set', error: '2fa_not_set',
    mfa_methods: { mfa_required: true, duo: true } })
  await flush()
}

test('a single Duo method starts Duo within the same locked attempt', async () => {
  const app = createLogin()
  await askForSingleDuo(app)
  assert.equal(app.requests.length, 2)
  const first = app.requests[0].body.data.payload
  const start = app.requests[1].body.data.payload
  assert.equal(start.user_2fa_selection, 'duo')
  assert.equal(start.duo_status, 'start_duo_auth')
  assert.equal(start.login, first.login)
  assert.equal(start.pw, first.pw)
  app.busy(true)
  app.enter()
  app.click()
  await flush()
  assert.equal(app.requests.length, 2)
  app.requests[1].resolve({ error: false, duo_url_ready: true, duo_redirect_url: 'https://example.test/duo' })
  await flush()
  app.busy(true)
  app.enter()
  assert.equal(app.requests.length, 2)
  app.timers[0]()
  assert.equal(app.navigation.href, 'https://example.test/duo')
})

test('a failed Duo start releases the form and one click retries it', async () => {
  const app = createLogin()
  await askForSingleDuo(app)
  app.requests[1].resolve({ error: true, mfa_error: true, message: 'Duo unavailable' })
  await flush()
  app.busy(false)
  assert.equal(app.requests.length, 2)
  app.click()
  await flush()
  assert.equal(app.requests.length, 3)
  assert.equal(app.requests[2].body.data.payload.duo_status, 'start_duo_auth')
})

test('a Duo start answered by another MFA challenge is not chained again', async () => {
  const app = createLogin()
  await askForSingleDuo(app)
  app.requests[1].resolve({ value: '2fa_not_set', error: '2fa_not_set',
    mfa_methods: { mfa_required: true, duo: true } })
  await flush()
  assert.equal(app.requests.length, 2)
  app.busy(false)
})

test('several MFA methods wait for a choice, even after a Duo callback', async () => {
  const app = createLogin()
  // The Duo callback page pre-fills the selection with 'duo'.
  app.nodes.get('2fa_user_selection').value = 'duo'
  app.launch()
  await flush()
  app.requests[0].resolve({ value: '2fa_not_set', error: '2fa_not_set',
    mfa_methods: { mfa_required: true, google: true, duo: true } })
  await flush()
  assert.equal(app.requests.length, 1)
  app.busy(false)
})

test('Duo callback can submit empty displayed credentials and recover from failure', async () => {
  const app = createLogin()
  app.nodes.get('login').value = ''
  app.nodes.get('pw').value = ''
  app.nodes.get('duo_code').value = 'dummy-code'
  app.nodes.get('duo_state').value = 'dummy-state'
  app.context.launchIdentify(true, '', '')
  app.enter()
  await flush()
  assert.equal(app.requests.length, 1)
  assert.equal(app.requests[0].body.data.payload.duo_code, 'dummy-code')
  app.requests[0].resolve(refusal)
  await flush()
  app.busy(false)
  assert.equal(app.nodes.get('login').disabled, false)
})

test('OAuth2 navigation shares the guard, even with empty credentials', async () => {
  const app = createLogin()
  app.nodes.get('pw').value = ''
  app.click('but_login_with_oauth2')
  app.enter()
  app.busy(true)
  await flush()
  assert.equal(app.requests.length, 0)
  assert.equal(app.navigation.href, 'includes/core/login.oauth2.php')
})

test('returning to a cached provider redirect page resynchronizes the server session', () => {
  const app = createLogin()
  app.context.window.handlers.pageshow({ originalEvent: { persisted: true } })
  assert.equal(app.navigation.reloads, 0)
  app.click('but_login_with_oauth2')
  app.context.window.handlers.pageshow({ originalEvent: { persisted: false } })
  assert.equal(app.navigation.reloads, 0)
  app.context.window.handlers.pageshow({ originalEvent: { persisted: true } })
  assert.equal(app.navigation.reloads, 1)
})

test('a repeated button event cannot clear a pending OAuth2 callback context', async () => {
  const app = createLogin()
  app.storage.set('userOauth2Info', { login: 'oauth-user', oauth2LoginOngoing: true })
  app.launch()
  app.click()
  await flush()
  assert.equal(app.requests[0].body.data.payload.oauth2LoginOngoing, true)
  assert.equal(app.requests[0].body.login, 'oauth-user')
})

test('session renewal and credential replay remain one locked attempt', async () => {
  const app = createLogin()
  app.launch()
  await flush()
  const original = app.requests[0].body.data.payload
  app.requests[0].resolve('ERROR SESSION EXPIRED')
  await flush()
  app.busy(true)
  assert.equal(app.requests[1].body.type, 'refresh_session_key')
  app.enter()
  app.click()
  assert.equal(app.requests.length, 2)
  app.requests[1].resolve(JSON.stringify({ key: 'renewed-key' }))
  await flush()
  app.busy(true)
  assert.equal(app.requests.length, 3)
  assert.deepEqual(app.requests[2].body.data.payload, original)
  assert.equal(app.requests[2].body.data.key, 'renewed-key')
  app.requests[2].resolve(refusal)
  await flush()
  app.busy(false)
})

for (const failure of ['network', 'null', '{}', '{"key":123}', '{"key":""}']) {
  test(`failed key recovery keeps the refresh dialog locked: ${failure}`, async () => {
    const app = createLogin()
    app.launch()
    await flush()
    app.requests[0].resolve('ERROR SESSION EXPIRED')
    await flush()
    if (failure === 'network') app.requests[1].reject(new Error('Network failure'))
    else app.requests[1].resolve(failure)
    await flush()
    app.busy(true)
    assert.ok(app.notices.some(notice => notice.level === 'refresh-dialog'))
    app.enter()
    assert.equal(app.requests.length, 2)
  })
}

test('a second stale response opens the refresh dialog instead of looping', async () => {
  const app = createLogin()
  app.launch()
  await flush()
  app.requests[0].resolve('ERROR SESSION EXPIRED')
  await flush()
  app.requests[1].resolve('{"key":"renewed-key"}')
  await flush()
  app.requests[2].resolve('ERROR SESSION EXPIRED')
  await flush()
  app.busy(true)
  assert.equal(app.requests.length, 3)
  assert.ok(app.notices.some(notice => notice.level === 'refresh-dialog'))
})

for (const valid of [true, false]) {
  test(`login waits for an earlier focus check (session valid: ${valid})`, async () => {
    const app = createLogin()
    app.context.lastSessionKeyCheck = 0
    app.context.checkSessionKeyFreshness()
    app.launch()
    assert.ok(app.notices.some(notice => notice.level === 'info'))
    app.enter()
    await flush()
    assert.equal(app.requests.length, 1)
    app.requests[0].resolve(JSON.stringify({ valid }))
    await flush()
    if (!valid) {
      assert.equal(app.requests[1].body.type, 'refresh_session_key')
      app.busy(true)
      app.requests[1].resolve('{"key":"focus-renewed-key"}')
      await flush()
    }
    const request = app.requests.at(-1)
    assert.equal(request.body.type, 'identify_user')
    if (!valid) assert.equal(request.body.data.key, 'focus-renewed-key')
    app.context.checkSessionKeyFreshness()
    assert.equal(app.requests.length, valid ? 2 : 3)
    request.resolve(refusal)
    await flush()
    app.busy(false)
  })
}

test('a failed background renewal cannot submit behind the refresh dialog', async () => {
  const app = createLogin()
  app.context.lastSessionKeyCheck = 0
  app.context.checkSessionKeyFreshness()
  app.launch()
  app.requests[0].resolve('{"valid":false}')
  await flush()
  app.requests[1].reject(new Error('Network failure'))
  await flush()
  app.busy(true)
  assert.equal(app.requests.length, 2)
})

test('a failed background validity check still allows credential submission', async () => {
  const app = createLogin()
  app.context.lastSessionKeyCheck = 0
  app.context.checkSessionKeyFreshness()
  app.launch()
  app.requests[0].reject(new Error('Network failure'))
  await flush()
  assert.equal(app.requests[1].body.type, 'identify_user')
})

test('rewording JavaScript comments does not break source extraction', async () => {
  const app = createLogin(source.replace(/^([ \t]*)\/\/.*$/gm, '$1// Reworded comment'))
  app.enter()
  await flush()
  assert.equal(app.requests[0].body.type, 'identify_user')
  app.requests[0].resolve(refusal)
  await flush()
  app.busy(false)
})

test('a hung validity check times out and cannot process a late response', async () => {
  const app = createLogin()
  app.context.lastSessionKeyCheck = 0
  app.context.checkSessionKeyFreshness()
  app.launch()
  const check = app.requests[0]
  const originalKey = app.context.tpSessionKey
  app.advanceTime(9999)
  await flush()
  assert.equal(app.requests.length, 1)
  app.busy(true)
  app.advanceTime(1)
  await flush()
  assert.equal(check.aborted, true)
  assert.equal(app.context.sessionKeyCheckInProgress, false)
  assert.equal(app.requests[1].body.type, 'identify_user')
  check.resolve('{"valid":false}')
  await flush()
  assert.equal(app.requests.length, 2)
  assert.equal(app.context.tpSessionKey, originalKey)
  app.requests[1].resolve(refusal)
  await flush()
  app.busy(false)
})

for (const background of [true, false]) {
  test(`a hung ${background ? 'background' : 'login'} renewal times out into the refresh dialog`, async () => {
    const app = createLogin()
    if (background) {
      app.context.lastSessionKeyCheck = 0
      app.context.checkSessionKeyFreshness()
    }
    app.launch()
    await flush()
    app.requests[0].resolve(background ? '{"valid":false}' : 'ERROR SESSION EXPIRED')
    await flush()
    const renewal = app.requests[1]
    assert.equal(renewal.body.type, 'refresh_session_key')
    const originalKey = app.context.tpSessionKey
    app.advanceTime(9999)
    await flush()
    assert.ok(!app.notices.some(notice => notice.level === 'refresh-dialog'))
    app.advanceTime(1)
    await flush()
    assert.equal(renewal.aborted, true)
    assert.ok(app.notices.some(notice => notice.level === 'refresh-dialog'))
    app.busy(true)
    renewal.resolve('{"key":"late-key"}')
    app.enter()
    await flush()
    assert.equal(app.requests.length, 2)
    assert.equal(app.context.tpSessionKey, originalKey)
    for (let second = 0; second < 5; second++) app.timers[0]()
    assert.equal(app.navigation.reloads, 1)
  })
}

test('settled session requests do not time out a slower authentication request', async () => {
  const app = createLogin()
  app.context.lastSessionKeyCheck = 0
  app.context.checkSessionKeyFreshness()
  app.launch()
  app.requests[0].resolve('{"valid":false}')
  await flush()
  app.requests[1].resolve('{"key":"renewed-key"}')
  await flush()
  app.advanceTime(10000)
  await flush()
  assert.equal(app.requests.length, 3)
  assert.ok(app.requests.every(request => !request.aborted))
  app.busy(true)
  app.requests[2].resolve(success(app.requests[2]))
  await flush()
  assert.equal(app.navigation.href, './index.php?page=items')
})

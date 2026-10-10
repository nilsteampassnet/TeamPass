const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const { join } = require('node:path')
const vm = require('node:vm')
const { test } = require('node:test')

// Execute the shipped browser side of the sign-in passkeys against a stubbed navigator.credentials:
// the server exchanges base64url JSON, the WebAuthn API wants bytes, and the PRF output must come
// back exactly as the authenticator produced it, or the wrap of the private key never opens.
const source = readFileSync(join(__dirname, '../../public/assets/js/webauthn-login.js'), 'utf8')

const b64u = bytes => Buffer.from(bytes).toString('base64url')
const bytesOf = value => Array.from(new Uint8Array(value.buffer ? value.buffer.slice(value.byteOffset, value.byteOffset + value.byteLength) : value))

function load({ secure = true, created = null, got = null } = {}) {
  const calls = {}
  const context = {
    atob: s => Buffer.from(s, 'base64').toString('binary'),
    btoa: s => Buffer.from(s, 'binary').toString('base64'),
  }
  context.window = Object.assign(context, { isSecureContext: secure, PublicKeyCredential: function () {} })
  context.navigator = {
    credentials: {
      create: async options => { calls.create = options; return created },
      get: async options => { calls.get = options; return got },
    },
  }
  vm.createContext(context)
  vm.runInContext(source, context)
  return { api: context.window.tpWebauthnLogin, calls, window: context.window }
}

function arrayBuffer(bytes) {
  return new Uint8Array(bytes).buffer
}

const challenge = [1, 2, 3, 250, 251, 252]
const userId = [9, 8, 7]
const excluded = [4, 5, 6, 255]
const salt = Array.from({ length: 32 }, (_, i) => i)
const prf = Array.from({ length: 32 }, (_, i) => 255 - i)
const rawId = [42, 43, 44, 250]

function attestationCredential(extensionResults) {
  return {
    id: b64u(rawId),
    rawId: arrayBuffer(rawId),
    type: 'public-key',
    response: {
      clientDataJSON: arrayBuffer([123, 125]),
      attestationObject: arrayBuffer([160]),
      getTransports: () => ['internal', 'hybrid'],
    },
    getClientExtensionResults: () => extensionResults,
  }
}

const creationOptions = {
  challenge: b64u(challenge),
  rp: { id: 'tp.example.com', name: 'TeamPass' },
  user: { id: b64u(userId), name: 'jdoe', displayName: 'John Doe' },
  pubKeyCredParams: [{ type: 'public-key', alg: -7 }],
  excludeCredentials: [{ type: 'public-key', id: b64u(excluded), transports: ['internal'] }],
}

test('register converts the options to bytes and requests PRF with the server salt', async () => {
  const { api, calls } = load({ created: attestationCredential({ prf: { enabled: true, results: { first: arrayBuffer(prf) } } }) })
  const result = await api.register(creationOptions, b64u(salt))
  const publicKey = calls.create.publicKey

  assert.deepEqual(bytesOf(publicKey.challenge), challenge)
  assert.deepEqual(bytesOf(publicKey.user.id), userId)
  assert.equal(publicKey.user.name, 'jdoe')
  assert.deepEqual(bytesOf(publicKey.excludeCredentials[0].id), excluded)
  assert.deepEqual(bytesOf(publicKey.extensions.prf.eval.first), salt)

  assert.equal(result.prf_state, 'results')
  assert.equal(result.prf_output, b64u(prf))
  assert.deepEqual(JSON.parse(JSON.stringify(result.credential)), {
    id: b64u(rawId),
    rawId: b64u(rawId),
    type: 'public-key',
    response: { clientDataJSON: b64u([123, 125]), attestationObject: b64u([160]), transports: ['internal', 'hybrid'] },
  })
})

test('an authenticator with PRF but no result at creation asks for a second gesture', async () => {
  const { api } = load({ created: attestationCredential({ prf: { enabled: true } }) })
  const result = await api.register(creationOptions, b64u(salt))

  assert.equal(result.prf_state, 'enabled')
  assert.equal(result.prf_output, '')
})

test('without a salt, PRF is neither requested nor reported', async () => {
  const { api, calls } = load({ created: attestationCredential({ prf: { enabled: true, results: { first: arrayBuffer(prf) } } }) })
  const result = await api.register(creationOptions, '')

  assert.equal(calls.create.publicKey.extensions.prf, undefined)
  assert.equal(result.prf_state, 'unsupported')
  assert.equal(result.prf_output, '')
})

test('assert converts the allowed ids and returns the signature, user handle and PRF output', async () => {
  const got = {
    id: b64u(rawId),
    rawId: arrayBuffer(rawId),
    type: 'public-key',
    response: {
      clientDataJSON: arrayBuffer([1]),
      authenticatorData: arrayBuffer([2]),
      signature: arrayBuffer([3]),
      userHandle: arrayBuffer(userId),
    },
    getClientExtensionResults: () => ({ prf: { results: { first: arrayBuffer(prf) } } }),
  }
  const { api, calls } = load({ got })
  const result = await api.assert({ challenge: b64u(challenge), rpId: 'tp.example.com', allowCredentials: [{ type: 'public-key', id: b64u(rawId) }], userVerification: 'required' }, b64u(salt))

  assert.deepEqual(bytesOf(calls.get.publicKey.allowCredentials[0].id), rawId)
  assert.deepEqual(bytesOf(calls.get.publicKey.extensions.prf.eval.first), salt)
  assert.deepEqual(JSON.parse(JSON.stringify(result.credential.response)), {
    clientDataJSON: b64u([1]),
    authenticatorData: b64u([2]),
    signature: b64u([3]),
    userHandle: b64u(userId),
  })
  assert.equal(result.prf_output, b64u(prf))
})

test('ceremony errors map to messages the user understands', () => {
  const { api } = load()
  assert.equal(api.errorKind({ name: 'NotAllowedError' }), 'cancelled')
  assert.equal(api.errorKind({ name: 'AbortError' }), 'cancelled')
  assert.equal(api.errorKind({ name: 'InvalidStateError' }), 'exists')
  assert.equal(api.errorKind({ name: 'SecurityError' }), 'security')
  assert.equal(api.errorKind(new Error('boom')), 'failed')
})

test('passkeys are only offered in a secure context', () => {
  assert.equal(load({ secure: true }).api.supported(), true)
  assert.equal(load({ secure: false }).api.supported(), false)
})

test('a passkey the server no longer knows, and the list it still accepts, are reported to the browser', async () => {
  const calls = []
  const signalling = load()
  const PublicKeyCredential = function () {}
  PublicKeyCredential.signalUnknownCredential = async details => { calls.push(['unknown', details]) }
  PublicKeyCredential.signalAllAcceptedCredentials = details => { calls.push(['accepted', details]); return Promise.reject(new Error('SecurityError')) }
  signalling.window.PublicKeyCredential = PublicKeyCredential

  assert.equal(signalling.api.signalUnknown('tp.example.com', b64u(rawId)), true)
  assert.equal(signalling.api.signalAccepted({ rp_id: 'tp.example.com', user_id: b64u(userId), credential_ids: [b64u(rawId)] }), true)
  // An account left without any passkey still reports its (empty) list
  assert.equal(signalling.api.signalAccepted({ rp_id: 'tp.example.com', user_id: b64u(userId), credential_ids: [] }), true)
  await new Promise(resolve => setImmediate(resolve))
  assert.deepEqual(JSON.parse(JSON.stringify(calls)), [
    ['unknown', { rpId: 'tp.example.com', credentialId: b64u(rawId) }],
    ['accepted', { rpId: 'tp.example.com', userId: b64u(userId), allAcceptedCredentialIds: [b64u(rawId)] }],
    ['accepted', { rpId: 'tp.example.com', userId: b64u(userId), allAcceptedCredentialIds: [] }],
  ])

  // Incomplete data reports nothing: a partial list would make a manager drop valid passkeys
  assert.equal(signalling.api.signalAccepted(null), false)
  assert.equal(signalling.api.signalAccepted({ rp_id: 'tp.example.com', user_id: b64u(userId) }), false)
  assert.equal(signalling.api.signalAccepted({ rp_id: '', user_id: b64u(userId), credential_ids: [] }), false)
  assert.equal(signalling.api.signalUnknown('tp.example.com', ''), false)
  assert.equal(calls.length, 3)
})

test('a browser without the Signal API is left alone', () => {
  const { api } = load()
  assert.equal(api.signalUnknown('tp.example.com', b64u(rawId)), false)
  assert.equal(api.signalAccepted({ rp_id: 'tp.example.com', user_id: b64u(userId), credential_ids: [] }), false)
  assert.equal(load({ secure: false }).api.signalUnknown('tp.example.com', b64u(rawId)), false)
})

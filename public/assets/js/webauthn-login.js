/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * ---
 * Browser side of the passkeys used to sign in to TeamPass: turns the JSON options the server
 * sends into navigator.credentials calls, and the credentials back into JSON.
 *
 * The PRF extension is requested with the input the server gives. Its output is the only secret
 * that leaves the authenticator; it is sent once, over the encrypted exchange, to wrap the
 * private key, and never stored by the browser.
 *
 * @file      webauthn-login.js
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/* global window, navigator */

(function () {
  'use strict'

  const toBytes = (value) => {
    const base64 = String(value).replace(/-/g, '+').replace(/_/g, '/')
    const binary = window.atob(base64 + '='.repeat((4 - base64.length % 4) % 4))
    const bytes = new Uint8Array(binary.length)
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i)
    }
    return bytes
  }

  const toBase64Url = (buffer) => {
    const bytes = new Uint8Array(buffer)
    let binary = ''
    for (let i = 0; i < bytes.length; i++) {
      binary += String.fromCharCode(bytes[i])
    }
    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
  }

  const descriptors = (list) => (Array.isArray(list) ? list : []).map((item) => ({
    type: item.type,
    id: toBytes(item.id),
    transports: Array.isArray(item.transports) ? item.transports : undefined
  }))

  const prfRequest = (input) => (input ? { prf: { eval: { first: toBytes(input) } } } : {})

  // The PRF output when the authenticator evaluated it, '' otherwise
  const prfOutput = (credential) => {
    const results = credential.getClientExtensionResults ? credential.getClientExtensionResults() : {}
    const first = results && results.prf && results.prf.results ? results.prf.results.first : null
    return first ? toBase64Url(first) : ''
  }

  const prfEnabled = (credential) => {
    const results = credential.getClientExtensionResults ? credential.getClientExtensionResults() : {}
    return Boolean(results && results.prf && results.prf.enabled === true)
  }

  const credentialToJson = (credential) => {
    const response = credential.response
    const json = {
      id: credential.id,
      rawId: toBase64Url(credential.rawId),
      type: credential.type,
      response: {
        clientDataJSON: toBase64Url(response.clientDataJSON)
      }
    }
    if (response.attestationObject) {
      json.response.attestationObject = toBase64Url(response.attestationObject)
      json.response.transports = typeof response.getTransports === 'function' ? response.getTransports() : []
    }
    if (response.authenticatorData) {
      json.response.authenticatorData = toBase64Url(response.authenticatorData)
      json.response.signature = toBase64Url(response.signature)
      if (response.userHandle) {
        json.response.userHandle = toBase64Url(response.userHandle)
      }
    }
    return json
  }

  const supported = () => window.isSecureContext === true &&
    typeof window.PublicKeyCredential !== 'undefined' &&
    Boolean(navigator.credentials)

  /**
   * Register a passkey.
   *
   * @param {object} options  Creation options from the server
   * @param {string} prfInput  base64url PRF input, '' when no passwordless copy is wanted
   * @returns {Promise<{credential: object, prf_state: string, prf_output: string}>}
   */
  const register = async (options, prfInput) => {
    const publicKey = Object.assign({}, options, {
      challenge: toBytes(options.challenge),
      user: Object.assign({}, options.user, { id: toBytes(options.user.id) }),
      excludeCredentials: descriptors(options.excludeCredentials),
      extensions: Object.assign({}, options.extensions || {}, prfRequest(prfInput))
    })
    const credential = await navigator.credentials.create({ publicKey })
    const output = prfInput ? prfOutput(credential) : ''
    let state = 'unsupported'
    if (output !== '') {
      state = 'results'
    } else if (prfInput && prfEnabled(credential)) {
      state = 'enabled'
    }
    return { credential: credentialToJson(credential), prf_state: state, prf_output: output }
  }

  /**
   * Sign with a passkey.
   *
   * @param {object} options  Request options from the server
   * @param {string} prfInput  base64url PRF input, '' when not needed
   * @returns {Promise<{credential: object, prf_output: string}>}
   */
  const assert = async (options, prfInput) => {
    const publicKey = Object.assign({}, options, {
      challenge: toBytes(options.challenge),
      allowCredentials: descriptors(options.allowCredentials),
      extensions: Object.assign({}, options.extensions || {}, prfRequest(prfInput))
    })
    const credential = await navigator.credentials.get({ publicKey })
    return { credential: credentialToJson(credential), prf_output: prfInput ? prfOutput(credential) : '' }
  }

  /**
   * Error key of a failed ceremony, for a message the user understands.
   *
   * @param {Error} error Rejection of navigator.credentials
   * @returns {string} cancelled|exists|security|failed
   */
  const errorKind = (error) => {
    const name = error && error.name ? error.name : ''
    if (name === 'NotAllowedError' || name === 'AbortError') {
      return 'cancelled'
    }
    if (name === 'InvalidStateError') {
      return 'exists'
    }
    if (name === 'SecurityError') {
      return 'security'
    }
    return 'failed'
  }

  window.tpWebauthnLogin = { supported, register, assert, errorKind }
})()

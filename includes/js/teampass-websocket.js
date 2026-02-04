/**
 * TeamPass WebSocket Client
 *
 * Provides real-time communication between the TeamPass web interface
 * and the WebSocket server for live notifications and updates.
 *
 * Features:
 * - Automatic reconnection with exponential backoff
 * - Heartbeat/ping-pong to detect dead connections
 * - Channel subscriptions (folders)
 * - Event-based API
 * - Request/response correlation via request_id
 *
 * @version 1.0.0
 * @author TeamPass
 */

'use strict';

(function(window) {

  /**
   * TeamPass WebSocket Client Class
   * @param {Object} options Configuration options
   */
  function TeamPassWebSocket(options) {
    options = options || {}

    // Configuration
    this.url = options.url ? options.url : this._getDefaultUrl();
    this.token = options.token || null
    this.reconnectInterval = options.reconnectInterval || 3000
    this.maxReconnectAttempts = options.maxReconnectAttempts || 10
    this.pingInterval = options.pingInterval || 25000
    this.requestTimeout = options.requestTimeout || 10000
    this.debug = options.debug || false
    
    // State
    this.ws = null
    this.reconnectAttempts = 0
    this.pingTimer = null
    this.isIntentionallyClosed = false
    this.isConnected = false
    this.subscriptions = new Set()
    this.pendingRequests = new Map()
    this.eventHandlers = new Map()

    // Default callbacks
    this._onOpen = function() {}
    this._onClose = function() {}
    this._onError = function() {}
    this._onReconnecting = function() {}
  }

  /**
   * Get default WebSocket URL based on current page location
   * @private
   */
  TeamPassWebSocket.prototype._getDefaultUrl = function() {
    var protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'
    //FIX: port is missing
    return protocol + '//' + window.location.host + '/ws'
  }

  /**
   * Generate a unique request ID
   * @private
   */
  TeamPassWebSocket.prototype._generateRequestId = function() {
    return 'req_' + Math.random().toString(36).substring(2, 15) +
           Math.random().toString(36).substring(2, 15)
  }

  /**
   * Log debug messages
   * @private
   */
  TeamPassWebSocket.prototype._log = function(level, message, data) {
    if (!this.debug && level === 'debug') return

    var prefix = '[TeamPass WS]'
    var logFn = console.log

    if (level === 'error') logFn = console.error
    else if (level === 'warn') logFn = console.warn

    if (data) {
      logFn(prefix, message, data)
    } else {
      logFn(prefix, message)
    }
  }

  /**
   * Connect to the WebSocket server
   * @returns {Promise} Resolves when connected, rejects on error
   */
  TeamPassWebSocket.prototype.connect = function() {
    var self = this

    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      return Promise.resolve()
    }

    return new Promise(function(resolve, reject) {
      self.isIntentionallyClosed = false

      var url = self.url
      if (self.token) {
        url += (url.indexOf('?') === -1 ? '?' : '&') + 'token=' + encodeURIComponent(self.token)
      }

      self._log('debug', 'Connecting to', url)

      try {
        self.ws = new WebSocket(url)
      } catch (e) {
        self._log('error', 'Failed to create WebSocket', e)
        reject(e)
        return
      }

      self.ws.onopen = function() {
        self._log('info', 'Connected')
        self.isConnected = true
        self.reconnectAttempts = 0
        self._startPingInterval()
        self._resubscribe()
        self._onOpen()
        resolve()
      }

      self.ws.onclose = function(event) {
        self._log('info', 'Disconnected', { code: event.code, reason: event.reason })
        self.isConnected = false
        self._stopPingInterval()
        self._rejectPendingRequests('Connection closed')
        self._onClose(event)

        if (!self.isIntentionallyClosed) {
          self._scheduleReconnect()
        }
      }

      self.ws.onerror = function(error) {
        self._log('error', 'WebSocket error', error)
        self._onError(error)
        // Don't reject here - onclose will be called after onerror
      }

      self.ws.onmessage = function(event) {
        self._handleMessage(event.data)
      }
    })
  }

  /**
   * Disconnect from the WebSocket server
   */
  TeamPassWebSocket.prototype.disconnect = function() {
    this._log('debug', 'Disconnecting')
    this.isIntentionallyClosed = true
    this._stopPingInterval()
    this._rejectPendingRequests('Disconnected by client')

    if (this.ws) {
      this.ws.close(1000, 'Client disconnect')
      this.ws = null
    }

    this.isConnected = false
  }

  /**
   * Schedule a reconnection attempt
   * @private
   */
  TeamPassWebSocket.prototype._scheduleReconnect = function() {
    var self = this

    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      this._log('error', 'Max reconnection attempts reached')
      this._emit('reconnect_failed', { attempts: this.reconnectAttempts })
      return
    }

    this.reconnectAttempts++
    var delay = Math.min(
      this.reconnectInterval * Math.pow(1.5, this.reconnectAttempts - 1),
      30000 // Max 30 seconds
    )

    this._log('info', 'Reconnecting in ' + delay + 'ms (attempt ' + this.reconnectAttempts + ')')
    this._onReconnecting(this.reconnectAttempts, delay)

    setTimeout(function() {
      if (!self.isIntentionallyClosed) {
        self.connect().catch(function() {
          // Error handled in connect()
        })
      }
    }, delay)
  }

  /**
   * Start the ping interval timer
   * @private
   */
  TeamPassWebSocket.prototype._startPingInterval = function() {
    var self = this
    this._stopPingInterval()

    this.pingTimer = setInterval(function() {
      if (self.isConnected) {
        self.send({ action: 'ping' }).catch(function() {
          // Ping failed, connection might be dead
        })
      }
    }, this.pingInterval)
  }

  /**
   * Stop the ping interval timer
   * @private
   */
  TeamPassWebSocket.prototype._stopPingInterval = function() {
    if (this.pingTimer) {
      clearInterval(this.pingTimer)
      this.pingTimer = null
    }
  }

  /**
   * Resubscribe to all channels after reconnection
   * @private
   */
  TeamPassWebSocket.prototype._resubscribe = function() {
    var self = this

    this.subscriptions.forEach(function(sub) {
      var parts = sub.split(':')
      var channel = parts[0]
      var id = parts[1] ? parseInt(parts[1], 10) : null

      if (channel === 'folder' && id) {
        self._sendSubscribe('folder', id).catch(function(err) {
          self._log('warn', 'Failed to resubscribe to folder ' + id, err)
        })
      }
    })
  }

  /**
   * Reject all pending requests (on disconnect)
   * @private
   */
  TeamPassWebSocket.prototype._rejectPendingRequests = function(reason) {
    var self = this

    this.pendingRequests.forEach(function(pending, requestId) {
      clearTimeout(pending.timeout)
      pending.reject(new Error(reason))
    })

    this.pendingRequests.clear()
  }

  /**
   * Handle incoming WebSocket message
   * @private
   */
  TeamPassWebSocket.prototype._handleMessage = function(data) {
    var message

    try {
      message = JSON.parse(data)
    } catch (e) {
      this._log('error', 'Invalid JSON received', data)
      return
    }

    this._log('debug', 'Message received', message)

    // Handle response to pending request
    if (message.request_id && this.pendingRequests.has(message.request_id)) {
      var pending = this.pendingRequests.get(message.request_id)
      this.pendingRequests.delete(message.request_id)
      clearTimeout(pending.timeout)

      if (message.type === 'error') {
        pending.reject(message)
      } else {
        pending.resolve(message)
      }
      return
    }

    // Handle server-initiated messages
    switch (message.type) {
      case 'event':
        this._emit(message.event, message.data)
        break

      case 'ping':
        // Respond to server ping with pong
        this._sendRaw({ action: 'pong', request_id: message.request_id })
        break

      case 'pong':
        // Server responded to our ping - connection is alive
        break

      case 'connected':
        this._emit('connected', message)
        break

      case 'error':
        this._log('error', 'Server error', message)
        this._emit('server_error', message)
        break

      default:
        this._log('debug', 'Unknown message type', message)
    }
  }

  /**
   * Send a message and wait for response
   * @param {Object} payload Message payload
   * @returns {Promise} Resolves with response, rejects on error/timeout
   */
  TeamPassWebSocket.prototype.send = function(payload) {
    var self = this

    if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
      return Promise.reject(new Error('Not connected'))
    }

    var requestId = this._generateRequestId()
    payload.request_id = requestId

    return new Promise(function(resolve, reject) {
      var timeoutId = setTimeout(function() {
        if (self.pendingRequests.has(requestId)) {
          self.pendingRequests.delete(requestId)
          reject(new Error('Request timeout'))
        }
      }, self.requestTimeout)

      self.pendingRequests.set(requestId, {
        resolve: resolve,
        reject: reject,
        timeout: timeoutId
      })

      self._sendRaw(payload)
    })
  }

  /**
   * Send raw message without waiting for response
   * @private
   */
  TeamPassWebSocket.prototype._sendRaw = function(payload) {
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this._log('debug', 'Sending', payload)
      this.ws.send(JSON.stringify(payload))
    }
  }

  /**
   * Send subscribe request
   * @private
   */
  TeamPassWebSocket.prototype._sendSubscribe = function(channel, id) {
    var data = {}
    if (channel === 'folder' && id) {
      data.folder_id = id
    }
    return this.send({ action: 'subscribe', channel: channel, data: data })
  }

  /**
   * Subscribe to a folder channel
   * @param {number} folderId Folder ID to subscribe to
   * @returns {Promise}
   */
  TeamPassWebSocket.prototype.subscribeToFolder = function(folderId) {
    var key = 'folder:' + folderId
    this.subscriptions.add(key)
    return this._sendSubscribe('folder', folderId)
  }

  /**
   * Unsubscribe from a folder channel
   * @param {number} folderId Folder ID to unsubscribe from
   * @returns {Promise}
   */
  TeamPassWebSocket.prototype.unsubscribeFromFolder = function(folderId) {
    var key = 'folder:' + folderId
    this.subscriptions.delete(key)
    return this.send({
      action: 'unsubscribe',
      channel: 'folder',
      data: { folder_id: folderId }
    })
  }

  /**
   * Get connection status
   * @returns {Promise}
   */
  TeamPassWebSocket.prototype.getStatus = function() {
    return this.send({ action: 'get_status' })
  }

  /**
   * Register an event handler
   * @param {string} eventName Event name to listen for
   * @param {Function} handler Handler function
   * @returns {TeamPassWebSocket} this (for chaining)
   */
  TeamPassWebSocket.prototype.on = function(eventName, handler) {
    if (!this.eventHandlers.has(eventName)) {
      this.eventHandlers.set(eventName, [])
    }
    this.eventHandlers.get(eventName).push(handler)
    return this
  }

  /**
   * Remove an event handler
   * @param {string} eventName Event name
   * @param {Function} handler Handler function to remove
   * @returns {TeamPassWebSocket} this (for chaining)
   */
  TeamPassWebSocket.prototype.off = function(eventName, handler) {
    if (!this.eventHandlers.has(eventName)) return this

    var handlers = this.eventHandlers.get(eventName)
    var index = handlers.indexOf(handler)
    if (index > -1) {
      handlers.splice(index, 1)
    }
    return this
  }

  /**
   * Emit an event to registered handlers
   * @private
   */
  TeamPassWebSocket.prototype._emit = function(eventName, data) {
    var self = this

    // Call specific event handlers
    var handlers = this.eventHandlers.get(eventName) || []
    handlers.forEach(function(handler) {
      try {
        handler(data)
      } catch (e) {
        self._log('error', 'Event handler error', e)
      }
    })

    // Call wildcard handlers
    var wildcardHandlers = this.eventHandlers.get('*') || []
    wildcardHandlers.forEach(function(handler) {
      try {
        handler(eventName, data)
      } catch (e) {
        self._log('error', 'Wildcard handler error', e)
      }
    })
  }

  /**
   * Set connection open callback
   * @param {Function} callback
   * @returns {TeamPassWebSocket} this (for chaining)
   */
  TeamPassWebSocket.prototype.onOpen = function(callback) {
    this._onOpen = callback
    return this
  }

  /**
   * Set connection close callback
   * @param {Function} callback
   * @returns {TeamPassWebSocket} this (for chaining)
   */
  TeamPassWebSocket.prototype.onClose = function(callback) {
    this._onClose = callback
    return this
  }

  /**
   * Set error callback
   * @param {Function} callback
   * @returns {TeamPassWebSocket} this (for chaining)
   */
  TeamPassWebSocket.prototype.onError = function(callback) {
    this._onError = callback
    return this
  }

  /**
   * Set reconnecting callback
   * @param {Function} callback Called with (attemptNumber, delayMs)
   * @returns {TeamPassWebSocket} this (for chaining)
   */
  TeamPassWebSocket.prototype.onReconnecting = function(callback) {
    this._onReconnecting = callback
    return this
  }

  /**
   * Check if currently connected
   * @returns {boolean}
   */
  TeamPassWebSocket.prototype.isConnectedNow = function() {
    return this.isConnected && this.ws && this.ws.readyState === WebSocket.OPEN
  }

  /**
   * Get list of current subscriptions
   * @returns {Array}
   */
  TeamPassWebSocket.prototype.getSubscriptions = function() {
    return Array.from(this.subscriptions)
  }

  // Export to window
  window.TeamPassWebSocket = TeamPassWebSocket

})(window);

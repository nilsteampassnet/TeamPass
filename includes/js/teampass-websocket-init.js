/**
 * TeamPass WebSocket Initialization
 *
 * This file initializes the WebSocket connection and sets up
 * event handlers for real-time notifications in TeamPass.
 *
 * Include this file after teampass-websocket.js
 */

'use strict';

(function(window, $) {

  // Check if WebSocket client is available
  if (typeof TeamPassWebSocket === 'undefined') {
    tpWsDebug('[TeamPass WS Init] TeamPassWebSocket not loaded', 'warn')
    return
  }

  // Check if WebSocket is enabled (set by PHP)
  if (typeof window.TeamPassWebSocketEnabled === 'undefined' || !window.TeamPassWebSocketEnabled) {
    tpWsDebug('[TeamPass WS Init] WebSocket is disabled', 'log')
    return
  }

  // Language strings (injected by PHP via window.TeamPassWsLang)
  var L = window.TeamPassWsLang || {}

  // Check if we have an authentication token
  if (typeof window.TeamPassWebSocketToken === 'undefined' || !window.TeamPassWebSocketToken) {
    tpWsDebug('[TeamPass WS Init] No WebSocket token available', 'log')
    return
  }
  
  // Get WebSocket URL from PHP config
  var wsUrl = window.TeamPassWebSocketUrl || 'ws://127.0.0.1:8080'

  // Create global instance with token authentication
  var tpWs = new TeamPassWebSocket({
    debug: window.TeamPassWebSocketDebug || false,
    url: wsUrl,
    token: window.TeamPassWebSocketToken,
  })

  // Store reference globally
  window.tpWebSocket = tpWs

  // Current folder tracking
  var currentFolderId = null

  /**
   * Initialize WebSocket connection
   */
  function init() {
    tpWs
      .onOpen(function() {
        tpWsDebug('[TeamPass WS] Connected to server', 'log')

        // Subscribe to current folder if any
        if (currentFolderId) {
          subscribeToFolder(currentFolderId)
        }

        // Show subtle connection indicator
        updateConnectionStatus(true)
      })
      .onClose(function(event) {
        tpWsDebug('[TeamPass WS] Disconnected - ' + event.code, 'log')
        updateConnectionStatus(false)

        // Show notification only if not intentional
        if (event.code !== 1000) {
          showNotification('warning', L.realtime_connection_lost, L.reconnecting)
        }
      })
      .onReconnecting(function(attempt, delay) {
        tpWsDebug('[TeamPass WS] Reconnecting, attempt' + attempt, 'log')
      })
      .onError(function(error) {
        tpWsDebug('[TeamPass WS] Error' + error, 'error')
      })

    // Set up event handlers
    setupEventHandlers()

    // Connect
    tpWs.connect().catch(function(err) {
      tpWsDebug('[TeamPass WS] Initial connection failed' + err, 'error')
    })
  }

  /**
   * Set up handlers for various WebSocket events
   */
  function setupEventHandlers() {
    // Item events
    tpWs.on('item_created', function(data) {
      if (data.folder_id === currentFolderId) {
        showNotification('success', L.new_item, '"' + data.label + '" ' + L.item_created_by + ' ' + data.created_by)
        refreshItemsList()
      }
    })

    tpWs.on('item_updated', function(data) {
      if (data.folder_id === currentFolderId) {
        showNotification('info', L.item_updated, '"' + data.label + '" ' + L.item_updated_by + ' ' + data.updated_by)
        refreshItemsList()
      }
    })

    tpWs.on('item_deleted', function(data) {
      if (data.folder_id === currentFolderId) {
        showNotification('warning', L.item_deleted, L.item_deleted_by + ' ' + data.deleted_by)
        refreshItemsList()
      }
    })

    // Edition lock events
    tpWs.on('item_edition_started', function(data) {
      if (data.folder_id === currentFolderId) {
        showEditionLockIndicator(data.item_id, data.user_login)
      }
      // Track locked items globally
      if (!window.tpLockedItems) window.tpLockedItems = {}
      window.tpLockedItems[data.item_id] = data.user_login
    })

    tpWs.on('item_edition_stopped', function(data) {
      if (data.folder_id === currentFolderId) {
        removeEditionLockIndicator(data.item_id)
      }
      // Remove from global tracking
      if (window.tpLockedItems) {
        delete window.tpLockedItems[data.item_id]
      }
      // Notify user if they previously tried to edit this item
      if (window.tpBlockedEditItemId && window.tpBlockedEditItemId === data.item_id) {
        showNotification('success',
          L.item_now_available || 'Item available',
          (L.item_edition_released || 'Item is now available for editing') +
          ' (' + data.user_login + ')'
        )
        window.tpBlockedEditItemId = null
      }
    })

    // Folder events
    tpWs.on('folder_created', function(data) {
      showNotification('success', L.new_folder, '"' + data.title + '" ' + L.folder_created)
      refreshFolderTree()
    })

    tpWs.on('folder_updated', function(data) {
      showNotification('info', L.folder_updated, '"' + data.title + '" ' + L.folder_has_been_updated)
      refreshFolderTree()
    })

    tpWs.on('folder_deleted', function(data) {
      showNotification('warning', L.folder_deleted, L.folder_has_been_deleted)
      refreshFolderTree()
    })

    tpWs.on('folder_permission_changed', function(data) {
      showNotification('info', L.permissions_changed, L.folder_permissions_changed)
      // May need to refresh accessible folders
      if (typeof refreshUserFolders === 'function') {
        refreshUserFolders()
      }
    })

    // User events
    tpWs.on('user_keys_ready', function(data) {
      showNotification('success', L.account_ready, L.account_operational)
      // Refresh page to show full interface
      if (typeof window.location.reload === 'function') {
        setTimeout(function() {
          window.location.reload()
        }, 2000)
      }
    })

    // Task progress events
    tpWs.on('task_progress', function(data) {
      updateTaskProgress(data)
    })

    tpWs.on('task_completed', function(data) {
      handleTaskCompleted(data)
    })

    // Session events
    tpWs.on('session_expired', function(data) {
      showNotification('error', L.session_expired, data.reason || L.please_reconnect)
      setTimeout(function() {
        window.location.href = 'includes/core/logout.php?session_expired=1'
      }, 2000)
    })

    // System events
    tpWs.on('system_maintenance', function(data) {
      showNotification('warning', L.maintenance, data.message)
    })

    // Reconnection failed
    tpWs.on('reconnect_failed', function() {
      showNotification('error', L.connection_lost, L.unable_to_reconnect)
    })

    // Debug: log all events
    if (window.TeamPassWebSocketDebug) {
      tpWs.on('*', function(eventName, data) {
        tpWsDebug('[TeamPass WS Event] ' + eventName + " - " + data, 'log')
      })
    }
  }

  /**
   * Subscribe to a folder for real-time updates
   */
  function subscribeToFolder(folderId) {
    if (!folderId || !tpWs.isConnectedNow()) return

    // Unsubscribe from previous folder
    if (currentFolderId && currentFolderId !== folderId) {
      tpWs.unsubscribeFromFolder(currentFolderId).catch(function() {})
    }

    currentFolderId = folderId

    tpWs.subscribeToFolder(folderId)
      .then(function() {
        tpWsDebug('[TeamPass WS] Subscribed to folder ' + folderId, 'log')
      })
      .catch(function(err) {
        tpWsDebug('[TeamPass WS] Failed to subscribe to folder ' + folderId + " - " + err, 'error')
      })
  }

  /**
   * Update connection status indicator
   */
  function updateConnectionStatus(connected) {
    var indicator = document.getElementById('ws-connection-status')
    if (!indicator) {
      // Create indicator if it doesn't exist
      indicator = document.createElement('span')
      indicator.id = 'ws-connection-status'
      indicator.style.cssText = 'position:fixed;bottom:10px;right:10px;width:10px;height:10px;' +
        'border-radius:50%;opacity:0.7;z-index:9999;transition:background-color 0.3s;'

      document.body.appendChild(indicator)
    }

    indicator.style.backgroundColor = connected ? '#28a745' : '#dc3545'
    indicator.title = connected ? (L.realtime_connected || 'Real-time: connected') : (L.realtime_disconnected || 'Real-time: disconnected')
  }

  /**
   * Show notification using Toastr or fallback
   */
  function showNotification(type, title, message, timeOut = 10000) {
    if (typeof toastr !== 'undefined') {
      toastr[type](message, title, { timeOut: timeOut, extendedTimeOut: 3000, progressBar: true })
    } else if (typeof alertify !== 'undefined') {
      alertify[type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'success'](title + ': ' + message)
    } else {
      tpWsDebug('[TeamPass WS Notification] ' + type +" - " + title + " - " + message, 'log')
    }
  }

  /**
   * Refresh items list in current view
   */
  function refreshItemsList() {
    // Try to call existing TeamPass refresh function
    if (typeof window.refreshVisibleItems === 'function') {
      window.refreshVisibleItems()
    } else if (typeof window.loadItems === 'function') {
      window.loadItems()
    } else if (typeof $ !== 'undefined' && $('#items-list').length) {
      // Trigger a custom event that pages can listen to
      $(document).trigger('teampass:items:refresh')
    }
  }

  /**
   * Refresh folder tree
   */
  function refreshFolderTree() {
    if (typeof window.refreshTree === 'function') {
      window.refreshTree()
    } else if (typeof $ !== 'undefined' && $('#jstree').length) {
      $('#jstree').jstree('refresh')
    } else {
      $(document).trigger('teampass:folders:refresh')
    }
  }

  /**
   * Update task progress UI
   */
  function updateTaskProgress(data) {
    var progressId = 'task-progress-' + data.task_id

    // Try to find existing progress bar
    var progressBar = document.getElementById(progressId)

    if (!progressBar && typeof $ !== 'undefined') {
      // Create progress notification if using Toastr
      if (typeof toastr !== 'undefined' && data.percent < 100) {
        toastr.info(
          '<div class="progress"><div class="progress-bar" id="' + progressId + '" style="width:' + data.percent + '%"></div></div>' +
          '<small>' + data.task_type + ': ' + data.progress + '/' + data.total + '</small>',
          L.progress || 'Progress',
          { timeOut: 0, extendedTimeOut: 0, closeButton: true }
        )
      }
    } else if (progressBar) {
      progressBar.style.width = data.percent + '%'
    }

    // Trigger custom event
    $(document).trigger('teampass:task:progress', [data])
  }

  /**
   * Handle task completion
   */
  function handleTaskCompleted(data) {
    var type = data.status === 'completed' ? 'success' : 'error'
    var message = data.message || (data.status === 'completed' ? (L.operation_completed || 'Operation completed') : (L.operation_failed || 'Operation failed'))

    showNotification(type, data.task_type || (L.task || 'Task'), message, 5000)

    // Auto-refresh item details when encryption task completes for the viewed item
    if (data.status === 'completed' && data.task_type === 'Item encryption' && data.item_id) {
      if (store.get('teampassItem').id == data.item_id) {
        if (typeof window.refreshItemDetails === 'function') {
          window.refreshItemDetails(data.item_id)
        }
      }
    }

    // Trigger custom event
    $(document).trigger('teampass:task:completed', [data])
  }

  /**
   * Show lock indicator on an item row in the items list
   */
  function showEditionLockIndicator(itemId, userLogin) {
    if (typeof $ === 'undefined') return

    var $row = $('#list-item-row_' + itemId)
    if ($row.length === 0) return

    // Don't add duplicate indicator
    if ($row.find('.edition-lock-badge').length > 0) return

    var badge = $('<span class="edition-lock-badge badge badge-warning ml-2" ' +
      'title="' + (L.being_edited_by || 'Being edited by') + ' ' + userLogin + '">' +
      '<i class="fas fa-lock mr-1"></i>' + userLogin +
      '</span>')

    $row.find('.list-item-row-description').first().after(badge)
  }

  /**
   * Remove lock indicator from an item row
   */
  function removeEditionLockIndicator(itemId) {
    if (typeof $ === 'undefined') return
    $('#list-item-row_' + itemId).find('.edition-lock-badge').remove()
  }

  /**
   * Refresh user folders after a permission change
   *
   * Triggers a custom jQuery event that items.js.php listens to.
   * This avoids scope/timing issues since items.js.php has direct
   * access to refreshVisibleFolders(), store, and jstree.
   */
  function refreshUserFolders() {
    if (typeof $ !== 'undefined') {
      $(document).trigger('teampass:permissions:refresh')
    }
  }

  // Expose functions globally
  window.tpWsSubscribeToFolder = subscribeToFolder
  window.tpWsShowNotification = showNotification
  window.tpWsShowEditionLock = showEditionLockIndicator
  window.tpWsRemoveEditionLock = removeEditionLockIndicator
  window.refreshUserFolders = refreshUserFolders

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init)
  } else {
    init()
  }

})(window, typeof jQuery !== 'undefined' ? jQuery : null);

function tpWsDebug(msg, logType = 'log') {
  if (debugJavascript) {
    logType === 'error' ? console.error(msg) : logType === 'warn' ? console.warn(msg) : console.log(msg)
  }
}
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
    console.warn('[TeamPass WS Init] TeamPassWebSocket not loaded')
    return
  }

  // Check if WebSocket is enabled (set by PHP)
  if (typeof window.TeamPassWebSocketEnabled === 'undefined' || !window.TeamPassWebSocketEnabled) {
    console.log('[TeamPass WS Init] WebSocket is disabled')
    return
  }

  // Check if we have an authentication token
  if (typeof window.TeamPassWebSocketToken === 'undefined' || !window.TeamPassWebSocketToken) {
    console.log('[TeamPass WS Init] No WebSocket token available')
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
        console.log('[TeamPass WS] Connected to server')

        // Subscribe to current folder if any
        if (currentFolderId) {
          subscribeToFolder(currentFolderId)
        }

        // Show subtle connection indicator
        updateConnectionStatus(true)
      })
      .onClose(function(event) {
        console.log('[TeamPass WS] Disconnected', event.code)
        updateConnectionStatus(false)

        // Show notification only if not intentional
        if (event.code !== 1000) {
          showNotification('warning', 'Connexion temps réel perdue', 'Reconnexion en cours...')
        }
      })
      .onReconnecting(function(attempt, delay) {
        console.log('[TeamPass WS] Reconnecting, attempt', attempt)
      })
      .onError(function(error) {
        console.error('[TeamPass WS] Error', error)
      })

    // Set up event handlers
    setupEventHandlers()

    // Connect
    tpWs.connect().catch(function(err) {
      console.error('[TeamPass WS] Initial connection failed', err)
    })
  }

  /**
   * Set up handlers for various WebSocket events
   */
  function setupEventHandlers() {
    // Item events
    tpWs.on('item_created', function(data) {
      if (data.folder_id === currentFolderId) {
        showNotification('success', 'Nouvel item', '"' + data.label + '" créé par ' + data.created_by)
        refreshItemsList()
      }
    })

    tpWs.on('item_updated', function(data) {
      if (data.folder_id === currentFolderId) {
        showNotification('info', 'Item modifié', '"' + data.label + '" modifié par ' + data.updated_by)
        refreshItemsList()
      }
    })

    tpWs.on('item_deleted', function(data) {
      if (data.folder_id === currentFolderId) {
        showNotification('warning', 'Item supprimé', 'Un item a été supprimé par ' + data.deleted_by)
        refreshItemsList()
      }
    })

    // Folder events
    tpWs.on('folder_created', function(data) {
      showNotification('success', 'Nouveau dossier', '"' + data.title + '" créé')
      refreshFolderTree()
    })

    tpWs.on('folder_updated', function(data) {
      showNotification('info', 'Dossier modifié', '"' + data.title + '" a été modifié')
      refreshFolderTree()
    })

    tpWs.on('folder_deleted', function(data) {
      showNotification('warning', 'Dossier supprimé', 'Un dossier a été supprimé')
      refreshFolderTree()
    })

    tpWs.on('folder_permission_changed', function(data) {
      showNotification('info', 'Permissions modifiées', 'Les permissions d\'un dossier ont changé')
      // May need to refresh accessible folders
      if (typeof refreshUserFolders === 'function') {
        refreshUserFolders()
      }
    })

    // User events
    tpWs.on('user_keys_ready', function(data) {
      showNotification('success', 'Compte prêt', 'Votre compte est maintenant opérationnel')
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
      showNotification('error', 'Session expirée', data.reason || 'Veuillez vous reconnecter')
      setTimeout(function() {
        window.location.href = 'includes/core/logout.php?session_expired=1'
      }, 2000)
    })

    // System events
    tpWs.on('system_maintenance', function(data) {
      showNotification('warning', 'Maintenance', data.message)
    })

    // Reconnection failed
    tpWs.on('reconnect_failed', function() {
      showNotification('error', 'Connexion perdue', 'Impossible de se reconnecter au serveur')
    })

    // Debug: log all events
    if (window.TeamPassWebSocketDebug) {
      tpWs.on('*', function(eventName, data) {
        console.log('[TeamPass WS Event]', eventName, data)
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
        console.log('[TeamPass WS] Subscribed to folder', folderId)
      })
      .catch(function(err) {
        console.error('[TeamPass WS] Failed to subscribe to folder', folderId, err)
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
    indicator.title = connected ? 'Temps réel: connecté' : 'Temps réel: déconnecté'
  }

  /**
   * Show notification using Toastr or fallback
   */
  function showNotification(type, title, message) {
    if (typeof toastr !== 'undefined') {
      toastr[type](message, title)
    } else if (typeof alertify !== 'undefined') {
      alertify[type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'success'](title + ': ' + message)
    } else {
      console.log('[Notification]', type, title, message)
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
          'Progression',
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
    var message = data.message || (data.status === 'completed' ? 'Opération terminée' : 'Opération échouée')

    showNotification(type, data.task_type || 'Tâche', message)

    // Trigger custom event
    $(document).trigger('teampass:task:completed', [data])
  }

  // Expose functions globally
  window.tpWsSubscribeToFolder = subscribeToFolder
  window.tpWsShowNotification = showNotification

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init)
  } else {
    init()
  }

})(window, typeof jQuery !== 'undefined' ? jQuery : null);

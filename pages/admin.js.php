<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      admin.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;

$session = SessionManager::getSession();
$lang = new Language($session->get('user-language') ?? 'english');

?>

<script type='text/javascript'>
//<![CDATA[

// ===================================
// REFRESH MANAGER - Intelligent auto-refresh
// ===================================

const AdminRefreshManager = {
    timers: {},
    isPageVisible: true,
    
    /**
     * Initialize the refresh manager
     * 
     * @return {void}
     */
    init: function() {
        // Listen for page visibility changes
        document.addEventListener('visibilitychange', () => {
            this.isPageVisible = !document.hidden
            
            if (this.isPageVisible) {
                console.log('Page visible - resuming auto-refresh')
                this.resumeAll()
            } else {
                console.log('Page hidden - pausing auto-refresh')
                this.pauseAll()
            }
        })
        
        console.log('AdminRefreshManager initialized')
    },
    
    /**
     * Start a new refresh timer
     * 
     * @param {string} name - Timer identifier
     * @param {function} callback - Function to call on refresh
     * @param {number} interval - Refresh interval in milliseconds
     * @param {string} countdownElementId - Optional countdown display element
     * @return {void}
     */
    start: function(name, callback, interval, countdownElementId) {
        if (!this.isPageVisible) {
            console.log(`Not starting timer ${name} - page not visible`)
            return
        }
        
        // Clear existing timer if any
        this.stop(name)
        
        const intervalSeconds = Math.floor(interval / 1000)
        let remainingSeconds = intervalSeconds
        
        // Execute immediately
        callback()
        
        // Start countdown timer if element provided
        let countdownTimer = null
        if (countdownElementId) {
            countdownTimer = setInterval(() => {
                remainingSeconds--
                if (remainingSeconds < 0) remainingSeconds = intervalSeconds
                $(`#${countdownElementId}`).text(`${remainingSeconds}s`)
            }, 1000)
        }
        
        // Start main refresh timer
        const mainTimer = setInterval(() => {
            callback()
            remainingSeconds = intervalSeconds
        }, interval)
        
        this.timers[name] = {
            callback: callback,
            interval: interval,
            mainTimer: mainTimer,
            countdownTimer: countdownTimer,
            countdownElementId: countdownElementId
        }
        
        console.log(`Started timer: ${name} with interval ${interval}ms`)
    },
    
    /**
     * Stop a specific refresh timer
     * 
     * @param {string} name - Timer identifier
     * @return {void}
     */
    stop: function(name) {
        if (this.timers[name]) {
            clearInterval(this.timers[name].mainTimer)
            if (this.timers[name].countdownTimer) {
                clearInterval(this.timers[name].countdownTimer)
            }
            delete this.timers[name]
            console.log(`Stopped timer: ${name}`)
        }
    },
    
    /**
     * Pause all timers
     * 
     * @return {void}
     */
    pauseAll: function() {
        Object.keys(this.timers).forEach(name => {
            clearInterval(this.timers[name].mainTimer)
            if (this.timers[name].countdownTimer) {
                clearInterval(this.timers[name].countdownTimer)
            }
        })
        console.log('All timers paused')
    },
    
    /**
     * Resume all timers
     * 
     * @return {void}
     */
    resumeAll: function() {
        Object.keys(this.timers).forEach(name => {
            const timer = this.timers[name]
            
            // Restart main timer
            timer.mainTimer = setInterval(timer.callback, timer.interval)
            
            // Restart countdown timer if exists
            if (timer.countdownElementId) {
                const intervalSeconds = Math.floor(timer.interval / 1000)
                let remainingSeconds = intervalSeconds
                
                timer.countdownTimer = setInterval(() => {
                    remainingSeconds--
                    if (remainingSeconds < 0) remainingSeconds = intervalSeconds
                    $(`#${timer.countdownElementId}`).text(`${remainingSeconds}s`)
                }, 1000)
            }
        })
        console.log('All timers resumed')
    },
    
    /**
     * Stop all timers
     * 
     * @return {void}
     */
    stopAll: function() {
        Object.keys(this.timers).forEach(name => this.stop(name))
        console.log('All timers stopped')
    }
}

// ===================================
// DASHBOARD FUNCTIONS
// ===================================

/**
 * Load dashboard statistics (users, items, folders, logs)
 * 
 * @return {void}
 */
function loadDashboardStats() {
    $('#loading-stat-users, #loading-stat-items, #loading-stat-folders, #loading-stat-logs').show()
    
    $.ajax({
        url: 'sources/admin.queries.php',
        type: 'POST',
        dataType: 'json',
        data: {
            type: 'get_dashboard_stats',
            key: '<?php echo $session->get('key'); ?>'
        },
        success: function(data) {
            if (data.error === false) {
                // Users stats
                $('#stat-users-active').text(data.users.active)
                $('#stat-users-online').text(data.users.online)
                $('#stat-users-blocked').text(data.users.blocked)
                
                // Items stats
                $('#stat-items-total').text(data.items.total)
                $('#stat-items-shared').text(data.items.shared)
                $('#stat-items-personal').text(data.items.personal)
                
                // Folders stats
                $('#stat-folders-total').text(data.folders.total)
                $('#stat-folders-public').text(data.folders.public)
                $('#stat-folders-personal').text(data.folders.personal)
                
                // Logs stats
                $('#stat-logs-actions').text(data.logs.actions)
                $('#stat-logs-accesses').text(data.logs.accesses)
                $('#stat-logs-errors').text(data.logs.errors)
                
                updateLastRefreshTime()
            } else {
                showErrorToast(data.message || '<?php echo $lang->get('error_occurred'); ?>')
            }
        },
        error: handleAjaxError,
        complete: function() {
            $('#loading-stat-users, #loading-stat-items, #loading-stat-folders, #loading-stat-logs').hide()
        }
    })
}

/**
 * Load live activity feed
 * 
 * @return {void}
 */
function loadLiveActivity() {
    $('#loading-activity').show()
    
    $.ajax({
        url: 'sources/admin.queries.php',
        type: 'POST',
        dataType: 'json',
        data: {
            type: 'get_live_activity',
            key: '<?php echo $session->get('key'); ?>'
        },
        success: function(data) {
            if (data.error === false && data.activities && data.activities.length > 0) {
                let html = ''
                
                data.activities.forEach(activity => {
                    const iconClass = getActivityIcon(activity.action)
                    const timeAgo = formatTimeAgo(activity.timestamp)
                    
                    html += `
                        <li class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <small class="text-muted">
                                    <i class="far fa-clock"></i> ${timeAgo}
                                </small>
                            </div>
                            <p class="mb-1">
                                <i class="${iconClass}"></i> 
                                <strong>${escapeHtml(activity.user_login)}</strong> 
                                ${escapeHtml(activity.action_text)}
                                ${activity.item_label ? `"<em>${escapeHtml(activity.item_label)}</em>"` : ''}
                            </p>
                        </li>
                    `
                })
                
                $('#live-activity-list').html(html)
            } else if (data.activities && data.activities.length === 0) {
                $('#live-activity-list').html(`
                    <li class="list-group-item text-center text-muted">
                        <i class="fas fa-info-circle"></i> <?php echo $lang->get('admin_no_recent_activity'); ?>
                    </li>
                `)
            } else {
                showErrorToast(data.message || '<?php echo $lang->get('error_occurred'); ?>')
            }
        },
        error: handleAjaxError,
        complete: function() {
            $('#loading-activity').hide()
        }
    })
}

/**
 * Load system status (CPU, RAM, disk, tasks)
 * 
 * @return {void}
 */
function loadSystemStatus() {
    $('#loading-status').show()
    
    $.ajax({
        url: 'sources/admin.queries.php',
        type: 'POST',
        dataType: 'json',
        data: {
            type: 'get_system_status',
            key: '<?php echo $session->get('key'); ?>'
        },
        success: function(data) {
            if (data.error === false) {
                $('#system-tasks').text(data.tasks_queue)
                $('#system-last-cron').text(data.last_cron)
            } else {
                showErrorToast(data.message || '<?php echo $lang->get('error_occurred'); ?>')
            }
        },
        error: handleAjaxError,
        complete: function() {
            $('#loading-status').hide()
        }
    })
}

/**
 * Load system health information
 * 
 * @return {void}
 */
function loadSystemHealth() {
    $('#loading-health').show()
    
    $.ajax({
        url: 'sources/admin.queries.php',
        type: 'POST',
        dataType: 'json',
        data: {
            type: 'get_system_health',
            key: '<?php echo $session->get('key'); ?>'
        },
        success: function(data) {
            if (data.error === false) {
                updateHealthBadge('health-encryption', data.encryption.status, data.encryption.text)
                updateHealthBadge('health-database', data.database.status, data.database.text)
                updateHealthBadge('health-sessions', 'info', data.sessions.count + ' <?php echo $lang->get('admin_active'); ?>')
                updateHealthBadge('health-cron', data.cron.status, data.cron.text)
                updateHealthBadge('health-unknown-files', data.unknown_files.count > 0 ? 'warning' : 'success', 
                    data.unknown_files.count + ' <?php echo $lang->get('admin_detected'); ?>')
            } else {
                showErrorToast(data.message || '<?php echo $lang->get('error_occurred'); ?>')
            }
        },
        error: handleAjaxError,
        complete: function() {
            $('#loading-health').hide()
        }
    })
}

/**
 * Update health badge status
 * 
 * @param {string} elementId - Badge element ID
 * @param {string} status - Status type (success, warning, danger, info)
 * @param {string} text - Badge text
 * @return {void}
 */
function updateHealthBadge(elementId, status, text) {
    $(`#${elementId}`)
        .removeClass('badge-success badge-warning badge-danger badge-info')
        .addClass(`badge-${status}`)
        .text(text)
}

/**
 * Initialize dashboard tab with auto-refresh
 * 
 * @return {void}
 */
function initDashboardTab() {
    console.log('Initializing dashboard tab')
    
    // Load initial data
    loadDashboardStats()
    loadLiveActivity()
    loadSystemStatus()
    loadSystemHealth()
    
    // Start auto-refresh timers
    AdminRefreshManager.start('dashboard_stats', loadDashboardStats, 30000, 'stats-refresh-countdown')
    AdminRefreshManager.start('live_activity', loadLiveActivity, 10000, 'activity-refresh-countdown')
    AdminRefreshManager.start('system_status', loadSystemStatus, 60000, 'status-refresh-countdown')
    AdminRefreshManager.start('system_health', loadSystemHealth, 60000, null)
}

// ===================================
// UTILITY FUNCTIONS
// ===================================

/**
 * Update last refresh timestamp
 * 
 * @return {void}
 */
function updateLastRefreshTime() {
    const now = new Date()
    const timeString = now.toLocaleTimeString()
    $('#last-refresh-time').text(timeString)
}

/**
 * Get icon class for activity type
 * 
 * @param {string} action - Action type
 * @return {string} - Icon class
 */
function getActivityIcon(action) {
    const icons = {
        'access': 'fas fa-eye text-info',
        'create': 'fas fa-plus-circle text-success',
        'modify': 'fas fa-edit text-warning',
        'delete': 'fas fa-trash text-danger',
        'login': 'fas fa-sign-in-alt text-primary',
        'logout': 'fas fa-sign-out-alt text-secondary'
    }
    return icons[action] || 'fas fa-info-circle text-muted'
}

/**
 * Format timestamp as time ago
 * 
 * @param {number} timestamp - Unix timestamp
 * @return {string} - Formatted time ago string
 */
function formatTimeAgo(timestamp) {
    const now = Math.floor(Date.now() / 1000)
    const diff = now - timestamp
    
    if (diff < 60) return `${diff}s <?php echo $lang->get('ago'); ?>`
    if (diff < 3600) return `${Math.floor(diff / 60)}m <?php echo $lang->get('ago'); ?>`
    if (diff < 86400) return `${Math.floor(diff / 3600)}h <?php echo $lang->get('ago'); ?>`
    return `${Math.floor(diff / 86400)}d <?php echo $lang->get('ago'); ?>`
}

/**
 * Escape HTML to prevent XSS
 * 
 * @param {string} text - Text to escape
 * @return {string} - Escaped text
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }
    return String(text).replace(/[&<>"']/g, m => map[m])
}

/**
 * Display error toast notification
 * 
 * @param {string} message - Error message
 * @return {void}
 */
function showErrorToast(message) {
    toastr.error(message, '<?php echo $lang->get('error'); ?>', {
        closeButton: true,
        progressBar: true,
        timeOut: 5000,
        positionClass: 'toast-top-right'
    })
}

/**
 * Display success toast notification
 * 
 * @param {string} message - Success message
 * @return {void}
 */
function showSuccessToast(message) {
    toastr.success(message, '<?php echo $lang->get('success'); ?>', {
        closeButton: true,
        progressBar: true,
        timeOut: 3000,
        positionClass: 'toast-top-right'
    })
}

/**
 * Handle AJAX errors
 * 
 * @param {object} xhr - XMLHttpRequest object
 * @param {string} status - Error status
 * @param {string} error - Error message
 * @return {void}
 */
function handleAjaxError(xhr, status, error) {
    console.error('AJAX Error:', status, error)
    showErrorToast('<?php echo $lang->get('error_ajax_request'); ?>')
}

// ===================================
// QUICK ACTIONS HANDLERS
// ===================================

/**
 * Reload cache table
 * 
 * @return {void}
 */
function reloadCacheTable() {
    const btn = $('#btn-reload-cache')
    btn.prop('disabled', true).html('<i class="fas fa-spin fa-spinner"></i> <?php echo $lang->get('admin_action_processing'); ?>')
    
    $.ajax({
        url: 'sources/admin.queries.php',
        type: 'POST',
        dataType: 'json',
        data: {
            type: 'reload_cache_table',
            key: '<?php echo $session->get('key'); ?>'
        },
        success: function(data) {
            if (data.error === false) {
                showSuccessToast('<?php echo $lang->get('admin_action_cache_reloaded'); ?>')
            } else {
                showErrorToast(data.message || '<?php echo $lang->get('error_occurred'); ?>')
            }
        },
        error: handleAjaxError,
        complete: function() {
            btn.prop('disabled', false).html('<i class="fas fa-sync"></i> <?php echo $lang->get('admin_action_reload_cache'); ?>')
        }
    })
}

/**
 * Clean old logs
 * 
 * @return {void}
 */
function cleanOldLogs() {
    if (!confirm('<?php echo $lang->get('admin_action_clean_logs_confirm'); ?>')) return
    
    const btn = $('#btn-clean-logs')
    btn.prop('disabled', true).html('<i class="fas fa-spin fa-spinner"></i> <?php echo $lang->get('admin_action_processing'); ?>')
    
    $.ajax({
        url: 'sources/admin.queries.php',
        type: 'POST',
        dataType: 'json',
        data: {
            type: 'clean_old_logs',
            key: '<?php echo $session->get('key'); ?>'
        },
        success: function(data) {
            if (data.error === false) {
                showSuccessToast('<?php echo $lang->get('admin_action_logs_cleaned'); ?> (' + data.deleted_count + ')')
            } else {
                showErrorToast(data.message || '<?php echo $lang->get('error_occurred'); ?>')
            }
        },
        error: handleAjaxError,
        complete: function() {
            btn.prop('disabled', false).html('<i class="fas fa-broom"></i> <?php echo $lang->get('admin_action_clean_logs'); ?>')
        }
    })
}

/**
 * Export statistics
 * 
 * @return {void}
 */
function exportStatistics() {
    showSuccessToast('<?php echo $lang->get('admin_action_export_started'); ?>')
    window.location.href = 'sources/admin.queries.php?type=export_statistics&key=<?php echo $session->get('key'); ?>'
}

// ===================================
// TAB MANAGEMENT
// ===================================

/**
 * Handle tab change events
 * 
 * @param {object} e - Event object
 * @return {void}
 */
function handleTabChange(e) {
    const tabId = $(e.target).attr('href')
    console.log('Tab changed to:', tabId)
    
    // Stop all refresh timers when changing tabs
    AdminRefreshManager.stopAll()
    
    // Initialize content for specific tabs
    switch(tabId) {
        case '#tab-dashboard':
            initDashboardTab()
            break
        case '#tab-system':
            loadSystemSettings()
            break
        case '#tab-users':
            loadUsersContent()
            break
        case '#tab-folders':
            loadFoldersContent()
            break
        case '#tab-security':
            loadSecurityContent()
            break
        case '#tab-database':
            loadDatabaseContent()
            break
        case '#tab-tools':
            loadToolsContent()
            break
    }
}

/**
 * Load system settings (placeholder)
 * 
 * @return {void}
 */
function loadSystemSettings() {
    $('#system-settings-container').html('<p class="text-muted"><?php echo $lang->get('admin_system_settings_placeholder'); ?></p>')
}

/**
 * Load users content (placeholder)
 * 
 * @return {void}
 */
function loadUsersContent() {
    $('#users-content-container').html('<p class="text-muted"><?php echo $lang->get('admin_users_content_placeholder'); ?></p>')
}

/**
 * Load folders content (placeholder)
 * 
 * @return {void}
 */
function loadFoldersContent() {
    $('#folders-content-container').html('<p class="text-muted"><?php echo $lang->get('admin_folders_content_placeholder'); ?></p>')
}

/**
 * Load security content (placeholder)
 * 
 * @return {void}
 */
function loadSecurityContent() {
    $('#security-content-container').html('<p class="text-muted"><?php echo $lang->get('admin_security_content_placeholder'); ?></p>')
}

/**
 * Load database content (placeholder)
 * 
 * @return {void}
 */
function loadDatabaseContent() {
    $('#database-content-container').html('<p class="text-muted"><?php echo $lang->get('admin_database_content_placeholder'); ?></p>')
}

/**
 * Load tools content (placeholder)
 * 
 * @return {void}
 */
function loadToolsContent() {
    $('#tools-content-container').html('<p class="text-muted"><?php echo $lang->get('admin_tools_content_placeholder'); ?></p>')
}

/**
 * Perform DB integrity check
 */
function performDBIntegrityCheck()
{
    $.post(
        "sources/admin.queries.php", {
            type: "tablesIntegrityCheck",
            key: "<?php echo $session->get('key'); ?>"
        },
        function(data) {
            // Handle server answer
            try {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
            } catch (e) {
                // error
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                    '', {
                        closeButton: true,
                        positionClass: 'toast-bottom-right'
                    }
                );
                return false;
            }
            
            let html = '',
                tablesInError = '',
                cnt = 0,
                tables = JSON.parse(data.tables);
            if (data.error === false) {
                $.each(tables, function(i, value) {
                    if (cnt < 5) {
                        tablesInError += '<li>' + value + '</li>';
                    } else {
                        tablesInError += '<li>...</li>';
                        return false;
                    }
                    cnt++;
                });

                if (tablesInError === '') {
                    html = '<i class="fa-solid fa-circle-check text-success mr-2"></i><span class="badge badge-secondary mr-2">Experimental</span>Database integrity check is successfull';
                } else {
                    html = '<i class="fa-solid fa-circle-xmark text-warning mr-2"></i><span class="badge badge-secondary mr-2">Experimental</span>Database integrity check has identified issues with the following tables:'
                        + '<i class="fa-regular fa-circle-question infotip ml-2 text-info" title="You should consider to run Upgrade process to fix this or perform manual changes on tables"></i>';
                    html += '<ul class="fs-6">' + tablesInError + '</ul>';
                }
            } else {
                html = '<i class="fa-solid fa-circle-xmark text-danger mr-2"></i><span class="badge badge-secondary mr-2">Experimental</span>Database integrity check could not be performed!'
                    + 'Error returned: ' + data.message;
            }
            $('#health-database-integrity').html(html);                

            requestRunning = false;

            performSimulateUserKeyChangeDuration();
        }
    );
}

/**
 * Perform simulate user key change
 */
function performSimulateUserKeyChangeDuration() {
    $.post(
        "sources/admin.queries.php", {
            type: "performSimulateUserKeyChangeDuration",
            key: "<?php echo $session->get('key'); ?>"
        },
        function(data) {
            // Handle server answer
            try {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
            } catch (e) {
                // error
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                    '', {
                        closeButton: true,
                        positionClass: 'toast-bottom-right'
                    }
                );
                return false;
            }
            
            let html = '';
            if (data.error === false) {
                if (data.setupProposal === false && data.estimatedTime !== null) {
                    html = '<i class="fa-solid fa-circle-exclamation text-warning mr-2"></i>'
                        + 'Estimated time to process all keys is about <b>' + data.estimatedTime + '</b> seconds.<br/>'
                        + 'It is suggested to allow <b>' + data.proposedDuration + '</b> seconds for a background task to run.<br/>'
                        + 'You should adapt from <a href="index.php?page=tasks">Tasks Parameters page</a>.';
                    
                    $('#task_duration_status')
                        .html(html)
                        .removeClass('hidden');
                }
            }

            requestRunning = false;
        }
    );
}

/**
 * Perform project files integrity check
 */
function performProjectFilesIntegrityCheck(refreshingData = false)
{
    if (requestRunning === true) {
        return false;
    }

    requestRunning = true;
    $('#check-project-files-btn').html('<i class="fa-solid fa-spinner fa-spin"></i>');

    // Remove the file from the list
    if (refreshingData === false) {
        $('#files-integrity-result').remove();
        $('#files-integrity-result-container').addClass('hidden');
    }

    $.post(
        "sources/admin.queries.php", {
            type: "filesIntegrityCheck",
            key: "<?php echo $session->get('key'); ?>"
        },
        function(data) {
            // Handle server answer
            try {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
            } catch (e) {
                // error
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                    '', {
                        closeButton: true,
                        positionClass: 'toast-bottom-right'
                    }
                );
                return false;
            }
            
            let html = '';
            if (data.error === false) {
                html = '<i class="fa-solid fa-circle-check text-success mr-2"></i>Project files integrity check is successfull';
            } else {
                // Create a list
                let ul = '<div class="border rounded p-2" style="max-height: 400px; overflow-y: auto;"><ul id="files-integrity-result" class="">';
                let files = JSON.parse(data.files);
                let numberOfFiles = Object.keys(files).length;
                $.each(files, function(i, value) {
                    ul += '<li value="'+i+'">' + value + '</li>';
                });

                // Prepare the HTML
                html = '<b>' + numberOfFiles + '</b> <?php echo $lang->get('files_are_not_expected_ones'); ?>.' + 
                    '<div class="alert alert-light" role="alert" id="files-integrity-result-container">' +
                    '<div class="alert alert-warning" role="alert"><?php echo $lang->get('unknown_files_should_be_deleted'); ?>' +
                    '<div class="btn-group ml-2" role="group">'+
                        '<button type="button" class="btn btn-primary btn-sm infotip" id="refresh_unknown_files" title="<?php echo $lang->get('refresh'); ?>"><i class="fa-solid fa-arrows-rotate"></i></button>' +
                        '<button type="button" class="btn btn-danger btn-sm infotip" id="delete_unknown_files" title="<?php echo $lang->get('delete'); ?>"><i class="fa-solid fa-trash"></i></button>' +
                    '</div></div>' +
                    ul + '</ul></div></div>';                        

                // Create the button to show/hide the list
                $(document)
                    .on('click', '#refresh_unknown_files', function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        // Show loader
                        $('#files-integrity-result').html('<i class="fa-solid fa-spinner fa-spin"></i>');
                        // Launch the integrity check
                        performProjectFilesIntegrityCheck(true);
                    })
                    .on('click', '#delete_unknown_files', function(event) {   
                        event.preventDefault();
                        event.stopPropagation();                         
                        // Ask the user if he wants to delete the files
                        if (confirm('<?php echo $lang->get('delete_unknown_files'); ?>')) {
                            // Show loader
                            $('#files-integrity-result').html('<i class="fa-solid fa-spinner fa-spin"></i>');
                        } else {
                            // Cancel
                            return false;
                        }
                        // Launch delete unknown files
                        performDeleteFilesIntegrityCheck();
                    });
            }
            // Display the result
            //$('#project-files-check-status').html(html);

            // Prepare modal
            showModalDialogBox(
                '#warningModal',
                '<i class="fas fa-eye fa-lg warning mr-2"></i><?php echo $lang->get('files_integrity_check'); ?>',
                html,
                '',
                '<?php echo $lang->get('close'); ?>',
                true
            );

            // Actions on modal buttons
            $(document).on('click', '#warningModalButtonClose', function() {
                // Nothing
            });

            $('#check-project-files-btn').html('<i class="fas fa-caret-right"></i>');

            requestRunning = false;
        }
    );
}

/**
 * Perform delete unknown files
 */
function performDeleteFilesIntegrityCheck()
{
    $.post(
        "sources/admin.queries.php", {
            type: "deleteFilesIntegrityCheck",
            key: "<?php echo $session->get('key'); ?>"
        },
        function(data) {
            // Handle server answer
            try {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
            } catch (e) {
                // error
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                    '', {
                        closeButton: true,
                        positionClass: 'toast-bottom-right'
                    }
                );
                return false;
            }

            if (data.deletionResults === '') {
                // No files to delete
                $('#files-integrity-result').html('<i class="fa-solid fa-circle-check text-success mr-2"></i><?php echo $session->get('done'); ?>');
                return false;
            }

            // Display the result as a list
            // Initialize the HTML output
            let output = '<ul style="margin-left:-60px;">';
            let showSuccessful = true;
            
            // Process each file result
            $.each(data.deletionResults, function(file, result) {
                // Skip successful operations if not showing them
                if (!showSuccessful && result.success) {
                    return true; // continue to next iteration
                }
                
                //const className = result.success ? 'success' : 'error';
                const icon = result.success ? '<i class="fa-solid fa-check text-success mr-1"></i>' : '<i class="fa-solid fa-xmark text-danger mr-1"></i>';
                const message = result.success ? '<?php echo $lang->get('server_returned_data');?>' : 'Error: ' + result.error;
                
                output += '<li>' + icon + '<b>' + file + '</b><br/>' + message + '</li>';
            });
            
            output += '</ul>';

            $('#files-integrity-result').html(output);

        }
    );
}

/**
 * Build the dialog with Transparent Recovery status
 */
function performTransparentRecoveryCheck()
{
    if (requestRunning === true) {
        return false;
    }

    requestRunning = true;
    $('#check-transparent-recovery-btn').html('<i class="fa-solid fa-spinner fa-spin"></i>');

    // Remove the file from the list
    $('#transparent-recovery-result').remove();
    $('#transparent-recovery-result-container').addClass('hidden');

    $.post(
        "sources/admin.queries.php", {
            type: "transparentRecoveryCheck",
            key: "<?php echo $session->get('key'); ?>"
        },
        function(data) {
            // Handle server answer
            try {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
            } catch (e) {
                // error
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                    '', {
                        closeButton: true,
                        positionClass: 'toast-bottom-right'
                    }
                );
                return false;
            }
            
            let stats = data.stats;
            
            // Build full html
            let html = '<div class="container-fluid p-0">';
            
            // === Main stats ===
            html += '<div class="row mb-3">' +
                '<div class="col-md-4 mb-2">' +
                    '<div class="card text-center border-success">' +
                        '<div class="card-body py-2">' +
                            '<h4 class="text-success mb-0">' + stats.users_migrated + '</h4>' +
                            '<small class="text-muted">Migrated Users</small>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="col-md-4 mb-2">' +
                    '<div class="card text-center border-info">' +
                        '<div class="card-body py-2">' +
                            '<h4 class="text-info mb-0">' + stats.total_users + '</h4>' +
                            '<small class="text-muted">Total Users</small>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="col-md-4 mb-2">' +
                    '<div class="card text-center border-primary">' +
                        '<div class="card-body py-2">' +
                            '<h4 class="text-primary mb-0">' + stats.migration_percentage + '%</h4>' +
                            '<small class="text-muted">Progress</small>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

            // === Progress bar ===
            html += '<div class="mb-3">' +
                        '<label class="font-weight-bold mb-1">Migration Progress</label>' +
                        '<div class="progress" style="height: 25px;">' +
                            '<div class="progress-bar progress-bar-striped bg-success" ' +
                                'role="progressbar" ' +
                                'style="width: ' + stats.migration_percentage + '%">' +
                                stats.migration_percentage + '%' +
                            '</div>' +
                        '</div>' +
                    '</div>';

            // === Error stats ===
            html += '<div class="row mb-3">' +
                        '<div class="col-md-4 mb-2">' +
                            '<div class="card text-center border-warning">' +
                                '<div class="card-body py-2">' +
                                    '<h5 class="text-warning mb-0">' + stats.auto_recoveries_last_24h + '</h5>' +
                                    '<small class="text-muted">Auto Recoveries (24h)</small>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="col-md-4 mb-2">' +
                            '<div class="card text-center border-danger">' +
                                '<div class="card-body py-2">' +
                                    '<h5 class="text-danger mb-0">' + stats.failed_recoveries_total + '</h5>' +
                                    '<small class="text-muted">Total Failures</small>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="col-md-4 mb-2">' +
                            '<div class="card text-center border-danger">' +
                                '<div class="card-body py-2">' +
                                    '<h5 class="text-danger mb-0">' + stats.critical_failures_total + '</h5>' +
                                    '<small class="text-muted">Critical Failures</small>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>';

            // === Failure rate ===
            html += '<div class="mb-3">' +
                        '<div class="card border-secondary">' +
                            '<div class="card-body py-2 d-flex justify-content-between align-items-center">' +
                                '<span class="font-weight-bold">Failure Rate (30 days)</span>' +
                                '<span class="badge badge-secondary badge-pill" style="font-size: 1.1rem;">' +
                                    stats.failure_rate_30d + '%' +
                                '</span>' +
                            '</div>' +
                        '</div>' +
                    '</div>';

            // === Recent events ===
            html += '<div class="card">' +
                        '<div class="card-header bg-dark text-white py-2">' +
                            '<h6 class="mb-0">' +
                                '<i class="fas fa-history mr-2"></i>' +
                                'Recent Events (' + stats.recent_events.length + ')' +
                            '</h6>' +
                        '</div>' +
                        '<div class="card-body p-0">' +
                            '<div style="max-height: 300px; overflow-y: auto;">';
            
            // Events list
            if (stats.recent_events.length === 0) {
                html += '<div class="text-center p-3 text-muted">No recent events</div>';
            } else {
                html += '<ul class="list-group list-group-flush">';
                
                $.each(stats.recent_events, function(i, event) {
                    // Format the date
                    let eventDate = new Date(event.date * 1000);
                    let formattedDate = eventDate.toLocaleString('fr-FR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    // Define icons and badge classes
                    let iconClass = 'fa-check-circle text-success';
                    let badgeClass = 'badge-success';
                    
                    if (event.label.includes('failure') || event.label.includes('error')) {
                        iconClass = 'fa-times-circle text-danger';
                        badgeClass = 'badge-danger';
                    } else if (event.label.includes('warning')) {
                        iconClass = 'fa-exclamation-triangle text-warning';
                        badgeClass = 'badge-warning';
                    }
                    
                    // Build event
                    html += '<li class="list-group-item py-2">' +
                                '<div class="d-flex justify-content-between align-items-start">' +
                                    '<div>' +
                                        '<i class="fas ' + iconClass + ' mr-2"></i>' +
                                        '<span class="badge ' + badgeClass + ' mr-2">' + 
                                            event.label.replace(/_/g, ' ') + 
                                        '</span>' +
                                        '<br>' +
                                        '<small class="text-muted ml-4">' + formattedDate + '</small>' +
                                    '</div>' +
                                    '<span class="badge badge-secondary">User ' + event.login + '</span>' +
                                '</div>' +
                            '</li>';
                });
                
                html += '</ul>';
            }
            
            html += '</div>' + // end max-height
                        '</div>' + // end card-body
                    '</div>' + // end card
                    '</div>'; // end container-fluid

            // Show modal
            showModalDialogBox(
                '#warningModal',
                '<i class="fas fa-chart-bar fa-lg mr-2"></i>Migration statistics',
                html,
                '',
                'Close',
                true
            );

            // Actions on modal buttons
            $(document).on('click', '#warningModalButtonClose', function() {
                // Nothing
            });

            $('#check-transparent-recovery-btn').html('<i class="fas fa-caret-right"></i>');

            requestRunning = false;
        }
    );
}

/**
 * Build the dialog with Personal Items Migration status
 */
function performPersonalItemsMigrationCheck()
{
    if (requestRunning === true) {
        return false;
    }

    requestRunning = true;
    $('#personal-items-migration-btn').html('<i class="fa-solid fa-spinner fa-spin"></i>');

    // Remove the file from the list
    $('#personal-items-migration-result').remove();
    $('#personal-items-migration-result-container').addClass('hidden');

    $.post(
        "sources/admin.queries.php", {
            type: "personalItemsMigrationCheck",
            key: "<?php echo $session->get('key'); ?>"
        },
        function(data) {
            // Handle server answer
            try {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
            } catch (e) {
                // error
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                    '', {
                        closeButton: true,
                        positionClass: 'toast-bottom-right'
                    }
                );
                return false;
            }
            
            // Get statistics values
            let stats = data.stats;
            
            // Build full html
            let html = '<div class="container-fluid p-0">';
            
            // === Main stats ===
            html += '<div class="row mb-3">' +
                '<div class="col-md-4 mb-2">' +
                    '<div class="card text-center border-success">' +
                        '<div class="card-body py-2">' +
                            '<h4 class="text-success mb-0">' + stats.doneUsers.length + '</h4>' +
                            '<small class="text-muted">Migrated Users</small>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="col-md-4 mb-2">' +
                    '<div class="card text-center border-warning">' +
                        '<div class="card-body py-2">' +
                            '<h4 class="text-warning mb-0">' + stats.pendingUsers.length + '</h4>' +
                            '<small class="text-muted">Pending Users</small>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="col-md-4 mb-2">' +
                    '<div class="card text-center border-info">' +
                        '<div class="card-body py-2">' +
                            '<h4 class="text-info mb-0">' + stats.totalUsers + '</h4>' +
                            '<small class="text-muted">Total Users</small>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

            // === Progress bar ===
            html += '<div class="mb-3">' +
                        '<label class="font-weight-bold mb-1">Migration Progress</label>' +
                        '<div class="progress" style="height: 25px;">' +
                            '<div class="progress-bar progress-bar-striped bg-success" ' +
                                'role="progressbar" ' +
                                'style="width: ' + stats.progressPercent + '%">' +
                                Math.round(stats.progressPercent) + '%' +
                            '</div>' +
                        '</div>' +
                    '</div>';

            // === Migrated Users ===
            html += '<div class="card mb-3">' +
                        '<div class="card-header bg-success text-white py-2">' +
                            '<h6 class="mb-0">' +
                                '<i class="fas fa-check-circle mr-2"></i>' +
                                'Migrated Users (' + stats.doneUsers.length + ')' +
                            '</h6>' +
                        '</div>' +
                        '<div class="card-body p-0">' +
                            '<div style="max-height: 250px; overflow-y: auto;">';
            
            // Migrated users list
            if (stats.doneUsers.length === 0) {
                html += '<div class="text-center p-3 text-muted">No users migrated yet</div>';
            } else {
                html += '<table class="table table-sm table-hover mb-0">' +
                            '<thead class="thead-light">' +
                                '<tr>' +
                                    '<th style="width: 15%;">User ID</th>' +
                                    '<th style="width: 25%;">Login</th>' +
                                    '<th style="width: 35%;">Email</th>' +
                                    '<th style="width: 25%;">Last Login</th>' +
                                '</tr>' +
                            '</thead>' +
                            '<tbody>';
                
                $.each(stats.doneUsers, function(i, user) {
                    // Format the date
                    let lastLogin = '-';
                    if (user.last_connexion && user.last_connexion !== '' && user.last_connexion !== null) {
                        let loginDate = new Date(user.last_connexion * 1000);
                        lastLogin = loginDate.toLocaleString('en-GB', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }
                    
                    html += '<tr>' +
                                '<td><span class="badge badge-secondary">' + user.id + '</span></td>' +
                                '<td><strong>' + htmlEncode(user.login) + '</strong></td>' +
                                '<td><small class="text-muted">' + htmlEncode(user.email) + '</small></td>' +
                                '<td><small>' + lastLogin + '</small></td>' +
                            '</tr>';
                });
                
                html += '</tbody>' +
                        '</table>';
            }
            
            html += '</div>' + // end max-height
                        '</div>' + // end card-body
                    '</div>'; // end card

            // === Pending Users ===
            html += '<div class="card">' +
                        '<div class="card-header bg-warning text-dark py-2">' +
                            '<h6 class="mb-0">' +
                                '<i class="fas fa-clock mr-2"></i>' +
                                'Pending Users (' + stats.pendingUsers.length + ')' +
                            '</h6>' +
                        '</div>' +
                        '<div class="card-body p-0">' +
                            '<div style="max-height: 250px; overflow-y: auto;">';
            
            // Pending users list
            if (stats.pendingUsers.length === 0) {
                html += '<div class="text-center p-3 text-muted">All users migrated!</div>';
            } else {
                html += '<table class="table table-sm table-hover mb-0">' +
                            '<thead class="thead-light">' +
                                '<tr>' +
                                    '<th style="width: 15%;">User ID</th>' +
                                    '<th style="width: 25%;">Login</th>' +
                                    '<th style="width: 35%;">Email</th>' +
                                    '<th style="width: 25%;">Last Login</th>' +
                                '</tr>' +
                            '</thead>' +
                            '<tbody>';
                
                $.each(stats.pendingUsers, function(i, user) {
                    // Format the date
                    let lastLogin = '<span class="text-danger font-italic">Never</span>';
                    let rowClass = '';
                    
                    if (user.last_connexion && user.last_connexion !== '' && user.last_connexion !== null) {
                        let loginDate = new Date(user.last_connexion * 1000);
                        lastLogin = loginDate.toLocaleString('en-GB', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        // Highlight users who haven't logged in for more than 30 days
                        let daysSinceLogin = (Date.now() / 1000 - user.last_connexion) / 86400;
                        if (daysSinceLogin > 30) {
                            rowClass = 'table-secondary';
                        }
                    } else {
                        rowClass = 'table-danger';
                    }
                    
                    // Highlight system users
                    if (user.login === 'API' || user.login === 'OTV' || user.login === 'TP') {
                        rowClass = 'table-info';
                    }
                    
                    html += '<tr class="' + rowClass + '">' +
                                '<td><span class="badge badge-secondary">' + user.id + '</span></td>' +
                                '<td><strong>' + htmlEncode(user.login) + '</strong></td>' +
                                '<td><small class="text-muted">' + htmlEncode(user.email) + '</small></td>' +
                                '<td><small>' + lastLogin + '</small></td>' +
                            '</tr>';
                });
                
                html += '</tbody>' +
                        '</table>';
            }
            
            html += '</div>' + // end max-height
                        '</div>' + // end card-body
                    '</div>'; // end card

            // === Legend ===
            html += '<div class="mt-3">' +
                        '<small class="text-muted">' +
                            '<i class="fas fa-info-circle mr-1"></i>' +
                            '<span class="badge badge-danger mr-1">Red</span> = Never logged in | ' +
                            '<span class="badge badge-secondary mr-1">Gray</span> = Inactive for 30+ days' +
                        '</small>' +
                    '</div>';

            html += '</div>'; // end container-fluid

            // Show modal
            showModalDialogBox(
                '#warningModal',
                '<i class="fas fa-chart-bar fa-lg mr-2"></i>Migration statistics',
                html,
                '',
                'Close',
                true
            );

            // Actions on modal buttons
            $(document).on('click', '#warningModalButtonClose', function() {
                // Nothing
            });

            $('#personal-items-migration-btn').html('<i class="fas fa-caret-right"></i>');

            requestRunning = false;
        }
    );
}

// ===================================
// DOCUMENT READY
// ===================================

$(document).ready(function() {
    console.log('Admin page initializing...')
    
    // Initialize refresh manager
    AdminRefreshManager.init()
    
    // Initialize dashboard (active tab)
    initDashboardTab()
    
    // Listen for tab changes
    $('.nav-tabs a[data-toggle="pill"]').on('shown.bs.tab', handleTabChange)
    
    // Quick action button handlers
    $('#btn-reload-cache').on('click', reloadCacheTable)
    $('#btn-clean-logs').on('click', cleanOldLogs)
    $('#btn-export-stats').on('click', exportStatistics)

    // Perform DB integrity check
    setTimeout(
        performDBIntegrityCheck,
        500
    );
    
    console.log('Admin page initialized successfully')
})

// Cleanup on page unload
$(window).on('beforeunload', function() {
    AdminRefreshManager.stopAll()
})

//]]>
</script>

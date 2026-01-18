<?php
/**
 * Script to reset PHP opcache
 * Access via: http://localhost/TeamPass/opcache_reset.php
 */

if (function_exists('opcache_reset')) {
    $result = opcache_reset();
    if ($result) {
        echo "✅ OPcache cleared successfully!\n";
        echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    } else {
        echo "❌ Failed to clear OPcache\n";
    }
} else {
    echo "⚠️ OPcache is not enabled\n";
}

echo "\nOPcache Status:\n";
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status(false);
    echo "- Enabled: " . ($status['opcache_enabled'] ? 'Yes' : 'No') . "\n";
    echo "- Cache full: " . ($status['cache_full'] ? 'Yes' : 'No') . "\n";
    echo "- Restart pending: " . ($status['restart_pending'] ? 'Yes' : 'No') . "\n";
    echo "- Restart in progress: " . ($status['restart_in_progress'] ? 'Yes' : 'No') . "\n";
} else {
    echo "Cannot get opcache status\n";
}

// Delete this file after use for security
echo "\n⚠️ DELETE THIS FILE after clearing cache!\n";

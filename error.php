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
 * @file      error.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

if (
    filter_input(INPUT_POST, 'session', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null
    && filter_input(INPUT_POST, 'session', FILTER_SANITIZE_FULL_SPECIAL_CHARS) === 'expired'
) {
    // Update table by deleting ID
    if ($session->has('user-id') && null !== $session->get('user-id')) {
        DB::update(
            DB_PREFIX . 'users',
            [
                'key_tempo' => '',
            ],
            'id=%i',
            $session->get('user-id')
        );
    }

    //Log into DB the user's disconnection
    if (isset($SETTINGS['log_connections']) && (int) $SETTINGS['log_connections'] === 1) {
        logEvents($SETTINGS, 'user_connection', 'disconnect', (string) $session->get('user-id'), $session->get('user-login'));
    }
} else {
    $errorCode = '';
    if (@$session->get('system-error_code') === ERR_NOT_ALLOWED) {
        $errorCode = 'ERROR NOT ALLOWED';
    } elseif (@$session->get('system-error_code') === ERR_NOT_EXIST) {
        $errorCode = 'ERROR NOT EXISTS';
    } elseif (@$session->get('system-error_code') === ERR_SESS_EXPIRED) {
        $errorCode = 'ERROR SESSION EXPIRED';
    } elseif (@$session->get('system-error_code') === ERR_VALID_SESSION) {
        $errorCode = 'ERROR NOT ALLOWED';
    } ?>
    <!-- Main content -->
    <section class="content">
        <div class="error-page" style="width:100%;">
            <h2 class="headline text-danger">500</h2>

            <div class="error-content">
                <h3><i class="fas fa-warning text-danger"></i> Oops! <?php echo $errorCode; ?>.</h3>

                <p>
                    For security reason, you have been disconnected. Click to <a href="./includes/core/logout.php?token=<?php echo $session->get('key'); ?>">log in</a>.
                </p>

                <!-- Redirection Timer Section -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <p style="color: #666; font-size: 14px; margin-bottom: 10px;">
                        Redirecting to home page in <strong><span id="countdown-timer">10</span> seconds</strong>
                    </p>
                    <div style="width: 100%; height: 4px; background: #f0f0f0; border-radius: 2px; overflow: hidden;">
                        <div id="progress-bar" style="height: 100%; background: #dc3545; width: 100%; transition: width 0.1s linear;"></div>
                    </div>
                </div>
                <!-- /.redirection-timer -->

            </div>
            <!-- /.error-content -->
        </div>
        <!-- /.error-page -->
    </section>
    <!-- /.content -->

    <script>
        // Check if user has already been redirected to error page
        if (sessionStorage.getItem('errorPageVisited') === 'true') {
            // Already visited, do nothing to prevent reloading
            // This stops the countdown from restarting
        } else {
            // Mark that error page has been visited
            sessionStorage.setItem('errorPageVisited', 'true');

            // Function to initialize countdown
            function initializeCountdown() {
                let secondsRemaining = 10;
                const timerElement = document.getElementById('countdown-timer');
                const progressBar = document.getElementById('progress-bar');

                // Verify elements exist before starting
                if (!timerElement || !progressBar) {
                    console.error('Countdown elements not found');
                    return;
                }

                // Update timer every second
                const countdownInterval = setInterval(function() {
                    secondsRemaining--;

                    // Update timer text
                    timerElement.textContent = secondsRemaining;

                    // Update progress bar
                    const progress = (secondsRemaining / 10) * 100;
                    progressBar.style.width = progress + '%';

                    // Redirect when timer reaches 0
                    if (secondsRemaining <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = './includes/core/logout.php?token=<?php echo $session->get('key'); ?>';
                    }
                }, 1000); // Update every 1 second
            }

            // Execute immediately if DOM is already loaded, otherwise wait for DOMContentLoaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeCountdown);
            } else {
                // DOM is already loaded (interactive or complete)
                initializeCountdown();
            }
        }
    </script>
<?php
}

// erase session table
$session->invalidate();
die;
?>

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
 * @copyright 2009-2024 Teampass.net
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
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

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

            </div>
            <!-- /.error-content -->
        </div>
        <!-- /.error-page -->
    </section>
    <!-- /.content -->
<?php
}

// erase session table
$session->invalidate();
die;
?>

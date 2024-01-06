<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @version   
 * @file      error.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;

// Load functions
require_once __DIR__.'/sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
$lang = new Language(); 

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}


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
    if (null !== $session->get('user-id')) {
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

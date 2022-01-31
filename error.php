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
 *
 * @file      error.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

if (file_exists('../sources/SecureHandler.php')) {
    include_once '../sources/SecureHandler.php';
} elseif (file_exists('./sources/SecureHandler.php')) {
    include_once './sources/SecureHandler.php';
} else {
    throw new Exception("Error file '/sources/SecureHandler.php' not exists", 1);
}
if (isset($_SESSION) === false) {
    session_name('teampass_session');
    session_start();
}
if (isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

if (
    filter_input(INPUT_POST, 'session', FILTER_SANITIZE_STRING) !== null
    && filter_input(INPUT_POST, 'session', FILTER_SANITIZE_STRING) === 'expired'
) {
    //Include files
    require_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
    require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
    require_once $SETTINGS['cpassman_dir'] . '/sources/SplClassLoader.php';
    require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
    // connect to DB
    require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
    if (defined('DB_PASSWD_CLEAR') === false) {
        define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
    }

    // Include main functions used by TeamPass
    require_once 'sources/main.functions.php';
    // Update table by deleting ID
    if (isset($_SESSION['user_id'])) {
        DB::update(
            DB_PREFIX . 'users',
            [
                'key_tempo' => '',
            ],
            'id=%i',
            $_SESSION['user_id']
        );
    }

    //Log into DB the user's disconnection
    if (isset($SETTINGS['log_connections']) && (int) $SETTINGS['log_connections'] === 1) {
        logEvents($SETTINGS, 'user_connection', 'disconnect', (string) $_SESSION['user_id'], $_SESSION['login']);
    }
} else {
    require_once $SETTINGS['cpassman_dir'] . '/sources/main.queries.php';
    $errorCode = '';
    if (@$_SESSION['error']['code'] === ERR_NOT_ALLOWED) {
        $errorCode = 'ERROR NOT ALLOWED';
    } elseif (@$_SESSION['error']['code'] === ERR_NOT_EXIST) {
        $errorCode = 'ERROR NOT EXISTS';
    } elseif (@$_SESSION['error']['code'] === ERR_SESS_EXPIRED) {
        $errorCode = 'ERROR SESSION EXPIRED';
    } elseif (@$_SESSION['error']['code'] === ERR_VALID_SESSION) {
        $errorCode = 'ERROR NOT ALLOWED';
    } ?>
    <!-- Main content -->
    <section class="content">
        <div class="error-page" style="width:100%;">
            <h2 class="headline text-danger">500</h2>

            <div class="error-content">
                <h3><i class="fa fa-warning text-danger"></i> Oops! <?php echo $errorCode; ?>.</h3>

                <p>
                    For security reason, you have been disconnected. Click to <a href="./includes/core/logout.php?user_id=" + <?php echo $_SESSION['user_id']; ?>>log in</a>.
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
$_SESSION = [];
// Kill session
session_destroy();
die;
?>

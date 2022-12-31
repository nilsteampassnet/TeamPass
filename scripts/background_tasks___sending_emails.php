<?php
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
 * @file      background_tasks___sending_emails.php
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

require_once __DIR__.'/../sources/SecureHandler.php';
session_name('teampass_session');
session_start();
$_SESSION['CPM'] = 1;

// Load config
require_once __DIR__.'/../includes/config/tp.config.php';

// increase the maximum amount of time a script is allowed to run
set_time_limit($SETTINGS['task_maximum_run_time']);

// Do checks
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.queries.php';

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;
DB::$ssl = DB_SSL;
DB::$connect_options = DB_CONNECT_OPTIONS;


// Manage emails to send in queue.
// Only manage 10 emails at time

$rows = DB::query(
    'SELECT *
    FROM ' . prefixTable('processes') . '
    WHERE is_in_progress = %i AND process_type = %s
    ORDER BY increment_id ASC LIMIT 0,10',
    0,
    'send_email'
);
foreach ($rows as $record) {
    // get email properties
    $email = json_decode($record['arguments'], true);

    // send email
    sendEmail(
        $email['subject'],
        $email['body'],
        $email['receivers'],
        $SETTINGS,
        null,
        true,
        true
    );

    // update DB
    DB::update(
        prefixTable('processes'),
        array(
            'updated_at' => time(),
            'finished_at' => time(),
            'is_in_progress' => -1,
        ),
        'increment_id = %i',
        $record['increment_id']
    );
}

// Now send statitics
if (isset($SETTINGS['send_stats']) === true && (int) $SETTINGS['send_stats'] === 1) {
    require_once $SETTINGS['cpassman_dir'].'/sources/main.queries.php';
    sendingStatistics($SETTINGS);
}

// Now send waiting emails - TODO - remove this in the future
sendEmailsNotSent(
    $SETTINGS
);
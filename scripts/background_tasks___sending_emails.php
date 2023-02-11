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
 * @copyright 2009-2023 Teampass.net
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
require_once __DIR__.'/background_tasks___functions.php';

// increase the maximum amount of time a script is allowed to run
set_time_limit($SETTINGS['task_maximum_run_time']);

// Do checks
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

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

// log start
$logID = doLog('start', 'sending_email', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0));

// Manage emails to send in queue.
// Only manage 10 emails at time
DB::debugmode(false);
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

// log end
doLog('end', '', (isset($SETTINGS['enable_tasks_log']) === true ? (int) $SETTINGS['enable_tasks_log'] : 0), $logID);


function sendEmailsNotSent(
    array $SETTINGS
)
{
    if ((int) $SETTINGS['enable_send_email_on_user_login'] === 1) {
        $row = DB::queryFirstRow(
            'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s',
            'cron',
            'sending_emails'
        );

        if ((int) (time() - $row['valeur']) >= 300 || (int) $row['valeur'] === 0) {
            $rows = DB::query(
                'SELECT *
                FROM ' . prefixTable('emails') .
                ' WHERE status != %s',
                'sent'
            );
            foreach ($rows as $record) {
                // Send email
                json_decode(
                    sendEmail(
                        $record['subject'],
                        $record['body'],
                        $record['receivers'],
                        $SETTINGS,
                        null,
                        true,
                        true
                    ),
                    true
                );

                // update item_id in files table
                DB::update(
                    prefixTable('emails'),
                    array(
                        'status' => 'sent',
                    ),
                    'increment_id = %i',
                    $record['increment_id']
                );
            }
        }
        // update cron time
        DB::update(
            prefixTable('misc'),
            array(
                'valeur' => time(),
            ),
            'intitule = %s AND type = %s',
            'sending_emails',
            'cron'
        );
    }
}
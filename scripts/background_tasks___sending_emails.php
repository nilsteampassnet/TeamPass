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

Use voku\helper\AntiXSS;
Use TeampassClasses\NestedTree\NestedTree;
Use TeampassClasses\SuperGlobal\SuperGlobal;
Use EZimuel\PHPSecureSession;
Use TeampassClasses\PerformChecks\PerformChecks;


// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
session_name('teampass_session');
session_start();

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);
// increase the maximum amount of time a script is allowed to run
set_time_limit($SETTINGS['task_maximum_run_time']);

// --------------------------------- //

require_once __DIR__.'/background_tasks___functions.php';

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

    // update DB - started_at
    DB::update(
        prefixTable('processes'),
        array(
            'started_at' => time(),
        ),
        'increment_id = %i',
        $record['increment_id']
    );

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
    //if ((int) $SETTINGS['enable_backlog_mail'] === 1) {
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
    //}
}
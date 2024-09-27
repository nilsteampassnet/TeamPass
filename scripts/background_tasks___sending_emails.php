<?php
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
 * @file      background_tasks___sending_emails.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\EmailService\EmailService;
use TeampassClasses\EmailService\EmailSettings;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

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
$emailSettings = new EmailSettings($SETTINGS);
$emailService = new EmailService();
$rows = DB::query(
    'SELECT *
    FROM ' . prefixTable('background_tasks') . '
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
        prefixTable('background_tasks'),
        array(
            'started_at' => time(),
        ),
        'increment_id = %i',
        $record['increment_id']
    );
    
    // if email.encryptedUserPassword is set, decrypt it
    if (isset($email['encryptedUserPassword']) === true) {
        $userPassword = cryption($email['encryptedUserPassword'], '', 'decrypt', $SETTINGS)['string'];
        $email['body'] = str_replace('#password#', $userPassword, $email['body']);
    }
    
    // send email
    $emailService->sendMail(
        $email['subject'],
        $email['body'],
        $email['receivers'],
        $emailSettings,
        null,
        true,
        true
    );

    // Clear body content and encryptedUserPassword
    $email['body'] = '<cleared>';
    $email['encryptedUserPassword'] = '<cleared>';

    // update DB
    DB::update(
        prefixTable('background_tasks'),
        array(
            'updated_at' => time(),
            'finished_at' => time(),
            'is_in_progress' => -1,
            'arguments' => json_encode($email),
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
    $emailSettings = new EmailSettings($SETTINGS);
    $emailService = new EmailService();
    
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
                    $emailService->sendMail(
                        $record['subject'],
                        $record['body'],
                        $record['receivers'],
                        $emailSettings,
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
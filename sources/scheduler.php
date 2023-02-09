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
 * @version   3.0.0.22
 * @file      scheduler.php
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

use GO\Scheduler;

require_once __DIR__.'/../sources/SecureHandler.php';
session_name('teampass_session');
session_start();

// Load config
require_once __DIR__.'/../includes/config/tp.config.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';

// Load library
require_once $SETTINGS['cpassman_dir'] . '/sources/SplClassLoader.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

// Create a new scheduler
$assert = new SplClassLoader('Webmozart\Assert', $SETTINGS['cpassman_dir'] . '/includes/libraries');
$assert->register();
$cron = new SplClassLoader('Cron', $SETTINGS['cpassman_dir'] . '/includes/libraries');
$cron->register();
$scheduler = new SplClassLoader('GO', $SETTINGS['cpassman_dir'] . '/includes/libraries');
$scheduler->register();
$scheduler = new scheduler();


// Build the scheduler jobs
// https://github.com/peppeocchi/php-cron-scheduler
$scheduler->php($SETTINGS['cpassman_dir'] . '/scripts/background_tasks___user_keys_creation.php')->everyMinute($SETTINGS['user_keys_job_frequency'] ?? '1');
$scheduler->php($SETTINGS['cpassman_dir'] . '/scripts/background_tasks___sending_emails.php')->everyMinute($SETTINGS['sending_emails_job_frequency'] ?? '2');
$scheduler->php($SETTINGS['cpassman_dir'] . '/scripts/background_tasks___do_calculation.php')->everyMinute($SETTINGS['items_statistics_job_frequency'] ?? '5');

// Let the scheduler execute jobs which are due.
$scheduler->run();
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
 * @file      background_tasks___functions.php
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

// Load config
require_once __DIR__.'/../includes/config/tp.config.php';
require_once __DIR__.'/../includes/config/include.php';
require_once __DIR__.'/../includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');


/**
 * Permits to log task status
 *
 * @param string $status
 * @param string $job
 * @param integer $enable_tasks_log
 * @param integer|null $id
 * @return integer
 */
function doLog(string $status, string $job, int $enable_tasks_log = 0, int $id = null, int $treated_objects = null): int
{
    // is log enabled?
    if ((int) $enable_tasks_log === 1) {
        // is log start?
        if (is_null($id) === true) {
            DB::insert(
                prefixTable('processes_logs'),
                array(
                    'created_at' => time(),
                    'job' => $job,
                    'status' => $status,
                )
            );
            return DB::insertId();
        }

        // Read start_time
        /*$start_time = DB::queryFirstField(
            'SELECT created_at FROM '.prefixTable('processes_logs').' WHERE increment_id = %i',
            $id
        );*/
        
        // Case is an update
        DB::update(
            prefixTable('processes_logs'),
            array(
                'status' => $status,
                'finished_at' => time(),
                'treated_objects' => $treated_objects,
            ),
            'increment_id = %i',
            $id
        );
    }
    
    return -1;
}

/**
 * Permits to run a task
 *
 * @param array $message
 * @param string $SETTINGS
 * @return void
 */
function provideLog(string $message, array $SETTINGS)
{
    echo '\n' . (string) date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], time()) . ' - '.$message . '\n';
}
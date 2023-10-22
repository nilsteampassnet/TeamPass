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
 * @file      task_maintenance_purge_old_files.php
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
$logID = doLog('start', 'do_maintenance - purge-old-files', 1);

// Perform maintenance tasks
purgeTemporaryFiles();

// log end
doLog('end', '', 1, $logID);
/**
 * Purge old files in FILES and UPLOAD folders.
 *
 * @return void
 */
function purgeTemporaryFiles(): void
{
    // Load expected files
    require_once __DIR__. '/../sources/main.functions.php';
    include __DIR__. '/../includes/config/tp.config.php';

    if (isset($SETTINGS) === true) {
        //read folder
        if (is_dir($SETTINGS['path_to_files_folder']) === true) {
            $dir = opendir($SETTINGS['path_to_files_folder']);
            if ($dir !== false) {
                //delete file FILES
                while (false !== ($f = readdir($dir))) {
                    if ($f !== '.' && $f !== '..' && $f !== '.htaccess') {
                        if (file_exists($dir . $f) && ((time() - filectime($dir . $f)) > 604800)) {
                            fileDelete($dir . '/' . $f, $SETTINGS);
                        }
                    }
                }

                //Close dir
                closedir($dir);
            }
        }

        //read folder  UPLOAD
        if (is_dir($SETTINGS['path_to_upload_folder']) === true) {
            $dir = opendir($SETTINGS['path_to_upload_folder']);

            if ($dir !== false) {
                //delete file
                while (false !== ($f = readdir($dir))) {
                    if ($f !== '.' && $f !== '..') {
                        if (strpos($f, '_delete.') > 0) {
                            fileDelete($SETTINGS['path_to_upload_folder'] . '/' . $f, $SETTINGS);
                        }
                    }
                }
                //Close dir
                closedir($dir);
            }
        }
    }
    
}
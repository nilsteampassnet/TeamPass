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

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;


// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$lang = new Language();

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
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
 * @file      task_maintenance_purge_old_files.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;


// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$lang = new Language('english');

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

    // Verify if $SETTINGS is defined
    if (isset($SETTINGS) === false) {
        throw new \RuntimeException('Settings are not defined.');
    }

    // $SETTINGS is set then read folder
    if (is_dir($SETTINGS['path_to_files_folder']) === true) {
        $dir = opendir($SETTINGS['path_to_files_folder']);
        if ($dir !== false) {
            //delete file FILES
            while (false !== ($f = readdir($dir))) {
                if ($f !== '.' && $f !== '..' && $f !== '.htaccess') {
                    $filePath = $SETTINGS['path_to_files_folder'] . '/' . $f;
                    if (file_exists($filePath) && ((time() - filectime($filePath)) > 604800)) {
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
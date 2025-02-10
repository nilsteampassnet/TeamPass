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
 * @file      run.step1.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require '../../vendor/autoload.php';
use TeampassClasses\SuperGlobal\SuperGlobal;

// Get some data
include __DIR__.'/../../includes/config/include.php';
// Load functions
include_once(__DIR__ . '/../tp.functions.php');

$superGlobal = new SuperGlobal();

// Initialize variables
$keys = [
    'absolutePath',
    'urlPath',
    'securePath',
];

// Initialize arrays
$inputData = [];
$filters = [];

// Loop to retrieve POST variables and build arrays
foreach ($keys as $key) {
    $inputData[$key] = $superGlobal->get($key, 'POST') ?? '';
    $filters[$key] = 'trim|escape';
}
$inputData = dataSanitizerForInstall(
    $inputData,
    $filters
);

header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Perform checks
$settingsFileStatus = checks($inputData);

// Prepare the response
if ($settingsFileStatus['success'] === true) {
    $response = [
        'success' => true,
        'message' => '<i class="fa-solid fa-check"></i> Done',
    ];
} else {
    $response = [
        'success' => false,
        'message' => $settingsFileStatus['message'],
    ];
}

// Send the response
echo json_encode($response);


/**
 * Checks the data
 * 
 * @param array $inputData
 * 
 * @return array
 */
function checks($inputData)
{
    // Is absolute path a folder?
    if (!is_dir($inputData['absolutePath'])) {
        return [
            'success' => false,
            'message' => 'Path ' . $inputData['absolutePath'] . ' is not a folder!',
        ];
    }

    // Is secure path a folder?
    if (!is_dir($inputData['securePath'])) {
        return [
            'success' => false,
            'message' => 'Path ' . $inputData['securePath'] . ' is not a folder!',
        ];
    }

    // Is secure path writable?
    if (is_writable($inputData['securePath']) === false) {
        return [
            'success' => false,
            'message' => 'Path ' . $inputData['securePath'] . ' is not writable!',
        ];
    }    

    return [
        'success' => true,
    ];
}
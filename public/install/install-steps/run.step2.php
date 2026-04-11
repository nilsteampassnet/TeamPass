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
 * @file      run.step2.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require '../../vendor/autoload.php';
use TeampassClasses\SuperGlobal\SuperGlobal;

// Get some data
include __DIR__.'/../../includes/config/include.php';
// Load functions
include_once(__DIR__ . '/../tp.functions.php');
require_once __DIR__.'/install.functions.php';

$superGlobal = new SuperGlobal();

// Initialize variables
$keys = [
    'type',
    'path',
    'name',
    'version',
    'limit',
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

header('Content-type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Perform checks
$response = ['success' => false];

// Check the type of operation
$type = $inputData['type'] ?? '';

switch ($type) {
    case 'directory':
        $path = $inputData['path'] ?? '';
        if ($path && is_writable($path)) {
            $response['success'] = true;
        }
        break;

    case 'extension':
        $extension = $inputData['name'] ?? '';
        if ($extension) {
            // pcntl and posix are CLI-only extensions, not available in web SAPI
            // Use shell check to verify they are installed for CLI (only when exec() is available)
            if (in_array($extension, ['pcntl', 'posix'], true)) {
                if (function_exists('exec')) {
                    $output = [];
                    $code = -1;
                    exec('php -m 2>/dev/null', $output, $code);
                    if ($code === 0 && in_array($extension, $output, true)) {
                        $response['success'] = true;
                    }
                }
                // If exec() is disabled, we cannot check CLI extensions — report as not found
            } elseif (extension_loaded($extension)) {
                $response['success'] = true;
            }
        }
        break;

    case 'php_version':
        if (version_compare(phpversion(), MIN_PHP_VERSION, '>=')) {
            $response['success'] = true;
        }
        break;

    case 'execution_time':
        $limit = (int) ($inputData['limit'] ?? 0);
        if ($limit > 0 && ini_get('max_execution_time') >= $limit) {
            $response['success'] = true;
        }
        break;

    case 'opcache':
        // Check that the Zend OPcache extension is present and enabled for the web SAPI
        if (extension_loaded('Zend OPcache') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN)) {
            $response['success'] = true;
        }
        break;

    case 'php_fpm':
        // Check that PHP is running under PHP-FPM (fpm-fcgi SAPI)
        if (PHP_SAPI === 'fpm-fcgi') {
            $response['success'] = true;
        }
        break;

    case 'apcu':
        // Check that APCu extension is present and enabled for the web SAPI
        if (extension_loaded('apcu') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            $response['success'] = true;
        }
        break;

    case 'redis':
        // Check that ext-redis is loaded (required for Redis session storage)
        if (extension_loaded('redis')) {
            $response['success'] = true;
        }
        break;

    default:
        $response['error'] = 'Invalid type';
        break;
}

// Send the response
echo json_encode($response);
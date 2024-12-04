<?php

require '../../vendor/autoload.php';
use TeampassClasses\SuperGlobal\SuperGlobal;

// Get some data
include __DIR__.'/../../includes/config/include.php';
// Load functions
include_once(__DIR__ . '/../tp.functions.php');

$superGlobal = new SuperGlobal();

// Initialize variables
$keys = [
    'type',
    'path',
    'name',
    'version',
    'limit',
];

// Initialiser les tableaux
$inputData = [];
$filters = [];

// Boucle pour récupérer les variables POST et constituer les tableaux
foreach ($keys as $key) {
    $inputData[$key] = $superGlobal->get($key, 'POST') ?? '';
    $filters[$key] = 'trim|escape';
}
$inputData = dataSanitizer(
    $inputData,
    $filters
);

header('Content-type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Perform checks
$response = ['success' => false];

// Vérifier le type d'opération
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
        if ($extension && extension_loaded($extension)) {
            $response['success'] = true;
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

    default:
        $response['error'] = 'Invalid type';
        break;
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

    /*
    // Handle the SK file to correct folder
    $secureFile = $inputData['securePathField'] . '/' . $inputData['secureFile'];
    $secureFileInConfigFolder = $inputData['securePath'].'/'.$inputData['secureFile'];

    if (!file_exists($secureFile)) {
        // Move file
        if (!copy($secureFileInConfigFolder, $secureFile)) {
            return [
                'success' => false,
                'message' => 'File ' . $secureFileInConfigFolder . ' could not be copied to `'.$secureFile.'`. Please check the path and the rights',
            ];
        }
    }

    if (file_exists($secureFileInConfigFolder)) {
        unlink($secureFileInConfigFolder);
    }
    */

    return [
        'success' => true,
    ];
}
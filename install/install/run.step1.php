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
    'absolutePath',
    'urlPath',
    'securePath',
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
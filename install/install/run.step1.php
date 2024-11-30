<?php

require '../../vendor/autoload.php';
use TeampassClasses\SuperGlobal\SuperGlobal;

// Get some data
include __DIR__.'/../../includes/config/include.php';
// Load functions
require_once __DIR__.'/../../sources/main.functions.php';

$superGlobal = new SuperGlobal();

// Initialize variables
$inputData = [
    'absolutePath' => $superGlobal->get('absolutePath', 'POST') ?? '',
    'urlPath' => $superGlobal->get('urlPath', 'POST') ?? '',
    'securePathField' => $superGlobal->get('securePathField', 'POST') ?? '',
    'randomInstalldKey' => $superGlobal->get('randomInstalldKey', 'POST') ?? '',
    'settingsPath' => rtrim($superGlobal->get('settingsPath', 'POST'), '/') ?? '',
    'secureFile' => $superGlobal->get('secureFile', 'POST') ?? '',
    'securePath' => $superGlobal->get('securePath', 'POST') ?? '',
];
$filters = [
    'absolutePath' => 'trim|escape',
    'urlPath' => 'trim|escape',
    'securePathField' => 'trim|escape',
    'randomInstalldKey' => 'trim|escape',
    'settingsPath' => 'trim|escape',
    'secureFile' => 'trim|escape',
    'securePath' => 'trim|escape',
];
$inputData = dataSanitizer(
    $inputData,
    $filters
);

header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$settingsFileStatus = checks($inputData);

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

echo json_encode($response);

function checks($inputData)
{
    //error_log(print_r($inputData, true));
    // Is SK path a folder?
    if (!is_dir($inputData['absolutePath'])) {
        return [
            'success' => false,
            'message' => 'Path ' . $inputData['absolutePath'] . ' is not a folder!',
        ];
    }

    // Is SK path a folder?
    if (!is_dir($inputData['securePathField'])) {
        return [
            'success' => false,
            'message' => 'Path ' . $inputData['securePathField'] . ' is not a folder!',
        ];
    }

    // Is SK path writable?
    if (is_writable($inputData['securePathField']) === false) {
        return [
            'success' => false,
            'message' => 'Path ' . $inputData['securePathField'] . ' is not writable!',
        ];
    }         

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

    return [
        'success' => true,
        'data' => [
            'status' => 'ok',
        ],
    ];
}
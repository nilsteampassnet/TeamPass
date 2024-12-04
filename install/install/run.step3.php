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
    'teampassAbsolutePath',
    'teampassUrl',
    'teampassSecurePath',
    'dbHost',
    'dbName',
    'dbLogin',
    'dbPw',
    'dbPort',
    'tablePrefix',
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
$databaseStatus = checks($inputData);

// Prepare the response
if ($databaseStatus['success'] === true) {
    $response = [
        'success' => true,
        'message' => '<i class="fa-solid fa-check"></i> Done',
    ];
} else {
    $response = [
        'success' => false,
        'message' => '<i class="fa-regular fa-circle-xmark text-alert"></i> '.$databaseStatus['message'],
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
    // Initialize database connection
    DB::$host = $inputData['dbHost'];
    DB::$user = $inputData['dbLogin'];
    DB::$password = $inputData['dbPw'];
    DB::$dbName = $inputData['dbName'];
    DB::$port = $inputData['dbPort'];
    DB::$encoding = 'utf8';
    DB::$ssl = array(
        "key" => "",
        "cert" => "",
        "ca_cert" => "",
        "ca_path" => "",
        "cipher" => ""
    );
    DB::$connect_options = array(
        MYSQLI_OPT_CONNECT_TIMEOUT => 10
    );

    try {
        // Force connecting to this database
        DB::disconnect();
        DB::useDB($inputData['dbName']);
        
        // Create install table
        DB::query(
            'CREATE TABLE IF NOT EXISTS `_install` (
            `key` varchar(100) NOT NULL,
            `value` varchar(500) NOT NULL,
            PRIMARY KEY (`key`)
            )
        ');

        DB::insertUpdate('_install', [
            'key' => 'teampassAbsolutePath',
            'value' => $inputData['teampassAbsolutePath'],
        ]);

        DB::insertUpdate('_install', [
            'key' => 'teampassUrl',
            'value' => $inputData['teampassUrl'],
        ]);

        DB::insertUpdate('_install', [
            'key' => 'teampassSecurePath',
            'value' => $inputData['teampassSecurePath'],
        ]);

        DB::insertUpdate('_install', [
            'key' => 'tablePrefix',
            'value' => $inputData['tablePrefix'],
        ]);


        return [
            'success' => true,
            'message' => 'Database connection successful',
        ];

    } catch (Exception $e) {
        // Si la connexion échoue, afficher un message d'erreur
        return [
            'success' => false,
            'message' => 'Database connection failed with error: ' . $e->getMessage(),
        ];
    }
}
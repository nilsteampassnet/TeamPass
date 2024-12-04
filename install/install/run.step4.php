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
    'adminPassword',
    'adminEmail',
    'adminName',
    'adminLastname',
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
    // Is password strong enough?
    if (!isPasswordStrongEnough($inputData)) {
        return [
            'success' => false,
            'message' => 'The new password must:<br/> - Be different from the previous one<br/> - Contain at least 10 characters<br/> - Contain at least one uppercase letter and one lowercase letter<br/> - Contain at least one number or special character<br/> - Not contain your name, first name, username, or email.',
        ];
    }
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
            'key' => 'adminPassword',
            'value' => $inputData['adminPassword'],
        ]);

        DB::insertUpdate('_install', [
            'key' => 'adminEmail',
            'value' => $inputData['adminEmail'],
        ]);

        DB::insertUpdate('_install', [
            'key' => 'adminName',
            'value' => $inputData['adminName'],
        ]);

        DB::insertUpdate('_install', [
            'key' => 'adminLastname',
            'value' => $inputData['adminLastname'],
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


function isPasswordStrongEnough($inputData) {

    // Password can't contain login, name or lastname
    $forbiddenWords = [
        'admin',
        $inputData['adminName'],
        $inputData['adminLastname'],
    ];

    // Cut out the email
    if ($email = $inputData['adminEmail']) {
        $emailParts = explode('@', $email);

        if (count($emailParts) === 2) {
            // Mail username (removed @domain.tld)
            $forbiddenWords[] = $emailParts[0];

            // Organisation name (removed username@ and .tld)
            $domain = explode('.', $emailParts[1]);
            if (count($domain) > 1)
                $forbiddenWords[] = $domain[0];
        }
    }

    // Search forbidden words in password
    foreach ($forbiddenWords as $word) {
        if (empty($word))
            continue;

        // Stop if forbidden word found in password
        if (stripos($inputData['adminPassword'], $word) !== false)
            return false;
    }

    // Get password complexity
    $length = strlen($inputData['adminPassword']);
    $hasUppercase = preg_match('/[A-Z]/', $inputData['adminPassword']);
    $hasLowercase = preg_match('/[a-z]/', $inputData['adminPassword']);
    $hasNumber = preg_match('/[0-9]/', $inputData['adminPassword']);
    $hasSpecialChar = preg_match('/[\W_]/', $inputData['adminPassword']);
    
    return $length >= 8
           && $hasUppercase
           && $hasLowercase
           && ($hasNumber || $hasSpecialChar);
}
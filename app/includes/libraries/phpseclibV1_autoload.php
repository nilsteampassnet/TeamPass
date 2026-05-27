<?php
/**
 * Autoloader for phpseclib v1
 * Provides automatic class loading for v1 classes (Crypt_*, File_*, Math_*, Net_*, System_*)
 */

spl_autoload_register(function ($class) {
    // Only handle phpseclib v1 classes (underscore naming convention)
    if (strpos($class, '_') === false) {
        return;
    }

    // Check if it's a phpseclib v1 class
    $prefixes = ['Crypt_', 'File_', 'Math_', 'Net_', 'System_'];
    $isPhpseclibClass = false;

    foreach ($prefixes as $prefix) {
        if (strpos($class, $prefix) === 0) {
            $isPhpseclibClass = true;
            break;
        }
    }

    if (!$isPhpseclibClass) {
        return;
    }

    // Convert class name to file path
    // Crypt_AES -> Crypt/AES.php
    // System_SSH_Agent -> System/SSH/Agent.php
    $file = str_replace('_', '/', $class) . '.php';

    // Try to load from phpseclibV1 directory
    // __DIR__ = includes/libraries/, so phpseclibV1 is in the same directory
    $phpseclibV1Path = __DIR__ . '/phpseclibV1/' . $file;

    if (file_exists($phpseclibV1Path)) {
        require_once $phpseclibV1Path;
    }
});

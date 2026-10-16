<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      backup_restore_permissions.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

require_once __DIR__ . '/../../app/vendor/autoload.php';
require_once __DIR__ . '/../../app/sources/backup.functions.php';

$root = $argv[1];
$scenario = $argv[2];
umask(octdec($argv[3]));
$plaintext = 'SQL restore fixture content';
file_put_contents($root . '/source.sql', $plaintext);
\Defuse\Crypto\File::encryptFileWithPassword($root . '/source.sql', $root . '/backup.sql', 'fixture-password');
$decryptionModes = [];

/** Observe destination protection before Defuse writes any decrypted bytes. */
function prepareFileWithDefuse(string $mode, string $source, string $destination, string $password): bool
{
    global $decryptionModes;
    clearstatcache(true, $destination);
    $decryptionModes[] = is_file($destination) ? (fileperms($destination) & 0777) : null;
    \Defuse\Crypto\File::decryptFileWithPassword($source, $destination, $password);
    return true;
}

$destination = $root . '/restored.sql';
if ($scenario === 'blocked') {
    mkdir($destination, 0700);
}
if ($scenario === 'zip' || $scenario === 'zip_existing' || $scenario === 'zip_document') {
    if ($scenario === 'zip_existing') {
        file_put_contents($destination, 'old contents longer than the restored SQL fixture');
    }
    $zip = new ZipArchive();
    $zip->open($root . '/fixture.zip', ZipArchive::CREATE);
    $zip->addFromString('database/dump.sql', $plaintext);
    $zip->close();
    $zip->open($root . '/fixture.zip');
    // Documents (attachments, avatars) keep group read; SQL dumps stay owner-only.
    $result = tpBackupExtractZipEntryToFile($zip, 'database/dump.sql', $destination, $scenario !== 'zip_document');
    $zip->close();
} else {
    $result = tpDefuseDecryptWithCandidates($root . '/backup.sql', $destination,
        $scenario === 'all_wrong' ? ['wrong-one', 'wrong-two'] : ['wrong-one', 'fixture-password']);
}
clearstatcache(true, $destination);
echo json_encode([
    'success' => $result['success'],
    'message' => $result['message'],
    'decryption_modes' => $decryptionModes,
    'mode' => is_file($destination) ? (fileperms($destination) & 0777) : null,
    'contents' => is_file($destination) ? file_get_contents($destination) : null,
    'exists' => file_exists($destination),
    'is_directory' => is_dir($destination),
], JSON_THROW_ON_ERROR);

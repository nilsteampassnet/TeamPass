<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

/** Copy the authorized scheduled archive without decrypting or recapturing its database. */
function tpBackupCopyScheduledArchive(string $directory, string $filename, string $target, string $expectedHash, array $settings): array
{
    $resolved = tpBackupResolveScheduledOutputDir($directory, $settings);
    if (!$resolved['success'] || basename($filename) !== $filename || !str_starts_with($filename, 'scheduled-')
        || !tpBackupFileExtensionIsSupported(pathinfo($filename, PATHINFO_EXTENSION))
        || preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1 || !is_dir($target) || !is_writable($target)) {
        throw new RuntimeException('Invalid scheduled backup copy source or destination.');
    }
    $source = tpBackupResolveFileInBase($resolved['path'], $filename);
    if ($source === '' || !hash_equals($expectedHash, tpBackupFileSha256($source))) {
        throw new RuntimeException('The scheduled backup changed or is no longer available.');
    }
    $metadata = tpReadBackupMetadata($source);
    if ($metadata === []) {
        throw new RuntimeException('The scheduled backup metadata is unavailable.');
    }
    $name = 'externalized-' . substr($filename, strlen('scheduled-'));
    $destination = rtrim($target, '/\\') . DIRECTORY_SEPARATOR . $name;
    $work = '';
    try {
        if (file_exists($destination) || is_link($destination)) {
            if (is_link($destination) || !is_file($destination) || !hash_equals($expectedHash, tpBackupFileSha256($destination))) {
                throw new RuntimeException('An externalized archive with different contents already exists.');
            }
        } else {
            $work = tpBackupCreateTemporaryDirectory($target);
            if ($work === '') {
                throw new RuntimeException('Could not stage the scheduled backup copy.');
            }
            $temporary = $work . '/archive.encrypted';
            $output = tpBackupOpenProtectedFile($temporary);
            $input = fopen($source, 'rb');
            if (!is_resource($output) || !is_resource($input)) {
                if (is_resource($output)) {
                    fclose($output);
                }
                if (is_resource($input)) {
                    fclose($input);
                }
                throw new RuntimeException('Could not open the scheduled backup copy streams.');
            }
            try {
                $size = filesize($source);
                if ($size === false || stream_copy_to_stream($input, $output) !== $size || !fflush($output)) {
                    throw new RuntimeException('The scheduled backup copy is incomplete.');
                }
            } finally {
                fclose($input);
                fclose($output);
            }
            if (!hash_equals($expectedHash, tpBackupFileSha256($temporary))) {
                throw new RuntimeException('The scheduled backup changed while being copied.');
            }
            if (!rename($temporary, $destination)) {
                throw new RuntimeException('Could not publish the scheduled backup copy.');
            }
        }
        $metadata['source'] = 'externalized';
        $metadata['copied_from'] = $filename;
        $metadata['archive_sha256'] = $expectedHash;
        if (!tpBackupWriteProtectedFile(tpGetBackupMetadataPath($destination), json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))) {
            throw new RuntimeException('The archive was copied, but its metadata could not be written.');
        }
        return ['success' => true, 'filename' => $name, 'filepath' => $destination, 'encrypted' => true,
            'size_bytes' => (int) filesize($destination), 'message' => ''];
    } finally {
        if ($work !== '') {
            tpBackupRemoveTemporaryDirectory($work);
        }
    }
}

<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_publication.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

/** Resolve a destination directory without traversing links, creating empty directories when requested. */
function tpRecoveryDestinationDirectory(string $root, string $relative, bool $create = false): string
{
    $directory = rtrim($root, '/\\');
    foreach (explode('/', $relative) as $part) {
        if ($part === '' || $part === '.' || $part === '..' || str_contains($part, '\\')) {
            throw new TeamPassRecoveryException('RECOVERY_PATH_INVALID');
        }
        $directory .= '/' . $part;
        clearstatcache(true, $directory);
        if (is_link($directory) || (file_exists($directory) && !is_dir($directory))) {
            throw new TeamPassRecoveryException('RECOVERY_PATH_INVALID');
        }
        if (!is_dir($directory) && (!$create || !@mkdir($directory, 0700))) {
            throw new TeamPassRecoveryException('RECOVERY_DIRECTORY_UNWRITABLE');
        }
    }
    return $directory;
}

/** Follow only the two exact persistent configuration links created by the official Docker entrypoint. */
function tpRecoveryConfigDestination(string $root, string $name): string
{
    $directories = ['settings.php' => 'app/config', 'csrfp.config.php' => 'app/includes/libraries/csrfp/libs'];
    if (!isset($directories[$name])) {
        throw new TeamPassRecoveryException('RECOVERY_PATH_INVALID');
    }
    $path = tpRecoveryDestinationDirectory($root, $directories[$name]) . '/' . $name;
    if (is_link($path)) {
        $persistent = rtrim($root, '/\\') . '/storage/config/' . $name;
        if (str_replace('\\', '/', (string) readlink($path)) !== str_replace('\\', '/', $persistent)) {
            throw new TeamPassRecoveryException('RECOVERY_PATH_INVALID');
        }
        tpRecoveryDestinationDirectory($root, 'storage/config');
        $path = $persistent;
    }
    if (is_link($path) || (file_exists($path) && !is_file($path))) {
        throw new TeamPassRecoveryException('RECOVERY_PATH_INVALID');
    }
    return $path;
}

/** Check publication access as the current PHP account before importing any database data. */
function tpRecoveryPublicationPreflight(string $root): void
{
    $directories = [];
    foreach (['settings.php', 'csrfp.config.php'] as $name) {
        $directories[] = dirname(tpRecoveryConfigDestination($root, $name));
    }
    foreach (['secrets', 'storage/upload', 'storage/files', 'storage/files/kb_attachments', 'public/assets/avatars'] as $relative) {
        $directories[] = tpRecoveryDestinationDirectory($root, $relative, true);
    }
    foreach (array_unique($directories) as $directory) {
        // PHP checks the effective process identity and its filesystem ACLs.
        // Do not leave probe files that could strand a later attempt on SIGKILL.
        if (!is_writable($directory)) {
            throw new TeamPassRecoveryException('RECOVERY_DESTINATION_UNWRITABLE');
        }
    }
}

/** Derive a publication temporary name from the persisted job and its exact destination. */
function tpRecoveryPublicationTemporary(string $path, string $id): string
{
    if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) {
        throw new TeamPassRecoveryException('RECOVERY_JOB_INVALID');
    }
    return dirname($path) . '/.recovery-' . $id . '-' . substr(hash('sha256', basename($path)), 0, 16);
}

/** Remove only one temporary belonging to a persisted publication target, never a link or directory. */
function tpRecoveryClearPublicationTemporary(string $path): void
{
    clearstatcache(true, $path);
    if (is_link($path) || (file_exists($path) && !is_file($path))) {
        throw new TeamPassRecoveryException('RECOVERY_PATH_INVALID');
    }
    if (is_file($path) && !unlink($path)) {
        throw new TeamPassRecoveryException('RECOVERY_WRITE_FAILED');
    }
}

/** Publish one staged file, accepting only an identical result or the recorded initial configuration. */
function tpRecoveryPublishFile(string $path, string $contents, ?string $previousHash = null, ?string $id = null): void
{
    $temporary = $id === null ? null : tpRecoveryPublicationTemporary($path, $id);
    if ($temporary !== null) { tpRecoveryClearPublicationTemporary($temporary); }
    clearstatcache(true, $path);
    if (is_link($path) || (file_exists($path) && !is_file($path))) {
        throw new TeamPassRecoveryException('RECOVERY_PATH_INVALID');
    }
    if (is_file($path)) {
        $actual = (string) hash_file('sha256', $path);
        if (hash_equals(hash('sha256', $contents), $actual)) {
            return;
        }
        if ($previousHash === null || !hash_equals($previousHash, $actual)) {
            throw new TeamPassRecoveryException('RECOVERY_PUBLICATION_CONFLICT');
        }
        tpRecoveryWritePrivate($path, $contents, false, $temporary);
        return;
    }
    tpRecoveryWritePrivate($path, $contents, true, $temporary);
}

/** Publish archive documents using retryable temporary names recorded by the job and manifest. */
function tpRecoveryPublishDocuments(string $zipPath, array $job, array $settings): void
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) { throw new TeamPassRecoveryException('DOCUMENT_PUBLICATION_FAILED'); }
    try {
        foreach ($job['manifest']['document_entries'] ?? [] as $entry) {
            $target = tpBackupResolveDocumentRestoreTarget($entry, $settings, false);
            if (empty($target['success'])) { throw new TeamPassRecoveryException('DOCUMENT_PUBLICATION_FAILED'); }
            $path = $target['target_path'];
            $temporary = tpRecoveryPublicationTemporary($path, $job['id']);
            tpRecoveryClearPublicationTemporary($temporary);
            if (is_link($path) || (file_exists($path) && !is_file($path))) {
                throw new TeamPassRecoveryException('RECOVERY_PATH_INVALID');
            }
            if (is_file($path)) {
                if (hash_equals($entry['sha256'], (string) hash_file('sha256', $path))) { continue; }
                throw new TeamPassRecoveryException('RECOVERY_PUBLICATION_CONFLICT');
            }
            try {
                $extracted = tpBackupExtractZipEntryToFile($zip, $target['package_path'], $temporary, false);
                if (empty($extracted['success']) || !hash_equals($entry['sha256'], (string) hash_file('sha256', $temporary))) {
                    throw new TeamPassRecoveryException('DOCUMENT_PUBLICATION_FAILED');
                }
                // An exclusive hard link refuses concurrent target creation; the
                // temporary is on the same filesystem as its final destination.
                if (!@link($temporary, $path)) { throw new TeamPassRecoveryException('DOCUMENT_PUBLICATION_FAILED'); }
            } finally {
                if (is_file($temporary) && !is_link($temporary) && !unlink($temporary)) {
                    throw new TeamPassRecoveryException('DOCUMENT_PUBLICATION_FAILED');
                }
            }
        }
    } finally { $zip->close(); }
}

/** Require every existing destination payload to belong to this interrupted publication and match its checksum. */
function tpRecoveryAssertPublicationData(string $root, array $job): void
{
    $allowed = ['secrets' => [$job['kit']['secure_name'] => $job['publication']['master_sha256']],
        'storage/upload' => [], 'storage/files/kb_attachments' => [], 'public/assets/avatars' => []];
    $kinds = ['item_attachment' => 'storage/upload', 'kb_attachment' => 'storage/files/kb_attachments',
        'user_avatar' => 'public/assets/avatars'];
    foreach ($job['manifest']['document_entries'] ?? [] as $entry) {
        if (!isset($kinds[$entry['kind'] ?? '']) || tpBackupSafeStorageBasename((string) ($entry['stored_name'] ?? '')) !== ($entry['stored_name'] ?? '')) {
            throw new TeamPassRecoveryException('DOCUMENT_MANIFEST_INVALID');
        }
        $allowed[$kinds[$entry['kind']]][$entry['stored_name']] = $entry['sha256'];
    }
    foreach ($allowed as $relative => $files) {
        $directory = tpRecoveryDestinationDirectory($root, $relative, true);
        $temporaries = [];
        if (isset($job['id'])) {
            foreach (array_keys($files) as $filename) {
                $temporaries[basename(tpRecoveryPublicationTemporary($directory . '/' . $filename, $job['id']))] = true;
            }
        }
        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $directory . '/' . $name;
            if (isset($temporaries[$name]) && !is_link($path) && is_file($path)) { continue; }
            if (in_array($name, ['.gitkeep', '.htaccess', 'empty_file.txt', 'index.html'], true)
                && !is_link($path) && is_file($path)) {
                continue;
            }
            if (!isset($files[$name]) || is_link($path) || !is_file($path)
                || !hash_equals($files[$name], (string) hash_file('sha256', $path))) {
                throw new TeamPassRecoveryException('RECOVERY_PUBLICATION_CONFLICT');
            }
        }
    }
}

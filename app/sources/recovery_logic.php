<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 *
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_logic.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

/** An actionable recovery failure. Messages must never contain archived secrets. */
final class TeamPassRecoveryException extends RuntimeException
{
    /** Create a failure with a stable, non-sensitive diagnostic identifier. */
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}

/** Validate the destination before writing it into the restored configuration. */
function tpRecoveryTargetUrl(mixed $input): string
{
    $url = is_string($input) ? rtrim(trim($input), '/') : '';
    $parts = parse_url($url);
    if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false
        || !is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        throw new TeamPassRecoveryException('TARGET_HTTPS_URL_REQUIRED');
    }
    return $url;
}

/** Reject unsupported versions, downgrades and unknown schema levels before import. */
function tpRecoveryPlanUpgrade(array $manifest, string $targetVersion, string $targetSchema): array
{
    $metadata = $manifest['teampass'] ?? null;
    $sourceVersion = is_array($metadata) && is_string($metadata['files_version'] ?? null) ? $metadata['files_version'] : '';
    $sourceSchema = is_array($metadata) && is_scalar($metadata['schema_level'] ?? null) ? (string) $metadata['schema_level'] : '';
    foreach ([$sourceVersion, $targetVersion] as $version) {
        if (preg_match('/^3\.\d+\.\d+\.\d+$/D', $version) !== 1) {
            throw new TeamPassRecoveryException('VERSION_UNKNOWN');
        }
    }
    if (version_compare($sourceVersion, '3.2.2.0', '<')) {
        throw new TeamPassRecoveryException('SOURCE_VERSION_UNSUPPORTED');
    }
    if (!ctype_digit($sourceSchema) || !ctype_digit($targetSchema)) {
        throw new TeamPassRecoveryException('SCHEMA_UNKNOWN');
    }
    if (version_compare($sourceVersion, $targetVersion, '>') || (int) $sourceSchema > (int) $targetSchema) {
        throw new TeamPassRecoveryException('DOWNGRADE_NOT_SUPPORTED');
    }
    return [
        'source_version' => $sourceVersion,
        'source_schema' => $sourceSchema,
        'target_version' => $targetVersion,
        'target_schema' => $targetSchema,
        'upgrade_required' => $sourceVersion !== $targetVersion || $sourceSchema !== $targetSchema,
    ];
}

/** Accept canonical, relative archive paths only; never silently repair an unsafe name. */
function tpRecoveryEntryPath(string $path): string
{
    if ($path === '' || strlen($path) > 1024 || preg_match('/[\x00-\x1f\\\\:]/', $path) === 1
        || str_starts_with($path, '/') || str_contains($path, '//')) {
        throw new TeamPassRecoveryException('ARCHIVE_PATH_INVALID');
    }
    foreach (explode('/', rtrim($path, '/')) as $part) {
        if ($part === '' || $part === '.' || $part === '..' || rtrim($part, ' .') !== $part) {
            throw new TeamPassRecoveryException('ARCHIVE_PATH_INVALID');
        }
    }
    return $path;
}

/** Bound archive expansion, reject duplicate entries and refuse symlinks/devices. */
function tpRecoveryInspectZip(ZipArchive $zip, int $maximumBytes = 53687091200): array
{
    if ($zip->numFiles < 1 || $zip->numFiles > 100000) {
        throw new TeamPassRecoveryException('ARCHIVE_ENTRY_LIMIT');
    }
    $entries = [];
    $caseNames = [];
    $total = 0;
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $stat = $zip->statIndex($index);
        if (!is_array($stat)) {
            throw new TeamPassRecoveryException('ARCHIVE_INVALID');
        }
        $name = tpRecoveryEntryPath((string) $stat['name']);
        $folded = strtolower(rtrim($name, '/'));
        if (isset($caseNames[$folded])) {
            throw new TeamPassRecoveryException('ARCHIVE_DUPLICATE_ENTRY');
        }
        $caseNames[$folded] = true;
        $opsys = 0;
        $attributes = 0;
        if ($zip->getExternalAttributesIndex($index, $opsys, $attributes) === false) {
            throw new TeamPassRecoveryException('ARCHIVE_INVALID');
        }
        $kind = ($attributes >> 16) & 0170000;
        if ($opsys === ZipArchive::OPSYS_UNIX && !in_array($kind, [0, 0100000, 0040000], true)) {
            throw new TeamPassRecoveryException('ARCHIVE_SPECIAL_ENTRY');
        }
        $size = (int) $stat['size'];
        if ($size < 0 || $size > $maximumBytes - $total) {
            throw new TeamPassRecoveryException('ARCHIVE_SIZE_LIMIT');
        }
        $total += $size;
        $entries[$name] = $size;
    }
    return ['entries' => $entries, 'expanded_bytes' => $total];
}

/** Read bounded JSON data without evaluating configuration PHP from an archive. */
function tpRecoveryReadZipJson(ZipArchive $zip, string $entry): array
{
    $stat = $zip->statName($entry);
    if (!is_array($stat) || (int) $stat['size'] > 2097152) {
        throw new TeamPassRecoveryException('MANIFEST_INVALID');
    }
    $contents = $zip->getFromName($entry);
    if (!is_string($contents)) {
        throw new TeamPassRecoveryException('MANIFEST_INVALID');
    }
    try {
        $data = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new TeamPassRecoveryException('MANIFEST_INVALID');
    }
    if (!is_array($data)) {
        throw new TeamPassRecoveryException('MANIFEST_INVALID');
    }
    return $data;
}

/** Validate a historical v1 recovery kit and return its data, never executing settings.php. */
function tpRecoveryReadKit(ZipArchive $zip): array
{
    $inventory = tpRecoveryInspectZip($zip, 16777216);
    $manifest = tpRecoveryReadZipJson($zip, 'manifest.json');
    if (($manifest['recovery_format'] ?? '') !== 'teampass.recovery'
        || ($manifest['recovery_format_version'] ?? null) !== 1) {
        throw new TeamPassRecoveryException('RECOVERY_FORMAT_UNSUPPORTED');
    }
    $constants = tpRecoveryReadZipJson($zip, 'config/constants.json');
    $prefix = $constants['DB_PREFIX'] ?? null;
    $secureName = $constants['SECUREFILE'] ?? null;
    if (!is_string($prefix) || preg_match('/^[a-zA-Z0-9_]{1,64}$/D', $prefix) !== 1
        || !is_string($secureName) || preg_match('/^[a-zA-Z0-9_.-]{1,200}$/D', $secureName) !== 1
        || in_array($secureName, ['.', '..'], true)) {
        throw new TeamPassRecoveryException('RECOVERY_CONFIG_INVALID');
    }
    $secretEntry = 'secrets/' . $secureName;
    $allowed = ['manifest.json', 'README.txt', 'config/settings.php', 'config/constants.json',
        'config/admin_settings_summary.json', $secretEntry, 'backup/instance_key.txt'];
    foreach ($inventory['entries'] as $entry => $size) {
        if (!in_array($entry, $allowed, true)) {
            throw new TeamPassRecoveryException('RECOVERY_UNEXPECTED_ENTRY');
        }
    }
    $declared = $manifest['contents'] ?? null;
    if (!is_array($declared) || !in_array($secretEntry, $declared, true)) {
        throw new TeamPassRecoveryException('RECOVERY_CONTENTS_INVALID');
    }
    foreach ($declared as $entry) {
        if (!is_string($entry) || !in_array($entry, $allowed, true) || !isset($inventory['entries'][$entry])) {
            throw new TeamPassRecoveryException('RECOVERY_CONTENTS_INVALID');
        }
    }
    $master = $zip->getFromName($secretEntry);
    if (!is_string($master) || strlen($master) > 4096) {
        throw new TeamPassRecoveryException('MASTER_KEY_INVALID');
    }
    try {
        $key = \Defuse\Crypto\Key::loadFromAsciiSafeString(trim($master));
    } catch (Throwable $exception) {
        throw new TeamPassRecoveryException('MASTER_KEY_INVALID');
    }
    $backupKey = $zip->getFromName('backup/instance_key.txt');
    return [
        'manifest' => $manifest,
        'prefix' => $prefix,
        'secure_name' => $secureName,
        'master_key' => $key->saveToAsciiSafeString(),
        'backup_key' => is_string($backupKey) ? rtrim($backupKey, "\r\n") : '',
        'legacy_constants' => array_intersect_key($constants, array_flip(['IKEY', 'SKEY', 'HOST'])),
    ];
}

<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 *
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_package_logic.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

/**
 * Fingerprint the material captured in a kit, without retaining any secret.
 *
 * The master key keys both HMACs, protecting low-entropy configuration values.
 * Compare the clear backup key, never its randomized encrypted representation.
 * Version numbers, schedules and ordinary vault changes do not age a kit.
 * The recovery generation also binds the receipt to the deployment after DRP.
 * Requires the canonical JSON helper from backup.functions.php.
 *
 * @return array{version: int, material: string, configuration: string}|array{}
 */
function tpRecoveryPackageFingerprint(
    string $masterKey,
    string $backupKey,
    array $constants,
    array $settings,
    string $generation,
    int $formatVersion
): array {
    if ($masterKey === '' || $backupKey === '') {
        return [];
    }

    $materialNames = ['DB_PREFIX', 'SECUREFILE', 'IKEY', 'SKEY', 'HOST'];
    $configurationNames = [
        'DB_HOST', 'DB_USER', 'DB_PASSWD', 'DB_NAME', 'DB_PORT', 'DB_ENCODING',
        'DB_SSL', 'DB_CONNECT_OPTIONS', 'TEAMPASS_ROOT', 'TEAMPASS_APP',
        'TEAMPASS_STORAGE', 'TEAMPASS_SECRETS',
    ];
    $deploymentNames = ['cpassman_dir', 'cpassman_url', 'path_to_files_folder', 'path_to_upload_folder'];
    $material = tpBackupCanonicalJson([
        'format' => $formatVersion,
        'backup_key' => $backupKey,
        'constants' => array_intersect_key($constants, array_flip($materialNames)),
    ]);
    $configuration = tpBackupCanonicalJson([
        'constants' => array_intersect_key($constants, array_flip($configurationNames)),
        'settings' => array_intersect_key($settings, array_flip($deploymentNames)),
        'generation' => $generation,
    ]);
    if ($material === '' || $configuration === '') {
        return [];
    }

    return [
        'version' => 1,
        'material' => hash_hmac('sha256', "teampass.recovery.material.v1\0" . $material, $masterKey),
        'configuration' => hash_hmac('sha256', "teampass.recovery.configuration.v1\0" . $configuration, $masterKey),
    ];
}

/** Tell whether a snapshot has the supported, complete fingerprint shape. */
function tpRecoveryPackageSnapshotIsValid(array $snapshot): bool
{
    return ($snapshot['version'] ?? null) === 1
        && is_string($snapshot['material'] ?? null)
        && preg_match('/^[a-f0-9]{64}$/D', $snapshot['material']) === 1
        && is_string($snapshot['configuration'] ?? null)
        && preg_match('/^[a-f0-9]{64}$/D', $snapshot['configuration']) === 1;
}

/** Resolve the notice state without database, session or filesystem access. */
function tpRecoveryPackageStatus(array $current, array $receipt): string
{
    if (tpRecoveryPackageSnapshotIsValid($current) === false) {
        return 'unavailable';
    }
    $saved = $receipt['snapshot'] ?? null;
    if (!is_array($saved) || tpRecoveryPackageSnapshotIsValid($saved) === false
        || !is_int($receipt['downloaded_at'] ?? null) || $receipt['downloaded_at'] <= 0
        || !is_int($receipt['downloaded_by'] ?? null) || $receipt['downloaded_by'] <= 0) {
        return 'missing';
    }
    if (hash_equals($current['material'], $saved['material']) === false) {
        return 'material_changed';
    }
    if (hash_equals($current['configuration'], $saved['configuration']) === false) {
        return 'configuration_changed';
    }

    return 'current';
}

/** An attempted or partial transfer must never acknowledge a kit. */
function tpRecoveryPackageTransferComplete(int|false $sent, int $expected, int $connectionStatus): bool
{
    return $expected > 0 && $sent === $expected && $connectionStatus === CONNECTION_NORMAL;
}

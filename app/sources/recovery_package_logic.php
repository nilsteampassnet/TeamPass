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
    if (tpRecoveryPackageReceiptIsValid($receipt) === false) {
        return 'missing';
    }
    $saved = $receipt['snapshot'];
    if (hash_equals($current['material'], $saved['material']) === false) {
        return 'material_changed';
    }
    if (hash_equals($current['configuration'], $saved['configuration']) === false) {
        return 'configuration_changed';
    }

    return 'current';
}

/** Distinguish an uninitialized backup key from unreadable recovery material. */
function tpRecoveryPackageStatusForInputs(array $current, array $receipt, array $settings): string
{
    return (string) ($settings['bck_script_passkey'] ?? '') === ''
        ? 'missing' : tpRecoveryPackageStatus($current, $receipt);
}

/** Build inexpensive change markers without copying a stored or legacy clear key. */
function tpRecoveryPackageStateInputs(array $settings): array
{
    $deployment = [];
    foreach (['cpassman_url', 'cpassman_dir', 'path_to_files_folder', 'path_to_upload_folder'] as $name) {
        $deployment[$name] = (string) ($settings[$name] ?? '');
    }

    return ['backup_key' => hash('sha256', (string) ($settings['bck_script_passkey'] ?? '')),
        'generation' => (string) ($settings['recovery_generation'] ?? ''), 'deployment' => $deployment];
}

/** Resolve the dashboard state using settings alone, without opening or decrypting keys. */
function tpRecoveryPackageStoredStatus(array $cached, array $settings): string
{
    if ($cached === []) {
        return 'missing';
    }
    if (($cached['version'] ?? null) !== 1
        || !in_array($cached['state'] ?? null, ['missing', 'material_changed', 'configuration_changed', 'unavailable', 'current'], true)
        || !is_array($cached['inputs'] ?? null)) {
        return 'unavailable';
    }
    if ((string) ($settings['bck_script_passkey'] ?? '') === '' || $cached['state'] === 'missing') {
        return 'missing';
    }
    if (in_array($cached['state'], ['unavailable', 'material_changed'], true)) {
        return $cached['state'];
    }
    $inputs = tpRecoveryPackageStateInputs($settings);
    if (($cached['inputs']['backup_key'] ?? null) !== $inputs['backup_key']) {
        return 'material_changed';
    }
    if (($cached['inputs']['generation'] ?? null) !== $inputs['generation']
        || ($cached['inputs']['deployment'] ?? null) !== $inputs['deployment']) {
        return 'configuration_changed';
    }

    return $cached['state'];
}

/** Validate the receipt before displaying its date or acknowledging a download. */
function tpRecoveryPackageReceiptIsValid(array $receipt): bool
{
    return is_array($receipt['snapshot'] ?? null)
        && tpRecoveryPackageSnapshotIsValid($receipt['snapshot'])
        && is_int($receipt['downloaded_at'] ?? null) && $receipt['downloaded_at'] > 0
        && is_int($receipt['downloaded_by'] ?? null) && $receipt['downloaded_by'] > 0;
}

/** An attempted or partial transfer must never acknowledge a kit. */
function tpRecoveryPackageTransferComplete(int|false $sent, int $expected, int $connectionStatus): bool
{
    return $expected > 0 && $sent === $expected && $connectionStatus === CONNECTION_NORMAL;
}

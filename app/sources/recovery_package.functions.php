<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 *
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_package.functions.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/backup.functions.php';

/** Remove a session-bound temporary package after download, expiry or replacement. */
function tpRecoveryForgetSessionPackage(object $session): void
{
    $current = $session->get('recovery-package-download');
    if (is_array($current) && !empty($current['path']) && is_scalar($current['path'])) {
        tpRecoverySafeDeletePackage((string) $current['path']);
    }
    if (method_exists($session, 'remove')) {
        $session->remove('recovery-package-download');
    } else {
        $session->set('recovery-package-download', null);
    }
}

/** Read fresh kit inputs without generating files or repairing keys; return inputs for state markers. */
function tpRecoveryPackageCurrentSnapshot(bool $lock = false, ?array &$inputs = null): array
{
    $settings = [];
    foreach (DB::query(
        'SELECT intitule, valeur FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule IN %ls'
            . ($lock ? ' FOR UPDATE' : ''),
        'admin',
        ['bck_script_passkey', 'recovery_generation', 'cpassman_dir', 'cpassman_url',
            'path_to_files_folder', 'path_to_upload_folder']
    ) as $row) {
        $settings[(string) $row['intitule']] = (string) $row['valeur'];
    }
    $inputs = $settings;
    $backup = tpResolveBackupScriptPasskey($settings, false);
    if ($backup['source'] === 'empty') {
        return [];
    }
    $path = tpRecoveryGetSecureFilePath();
    $master = $path !== '' && is_readable($path) ? @file_get_contents($path) : false;
    if (!is_string($master) || empty($backup['success']) || !is_readable(tpRecoveryGetSettingsFilePath())) {
        return [];
    }

    return tpRecoveryPackageFingerprint(
        $master,
        (string) $backup['clear_key'],
        tpRecoveryCaptureConfigConstants(),
        $settings,
        (string) ($settings['recovery_generation'] ?? ''),
        tpRecoveryGetPackageFormatVersion()
    );
}

/** Read the instance-wide download receipt; missing or malformed legacy state remains unknown. */
function tpRecoveryPackageReadReceipt(): array
{
    $json = DB::queryFirstField(
        'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s',
        'settings',
        'recovery_package_download'
    );
    $receipt = is_string($json) ? json_decode($json, true) : null;

    return is_array($receipt) ? $receipt : [];
}

/** Run a read-only full check outside the dashboard, without updating the stored state. */
function tpRecoveryPackageGetStatus(): string
{
    try {
        $inputs = [];
        $snapshot = tpRecoveryPackageCurrentSnapshot(false, $inputs);
        return tpRecoveryPackageStatusForInputs($snapshot, tpRecoveryPackageReadReceipt(), $inputs);
    } catch (Throwable $exception) {
        return 'unavailable';
    }
}

/** Return only the date and status labels needed by the administrator's kit tab. */
function tpRecoveryPackageGetDisplayInformation(array $SETTINGS, Language $lang): array
{
    $receipt = [];
    $state = 'unavailable';
    $inputs = $SETTINGS;
    try {
        $receipt = tpRecoveryPackageReadReceipt();
        $snapshot = tpRecoveryPackageCurrentSnapshot(false, $inputs);
        $state = tpRecoveryPackageStatusForInputs($snapshot, $receipt, $inputs);
    } catch (Throwable $exception) {
        // A known last download may still be shown, without claiming the kit is current.
    }
    try {
        tpRecoveryPackageSaveState($state, $inputs);
    } catch (Throwable $exception) {
        $state = 'unavailable';
        error_log('TeamPass: unable to store the Recovery Package status.');
    }
    $downloadedAt = tpRecoveryPackageReceiptIsValid($receipt) ? $receipt['downloaded_at'] : 0;
    $downloadLabel = $downloadedAt > 0
        ? sprintf($lang->get('bck_recovery_package_last_download'), date(
            (string) ($SETTINGS['date_format'] ?? 'Y-m-d') . ' ' . (string) ($SETTINGS['time_format'] ?? 'H:i'),
            $downloadedAt
        ))
        : $lang->get('bck_recovery_package_no_download');
    $statusKey = match ($state) {
        'current' => 'bck_recovery_package_current',
        'material_changed', 'configuration_changed' => 'bck_recovery_package_outdated',
        'unavailable' => 'bck_recovery_package_status_unavailable',
        default => '',
    };

    return ['downloaded_at' => $downloadedAt, 'state' => $state,
        'download_label' => $downloadLabel, 'status_label' => $statusKey !== '' ? $lang->get($statusKey) : ''];
}

/**
 * Audit an entirely sent kit and acknowledge it only when it is still current.
 *
 * Lock the singleton receipt before re-reading the key and deployment settings.
 * An older download can never overwrite a receipt for the current material.
 * Only the server's session-bound snapshot is accepted by the caller.
 */
function tpRecoveryPackageRecordDownload(array $snapshot, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    $started = false;
    try {
        if (DB::transactionDepth() !== 0) {
            return false;
        }
        DB::startTransaction();
        $started = true;
        $now = time();
        $current = false;
        DB::query(
            'INSERT IGNORE INTO ' . prefixTable('misc') . ' (type, intitule, valeur) VALUES (%s, %s, %s)',
            'settings', 'recovery_package_download', ''
        );
        $stored = DB::queryFirstField(
            'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s FOR UPDATE',
            'settings', 'recovery_package_download'
        );
        $savedReceipt = is_string($stored) ? json_decode($stored, true) : null;
        $savedReceipt = is_array($savedReceipt) ? $savedReceipt : [];
        $inputs = [];
        $currentSnapshot = tpRecoveryPackageCurrentSnapshot(true, $inputs);
        if (tpRecoveryPackageSnapshotIsValid($snapshot)) {
            $receipt = ['snapshot' => $snapshot, 'downloaded_at' => $now, 'downloaded_by' => $userId];
            $current = tpRecoveryPackageStatusForInputs($currentSnapshot, $receipt, $inputs) === 'current';
            if ($current) {
                DB::update(
                    prefixTable('misc'),
                    ['valeur' => json_encode($receipt, JSON_THROW_ON_ERROR)],
                    'type=%s AND intitule=%s',
                    'settings', 'recovery_package_download'
                );
                $savedReceipt = $receipt;
            }
        }
        tpRecoveryPackageSaveState(tpRecoveryPackageStatusForInputs($currentSnapshot, $savedReceipt, $inputs), $inputs);
        DB::insert(prefixTable('log_system'), [
            'type' => 'admin_action', 'date' => $now, 'label' => 'recovery_package_downloaded',
            'qui' => (string) $userId, 'field_1' => '',
        ]);
        DB::commit();
    } catch (Throwable $exception) {
        if ($started) {
            DB::rollback();
        }
        // The response already contains the encrypted file. Never append an error or a secret.
        error_log('TeamPass: unable to record the Recovery Package download.');
        return false;
    }
    ConfigManager::invalidateCache();

    return $current;
}

<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 *
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_package_state.functions.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

require_once __DIR__ . '/recovery_package_logic.php';

/** Read the optional derived state with one indexed lookup and no backup dependencies. */
function tpRecoveryPackageReadStoredState(): array
{
    $json = DB::queryFirstField(
        'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s',
        'settings', 'recovery_package_status'
    );
    $cached = is_string($json) ? json_decode($json, true) : null;

    return is_array($cached) ? $cached : [];
}

/** Read the dashboard's persisted status without filesystem access or decryption. */
function tpRecoveryPackageGetStoredStatus(array $settings): string
{
    try {
        return tpRecoveryPackageStoredStatus(tpRecoveryPackageReadStoredState(), $settings);
    } catch (Throwable $exception) {
        return 'unavailable';
    }
}

/** Persist only derived status and non-secret markers; the receipt remains independent. */
function tpRecoveryPackageSaveState(string $state, array $settings): void
{
    $json = json_encode(['version' => 1, 'state' => $state,
        'inputs' => tpRecoveryPackageStateInputs($settings)], JSON_THROW_ON_ERROR);
    DB::insertUpdate(prefixTable('misc'), [
        'type' => 'settings', 'intitule' => 'recovery_package_status', 'valeur' => $json,
    ], ['valeur' => $json]);
}

/**
 * Invalidate state after an application setting changes, without checking recovery files.
 *
 * When the clear backup key is unchanged, compare the previous storage markers before
 * replacing them. Randomized re-encryption alone must not request another kit.
 */
function tpRecoveryPackageSettingsChanged(array $settings, ?array $previousSettings = null): void
{
    try {
        $state = tpRecoveryPackageStoredStatus(tpRecoveryPackageReadStoredState(), $previousSettings ?? $settings);
        tpRecoveryPackageSaveState($state, $settings);
    } catch (Throwable $exception) {
        // Dashboard marker comparisons still detect changed inputs if this optional cache write fails.
        error_log('TeamPass: unable to update the Recovery Package status.');
    }
}

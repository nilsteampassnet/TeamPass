<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_upgrade.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

require_once __DIR__ . '/recovery_logic.php';

/** Select shipped upgrade scripts, including replayable changes in the source release. */
function tpRecoveryUpgradeScripts(array $plan, string $directory): array
{
    if (empty($plan['upgrade_required'])) {
        return [];
    }
    $source = implode('.', array_slice(explode('.', $plan['source_version']), 0, 3));
    $target = implode('.', array_slice(explode('.', $plan['target_version']), 0, 3));
    $scripts = [];
    foreach (['3.2.2', '3.2.3'] as $release) {
        if (version_compare($release, $source, '>=') && version_compare($release, $target, '<=')) {
            $file = $directory . '/upgrade_run_' . $release . '.php';
            if (!is_file($file) || !is_readable($file)) {
                throw new TeamPassRecoveryException('UPGRADE_SCRIPT_MISSING');
            }
            $scripts[$release] = $file;
        }
    }
    if (!isset($scripts[$target])) {
        throw new TeamPassRecoveryException('UPGRADE_PATH_UNSUPPORTED');
    }
    return $scripts;
}

/** Decode a legacy migration response; exit status alone is not a success signal. */
function tpRecoveryCheckMigrationResult(string $output): void
{
    $result = json_decode(trim($output), true);
    if (!is_array($result) || count($result) !== 1 || !is_array($result[0])
        || (string) ($result[0]['finish'] ?? '') !== '1'
        || (string) ($result[0]['error'] ?? '') !== '') {
        throw new TeamPassRecoveryException('UPGRADE_FAILED');
    }
}

<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

/** Exercise the actual CLI against the disposable database and original key created by the recovery fixture. */
function tpTestClassicBackupRestore(string $root, string $fixture, array $backup, array $connection, array $settings, string $bytes): void
{
    if (!is_file($root . '/_tools/drp-lab-allow-activation') || is_file($root . '/app/config/settings.php')
        || is_file($root . '/secrets/fixture.key') || tpBackupRestoreIsInProgress()) {
        throw new RuntimeException('Classic restore integration requires an empty, explicitly disposable checkout.');
    }
    $master = (string) file_get_contents($fixture . '/keys/fixture.key');
    $config = tpRecoverySettingsPhp(['connection' => $connection,
        'kit' => ['prefix' => DB_PREFIX, 'secure_name' => 'fixture.key', 'legacy_constants' => []]], $master);
    file_put_contents($root . '/app/config/settings.php', $config);
    file_put_contents($root . '/secrets/fixture.key', $master);
    chmod($root . '/app/config/settings.php', 0600);
    chmod($root . '/secrets/fixture.key', 0600);
    try {
        $authorization = tpRestoreAuthorizationCreate(1, 'admin', $backup['filepath'], '', basename($backup['filepath']),
            0, 'synthetic-backup-password', '', ['expected_schema_level' => (string) UPGRADE_MIN_DATE], 3600, $settings);
        if (!$authorization['success']) { throw new RuntimeException('Could not authorize the classic restore fixture.'); }
        $process = new \Symfony\Component\Process\Process([PHP_BINARY, $root . '/app/scripts/restore.php',
            '--file', $backup['filepath'], '--auth-token', $authorization['token'], '--force-disconnect'], $root);
        $process->setTimeout(180);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException('Classic restore failed: ' . $process->getOutput() . $process->getErrorOutput());
        }
        if (tpBackupRestoreIsInProgress() || DB::queryFirstField('SELECT payload FROM ' . prefixTable('backup_bytes')) !== $bytes
            || (int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('api_tokens')) !== 0
            || (int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('device_pairings')) !== 0) {
            throw new RuntimeException('Classic restore lost bytes, retained old credentials or left its hold active.');
        }
        foreach (['bck_scheduled_enabled', 'bck_externalized_enabled'] as $name) {
            if (DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s', 'settings', $name) !== '0') {
                throw new RuntimeException('Classic restore did not disable backup schedules.');
            }
        }
        if (DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s', 'admin', 'maintenance_mode') !== '1'
            || strlen((string) DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s', 'admin', 'recovery_generation')) !== 32) {
            throw new RuntimeException('Classic restore did not retain maintenance and reset offline synchronization.');
        }
    } finally {
        // These files were absent at entry and were created only in the opted-in disposable checkout.
        foreach ([$root . '/app/config/settings.php', $root . '/secrets/fixture.key', tpBackupRestoreGuardPath()] as $path) {
            if (is_file($path)) { unlink($path); }
        }
    }
}

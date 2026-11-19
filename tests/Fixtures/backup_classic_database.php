<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

/** Exercise the actual CLI against the disposable database and original key created by the recovery fixture. */
function tpTestClassicBackupRestore(string $root, string $fixture, array $backup, array $connection, array $settings, string $bytes, bool $exerciseResume = false): void
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
        require_once $root . '/app/sources/upgrade_cli.functions.php';
        $temporaryBefore = glob(sys_get_temp_dir() . '/tpbackup_tmp_*');
        tpUpgradeCliVerifyBackup($backup['filepath'], [], $settings);
        $instanceCandidates = tpGetBackupScriptPasskeyCandidates($settings, false);
        file_put_contents($fixture . '/verify.sql', "DROP TABLE IF EXISTS `fixture_records`;\n");
        \Defuse\Crypto\File::encryptFileWithPassword($fixture . '/verify.sql', $fixture . '/verify.encrypted.sql', $instanceCandidates[0]);
        $verified = tpUpgradeCliVerifyBackup($fixture . '/verify.encrypted.sql', ['fixture_records' => 0], $settings);
        if (!$verified['success'] || glob(sys_get_temp_dir() . '/tpbackup_tmp_*') !== $temporaryBefore) {
            throw new RuntimeException('Upgrade backup verification left private staging directories behind.');
        }
        $authorization = tpRestoreAuthorizationCreate(1, 'admin', $backup['filepath'], '', basename($backup['filepath']),
            0, 'synthetic-backup-password', '', ['expected_schema_level' => (string) UPGRADE_MIN_DATE], 3600, $settings);
        if (!$authorization['success']) { throw new RuntimeException('Could not authorize the classic restore fixture.'); }
        $process = new \Symfony\Component\Process\Process([PHP_BINARY, $root . '/app/scripts/restore.php',
            '--file', $backup['filepath'], '--auth-token', $authorization['token'], '--force-disconnect'], $root);
        $process->setTimeout(180);
        $previousMaintenance = DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s', 'admin', 'maintenance_mode');
        DB::query('CREATE TRIGGER restore_auth_failure_fixture BEFORE UPDATE ON ' . prefixTable('misc')
            . " FOR EACH ROW BEGIN IF NEW.type='restore_authorization_cli' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic authorization write failure'; END IF; END");
        $process->run();
        if ($process->getExitCode() !== 50 || tpBackupRestoreIsInProgress()
            || DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s', 'admin', 'maintenance_mode') !== $previousMaintenance) {
            throw new RuntimeException('A harmless pre-import failure retained its hold or changed maintenance.');
        }
        DB::query('DROP TRIGGER restore_auth_failure_fixture');
        if ($exerciseResume) {
            $blocker = new mysqli($connection['host'], $connection['user'], $connection['password'], $connection['database'], $connection['port']);
            $blocker->query('LOCK TABLES ' . prefixTable('api') . ' READ');
            try {
                $process->start();
                $deadline = microtime(true) + 15;
                while (!tpBackupRestoreIsInProgress() && $process->isRunning() && microtime(true) < $deadline) {
                    usleep(20000);
                }
                if (!tpBackupRestoreIsInProgress()) {
                    throw new RuntimeException('The controlled import did not reach its persistent hold.');
                }
                $process->stop(0, 9);
            } finally {
                $blocker->query('UNLOCK TABLES');
                $blocker->close();
            }
            // Simulate the point where an interrupted DDL import has already replaced auth/settings tables.
            DB::query('DROP TABLE ' . prefixTable('misc'));
            DB::query('DROP TABLE ' . prefixTable('users'));
            $arguments = [PHP_BINARY, $root . '/app/scripts/restore.php', '--file', $backup['filepath'],
                '--auth-token', 'wrong-original-token', '--resume'];
            $refused = new \Symfony\Component\Process\Process($arguments, $root);
            $refused->run();
            if ($refused->getExitCode() !== 36) {
                throw new RuntimeException('Resume accepted another authorization token.');
            }
            $arguments[5] = $authorization['token'];
            $originalArchive = file_get_contents($backup['filepath']);
            file_put_contents($backup['filepath'], 'changed archive', FILE_APPEND);
            $changed = new \Symfony\Component\Process\Process($arguments, $root);
            $changed->run();
            file_put_contents($backup['filepath'], $originalArchive);
            if ($changed->getExitCode() !== 34 || !tpBackupRestoreIsInProgress()) {
                throw new RuntimeException('Resume accepted a changed archive or released the hold.');
            }
            $process = new \Symfony\Component\Process\Process($arguments, $root);
            $process->setTimeout(180);
        }
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException('Classic restore failed: ' . $process->getOutput() . $process->getErrorOutput());
        }
        if ((string) DB::queryFirstField('SELECT qui FROM ' . prefixTable('log_system') . ' WHERE type=%s AND label=%s',
            'admin_action', 'dataBase restore') !== '1') {
            throw new RuntimeException('The restore audit lost its original administrator identity.');
        }
        if (tpBackupRestoreIsInProgress() || DB::queryFirstField('SELECT payload FROM ' . prefixTable('backup_bytes')) !== $bytes
            || (int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('api_tokens')) !== 0
            || (int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('device_pairings')) !== 0
            || (int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('api') . ' WHERE type=%s AND value<>%s', 'user', '') !== 0
            || DB::queryFirstField('SELECT value FROM ' . prefixTable('api') . ' WHERE type=%s', 'ip') !== '127.0.0.1'
            || (int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('api')
                . ' WHERE session_key IS NOT NULL OR session_aes_key IS NOT NULL OR encrypted_private_key IS NOT NULL') !== 0) {
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

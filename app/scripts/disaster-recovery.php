<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file disaster-recovery.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!is_readable(__DIR__ . '/../config/include.php')) {
    fwrite(STDERR, "Recovery stopped: RECOVERY_BOOTSTRAP_UNREADABLE. Check file permissions and copy the complete PHP service account command from /install/recovery.php. Do not replace sudo -u with sudo php.\n");
    exit(1);
}
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/include.php';
require_once __DIR__ . '/../sources/recovery.functions.php';

$options = getopt('', ['enable', 'prepare:', 'run:', 'activate:', 'resume:', 'status:', 'discard:', 'help']);
try {
    foreach (['enable', 'prepare', 'run', 'activate', 'resume', 'discard'] as $writeOperation) {
        if (isset($options[$writeOperation])) {
            tpRecoveryAssertServiceAccount();
            break;
        }
    }
    if (isset($options['enable'])) {
        tpRecoveryAssertFreshTarget();
        $token = bin2hex(random_bytes(32));
        tpRecoveryWritePrivate(tpRecoveryJobDirectory() . '/operator.json', json_encode([
            'token_hash' => hash('sha256', $token), 'expires_at' => time() + 3600,
        ], JSON_THROW_ON_ERROR));
        fwrite(STDOUT, "Open /install/recovery.php and enter this one-hour operator code:\n" . $token . "\n");
        exit(0);
    }
    if (isset($options['prepare'])) {
        $path = (string) $options['prepare'];
        $payload = $path === '-' ? stream_get_contents(STDIN) : file_get_contents($path);
        if (!is_string($payload) || strlen($payload) > 2097152) {
            throw new TeamPassRecoveryException('RECOVERY_REQUEST_INVALID');
        }
        $input = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($input)) {
            throw new TeamPassRecoveryException('RECOVERY_REQUEST_INVALID');
        }
        $result = tpRecoveryPrepare($input);
        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        fwrite(STDOUT, 'Next: ' . tpRecoveryCommandBase() . ' --run=' . $result['id'] . "\n");
        exit(0);
    }
    if (isset($options['run'])) {
        $id = (string) $options['run'];
        fwrite(STDOUT, "Importing and verifying the dedicated recovery database...\n");
        $result = tpRecoveryRun($id);
        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        fwrite(STDOUT, "Database verified. Publish the files and configuration with:\n" . tpRecoveryCommandBase() . ' --activate=' . $id . "\n");
        exit(0);
    }
    if (isset($options['activate'])) {
        $result = tpRecoveryActivate((string) $options['activate']);
        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        fwrite(STDOUT, "Instance restored in maintenance. Check administrator access, user decryption, MFA and HTTPS before resuming.\n");
        exit(0);
    }
    if (isset($options['status'])) {
        $id = (string) $options['status'];
        $directory = tpRecoveryJobDirectory($id);
        if (is_file($directory . '/receipt.json') && !is_link($directory . '/receipt.json')) {
            $result = json_decode((string) file_get_contents($directory . '/receipt.json'), true, 32, JSON_THROW_ON_ERROR);
        } else {
            $result = tpRecoveryPublicJob(tpRecoveryLoadJob($id));
        }
        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        exit(0);
    }
    if (isset($options['resume'])) {
        $id = (string) $options['resume'];
        tpRecoveryJobDirectory($id);
        require_once __DIR__ . '/../sources/main.functions.php';
        if (($SETTINGS['recovery_job_id'] ?? '') !== $id || (int) ($SETTINGS['recovery_pending_checks'] ?? 0) !== 1) {
            throw new TeamPassRecoveryException('RECOVERY_RESUME_INVALID');
        }
        DB::startTransaction();
        try {
            foreach (['recovery_pending_checks', 'maintenance_mode'] as $name) {
                DB::update(prefixTable('misc'), ['valeur' => '0'], 'type=%s AND intitule=%s', 'admin', $name);
            }
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollback();
            throw $exception;
        }
        \TeampassClasses\ConfigManager\ConfigManager::invalidateCache();
        fwrite(STDOUT, "Recovery checks acknowledged. Maintenance and the recovery task hold are released.\nBackup schedules and LAPR remain disabled until reconfigured.\n");
        exit(0);
    }
    if (isset($options['discard'])) {
        tpRecoveryDiscard((string) $options['discard']);
        fwrite(STDOUT, "Private recovery staging removed. The destination database was not changed.\n");
        exit(0);
    }
    fwrite(STDOUT, "TeamPass disaster recovery\n"
        . "  --enable              Enable the installation recovery form for one hour\n"
        . "  --prepare=FILE|-      Validate a private JSON request (or read standard input)\n"
        . "  --run=JOB             Restore and upgrade an empty dedicated database\n"
        . "  --activate=JOB        Publish verified data and configuration in maintenance\n"
        . "  --resume=JOB          Release maintenance after operational checks\n"
        . "  --status=JOB          Print non-sensitive progress\n"
        . "  --discard=JOB         Remove private staging of a prepared or failed job\n"
        . "See docs/install/disaster-recovery.md. Secrets are never accepted as command arguments.\n");
    exit(isset($options['help']) ? 0 : 2);
} catch (Throwable $exception) {
    $reason = $exception instanceof TeamPassRecoveryException ? $exception->reason : 'RECOVERY_OPERATION_FAILED';
    if ($reason === 'RECOVERY_SERVICE_ACCOUNT_REQUIRED') {
        fwrite(STDERR, "Do not run recovery with sudo php or as root. Open /install/recovery.php for the exact sudo -u command using the PHP service account.\n");
    }
    fwrite(STDERR, 'Recovery stopped: ' . $reason . ". No automatic cleanup of the destination database was attempted.\n");
    exit(1);
}

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

$options = getopt('', ['enable', 'prepare:', 'run:', 'verification-timeout:', 'activate:',
    'acknowledge-unusable-tp-key', 'check-permissions:', 'resume:', 'status:', 'discard:', 'help']);
try {
    foreach (['enable', 'prepare', 'run', 'activate', 'check-permissions', 'resume', 'discard'] as $writeOperation) {
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
        $verificationTimeout = filter_var($options['verification-timeout'] ?? 1800, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 86400]]);
        if ($verificationTimeout === false) {
            throw new TeamPassRecoveryException('RECOVERY_VERIFICATION_TIMEOUT_INVALID');
        }
        fwrite(STDOUT, "Importing and verifying the dedicated recovery database...\n");
        $result = tpRecoveryRun($id, $verificationTimeout);
        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        $acknowledgement = ($result['encryption']['tp_key_status'] ?? '') === 'unusable';
        if ($acknowledgement) {
            fwrite(STDOUT, "The internal TP private key cannot be opened. User sharekeys have been preserved, but their item decryption could not be verified. Review the diagnostic and repair the TP reference keys after recovery. Publication requires your explicit acknowledgement.\n");
        }
        fwrite(STDOUT, "Database verified. Publish the files and configuration with:\n" . tpRecoveryCommandBase()
            . ' --activate=' . $id . ($acknowledgement ? ' --acknowledge-unusable-tp-key' : '') . "\n");
        exit(0);
    }
    if (isset($options['activate'])) {
        $result = tpRecoveryActivate((string) $options['activate'], isset($options['acknowledge-unusable-tp-key']));
        try {
            // Publication has released its lock. Audit once here, so operators
            // can go straight to a single correction-and-verification block.
            $result = tpRecoveryCheckPermissions($result['id']);
        } catch (Throwable $exception) {
            fwrite(STDERR, "Publication succeeded; the permission audit needs to be run from the assistant's fallback command.\n");
        }
        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        fwrite(STDOUT, "Instance published in maintenance. Refresh the assistant for permission corrections and finalization. Keep cron stopped until cleanup and access checks are complete.\n");
        exit(0);
    }
    if (isset($options['status'])) {
        $id = (string) $options['status'];
        $directory = tpRecoveryJobDirectory($id);
        if (is_file($directory . '/receipt.json') && !is_link($directory . '/receipt.json')) {
            $result = tpRecoveryLoadReceipt($id);
        } else {
            $result = tpRecoveryPublicJob(tpRecoveryLoadJob($id));
        }
        fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        exit(0);
    }
    if (isset($options['check-permissions'])) {
        $receipt = tpRecoveryCheckPermissions((string) $options['check-permissions']);
        $review = $receipt['permissions'];
        fwrite(STDOUT, 'Permission review: ' . $review['status'] . "\n");
        if ($review['issues'] !== null) { fwrite(STDOUT, 'Permission findings: ' . $review['issues'] . "\n"); }
        if ($review['commands'] !== []) {
            fwrite(STDOUT, "Review and run these commands as an administrator, then repeat the permission check:\n"
                . implode("\n", $review['commands']) . "\n");
        } elseif ($review['status'] === 'manual') {
            fwrite(STDOUT, "Automatic POSIX permission auditing is unavailable. Review the platform's ACLs manually.\n");
        } elseif ($review['status'] === 'issues') {
            fwrite(STDOUT, "No automatic remediation plan is available for this layout. Correct the findings manually, then repeat this audit.\n");
        }
        fwrite(STDOUT, "Refresh the recovery assistant to continue. No permissions were changed by this command.\n");
        exit($review['status'] === 'issues' ? 2 : 0);
    }
    if (isset($options['resume'])) {
        $id = (string) $options['resume'];
        $lock = tpRecoveryFinalizationLock();
        try {
            $receipt = tpRecoveryLoadReceipt($id);
            require_once __DIR__ . '/../sources/main.functions.php';
            if (($SETTINGS['recovery_job_id'] ?? '') !== $id) {
                throw new TeamPassRecoveryException('RECOVERY_RESUME_INVALID');
            }
            $pendingChecks = (string) ($SETTINGS['recovery_pending_checks'] ?? '');
            if (!in_array($pendingChecks, ['0', '1'], true)) {
                throw new TeamPassRecoveryException('RECOVERY_RESUME_INVALID');
            }
            if ($pendingChecks === '1') {
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
            }
            // Publish completion only after the database transaction succeeds.
            // Repeating this command repairs a receipt left behind by an interruption.
            $receipt['status'] = 'resumed';
            $receipt['resumed_at'] ??= time();
            $receipt['maintenance_enabled'] = $pendingChecks === '0' && (int) ($SETTINGS['maintenance_mode'] ?? 0) === 1;
            tpRecoverySaveReceipt($receipt);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        \TeampassClasses\ConfigManager\ConfigManager::invalidateCache();
        fwrite(STDOUT, $receipt['maintenance_enabled']
            ? "Recovery was already resumed. Separately enabled maintenance was left unchanged.\n"
            : "Recovery finalized. Maintenance and the recovery task hold are released.\n");
        fwrite(STDOUT, "Backup schedules and LAPR remain disabled until reconfigured.\n");
        fwrite(STDOUT, "Refresh the assistant, remove the installer, check application access, then restore the host cron job.\n");
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
        . "  --verification-timeout=SECONDS  Timeout per 100-item verification batch (1-86400; default 1800)\n"
        . "  --activate=JOB        Publish verified data and configuration in maintenance\n"
        . "  --acknowledge-unusable-tp-key  Explicitly accept the reported TP key diagnostic at activation\n"
        . "  --check-permissions=JOB  Audit runtime permissions and propose administrator commands\n"
        . "  --resume=JOB          Finalize publication and release the recovery hold\n"
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
    if ($reason === 'RECOVERY_CRYPTO_VERIFICATION_TIMEOUT') {
        fwrite(STDERR, "Retry --run=JOB with a larger --verification-timeout=SECONDS. Completed verification batches are preserved; the database is not imported again.\n");
    }
    fwrite(STDERR, "A failed or interrupted job retains private job.json database credentials and keys until --discard=JOB. Retry publication first when applicable. Discard removes staging only, not the database or files already published. Private request files and storage/recovery/incoming/ archives are not automatically removed; protect or move them to approved storage outside the web root.\n");
    exit(1);
}

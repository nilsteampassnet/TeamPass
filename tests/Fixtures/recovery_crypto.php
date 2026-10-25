<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_crypto.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

/** Exercise real worker batches and production key anomalies in a disposable database. */
function tpTestRecoveryCrypto(string $root, string $source, string $jobId, mysqli $database): void
{
    $originalJob = tpRecoveryLoadJob($jobId);
    $keyPath = tpRecoveryJobDirectory($jobId) . '/keys/' . $originalJob['kit']['secure_name'];
    $master = (string) file_get_contents($keyPath);
    $private = $database->query('SELECT private_key FROM recovery_user_private_keys WHERE user_id=' . (int) TP_USER_ID . ' AND is_current=1')->fetch_row()[0];
    $generation = $database->query("SELECT valeur FROM recovery_misc WHERE intitule='recovery_generation' AND type='admin'")->fetch_row()[0];
    $worker = static function (array $job) use ($root, $jobId): \Symfony\Component\Process\Process {
        tpRecoverySaveJob($job);
        $process = new \Symfony\Component\Process\Process([
            PHP_BINARY, 'app/scripts/recovery-migrate.php', '--job=' . $jobId, '--release=verify',
        ], $root);
        $process->setTimeout(120);
        $process->run();
        return $process;
    };
    $freshVerification = static function () use ($originalJob): array {
        $job = $originalJob;
        $job['status'] = 'verifying';
        unset($job['encryption']);
        return $job;
    };
    try {
        $job = $freshVerification();
        $process = $worker($job);
        $response = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
        $firstBatch = $response[0]['encryption'] ?? [];
        if (!$process->isSuccessful() || ($firstBatch['complete'] ?? true)
            || $firstBatch['checked_items'] !== 99 || $firstBatch['missing_tp_sharekeys'] !== 1) {
            throw new RuntimeException('The first encryption batch was not bounded or counted a missing key as corrupt');
        }
        // This is the on-disk state after an operator stopped between workers.
        $job['encryption'] = $firstBatch;
        tpRecoverySaveJob($job);
        // Force a real worker timeout without depending on RSA speed: its item
        // read waits on this other connection, while the master-key proof can run.
        $database->query('LOCK TABLES recovery_items WRITE');
        try {
            try {
                tpRecoveryRun($jobId, 1);
                throw new RuntimeException('The verification worker did not enforce its configured timeout');
            } catch (TeamPassRecoveryException $exception) {
                $stopped = tpRecoveryLoadJob($jobId);
                if ($exception->reason !== 'RECOVERY_CRYPTO_VERIFICATION_TIMEOUT'
                    || $stopped['status'] !== 'verifying' || $stopped['encryption'] !== $firstBatch) {
                    throw $exception;
                }
            }
        } finally { $database->query('UNLOCK TABLES'); }
        $resumed = tpRecoveryRun($jobId);
        if ($resumed['status'] !== 'verified' || $resumed['encryption']['checked_items'] !== 102
            || $database->query("SELECT valeur FROM recovery_misc WHERE intitule='recovery_generation' AND type='admin'")->fetch_row()[0] !== $generation) {
            throw new RuntimeException('Resuming verification reimported the database or double-counted a batch');
        }

        $database->query('DELETE FROM recovery_sharekeys_items WHERE user_id=' . (int) TP_USER_ID);
        tpRecoverySaveJob($freshVerification());
        $rebuilt = tpRecoveryRun($jobId);
        if ($rebuilt['encryption']['checked_items'] !== 0 || $rebuilt['encryption']['missing_tp_sharekeys'] !== 103) {
            throw new RuntimeException('A rebuilt TP account without reference keys was rejected');
        }
        $database->query('INSERT INTO recovery_sharekeys_items SELECT * FROM `' . $source . '`.recovery_sharekeys_items WHERE user_id=' . (int) TP_USER_ID);

        $database->query("UPDATE recovery_sharekeys_items SET share_key='' WHERE object_id=1 AND user_id=" . (int) TP_USER_ID);
        $corrupt = $worker($freshVerification());
        if ($corrupt->isSuccessful() || trim($corrupt->getErrorOutput()) !== 'ITEM_DECRYPTION_FAILED') {
            throw new RuntimeException('An existing but unreadable TP sharekey was accepted');
        }
        $publicKey = $database->query('SELECT public_key FROM recovery_users WHERE id=' . (int) TP_USER_ID)->fetch_row()[0];
        $wrongObjectKey = encryptUserObjectKey(base64_encode(bin2hex(random_bytes(32))), $publicKey);
        $statement = $database->prepare('UPDATE recovery_sharekeys_items SET share_key=? WHERE object_id=1 AND user_id=?');
        $systemId = (int) TP_USER_ID;
        $statement->bind_param('si', $wrongObjectKey, $systemId);
        $statement->execute();
        $statement->close();
        $mismatch = $worker($freshVerification());
        if ($mismatch->isSuccessful() || trim($mismatch->getErrorOutput()) !== 'ITEM_DECRYPTION_FAILED') {
            throw new RuntimeException('A TP sharekey that opened a different object key was accepted');
        }
        $database->query('UPDATE recovery_sharekeys_items t JOIN `' . $source . '`.recovery_sharekeys_items s ON s.increment_id=t.increment_id SET t.share_key=s.share_key WHERE t.user_id=' . (int) TP_USER_ID);

        $database->query("UPDATE recovery_user_private_keys SET private_key='' WHERE user_id=" . (int) TP_USER_ID . ' AND is_current=1');
        tpRecoverySaveJob($freshVerification());
        $unusable = tpRecoveryRun($jobId);
        if ($unusable['encryption']['tp_key_status'] !== 'unusable'
            || $unusable['encryption']['diagnostic'] !== 'private_key_missing'
            || $unusable['encryption']['checked_items'] !== 0 || $unusable['encryption']['skipped_items'] !== 102) {
            throw new RuntimeException('An unusable TP private key was not reported as an explicit diagnostic');
        }
        try {
            tpRecoveryActivate($jobId);
            throw new RuntimeException('An unusable TP private key was activated without acknowledgement');
        } catch (TeamPassRecoveryException $exception) {
            if ($exception->reason !== 'RECOVERY_TP_KEY_ACKNOWLEDGEMENT_REQUIRED'
                || tpRecoveryLoadJob($jobId)['status'] !== 'verified') {
                throw $exception;
            }
        }
        $statement = $database->prepare('UPDATE recovery_user_private_keys SET private_key=? WHERE user_id=? AND is_current=1');
        $systemId = (int) TP_USER_ID;
        $statement->bind_param('si', $private, $systemId);
        $statement->execute();
        $statement->close();

        file_put_contents($keyPath, \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString());
        tpRecoverySaveJob($freshVerification());
        try {
            tpRecoveryRun($jobId);
            throw new RuntimeException('A different instance master key was accepted');
        } catch (TeamPassRecoveryException $exception) {
            if ($exception->reason !== 'MASTER_KEY_DATABASE_MISMATCH') { throw $exception; }
        }
        $job = $originalJob;
        $job['encryption'] = $unusable['encryption'];
        tpRecoverySaveJob($job);
        try {
            tpRecoveryActivate($jobId, true);
            throw new RuntimeException('The TP-key acknowledgement bypassed the master-key proof');
        } catch (TeamPassRecoveryException $exception) {
            if ($exception->reason !== 'MASTER_KEY_DATABASE_MISMATCH') { throw $exception; }
        }
        foreach ([0, 86401] as $timeout) {
            try {
                tpRecoveryRun($jobId, $timeout);
                throw new RuntimeException('An invalid verification timeout was accepted');
            } catch (TeamPassRecoveryException $exception) {
                if ($exception->reason !== 'RECOVERY_VERIFICATION_TIMEOUT_INVALID') { throw $exception; }
            }
        }
        echo "OK: bounded encryption batches, resumable progress, missing/rebuilt TP sharekeys, corrupt-row rejection, TP-key acknowledgement and mandatory master-key proof\n";
    } finally {
        file_put_contents($keyPath, $master);
        tpRecoverySaveJob($originalJob);
    }
}

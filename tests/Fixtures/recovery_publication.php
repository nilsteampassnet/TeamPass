<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_publication.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

/** Exercise a real failed activation, then leave matching partial publication for the normal fixture to resume. */
function tpRecoveryTestPublicationRetry(string $root, string $jobId): void
{
    if (!is_file($root . '/_tools/drp-lab-allow-activation')) {
        throw new RuntimeException('Publication retry test requires an explicitly disposable checkout');
    }
    $job = tpRecoveryLoadJob($jobId);
    $manifest = $job['manifest'];
    // A missing document causes publication to fail after its retry checkpoint
    // has been committed. No test-only hook is needed in production code.
    $job['manifest']['document_entries'][] = ['kind' => 'item_attachment', 'stored_name' => 'missing-fixture.bin',
        'package_path' => 'documents/item_attachments/missing-fixture.bin', 'sha256' => hash('sha256', 'missing')];
    tpRecoverySaveJob($job);
    $process = new \Symfony\Component\Process\Process([PHP_BINARY, 'app/scripts/disaster-recovery.php', '--activate=' . $jobId], $root);
    $process->setTimeout(120);
    $process->run();
    if ($process->isSuccessful() || !str_contains($process->getErrorOutput(), 'DOCUMENT_PUBLICATION_FAILED')) {
        throw new RuntimeException('An interrupted publication did not report its failure');
    }
    $job = tpRecoveryLoadJob($jobId);
    if ($job['status'] !== 'activating' || $job['error'] !== 'DOCUMENT_PUBLICATION_FAILED'
        || is_file($root . '/app/config/settings.php')) {
        throw new RuntimeException('A failed publication lost its retryable phase or published settings early');
    }
    $directory = tpRecoveryJobDirectory($jobId);
    $config = (string) file_get_contents($directory . '/publication/settings.php');
    $csrf = (string) file_get_contents($directory . '/publication/csrfp.config.php');
    $master = (string) file_get_contents($directory . '/keys/' . $job['kit']['secure_name']);
    // Model interruption after CSRF and the original master key were already
    // published, with settings.php still absent. A conflicting key must fail
    // closed without overwriting it; correcting that conflict permits retry.
    $job['manifest'] = $manifest;
    tpRecoverySaveJob($job);
    $keyPath = $root . '/secrets/' . $job['kit']['secure_name'];
    tpRecoveryPublishFile($keyPath, 'conflicting synthetic key');
    $process->run();
    if ($process->isSuccessful() || !str_contains($process->getErrorOutput(), 'RECOVERY_PUBLICATION_CONFLICT')
        || file_get_contents($keyPath) !== 'conflicting synthetic key'
        || tpRecoveryLoadJob($jobId)['status'] !== 'activating') {
        throw new RuntimeException('A publication retry replaced a conflicting destination key');
    }
    unlink($keyPath);
    tpRecoveryPublishFile(tpRecoveryConfigDestination($root, 'csrfp.config.php'), $csrf,
        $job['publication']['previous_csrf_sha256']);
    tpRecoveryPublishFile($keyPath, $master);
    // A kill can also happen after settings.php was published but before the
    // non-sensitive receipt exists. All published bytes must be accepted as-is.
    $settingsPath = tpRecoveryConfigDestination($root, 'settings.php');
    tpRecoveryPublishFile($settingsPath, $config);
    // Partial same-job temporary writes must not strand a subsequent retry.
    file_put_contents(tpRecoveryPublicationTemporary($keyPath, $jobId), 'partial master write');
    file_put_contents(tpRecoveryPublicationTemporary($settingsPath, $jobId), 'partial settings write');
    file_put_contents(tpRecoveryPublicationTemporary(tpRecoveryConfigDestination($root, 'csrfp.config.php'), $jobId), 'partial csrf write');
    if (!hash_equals($job['publication']['settings_sha256'], hash('sha256', $config))
        || file_get_contents($directory . '/publication/settings.php') !== $config) {
        throw new RuntimeException('Publication retries regenerated the destination configuration');
    }
}

/** Verify a published receipt survives repeated activation and a remaining private cleanup fragment. */
function tpRecoveryTestPublicationReceiptRetry(string $root, string $jobId): void
{
    $directory = tpRecoveryJobDirectory($jobId);
    $settingsPath = tpRecoveryConfigDestination($root, 'settings.php');
    $before = hash_file('sha256', $settingsPath);
    $receipt = tpRecoveryLoadReceipt($jobId);
    $receipt['cleanup_pending'] = true;
    tpRecoverySaveReceipt($receipt);
    // Simulate a process interrupted while purging private staging after the
    // complete receipt was committed. The retry must purge it and keep receipt.
    tpRecoveryWritePrivate($directory . '/database.sql', 'synthetic private cleanup fragment');
    tpRecoveryWritePrivate($directory . '/job.json', json_encode(['id' => $jobId, 'status' => 'activating'], JSON_THROW_ON_ERROR));
    $discard = new \Symfony\Component\Process\Process([PHP_BINARY, 'app/scripts/disaster-recovery.php', '--discard=' . $jobId], $root);
    $discard->setTimeout(120);
    $discard->run();
    if ($discard->isSuccessful() || !str_contains($discard->getErrorOutput(), 'RECOVERY_CLEANUP_REQUIRED')
        || !is_file($directory . '/receipt.json') || !is_file($directory . '/job.json')
        || !is_file($directory . '/database.sql')) {
        throw new RuntimeException('Discard destroyed a committed publication before its cleanup could resume');
    }
    $resume = new \Symfony\Component\Process\Process([PHP_BINARY, 'app/scripts/disaster-recovery.php', '--resume=' . $jobId], $root);
    $resume->setTimeout(120);
    $resume->run();
    if ($resume->isSuccessful() || !str_contains($resume->getErrorOutput(), 'RECOVERY_CLEANUP_REQUIRED')
        || tpRecoveryLoadReceipt($jobId)['status'] !== $receipt['status']) {
        throw new RuntimeException('Service resume ignored unfinished private staging cleanup');
    }
    $process = new \Symfony\Component\Process\Process([PHP_BINARY, 'app/scripts/disaster-recovery.php', '--activate=' . $jobId], $root);
    $process->setTimeout(120);
    $process->run();
    if (!$process->isSuccessful() || is_file($directory . '/database.sql')
        || !is_file($directory . '/receipt.json') || hash_file('sha256', $settingsPath) !== $before
        || tpRecoveryLoadReceipt($jobId)['cleanup_pending'] !== false
        || tpRecoveryLoadReceipt($jobId)['status'] !== $receipt['status']) {
        throw new RuntimeException('Completed publication could not resume private cleanup idempotently');
    }
}

/** Prepare through the real CLI with the persistent configuration links used by the official image. */
function tpRecoveryTestDockerPrepare(string $root, array $input, array &$createdPaths): array
{
    if (PHP_OS_FAMILY === 'Windows' || !is_file($root . '/_tools/drp-lab-allow-activation')) {
        throw new RuntimeException('Docker publication test requires an explicitly disposable POSIX checkout');
    }
    foreach (['settings.php' => 'app/config', 'csrfp.config.php' => 'app/includes/libraries/csrfp/libs'] as $name => $directory) {
        $path = $root . '/' . $directory . '/' . $name;
        $persistent = $root . '/storage/config/' . $name;
        if (file_exists($path) || is_link($path) || file_exists($persistent) || is_link($persistent)) {
            throw new RuntimeException('Docker fixture refuses to replace existing configuration');
        }
        if (!symlink($persistent, $path)) { throw new RuntimeException('Docker fixture could not create its official configuration link'); }
        $createdPaths[] = $path;
        $createdPaths[] = $persistent;
    }
    $process = new \Symfony\Component\Process\Process([PHP_BINARY, 'app/scripts/disaster-recovery.php', '--enable'], $root);
    $process->setTimeout(120);
    $process->run();
    if (!$process->isSuccessful()) { throw new RuntimeException('Docker CLI enable failed: ' . $process->getErrorOutput()); }
    $requestPath = $root . '/_tools/docker-recovery-request-' . bin2hex(random_bytes(8)) . '.json';
    tpRecoveryWritePrivate($requestPath, json_encode($input, JSON_THROW_ON_ERROR));
    try {
        $process = new \Symfony\Component\Process\Process([PHP_BINARY, 'app/scripts/disaster-recovery.php', '--prepare=' . $requestPath], $root);
        $process->setTimeout(120);
        $process->run();
        if (!$process->isSuccessful() || preg_match('/\A(\{.*\})\s+Next:/s', $process->getOutput(), $match) !== 1) {
            throw new RuntimeException('Docker CLI preparation failed: ' . $process->getErrorOutput());
        }
        return json_decode($match[1], true, 32, JSON_THROW_ON_ERROR);
    } finally { unlink($requestPath); }
}

/** Import and verify through the same CLI used with docker exec -u nginx. */
function tpRecoveryTestDockerRun(string $root, string $jobId): array
{
    $process = new \Symfony\Component\Process\Process([PHP_BINARY, 'app/scripts/disaster-recovery.php', '--run=' . $jobId], $root);
    $process->setTimeout(120);
    $process->run();
    if (!$process->isSuccessful()) { throw new RuntimeException('Docker CLI database restoration failed: ' . $process->getErrorOutput()); }
    return tpRecoveryPublicJob(tpRecoveryLoadJob($jobId));
}

/** Confirm publication preserves the persistent links and writes the volume-backed configuration files. */
function tpRecoveryTestDockerPublication(string $root): void
{
    foreach (['settings.php' => 'app/config', 'csrfp.config.php' => 'app/includes/libraries/csrfp/libs'] as $name => $directory) {
        $path = $root . '/' . $directory . '/' . $name;
        $persistent = $root . '/storage/config/' . $name;
        if (!is_link($path) || readlink($path) !== $persistent || !is_file($persistent)
            || hash_file('sha256', $path) !== hash_file('sha256', $persistent)) {
            throw new RuntimeException('Docker publication did not preserve its volume-backed configuration');
        }
    }
}

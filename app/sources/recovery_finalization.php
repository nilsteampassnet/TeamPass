<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_finalization.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

require_once __DIR__ . '/file_permissions.functions.php';

/** Keep only non-sensitive receipt fields when persisting or exposing finalization. */
function tpRecoveryReceiptFields(array $receipt): array
{
    return array_intersect_key($receipt, array_flip([
        'id', 'status', 'error', 'plan', 'tables', 'documents', 'created_at',
        'activated_at', 'resumed_at', 'permissions', 'maintenance_enabled',
    ]));
}

/** Read the receipt without bootstrapping an installed session or disclosing credentials. */
function tpRecoveryLoadReceipt(string $id): array
{
    $path = tpRecoveryJobDirectory($id) . '/receipt.json';
    if (is_link($path) || !is_readable($path) || filesize($path) > 1048576) {
        throw new TeamPassRecoveryException('RECOVERY_RECEIPT_UNREADABLE');
    }
    $receipt = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($receipt) || ($receipt['id'] ?? '') !== $id
        || !in_array($receipt['status'] ?? '', ['complete', 'resumed'], true)) {
        throw new TeamPassRecoveryException('RECOVERY_RECEIPT_INVALID');
    }
    return tpRecoveryReceiptFields($receipt);
}

/** Save a receipt atomically; the private job directory remains writable at runtime. */
function tpRecoverySaveReceipt(array $receipt): void
{
    tpRecoveryWritePrivate(tpRecoveryJobDirectory($receipt['id']) . '/receipt.json',
        json_encode(tpRecoveryReceiptFields($receipt), JSON_THROW_ON_ERROR));
}

/**
 * Acquire the same operation lock used by restore and publication.
 * @return resource
 */
function tpRecoveryFinalizationLock()
{
    $lock = tpBackupOpenProtectedFile(tpRecoveryJobDirectory() . '/operation.lock', 'c+b', true);
    if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) { fclose($lock); }
        throw new TeamPassRecoveryException('RECOVERY_ALREADY_RUNNING');
    }
    return $lock;
}

/** Summarize the real integrity permission scan and reuse its exact remediation model. */
function tpRecoveryPermissionReview(string $root, array $report, int $checkedAt): array
{
    $supported = !empty($report['scan_supported']) && !empty($report['has_result'])
        && ($report['identity']['source'] ?? '') === 'current_process';
    $issues = $supported ? max(0, (int) ($report['counts']['issues'] ?? 0)) : null;
    $commands = $supported ? tpFilePermissionsRemediationCommands($root, $report) : [];
    $status = !$supported ? 'manual' : ($issues === 0 ? 'passed' : 'issues');
    return ['status' => $status, 'checked_at' => $checkedAt, 'issues' => $issues,
        'scan_supported' => $supported, 'commands' => $commands,
        'remediation_supported' => $supported && !empty($report['remediation_supported'])];
}

/** Audit only permissions, not release hashes, and propose commands without executing them. */
function tpRecoveryCheckPermissions(string $id): array
{
    $lock = tpRecoveryFinalizationLock();
    try {
        $receipt = tpRecoveryLoadReceipt($id);
        $report = tpFilePermissionsScan(TEAMPASS_ROOT, null, null, true);
        $receipt['permissions'] = tpRecoveryPermissionReview(TEAMPASS_ROOT, $report, time());
        tpRecoverySaveReceipt($receipt);
        return $receipt;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Build the final administrator-only cleanup command for the real installer directory. */
function tpRecoveryInstallerCleanupCommand(string $root, string $os): ?string
{
    $realRoot = realpath($root);
    if ($realRoot === false || $realRoot === dirname($realRoot)) { return null; }
    $installer = $realRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'install';
    if (is_link($installer) || realpath($installer) !== $installer || !is_dir($installer)) { return null; }
    $quoted = tpRecoveryCommandArgument($installer, $os);
    return $os === 'Windows' ? 'Remove-Item -LiteralPath ' . $quoted . ' -Recurse -Force'
        : 'sudo rm -rf -- ' . $quoted;
}

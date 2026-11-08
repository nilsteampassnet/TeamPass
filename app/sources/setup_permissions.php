<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file setup_permissions.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

require_once __DIR__ . '/file_permissions.functions.php';
require_once __DIR__ . '/runtime_files.functions.php';

/** Summarize a permission audit without treating an unavailable audit as success. */
function tpSetupPermissionsReview(string $root, array $report, int $checkedAt): array
{
    $supported = !empty($report['scan_supported']) && !empty($report['has_result'])
        && ($report['identity']['source'] ?? '') === 'current_process';
    $manualReason = (string) ($report['manual_reason'] ?? '');
    $issues = $supported ? max(0, (int) ($report['counts']['issues'] ?? 0)) : null;
    $manual = !$supported || $manualReason !== '';
    $blockingIssues = $manual && $supported ? array_filter($report['issues'] ?? [],
        static fn (array $issue): bool => $manualReason !== 'container'
            || !in_array($issue['reason'] ?? '', ['protected_writable', 'protected_web_owned', 'secret_writable', 'secret_web_owned'], true)) : [];
    return [
        'status' => $blockingIssues !== [] ? 'issues' : ($manual ? 'manual' : ($issues === 0 ? 'passed' : 'issues')),
        'checked_at' => $checkedAt, 'issues' => $issues, 'scan_supported' => $supported,
        'remediation_supported' => !$manual && !empty($report['remediation_supported']),
        'commands' => !$manual ? tpFilePermissionsRemediationCommands($root, $report) : [],
        'manual_reason' => $manualReason !== '' ? $manualReason : ($supported ? '' : 'unsupported_audit'),
        'identity' => array_intersect_key($report['identity'] ?? [], array_flip(['web_user', 'web_group', 'source'])),
        'findings' => array_slice($report['issues'] ?? [], 0, 30),
    ];
}

/** Audit current rights only; never hash application files or apply corrections. */
function tpSetupPermissionsAudit(string $root): array
{
    clearstatcache();
    $report = tpFilePermissionsScan($root, null, null, true);
    // The official container owns code as nginx and persists configuration via
    // links. Host ownership commands must not rewrite that deployment contract.
    if (is_file('/.dockerenv') || is_file('/run/.containerenv')) {
        $report['manual_reason'] = 'container';
    } else {
        foreach (['app', 'public', 'app/config', 'app/config/settings.php',
            'app/includes/libraries/csrfp/libs', 'secrets'] as $path) {
            if (tpFilePermissionsPathHasSymlink($root, $path)) {
                $report['manual_reason'] = 'linked_layout';
                break;
            }
        }
    }
    return tpSetupPermissionsReview($root, $report, time());
}

/** Refuse known findings; a manual review requires an explicit operator acknowledgement. */
function tpSetupPermissionsApprove(array $review, bool $acknowledgeManual): array
{
    if (($review['status'] ?? '') === 'passed' && ($review['issues'] ?? null) === 0
        && !empty($review['scan_supported'])) {
        return $review;
    }
    if (($review['status'] ?? '') === 'manual' && $acknowledgeManual) {
        $review['manual_acknowledged_at'] = time();
        return $review;
    }
    throw new RuntimeException(($review['status'] ?? '') === 'manual'
        ? 'PERMISSIONS_MANUAL_ACK_REQUIRED' : 'PERMISSIONS_REVIEW_REQUIRED');
}

/** Persist a non-sensitive setup review; failure must not silently authorize completion. */
function tpSetupPermissionsRecord(string $root, string $flow, array $review): void
{
    $directory = $root . '/storage/logs';
    if (is_link($root . '/storage') || is_link($directory) || !is_dir($directory)) {
        throw new RuntimeException('PERMISSIONS_AUDIT_LOG_UNAVAILABLE');
    }
    $entry = ['flow' => $flow, 'recorded_at' => time(), 'status' => $review['status'],
        'checked_at' => $review['checked_at'], 'issues' => $review['issues'],
        'identity' => $review['identity'] ?? [], 'manual_reason' => $review['manual_reason'] ?? '',
        'manual_acknowledged_at' => $review['manual_acknowledged_at'] ?? null];
    if (!tpAppendRuntimeFile($directory . '/setup-permissions.jsonl', json_encode($entry, JSON_THROW_ON_ERROR) . "\n")) {
        throw new RuntimeException('PERMISSIONS_AUDIT_LOG_UNAVAILABLE');
    }
}

/**
 * Serialize finalizations while the caller also holds the shared recovery lock.
 *
 * @param string $root Application root with an initialized recovery directory.
 *
 * @return resource Exclusive lock; the caller must unlock and close it.
 * @throws RuntimeException When the lock is unavailable, unsafe or already held.
 */
function tpSetupPermissionsFinalizationLock(string $root)
{
    $storage = $root . '/storage';
    $directory = $storage . '/recovery';
    if (is_link($storage) || is_link($directory) || !is_dir($directory)) {
        throw new RuntimeException('SETUP_OPERATION_BUSY');
    }
    $lock = tpOpenRuntimeFile($directory . '/setup-finalization.lock', true);
    if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        throw new RuntimeException('SETUP_OPERATION_BUSY');
    }
    return $lock;
}

/** Build a quoted cleanup command for the real installer, without deleting anything. */
function tpSetupPermissionsCleanupCommand(string $root, string $os, string $manualReason = ''): ?string
{
    if ($manualReason === 'container') {
        return 'docker compose restart teampass';
    }
    $realRoot = realpath($root);
    if ($realRoot === false || $realRoot === dirname($realRoot)) { return null; }
    $installer = $realRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'install';
    if (is_link($installer) || realpath($installer) !== $installer || !is_dir($installer)) { return null; }
    $quoted = $os === 'Windows' ? "'" . str_replace("'", "''", $installer) . "'"
        : "'" . str_replace("'", "'\\''", $installer) . "'";
    return $os === 'Windows' ? 'Remove-Item -LiteralPath ' . $quoted . ' -Recurse -Force'
        : 'sudo rm -rf -- ' . $quoted;
}

/** Authorize only the completed setup session and its own finalization CSRF token. */
function tpSetupPermissionsRequestAllowed(array $session, string $flow, ?string $token, bool $ownsSettings): bool
{
    return in_array($flow, ['install', 'upgrade'], true)
        && ($session['setup_permissions_ready'] ?? '') === $flow
        && is_string($session['setup_permissions_csrf'] ?? null)
        && $token !== null && $token !== '' && hash_equals($session['setup_permissions_csrf'], $token)
        && ($flow === 'install' ? $ownsSettings : ($session['user_granted'] ?? '') === '1');
}

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

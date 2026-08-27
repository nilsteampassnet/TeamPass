<?php

declare(strict_types=1);

/**
 * TeamPass file integrity CLI.
 *
 * This command is read-only with respect to application code. It can scan and
 * persist a report or print an SSH cleanup plan, but it never removes or moves
 * files itself.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = realpath(__DIR__ . '/../..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve the TeamPass root.\n");
    exit(1);
}

require_once $root . '/app/sources/file_integrity.functions.php';

$options = getopt('', ['status', 'json', 'cleanup-plan', 'permissions-plan', 'no-save', 'help']);
if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php app/scripts/file_integrity.php [--json] [--no-save]\n";
    echo "  php app/scripts/file_integrity.php --status [--json]\n";
    echo "  php app/scripts/file_integrity.php --cleanup-plan\n";
    echo "  php app/scripts/file_integrity.php --permissions-plan\n";
    exit(0);
}

try {
    if (isset($options['status']) || isset($options['cleanup-plan']) || isset($options['permissions-plan'])) {
        $report = tpFileIntegrityLoadReport($root);
        if ((bool) ($report['has_result'] ?? false) === false) {
            throw new RuntimeException('No file integrity report is available. Run a scan first.');
        }
    } else {
        $report = tpFileIntegrityScan($root, $root . '/app/files_reference.txt');
        if (isset($options['no-save']) === false) {
            tpFileIntegritySaveReport($root, $report);
        }
    }

    $summary = tpFileIntegritySummary($report);

    if (isset($options['cleanup-plan'])) {
        echo implode(PHP_EOL, tpFileIntegrityCleanupPlan($root, $report)) . PHP_EOL;
        exit($summary['status'] === 'success' ? 0 : 2);
    }

    if (isset($options['permissions-plan'])) {
        $permissionReport = is_array($report['permissions'] ?? null) ? $report['permissions'] : [];
        $commands = tpFilePermissionsRemediationCommands($root, $permissionReport);
        if ($commands === []) {
            echo "No permission remediation command is available for this report.\n";
        } else {
            echo implode(PHP_EOL, $commands) . PHP_EOL;
        }
        exit($summary['status'] === 'success' ? 0 : 2);
    }

    if (isset($options['json'])) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
        exit($summary['status'] === 'success' ? 0 : 2);
    }

    $counts = $summary['counts'];
    echo 'Status: ' . $summary['status'] . PHP_EOL;
    echo 'Scan ID: ' . $summary['scan_id'] . PHP_EOL;
    echo 'Reference entries: ' . strval($counts['reference_entries']) . PHP_EOL;
    echo 'Checked: ' . strval($counts['checked']) . PHP_EOL;
    echo 'Modified: ' . strval($counts['modified']) . PHP_EOL;
    echo 'Missing: ' . strval($counts['missing']) . PHP_EOL;
    echo 'Unknown: ' . strval($counts['unknown']) . PHP_EOL;
    echo 'Legacy layout: ' . strval($counts['legacy']) . PHP_EOL;
    echo 'Development dependencies: ' . strval($counts['development']) . PHP_EOL;
    echo 'Warnings: ' . strval($counts['warnings']) . PHP_EOL;
    echo 'Permission entries checked: ' . strval($counts['permissions_checked']) . PHP_EOL;
    echo 'Permission findings: ' . strval($counts['permission_issues']) . PHP_EOL;
    echo 'Duration: ' . strval($summary['duration_ms']) . ' ms' . PHP_EOL;
    exit($summary['status'] === 'success' ? 0 : 2);
} catch (Throwable $exception) {
    fwrite(STDERR, 'File integrity scan failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

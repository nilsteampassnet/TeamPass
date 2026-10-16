<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      backup_protection_probe.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

namespace BackupProtectionProbe;

// Exercise the real helper with controlled POSIX filesystem responses, on all OSes.
const PHP_OS_FAMILY = 'Linux';
require_once __DIR__ . '/../../app/sources/backup.functions.php';

$scenario = $argv[2];
$path = $argv[1];
$existing = $argv[3] === 'existing';
$reportedMode = 0755;
$statCalls = 0;
$identityCalls = 0;
$chmodCalls = 0;
$warnings = [];
if ($existing) {
    \file_put_contents($path, 'keep existing metadata');
}

/** Return real stream identity with a controlled filesystem permission mode. */
function fstat($handle): array|false
{
    global $scenario, $reportedMode, $statCalls;
    $statCalls++;
    if ($scenario === 'stat_failure' && $statCalls > 1) {
        return false;
    }
    $stat = \fstat($handle);
    $stat['mode'] = ($stat['mode'] & ~07777) | $reportedMode;
    return $stat;
}

/** Simulate a mount which ignores chmod, refuses it, or only partially applies it. */
function chmod(string $path, int $mode): bool
{
    global $scenario, $reportedMode, $chmodCalls;
    $chmodCalls++;
    if ($scenario === 'chmod_false') {
        return false;
    }
    if ($scenario === 'partial') {
        $reportedMode = 0750;
    } elseif ($scenario !== 'noop') {
        $reportedMode = $mode;
    }
    return true;
}

/** Simulate disabled chmod independently of the host's PHP configuration. */
function function_exists(string $name): bool
{
    global $scenario;
    return $name === 'chmod' && $scenario === 'disabled' ? false : \function_exists($name);
}

/** Keep the real identity checks, or simulate an observed target replacement. */
function tpRuntimeFileMatchesPath(string $path, array $stat): bool
{
    global $scenario, $identityCalls;
    $identityCalls++;
    if ($scenario === 'replacement' && $identityCalls > 1) {
        \file_put_contents($path, 'replacement contents');
        return false;
    }
    return \tpRuntimeFileMatchesPath($path, $stat);
}

/** Capture diagnostics without writing filesystem paths to the test runner output. */
function error_log(string $message): bool
{
    global $warnings;
    $warnings[] = $message;
    return true;
}

$reflection = new \ReflectionFunction('tpBackupOpenProtectedFile');
$lines = \file($reflection->getFileName());
$source = \implode('', \array_slice($lines, $reflection->getStartLine() - 1,
    $reflection->getEndLine() - $reflection->getStartLine() + 1));
eval('namespace ' . __NAMESPACE__ . ';' . $source);

$handle = tpBackupOpenProtectedFile($path, $existing ? 'c+b' : 'x+b', true);
$accepted = \is_resource($handle);
if ($accepted) {
    \fclose($handle);
}
\clearstatcache(true, $path);
echo \json_encode([
    'accepted' => $accepted,
    'exists' => \file_exists($path),
    'contents' => \is_file($path) ? \file_get_contents($path) : null,
    'warnings' => $warnings,
    'chmod_calls' => $chmodCalls,
], JSON_THROW_ON_ERROR);

<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * Decision logic of the command-line upgrade, kept free of any database, session or file
 * access so it can be unit-tested on its own — same pattern as licence_trial_logic.php.
 *
 * It is included by both:
 *   - app/sources/upgrade_cli.functions.php   (database, files and subprocesses)
 *   - tests/Unit/UpgradeCliLogicTest.php      (unit tests)
 *
 * @file      upgrade_cli_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Oldest database version the command line upgrades. Below it the manifest interleaves
 * operations that read their parameters from POST; the web wizard handles those databases.
 */
const TP_UPGRADE_CLI_FLOOR = '3.2.2';

const TP_UPGRADE_CLI_EXIT_OK = 0;
const TP_UPGRADE_CLI_EXIT_USAGE = 2;
const TP_UPGRADE_CLI_EXIT_AVAILABLE = 10;
const TP_UPGRADE_CLI_EXIT_BLOCKED = 20;
const TP_UPGRADE_CLI_EXIT_BACKUP_FAILED = 30;
const TP_UPGRADE_CLI_EXIT_REVERTED = 40;
const TP_UPGRADE_CLI_EXIT_STOPPED = 41;
const TP_UPGRADE_CLI_EXIT_REVERT_FAILED = 50;
const TP_UPGRADE_CLI_EXIT_LOCKED = 60;

/**
 * Operations of upgrade_operations.php that can run from the command line: single-pass, no
 * nb/start input. Must match the allow-list of upgrade_operations.php itself.
 */
const TP_UPGRADE_CLI_CLI_OPERATIONS = [
    'sanitize_legacy_fa_icons',
    'sanitize_directory_user_fields',
    'sanitize_imported_folder_titles',
];

/**
 * Schema level of the releases that recorded teampass_version_full but not yet
 * teampass_schema_level. Older releases recorded neither: their schema level is unknown.
 */
const TP_UPGRADE_CLI_LEGACY_SCHEMA_LEVELS = [
    '3.2.3.3' => '1792993330',
];

/** Upper bound of calls to an operation answering "call again" (finish 0). */
const TP_UPGRADE_CLI_MAX_OPERATION_LOOPS = 10000;

/**
 * Parse a dotted numeric version.
 *
 * @param string $version Version such as "3.2.3" or "3.2.3.4"
 *
 * @return list<int>|null Components, or null when the string is not a version
 */
function tpUpgradeCliParseVersion(string $version): ?array
{
    if (preg_match('/^\d+(\.\d+){0,3}$/', $version) !== 1) {
        return null;
    }

    return array_map('intval', explode('.', $version));
}

/**
 * Compare two versions numerically, component by component (3.2.10 > 3.2.9).
 *
 * @param string $a First version
 * @param string $b Second version
 *
 * @return int Negative, zero or positive, like strcmp()
 */
function tpUpgradeCliCompareVersions(string $a, string $b): int
{
    $left = array_pad(tpUpgradeCliParseVersion($a) ?? [0], 4, 0);
    $right = array_pad(tpUpgradeCliParseVersion($b) ?? [0], 4, 0);

    return $left <=> $right;
}

/**
 * Version handled by an upgrade script.
 *
 * @param string $scriptName File name such as upgrade_run_3.2.3.php
 *
 * @return string|null "3.2.3", or null for helpers (upgrade_run_3.0.0_users.php) and X.Y scripts
 */
function tpUpgradeCliScriptVersion(string $scriptName): ?string
{
    if (preg_match('/^upgrade_run_(\d+\.\d+\.\d+)\.php$/', $scriptName, $matches) !== 1) {
        return null;
    }

    return $matches[1];
}

/**
 * Decide what the command line must do.
 *
 * "Up to date" needs the version AND the schema timestamp: every 3.2.x script writes
 * upgrade_timestamp when it ends, so a chain stopped halfway can show a fresh timestamp.
 *
 * @param array{db_version: string, db_version_full: string, db_schema_level: string, upgrade_timestamp: int,
 *              files_version: string, files_version_full: string, min_date: int} $state Current state
 * @param string $floor Oldest database version accepted
 *
 * @return array{decision: string, reason: string} decision: up_to_date, record_release_only, upgrade or blocked
 */
function tpUpgradeCliDecide(array $state, string $floor = TP_UPGRADE_CLI_FLOOR): array
{
    $dbVersion = $state['db_version'];
    if (tpUpgradeCliParseVersion($dbVersion) === null) {
        return ['decision' => 'blocked', 'reason' => 'DB_VERSION_UNREADABLE'];
    }

    $comparison = tpUpgradeCliCompareVersions($dbVersion, $state['files_version']);
    if ($comparison > 0) {
        return ['decision' => 'blocked', 'reason' => 'DOWNGRADE'];
    }
    if (tpUpgradeCliCompareVersions($dbVersion, $floor) < 0) {
        return ['decision' => 'blocked', 'reason' => 'BELOW_FLOOR'];
    }
    if ($comparison < 0) {
        return ['decision' => 'upgrade', 'reason' => 'VERSION_BEHIND'];
    }
    if ($state['upgrade_timestamp'] < $state['min_date']) {
        return ['decision' => 'upgrade', 'reason' => 'SCHEMA_BEHIND'];
    }
    if ($state['db_version_full'] !== $state['files_version_full']
        || $state['db_schema_level'] !== (string) $state['min_date']
    ) {
        // A patch release without schema change: only the release record is stale.
        return ['decision' => 'record_release_only', 'reason' => 'RELEASE_RECORD_STALE'];
    }

    return ['decision' => 'up_to_date', 'reason' => ''];
}

/**
 * Ordered steps to run, taken from the shared manifest.
 *
 * Keeps every upgrade_run_X.Y.Z.php between the database version (its own script is replayed:
 * patch releases append to it) and the files version, plus the operations that follow a kept
 * script. A kept operation that cannot run from the command line is a release mistake.
 *
 * @param list<array{0: string, 1: string}> $manifest     Content of public/install/upgrade_manifest.php
 * @param string                            $dbVersion    teampass_version recorded in the database
 * @param string                            $filesVersion TP_VERSION of the deployed files
 * @param string                            $floor        Oldest version accepted
 *
 * @return list<array{kind: string, file: string, name: string, version: string}>
 *
 * @throws RuntimeException When a kept operation is not runnable from the command line
 */
function tpUpgradeCliBuildPlan(array $manifest, string $dbVersion, string $filesVersion, string $floor = TP_UPGRADE_CLI_FLOOR): array
{
    $plan = [];
    $currentVersion = '';
    $keep = false;

    foreach ($manifest as $entry) {
        $file = (string) $entry[0];
        $parameter = (string) $entry[1];

        if ($file === 'upgrade_operations.php') {
            if ($keep === true) {
                if (in_array($parameter, TP_UPGRADE_CLI_CLI_OPERATIONS, true) === false) {
                    throw new RuntimeException('Operation ' . $parameter . ' cannot run from the command line');
                }
                $plan[] = ['kind' => 'operation', 'file' => $file, 'name' => $parameter, 'version' => $currentVersion];
            }
            continue;
        }

        $version = tpUpgradeCliScriptVersion($file);
        $keep = $version !== null
            && tpUpgradeCliCompareVersions($version, $floor) >= 0
            && tpUpgradeCliCompareVersions($version, $dbVersion) >= 0
            && tpUpgradeCliCompareVersions($version, $filesVersion) <= 0;

        if ($keep === true) {
            $currentVersion = (string) $version;
            $plan[] = ['kind' => 'script', 'file' => $file, 'name' => $file, 'version' => $currentVersion];
        }
    }

    return $plan;
}

/**
 * Read the result of an upgrade script or operation.
 *
 * The scripts always exit 0 and report a failure in their JSON only. A script may also print
 * PHP warnings before the JSON, and some errors embed addslashes() escapes that are not JSON.
 *
 * @param string $stdout   Standard output of the subprocess
 * @param int    $exitCode Exit code of the subprocess
 *
 * @return array{ok: bool, finish: string, error: string, next: string}
 */
function tpUpgradeCliParseStepOutput(string $stdout, int $exitCode): array
{
    $result = ['ok' => false, 'finish' => '', 'error' => '', 'next' => ''];

    $position = strrpos($stdout, '[{');
    $candidate = $position === false ? '' : trim(substr($stdout, $position));
    $decoded = $candidate === '' ? null : json_decode($candidate, true);

    if (is_array($decoded) === true && isset($decoded[0]) === true && is_array($decoded[0]) === true) {
        $result['finish'] = (string) ($decoded[0]['finish'] ?? '');
        $result['error'] = trim((string) ($decoded[0]['error'] ?? ''));
        $result['next'] = (string) ($decoded[0]['next'] ?? '');
    } elseif ($candidate !== '' && preg_match('/"error"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $candidate, $matches) === 1) {
        $result['error'] = trim(stripslashes($matches[1]));
        if (preg_match('/"finish"\s*:\s*"?(\d)/', $candidate, $finish) === 1) {
            $result['finish'] = $finish[1];
        }
        if ($result['error'] === '') {
            $result['error'] = 'Unreadable output';
        }
    } else {
        $result['error'] = 'No result returned';
    }

    if ($exitCode !== 0) {
        $result['error'] = trim('Exit code ' . $exitCode . '. ' . $result['error']);
    }

    $result['ok'] = $exitCode === 0 && $result['error'] === '' && $result['finish'] === '1';

    return $result;
}

/**
 * Schema level of the code that last upgraded the database.
 *
 * @param string $recordedSchemaLevel teampass_schema_level recorded in the database
 * @param string $versionFull         teampass_version_full recorded in the database
 *
 * @return string The level, or '' when unknown
 */
function tpUpgradeCliResolveSchemaLevel(string $recordedSchemaLevel, string $versionFull): string
{
    if (preg_match('/^\d+$/', $recordedSchemaLevel) === 1) {
        return $recordedSchemaLevel;
    }

    return TP_UPGRADE_CLI_LEGACY_SCHEMA_LEVELS[$versionFull] ?? '';
}

/**
 * Free space the backup needs: the plain and the encrypted temporary dumps coexist.
 *
 * @param int $dataBytes Sum of data_length of the dumped tables
 *
 * @return int Bytes
 */
function tpUpgradeCliRequiredFreeBytes(int $dataBytes): int
{
    return max(10 * 1024 * 1024, (int) ceil($dataBytes * 2.5));
}

/**
 * Tables to drop after a restore: created by the failed migration, so absent from the dump.
 *
 * @param list<string>       $tablesNow Tables of the database after the restore
 * @param array<string, int> $inventory Prefixed tables before the upgrade => row count
 * @param string             $prefix    DB_PREFIX
 *
 * @return list<string>
 */
function tpUpgradeCliTablesToDrop(array $tablesNow, array $inventory, string $prefix): array
{
    $drop = [];
    foreach ($tablesNow as $table) {
        $table = (string) $table;
        if (str_starts_with($table, $prefix) === true
            && array_key_exists($table, $inventory) === false
            && preg_match('/^[A-Za-z0-9_]+$/', $table) === 1
        ) {
            $drop[] = $table;
        }
    }

    return $drop;
}

/**
 * Row-count differences between the pre-upgrade inventory and the restored database.
 *
 * @param array<string, int> $expected Inventory taken before the backup
 * @param array<string, int> $actual   Inventory after the restore
 *
 * @return array<string, array{0: int, 1: int|null}> table => [expected, actual] (null = missing)
 */
function tpUpgradeCliInventoryDiff(array $expected, array $actual): array
{
    $diff = [];
    foreach ($expected as $table => $count) {
        if (array_key_exists($table, $actual) === false) {
            $diff[$table] = [$count, null];
        } elseif ($actual[$table] !== $count) {
            $diff[$table] = [$count, $actual[$table]];
        }
    }

    return $diff;
}

/**
 * Tables the installer creates, extracted from public/install/install-steps/run.step5.php.
 *
 * @param string $installerSource Source of run.step5.php
 *
 * @return list<string> Table names without prefix
 */
function tpUpgradeCliInstallerTables(string $installerSource): array
{
    preg_match_all(
        '/CREATE TABLE(?: IF NOT EXISTS)?\s*`?[\'"]\s*\.\s*\$this->inputData\[\'tablePrefix\'\]\s*\.\s*[\'"]([a-z0-9_]+)/',
        $installerSource,
        $matches
    );

    return array_values(array_unique($matches[1]));
}

/**
 * Whether a journal may move from one status to another.
 *
 * A status may follow itself for "running" and "reverting": a resumed or retried run keeps it.
 *
 * @param string $from Current status ('' for a new journal)
 * @param string $to   Next status
 *
 * @return bool
 */
function tpUpgradeCliCanTransition(string $from, string $to): bool
{
    $allowed = [
        '' => ['running'],
        'running' => ['running', 'completed', 'failed_resumable', 'reverting', 'aborted'],
        'failed_resumable' => ['running', 'reverting'],
        'reverting' => ['reverting', 'reverted', 'revert_failed'],
        'revert_failed' => ['reverting'],
        'completed' => ['reverting'],
    ];

    return in_array($to, $allowed[$from] ?? [], true);
}

/**
 * Identifier of a run: sortable date plus randomness.
 *
 * @param int    $now       Unix timestamp
 * @param string $randomHex 8 lowercase hexadecimal characters
 *
 * @return string Such as 20261029T143000-ab12cd34
 */
function tpUpgradeCliNewRunId(int $now, string $randomHex): string
{
    return gmdate('Ymd\THis', $now) . '-' . $randomHex;
}

/**
 * Whether a string is a run identifier (it becomes part of a file name).
 *
 * @param string $runId Candidate
 *
 * @return bool
 */
function tpUpgradeCliIsValidRunId(string $runId): bool
{
    return preg_match('/^\d{8}T\d{6}-[a-f0-9]{8}$/', $runId) === 1;
}

/**
 * Exit code matching the final status of a journal.
 *
 * @param string $status Journal status
 *
 * @return int
 */
function tpUpgradeCliExitCodeForStatus(string $status): int
{
    return [
        'completed' => TP_UPGRADE_CLI_EXIT_OK,
        'failed_resumable' => TP_UPGRADE_CLI_EXIT_STOPPED,
        'reverted' => TP_UPGRADE_CLI_EXIT_REVERTED,
        'revert_failed' => TP_UPGRADE_CLI_EXIT_REVERT_FAILED,
        'aborted' => TP_UPGRADE_CLI_EXIT_BACKUP_FAILED,
    ][$status] ?? TP_UPGRADE_CLI_EXIT_LOCKED;
}

/**
 * What the administrator must do once the database has been reverted: the files deployed are
 * still the new release, and the command line never touches them.
 *
 * @param string $fromFull   Release the database is back to ('' when unknown)
 * @param bool   $gitCheckout Whether the installation is a git working copy
 *
 * @return list<string> Lines of instructions
 */
function tpUpgradeCliRevertNextAction(string $fromFull, bool $gitCheckout): array
{
    $release = $fromFull !== '' ? $fromFull : 'the previous release';
    $lines = ['1. Restore the code of ' . $release . ':'];
    if ($gitCheckout === true && $fromFull !== '') {
        $lines[] = '   git checkout ' . $fromFull . '   (git deployment)';
        $lines[] = '   or redeploy the ' . $fromFull . ' archive.';
    } else {
        $lines[] = '   redeploy its files (from your git repository or from the release archive).';
    }
    $lines[] = '2. Sign in as an administrator and disable the maintenance mode.';

    return $lines;
}

/**
 * Parse the command line: a command first, then options (getopt() stops at the command).
 *
 * @param list<string> $arguments $argv without the script name
 *
 * @return array{command: string, run_id: string, options: array<string, string|bool>, error: string}
 */
function tpUpgradeCliParseArguments(array $arguments): array
{
    $allowed = [
        'check' => ['json', 'notify'],
        'run' => ['yes', 'on-failure', 'wait-tasks', 'no-email', 'debug'],
        'resume' => ['yes', 'on-failure', 'wait-tasks', 'no-email', 'debug'],
        'revert' => ['yes', 'wait-tasks', 'no-email', 'debug'],
        'status' => ['json'],
        'help' => [],
    ];
    $parsed = ['command' => 'help', 'run_id' => '', 'options' => [], 'error' => ''];
    $positional = [];

    foreach ($arguments as $argument) {
        if (in_array($argument, ['-h', '--help'], true)) {
            $parsed['command'] = 'help';
            return $parsed;
        }
        if (str_starts_with($argument, '--') === true) {
            [$name, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, true);
            $parsed['options'][(string) $name] = $value;
        } else {
            $positional[] = $argument;
        }
    }

    $parsed['command'] = $positional[0] ?? 'help';
    if (array_key_exists($parsed['command'], $allowed) === false) {
        $parsed['error'] = 'Unknown command: ' . $parsed['command'];
        return $parsed;
    }
    foreach (array_keys($parsed['options']) as $name) {
        if (in_array($name, $allowed[$parsed['command']], true) === false) {
            $parsed['error'] = 'Unknown option for ' . $parsed['command'] . ': --' . $name;
            return $parsed;
        }
    }

    $parsed['run_id'] = $positional[1] ?? '';
    if (in_array($parsed['command'], ['resume', 'revert'], true) && tpUpgradeCliIsValidRunId($parsed['run_id']) === false) {
        $parsed['error'] = 'A run identifier is required (see: status)';
    } elseif ($parsed['run_id'] !== '' && tpUpgradeCliIsValidRunId($parsed['run_id']) === false) {
        $parsed['error'] = 'Invalid run identifier: ' . $parsed['run_id'];
    } elseif (count($positional) > 2 || ($parsed['run_id'] !== '' && in_array($parsed['command'], ['resume', 'revert', 'status'], true) === false)) {
        $parsed['error'] = 'Unexpected argument';
    }

    $onFailure = $parsed['options']['on-failure'] ?? 'revert';
    if (in_array($onFailure, ['revert', 'stop'], true) === false) {
        $parsed['error'] = '--on-failure must be revert or stop';
    }
    $waitTasks = $parsed['options']['wait-tasks'] ?? '600';
    if (is_string($waitTasks) === false || preg_match('/^\d{1,5}$/', $waitTasks) !== 1) {
        $parsed['error'] = '--wait-tasks must be a number of seconds';
    }

    return $parsed;
}

/**
 * Human-readable size.
 *
 * @param int $bytes Size in bytes
 *
 * @return string
 */
function tpUpgradeCliFormatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $size = (float) max(0, $bytes);
    $unit = 0;
    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }

    return ($unit === 0 ? (string) (int) $size : number_format($size, 1, '.', '')) . ' ' . $units[$unit];
}

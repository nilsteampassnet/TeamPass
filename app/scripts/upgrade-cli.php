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
 * Command-line upgrade, for an administrator in a shell or a cron job, once the new files are
 * deployed (git pull, archive...). It checks whether the database can be upgraded, then puts
 * TeamPass in maintenance, disconnects every user, makes and verifies a backup, runs the upgrade
 * scripts and, if one fails, restores that backup. Every operation is logged under
 * storage/upgrade/logs; each run keeps a journal under storage/upgrade/runs.
 *
 * Run it as the PHP service account: sudo -u www-data php app/scripts/upgrade-cli.php help
 *
 * @file      upgrade-cli.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\ConfigManager\ConfigManager;

if (PHP_SAPI !== 'cli') {
    exit(2);
}

require_once __DIR__ . '/../config/include.php';
require_once __DIR__ . '/../sources/upgrade_cli.functions.php';

set_time_limit(0);

/**
 * Help text.
 *
 * @return string
 */
function upgradeCliUsage(): string
{
    return <<<'TXT'
Usage: php app/scripts/upgrade-cli.php <command> [options]

Run it as the PHP service account, for example: sudo -u www-data php app/scripts/upgrade-cli.php check

Commands:
  check   [--json] [--notify]
      Tell whether the deployed files can upgrade the database. Changes nothing.
      --notify e-mails the administrators once per new release waiting to be applied.
  run     [--yes] [--on-failure=revert|stop] [--wait-tasks=600] [--no-email] [--debug]
      Upgrade: maintenance mode, users disconnected, verified backup, upgrade scripts.
      On failure the backup is restored (revert, default) or the run stops resumable (stop).
  resume  <run-id> [--yes] [--on-failure=revert|stop] [--wait-tasks=600] [--no-email] [--debug]
      Continue a stopped or interrupted run from its failed step.
  revert  <run-id> [--yes] [--wait-tasks=600] [--no-email] [--debug]
      Restore the pre-upgrade backup of a run.
  status  [<run-id>] [--json]
      Show the journal of a run (the latest by default).

--yes is required when the command does not run in a terminal (cron).

Exit codes:
  0  up to date / completed      30 backup failed, nothing changed
  2  usage error or cancelled     40 failed, database reverted (maintenance on)
  10 upgrade available (check)    41 failed or interrupted, resumable (maintenance on)
  20 blocked by a check           50 revert failed (maintenance on)
                                  60 another run is active or unfinished

TXT;
}

/**
 * Whether a person can answer questions.
 *
 * @return bool
 */
function upgradeCliInteractive(): bool
{
    return function_exists('stream_isatty') === true && @stream_isatty(STDIN) === true;
}

/**
 * Ask a yes/no question on the terminal.
 *
 * @param string $question Question
 * @param bool   $default  Answer when the operator just presses Enter
 *
 * @return bool
 */
function upgradeCliAsk(string $question, bool $default): bool
{
    fwrite(STDOUT, $question . ($default === true ? ' [Y/n] ' : ' [y/N] '));
    $answer = fgets(STDIN);
    if ($answer === false) {
        return $default;
    }
    $answer = strtolower(trim($answer));

    return $answer === '' ? $default : in_array($answer, ['y', 'yes'], true);
}

/**
 * Get the operator's go-ahead: --yes, or a question when a terminal is attached.
 *
 * @param array<string, string|bool> $options  Options
 * @param TpUpgradeCliLogger         $logger   Logger
 * @param string                     $question Question
 *
 * @return bool
 */
function upgradeCliConfirm(array $options, TpUpgradeCliLogger $logger, string $question): bool
{
    if (empty($options['yes']) === false) {
        return true;
    }
    if (upgradeCliInteractive() === false) {
        $logger->error('confirm', '--yes is required when the command does not run in a terminal');
        return false;
    }
    $confirmed = upgradeCliAsk($question, false);
    if ($confirmed === false) {
        $logger->info('confirm', 'Cancelled by the operator');
    }

    return $confirmed;
}

/**
 * Take the lock that keeps two command-line upgrades apart.
 *
 * @param array{lock: string} $paths Paths
 *
 * @return BackgroundTaskLock|null
 */
function upgradeCliLockRuns(array $paths): ?BackgroundTaskLock
{
    $lock = new BackgroundTaskLock($paths['lock']);

    return $lock->acquire() === true ? $lock : null;
}

/**
 * Open the log of a run, resume or revert.
 *
 * @param array{logs: string} $paths   Paths
 * @param string              $runId   Run identifier
 * @param array<string, string|bool> $options Options
 *
 * @return TpUpgradeCliLogger|null
 */
function upgradeCliOpenRunLog(array $paths, string $runId, array $options): ?TpUpgradeCliLogger
{
    $logger = new TpUpgradeCliLogger(empty($options['debug']) === false, true);
    if ($logger->open($paths['logs'] . '/upgrade_' . date('Ymd_His') . '_' . $runId . '.log') === false) {
        fwrite(STDERR, 'ERROR: cannot write the log in ' . $paths['logs'] . PHP_EOL);
        return null;
    }

    return $logger;
}

/**
 * Release before the upgrade, for messages.
 *
 * @param array<string, mixed> $journal Journal
 *
 * @return string
 */
function upgradeCliFromLabel(array $journal): string
{
    return (string) ($journal['from']['version_full'] !== '' ? $journal['from']['version_full'] : $journal['from']['version']);
}

/**
 * Evaluate the instance: decision, plan and checks.
 *
 * @param array $SETTINGS Settings
 *
 * @return array<string, mixed> status, exit_code, reason, message, from, to, plan, checks, maintenance_mode, run_id
 */
function upgradeCliEvaluate(array $SETTINGS): array
{
    $db = tpUpgradeCliReadDbState();
    $decision = tpUpgradeCliDecide(tpUpgradeCliDecisionState($db));
    $report = [
        'status' => '',
        'exit_code' => TP_UPGRADE_CLI_EXIT_OK,
        'reason' => $decision['reason'],
        'message' => '',
        'from' => [
            'version' => $db['db_version'],
            'version_full' => $db['db_version_full'],
            'upgrade_timestamp' => $db['upgrade_timestamp'],
            'schema_level' => tpUpgradeCliResolveSchemaLevel($db['db_schema_level'], $db['db_version_full']),
        ],
        'to' => [
            'version' => (string) TP_VERSION,
            'version_full' => TP_VERSION . '.' . TP_VERSION_MINOR,
            'schema_level' => (string) UPGRADE_MIN_DATE,
        ],
        'plan' => [],
        'checks' => [],
        'maintenance_mode' => $db['maintenance_mode'],
        'run_id' => '',
    ];

    $unfinished = tpUpgradeCliFindUnfinishedRun();
    if ($unfinished !== null) {
        $report['status'] = 'unfinished_run';
        $report['exit_code'] = TP_UPGRADE_CLI_EXIT_LOCKED;
        $report['run_id'] = (string) $unfinished['run_id'];
        $report['message'] = 'Run ' . $unfinished['run_id'] . ' is ' . $unfinished['status']
            . ': resume or revert it first (php app/scripts/upgrade-cli.php status ' . $unfinished['run_id'] . ')';
        return $report;
    }

    switch ($decision['decision']) {
        case 'up_to_date':
            $report['status'] = 'up_to_date';
            $report['message'] = 'TeamPass is up to date';
            return $report;
        case 'record_release_only':
            $report['status'] = 'release_record_stale';
            $report['message'] = 'The schema is up to date; "run" records the release ' . $report['to']['version_full'];
            return $report;
        case 'blocked':
            $report['status'] = 'blocked';
            $report['exit_code'] = TP_UPGRADE_CLI_EXIT_BLOCKED;
            $report['message'] = [
                'DB_VERSION_UNREADABLE' => 'The version recorded in the database cannot be read',
                'DOWNGRADE' => 'The database is newer than the deployed files',
                'BELOW_FLOOR' => 'The database is older than ' . TP_UPGRADE_CLI_FLOOR . ': upgrade it with the web wizard (/install/upgrade.php)',
            ][$decision['reason']] ?? $decision['reason'];
            return $report;
    }

    try {
        $report['plan'] = tpUpgradeCliBuildPlan(
            require dirname(__DIR__, 2) . '/public/install/upgrade_manifest.php',
            $db['db_version'],
            (string) TP_VERSION
        );
    } catch (RuntimeException $exception) {
        $report['status'] = 'blocked';
        $report['exit_code'] = TP_UPGRADE_CLI_EXIT_BLOCKED;
        $report['message'] = $exception->getMessage() . ': upgrade with the web wizard (/install/upgrade.php)';
        return $report;
    }
    if ($report['plan'] === []) {
        $report['status'] = 'blocked';
        $report['exit_code'] = TP_UPGRADE_CLI_EXIT_BLOCKED;
        $report['message'] = 'No upgrade script found between ' . $db['db_version'] . ' and ' . TP_VERSION;
        return $report;
    }

    $report['checks'] = tpUpgradeCliPreflight($report['plan'], $SETTINGS, (string) $report['from']['schema_level']);
    $errors = array_filter($report['checks'], static fn (array $check): bool => $check['status'] === 'error');
    $report['status'] = $errors === [] ? 'upgrade_available' : 'blocked';
    $report['exit_code'] = $errors === [] ? TP_UPGRADE_CLI_EXIT_AVAILABLE : TP_UPGRADE_CLI_EXIT_BLOCKED;
    $report['message'] = $errors === [] ? 'Upgrade available' : 'Upgrade blocked by ' . count($errors) . ' check(s)';

    return $report;
}

/**
 * Print an evaluation for a person.
 *
 * @param array<string, mixed> $report Evaluation
 * @param TpUpgradeCliLogger   $logger Logger echoing to the terminal
 */
function upgradeCliPrintReport(array $report, TpUpgradeCliLogger $logger): void
{
    $from = $report['from']['version'] . ($report['from']['version_full'] !== '' ? ' (release ' . $report['from']['version_full'] . ')' : '');
    $logger->info('check', 'Database : ' . ($from !== '' ? $from : 'unknown'));
    $logger->info('check', 'Files    : ' . $report['to']['version_full']);
    if ($report['plan'] !== []) {
        $logger->info('check', 'Steps    : ' . implode(', ', array_map(static fn (array $step): string => $step['name'], $report['plan'])));
    }
    foreach ($report['checks'] as $check) {
        $line = str_pad('[' . $check['status'] . ']', 10) . str_pad($check['id'], 20) . $check['message'];
        $level = ['error' => 'ERROR', 'warning' => 'WARN'][$check['status']] ?? 'INFO';
        $logger->log($level, 'check', $line);
    }
    $logger->log($report['exit_code'] === TP_UPGRADE_CLI_EXIT_BLOCKED || $report['exit_code'] === TP_UPGRADE_CLI_EXIT_LOCKED ? 'ERROR' : 'INFO', 'check', (string) $report['message']);
}

/**
 * Trace an outcome in the administration logs and e-mail it, without ever failing the run.
 *
 * @param string                     $status    Audit status (completed, stopped, reverted, revert_failed, aborted, release_recorded)
 * @param array<string, mixed>       $journal   Journal
 * @param array                      $SETTINGS  Settings
 * @param TpUpgradeCliLogger         $logger    Logger
 * @param array<string, string|bool> $options   Options
 * @param string                     $message   What happened
 * @param list<string>               $nextAction What the administrator must do
 */
function upgradeCliReport(string $status, array $journal, array $SETTINGS, TpUpgradeCliLogger $logger, array $options, string $message, array $nextAction): void
{
    try {
        tpUpgradeCliAuditLog($status, upgradeCliFromLabel($journal), (string) $journal['to']['version_full'], (string) $journal['run_id'], $SETTINGS);
    } catch (Throwable $exception) {
        $logger->warn('notify', 'The administration log could not be written');
    }
    foreach ($nextAction as $line) {
        $logger->info('next', $line);
    }
    if (empty($options['no-email']) === false) {
        return;
    }
    try {
        tpUpgradeCliNotify(
            strtoupper(str_replace('_', ' ', $status)),
            [
                'from' => upgradeCliFromLabel($journal),
                'to' => $journal['to']['version_full'],
                'message' => $message,
                'backup' => (string) ($journal['backup']['file'] ?? ''),
                'log' => $logger->path(),
                'next_action' => $nextAction,
            ],
            $SETTINGS,
            $logger
        );
    } catch (Throwable $exception) {
        $logger->warn('notify', 'E-mail failed: ' . $exception->getMessage());
    }
}

/**
 * Command "check".
 *
 * @param array<string, string|bool> $options  Options
 * @param array                      $SETTINGS Settings
 *
 * @return int Exit code
 */
function upgradeCliCheck(array $options, array $SETTINGS): int
{
    $paths = tpUpgradeCliPaths();
    $json = empty($options['json']) === false;
    $report = upgradeCliEvaluate($SETTINGS);

    $fileLog = new TpUpgradeCliLogger(false, false);
    $fileLog->open($paths['logs'] . '/check.log');
    $fileLog->info('check', 'status=' . $report['status'] . ' from=' . ($report['from']['version_full'] ?: $report['from']['version'])
        . ' to=' . $report['to']['version_full'] . ' exit=' . $report['exit_code']);

    if ($json === true) {
        fwrite(STDOUT, (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    } else {
        upgradeCliPrintReport($report, new TpUpgradeCliLogger(false, true));
    }

    if (empty($options['notify']) === false && $report['exit_code'] === TP_UPGRADE_CLI_EXIT_AVAILABLE) {
        $notified = json_decode((string) @file_get_contents($paths['notified']), true);
        $notified = is_array($notified) === true ? $notified : [];
        $target = (string) $report['to']['version_full'];
        if (isset($notified[$target]) === false) {
            $sent = tpUpgradeCliNotify(
                'PENDING',
                [
                    'from' => $report['from']['version_full'] ?: $report['from']['version'],
                    'to' => $target,
                    'message' => 'New files are deployed but the database has not been upgraded yet.',
                    'next_action' => ['Run as the PHP service account: php app/scripts/upgrade-cli.php run'],
                ],
                $SETTINGS,
                $fileLog
            );
            if ($sent > 0) {
                $notified[$target] = time();
                @file_put_contents($paths['notified'], (string) json_encode($notified), LOCK_EX);
                @chmod($paths['notified'], 0600);
            }
        }
    }

    return (int) $report['exit_code'];
}

/**
 * Command "run".
 *
 * @param array<string, string|bool> $options  Options
 * @param array                      $SETTINGS Settings
 *
 * @return int Exit code
 */
function upgradeCliRun(array $options, array &$SETTINGS): int
{
    $paths = tpUpgradeCliPaths();
    $runLock = upgradeCliLockRuns($paths);
    if ($runLock === null) {
        fwrite(STDERR, 'ERROR: another command-line upgrade is running' . PHP_EOL);
        return TP_UPGRADE_CLI_EXIT_LOCKED;
    }
    $runId = tpUpgradeCliNewRunId(time(), bin2hex(random_bytes(4)));
    $logger = upgradeCliOpenRunLog($paths, $runId, $options);
    if ($logger === null) {
        return TP_UPGRADE_CLI_EXIT_BLOCKED;
    }
    tpUpgradeCliInstallSignalHandlers();
    $logger->info('check', 'TeamPass command-line upgrade, run ' . $runId . ' - log ' . $logger->path());
    upgradeCliCloseInterruptedQuiesce($logger);

    $report = upgradeCliEvaluate($SETTINGS);
    upgradeCliPrintReport($report, $logger);
    if ($report['status'] === 'up_to_date') {
        return TP_UPGRADE_CLI_EXIT_OK;
    }
    if ($report['status'] === 'release_record_stale') {
        if (tpUpgradeCliRecordVersion((string) TP_VERSION) === false) {
            $logger->error('record', 'The release could not be recorded');
            return TP_UPGRADE_CLI_EXIT_BLOCKED;
        }
        $logger->info('record', 'Release ' . $report['to']['version_full'] . ' recorded');
        try {
            tpUpgradeCliAuditLog('release_recorded', (string) ($report['from']['version_full'] ?: $report['from']['version']), (string) $report['to']['version_full'], $runId, $SETTINGS);
        } catch (Throwable $exception) {
            $logger->warn('record', 'The administration log could not be written');
        }
        return TP_UPGRADE_CLI_EXIT_OK;
    }
    if ($report['status'] !== 'upgrade_available') {
        return (int) $report['exit_code'];
    }

    if (upgradeCliConfirm($options, $logger, 'Users will be disconnected and TeamPass placed in maintenance. Continue?') === false) {
        return TP_UPGRADE_CLI_EXIT_USAGE;
    }

    $journal = [
        'run_id' => $runId,
        'status' => 'running',
        'phase' => 'quiesce',
        'started_at' => time(),
        'updated_at' => time(),
        'finished_at' => 0,
        'on_failure' => (string) ($options['on-failure'] ?? 'revert'),
        'from' => $report['from'],
        'to' => $report['to'],
        'maintenance_before' => (string) $report['maintenance_mode'],
        'backup' => ['file' => '', 'sha256' => '', 'size_bytes' => 0, 'created_at' => 0],
        'inventory' => [],
        'steps' => array_map(
            static fn (array $step): array => $step + ['status' => 'pending', 'duration_ms' => 0, 'error' => ''],
            $report['plan']
        ),
        'warnings' => [],
        'error' => ['phase' => '', 'step' => '', 'message' => ''],
        'revert' => ['restore_exit_code' => 0, 'dropped_tables' => [], 'warnings' => []],
        'log_file' => $logger->path(),
    ];
    tpUpgradeCliJournalSave($journal);

    try {
        // Quiesce: no background task, maintenance visible to the web server, nobody connected.
        $tasksLock = tpUpgradeCliAcquireTasksLock((int) ($options['wait-tasks'] ?? 600), $logger);
        if ($tasksLock === null) {
            return upgradeCliAbort($journal, $SETTINGS, $logger, $options, 'quiesce', 'The background tasks handler is still running', TP_UPGRADE_CLI_EXIT_BLOCKED);
        }
        if ($journal['maintenance_before'] !== '1') {
            tpUpgradeCliSetMaintenance('1');
            // The web server caches the settings for up to 60 s (ConfigManager APCu).
            $logger->info('quiesce', 'Maintenance mode on; waiting 60 s for the web server to see it...');
            for ($second = 0; $second < 60; $second++) {
                if (tpUpgradeCliStopRequested() === true) {
                    return upgradeCliAbort($journal, $SETTINGS, $logger, $options, 'quiesce', 'Interrupted by the operator', TP_UPGRADE_CLI_EXIT_USAGE);
                }
                sleep(1);
            }
        } else {
            $logger->info('quiesce', 'Maintenance mode already on');
        }
        $disconnected = tpUpgradeCliDisconnectAll();
        $logger->info('quiesce', 'Disconnected ' . $disconnected['web'] . ' web user(s) and ' . $disconnected['api'] . ' API client(s)');

        // Backup, then prove it can be restored.
        $journal['phase'] = 'backup';
        $journal['inventory'] = tpUpgradeCliTableInventory((string) DB_PREFIX);
        tpUpgradeCliJournalSave($journal);
        $logger->info('backup', 'Backing up ' . count($journal['inventory']) . ' tables...');
        $backup = tpUpgradeCliCreateBackup($SETTINGS, $journal);
        if ($backup['success'] === false) {
            return upgradeCliAbort($journal, $SETTINGS, $logger, $options, 'backup', 'Backup failed: ' . $backup['message'], TP_UPGRADE_CLI_EXIT_BACKUP_FAILED);
        }
        $journal['backup'] = ['file' => $backup['file'], 'sha256' => $backup['sha256'], 'size_bytes' => $backup['size_bytes'], 'created_at' => time()];
        tpUpgradeCliJournalSave($journal);
        $logger->info('backup', 'Backup created: ' . $backup['file'] . ' (' . tpUpgradeCliFormatBytes($backup['size_bytes']) . ')');

        $verification = tpUpgradeCliVerifyBackup($backup['file'], $journal['inventory'], $SETTINGS);
        if ($verification['success'] === false) {
            return upgradeCliAbort($journal, $SETTINGS, $logger, $options, 'backup', 'Backup verification failed: ' . $verification['message'], TP_UPGRADE_CLI_EXIT_BACKUP_FAILED);
        }
        $logger->info('backup', 'Backup verified: ' . $verification['message']);
        if ($report['from']['schema_level'] === '') {
            $logger->warn('backup', 'Schema level of the data unknown: Administration > Backups will refuse this file; the command line can still revert');
        }
        if (tpUpgradeCliStopRequested() === true) {
            return upgradeCliAbort($journal, $SETTINGS, $logger, $options, 'backup', 'Interrupted by the operator', TP_UPGRADE_CLI_EXIT_USAGE);
        }
    } catch (Throwable $exception) {
        // Nothing in the schema changed yet: give the instance back as it was.
        return upgradeCliAbort($journal, $SETTINGS, $logger, $options, (string) $journal['phase'], 'Unexpected error: ' . $exception->getMessage(), TP_UPGRADE_CLI_EXIT_BACKUP_FAILED);
    }

    $exitCode = upgradeCliMigrateSafely($journal, $options, $SETTINGS, $logger);
    $tasksLock->release();
    $runLock->release();

    return $exitCode;
}

/**
 * Run upgradeCliMigrate(); an unexpected error is a failed step like any other.
 *
 * @param array<string, mixed>       $journal  Journal
 * @param array<string, string|bool> $options  Options
 * @param array                      $SETTINGS Settings
 * @param TpUpgradeCliLogger         $logger   Logger
 *
 * @return int Exit code
 */
function upgradeCliMigrateSafely(array &$journal, array $options, array &$SETTINGS, TpUpgradeCliLogger $logger): int
{
    try {
        return upgradeCliMigrate($journal, $options, $SETTINGS, $logger);
    } catch (Throwable $exception) {
        return upgradeCliFail($journal, $options, $SETTINGS, $logger, (string) $journal['phase'], '', 'Unexpected error: ' . $exception->getMessage(), false);
    }
}

/**
 * Close a run that died before changing the schema (quiesce or backup), restoring the
 * maintenance mode it found. Called with the run lock held, so no live process owns it.
 *
 * @param TpUpgradeCliLogger $logger Logger
 */
function upgradeCliCloseInterruptedQuiesce(TpUpgradeCliLogger $logger): void
{
    $journal = tpUpgradeCliFindUnfinishedRun();
    if ($journal === null || $journal['status'] !== 'running' || in_array($journal['phase'], ['quiesce', 'backup'], true) === false) {
        return;
    }
    tpUpgradeCliSetMaintenance((string) $journal['maintenance_before']);
    $journal['status'] = 'aborted';
    $journal['error'] = ['phase' => $journal['phase'], 'step' => '', 'message' => 'Interrupted before any schema change'];
    $journal['finished_at'] = time();
    tpUpgradeCliJournalSave($journal);
    $logger->warn('check', 'Run ' . $journal['run_id'] . ' was interrupted before any schema change: closed, maintenance mode restored');
}

/**
 * Stop before any schema change: restore the maintenance mode, nothing else was changed.
 *
 * @param array<string, mixed>       $journal  Journal
 * @param array                      $SETTINGS Settings
 * @param TpUpgradeCliLogger         $logger   Logger
 * @param array<string, string|bool> $options  Options
 * @param string                     $phase    Phase
 * @param string                     $message  Reason
 * @param int                        $exitCode Exit code
 *
 * @return int
 */
function upgradeCliAbort(array &$journal, array $SETTINGS, TpUpgradeCliLogger $logger, array $options, string $phase, string $message, int $exitCode): int
{
    $logger->error($phase, $message);
    try {
        tpUpgradeCliSetMaintenance((string) $journal['maintenance_before']);
    } catch (Throwable $exception) {
        $logger->warn($phase, 'The maintenance mode could not be restored: disable it in the settings');
    }
    $journal['status'] = 'aborted';
    $journal['error'] = ['phase' => $phase, 'step' => '', 'message' => $message];
    $journal['finished_at'] = time();
    tpUpgradeCliJournalSave($journal);
    upgradeCliReport('aborted', $journal, $SETTINGS, $logger, $options, $message, ['Nothing was changed. Fix the cause, then run the upgrade again.']);

    return $exitCode;
}

/**
 * Run the remaining steps, verify, and leave maintenance.
 *
 * @param array<string, mixed>       $journal  Journal
 * @param array<string, string|bool> $options  Options
 * @param array                      $SETTINGS Settings
 * @param TpUpgradeCliLogger         $logger   Logger
 *
 * @return int Exit code
 */
function upgradeCliMigrate(array &$journal, array $options, array &$SETTINGS, TpUpgradeCliLogger $logger): int
{
    $journal['phase'] = 'migrate';
    tpUpgradeCliJournalSave($journal);
    $total = count($journal['steps']);

    foreach ($journal['steps'] as $index => $step) {
        if ($step['status'] === 'done') {
            continue;
        }
        if (tpUpgradeCliStopRequested() === true) {
            return upgradeCliFail($journal, $options, $SETTINGS, $logger, 'migrate', $step['name'], 'Interrupted by the operator', true);
        }

        $logger->info('migrate', '[' . ($index + 1) . '/' . $total . '] ' . $step['name'] . '...');
        $journal['steps'][$index]['status'] = 'running';
        tpUpgradeCliJournalSave($journal);

        $result = tpUpgradeCliRunStep($step, $logger);
        $journal['steps'][$index]['duration_ms'] = $result['duration_ms'];
        if ($result['ok'] === true && $step['kind'] === 'script' && tpUpgradeCliRecordVersion($step['version']) === false) {
            $result = ['ok' => false, 'error' => 'Version ' . $step['version'] . ' could not be recorded', 'duration_ms' => $result['duration_ms']];
        }
        if ($result['ok'] === false) {
            $journal['steps'][$index]['status'] = 'failed';
            $journal['steps'][$index]['error'] = $result['error'];
            tpUpgradeCliJournalSave($journal);
            return upgradeCliFail($journal, $options, $SETTINGS, $logger, 'migrate', $step['name'], $result['error'], false);
        }

        $journal['steps'][$index]['status'] = 'done';
        $journal['steps'][$index]['error'] = '';
        tpUpgradeCliJournalSave($journal);
        $logger->info('migrate', '    done in ' . round($result['duration_ms'] / 1000, 1) . ' s');
    }

    // Verify. Recording TP_VERSION again is idempotent and covers a release without its own script.
    $journal['phase'] = 'verify';
    tpUpgradeCliJournalSave($journal);
    if (tpUpgradeCliRecordVersion((string) TP_VERSION) === false) {
        return upgradeCliFail($journal, $options, $SETTINGS, $logger, 'verify', '', 'Version ' . TP_VERSION . ' could not be recorded', false);
    }
    ConfigManager::invalidateCache();
    $SETTINGS = (new ConfigManager())->getAllSettings();
    $db = tpUpgradeCliReadDbState();
    $problems = [];
    if ($db['db_version'] !== (string) TP_VERSION) {
        $problems[] = 'teampass_version is "' . $db['db_version'] . '"';
    }
    if ($db['db_version_full'] !== $journal['to']['version_full']) {
        $problems[] = 'teampass_version_full is "' . $db['db_version_full'] . '"';
    }
    if (upgradeRequired() === true) {
        $problems[] = 'upgrade_timestamp is below UPGRADE_MIN_DATE';
    }
    if ($problems !== []) {
        return upgradeCliFail($journal, $options, $SETTINGS, $logger, 'verify', '', 'Verification failed: ' . implode('; ', $problems), false);
    }

    $installer = (string) @file_get_contents(dirname(__DIR__, 2) . '/public/install/install-steps/run.step5.php');
    $expected = array_map(static fn (string $table): string => DB_PREFIX . $table, tpUpgradeCliInstallerTables($installer));
    $missing = array_values(array_diff($expected, tpUpgradeCliListTables((string) DB_PREFIX)));
    if ($missing !== []) {
        $journal['warnings'][] = 'Tables created by the installer but absent: ' . implode(', ', $missing);
        $logger->warn('verify', 'Tables created by the installer but absent: ' . implode(', ', $missing));
    }

    // Leave maintenance as it was before the run.
    tpUpgradeCliSetMaintenance((string) $journal['maintenance_before']);
    $journal['status'] = 'completed';
    $journal['phase'] = 'done';
    $journal['finished_at'] = time();
    tpUpgradeCliJournalSave($journal);

    $message = 'Upgrade ' . upgradeCliFromLabel($journal) . ' to ' . $journal['to']['version_full'] . ' completed in '
        . ($journal['finished_at'] - (int) $journal['started_at']) . ' s';
    $logger->info('done', $message);
    $nextAction = [];
    if ((int) ($SETTINGS['websocket_enabled'] ?? 0) === 1) {
        $nextAction[] = 'Restart the WebSocket daemon.';
    }
    $nextAction[] = 'Make sure HTTP access to public/install/ is blocked (docs/install/upgrade.md, Step 5b).';
    upgradeCliReport('completed', $journal, $SETTINGS, $logger, $options, $message, $nextAction);

    return TP_UPGRADE_CLI_EXIT_OK;
}

/**
 * A step or the verification failed: stop resumable, or revert.
 *
 * @param array<string, mixed>       $journal   Journal
 * @param array<string, string|bool> $options   Options
 * @param array                      $SETTINGS  Settings
 * @param TpUpgradeCliLogger         $logger    Logger
 * @param string                     $phase     Phase
 * @param string                     $step      Step name ('' for the verification)
 * @param string                     $message   Error
 * @param bool                       $resumable Interrupted by the operator: never revert
 *
 * @return int Exit code
 */
function upgradeCliFail(array &$journal, array $options, array &$SETTINGS, TpUpgradeCliLogger $logger, string $phase, string $step, string $message, bool $resumable): int
{
    $logger->error($phase, ($step !== '' ? $step . ': ' : '') . $message);
    $journal['error'] = ['phase' => $phase, 'step' => $step, 'message' => $message];

    $mode = (string) $journal['on_failure'];
    if ($resumable === false && upgradeCliInteractive() === true) {
        $mode = upgradeCliAsk('Revert the database to the pre-upgrade backup now?', $mode === 'revert') === true ? 'revert' : 'stop';
    }
    if ($resumable === true || $mode === 'stop') {
        $journal['status'] = 'failed_resumable';
        tpUpgradeCliJournalSave($journal);
        upgradeCliReport('stopped', $journal, $SETTINGS, $logger, $options, ($step !== '' ? $step . ': ' : '') . $message, [
            'TeamPass stays in maintenance.',
            'Fix the cause (see the log), then: php app/scripts/upgrade-cli.php resume ' . $journal['run_id'],
            'Or restore the pre-upgrade backup: php app/scripts/upgrade-cli.php revert ' . $journal['run_id'],
        ]);
        return TP_UPGRADE_CLI_EXIT_STOPPED;
    }

    return upgradeCliRevertRun($journal, $options, $SETTINGS, $logger);
}

/**
 * Restore the pre-upgrade backup of a run and report it.
 *
 * @param array<string, mixed>       $journal  Journal
 * @param array<string, string|bool> $options  Options
 * @param array                      $SETTINGS Settings
 * @param TpUpgradeCliLogger         $logger   Logger
 *
 * @return int Exit code
 */
function upgradeCliRevertRun(array &$journal, array $options, array &$SETTINGS, TpUpgradeCliLogger $logger): int
{
    $journal['status'] = 'reverting';
    $journal['phase'] = 'revert';
    tpUpgradeCliJournalSave($journal);

    try {
        $result = tpUpgradeCliRevert($journal, $SETTINGS, $logger);
    } catch (Throwable $exception) {
        $result = ['success' => false, 'warnings' => [], 'dropped' => [], 'restore_exit_code' => 0, 'message' => 'Unexpected error: ' . $exception->getMessage()];
    }
    $journal['revert'] = ['restore_exit_code' => $result['restore_exit_code'], 'dropped_tables' => $result['dropped'], 'warnings' => $result['warnings']];
    $journal['finished_at'] = time();
    $from = upgradeCliFromLabel($journal);
    $backupFile = (string) $journal['backup']['file'];

    if ($result['success'] === false) {
        $journal['status'] = 'revert_failed';
        tpUpgradeCliJournalSave($journal);
        $logger->error('revert', 'Revert failed: ' . $result['message']);
        upgradeCliReport('revert_failed', $journal, $SETTINGS, $logger, $options, 'Revert failed: ' . $result['message'], [
            'TeamPass stays in maintenance.',
            'Retry: php app/scripts/upgrade-cli.php revert ' . $journal['run_id'],
            'Pre-upgrade backup: ' . $backupFile,
        ]);
        return TP_UPGRADE_CLI_EXIT_REVERT_FAILED;
    }

    $journal['status'] = 'reverted';
    tpUpgradeCliJournalSave($journal);
    foreach ($result['dropped'] as $table) {
        $logger->info('revert', 'Dropped ' . $table . ', created by the failed upgrade');
    }
    foreach ($result['warnings'] as $warning) {
        $logger->warn('revert', $warning);
    }
    $logger->info('revert', 'The database is back to ' . $from);
    upgradeCliReport(
        'reverted',
        $journal,
        $SETTINGS,
        $logger,
        $options,
        'The upgrade failed (' . $journal['error']['message'] . ') and the database was restored to ' . $from . '.',
        array_merge(
            ['The files are still ' . $journal['to']['version_full'] . ' and TeamPass stays in maintenance.'],
            tpUpgradeCliRevertNextAction((string) $journal['from']['version_full'], is_dir(dirname(__DIR__, 2) . '/.git')),
            ['Pre-upgrade backup: ' . $backupFile]
        )
    );

    return TP_UPGRADE_CLI_EXIT_REVERTED;
}

/**
 * Load a run for resume/revert and check its backup is still the one made by the run.
 *
 * @param string             $runId   Run identifier
 * @param list<string>       $allowed Statuses accepted
 * @param TpUpgradeCliLogger $logger  Logger
 * @param int                $code    Exit code to return on refusal (by reference)
 *
 * @return array<string, mixed>|null
 */
function upgradeCliLoadRun(string $runId, array $allowed, TpUpgradeCliLogger $logger, int &$code): ?array
{
    $journal = tpUpgradeCliJournalLoad($runId);
    if ($journal === null) {
        $logger->error('load', 'Unknown run ' . $runId);
        $code = TP_UPGRADE_CLI_EXIT_USAGE;
        return null;
    }
    if (in_array($journal['status'], $allowed, true) === false) {
        $logger->error('load', 'Run ' . $runId . ' is ' . $journal['status'] . ': nothing to do');
        $code = TP_UPGRADE_CLI_EXIT_USAGE;
        return null;
    }
    $file = (string) $journal['backup']['file'];
    if ($file === '' || is_file($file) === false || tpBackupFileSha256($file) !== $journal['backup']['sha256']) {
        $logger->error('load', 'The pre-upgrade backup of run ' . $runId . ' is missing or modified: ' . ($file !== '' ? $file : 'none'));
        $code = TP_UPGRADE_CLI_EXIT_BACKUP_FAILED;
        return null;
    }

    return $journal;
}

/**
 * Command "resume".
 *
 * @param string                     $runId    Run identifier
 * @param array<string, string|bool> $options  Options
 * @param array                      $SETTINGS Settings
 *
 * @return int Exit code
 */
function upgradeCliResume(string $runId, array $options, array &$SETTINGS): int
{
    $paths = tpUpgradeCliPaths();
    $runLock = upgradeCliLockRuns($paths);
    if ($runLock === null) {
        fwrite(STDERR, 'ERROR: another command-line upgrade is running' . PHP_EOL);
        return TP_UPGRADE_CLI_EXIT_LOCKED;
    }
    $logger = upgradeCliOpenRunLog($paths, $runId, $options);
    if ($logger === null) {
        return TP_UPGRADE_CLI_EXIT_BLOCKED;
    }
    $code = TP_UPGRADE_CLI_EXIT_OK;
    // A "running" journal whose lock we hold belongs to a process that died.
    $journal = upgradeCliLoadRun($runId, ['failed_resumable', 'running'], $logger, $code);
    if ($journal === null) {
        return $code;
    }
    if ((string) TP_VERSION . '.' . TP_VERSION_MINOR !== $journal['to']['version_full']) {
        $logger->error('load', 'The deployed files changed since run ' . $runId . ' (' . $journal['to']['version_full'] . '): revert it instead');
        return TP_UPGRADE_CLI_EXIT_USAGE;
    }
    $logger->info('load', 'Resuming run ' . $runId . ' (' . upgradeCliFromLabel($journal) . ' to ' . $journal['to']['version_full'] . ')');
    if (upgradeCliConfirm($options, $logger, 'Resume the upgrade from its failed step?') === false) {
        return TP_UPGRADE_CLI_EXIT_USAGE;
    }
    tpUpgradeCliInstallSignalHandlers();
    if (isset($options['on-failure']) === true) {
        $journal['on_failure'] = (string) $options['on-failure'];
    }

    $tasksLock = tpUpgradeCliAcquireTasksLock((int) ($options['wait-tasks'] ?? 600), $logger);
    if ($tasksLock === null) {
        $logger->error('quiesce', 'The background tasks handler is still running');
        return TP_UPGRADE_CLI_EXIT_BLOCKED;
    }
    tpUpgradeCliSetMaintenance('1');
    tpUpgradeCliDisconnectAll();
    $journal['status'] = 'running';
    $journal['error'] = ['phase' => '', 'step' => '', 'message' => ''];
    $journal['log_file'] = $logger->path();

    $exitCode = upgradeCliMigrateSafely($journal, $options, $SETTINGS, $logger);
    $tasksLock->release();
    $runLock->release();

    return $exitCode;
}

/**
 * Command "revert".
 *
 * @param string                     $runId    Run identifier
 * @param array<string, string|bool> $options  Options
 * @param array                      $SETTINGS Settings
 *
 * @return int Exit code
 */
function upgradeCliRevertCommand(string $runId, array $options, array &$SETTINGS): int
{
    $paths = tpUpgradeCliPaths();
    $runLock = upgradeCliLockRuns($paths);
    if ($runLock === null) {
        fwrite(STDERR, 'ERROR: another command-line upgrade is running' . PHP_EOL);
        return TP_UPGRADE_CLI_EXIT_LOCKED;
    }
    $logger = upgradeCliOpenRunLog($paths, $runId, $options);
    if ($logger === null) {
        return TP_UPGRADE_CLI_EXIT_BLOCKED;
    }
    $code = TP_UPGRADE_CLI_EXIT_OK;
    $journal = upgradeCliLoadRun($runId, ['failed_resumable', 'running', 'reverting', 'revert_failed', 'completed'], $logger, $code);
    if ($journal === null) {
        return $code;
    }
    if ($journal['status'] === 'completed') {
        $logger->warn('load', 'Run ' . $runId . ' completed on ' . date('Y-m-d H:i', (int) $journal['finished_at'])
            . ': everything written in TeamPass since then will be lost');
    }
    $logger->info('load', 'Restoring the pre-upgrade backup of run ' . $runId . ': ' . $journal['backup']['file']);
    if (upgradeCliConfirm($options, $logger, 'Restore this backup now?') === false) {
        return TP_UPGRADE_CLI_EXIT_USAGE;
    }
    tpUpgradeCliInstallSignalHandlers();

    $tasksLock = tpUpgradeCliAcquireTasksLock((int) ($options['wait-tasks'] ?? 600), $logger);
    if ($tasksLock === null) {
        $logger->error('quiesce', 'The background tasks handler is still running');
        return TP_UPGRADE_CLI_EXIT_BLOCKED;
    }
    tpUpgradeCliSetMaintenance('1');
    $journal['log_file'] = $logger->path();

    $exitCode = upgradeCliRevertRun($journal, $options, $SETTINGS, $logger);
    $tasksLock->release();
    $runLock->release();

    return $exitCode;
}

/**
 * Command "status".
 *
 * @param string                     $runId   Run identifier ('' for the latest)
 * @param array<string, string|bool> $options Options
 *
 * @return int Exit code
 */
function upgradeCliStatus(string $runId, array $options): int
{
    $paths = tpUpgradeCliPaths();
    $runId = $runId !== '' ? $runId : (tpUpgradeCliRunIds()[0] ?? '');
    if ($runId === '') {
        fwrite(STDOUT, 'No command-line upgrade recorded.' . PHP_EOL);
        return TP_UPGRADE_CLI_EXIT_OK;
    }
    $journal = tpUpgradeCliJournalLoad($runId);
    if ($journal === null) {
        fwrite(STDERR, 'ERROR: unknown run ' . $runId . PHP_EOL);
        return TP_UPGRADE_CLI_EXIT_USAGE;
    }

    // An active status whose lock is free belongs to a process that died.
    $journal['interrupted'] = false;
    if (in_array($journal['status'], ['running', 'reverting'], true) === true) {
        $lock = upgradeCliLockRuns($paths);
        if ($lock !== null) {
            $journal['interrupted'] = true;
            $lock->release();
        }
    }

    if (empty($options['json']) === false) {
        fwrite(STDOUT, (string) json_encode($journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        return TP_UPGRADE_CLI_EXIT_OK;
    }

    $lines = [
        'Run      : ' . $journal['run_id'],
        'Status   : ' . $journal['status'] . ($journal['interrupted'] === true ? ' (interrupted)' : ''),
        'Upgrade  : ' . upgradeCliFromLabel($journal) . ' to ' . $journal['to']['version_full'],
        'Started  : ' . date('Y-m-d H:i:s', (int) $journal['started_at'])
            . ((int) $journal['finished_at'] > 0 ? ', finished ' . date('Y-m-d H:i:s', (int) $journal['finished_at']) : ''),
        'Backup   : ' . ($journal['backup']['file'] !== '' ? $journal['backup']['file'] : 'none'),
        'Log      : ' . $journal['log_file'],
        'Steps    :',
    ];
    foreach ($journal['steps'] as $step) {
        $lines[] = '  ' . str_pad('[' . $step['status'] . ']', 11) . $step['name'] . ($step['error'] !== '' ? ' - ' . $step['error'] : '');
    }
    if ($journal['error']['message'] !== '') {
        $lines[] = 'Error    : ' . $journal['error']['message'];
    }
    fwrite(STDOUT, implode(PHP_EOL, $lines) . PHP_EOL);

    return TP_UPGRADE_CLI_EXIT_OK;
}

// ---------------------------------------------------------------------------------------------

$parsed = tpUpgradeCliParseArguments(array_slice($argv, 1));
if ($parsed['error'] !== '') {
    fwrite(STDERR, 'ERROR: ' . $parsed['error'] . PHP_EOL . PHP_EOL . upgradeCliUsage());
    exit(TP_UPGRADE_CLI_EXIT_USAGE);
}
if ($parsed['command'] === 'help') {
    fwrite(STDOUT, upgradeCliUsage());
    exit(TP_UPGRADE_CLI_EXIT_OK);
}
if (tpUpgradeCliRunsAsRoot() === true) {
    fwrite(STDERR, 'ERROR: run this command as the PHP service account, for example:' . PHP_EOL
        . '  sudo -u www-data php app/scripts/upgrade-cli.php ' . $parsed['command'] . PHP_EOL);
    exit(TP_UPGRADE_CLI_EXIT_BLOCKED);
}
if ($parsed['command'] === 'status') {
    exit(upgradeCliStatus($parsed['run_id'], $parsed['options']));
}

try {
    loadClasses('DB');
    $SETTINGS = (new ConfigManager())->getAllSettings();
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERROR: cannot read the TeamPass database: ' . $exception->getMessage() . PHP_EOL);
    exit(TP_UPGRADE_CLI_EXIT_BLOCKED);
}

try {
    $exitCode = match ($parsed['command']) {
        'check' => upgradeCliCheck($parsed['options'], $SETTINGS),
        'run' => upgradeCliRun($parsed['options'], $SETTINGS),
        'resume' => upgradeCliResume($parsed['run_id'], $parsed['options'], $SETTINGS),
        'revert' => upgradeCliRevertCommand($parsed['run_id'], $parsed['options'], $SETTINGS),
        default => TP_UPGRADE_CLI_EXIT_USAGE,
    };
} catch (Throwable $exception) {
    // A run in progress keeps its journal: "status" then shows it as interrupted.
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . PHP_EOL);
    $exitCode = TP_UPGRADE_CLI_EXIT_LOCKED;
}
exit($exitCode);

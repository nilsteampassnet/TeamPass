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
 * Database, file and subprocess operations of the command-line upgrade
 * (app/scripts/upgrade-cli.php). Decisions live in upgrade_cli_logic.php.
 *
 * Never log a secret: the log keeps steps, durations, exit codes and the error field the
 * upgrade scripts return. Their raw output can quote SQL values, so it is only logged with
 * --debug, and never e-mailed.
 *
 * @file      upgrade_cli.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\EmailService\EmailService;
use TeampassClasses\EmailService\EmailSettings;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/main.functions.php';
require_once __DIR__ . '/backup.functions.php';
require_once __DIR__ . '/upgrade_cli_logic.php';
require_once __DIR__ . '/../scripts/backgroundTaskLock.php';

/**
 * Console and file logger of one command-line upgrade invocation.
 */
final class TpUpgradeCliLogger
{
    /** @var resource|null */
    private $handle = null;
    private string $path = '';

    /**
     * @param bool $debug   Also write DEBUG lines (raw subprocess output)
     * @param bool $console Echo the lines on the terminal
     */
    public function __construct(private bool $debug, private bool $console)
    {
    }

    /**
     * Append to a log file readable by its owner only.
     *
     * @param string $path Log file path
     *
     * @return bool
     */
    public function open(string $path): bool
    {
        $handle = @fopen($path, 'ab');
        if ($handle === false) {
            return false;
        }
        @chmod($path, 0600);
        $this->handle = $handle;
        $this->path = $path;

        return true;
    }

    /** @return string Path of the log file, '' when none is open */
    public function path(): string
    {
        return $this->path;
    }

    /** @return bool Whether DEBUG lines are kept */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Write one line.
     *
     * @param string $level   INFO, WARN, ERROR or DEBUG
     * @param string $phase   Phase of the run (check, backup, migrate...)
     * @param string $message Message, never a secret
     */
    public function log(string $level, string $phase, string $message): void
    {
        if ($level === 'DEBUG' && $this->debug === false) {
            return;
        }
        if (is_resource($this->handle)) {
            fwrite($this->handle, '[' . date('c') . '][' . $level . '][' . $phase . '] ' . $message . PHP_EOL);
        }
        if ($this->console === true) {
            $prefix = $level === 'INFO' ? '' : $level . ': ';
            fwrite($level === 'ERROR' || $level === 'WARN' ? STDERR : STDOUT, $prefix . $message . PHP_EOL);
        }
    }

    public function info(string $phase, string $message): void
    {
        $this->log('INFO', $phase, $message);
    }

    public function warn(string $phase, string $message): void
    {
        $this->log('WARN', $phase, $message);
    }

    public function error(string $phase, string $message): void
    {
        $this->log('ERROR', $phase, $message);
    }

    public function debug(string $phase, string $message): void
    {
        $this->log('DEBUG', $phase, $message);
    }
}

/**
 * Directories and files of the command-line upgrade, created owner-only on first use.
 *
 * @return array{root: string, runs: string, logs: string, lock: string, notified: string}
 */
function tpUpgradeCliPaths(): array
{
    $root = (defined('TEAMPASS_STORAGE') === true ? TEAMPASS_STORAGE : dirname(__DIR__, 2) . '/storage') . '/upgrade';
    $paths = [
        'root' => $root,
        'runs' => $root . '/runs',
        'logs' => $root . '/logs',
        'lock' => $root . '/upgrade.lock',
        'notified' => $root . '/notified.json',
    ];
    foreach ([$paths['root'], $paths['runs'], $paths['logs']] as $dir) {
        if (is_dir($dir) === false) {
            @mkdir($dir, 0700, true);
        }
    }

    return $paths;
}

/**
 * Whether the process runs as root: files it creates would be unreadable by the web server
 * (same rule as app/scripts/disaster-recovery.php).
 *
 * @return bool
 */
function tpUpgradeCliRunsAsRoot(): bool
{
    return PHP_OS_FAMILY !== 'Windows' && function_exists('posix_geteuid') === true && posix_geteuid() === 0;
}

/**
 * Upgrade state recorded in the database.
 *
 * @return array{db_version: string, db_version_full: string, db_schema_level: string, upgrade_timestamp: int, maintenance_mode: string}
 */
function tpUpgradeCliReadDbState(): array
{
    $rows = DB::query(
        'SELECT intitule, valeur FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule IN %ls',
        'admin',
        ['teampass_version', 'teampass_version_full', 'teampass_schema_level', 'upgrade_timestamp', 'maintenance_mode']
    );
    $values = [];
    foreach ($rows as $row) {
        $values[(string) $row['intitule']] = trim((string) $row['valeur']);
    }

    return [
        'db_version' => $values['teampass_version'] ?? '',
        'db_version_full' => $values['teampass_version_full'] ?? '',
        'db_schema_level' => $values['teampass_schema_level'] ?? '',
        'upgrade_timestamp' => (int) ($values['upgrade_timestamp'] ?? 0),
        'maintenance_mode' => $values['maintenance_mode'] ?? '0',
    ];
}

/**
 * Database state merged with the version of the deployed files, as tpUpgradeCliDecide() expects.
 *
 * @param array{db_version: string, db_version_full: string, db_schema_level: string, upgrade_timestamp: int, maintenance_mode: string} $dbState
 *
 * @return array{db_version: string, db_version_full: string, db_schema_level: string, upgrade_timestamp: int,
 *               files_version: string, files_version_full: string, min_date: int}
 */
function tpUpgradeCliDecisionState(array $dbState): array
{
    return [
        'db_version' => $dbState['db_version'],
        'db_version_full' => $dbState['db_version_full'],
        'db_schema_level' => $dbState['db_schema_level'],
        'upgrade_timestamp' => $dbState['upgrade_timestamp'],
        'files_version' => (string) TP_VERSION,
        'files_version_full' => TP_VERSION . '.' . TP_VERSION_MINOR,
        'min_date' => (int) UPGRADE_MIN_DATE,
    ];
}

/**
 * Prefixed base tables of the database.
 *
 * @param string $prefix DB_PREFIX
 *
 * @return list<string>
 */
function tpUpgradeCliListTables(string $prefix): array
{
    $tables = [];
    foreach (DB::queryFirstColumn("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $table) {
        $table = (string) $table;
        if (str_starts_with($table, $prefix) === true) {
            $tables[] = $table;
        }
    }

    return $tables;
}

/**
 * Row count of every prefixed table.
 *
 * @param string $prefix DB_PREFIX
 *
 * @return array<string, int>
 */
function tpUpgradeCliTableInventory(string $prefix): array
{
    $inventory = [];
    foreach (tpUpgradeCliListTables($prefix) as $table) {
        $inventory[$table] = (int) DB::queryFirstField('SELECT COUNT(*) FROM %b', $table);
    }

    return $inventory;
}

/**
 * Checks run before anything is changed.
 *
 * @param list<array{kind: string, file: string, name: string, version: string}> $plan     Steps to run
 * @param array<string, mixed>                                                     $SETTINGS Settings
 * @param string                                                                   $schemaLevel Resolved schema level of the data
 *
 * @return list<array{id: string, status: string, message: string}> status: ok, info, warning or error
 */
function tpUpgradeCliPreflight(array $plan, array $SETTINGS, string $schemaLevel): array
{
    $checks = [];
    $add = static function (string $id, string $status, string $message) use (&$checks): void {
        $checks[] = ['id' => $id, 'status' => $status, 'message' => $message];
    };
    $prefix = (string) DB_PREFIX;

    $add(
        'php_version',
        version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=') ? 'ok' : 'error',
        'PHP ' . PHP_VERSION . ' (minimum ' . MIN_PHP_VERSION . ')'
    );
    $missing = array_values(array_filter(
        ['openssl', 'mbstring', 'bcmath', 'xml', 'curl', 'mysqli'],
        static fn (string $extension): bool => extension_loaded($extension) === false
    ));
    $add('php_extensions', $missing === [] ? 'ok' : 'error', $missing === [] ? 'Required PHP extensions loaded' : 'Missing PHP extensions: ' . implode(', ', $missing));
    $add('php_cli_vs_fpm', 'info', 'Checked with the PHP command line (' . PHP_VERSION . '); the web server may run another PHP version');

    $secureFile = TEAMPASS_SECRETS . '/' . SECUREFILE;
    $add('secure_file', is_readable($secureFile) ? 'ok' : 'error', is_readable($secureFile) ? 'Encryption key file readable' : 'Encryption key file not readable: ' . $secureFile);

    $csrfp = dirname(__DIR__) . '/includes/libraries/csrfp/libs/csrfp.config.php';
    $add('csrfp_config', is_file($csrfp) ? 'ok' : 'error', is_file($csrfp) ? 'CSRF configuration present' : 'CSRF configuration missing: run the web wizard');

    $serverVersion = (string) DB::queryFirstField('SELECT VERSION()');
    $isMariaDb = stripos($serverVersion, 'mariadb') !== false;
    $numericVersion = preg_match('/^\d+(\.\d+)*/', $serverVersion, $matches) === 1 ? $matches[0] : '0';
    $minimum = $isMariaDb ? MIN_MARIADB_VERSION : MIN_MYSQL_VERSION;
    $add('db_version', version_compare($numericVersion, $minimum, '>=') ? 'ok' : 'error', ($isMariaDb ? 'MariaDB ' : 'MySQL ') . $numericVersion . ' (minimum ' . $minimum . ')');

    $tableStatus = DB::query(
        "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND LEFT(TABLE_NAME, %i) = %s",
        strlen($prefix),
        $prefix
    );
    $badEngine = [];
    $badCharset = [];
    foreach ($tableStatus as $row) {
        if (strcasecmp((string) $row['ENGINE'], 'InnoDB') !== 0) {
            $badEngine[] = $row['TABLE_NAME'] . ' (' . $row['ENGINE'] . ')';
        }
        if (str_starts_with((string) $row['TABLE_COLLATION'], 'utf8mb4') === false) {
            $badCharset[] = $row['TABLE_NAME'] . ' (' . $row['TABLE_COLLATION'] . ')';
        }
    }
    $add('db_engine', $badEngine === [] ? 'ok' : 'error', $badEngine === [] ? 'All tables use InnoDB' : 'Tables not using InnoDB: ' . implode(', ', $badEngine));
    $add('db_charset', $badCharset === [] ? 'ok' : 'error', $badCharset === [] ? 'All tables use utf8mb4' : 'Tables not converted to utf8mb4 (use the web wizard): ' . implode(', ', $badCharset));

    $grants = strtoupper(implode(' ', DB::queryFirstColumn('SHOW GRANTS FOR CURRENT_USER()')));
    $grantsOk = str_contains($grants, 'ALL PRIVILEGES')
        || (str_contains($grants, 'CREATE') && str_contains($grants, 'ALTER') && str_contains($grants, 'DROP') && str_contains($grants, 'INDEX'));
    $add('db_privileges', $grantsOk ? 'ok' : 'warning', $grantsOk ? 'Database privileges allow schema changes' : 'CREATE, ALTER, DROP and INDEX privileges not detected (they may come from a role)');

    $installDir = dirname(__DIR__, 2) . '/public/install';
    $missingFiles = [];
    foreach ($plan as $step) {
        if (is_file($installDir . '/' . $step['file']) === false) {
            $missingFiles[$step['file']] = true;
        }
    }
    $add('plan_files', $missingFiles === [] ? 'ok' : 'error', $missingFiles === [] ? 'Upgrade scripts present' : 'Missing in public/install: ' . implode(', ', array_keys($missingFiles)));

    $paths = tpUpgradeCliPaths();
    $backupDir = tpBackupResolveScheduledOutputDir('', $SETTINGS);
    $storageOk = is_writable($paths['runs']) && is_writable($paths['logs']);
    $backupOk = $backupDir['success'] === true && is_writable($backupDir['path']);
    $add(
        'storage_writable',
        $storageOk && $backupOk ? 'ok' : 'error',
        $storageOk && $backupOk ? 'Upgrade and backup directories writable' : 'Not writable: ' . ($storageOk ? '' : $paths['root'] . ' ') . ($backupOk ? '' : (string) ($backupDir['path'] ?: 'backup directory'))
    );

    if ($backupOk === true) {
        $dataBytes = (int) DB::queryFirstField(
            'SELECT COALESCE(SUM(DATA_LENGTH), 0) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND LEFT(TABLE_NAME, %i) = %s',
            strlen($prefix),
            $prefix
        );
        $required = tpUpgradeCliRequiredFreeBytes($dataBytes);
        $free = @disk_free_space($backupDir['path']);
        $add(
            'disk_space',
            $free === false || $free >= $required ? 'ok' : 'error',
            'Backup needs ' . tpUpgradeCliFormatBytes($required) . ', ' . ($free === false ? 'free space unknown' : tpUpgradeCliFormatBytes((int) $free) . ' free')
        );
    }

    $key = tpResolveBackupScriptPasskey($SETTINGS, false);
    $keyOk = $key['success'] === true && $key['clear_key'] !== '';
    $add('backup_key', $keyOk ? 'ok' : 'error', $keyOk ? 'Backup encryption key available' : 'Backup encryption key unavailable (Administration > Backups)');

    $add(
        'schema_level_known',
        $schemaLevel !== '' ? 'ok' : 'warning',
        $schemaLevel !== ''
            ? 'Schema level of the data known'
            : 'Schema level of the data unknown: the command line can revert, but Administration > Backups will refuse to restore this backup'
    );

    return $checks;
}

/**
 * Turn the maintenance mode on or off.
 *
 * @param string $value '1' or '0'
 */
function tpUpgradeCliSetMaintenance(string $value): void
{
    $exists = (int) DB::queryFirstField(
        'SELECT COUNT(*) FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s',
        'admin',
        'maintenance_mode'
    );
    if ($exists > 0) {
        DB::update(prefixTable('misc'), ['valeur' => $value, 'updated_at' => time()], 'type = %s AND intitule = %s', 'admin', 'maintenance_mode');
    } else {
        DB::insert(prefixTable('misc'), ['type' => 'admin', 'intitule' => 'maintenance_mode', 'valeur' => $value, 'updated_at' => time()]);
    }
    ConfigManager::invalidateCache();
}

/**
 * Close every web session and revoke every API session.
 *
 * @return array{web: int, api: int} Number of users disconnected
 */
function tpUpgradeCliDisconnectAll(): array
{
    $now = time();
    // session_end is a varchar often holding '': comparing it in an UPDATE fails in strict mode.
    $webUsers = DB::queryFirstColumn('SELECT id FROM ' . prefixTable('users') . " WHERE session_end != '' AND session_end >= %i", $now);
    if ($webUsers !== []) {
        DB::update(
            prefixTable('users'),
            ['timestamp' => '', 'key_tempo' => '', 'session_end' => ''],
            'id IN %li',
            array_map('intval', $webUsers)
        );
    }
    $web = count($webUsers);

    $apiUsers = DB::queryFirstColumn(
        'SELECT DISTINCT user_id FROM ' . prefixTable('api_sessions') . ' WHERE revoked_at IS NULL AND expires_at >= %i',
        $now
    );
    foreach ($apiUsers as $userId) {
        tpInvalidateUserApiSession((int) $userId);
    }

    return ['web' => $web, 'api' => count($apiUsers)];
}

/**
 * Take the background tasks handler lock, so no task runs while the schema changes. The handler
 * stops its workers before it releases this lock.
 *
 * @param int                $timeout Seconds to wait for a running handler
 * @param TpUpgradeCliLogger $logger  Logger
 *
 * @return BackgroundTaskLock|null The held lock, null on timeout
 */
function tpUpgradeCliAcquireTasksLock(int $timeout, TpUpgradeCliLogger $logger): ?BackgroundTaskLock
{
    $lockFile = !empty(TASKS_LOCK_FILE) ? TASKS_LOCK_FILE : TEAMPASS_STORAGE . '/logs/teampass_background_tasks.lock';
    $lock = new BackgroundTaskLock($lockFile);
    $deadline = time() + max(0, $timeout);
    $announced = false;

    while ($lock->acquire() === false) {
        if (time() >= $deadline) {
            return null;
        }
        if ($announced === false) {
            $logger->info('quiesce', 'Waiting for the background tasks handler to finish (up to ' . $timeout . ' s)...');
            $announced = true;
        }
        sleep(2);
    }

    return $lock;
}

/**
 * Run a PHP script in a subprocess, without a shell.
 *
 * The child ignores SIGINT, SIGTERM and SIGHUP: a Ctrl-C or a dropped SSH session must not kill
 * an upgrade script halfway through a schema change. The parent stops after the current step.
 *
 * @param list<string>  $arguments Script path followed by its arguments
 * @param string        $cwd       Working directory
 * @param callable|null $onLine    Receives (string $stream, string $line) as lines arrive
 *
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function tpUpgradeCliRunPhp(array $arguments, string $cwd, ?callable $onLine = null): array
{
    $signals = function_exists('pcntl_signal') === true;
    if ($signals === true) {
        // Ignored dispositions are inherited across exec; installed handlers are not.
        pcntl_signal(SIGINT, SIG_IGN);
        pcntl_signal(SIGTERM, SIG_IGN);
    }
    $process = proc_open(
        array_merge([PHP_BINARY], $arguments),
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $cwd
    );
    if ($signals === true) {
        tpUpgradeCliInstallSignalHandlers();
    }
    if (is_resource($process) === false) {
        return ['exit_code' => -1, 'stdout' => '', 'stderr' => 'Cannot start ' . PHP_BINARY];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $buffers = [1 => '', 2 => ''];
    $pending = [1 => '', 2 => ''];

    while (true) {
        $read = [];
        foreach ([1, 2] as $index) {
            if (feof($pipes[$index]) === false) {
                $read[$index] = $pipes[$index];
            }
        }
        if ($read === []) {
            break;
        }
        $write = null;
        $except = null;
        // A signal interrupts select(): just poll again.
        if (@stream_select($read, $write, $except, 1) === false) {
            continue;
        }
        foreach ($read as $index => $pipe) {
            $chunk = (string) fread($pipe, 65536);
            $buffers[$index] .= $chunk;
            if ($onLine !== null && $chunk !== '') {
                $pending[$index] .= $chunk;
                while (($position = strpos($pending[$index], "\n")) !== false) {
                    $onLine($index === 1 ? 'stdout' : 'stderr', rtrim(substr($pending[$index], 0, $position), "\r"));
                    $pending[$index] = substr($pending[$index], $position + 1);
                }
            }
        }
    }
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['exit_code' => proc_close($process), 'stdout' => $buffers[1], 'stderr' => $buffers[2]];
}

/**
 * Operator stop request (Ctrl-C, SIGTERM): honoured between two steps, never in the middle.
 *
 * @param bool|null $set New value, null to read
 *
 * @return bool
 */
function tpUpgradeCliStopRequested(?bool $set = null): bool
{
    static $requested = false;
    if ($set !== null) {
        $requested = $set;
    }

    return $requested;
}

/**
 * Catch Ctrl-C and SIGTERM as a stop request, and survive a dropped terminal.
 */
function tpUpgradeCliInstallSignalHandlers(): void
{
    if (function_exists('pcntl_signal') === false) {
        return;
    }
    pcntl_async_signals(true);
    $handler = static function (): void {
        if (tpUpgradeCliStopRequested() === false) {
            fwrite(STDERR, PHP_EOL . 'Stop requested: the current step finishes first.' . PHP_EOL);
        }
        tpUpgradeCliStopRequested(true);
    };
    pcntl_signal(SIGINT, $handler);
    pcntl_signal(SIGTERM, $handler);
    pcntl_signal(SIGHUP, SIG_IGN);
}

/**
 * Run one step of the plan: an upgrade_run script, or an operation of upgrade_operations.php.
 *
 * @param array{kind: string, file: string, name: string, version: string} $step   Step
 * @param TpUpgradeCliLogger                                                $logger Logger
 *
 * @return array{ok: bool, error: string, duration_ms: int}
 */
function tpUpgradeCliRunStep(array $step, TpUpgradeCliLogger $logger): array
{
    $installDir = dirname(__DIR__, 2) . '/public/install';
    $arguments = [$installDir . '/' . $step['file']];
    if ($step['kind'] === 'operation') {
        $arguments[] = $step['name'];
    }
    $started = microtime(true);

    for ($loop = 1; $loop <= TP_UPGRADE_CLI_MAX_OPERATION_LOOPS; $loop++) {
        $process = tpUpgradeCliRunPhp($arguments, $installDir);
        $logger->debug('migrate', $step['name'] . ' stdout: ' . substr(trim($process['stdout']), 0, 20000));
        $logger->debug('migrate', $step['name'] . ' stderr: ' . substr(trim($process['stderr']), 0, 20000));

        $result = tpUpgradeCliParseStepOutput($process['stdout'], $process['exit_code']);
        $duration = (int) round((microtime(true) - $started) * 1000);
        if ($result['ok'] === true) {
            return ['ok' => true, 'error' => '', 'duration_ms' => $duration];
        }
        // Only an operation may ask to be called again; a script never batches from 3.1.5 on.
        if ($step['kind'] === 'operation' && $result['finish'] === '0' && $result['error'] === '' && $process['exit_code'] === 0) {
            continue;
        }

        $error = $result['error'] !== '' ? $result['error'] : 'The step asked to be called again';
        if ($logger->isDebug() === false) {
            $error .= ' (run with --debug to log the raw output)';
        }

        return ['ok' => false, 'error' => $error, 'duration_ms' => $duration];
    }

    return ['ok' => false, 'error' => 'Still unfinished after ' . TP_UPGRADE_CLI_MAX_OPERATION_LOOPS . ' calls', 'duration_ms' => (int) round((microtime(true) - $started) * 1000)];
}

/**
 * Record the version reached, with app/scripts/write-db-version.php: the single implementation
 * the Docker chain uses too. Reaching TP_VERSION also records the release and its schema level.
 *
 * @param string $version Version reached (X.Y.Z)
 *
 * @return bool
 */
function tpUpgradeCliRecordVersion(string $version): bool
{
    $scriptsDir = dirname(__DIR__) . '/scripts';
    $process = tpUpgradeCliRunPhp([$scriptsDir . '/write-db-version.php', $version], $scriptsDir);

    return $process['exit_code'] === 0;
}

/**
 * Create the pre-upgrade backup: encrypted with the instance backup key, limited to the
 * TeamPass tables, stamped with the version and schema level of the data (not of the files).
 *
 * @param array                $SETTINGS Settings (the backup key may be self-healed)
 * @param array<string, mixed> $journal  Journal of the run (from, to, inventory)
 *
 * @return array{success: bool, file: string, sha256: string, size_bytes: int, message: string}
 */
function tpUpgradeCliCreateBackup(array &$SETTINGS, array $journal): array
{
    $failure = static fn (string $message): array => ['success' => false, 'file' => '', 'sha256' => '', 'size_bytes' => 0, 'message' => $message];

    $dir = tpBackupResolveScheduledOutputDir('', $SETTINGS);
    if ($dir['success'] !== true || is_writable($dir['path']) === false) {
        return $failure('Backup directory not writable');
    }
    $key = tpResolveBackupScriptPasskey($SETTINGS, true);
    $clearKey = $key['clear_key'];
    if ($key['success'] !== true || $clearKey === '') {
        return $failure('Backup encryption key unavailable');
    }

    $from = $journal['from'];
    $schemaLevel = (string) $from['schema_level'];
    $result = tpCreateDatabaseBackup($SETTINGS, $clearKey, [
        'output_dir' => $dir['path'],
        'filename_prefix' => 'scheduled-pre-upgrade-',
        'include_tables' => array_keys($journal['inventory']),
        'schema_level' => $schemaLevel,
    ]);
    if ($result['success'] !== true) {
        return $failure((string) $result['message']);
    }
    if ($result['encrypted'] !== true) {
        @unlink($result['filepath']);
        return $failure('The backup was not encrypted');
    }

    $fromRelease = (string) ($from['version_full'] !== '' ? $from['version_full'] : $from['version']);
    $extra = [
        'source' => 'upgrade_cli',
        'run_id' => $journal['run_id'],
        'upgrade_from' => $fromRelease,
        'upgrade_to' => $journal['to']['version_full'],
        'comment' => 'Before upgrade ' . $fromRelease . ' to ' . $journal['to']['version_full'],
    ];
    if ($schemaLevel === '') {
        // tpWriteBackupMetadata() would otherwise stamp the level of the new files.
        $extra['schema_level'] = null;
    }
    tpWriteBackupMetadata($result['filepath'], $fromRelease, $schemaLevel, $extra);

    return [
        'success' => true,
        'file' => $result['filepath'],
        'sha256' => tpBackupFileSha256($result['filepath']),
        'size_bytes' => (int) $result['size_bytes'],
        'message' => '',
    ];
}

/**
 * Prove the backup can be restored: decrypt it and find every table of the inventory.
 *
 * @param string               $file      Backup file
 * @param array<string, int>   $inventory Tables expected in the dump
 * @param array                $SETTINGS  Settings
 *
 * @return array{success: bool, message: string}
 */
function tpUpgradeCliVerifyBackup(string $file, array $inventory, array &$SETTINGS): array
{
    $temporary = tpBackupRestoreTempPath('sql');
    if ($temporary === '') {
        return ['success' => false, 'message' => 'No temporary file available to verify the backup'];
    }

    try {
        $decrypted = tpDefuseDecryptWithCandidates($file, $temporary, tpGetBackupScriptPasskeyCandidates($SETTINGS, false), $SETTINGS);
        if (empty($decrypted['success'])) {
            return ['success' => false, 'message' => 'The backup cannot be decrypted'];
        }

        $handle = fopen($temporary, 'rb');
        if ($handle === false) {
            return ['success' => false, 'message' => 'The decrypted backup cannot be read'];
        }
        $found = [];
        while (($line = fgets($handle)) !== false) {
            if (strncmp($line, 'DROP TABLE IF EXISTS `', 22) === 0 && preg_match('/^DROP TABLE IF EXISTS `([^`]+)`;/', $line, $matches) === 1) {
                $found[$matches[1]] = true;
            }
        }
        fclose($handle);

        $missing = array_diff(array_keys($inventory), array_keys($found));
        if ($missing !== []) {
            return ['success' => false, 'message' => 'Tables missing from the backup: ' . implode(', ', $missing)];
        }

        return ['success' => true, 'message' => count($found) . ' tables verified'];
    } finally {
        if (is_file($temporary) === true) {
            @unlink($temporary);
        }
    }
}

/**
 * Restore the pre-upgrade backup with app/scripts/restore.php, drop the tables the failed
 * migration created, and check the database is back to its recorded version.
 *
 * Maintenance stays on: restore.php forces it, and the deployed files are still the new release.
 *
 * @param array<string, mixed> $journal  Journal of the run
 * @param array                $SETTINGS Settings, reloaded from the restored database
 * @param TpUpgradeCliLogger   $logger   Logger
 *
 * @return array{success: bool, warnings: list<string>, dropped: list<string>, restore_exit_code: int, message: string}
 */
function tpUpgradeCliRevert(array $journal, array &$SETTINGS, TpUpgradeCliLogger $logger): array
{
    $result = ['success' => false, 'warnings' => [], 'dropped' => [], 'restore_exit_code' => 0, 'message' => ''];
    $file = (string) $journal['backup']['file'];

    $authorization = tpRestoreAuthorizationCreate(
        0,
        'upgrade-cli',
        $file,
        'scheduled',
        basename($file),
        0,
        '',
        '',
        ['source' => 'upgrade_cli', 'run_id' => $journal['run_id']],
        3600,
        $SETTINGS
    );
    if (empty($authorization['success'])) {
        $result['message'] = 'Cannot authorize the restore';
        return $result;
    }

    $logger->info('revert', 'Restoring ' . basename($file) . ' (restore log under ' . rtrim((string) ($SETTINGS['path_to_files_folder'] ?? ''), '/') . '/restore_cli_logs)...');
    $scriptsDir = dirname(__DIR__) . '/scripts';
    $process = tpUpgradeCliRunPhp(
        [$scriptsDir . '/restore.php', '--file=' . $file, '--auth-token=' . (string) $authorization['token'], '--force-disconnect'],
        $scriptsDir,
        static function (string $stream, string $line) use ($logger): void {
            // Progress only: restore.php writes its own detailed log.
            if (str_starts_with($line, 'Progress:') === true) {
                $logger->info('revert', $line);
            } else {
                $logger->debug('revert', 'restore.php ' . $stream . ': ' . $line);
            }
        }
    );
    $result['restore_exit_code'] = $process['exit_code'];
    if ($process['exit_code'] !== 0) {
        $result['message'] = 'restore.php failed with exit code ' . $process['exit_code'];
        return $result;
    }

    ConfigManager::invalidateCache();
    $SETTINGS = (new ConfigManager())->getAllSettings();

    foreach (tpUpgradeCliTablesToDrop(tpUpgradeCliListTables((string) DB_PREFIX), $journal['inventory'], (string) DB_PREFIX) as $table) {
        DB::query('DROP TABLE IF EXISTS %b', $table);
        $result['dropped'][] = $table;
    }

    $state = tpUpgradeCliReadDbState();
    if ($state['db_version'] !== (string) $journal['from']['version']
        || $state['upgrade_timestamp'] !== (int) $journal['from']['upgrade_timestamp']
    ) {
        $result['message'] = 'The restored database does not hold the pre-upgrade version';
        return $result;
    }

    foreach (tpUpgradeCliInventoryDiff($journal['inventory'], tpUpgradeCliTableInventory((string) DB_PREFIX)) as $table => $counts) {
        $result['warnings'][] = $table . ': ' . $counts[0] . ' rows expected, ' . ($counts[1] === null ? 'table missing' : $counts[1] . ' found');
    }
    $result['success'] = true;

    return $result;
}

/**
 * E-mail the active administrators, synchronously: the background tasks handler is locked during
 * a run and must stay idle after a revert, so a queued e-mail would not leave.
 *
 * @param string               $status     PENDING, COMPLETED, STOPPED, REVERTED, REVERT FAILED, BACKUP FAILED
 * @param array<string, mixed> $details    from, to, message, backup, log, next_action (list<string>)
 * @param array<string, mixed> $SETTINGS   Settings
 * @param TpUpgradeCliLogger   $logger     Logger
 *
 * @return int Number of e-mails sent
 */
function tpUpgradeCliNotify(string $status, array $details, array $SETTINGS, TpUpgradeCliLogger $logger): int
{
    if (trim((string) ($SETTINGS['email_smtp_server'] ?? '')) === '') {
        $logger->info('notify', 'No SMTP server configured: no e-mail sent');
        return 0;
    }

    try {
        $sql = 'SELECT id, login, email, user_language FROM ' . prefixTable('users') . " WHERE admin = %i AND disabled = %i AND email != ''";
        $excluded = teampassGetSystemAccountIds();
        $admins = count($excluded) > 0 ? DB::query($sql . ' AND id NOT IN %li', 1, 0, $excluded) : DB::query($sql, 1, 0);
    } catch (Throwable $exception) {
        $logger->warn('notify', 'Cannot list the administrators: no e-mail sent');
        return 0;
    }

    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $nextAction = implode('<br>', array_map($escape, (array) ($details['next_action'] ?? [])));
    $tokens = [
        '#tp_status#' => $escape($status),
        '#tp_datetime#' => $escape(date('Y-m-d H:i:s')),
        '#tp_server#' => $escape((string) gethostname()),
        '#tp_from#' => $escape((string) ($details['from'] ?? '')),
        '#tp_to#' => $escape((string) ($details['to'] ?? '')),
        '#tp_backup#' => $escape((string) ($details['backup'] ?? '')),
        '#tp_log#' => $escape((string) ($details['log'] ?? '')),
        '#tp_message#' => $escape((string) ($details['message'] ?? '')),
        '#tp_next_action#' => $nextAction,
    ];

    $emailService = new EmailService();
    $emailSettings = new EmailSettings($SETTINGS);
    $sent = 0;
    foreach ($admins as $admin) {
        $language = (string) ($admin['user_language'] ?? '');
        $lang = new Language($language === '' || $language === '0' ? 'english' : $language);
        $subject = str_replace('#tp_status#', $status, (string) $lang->get('email_subject_upgrade_cli_report'));
        $body = str_replace(array_keys($tokens), array_values($tokens), (string) $lang->get('email_body_upgrade_cli_report'));

        // Silent: an SMTP debug transcript would print the AUTH exchange on the terminal.
        $answer = $emailService->sendMail($subject, $body, (string) $admin['email'], $emailSettings, '', true, true);
        if ($answer === '') {
            $sent++;
        } else {
            $logger->warn('notify', 'E-mail to ' . $admin['login'] . ' failed');
        }
    }
    $logger->info('notify', $sent . ' administrator(s) notified by e-mail');

    return $sent;
}

/**
 * Trace the outcome in the administration logs.
 *
 * @param string               $status   completed, stopped, reverted, revert_failed, backup_failed, release_recorded
 * @param string               $from     Release before the upgrade
 * @param string               $to       Release of the files
 * @param string               $runId    Run identifier
 * @param array<string, mixed> $SETTINGS Settings
 */
function tpUpgradeCliAuditLog(string $status, string $from, string $to, string $runId, array $SETTINGS): void
{
    logEvents($SETTINGS, 'admin_action', 'at_upgrade_cli_' . $status . ':' . $from . ':' . $to, (string) TP_USER_ID, 'upgrade-cli', $runId);
}

/**
 * Read a journal.
 *
 * @param string $runId Run identifier
 *
 * @return array<string, mixed>|null
 */
function tpUpgradeCliJournalLoad(string $runId): ?array
{
    if (tpUpgradeCliIsValidRunId($runId) === false) {
        return null;
    }
    $path = tpUpgradeCliPaths()['runs'] . '/' . $runId . '.json';
    if (is_file($path) === false) {
        return null;
    }
    $journal = json_decode((string) file_get_contents($path), true);

    return is_array($journal) === true ? $journal : null;
}

/**
 * Write a journal atomically, owner-only.
 *
 * @param array<string, mixed> $journal Journal
 *
 * @return bool
 */
function tpUpgradeCliJournalSave(array &$journal): bool
{
    $journal['updated_at'] = time();
    $path = tpUpgradeCliPaths()['runs'] . '/' . $journal['run_id'] . '.json';
    $temporary = $path . '.tmp';
    $json = json_encode($journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || @file_put_contents($temporary, $json, LOCK_EX) === false) {
        return false;
    }
    @chmod($temporary, 0600);

    return @rename($temporary, $path);
}

/**
 * Identifiers of the recorded runs, newest first.
 *
 * @return list<string>
 */
function tpUpgradeCliRunIds(): array
{
    $ids = [];
    foreach (glob(tpUpgradeCliPaths()['runs'] . '/*.json') ?: [] as $path) {
        $id = basename($path, '.json');
        if (tpUpgradeCliIsValidRunId($id) === true) {
            $ids[] = $id;
        }
    }
    rsort($ids);

    return $ids;
}

/**
 * The most recent run that did not reach a final state.
 *
 * @return array<string, mixed>|null
 */
function tpUpgradeCliFindUnfinishedRun(): ?array
{
    foreach (tpUpgradeCliRunIds() as $id) {
        $journal = tpUpgradeCliJournalLoad($id);
        if ($journal !== null && in_array($journal['status'] ?? '', ['running', 'reverting', 'failed_resumable', 'revert_failed'], true)) {
            return $journal;
        }
    }

    return null;
}

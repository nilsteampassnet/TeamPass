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
 * @file      restore.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\ConfigManager\ConfigManager;

require_once __DIR__ . '/../sources/main.functions.php';
require_once __DIR__ . '/../sources/backup.functions.php';

loadClasses('DB');

function tpCliOut(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

function tpCliErr(string $msg): void
{
    fwrite(STDERR, $msg . PHP_EOL);
}

function tpCliHelp(): void
{
    tpCliOut('Usage: php scripts/restore.php --file "/path/to/backup.sql" --auth-token "TOKEN" [--force-disconnect]');
}

function tpCliIsInteractive(): bool
{
    try {
        if (function_exists('stream_isatty')) {
            return @stream_isatty(STDERR);
        }
        if (function_exists('posix_isatty')) {
            return @posix_isatty(STDERR);
        }
    } catch (Throwable $e) {
        // ignore
    }
    return false;
}

/**
 * Render a single-line progress bar on STDERR.
 * - Interactive terminals: updates in place (\r)
 * - Non-interactive: prints a line every 5% (and at 100%)
 */
function tpCliProgressBar(int $pct, int $totalExecuted, bool $interactive): void
{
    static $lastPct = -1;
    static $lastLen = 0;
    static $lastTs = 0.0;

    $pct = max(0, min(100, $pct));

    if (!$interactive) {
        if ($pct !== $lastPct && ($pct % 5 === 0 || $pct === 100)) {
            fwrite(STDERR, 'Progress: ' . $pct . '% (statements executed: ' . $totalExecuted . ')' . PHP_EOL);
            $lastPct = $pct;
        }
        return;
    }

    $now = microtime(true);
    if ($pct === $lastPct && ($now - $lastTs) < 0.20) {
        return;
    }

    $lastTs = $now;
    $lastPct = $pct;

    $width = 30;
    $filled = (int) round(($pct / 100) * $width);
    $bar = str_repeat('#', $filled) . str_repeat('-', $width - $filled);
    $msg = sprintf("\r[%s] %3d%% | statements: %d", $bar, $pct, $totalExecuted);

    $pad = max(0, $lastLen - strlen($msg));
    fwrite(STDERR, $msg . str_repeat(' ', $pad));
    $lastLen = strlen($msg);

    if ($pct === 100) {
        fwrite(STDERR, PHP_EOL);
    }
}

if (PHP_SAPI !== 'cli') {
    tpCliErr('ERROR: This script must be executed in CLI.');
    exit(2);
}

// Parse CLI options
$options = getopt('', ['file:', 'auth-token:', 'force-disconnect', 'help']);
if (isset($options['help']) || empty($options['file']) || empty($options['auth-token'])) {
    tpCliHelp();
    exit(isset($options['help']) ? 0 : 2);
}

$file = (string) $options['file'];
$authToken = (string) $options['auth-token'];
$forceDisconnect = array_key_exists('force-disconnect', $options);

$interactive = tpCliIsInteractive();

// Load settings
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Logging
$filesDir = rtrim((string) ($SETTINGS['path_to_files_folder'] ?? sys_get_temp_dir()), '/');
$logDir = $filesDir . '/restore_cli_logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0700, true);
}
$logFile = $logDir . '/restore_' . date('Ymd_His') . '.log';

$log = function (string $level, string $message) use ($logFile): void {
    $line = '[' . date('c') . '][' . $level . '] ' . $message . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
    if ($level === 'ERROR') {
        tpCliErr($message);
    } else {
        tpCliOut($message);
    }
};

// Lock (prevent concurrent restores)
// Prefer the TeamPass files folder, but fallback to system temp if not writable for the current user.
$lockFile = '';
$lockHandle = false;
$lockCandidates = [
    $filesDir . '/restore_cli.lock',
    rtrim((string) sys_get_temp_dir(), '/\\') . '/teampass_restore_cli_' . substr(md5($filesDir), 0, 12) . '.lock',
];

foreach ($lockCandidates as $candidate) {
    $h = @fopen($candidate, 'c');
    if ($h !== false) {
        $lockFile = $candidate;
        $lockHandle = $h;
        break;
    }
}

if ($lockHandle === false) {
    $log('ERROR', 'Unable to open any lock file. Tried: ' . implode(', ', $lockCandidates));
    exit(20);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    $log('ERROR', 'Another restore seems to be in progress (lock: ' . $lockFile . ').');
    exit(20);
}

@ftruncate($lockHandle, 0);
@fwrite($lockHandle, (string) getmypid() . ' ' . date('c') . PHP_EOL);

// Normalize file path
$realFile = realpath($file);
if ($realFile !== false) {
    $file = $realFile;
}

if (!is_file($file)) {
    $log('ERROR', 'Backup file not found: ' . $file);
    exit(3);
}

$tokenHash = hash('sha256', $authToken);

// Token verification (DB)
$fetch = tpRestoreAuthorizationFetchByHash($tokenHash);
if (empty($fetch['success'])) {
    $log('ERROR', 'Authorization token not found or invalid.');
    exit(30);
}

$authId = (int) ($fetch['id'] ?? 0);
$payload = $fetch['payload'] ?? [];
if (!is_array($payload)) {
    $log('ERROR', 'Authorization payload is invalid.');
    exit(30);
}

$now = time();
$status = (string) ($payload['status'] ?? '');
$expiresAt = (int) ($payload['expires_at'] ?? 0);
$payloadFilePath = (string) (($payload['file']['path'] ?? ''));

if ($status !== 'pending') {
    $log('ERROR', 'Authorization token is not pending (status=' . $status . ').');
    exit(31);
}

if ($expiresAt > 0 && $expiresAt < $now) {
    $log('ERROR', 'Authorization token is expired.');
    exit(32);
}

// Ensure the token matches the file we are about to restore
$cmp1 = $payloadFilePath;
$cmp2 = $file;
$cmp1Real = ($cmp1 !== '') ? realpath($cmp1) : false;
if ($cmp1Real !== false) {
    $cmp1 = $cmp1Real;
}
$cmp2Real = realpath($cmp2);
if ($cmp2Real !== false) {
    $cmp2 = $cmp2Real;
}

if ($cmp1 === '' || $cmp1 !== $cmp2) {
    $log('ERROR', 'Authorization token does not match the provided --file path.');
    $log('ERROR', 'Expected: ' . $payloadFilePath);
    $log('ERROR', 'Provided: ' . $file);
    exit(33);
}

// Always disconnect the restore initiator right away (so the admin can safely run the CLI).
$initiatorId = (int) (($payload['initiator']['id'] ?? 0));
if ($initiatorId > 0) {
    try {
        DB::update(
            prefixTable('users'),
            [
                'timestamp' => '',
                'key_tempo' => '',
                'session_end' => '',
            ],
            'id = %i',
            $initiatorId
        );
        $log('INFO', 'Disconnected restore initiator (user id=' . $initiatorId . ').');
    } catch (Throwable $e) {
        $log('WARN', 'Unable to disconnect restore initiator: ' . $e->getMessage());
    }
}

// Connected users detection (web + API if possible)
$webUsers = [];
$apiUsers = [];
try {
    if ($initiatorId > 0) {
        $webUsers = DB::query(
            'SELECT id, login, name, lastname, session_end
             FROM ' . prefixTable('users') . '
             WHERE session_end >= %i AND id != %i',
            $now,
            $initiatorId
        );
    } else {
        $webUsers = DB::query(
            'SELECT id, login, name, lastname, session_end
             FROM ' . prefixTable('users') . '
             WHERE session_end >= %i',
            $now
        );
    }
} catch (Throwable $e) {
	// Ensure any in-place progress bar line is terminated before printing errors
	if ($interactive) {
	    @fwrite(STDERR, PHP_EOL);
	}
    $log('WARN', 'Unable to check web sessions: ' . $e->getMessage());
}

try {
    $apiTokenDuration = 3600;
    if (isset($SETTINGS['api_token_duration']) && is_numeric($SETTINGS['api_token_duration'])) {
        $apiTokenDuration = (int) $SETTINGS['api_token_duration'];
    }
    if ($apiTokenDuration <= 0) {
        $apiTokenDuration = 3600;
    }
    $apiConnectedAfter = $now - ($apiTokenDuration + 600);

    $apiUsers = DB::query(
        'SELECT u.id, u.login, u.name, u.lastname, api_conn.last_api_date AS last_api
         FROM ' . prefixTable('users') . ' u
         INNER JOIN (
            SELECT qui, MAX(date) AS last_api_date
            FROM ' . prefixTable('log_system') . '
            WHERE type = %s AND label = %s AND date >= %i
            GROUP BY qui
         ) api_conn ON api_conn.qui = u.id',
        'api',
        'user_connection',
        $apiConnectedAfter
    );
} catch (Throwable $e) {
    // Optional: ignore API checks if unavailable
    $log('WARN', 'Unable to check API activity: ' . $e->getMessage());
}

// Enforce disconnect policy for web sessions
if (!empty($webUsers) && $forceDisconnect === false) {
    $log('ERROR', 'Active web sessions detected. Re-run with --force-disconnect or disconnect users first.');
    foreach ($webUsers as $u) {
        $log('ERROR', 'WEB user connected: ' . ($u['login'] ?? '') . ' (' . ($u['name'] ?? '') . ' ' . ($u['lastname'] ?? '') . ')');
    }
    if (!empty($apiUsers)) {
        $log('WARN', 'API activity detected as well (best-effort detection):');
        foreach ($apiUsers as $u) {
            $log('WARN', 'API activity: ' . ($u['login'] ?? '') . ' (' . ($u['name'] ?? '') . ' ' . ($u['lastname'] ?? '') . ')');
        }
    }
    // IMPORTANT: keep token pending so the operator can retry after disconnecting users.
    $payload['last_error'] = 'active_web_sessions';
    $payload['last_error_at'] = time();
    tpRestoreAuthorizationUpdatePayload($authId, $payload);

    exit(10);
}

if (!empty($webUsers) && $forceDisconnect === true) {
    $log('INFO', 'Active web sessions detected: forcing disconnection.');
    try {
        DB::update(
            prefixTable('users'),
            [
                'timestamp' => '',
                'key_tempo' => '',
                'session_end' => '',
            ],
            'session_end >= %i',
            $now
        );
    } catch (Throwable $e) {
        $log('WARN', 'Failed to force-disconnect web users: ' . $e->getMessage());
    }
}

// From this point, we consider the restore authorized to start.
$payload['status'] = 'in_progress';
$payload['started_at'] = time();
tpRestoreAuthorizationUpdatePayload($authId, $payload);

if (!empty($apiUsers)) {
    $log('WARN', 'API activity detected (best-effort): those sessions may not be revocable. Proceeding.');
    foreach ($apiUsers as $u) {
        $log('WARN', 'API activity: ' . ($u['login'] ?? '') . ' (' . ($u['name'] ?? '') . ' ' . ($u['lastname'] ?? '') . ')');
    }
}

// Maintenance mode toggle (best-effort)
// Keep maintenance enabled at the end of the restore (same behavior as the legacy web restore),
// because the SQL dump may restore maintenance_mode to 0.
try {
    $affected = DB::update(
        prefixTable('misc'),
        [
            'valeur' => '1',
            'updated_at' => time(),
        ],
        'intitule = %s AND type = %s',
        'maintenance_mode',
        'admin'
    );
    if ($affected === 0) {
        DB::insert(
            prefixTable('misc'),
            [
                'intitule' => 'maintenance_mode',
                'type' => 'admin',
                'valeur' => '1',
                'updated_at' => time(),
            ]
        );
    }
} catch (Throwable $e) {
    // Fallback without updated_at
    try {
        $affected = DB::update(
            prefixTable('misc'),
            [
                'valeur' => '1',
            ],
            'intitule = %s AND type = %s',
            'maintenance_mode',
            'admin'
        );
        if ($affected === 0) {
            DB::insert(
                prefixTable('misc'),
                [
                    'intitule' => 'maintenance_mode',
                    'type' => 'admin',
                    'valeur' => '1',
                ]
            );
        }
    } catch (Throwable $e2) {
        $log('WARN', 'Unable to enable maintenance mode: ' . $e2->getMessage());
    }
}

$log('INFO', 'Starting restore from: ' . $file);
$log('INFO', 'Log file: ' . $logFile);

// Build decryption keys to try
$keysToTry = [];

// Secrets from payload (encrypted in DB)
try {
    $secrets = $payload['secrets'] ?? [];
    if (is_array($secrets)) {
        if (!empty($secrets['encryptionKey'])) {
            $tmp = cryption((string) $secrets['encryptionKey'], '', 'decrypt', $SETTINGS);
            $k = isset($tmp['string']) ? (string) $tmp['string'] : '';
            if ($k !== '') $keysToTry[] = $k;
        }
        if (!empty($secrets['overrideKey'])) {
            $tmp = cryption((string) $secrets['overrideKey'], '', 'decrypt', $SETTINGS);
            $k = isset($tmp['string']) ? (string) $tmp['string'] : '';
            if ($k !== '') $keysToTry[] = $k;
        }
    }
} catch (Throwable $e) {
    $log('WARN', 'Unable to decrypt stored secrets: ' . $e->getMessage());
}

// Instance key (scheduled backups)
try {
    if (!empty($SETTINGS['bck_script_passkey'] ?? '') === true) {
        $rawInstanceKey = (string) $SETTINGS['bck_script_passkey'];
        $tmp = cryption($rawInstanceKey, '', 'decrypt', $SETTINGS);
        $decInstanceKey = isset($tmp['string']) ? (string) $tmp['string'] : '';

        if ($decInstanceKey !== '') {
            $keysToTry[] = $decInstanceKey;
        }
        // Some environments may store bck_script_passkey already in clear
        if ($rawInstanceKey !== '' && $rawInstanceKey !== $decInstanceKey) {
            $keysToTry[] = $rawInstanceKey;
        }
    }
} catch (Throwable $e) {
    $log('WARN', 'Unable to decrypt instance backup key: ' . $e->getMessage());
}

$keysToTry = array_values(array_unique(array_filter($keysToTry, function ($v) { return $v !== ''; })));

// Decrypt backup file to temp SQL
// IMPORTANT: do NOT use tempnam() here. It creates the file and can break Defuse file decrypt.
$tmpRand = '';
try {
    $tmpRand = bin2hex(random_bytes(4));
} catch (Throwable $ignored) {
    $tmpRand = uniqid('', true);
}

$tmpSql = rtrim((string) sys_get_temp_dir(), '/\\') . '/defuse_temp_restore_' . getmypid() . '_' . time() . '_' . $tmpRand . '.sql';

$dec = tpDefuseDecryptWithCandidates($file, $tmpSql, $keysToTry, $SETTINGS);
if (empty($dec['success'])) {
    @unlink($tmpSql);
    $log('ERROR', 'Decrypt failed: ' . (string) ($dec['message'] ?? ''));
    $payload['status'] = 'failed';
    $payload['finished_at'] = time();
    $payload['error'] = 'decrypt_failed';
    tpRestoreAuthorizationUpdatePayload($authId, $payload);
    exit(41);
}

$log('INFO', 'Decrypt OK. Importing SQL...');

// Restore SQL (chunked)
$totalSize = filesize($tmpSql);
if ($totalSize === false) {
    $totalSize = 0;
}
$offset = 0;
$batchSize = 500;
$totalExecuted = 0;

$handle = fopen($tmpSql, 'r');
if ($handle === false) {
    @unlink($tmpSql);
    $log('ERROR', 'Unable to open decrypted SQL file.');
    exit(42);
}

try {
    while (true) {
        if ($offset > 0) {
            fseek($handle, $offset);
        }

        $query = '';
        $executed = 0;
        $inMultiLineComment = false;
        $errors = [];

        try {
            DB::startTransaction();
            DB::query("SET FOREIGN_KEY_CHECKS = 0");
            DB::query("SET UNIQUE_CHECKS = 0");

            while (!feof($handle) && $executed < $batchSize) {
                $line = fgets($handle);
                if ($line === false) {
                    break;
                }

                $trimmedLine = trim($line);

                // Skip empty lines or comments
                if (
                    $trimmedLine === ''
                    || strpos($trimmedLine, '--') === 0
                    || strpos($trimmedLine, '#') === 0
                ) {
                    continue;
                }

                // Handle multi-line comments
                if (strpos($trimmedLine, '/*') === 0 && strpos($trimmedLine, '*/') === false) {
                    $inMultiLineComment = true;
                    continue;
                }
                if ($inMultiLineComment) {
                    if (strpos($trimmedLine, '*/') !== false) {
                        $inMultiLineComment = false;
                    }
                    continue;
                }

                $query .= $line;

                // Execute query if it ends with semicolon
                if (substr(rtrim($query), -1) === ';') {
                    try {
                        DB::query($query);
                        $executed++;
                        $totalExecuted++;
                    } catch (Throwable $e) {
                        $errors[] = $e->getMessage();
                        $log('ERROR', 'SQL error: ' . $e->getMessage());
                        // Stop early on first error
                        break;
                    }
                    $query = '';
                }
            }

            if (empty($errors)) {
                DB::commit();
            } else {
                DB::rollback();
            }
        } catch (Throwable $e) {
            try {
                DB::query("SET FOREIGN_KEY_CHECKS = 1");
                DB::query("SET UNIQUE_CHECKS = 1");
            } catch (Throwable $ignored) {
                // ignore
            }
            DB::rollback();
            $errors[] = $e->getMessage();
            $log('ERROR', 'Restore chunk failed: ' . $e->getMessage());
        } finally {
            try {
                DB::query("SET FOREIGN_KEY_CHECKS = 1");
                DB::query("SET UNIQUE_CHECKS = 1");
            } catch (Throwable $ignored) {
                // ignore
            }
        }

        $newOffset = ftell($handle);
        if ($newOffset === false) {
            $newOffset = $offset;
        }


	    // Progress (single-line bar on STDERR to avoid flooding the terminal)
	    if ($totalSize > 0) {
	        $pct = (int) floor(($newOffset / $totalSize) * 100);
	        if ($pct > 100) {
	            $pct = 100;
	        }
	        tpCliProgressBar($pct, $totalExecuted, $interactive);

	        // Log progress in file every 10%
	        static $lastLoggedPct = -1;
	        if ($pct !== $lastLoggedPct && ($pct % 10 === 0 || $pct === 100)) {
	            $lastLoggedPct = $pct;
	            @file_put_contents(
	                $logFile,
	                '[' . date('c') . '][INFO] Progress: ' . $pct . '% (statements executed: ' . $totalExecuted . ')' . PHP_EOL,
	                FILE_APPEND
	            );
	        }
	    }

        if (!empty($errors)) {
            throw new RuntimeException('SQL restore failed: ' . implode(' | ', $errors));
        }

        // End conditions
        if (feof($handle) || $newOffset >= $totalSize) {
            break;
        }

        // Continue with next offset
        $offset = $newOffset;
    }

    $log('INFO', 'SQL import completed. Total statements executed: ' . $totalExecuted);

    $payload['status'] = 'success';
    $payload['finished_at'] = time();
    tpRestoreAuthorizationUpdatePayload($authId, $payload);

    $exitCode = 0;
} catch (Throwable $e) {
    $payload['status'] = 'failed';
    $payload['finished_at'] = time();
    $payload['error'] = $e->getMessage();
    tpRestoreAuthorizationUpdatePayload($authId, $payload);

    $log('ERROR', 'Restore failed: ' . $e->getMessage());
    $exitCode = 50;
} finally {
    if (is_resource($handle)) {
        fclose($handle);
    }
    @unlink($tmpSql);

    // Re-force maintenance mode ON at the end (dump may have restored it to 0).
    try {
        DB::update(
            prefixTable('misc'),
            [
                'valeur' => '1',
                'updated_at' => time(),
            ],
            'intitule = %s AND type = %s',
            'maintenance_mode',
            'admin'
        );
    } catch (Throwable $e) {
        try {
            DB::update(
                prefixTable('misc'),
                [
                    'valeur' => '1',
                ],
                'intitule = %s AND type = %s',
                'maintenance_mode',
                'admin'
            );
        } catch (Throwable $ignored) {
            // ignore
        }
    }

    // Release lock
    try {
        flock($lockHandle, LOCK_UN);
    } catch (Throwable $ignored) {
        // ignore
    }
    @fclose($lockHandle);
}

exit($exitCode);

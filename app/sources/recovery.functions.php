<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery.functions.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

require_once __DIR__ . '/recovery_upgrade.php';
require_once __DIR__ . '/recovery_sql.php';
require_once __DIR__ . '/backup.functions.php';
require_once __DIR__ . '/config_access_logic.php';
require_once __DIR__ . '/install_security_logic.php';

/** Resolve a job inside the private recovery directory, refusing links in its ancestry. */
function tpRecoveryJobDirectory(string $id = ''): string
{
    $base = TEAMPASS_ROOT . '/storage/recovery';
    foreach ([TEAMPASS_ROOT . '/storage', $base] as $directory) {
        if (is_link($directory)) {
            throw new TeamPassRecoveryException('RECOVERY_PATH_INVALID');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700)) {
            throw new TeamPassRecoveryException('RECOVERY_DIRECTORY_UNWRITABLE');
        }
    }
    if ($id === '') {
        return $base;
    }
    if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1 || is_link($base . '/' . $id)) {
        throw new TeamPassRecoveryException('RECOVERY_JOB_INVALID');
    }
    return $base . '/' . $id;
}

/** Write sensitive data owner-only and replace it atomically within a trusted directory. */
function tpRecoveryWritePrivate(string $path, string $contents, bool $exclusive = false): void
{
    if (is_link($path)) {
        throw new TeamPassRecoveryException('RECOVERY_PATH_INVALID');
    }
    $temporary = dirname($path) . '/.recovery-' . bin2hex(random_bytes(16));
    $handle = tpBackupOpenProtectedFile($temporary, 'x+b', true);
    if (!is_resource($handle)) {
        throw new TeamPassRecoveryException('RECOVERY_WRITE_FAILED');
    }
    try {
        $offset = 0;
        while ($offset < strlen($contents)) {
            $written = fwrite($handle, substr($contents, $offset));
            if ($written === false || $written === 0) {
                throw new TeamPassRecoveryException('RECOVERY_WRITE_FAILED');
            }
            $offset += $written;
        }
        if (!fflush($handle)) {
            throw new TeamPassRecoveryException('RECOVERY_WRITE_FAILED');
        }
    } finally {
        fclose($handle);
    }
    if (!($exclusive ? @link($temporary, $path) : rename($temporary, $path))) {
        @unlink($temporary);
        throw new TeamPassRecoveryException('RECOVERY_WRITE_FAILED');
    }
    if ($exclusive) {
        unlink($temporary);
    }
}

/** Persist job metadata outside the database being restored. */
function tpRecoverySaveJob(array $job): void
{
    tpRecoveryWritePrivate(tpRecoveryJobDirectory($job['id']) . '/job.json', json_encode($job, JSON_THROW_ON_ERROR));
}

/** Load a private recovery job; never return this payload to the browser. */
function tpRecoveryLoadJob(string $id): array
{
    $path = tpRecoveryJobDirectory($id) . '/job.json';
    if (is_link($path) || !is_readable($path) || filesize($path) > 16777216) {
        throw new TeamPassRecoveryException('RECOVERY_JOB_INVALID');
    }
    $job = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($job) || ($job['id'] ?? '') !== $id) {
        throw new TeamPassRecoveryException('RECOVERY_JOB_INVALID');
    }
    return $job;
}

/** Require a fresh deployment. Existing key material is evidence of an existing installation. */
function tpRecoveryAssertFreshTarget(): void
{
    if (teampassConfigState(TEAMPASS_ROOT . '/app/config') !== 'not_installed') {
        throw new TeamPassRecoveryException('TARGET_ALREADY_INSTALLED');
    }
    foreach (['secrets', 'storage/upload', 'storage/files/kb_attachments', 'public/assets/avatars'] as $relative) {
        $directory = TEAMPASS_ROOT . '/' . $relative;
        if (is_link($directory)) {
            throw new TeamPassRecoveryException('RECOVERY_PATH_INVALID');
        }
        if (!is_dir($directory)) {
            continue;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if (!in_array($entry, ['.', '..', '.gitkeep', '.htaccess', 'empty_file.txt', 'index.html'], true)) {
                throw new TeamPassRecoveryException('TARGET_DATA_NOT_EMPTY');
            }
        }
    }
}

/** Validate destination connection data without accepting source configuration PHP. */
function tpRecoveryConnectionOptions(array $input): array
{
    $host = $input['host'] ?? '';
    $user = $input['user'] ?? '';
    $database = $input['database'] ?? '';
    $password = $input['password'] ?? '';
    $port = filter_var($input['port'] ?? 3306, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if (!is_string($host) || $host === '' || strlen($host) > 255 || str_contains($host, "\0")
        || !is_string($user) || $user === '' || strlen($user) > 128
        || !is_string($password) || !is_string($database)
        || preg_match('/^[a-zA-Z0-9_]{1,64}$/D', $database) !== 1 || $port === false) {
        throw new TeamPassRecoveryException('TARGET_DATABASE_CONFIG_INVALID');
    }
    return compact('host', 'user', 'database', 'password', 'port');
}

/** Open a destination connection, keeping driver diagnostics out of logs and HTTP responses. */
function tpRecoveryConnect(array $options): mysqli
{
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = mysqli_init();
        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        $db->real_connect($options['host'], $options['user'], $options['password'], $options['database'], $options['port']);
        $db->set_charset('utf8mb4');
        return $db;
    } catch (Throwable $exception) {
        throw new TeamPassRecoveryException('TARGET_DATABASE_UNREACHABLE');
    }
}

/** Authenticate a complete encrypted archive before inspecting any extracted data. */
function tpRecoveryDecrypt(string $source, string $destination, string $password, int $maximumBytes = 53687091200): void
{
    if ($password === '' || is_link($source) || !is_file($source) || !is_readable($source)) {
        throw new TeamPassRecoveryException('ARCHIVE_INPUT_INVALID');
    }
    $size = filesize($source);
    $free = disk_free_space(dirname($destination));
    if ($size === false || $free === false || $size > $maximumBytes || $size + 67108864 > $free) {
        throw new TeamPassRecoveryException('RECOVERY_SPACE_INSUFFICIENT');
    }
    $handle = tpBackupOpenProtectedFile($destination, 'x+b', true);
    if (!is_resource($handle)) {
        throw new TeamPassRecoveryException('RECOVERY_WRITE_FAILED');
    }
    fclose($handle);
    try {
        \Defuse\Crypto\File::decryptFileWithPassword($source, $destination, $password);
    } catch (Throwable $exception) {
        @unlink($destination);
        throw new TeamPassRecoveryException('ARCHIVE_AUTHENTICATION_FAILED');
    }
}

/** Require checksums and a complete document inventory in addition to legacy package checks. */
function tpRecoveryValidateBackup(string $zipPath, string $sqlPath, int $maximumBytes): array
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new TeamPassRecoveryException('ARCHIVE_INVALID');
    }
    try {
        $inventory = tpRecoveryInspectZip($zip, $maximumBytes);
        $manifest = tpRecoveryReadZipJson($zip, 'manifest.json');
        if (!is_array($manifest['document_entries'] ?? null) || !is_array($manifest['documents'] ?? null)) {
            throw new TeamPassRecoveryException('DOCUMENT_MANIFEST_INVALID');
        }
        $hash = $manifest['database']['sha256'] ?? '';
        if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            throw new TeamPassRecoveryException('DATABASE_CHECKSUM_REQUIRED');
        }
        $allowed = ['manifest.json', 'database/dump.sql'];
        $targets = [];
        foreach ($manifest['document_entries'] as $entry) {
            if (!is_array($entry)) {
                throw new TeamPassRecoveryException('DOCUMENT_MANIFEST_INVALID');
            }
            $path = tpRecoveryEntryPath((string) ($entry['package_path'] ?? ''));
            $kind = $entry['kind'] ?? '';
            $prefixes = ['item_attachment' => 'documents/item_attachments/',
                'kb_attachment' => 'documents/kb_attachments/', 'user_avatar' => 'documents/user_avatars/'];
            $name = (string) ($entry['stored_name'] ?? '');
            if (!isset($prefixes[$kind]) || $path !== $prefixes[$kind] . $name
                || tpBackupSafeStorageBasename($name) !== $name || $name === ''
                || preg_match('/^[a-f0-9]{64}$/D', (string) ($entry['sha256'] ?? '')) !== 1
                || isset($targets[$path])) {
                throw new TeamPassRecoveryException('DOCUMENT_MANIFEST_INVALID');
            }
            $targets[$path] = true;
            $allowed[] = $path;
        }
        foreach ($inventory['entries'] as $name => $size) {
            if (!in_array($name, $allowed, true)) {
                throw new TeamPassRecoveryException('BACKUP_UNEXPECTED_ENTRY');
            }
        }
        foreach ($manifest['documents'] as $summary) {
            if (is_array($summary) && (int) ($summary['missing_count'] ?? 0) > 0) {
                throw new TeamPassRecoveryException('DOCUMENTS_INCOMPLETE');
            }
        }
        if (!empty($manifest['warnings']) || !empty($manifest['documents']['warnings'])) {
            throw new TeamPassRecoveryException('BACKUP_HAS_WARNINGS');
        }
        $free = disk_free_space(dirname($sqlPath));
        if ($free === false || $free < $inventory['expanded_bytes'] + 67108864) {
            throw new TeamPassRecoveryException('RECOVERY_SPACE_INSUFFICIENT');
        }
    } finally {
        $zip->close();
    }
    $result = tpBackupValidatePackageZipAndExtractSql($zipPath, $sqlPath, true);
    if (empty($result['success'])) {
        throw new TeamPassRecoveryException((string) ($result['error_code'] ?? 'BACKUP_INVALID'));
    }
    return $manifest;
}

/** Prepare an offline, owner-only job. No target database data is changed here. */
function tpRecoveryPrepare(array $input): array
{
    tpRecoveryAssertFreshTarget();
    $lock = tpBackupOpenProtectedFile(tpRecoveryJobDirectory() . '/operation.lock', 'c+b', true);
    if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new TeamPassRecoveryException('RECOVERY_ALREADY_RUNNING');
    }
    if (file_exists(tpRecoveryJobDirectory() . '/pending.json')) {
        flock($lock, LOCK_UN);
        fclose($lock);
        throw new TeamPassRecoveryException('RECOVERY_ALREADY_PREPARED');
    }
    $connection = tpRecoveryConnectionOptions((array) ($input['connection'] ?? []));
    $url = tpRecoveryTargetUrl($input['url'] ?? null);
    $id = bin2hex(random_bytes(16));
    $directory = tpRecoveryJobDirectory($id);
    if (!mkdir($directory, 0700)) {
        throw new TeamPassRecoveryException('RECOVERY_DIRECTORY_UNWRITABLE');
    }
    tpRecoveryWritePrivate(tpRecoveryJobDirectory() . '/pending.json', json_encode(['id' => $id], JSON_THROW_ON_ERROR));
    try {
        tpRecoveryDecrypt((string) ($input['kit_path'] ?? ''), $directory . '/kit.zip', (string) ($input['kit_password'] ?? ''), 16777216);
        $zip = new ZipArchive();
        if ($zip->open($directory . '/kit.zip') !== true) {
            throw new TeamPassRecoveryException('RECOVERY_ARCHIVE_INVALID');
        }
        try {
            $kit = tpRecoveryReadKit($zip);
        } finally {
            $zip->close();
            unlink($directory . '/kit.zip');
        }
        $password = (string) ($input['backup_password'] ?? '');
        if ($password === '') {
            $password = $kit['backup_key'];
        }
        tpRecoveryDecrypt((string) ($input['backup_path'] ?? ''), $directory . '/backup.zip', $password);
        $manifest = tpRecoveryValidateBackup($directory . '/backup.zip', $directory . '/database.sql', 53687091200);
        $plan = tpRecoveryPlanUpgrade($manifest, TP_VERSION . '.' . TP_VERSION_MINOR, (string) UPGRADE_MIN_DATE);
        tpRecoveryUpgradeScripts($plan, TEAMPASS_ROOT . '/public/install');
        $sql = tpRecoveryInspectSql($directory . '/database.sql', $kit['prefix']);
        $db = tpRecoveryConnect($connection);
        try {
            if ($db->query('SHOW FULL TABLES')->num_rows !== 0) {
                throw new TeamPassRecoveryException('TARGET_DATABASE_NOT_EMPTY');
            }
            $server = $db->server_info;
            $isMaria = stripos($server, 'MariaDB') !== false;
            preg_match('/\d+\.\d+\.\d+/', $server, $serverVersion);
            if (empty($serverVersion) || version_compare($serverVersion[0], $isMaria ? MIN_MARIADB_VERSION : MIN_MYSQL_VERSION, '<')) {
                throw new TeamPassRecoveryException('TARGET_DATABASE_VERSION_UNSUPPORTED');
            }
        } finally {
            $db->close();
        }
        mkdir($directory . '/keys', 0700);
        tpRecoveryWritePrivate($directory . '/keys/' . $kit['secure_name'], $kit['master_key']);
        unset($kit['master_key'], $kit['backup_key']);
        $job = [
            'id' => $id, 'created_at' => time(), 'status' => 'prepared', 'error' => '',
            'connection' => $connection, 'kit' => $kit, 'manifest' => $manifest, 'plan' => $plan,
            'sql' => $sql, 'url' => rtrim($url, '/'),
            'sql_sha256' => hash_file('sha256', $directory . '/database.sql'),
            'zip_sha256' => hash_file('sha256', $directory . '/backup.zip'),
        ];
        tpRecoverySaveJob($job);
        return tpRecoveryPublicJob($job);
    } catch (Throwable $exception) {
        tpRecoveryRemoveJobFiles($id);
        throw $exception;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Return only non-sensitive status and compatibility data to the user interface. */
function tpRecoveryPublicJob(array $job): array
{
    return ['id' => $job['id'], 'status' => $job['status'], 'error' => $job['error'],
        'plan' => $job['plan'], 'tables' => count($job['sql']['tables']),
        'documents' => count($job['manifest']['document_entries'] ?? []),
        'created_at' => $job['created_at']];
}

/** Store an admin setting using prepared statements against the original validated prefix. */
function tpRecoverySetSetting(mysqli $db, string $prefix, string $name, string $value): void
{
    $statement = $db->prepare('UPDATE `' . $prefix . 'misc` SET valeur=? WHERE type=\'admin\' AND intitule=?');
    $statement->bind_param('ss', $value, $name);
    $statement->execute();
    $statement->close();
    $statement = $db->prepare('INSERT INTO `' . $prefix . 'misc` (type,intitule,valeur) SELECT \'admin\',?,? WHERE NOT EXISTS (SELECT 1 FROM `' . $prefix . 'misc` WHERE type=\'admin\' AND intitule=?)');
    $statement->bind_param('sss', $name, $value, $name);
    $statement->execute();
    $statement->close();
}

/** Verify the original master key against an authenticated value from the restored database. */
function tpRecoveryVerifyMaster(mysqli $db, array $job): void
{
    $prefix = $job['kit']['prefix'];
    $system = $db->query('SELECT pw FROM `' . $prefix . 'users` WHERE id=' . (int) TP_USER_ID)->fetch_assoc();
    try {
        $key = \Defuse\Crypto\Key::loadFromAsciiSafeString((string) file_get_contents(
            tpRecoveryJobDirectory($job['id']) . '/keys/' . $job['kit']['secure_name']
        ));
        if (!is_array($system) || \Defuse\Crypto\Crypto::decrypt((string) $system['pw'], $key) === '') {
            throw new RuntimeException();
        }
    } catch (Throwable $exception) {
        throw new TeamPassRecoveryException('MASTER_KEY_DATABASE_MISMATCH');
    }
}

/** Run replayable migrations in isolated CLI processes and require explicit success. */
function tpRecoveryUpgradeJob(array &$job): void
{
    $job['status'] = 'upgrading';
    tpRecoverySaveJob($job);
    foreach (tpRecoveryUpgradeScripts($job['plan'], TEAMPASS_ROOT . '/public/install') as $release => $file) {
        $process = new \Symfony\Component\Process\Process([
            PHP_BINARY, TEAMPASS_ROOT . '/app/scripts/recovery-migrate.php', '--job=' . $job['id'], '--release=' . $release,
        ], TEAMPASS_ROOT . '/public/install');
        $process->setTimeout(1800);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new TeamPassRecoveryException('UPGRADE_FAILED');
        }
        tpRecoveryCheckMigrationResult($process->getOutput());
        $job['completed_migrations'][] = $release;
        tpRecoverySaveJob($job);
    }
}

/** Restore into an empty database, validate it, and leave it ready for explicit activation. */
function tpRecoveryRun(string $id): array
{
    if (PHP_SAPI !== 'cli') {
        throw new TeamPassRecoveryException('RECOVERY_CLI_REQUIRED');
    }
    tpRecoveryAssertFreshTarget();
    $directory = tpRecoveryJobDirectory($id);
    $lock = tpBackupOpenProtectedFile(tpRecoveryJobDirectory() . '/operation.lock', 'c+b', true);
    if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new TeamPassRecoveryException('RECOVERY_ALREADY_RUNNING');
    }
    $job = tpRecoveryLoadJob($id);
    $db = null;
    try {
        if ($job['status'] !== 'prepared') {
            throw new TeamPassRecoveryException('RECOVERY_JOB_NOT_PREPARED');
        }
        if (!hash_equals($job['sql_sha256'], (string) hash_file('sha256', $directory . '/database.sql'))
            || !hash_equals($job['zip_sha256'], (string) hash_file('sha256', $directory . '/backup.zip'))
            || $job['plan']['target_version'] !== TP_VERSION . '.' . TP_VERSION_MINOR
            || $job['plan']['target_schema'] !== (string) UPGRADE_MIN_DATE) {
            throw new TeamPassRecoveryException('RECOVERY_INPUT_CHANGED');
        }
        $db = tpRecoveryConnect($job['connection']);
        if ($db->query('SHOW FULL TABLES')->num_rows !== 0) {
            throw new TeamPassRecoveryException('TARGET_DATABASE_NOT_EMPTY');
        }
        $job['status'] = 'importing';
        tpRecoverySaveJob($job);
        $db->query("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO,STRICT_ALL_TABLES'");
        $db->query('SET SESSION FOREIGN_KEY_CHECKS=0');
        foreach (tpRecoverySqlStatements($directory . '/database.sql') as $statement) {
            tpRecoverySqlStatementInfo($statement, $job['kit']['prefix']);
            $db->query($statement);
        }
        $db->query('SET SESSION FOREIGN_KEY_CHECKS=1');
        foreach ($job['sql']['rows'] as $table => $expected) {
            $actual = (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetch_row()[0];
            if ($actual !== $expected) {
                throw new TeamPassRecoveryException('DATABASE_ROW_COUNT_MISMATCH');
            }
        }
        tpRecoveryVerifyMaster($db, $job);
        tpRecoveryCheckDocuments($db, $job);
        $storedVersion = $db->query('SELECT valeur FROM `' . $job['kit']['prefix'] . "misc` WHERE type='admin' AND intitule='teampass_version'")->fetch_row();
        $sourceRelease = implode('.', array_slice(explode('.', $job['plan']['source_version']), 0, 3));
        $storedRelease = implode('.', array_slice(explode('.', (string) ($storedVersion[0] ?? '')), 0, 3));
        if ($sourceRelease !== $storedRelease) {
            throw new TeamPassRecoveryException('DATABASE_VERSION_METADATA_MISMATCH');
        }
        tpRecoveryUpgradeJob($job);
        tpRecoveryVerifyMaster($db, $job);
        $prefix = $job['kit']['prefix'];
        $presentTables = array_column($db->query('SHOW TABLES')->fetch_all(MYSQLI_NUM), 0);
        foreach (installAllowedActions('step5') as $table) {
            if (!in_array($table, ['utf8', 'migrateToUtf8mb4', 'checkAndSetInnoDBEngine'], true)
                && !in_array($prefix . $table, $presentTables, true)) {
                throw new TeamPassRecoveryException('TARGET_SCHEMA_INCOMPLETE');
            }
        }
        // Reuse the installer rules so URLs derived from the old host follow
        // the destination, while explicitly customized URLs stay untouched.
        require_once TEAMPASS_ROOT . '/public/install/tp.functions.php';
        refreshStoredSiteUrl($db, $prefix, $job['url']);
        // Do not replay stale jobs, consumed links or revoked sessions after a time rollback.
        foreach (['maintenance_mode' => '1', 'lapr_enabled' => '0', 'bck_scheduled_enabled' => '0',
            'bck_externalized_enabled' => '0', 'cpassman_url' => $job['url'],
            'cpassman_dir' => TEAMPASS_ROOT, 'path_to_upload_folder' => TEAMPASS_ROOT . '/storage/upload',
            'path_to_files_folder' => TEAMPASS_ROOT . '/storage/files',
            'teampass_version' => TP_VERSION,
            'upgrade_timestamp' => (string) UPGRADE_MIN_DATE, 'recovery_pending_checks' => '1', 'recovery_job_id' => $job['id'],
            'recovery_generation' => bin2hex(random_bytes(16))] as $name => $value) {
            tpRecoverySetSetting($db, $prefix, $name, $value);
        }
        $tables = array_column($db->query('SHOW TABLES')->fetch_all(MYSQLI_NUM), 0);
        foreach (['api_sessions', 'api_tokens', 'tokens', 'websocket_tokens', 'websocket_connections',
            'background_subtasks', 'background_tasks', 'api_idempotency'] as $ephemeral) {
            if (in_array($prefix . $ephemeral, $tables, true)) {
                $db->query('DELETE FROM `' . $prefix . $ephemeral . '`');
            }
        }
        $db->query('UPDATE `' . $prefix . "users` SET key_tempo='', session_end='0'");
        $process = new \Symfony\Component\Process\Process([
            PHP_BINARY, TEAMPASS_ROOT . '/app/scripts/recovery-migrate.php', '--job=' . $job['id'], '--release=verify',
        ], TEAMPASS_ROOT . '/public/install');
        $process->setTimeout(1800);
        $process->run();
        if (!$process->isSuccessful()) {
            $reason = trim($process->getErrorOutput());
            throw new TeamPassRecoveryException(in_array($reason, ['SYSTEM_PRIVATE_KEY_INVALID', 'ITEM_DECRYPTION_FAILED',
                'RECOVERY_LINK_INVALIDATION_FAILED'], true) ? $reason : 'RECOVERY_CRYPTO_VERIFICATION_FAILED');
        }
        tpRecoveryCheckMigrationResult($process->getOutput());
        $job['status'] = 'verified';
        tpRecoverySaveJob($job);
        return tpRecoveryPublicJob($job);
    } catch (Throwable $exception) {
        $job['status'] = 'failed';
        $job['error'] = $exception instanceof TeamPassRecoveryException ? $exception->reason : 'RECOVERY_DATABASE_OPERATION_FAILED';
        tpRecoverySaveJob($job);
        throw new TeamPassRecoveryException($job['error']);
    } finally {
        if ($db instanceof mysqli) {
            $db->close();
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Check document references against the imported database, not only the archive manifest. */
function tpRecoveryCheckDocuments(mysqli $db, array $job): void
{
    $prefix = $job['kit']['prefix'];
    $entries = $job['manifest']['document_entries'] ?? [];
    $files = [];
    $kb = [];
    $avatars = [];
    foreach ($entries as $entry) {
        if ($entry['kind'] === 'item_attachment') {
            $files[(string) $entry['file_id']] = $entry;
        } elseif ($entry['kind'] === 'kb_attachment') {
            $kb[(string) $entry['misc_id']] = $entry;
        } elseif ($entry['kind'] === 'user_avatar') {
            $avatars[(string) $entry['user_id'] . ':' . $entry['role']] = $entry['stored_name'];
        }
    }
    foreach ($db->query('SELECT id,id_item,file FROM `' . $prefix . 'files`')->fetch_all(MYSQLI_ASSOC) as $row) {
        $entry = $files[(string) $row['id']] ?? [];
        if ((string) ($entry['item_id'] ?? '') !== (string) $row['id_item'] || ($entry['db_file'] ?? null) !== $row['file']) {
            throw new TeamPassRecoveryException('DOCUMENTS_DATABASE_MISMATCH');
        }
    }
    foreach ($db->query('SELECT increment_id,valeur FROM `' . $prefix . "misc` WHERE type='kb_attachment'")->fetch_all(MYSQLI_ASSOC) as $row) {
        $payload = json_decode($row['valeur'], true);
        if (($kb[(string) $row['increment_id']]['stored_name'] ?? null) !== ($payload['stored_name'] ?? '')) {
            throw new TeamPassRecoveryException('DOCUMENTS_DATABASE_MISMATCH');
        }
    }
    foreach ($db->query('SELECT id,avatar,avatar_thumb FROM `' . $prefix . 'users`')->fetch_all(MYSQLI_ASSOC) as $row) {
        foreach (['avatar', 'avatar_thumb'] as $role) {
            if (($row[$role] ?? '') !== '' && ($avatars[$row['id'] . ':' . $role] ?? null) !== $row[$role]) {
                throw new TeamPassRecoveryException('DOCUMENTS_DATABASE_MISMATCH');
            }
        }
    }
}

/** Remove only a validated private job tree; symlinks are unlinked, never followed. */
function tpRecoveryRemoveJobFiles(string $id): void
{
    $directory = tpRecoveryJobDirectory($id);
    if (!is_dir($directory)) {
        return;
    }
    // scandir closes the directory handle before deletion, including on Windows.
    $remove = static function (string $path) use (&$remove): void {
        $entries = scandir($path);
        if ($entries === false) {
            throw new TeamPassRecoveryException('RECOVERY_CLEANUP_FAILED');
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . '/' . $entry;
            if (is_dir($child) && !is_link($child)) {
                $remove($child);
            } elseif (!unlink($child)) {
                throw new TeamPassRecoveryException('RECOVERY_CLEANUP_FAILED');
            }
        }
        if (!rmdir($path)) {
            throw new TeamPassRecoveryException('RECOVERY_CLEANUP_FAILED');
        }
    };
    $remove($directory);
    $pendingPath = tpRecoveryJobDirectory() . '/pending.json';
    if (is_file($pendingPath) && !is_link($pendingPath)) {
        $pending = json_decode((string) file_get_contents($pendingPath), true);
        if (($pending['id'] ?? '') === $id) {
            unlink($pendingPath);
        }
    }
}

/** Build a fresh configuration with destination credentials and the original encryption key. */
function tpRecoverySettingsPhp(array $job, string $masterKey): string
{
    $connection = $job['connection'];
    $values = [
        'DB_HOST' => $connection['host'], 'DB_USER' => $connection['user'],
        'DB_PASSWD' => \Defuse\Crypto\Crypto::encrypt($connection['password'], \Defuse\Crypto\Key::loadFromAsciiSafeString($masterKey)),
        'DB_NAME' => $connection['database'], 'DB_PREFIX' => $job['kit']['prefix'],
        'DB_PORT' => $connection['port'], 'DB_ENCODING' => 'utf8mb4',
        'DB_SSL' => false, 'DB_CONNECT_OPTIONS' => [MYSQLI_OPT_CONNECT_TIMEOUT => 10],
        'SECUREFILE' => $job['kit']['secure_name'],
    ];
    foreach ($job['kit']['legacy_constants'] as $name => $value) {
        if (!is_string($value)) {
            throw new TeamPassRecoveryException('RECOVERY_CONFIG_INVALID');
        }
        $values[$name] = $value;
    }
    $php = "<?php\n// Rebuilt by TeamPass disaster recovery.\n";
    foreach ($values as $name => $value) {
        $php .= 'define(' . var_export($name, true) . ', ' . var_export($value, true) . ");\n";
    }
    return $php;
}

/** Publish validated documents and configuration last, keeping the restored service in maintenance. */
function tpRecoveryActivate(string $id): array
{
    if (PHP_SAPI !== 'cli') {
        throw new TeamPassRecoveryException('RECOVERY_CLI_REQUIRED');
    }
    tpRecoveryAssertFreshTarget();
    $lock = tpBackupOpenProtectedFile(tpRecoveryJobDirectory() . '/operation.lock', 'c+b', true);
    if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new TeamPassRecoveryException('RECOVERY_ALREADY_RUNNING');
    }
    try {
        $job = tpRecoveryLoadJob($id);
        $directory = tpRecoveryJobDirectory($id);
        if ($job['status'] !== 'verified'
            || !hash_equals($job['zip_sha256'], (string) hash_file('sha256', $directory . '/backup.zip'))) {
            throw new TeamPassRecoveryException('RECOVERY_JOB_NOT_VERIFIED');
        }
        $db = tpRecoveryConnect($job['connection']);
        try {
            tpRecoveryVerifyMaster($db, $job);
        } finally {
            $db->close();
        }
        $job['status'] = 'activating';
        tpRecoverySaveJob($job);
        $settings = ['path_to_upload_folder' => TEAMPASS_ROOT . '/storage/upload',
            'path_to_files_folder' => TEAMPASS_ROOT . '/storage/files'];
        $documents = tpBackupRestorePackageDocumentsFromZip($directory . '/backup.zip', $job['manifest'], $settings, false);
        if (empty($documents['success'])) {
            throw new TeamPassRecoveryException('DOCUMENT_PUBLICATION_FAILED');
        }
        $master = (string) file_get_contents($directory . '/keys/' . $job['kit']['secure_name']);
        $config = tpRecoverySettingsPhp($job, $master);
        $csrf = require TEAMPASS_ROOT . '/app/includes/libraries/csrfp/libs/csrfp.config.sample.php';
        $csrf['CSRFP_TOKEN'] = bin2hex(random_bytes(25));
        $csrf['jsUrl'] = './assets/lib/csrfp/csrfprotector.js';
        tpRecoveryWritePrivate(TEAMPASS_ROOT . '/app/includes/libraries/csrfp/libs/csrfp.config.php', "<?php\nreturn " . var_export($csrf, true) . ";\n");
        tpRecoveryWritePrivate(TEAMPASS_ROOT . '/secrets/' . $job['kit']['secure_name'], $master, true);
        // settings.php is the commit marker: no login or normal bootstrap is possible before it exists.
        tpRecoveryWritePrivate(TEAMPASS_ROOT . '/app/config/settings.php', $config, true);
        \TeampassClasses\ConfigManager\ConfigManager::invalidateCache();
        $job['status'] = 'complete';
        $result = tpRecoveryPublicJob($job);
        // Keep only a non-sensitive receipt. Decrypted dumps, keys and credentials are removed.
        tpRecoveryRemoveJobFiles($id);
        mkdir($directory, 0700);
        tpRecoveryWritePrivate($directory . '/receipt.json', json_encode($result, JSON_THROW_ON_ERROR));
        @unlink(tpRecoveryJobDirectory() . '/operator.json');
        return $result;
    } catch (Throwable $exception) {
        if (isset($job) && is_file(tpRecoveryJobDirectory($id) . '/job.json')) {
            $job['status'] = 'failed';
            $job['error'] = $exception instanceof TeamPassRecoveryException ? $exception->reason : 'RECOVERY_ACTIVATION_FAILED';
            tpRecoverySaveJob($job);
        }
        throw $exception;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Discard private staging only when no installer/recovery operation is using it. */
function tpRecoveryDiscard(string $id): void
{
    $lock = tpBackupOpenProtectedFile(tpRecoveryJobDirectory() . '/operation.lock', 'c+b', true);
    if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new TeamPassRecoveryException('RECOVERY_ALREADY_RUNNING');
    }
    try {
        $job = tpRecoveryLoadJob($id);
        if (!in_array($job['status'], ['prepared', 'failed', 'importing', 'upgrading', 'verified'], true)) {
            throw new TeamPassRecoveryException('RECOVERY_JOB_BUSY');
        }
        tpRecoveryRemoveJobFiles($id);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

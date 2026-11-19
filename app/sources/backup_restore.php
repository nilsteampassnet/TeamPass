<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

require_once __DIR__ . '/recovery_sql.php';
require_once __DIR__ . '/restore_guard.php';

/** Map stable restore diagnostics to user-facing translations without driver messages. */
function tpBackupRestoreErrorLanguageKey(string $reason): string
{
    return match ($reason) {
        'MASTER_KEY_DATABASE_MISMATCH' => 'bck_restore_master_key_mismatch',
        'SCHEMA_MISMATCH', 'DATABASE_VERSION_METADATA_MISMATCH', 'MISSING_SCHEMA' => 'bck_restore_schema_mismatch',
        'SQL_REQUIRED_TABLE_MISSING' => 'bck_restore_tables_missing',
        default => 'bck_restore_validation_failed',
    };
}

/** Read a private, token-bound retry journal without consulting a potentially partial database. */
function tpBackupReadRestoreJournal(string $tokenHash, ?string $path = null): array
{
    $path = $path ?? tpBackupRestoreGuardPath();
    if (is_link($path) || !is_file($path) || filesize($path) > 8388608
        || (PHP_OS_FAMILY !== 'Windows' && (fileperms($path) & 0077) !== 0)) {
        throw new TeamPassRecoveryException('RESTORE_RESUME_UNAVAILABLE');
    }
    $journal = json_decode((string) file_get_contents($path), true, 64);
    if (!is_array($journal) || ($journal['version'] ?? null) !== 1
        || !is_string($journal['token_hash'] ?? null) || !hash_equals($journal['token_hash'], $tokenHash)
        || !is_array($journal['payload'] ?? null) || !is_array($journal['settings'] ?? null)
        || !is_array($journal['key_candidates'] ?? null)) {
        throw new TeamPassRecoveryException('RESTORE_RESUME_UNAVAILABLE');
    }
    return $journal;
}

/** Persist only encrypted key candidates in an owner-only hold before importing SQL. */
function tpBackupWriteRestoreJournal(array $journal, ?string $path = null): void
{
    $json = json_encode($journal, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $path = $path ?? tpBackupRestoreGuardPath();
    $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
    $handle = tpBackupOpenProtectedFile($temporary, 'x+b', true);
    if (!is_resource($handle)) {
        throw new TeamPassRecoveryException('RESTORE_HOLD_FAILED');
    }
    try {
        if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
            throw new TeamPassRecoveryException('RESTORE_HOLD_FAILED');
        }
        if (function_exists('fsync') && !fsync($handle)) {
            throw new TeamPassRecoveryException('RESTORE_HOLD_FAILED');
        }
        fclose($handle);
        $handle = null;
        if (file_exists($path) || is_link($path) || !rename($temporary, $path)) {
            throw new TeamPassRecoveryException('RESTORE_HOLD_FAILED');
        }
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

/** Re-read archive bytes at each security boundary, including after decryption and preflight. */
function tpBackupAssertRestoreFileUnchanged(string $path, string $expectedHash): void
{
    if (preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1
        || !hash_equals($expectedHash, tpBackupFileSha256($path))) {
        throw new TeamPassRecoveryException('RESTORE_INPUT_CHANGED');
    }
}

/** Read the literal values of a validated TeamPass INSERT without evaluating SQL. */
function tpBackupSqlValues(string $sql): array
{
    $start = strpos($sql, ' VALUES(');
    if ($start === false) {
        throw new TeamPassRecoveryException('SQL_VALUES_UNSUPPORTED');
    }
    $input = substr($sql, $start + 8);
    if (!tpRecoverySqlLiteralValues($input)) {
        throw new TeamPassRecoveryException('SQL_VALUES_UNSUPPORTED');
    }
    $values = [];
    $length = strlen($input);
    $offset = 0;
    while ($offset < $length - 2) {
        if (substr($input, $offset, 4) === 'NULL') {
            $values[] = null;
            $offset += 4;
        } else {
            $offset++;
            $value = '';
            while ($offset < $length) {
                $byte = $input[$offset];
                $offset++;
                if ($byte === '"') {
                    break;
                }
                if ($byte === '\\') {
                    $escaped = $input[$offset];
                    $offset++;
                    $byte = match ($escaped) { '0' => "\0", 'b' => "\x08", 'n' => "\n",
                        'r' => "\r", 't' => "\t", 'Z' => "\x1a", default => $escaped };
                }
                $value .= $byte;
            }
            $values[] = $value;
        }
        if (($input[$offset] ?? '') === ',') {
            $offset++;
        }
    }
    return $values;
}

/** Set the session dialect expected by historical and current TeamPass dump literals. */
function tpBackupRestoreSqlMode(string $mode): string
{
    $flags = array_filter(explode(',', $mode), static fn (string $flag): bool =>
        !in_array(strtoupper(trim($flag)), ['ANSI_QUOTES', 'NO_BACKSLASH_ESCAPES'], true));
    return implode(',', array_unique(array_merge($flags, ['NO_AUTO_VALUE_ON_ZERO', 'STRICT_ALL_TABLES'])));
}

/** Validate the entire dump, its embedded release/schema and its original master-key proof. */
function tpBackupInspectRestoreSql(
    string $path,
    string $prefix,
    string $expectedSchema,
    string $expectedVersion,
    array $manifest = [],
    ?callable $masterValidator = null,
    int $systemUserId = -1
): array {
    if ($manifest !== [] && (string) ($manifest['teampass']['schema_level'] ?? '') !== $expectedSchema) {
        throw new TeamPassRecoveryException('SCHEMA_MISMATCH');
    }
    $columns = [];
    $settings = [];
    $masterVerified = false;
    $inventory = tpRecoveryInspectSql($path, $prefix, static function (string $sql, array $info) use (
        $prefix, $systemUserId, $masterValidator, &$columns, &$settings, &$masterVerified
    ): void {
        if (!in_array($info['table'], [$prefix . 'misc', $prefix . 'users'], true)) {
            return;
        }
        if ($info['type'] === 'create') {
            $definition = substr($sql, strpos($sql, '(') + 1);
            preg_match_all('/(?:^|\n)\s*`([A-Za-z0-9_]+)`\s+[A-Za-z]/', $definition, $matches);
            $columns[$info['table']] = $matches[1];
        } elseif ($info['type'] === 'insert') {
            $values = tpBackupSqlValues($sql);
            $names = $columns[$info['table']] ?? [];
            if (count($names) !== count($values)) {
                throw new TeamPassRecoveryException('SQL_COLUMNS_INVALID');
            }
            $row = array_combine($names, $values);
            if ($info['table'] === $prefix . 'misc' && ($row['type'] ?? '') === 'admin') {
                $name = (string) ($row['intitule'] ?? '');
                if (in_array($name, ['teampass_version', 'teampass_version_full', 'teampass_schema_level'], true)) {
                    $settings[$name] = (string) ($row['valeur'] ?? '');
                }
            } elseif ($info['table'] === $prefix . 'users' && (int) ($row['id'] ?? 0) === $systemUserId && $masterValidator !== null) {
                $masterVerified = $masterValidator((string) ($row['pw'] ?? '')) === true;
            }
        }
    });
    $sourceVersion = $settings['teampass_version_full'] ?? $settings['teampass_version'] ?? '';
    $releaseLine = static fn (string $version): string => implode('.', array_slice(explode('.', $version), 0, 3));
    $embeddedSchema = $settings['teampass_schema_level'] ?? ($manifest['teampass']['schema_level'] ?? '');
    // Database release markers record the last migration, not necessarily the files
    // that created this archive. The authenticated schema is the boundary when present.
    if ($sourceVersion === '' || ($embeddedSchema === ''
        && $releaseLine($settings['teampass_version'] ?? $sourceVersion) !== $releaseLine($expectedVersion))) {
        throw new TeamPassRecoveryException('DATABASE_VERSION_METADATA_MISMATCH');
    }
    if ($embeddedSchema !== '' && $embeddedSchema !== $expectedSchema) {
        throw new TeamPassRecoveryException('SCHEMA_MISMATCH');
    }
    if ($masterValidator !== null && !$masterVerified) {
        throw new TeamPassRecoveryException('MASTER_KEY_DATABASE_MISMATCH');
    }
    return $inventory;
}

/** Validate a restore against the running instance without changing application data. */
function tpBackupPreflightRestoreSql(string $path, array $manifest = []): array
{
    $key = \Defuse\Crypto\Key::loadFromAsciiSafeString((string) file_get_contents(TEAMPASS_SECRETS . '/' . SECUREFILE));
    $inventory = tpBackupInspectRestoreSql($path, prefixTable(''), tpGetSchemaLevel(), tpGetTpFilesVersion(), $manifest,
        static function (string $ciphertext) use ($key): bool {
            try {
                return \Defuse\Crypto\Crypto::decrypt($ciphertext, $key) !== '';
            } catch (Throwable $exception) {
                return false;
            }
        }, (int) TP_USER_ID);
    foreach (DB::query('SHOW TABLES') as $row) {
        $table = (string) reset($row);
        if (str_starts_with($table, prefixTable('')) && !in_array($table, $inventory['tables'], true)) {
            throw new TeamPassRecoveryException('SQL_REQUIRED_TABLE_MISSING');
        }
    }
    return $inventory;
}

/** Invalidate credentials and replayable work after a database time rollback. */
function tpBackupInvalidateRestoredState(): void
{
    require_once __DIR__ . '/recovery_verification.php';
    $tables = [];
    foreach (DB::query('SHOW TABLES') as $row) {
        $tables[] = (string) reset($row);
    }
    DB::startTransaction();
    try {
        foreach (['api_sessions', 'api_tokens', 'device_pairings', 'tokens', 'websocket_tokens',
            'websocket_connections', 'background_subtasks', 'background_tasks', 'api_idempotency'] as $table) {
            if (in_array(prefixTable($table), $tables, true)) {
                DB::query('DELETE FROM %b', prefixTable($table));
            }
        }
        if (in_array(prefixTable('api'), $tables, true)) {
            // A JWT without a per-token session can still fall back to this legacy row.
            DB::update(prefixTable('api'), ['encrypted_private_key' => null, 'session_key_salt' => null,
                'session_key' => null, 'session_aes_key' => null, 'timestamp' => ''], '1=1');
            DB::update(prefixTable('api'), ['value' => ''], 'type=%s', 'user');
        }
        DB::query('UPDATE %b SET key_tempo=%s, session_end=%s', prefixTable('users'), '', '0');
        DB::delete(prefixTable('misc'), 'type IN %ls', ['restore_authorization_cli', 'temp_file']);
        foreach (['maintenance_mode' => '1', 'lapr_enabled' => '0', 'bck_scheduled_enabled' => '0',
            'bck_externalized_enabled' => '0', 'recovery_generation' => bin2hex(random_bytes(16))] as $name => $value) {
            $type = str_starts_with($name, 'bck_') ? 'settings' : 'admin';
            DB::update(prefixTable('misc'), ['valeur' => $value], 'type=%s AND intitule=%s', $type, $name);
            if (DB::affectedRows() === 0 && !DB::queryFirstField('SELECT 1 FROM %b WHERE type=%s AND intitule=%s', prefixTable('misc'), $type, $name)) {
                DB::insert(prefixTable('misc'), ['type' => $type, 'intitule' => $name, 'valeur' => $value]);
            }
        }
        DB::commit();
    } catch (Throwable $exception) {
        DB::rollback();
        throw new TeamPassRecoveryException('RESTORE_INVALIDATION_FAILED');
    }
    if (in_array(prefixTable('otv'), $tables, true)) {
        tpRecoveryInvalidateSharedLinks();
    }
    \TeampassClasses\ConfigManager\ConfigManager::invalidateCache();
}

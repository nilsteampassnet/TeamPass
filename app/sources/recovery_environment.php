<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_environment.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

require_once __DIR__ . '/recovery_logic.php';

/**
 * Read the current server-side operator grant; a browser deadline alone is not authorization.
 * @return array{token_hash:string,expires_at:int}
 */
function tpRecoveryReadOperator(string $path, ?int $now = null): array
{
    if (is_link($path) || !is_file($path)) { throw new TeamPassRecoveryException('OPERATOR_CODE_REQUIRED'); }
    if (!is_readable($path)) { throw new TeamPassRecoveryException('OPERATOR_CODE_UNREADABLE'); }
    $contents = @file_get_contents($path, false, null, 0, 4097);
    if (!is_string($contents) || strlen($contents) > 4096) {
        throw new TeamPassRecoveryException('OPERATOR_CODE_REQUIRED');
    }
    try {
        $operator = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new TeamPassRecoveryException('OPERATOR_CODE_REQUIRED');
    }
    if (!is_array($operator) || !is_string($operator['token_hash'] ?? null)
        || preg_match('/^[a-f0-9]{64}$/D', $operator['token_hash']) !== 1
        || !is_int($operator['expires_at'] ?? null)) {
        throw new TeamPassRecoveryException('OPERATOR_CODE_REQUIRED');
    }
    if ($operator['expires_at'] <= ($now ?? time())) {
        throw new TeamPassRecoveryException('OPERATOR_CODE_EXPIRED');
    }
    return ['token_hash' => $operator['token_hash'], 'expires_at' => $operator['expires_at']];
}

/** Resolve the process identity, never the owner of the PHP script. */
function tpRecoveryServiceAccount(): ?string
{
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $account = posix_getpwuid(posix_geteuid());
        return is_array($account) && !empty($account['name']) ? (string) $account['name'] : null;
    }
    return null;
}

/** Find the CLI next to the running PHP installation without executing a shell. */
function tpRecoveryPhpCliBinary(): string
{
    $windows = PHP_OS_FAMILY === 'Windows';
    $candidates = $windows
        ? [dirname(PHP_BINARY) . '/php.exe', PHP_BINDIR . '/php.exe']
        : [PHP_BINDIR . '/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, PHP_BINDIR . '/php'];
    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }
    return $windows ? 'php.exe' : 'php';
}

/** Quote a displayed command argument for the server's shell; never execute it. */
function tpRecoveryCommandArgument(string $value, string $os): string
{
    return $os === 'Windows'
        ? "'" . str_replace("'", "''", $value) . "'"
        : "'" . str_replace("'", "'\\''", $value) . "'";
}

/** Build a copyable command using the web process account and absolute paths. */
function tpRecoveryCommandBase(?string $account = null, ?string $os = null, ?string $php = null, ?string $root = null): string
{
    $os ??= PHP_OS_FAMILY;
    $account ??= tpRecoveryServiceAccount();
    $php ??= tpRecoveryPhpCliBinary();
    $root ??= TEAMPASS_ROOT;
    $command = tpRecoveryCommandArgument($php, $os) . ' '
        . tpRecoveryCommandArgument(rtrim($root, '/\\') . '/app/scripts/disaster-recovery.php', $os);
    return $os === 'Windows' ? '& ' . $command
        : 'sudo -u ' . tpRecoveryCommandArgument($account ?? 'PHP_SERVICE_ACCOUNT', $os) . ' -- ' . $command;
}

/** Root-created private recovery files cannot be read by an unprivileged web worker. */
function tpRecoveryIsRootIdentity(string $os, ?int $uid): bool
{
    return $os !== 'Windows' && $uid === 0;
}

/** Refuse a privileged CLI writer before it creates an inaccessible operator file or job. */
function tpRecoveryAssertServiceAccount(): void
{
    $uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
    if (tpRecoveryIsRootIdentity(PHP_OS_FAMILY, $uid)) {
        throw new TeamPassRecoveryException('RECOVERY_SERVICE_ACCOUNT_REQUIRED');
    }
}

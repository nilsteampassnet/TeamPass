<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * This program is free software under the GNU General Public License, version 3
 * or later, without any warranty. See <https://www.gnu.org/licenses/>.
 */

namespace TeampassClasses\EmailService;

use DB;

/** Encrypt before persistence; keep token data out of the general settings cache. */
final class DatabaseEmailTokenStore implements EmailTokenStoreInterface
{
    /** Bind this store to one immutable configuration revision. */
    public function __construct(private readonly string $revision)
    {
        if (preg_match('/\A[a-f0-9]{32}\z/', $revision) !== 1) {
            throw new EmailTransportException(EmailSendResult::failure('configuration'));
        }
    }

    /** @return array{access_token:string, refresh_token:string, expires_at:int}|null */
    public function load(string $cacheKey): ?array
    {
        $this->assertActiveRevision();
        $row = DB::queryFirstRow(
            'SELECT access_token, refresh_token, expires_at FROM ' . prefixTable('email_oauth_state') . '
             WHERE cache_key = %s AND config_revision = %s', $cacheKey, $this->revision
        );
        if ($row === null || $row === false) {
            return null;
        }

        return [
            'access_token' => $this->decrypt($row['access_token']),
            'refresh_token' => $this->decrypt($row['refresh_token']),
            'expires_at' => (int) $row['expires_at'],
        ];
    }

    /** Acquire an expiring lease using the database clock and a conditional update. */
    public function claim(string $cacheKey, string $owner, int $leaseSeconds): bool
    {
        $this->assertActiveRevision();
        if ($leaseSeconds < 1 || $leaseSeconds > 600 || preg_match('/\A[a-f0-9]{32}\z/', $owner) !== 1) {
            throw new EmailTransportException(EmailSendResult::failure('configuration'));
        }
        DB::query(
            'INSERT IGNORE INTO ' . prefixTable('email_oauth_state') . '
             (cache_key, config_revision, access_token, refresh_token, access_token_hash, expires_at, lease_owner, lease_until, updated_at)
             VALUES (%s, %s, %s, %s, %s, 0, %s, 0, UNIX_TIMESTAMP())',
            $cacheKey, $this->revision, '', '', '', ''
        );
        DB::query(
            'UPDATE ' . prefixTable('email_oauth_state') . '
             SET lease_owner = %s, lease_until = UNIX_TIMESTAMP() + %i
             WHERE cache_key = %s AND config_revision = %s AND lease_until <= UNIX_TIMESTAMP()',
            $owner, $leaseSeconds, $cacheKey, $this->revision
        );

        return DB::affectedRows() === 1;
    }

    /** Encrypt all secrets before issuing a write, and refuse expired or replaced ownership. */
    public function save(string $cacheKey, string $owner, #[\SensitiveParameter] array $token): bool
    {
        if (!isset($token['access_token'], $token['expires_at']) || !is_string($token['access_token'])
            || $token['access_token'] === '' || !is_int($token['expires_at']) || $token['expires_at'] <= time()
            || !is_string($token['refresh_token'] ?? '')
        ) {
            throw new EmailTransportException(EmailSendResult::failure('configuration'));
        }
        $access = $this->encrypt($token['access_token']);
        $refresh = $this->encrypt($token['refresh_token'] ?? '');
        DB::query(
            'UPDATE ' . prefixTable('email_oauth_state') . '
             SET access_token = %s, refresh_token = %s, access_token_hash = %s, expires_at = %i, updated_at = UNIX_TIMESTAMP()
             WHERE cache_key = %s AND config_revision = %s AND lease_owner = %s AND lease_until > UNIX_TIMESTAMP()
             AND EXISTS (SELECT 1 FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s AND valeur = %s)',
            $access, $refresh, hash('sha256', $token['access_token']), $token['expires_at'],
            $cacheKey, $this->revision, $owner, 'admin', 'email_config_revision', $this->revision
        );

        return DB::affectedRows() === 1;
    }

    /** A late worker cannot release a newer worker's ownership. */
    public function release(string $cacheKey, string $owner): void
    {
        DB::query(
            'UPDATE ' . prefixTable('email_oauth_state') . ' SET lease_owner = %s, lease_until = 0
             WHERE cache_key = %s AND config_revision = %s AND lease_owner = %s',
            '', $cacheKey, $this->revision, $owner
        );
    }

    /** A stale 401 response must not discard an access token renewed by another process. */
    public function invalidateAccess(string $cacheKey, #[\SensitiveParameter] string $accessToken): void
    {
        DB::query(
            'UPDATE ' . prefixTable('email_oauth_state') . ' SET access_token = %s, access_token_hash = %s, expires_at = 0
             WHERE cache_key = %s AND config_revision = %s AND access_token_hash = %s',
            '', '', $cacheKey, $this->revision, hash('sha256', $accessToken)
        );
    }

    private function assertActiveRevision(): void
    {
        $active = DB::queryFirstField(
            'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s',
            'admin', 'email_config_revision'
        );
        if (!is_string($active) || !hash_equals($this->revision, $active)) {
            throw new EmailTransportException(EmailSendResult::failure('configuration'));
        }
    }

    private function encrypt(#[\SensitiveParameter] string $value): string
    {
        if ($value === '') {
            return '';
        }
        try {
            $result = \cryption($value, '', 'encrypt');
            if (($result['error'] ?? true) === false && ($result['string'] ?? '') !== '') {
                return $result['string'];
            }
        } catch (\Throwable $e) {
            // No plaintext, provider response, or nested exception may escape this boundary.
        }

        throw new EmailTransportException(EmailSendResult::failure('configuration'));
    }

    private function decrypt(#[\SensitiveParameter] string $value): string
    {
        if ($value === '') {
            return '';
        }
        try {
            if (\tpIsInstanceKeyCiphertext($value)) {
                $result = \cryption($value, '', 'decrypt');
                if (($result['error'] ?? true) === false) {
                    return $result['string'];
                }
            }
        } catch (\Throwable $e) {
            // Unlike legacy SMTP settings, OAuth token storage never accepts plaintext.
        }

        throw new EmailTransportException(EmailSendResult::failure('configuration'));
    }
}

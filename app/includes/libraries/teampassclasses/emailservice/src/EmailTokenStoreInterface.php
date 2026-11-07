<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * This program is free software under the GNU General Public License, version 3
 * or later, without any warranty. See <https://www.gnu.org/licenses/>.
 */

namespace TeampassClasses\EmailService;

/** Instance-scoped token persistence and short renewable ownership leases. */
interface EmailTokenStoreInterface
{
    /** @return array{access_token:string, refresh_token:string, expires_at:int}|null */
    public function load(string $cacheKey): ?array;

    /** Claim renewal without holding a database transaction during network I/O. */
    public function claim(string $cacheKey, string $owner, int $leaseSeconds): bool;

    /** Persist token data only while this owner and configuration are still active. */
    public function save(string $cacheKey, string $owner, #[\SensitiveParameter] array $token): bool;

    /** Release only this caller's lease, never a replacement owner's lease. */
    public function release(string $cacheKey, string $owner): void;

    /** Invalidate only the access token rejected by the provider, preserving a newer token. */
    public function invalidateAccess(string $cacheKey, #[\SensitiveParameter] string $accessToken): void;
}

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
 * DB-free decisions used by the persistent API idempotency adapter.
 *
 * @file      api_idempotency_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require_once __DIR__ . '/item_revisions_logic.php';

/**
 * Canonicalize a request value before hashing its functional intent.
 *
 * Associative keys are sorted recursively. List order remains significant.
 *
 * @param mixed $value Value to canonicalize
 * @return mixed Canonical value
 */
function apiIdempotencyCanonicalize($value)
{
    if (is_array($value) === false) {
        return $value;
    }

    if (array_is_list($value)) {
        return array_map('apiIdempotencyCanonicalize', $value);
    }

    ksort($value, SORT_STRING);
    foreach ($value as $key => $entry) {
        $value[$key] = apiIdempotencyCanonicalize($entry);
    }

    return $value;
}

/**
 * Compute the replay expiry aligned with the configured offline-sync window.
 *
 * Zero means that neither the revision journal nor idempotency replays expire.
 * The overflow guard also makes unexpectedly large administrator values safe.
 *
 * @param int   $now           Current Unix timestamp
 * @param mixed $rawWindowDays Configured offline-sync window in days
 * @return int Expiry timestamp, 0 when the window is unlimited
 */
function apiIdempotencyReplayExpiry(int $now, $rawWindowDays): int
{
    if ($now < 0) {
        throw new InvalidArgumentException('Current timestamp must not be negative.');
    }

    $windowDays = offlineSyncResolveWindowDays($rawWindowDays);
    if ($windowDays === 0) {
        return 0;
    }

    $secondsPerDay = 86400;
    if ($windowDays > intdiv(PHP_INT_MAX - $now, $secondsPerDay)) {
        return PHP_INT_MAX;
    }

    return $now + ($windowDays * $secondsPerDay);
}

/**
 * Build the reservation returned after an insert or successful lease takeover.
 *
 * @param int    $recordId          Idempotency record identifier
 * @param string $ownerToken        Raw request-local owner token
 * @param string $requestFingerprint HMAC of the functional request intent
 * @return array{state:string,id:int,owner_token:string,request_fingerprint:string}
 */
function apiIdempotencyAcquiredDecision(
    int $recordId,
    string $ownerToken,
    string $requestFingerprint
): array {
    if ($recordId <= 0 || $ownerToken === '' || $requestFingerprint === '') {
        throw new InvalidArgumentException('An acquired idempotency reservation must be complete.');
    }

    return [
        'state' => 'acquired',
        'id' => $recordId,
        'owner_token' => $ownerToken,
        'request_fingerprint' => $requestFingerprint,
    ];
}

/**
 * Convert a completed persistence row into the replay returned to the caller.
 *
 * @param array<string, mixed> $record Completed database row
 * @return array{state:string,resource_id:int,http_status:int,response:array<mixed>}
 */
function apiIdempotencyReplayDecision(array $record): array
{
    $response = json_decode((string) ($record['response_body'] ?? ''), true);
    if (is_array($response) === false) {
        throw new RuntimeException('The stored idempotency response is invalid.');
    }

    return [
        'state' => 'replay',
        'resource_id' => (int) ($record['resource_id'] ?? 0),
        'http_status' => (int) ($record['http_status'] ?? 200),
        'response' => $response,
    ];
}

/**
 * Decide how an existing idempotency record must be handled.
 *
 * A stale result authorizes the persistence adapter to attempt one compare-and-swap
 * lease takeover. It does not mean that ownership has already been acquired.
 *
 * @param array<string, mixed> $record Existing database row
 * @param string $requestFingerprint HMAC of the current request intent
 * @param int    $now Current Unix timestamp
 * @return array<string, mixed> conflict, replay, processing or stale decision
 */
function apiIdempotencyExistingRecordDecision(
    array $record,
    string $requestFingerprint,
    int $now
): array {
    if (hash_equals((string) ($record['request_fingerprint'] ?? ''), $requestFingerprint) === false) {
        return ['state' => 'conflict'];
    }

    if ((string) ($record['status'] ?? '') === 'completed') {
        return apiIdempotencyReplayDecision($record);
    }

    $lockedUntil = (int) ($record['locked_until'] ?? 0);
    if ($lockedUntil >= $now) {
        return [
            'state' => 'processing',
            'retry_after' => max(1, $lockedUntil - $now),
        ];
    }

    return ['state' => 'stale'];
}

/**
 * Convert a compare-and-swap result into an acquired lease when it won.
 *
 * @param int    $affectedRows       Rows changed by the guarded lease update
 * @param int    $recordId          Idempotency record identifier
 * @param string $ownerToken        Raw request-local owner token
 * @param string $requestFingerprint HMAC of the functional request intent
 * @return array<string, mixed>|null Acquired decision, or null when another request won
 */
function apiIdempotencyLeaseTakeoverDecision(
    int $affectedRows,
    int $recordId,
    string $ownerToken,
    string $requestFingerprint
): ?array {
    if ($affectedRows !== 1) {
        return null;
    }

    return apiIdempotencyAcquiredDecision($recordId, $ownerToken, $requestFingerprint);
}

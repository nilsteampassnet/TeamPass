<?php
declare(strict_types=1);

require_once __DIR__ . '/../../sources/api_idempotency_logic.php';

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @version   API
 * @file      ApiIdempotencyModel.php
 * @copyright 2009-2026 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Persistent idempotency records for mutating API operations.
 *
 * Only HMACs and replay-safe response metadata are stored. Request bodies,
 * passwords, TOTP secrets, custom-field values and raw idempotency keys never
 * reach the database through this model.
 */
class ApiIdempotencyModel
{
    public const OPERATION_ITEM_CREATE = 'item.create';
    public const OPERATION_ITEM_DELETE = 'item.delete';
    public const MAX_KEY_LENGTH = 128;
    public const PROCESSING_LEASE_SECONDS = 300;

    private string $hmacSecret;
    private int $replayWindowDays;

    /**
     * @param string|null $hmacSecret Test-only secret override; production derives it from SECUREFILE
     * @param int|string|null $replayWindowDays Test override; production reads offline_sync_window_days
     */
    public function __construct(?string $hmacSecret = null, int|string|null $replayWindowDays = null)
    {
        if ($replayWindowDays === null && $hmacSecret === null) {
            $configManager = new \TeampassClasses\ConfigManager\ConfigManager();
            $replayWindowDays = $configManager->getSetting('offline_sync_window_days');
        }
        $this->replayWindowDays = offlineSyncResolveWindowDays($replayWindowDays);

        if ($hmacSecret !== null) {
            $this->hmacSecret = $hmacSecret;
            return;
        }

        if (defined('TEAMPASS_SECRETS') === false || defined('SECUREFILE') === false) {
            throw new RuntimeException('The server idempotency secret is unavailable.');
        }

        $masterSecret = @file_get_contents(TEAMPASS_SECRETS . '/' . SECUREFILE);
        if ($masterSecret === false || $masterSecret === '') {
            throw new RuntimeException('The server idempotency secret is unavailable.');
        }

        $this->hmacSecret = hash_hmac('sha256', 'teampass-api-idempotency-v1', $masterSecret, true);
    }

    /**
     * Validate and return an opaque Idempotency-Key header value.
     *
     * @param string $key Raw header value
     * @return string Validated key
     */
    public static function validateKey(string $key): string
    {
        if ($key === '' || strlen($key) > self::MAX_KEY_LENGTH) {
            throw new InvalidArgumentException('Idempotency-Key must contain between 1 and 128 characters.');
        }

        if (preg_match('/^[\x21-\x7E]+$/D', $key) !== 1) {
            throw new InvalidArgumentException('Idempotency-Key must contain visible ASCII characters without spaces.');
        }

        return $key;
    }

    /**
     * Build a deterministic HMAC fingerprint for a functional request intent.
     *
     * Associative keys are sorted recursively, so JSON property order does not
     * influence the result. List order is preserved because it may be functional.
     *
     * @param array<string, mixed> $intent Functional request fields
     * @return string Lowercase hexadecimal HMAC
     */
    public function fingerprint(array $intent): string
    {
        $canonical = apiIdempotencyCanonicalize($intent);
        $encoded = json_encode(
            $canonical,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );

        return hash_hmac('sha256', $encoded, $this->hmacSecret);
    }

    /**
     * Reserve an idempotency identity or return the existing request outcome.
     *
     * @param int $userId Authenticated TeamPass user
     * @param string $operation Operation scope
     * @param string $key Validated raw Idempotency-Key
     * @param array<string, mixed> $intent Functional request intent
     * @return array<string, mixed> acquired, replay, conflict or processing decision
     */
    public function reserve(int $userId, string $operation, string $key, array $intent): array
    {
        if ($userId <= 0 || in_array($operation, [self::OPERATION_ITEM_CREATE, self::OPERATION_ITEM_DELETE], true) === false) {
            throw new InvalidArgumentException('Invalid idempotency scope.');
        }

        $key = self::validateKey($key);
        $keyHash = hash_hmac('sha256', $key, $this->hmacSecret);
        $requestFingerprint = $this->fingerprint($intent);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $now = time();
            $ownerToken = bin2hex(random_bytes(32));
            $ownerTokenHash = hash('sha256', $ownerToken);

            try {
                DB::insert(
                    prefixTable('api_idempotency'),
                    [
                        'user_id' => $userId,
                        'operation' => $operation,
                        'key_hash' => $keyHash,
                        'request_fingerprint' => $requestFingerprint,
                        'status' => 'processing',
                        'owner_token_hash' => $ownerTokenHash,
                        'resource_id' => null,
                        'http_status' => null,
                        'response_body' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'locked_until' => $now + self::PROCESSING_LEASE_SECONDS,
                        'expires_at' => apiIdempotencyReplayExpiry($now, $this->replayWindowDays),
                    ]
                );

                return apiIdempotencyAcquiredDecision(
                    (int) DB::insertId(),
                    $ownerToken,
                    $requestFingerprint
                );
            } catch (Throwable $exception) {
                $record = $this->findRecord($userId, $operation, $keyHash);
                if ($record === null) {
                    throw $exception;
                }
            }

            $decision = apiIdempotencyExistingRecordDecision($record, $requestFingerprint, $now);
            if ($decision['state'] !== 'stale') {
                return $decision;
            }

            // A committed create is linked back to its reservation. This defensive recovery
            // path repairs an old/stale processing row without re-running any side effect.
            if ($operation === self::OPERATION_ITEM_CREATE) {
                $recovered = $this->recoverCommittedCreate($record, $requestFingerprint, $now);
                if ($recovered !== null) {
                    return $recovered;
                }
            }

            DB::update(
                prefixTable('api_idempotency'),
                [
                    'owner_token_hash' => $ownerTokenHash,
                    'updated_at' => $now,
                    'locked_until' => $now + self::PROCESSING_LEASE_SECONDS,
                    'expires_at' => apiIdempotencyReplayExpiry($now, $this->replayWindowDays),
                ],
                'id = %i AND status = %s AND locked_until < %i AND request_fingerprint = %s',
                (int) $record['id'],
                'processing',
                $now,
                $requestFingerprint
            );

            $takeover = apiIdempotencyLeaseTakeoverDecision(
                (int) DB::affectedRows(),
                (int) $record['id'],
                $ownerToken,
                $requestFingerprint
            );
            if ($takeover !== null) {
                return $takeover;
            }
        }

        return ['state' => 'processing', 'retry_after' => 1];
    }

    /**
     * Lock and verify a reservation inside the mutation transaction.
     *
     * @param array<string, mixed> $reservation Acquired reservation
     * @return void
     */
    public function lockReservation(array $reservation): void
    {
        $record = DB::queryFirstRow(
            'SELECT id, status, owner_token_hash
             FROM ' . prefixTable('api_idempotency') . '
             WHERE id = %i
             FOR UPDATE',
            (int) ($reservation['id'] ?? 0)
        );

        $expectedTokenHash = hash('sha256', (string) ($reservation['owner_token'] ?? ''));
        if ($record === null
            || (string) $record['status'] !== 'processing'
            || hash_equals((string) $record['owner_token_hash'], $expectedTokenHash) === false
        ) {
            throw new RuntimeException('The idempotency reservation is no longer owned by this request.');
        }
    }

    /**
     * Complete a reservation in the same transaction as the functional mutation.
     *
     * @param array<string, mixed> $reservation Acquired reservation
     * @param int $resourceId Created or deleted item id
     * @param int $httpStatus Replay HTTP status
     * @param array<string, mixed> $response Replay-safe response body
     * @return void
     */
    public function completeReservation(
        array $reservation,
        int $resourceId,
        int $httpStatus,
        array $response
    ): void {
        $responseBody = json_encode(
            $response,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $now = time();

        DB::update(
            prefixTable('api_idempotency'),
            [
                'status' => 'completed',
                'resource_id' => $resourceId,
                'http_status' => $httpStatus,
                'response_body' => $responseBody,
                'updated_at' => $now,
                'locked_until' => 0,
                'expires_at' => apiIdempotencyReplayExpiry($now, $this->replayWindowDays),
            ],
            'id = %i AND status = %s AND owner_token_hash = %s',
            (int) ($reservation['id'] ?? 0),
            'processing',
            hash('sha256', (string) ($reservation['owner_token'] ?? ''))
        );

        if (DB::affectedRows() !== 1) {
            throw new RuntimeException('Unable to finalize the idempotency reservation.');
        }
    }

    /**
     * Release an acquired reservation after validation or a rolled-back mutation failed.
     *
     * @param array<string, mixed> $reservation Acquired reservation
     * @return void
     */
    public function releaseReservation(array $reservation): void
    {
        DB::delete(
            prefixTable('api_idempotency'),
            'id = %i AND status = %s AND owner_token_hash = %s',
            (int) ($reservation['id'] ?? 0),
            'processing',
            hash('sha256', (string) ($reservation['owner_token'] ?? ''))
        );
    }

    /**
     * Find one idempotency record by its scoped identity.
     *
     * @return array<string, mixed>|null
     */
    private function findRecord(int $userId, string $operation, string $keyHash): ?array
    {
        $record = DB::queryFirstRow(
            'SELECT id, user_id, operation, request_fingerprint, status, owner_token_hash,
                    resource_id, http_status, response_body, locked_until, expires_at
             FROM ' . prefixTable('api_idempotency') . '
             WHERE user_id = %i AND operation = %s AND key_hash = %s',
            $userId,
            $operation,
            $keyHash
        );

        return is_array($record) ? $record : null;
    }

    /**
     * Recover a committed create from the durable item-to-reservation link.
     *
     * @param array<string, mixed> $record Stale processing row
     * @param string $requestFingerprint Current request fingerprint
     * @param int $now Current Unix timestamp
     * @return array<string, mixed>|null Replay decision when recovery succeeded
     */
    private function recoverCommittedCreate(array $record, string $requestFingerprint, int $now): ?array
    {
        $item = DB::queryFirstRow(
            'SELECT id, revision, revision_changed_at
             FROM ' . prefixTable('items') . '
             WHERE api_idempotency_id = %i',
            (int) $record['id']
        );
        if ($item === null) {
            return null;
        }

        $response = [
            'error' => false,
            'message' => 'Item added successfully',
            'newId' => (int) $item['id'],
            'revision' => (int) ($item['revision'] ?? 0),
            'revision_changed_at' => $item['revision_changed_at'] === null
                ? null
                : (int) $item['revision_changed_at'],
        ];
        $responseBody = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        DB::update(
            prefixTable('api_idempotency'),
            [
                'status' => 'completed',
                'resource_id' => (int) $item['id'],
                'http_status' => 201,
                'response_body' => $responseBody,
                'updated_at' => $now,
                'locked_until' => 0,
                'expires_at' => apiIdempotencyReplayExpiry($now, $this->replayWindowDays),
            ],
            'id = %i AND status = %s AND locked_until < %i AND request_fingerprint = %s',
            (int) $record['id'],
            'processing',
            $now,
            $requestFingerprint
        );

        if (DB::affectedRows() !== 1) {
            return null;
        }

        return apiIdempotencyReplayDecision([
            'resource_id' => (int) $item['id'],
            'http_status' => 201,
            'response_body' => $responseBody,
        ]);
    }
}

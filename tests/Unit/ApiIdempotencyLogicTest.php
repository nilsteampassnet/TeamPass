<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/api_idempotency_logic.php';

/**
 * Behavioural tests for the DB-free API idempotency state machine.
 */
class ApiIdempotencyLogicTest extends TestCase
{
    public function testCanonicalizationSortsObjectsAndPreservesListOrder(): void
    {
        self::assertSame(
            [
                'a' => ['x' => 1, 'y' => 2],
                'z' => [['b' => 2, 'c' => 3], ['a' => 1]],
            ],
            apiIdempotencyCanonicalize([
                'z' => [['c' => 3, 'b' => 2], ['a' => 1]],
                'a' => ['y' => 2, 'x' => 1],
            ])
        );
    }

    public function testNewReservationReturnsAnAcquiredDecision(): void
    {
        self::assertSame(
            [
                'state' => 'acquired',
                'id' => 42,
                'owner_token' => 'owner-token',
                'request_fingerprint' => 'request-fingerprint',
            ],
            apiIdempotencyAcquiredDecision(42, 'owner-token', 'request-fingerprint')
        );
    }

    public function testCompletedRecordReturnsItsReplaySafeResponse(): void
    {
        self::assertSame(
            [
                'state' => 'replay',
                'resource_id' => 73,
                'http_status' => 201,
                'response' => ['error' => false, 'newId' => 73],
            ],
            apiIdempotencyExistingRecordDecision(
                [
                    'request_fingerprint' => 'same-request',
                    'status' => 'completed',
                    'resource_id' => 73,
                    'http_status' => 201,
                    'response_body' => '{"error":false,"newId":73}',
                    'locked_until' => 0,
                ],
                'same-request',
                1_800_000_000
            )
        );
    }

    public function testExistingRecordRejectsAnotherFunctionalIntent(): void
    {
        self::assertSame(
            ['state' => 'conflict'],
            apiIdempotencyExistingRecordDecision(
                [
                    'request_fingerprint' => 'first-request',
                    'status' => 'completed',
                    'response_body' => '{}',
                ],
                'different-request',
                1_800_000_000
            )
        );
    }

    public function testLiveLeaseReturnsTheRemainingRetryDelay(): void
    {
        self::assertSame(
            ['state' => 'processing', 'retry_after' => 20],
            apiIdempotencyExistingRecordDecision(
                [
                    'request_fingerprint' => 'same-request',
                    'status' => 'processing',
                    'locked_until' => 1_800_000_020,
                ],
                'same-request',
                1_800_000_000
            )
        );
    }

    public function testExpiredLeaseRequestsAnAtomicTakeover(): void
    {
        self::assertSame(
            ['state' => 'stale'],
            apiIdempotencyExistingRecordDecision(
                [
                    'request_fingerprint' => 'same-request',
                    'status' => 'processing',
                    'locked_until' => 1_799_999_999,
                ],
                'same-request',
                1_800_000_000
            )
        );
    }

    public function testLeaseTakeoverOnlySucceedsForOneChangedRow(): void
    {
        self::assertSame(
            [
                'state' => 'acquired',
                'id' => 42,
                'owner_token' => 'new-owner',
                'request_fingerprint' => 'same-request',
            ],
            apiIdempotencyLeaseTakeoverDecision(1, 42, 'new-owner', 'same-request')
        );
        self::assertNull(apiIdempotencyLeaseTakeoverDecision(0, 42, 'new-owner', 'same-request'));
        self::assertNull(apiIdempotencyLeaseTakeoverDecision(2, 42, 'new-owner', 'same-request'));
    }

    public function testReplayExpiryUsesTheConfiguredOfflineSyncWindow(): void
    {
        $now = 1_800_000_000;

        self::assertSame($now + (90 * 86400), apiIdempotencyReplayExpiry($now, null));
        self::assertSame($now + (7 * 86400), apiIdempotencyReplayExpiry($now, '7'));
        self::assertSame(0, apiIdempotencyReplayExpiry($now, 0));
        self::assertSame(PHP_INT_MAX, apiIdempotencyReplayExpiry($now, PHP_INT_MAX));
    }

    public function testMalformedStoredReplayIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        apiIdempotencyReplayDecision(['response_body' => '{invalid']);
    }
}

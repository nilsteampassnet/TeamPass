<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\TestCase;
use TeampassClasses\EmailService\EmailSendResult;

final class EmailSendResultTest extends TestCase
{
    /** Acceptance never asks a caller to replay the request. */
    public function testAcceptedRequestIsNotRetryable(): void
    {
        $result = EmailSendResult::accepted('message-id');
        self::assertTrue($result->accepted);
        self::assertFalse($result->retryable);
        self::assertFalse($result->indeterminate);
        self::assertSame('message-id', $result->providerMessageId);
    }

    /** Retry-After must be preserved instead of shortening the provider's delay. */
    public function testDeferredFailurePreservesLongRetryAfter(): void
    {
        $result = EmailSendResult::failure('throttled', true, 172800);
        self::assertSame(172800, $result->retryAfter);
        self::assertFalse($result->accepted);
    }

    /** An ambiguous outcome cannot be automatically replayed. */
    public function testIndeterminateRequestIsNotRetryable(): void
    {
        $result = EmailSendResult::indeterminate('timeout');
        self::assertTrue($result->indeterminate);
        self::assertFalse($result->accepted);
        self::assertFalse($result->retryable);
    }

    /** Error metadata is a closed set, never a raw provider response. */
    public function testRawProviderErrorIsNotAcceptedAsAnErrorCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmailSendResult::failure('private-provider-response');
    }

    /** A negative delay must not turn a deferred message into an immediate retry. */
    public function testNegativeRetryAfterIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EmailSendResult::failure('temporary', true, -1);
    }
}

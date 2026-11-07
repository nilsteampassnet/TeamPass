<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * This program is free software under the GNU General Public License, version 3
 * or later, without any warranty. See <https://www.gnu.org/licenses/>.
 */

namespace TeampassClasses\EmailService;

/** Safe, provider-independent outcome; no response body or message content. */
final class EmailSendResult
{
    private const ERROR_CODES = [
        'not_attempted', 'configuration', 'message', 'authentication', 'permission',
        'network', 'timeout', 'throttled', 'temporary', 'rejected', 'partial_acceptance',
    ];

    private function __construct(
        public readonly bool $accepted,
        public readonly string $errorCode,
        public readonly bool $retryable,
        public readonly ?int $retryAfter,
        public readonly bool $indeterminate,
        public readonly ?string $providerMessageId
    ) {
    }

    /** The relay or provider accepted this request; distribution is not confirmed. */
    public static function accepted(?string $providerMessageId = null): self
    {
        return new self(true, '', false, null, false, $providerMessageId);
    }

    /** A confirmed failure, optionally eligible for a bounded later attempt. */
    public static function failure(string $errorCode, bool $retryable = false, ?int $retryAfter = null): self
    {
        self::validateError($errorCode, $retryAfter);

        return new self(false, $errorCode, $retryable, $retryAfter, false, null);
    }

    /** Acceptance is unknown; automatically replaying the request could duplicate mail. */
    public static function indeterminate(string $errorCode = 'network'): self
    {
        self::validateError($errorCode, null);

        return new self(false, $errorCode, false, null, true, null);
    }

    /** Metadata suitable for callers and task status, without provider diagnostics. */
    public function toArray(): array
    {
        return [
            'accepted' => $this->accepted,
            'code' => $this->errorCode,
            'retryable' => $this->retryable,
            'retry_after' => $this->retryAfter,
            'indeterminate' => $this->indeterminate,
            'provider_message_id' => $this->providerMessageId,
        ];
    }

    private static function validateError(string $errorCode, ?int $retryAfter): void
    {
        if (!in_array($errorCode, self::ERROR_CODES, true) || ($retryAfter !== null && $retryAfter < 0)) {
            throw new \InvalidArgumentException('Invalid email result metadata.');
        }
    }
}

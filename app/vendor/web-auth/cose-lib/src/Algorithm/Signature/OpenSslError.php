<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature;

use function openssl_error_string;

/**
 * Reads the OpenSSL error queue so that a failed signature operation can say why it failed.
 *
 * @internal
 */
final class OpenSslError
{
    /**
     * Empties the queue, so that lastMessage() reports the errors of the next operation only. The queue is
     * process-wide and is not cleared by successful calls.
     */
    public static function clear(): void
    {
        while (openssl_error_string() !== false) {
            // Nothing to do: the call itself pops one entry off the queue.
        }
    }

    /**
     * Returns the most recent message of the queue, emptying it.
     */
    public static function lastMessage(): string
    {
        $message = false;
        while (($entry = openssl_error_string()) !== false) {
            $message = $entry;
        }

        return $message === false ? 'unknown OpenSSL error' : $message;
    }
}

<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

/** Protect new queued subjects, recipients and bodies with the existing instance key before SQL. */
function emailQueueSeal(#[SensitiveParameter] array $message): array
{
    emailQueueValidateMessage($message);
    $result = cryption(json_encode($message, JSON_THROW_ON_ERROR), '', 'encrypt');
    if (($result['error'] ?? true) !== false || !tpIsInstanceKeyCiphertext((string) ($result['string'] ?? ''))) {
        throw new RuntimeException('Email payload could not be encrypted.');
    }
    return ['email_payload_version' => 1, 'ciphertext' => $result['string']];
}

/** Open only the supported envelope; keep historical plaintext tasks readable until drained. */
function emailQueueOpen(#[SensitiveParameter] array $arguments): array
{
    if (array_key_exists('email_payload_version', $arguments)) {
        if ($arguments['email_payload_version'] !== 1 || !is_string($arguments['ciphertext'] ?? null)
            || !tpIsInstanceKeyCiphertext($arguments['ciphertext'])
        ) {
            throw new RuntimeException('Email payload format is invalid.');
        }
        $result = cryption($arguments['ciphertext'], '', 'decrypt');
        if (($result['error'] ?? true) !== false) {
            throw new RuntimeException('Email payload could not be decrypted.');
        }
        $arguments = json_decode((string) ($result['string'] ?? ''), true);
        if (!is_array($arguments)) {
            throw new RuntimeException('Email payload format is invalid.');
        }
    }
    emailQueueValidateMessage($arguments);
    return $arguments;
}

/** No token, provider credentials or arbitrary settings belong in a queued email. */
function emailQueueValidateMessage(#[SensitiveParameter] array $message): void
{
    if (array_diff(array_keys($message), ['subject', 'body', 'receivers', 'receiver_name', 'encryptedUserPassword']) !== []) {
        throw new RuntimeException('Email payload format is invalid.');
    }
    foreach (['subject', 'body', 'receivers'] as $name) {
        if (!is_string($message[$name] ?? null)) {
            throw new RuntimeException('Email payload format is invalid.');
        }
    }
    foreach (['receiver_name', 'encryptedUserPassword'] as $name) {
        if (isset($message[$name]) && !is_string($message[$name])) {
            throw new RuntimeException('Email payload format is invalid.');
        }
    }
}

/** Schedule only confirmed retryable failures; acceptance and ambiguous outcomes never replay. */
function emailQueueRetryAt(TeampassClasses\EmailService\EmailSendResult $result, int $attempts, int $now): ?int
{
    if ($result->accepted || $result->indeterminate || !$result->retryable || $attempts < 1 || $attempts >= 3
        || !in_array($result->errorCode, ['network', 'timeout', 'throttled', 'temporary'], true)
    ) {
        return null;
    }
    $delay = max(30 * (2 ** ($attempts - 1)), $result->retryAfter ?? 0);
    // Fail rather than park a task indefinitely or retry before the provider permits it.
    return $delay > 3600 || $now > PHP_INT_MAX - $delay ? null : $now + $delay;
}

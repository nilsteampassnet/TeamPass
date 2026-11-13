<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See the LICENSE file for details.
 */

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class EmailTaskDeliveryTest extends TestCase
{
    /** A rejected SMTP result must reach the worker's failure handler. */
    public function testRejectedDeliveryFailsTheTaskWithoutExposingTheProviderResponse(): void
    {
        $result = $this->probe('rejected');
        self::assertSame('rejected', $result['failure']);
        self::assertCount(1, $result['messages']);
        self::assertStringNotContainsString('private-provider-response', $result['failure']);
    }

    /** Ordinary queued notifications contain an empty credential, not ciphertext. */
    public function testOrdinaryMessagesDoNotAttemptToDecryptAnEmptyCredential(): void
    {
        $result = $this->probe('accepted');
        self::assertNull($result['failure']);
        self::assertSame(0, $result['decrypt_calls']);
        self::assertSame([['body' => '<p>Task body</p>', 'silent' => true, 'cron' => true]], $result['messages']);
    }

    /** Preserve the existing last-moment substitution for credential emails. */
    public function testCredentialIsDecryptedOnlyAtDelivery(): void
    {
        $result = $this->probe('encrypted');
        self::assertNull($result['failure']);
        self::assertSame(1, $result['decrypt_calls']);
        self::assertSame('<p>temporary-password</p>', $result['messages'][0]['body']);
    }

    /** A decryption failure must not send an empty password to the recipient. */
    public function testUndecryptableCredentialPreventsDelivery(): void
    {
        $result = $this->probe('invalid');
        self::assertSame('Email credential could not be decrypted.', $result['failure']);
        self::assertSame([], $result['messages']);
    }

    /** Execute the production worker, including persistence and logger boundaries. */
    public function testWorkerPersistsDeliveryFailuresAndLogsOnlyMetadata(): void
    {
        foreach (['accepted' => 'completed', 'encrypted' => 'completed', 'sealed' => 'completed', 'rejected' => 'failed', 'unexpected' => 'failed', 'spoofed' => 'failed', 'invalid' => 'failed', 'throttled' => 'queued', 'exhausted' => 'failed', 'indeterminate' => 'failed'] as $mode => $status) {
            $result = $this->probe($mode, true);
            self::assertCount(1, $result['task_updates']);
            self::assertSame($status, $result['task_updates'][0]['status'], $mode);
            self::assertSame(
                match ($mode) {
                    'accepted', 'encrypted', 'sealed' => null,
                    'invalid' => 'Email credential could not be decrypted.',
                    'rejected' => 'Email delivery failed: rejected.',
                    'exhausted' => 'Email delivery failed: throttled.',
                    'throttled' => 'Email delivery deferred: throttled.',
                    'indeterminate' => 'Email delivery indeterminate: timeout.',
                    default => 'Email delivery failed.',
                },
                $result['task_updates'][0]['error_message'],
                $mode
            );
            if ($status === 'queued') {
                self::assertArrayNotHasKey('arguments', $result['task_updates'][0]);
            } else {
                self::assertSame(
                    ($status === 'completed' ? ['accepted' => true] : ['failed' => true])
                        + ['email' => 'recipient@example.com', 'login' => 'Recipient'],
                    json_decode($result['task_updates'][0]['arguments'], true, 512, JSON_THROW_ON_ERROR),
                    $mode
                );
                self::assertSame('Recipient', $result['display_user'], $mode);
                foreach (['body', 'subject', 'encryptedUserPassword', 'valid-ciphertext', 'invalid-ciphertext'] as $sensitive) {
                    self::assertStringNotContainsString($sensitive, $result['task_updates'][0]['arguments'], $mode);
                }
            }
            $diagnostics = json_encode($result['logs']) . $result['task_updates'][0]['error_message'];
            foreach (['Task test', '<p>Task body</p>', 'temporary-password', 'valid-ciphertext', 'invalid-ciphertext', 'private-provider-response'] as $sensitive) {
                self::assertStringNotContainsString($sensitive, $diagnostics, $mode);
            }
            self::assertStringContainsString('123 (send_email)', implode('\n', $result['logs']));
            self::assertStringNotContainsString('recipient@example.com', implode('\n', $result['logs']));
        }
    }

    /** Throttling defers this same task; ambiguous acceptance and exhausted retries remain failed. */
    public function testWorkerDefersOnlyConfirmedRetryableFailures(): void
    {
        $before = time();
        $deferred = $this->probe('throttled', true)['task_updates'][0];
        self::assertSame('queued', $deferred['status']);
        self::assertSame(0, $deferred['is_in_progress']);
        self::assertNull($deferred['finished_at']);
        self::assertGreaterThanOrEqual($before + 120, $deferred['next_attempt_at']);
        foreach (['indeterminate', 'exhausted', 'excessive-delay'] as $mode) {
            $failed = $this->probe($mode, true)['task_updates'][0];
            self::assertSame('failed', $failed['status']);
            self::assertArrayNotHasKey('next_attempt_at', $failed);
        }
    }

    /** Completion keeps only its outcome and the fields needed by the actual Tasks log renderer. */
    public function testEncryptedAndLegacyTasksCompleteWithPrivateMetadataOnly(): void
    {
        foreach (['accepted', 'sealed'] as $mode) {
            $result = $this->probe($mode, true);
            self::assertSame(['accepted' => true, 'email' => 'recipient@example.com', 'login' => 'Recipient'],
                json_decode($result['task_updates'][0]['arguments'], true));
            self::assertSame('Recipient', $result['display_user']);
            $metadata = json_encode($result['task_updates']) . json_encode($result['logs']);
            foreach (['Task body', 'def50200cafe'] as $sensitive) {
                self::assertStringNotContainsString($sensitive, $metadata);
            }
        }
    }

    /** Terminal failures drop the sealed payload without claiming acceptance; deferrals retain it. */
    public function testEncryptedFailureOutcomesRetainOnlyPrivateStatusMetadata(): void
    {
        foreach (['rejected', 'invalid', 'indeterminate', 'exhausted', 'spoofed', 'excessive-delay'] as $mode) {
            $result = $this->probe('sealed-' . $mode, true);
            $update = $result['task_updates'][0];
            self::assertSame('failed', $update['status']);
            self::assertSame(['failed' => true, 'email' => 'recipient@example.com', 'login' => 'Recipient'],
                json_decode($update['arguments'], true, 512, JSON_THROW_ON_ERROR));
            self::assertSame('Recipient', $result['display_user']);
            $metadata = json_encode($result['task_updates']) . json_encode($result['logs']);
            foreach (['Task body', 'def50200cafe', 'invalid-ciphertext'] as $sensitive) {
                self::assertStringNotContainsString($sensitive, $metadata);
            }
            if ($mode === 'invalid') {
                self::assertSame('Email credential could not be decrypted.', $update['error_message']);
            }
        }
        $deferred = $this->probe('sealed-throttled', true)['task_updates'][0];
        self::assertSame('queued', $deferred['status']);
        self::assertArrayNotHasKey('arguments', $deferred);
    }

    /** The Tasks user column falls back to the email if no receiver name was supplied. */
    public function testTaskLogCanDisplayAnUnnamedRecipient(): void
    {
        $result = $this->probe('unnamed', true);
        self::assertSame('recipient@example.com', $result['display_user']);
    }

    /** An envelope that cannot be opened provides no recipient metadata to retain. */
    public function testInvalidEnvelopeDoesNotRetainItsPayload(): void
    {
        $result = $this->probe('payload-invalid', true);
        self::assertSame('failed', $result['task_updates'][0]['status']);
        self::assertSame(['failed' => true], json_decode($result['task_updates'][0]['arguments'], true));
        self::assertSame([], $result['messages']);
    }

    /** The real worker must refuse unavailable links and retain safe failure metadata. */
    public function testSecureSendWorkerChecksAvailabilityBeforeEveryAttempt(): void
    {
        foreach (['accepted', 'throttled', 'revoked', 'expired', 'disabled', 'email-disabled'] as $mode) {
            $result = $this->probe('secure-' . $mode, true);
            $expected = match ($mode) { 'accepted' => 'completed', 'throttled' => 'queued', default => 'failed' };
            self::assertSame($expected, $result['task_updates'][0]['status'], $mode);
            self::assertCount(in_array($mode, ['accepted', 'throttled'], true) ? 1 : 0, $result['messages'], $mode);
            if ($expected === 'failed') {
                self::assertSame('Secure Send is no longer available; email was not submitted.', $result['task_updates'][0]['error_message']);
                self::assertSame(['failed' => true, 'email' => 'recipient@example.com', 'login' => 'Recipient'],
                    json_decode($result['task_updates'][0]['arguments'], true));
            }
        }
    }

    /** The real worker must resolve disclosure policy and the profile after opening the queued payload. */
    public function testSecureSendWorkerResolvesTheSubjectWithCurrentDisclosurePolicy(): void
    {
        foreach (['sender-shown' => 'R&D O\'Brien shared a secure message with you via TeamPass', 'sender-hidden' => 'Someone shared a secure message with you via TeamPass'] as $mode => $subject) {
            $result = $this->probe('secure-' . $mode, true);
            self::assertSame('completed', $result['task_updates'][0]['status']);
            self::assertCount(1, $result['messages']);
            self::assertSame($subject, $result['messages'][0]['subject']);
            $metadata = json_encode($result['task_updates']) . json_encode($result['logs']);
            self::assertStringNotContainsString('O\'Brien', $metadata);
            self::assertStringNotContainsString('#sender_prefix#', $metadata);
            self::assertStringNotContainsString('#sender#', $metadata);
        }
    }

    /** @return array{failure: ?string, decrypt_calls: int, messages: array, task_updates: array, logs: array} */
    private function probe(string $mode, bool $worker = false): array
    {
        $process = new Process([PHP_BINARY, __DIR__ . '/../Fixtures/email_task_probe.php', $mode, $worker ? 'worker' : 'trait']);
        $process->setTimeout(15);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}

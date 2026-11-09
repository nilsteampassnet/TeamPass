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
        self::assertSame('Email delivery failed.', $result['failure']);
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
        foreach (['accepted' => 'completed', 'encrypted' => 'completed', 'rejected' => 'failed', 'unexpected' => 'failed', 'invalid' => 'failed'] as $mode => $status) {
            $result = $this->probe($mode, true);
            self::assertCount(1, $result['task_updates']);
            self::assertSame($status, $result['task_updates'][0]['status'], $mode);
            self::assertSame(
                ['email' => 'recipient@example.com', 'login' => 'Recipient'],
                json_decode($result['task_updates'][0]['arguments'], true, 512, JSON_THROW_ON_ERROR),
                $mode
            );
            foreach (['body', 'subject', 'encryptedUserPassword', 'valid-ciphertext', 'invalid-ciphertext'] as $sensitive) {
                self::assertStringNotContainsString($sensitive, $result['task_updates'][0]['arguments'], $mode);
            }
            $diagnostics = json_encode($result['logs']) . $result['task_updates'][0]['error_message'];
            foreach (['Task test', '<p>Task body</p>', 'temporary-password', 'valid-ciphertext', 'invalid-ciphertext', 'private-provider-response'] as $sensitive) {
                self::assertStringNotContainsString($sensitive, $diagnostics, $mode);
            }
            self::assertStringContainsString('123 (send_email)', implode('\n', $result['logs']));
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

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
    public function testRejectedDeliveryFailsTheTaskWithoutExposingTheProviderResponse(): void
    {
        $result = $this->probe('rejected');
        self::assertSame('Email delivery failed.', $result['failure']);
        self::assertCount(1, $result['messages']);
        self::assertStringNotContainsString('private-provider-response', $result['failure']);
    }

    public function testOrdinaryMessagesDoNotAttemptToDecryptAnEmptyCredential(): void
    {
        $result = $this->probe('accepted');
        self::assertNull($result['failure']);
        self::assertSame(0, $result['decrypt_calls']);
        self::assertSame([['body' => '<p>Task body</p>', 'silent' => true, 'cron' => true]], $result['messages']);
    }

    public function testCredentialIsDecryptedOnlyAtDelivery(): void
    {
        $result = $this->probe('encrypted');
        self::assertNull($result['failure']);
        self::assertSame(1, $result['decrypt_calls']);
        self::assertSame('<p>temporary-password</p>', $result['messages'][0]['body']);
    }

    public function testUndecryptableCredentialPreventsDelivery(): void
    {
        $result = $this->probe('invalid');
        self::assertSame('Email credential could not be decrypted.', $result['failure']);
        self::assertSame([], $result['messages']);
    }

    /** @return array{failure: ?string, decrypt_calls: int, messages: array} */
    private function probe(string $mode): array
    {
        $process = new Process([PHP_BINARY, __DIR__ . '/../Fixtures/email_task_probe.php', $mode]);
        $process->setTimeout(15);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}

<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class EmailQueueFeedbackTest extends TestCase
{
    private function probe(string $mode): string
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__) . '/Fixtures/email_queue_feedback_probe.php', $mode]);
        $process->setTimeout(15);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringNotContainsString('private-temporary-credential', $process->getOutput());
        return $process->getOutput();
    }

    /** Keep null for queued success and return the normal encoded error contract on failure. */
    public function testQueuedMailWrapperReportsFailure(): void
    {
        $accepted = json_decode($this->probe('wrapper-ok'), true, 512, JSON_THROW_ON_ERROR);
        self::assertNull($accepted['response']);
        $failed = json_decode($this->probe('wrapper-fail'), true, 512, JSON_THROW_ON_ERROR);
        $response = json_decode($failed['response'], true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($response['error']);
        self::assertStringContainsString('could not be queued', $response['message']);
        self::assertSame(1, $failed['state']['queued_calls']);
    }

    /** The MFA action cannot claim an email was sent when enqueue failed. */
    public function testGaEmailReportsOnlySuccessfullyQueuedMessages(): void
    {
        $accepted = json_decode($this->probe('ga-ok'), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($accepted['error']);
        self::assertArrayHasKey('email_result', $accepted);
        $failed = json_decode($this->probe('ga-fail'), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($failed['error']);
        self::assertStringContainsString('could not be queued', $failed['message']);
        self::assertArrayNotHasKey('email_result', $failed);
    }

    /** A completed reset still revokes sessions and audits the change, but renders a mail failure. */
    public function testResetPageDoesNotReportSuccessAfterAnEnqueueFailure(): void
    {
        $accepted = $this->probe('reset-ok');
        self::assertStringContainsString('alert-success', $accepted);
        self::assertStringContainsString('A temporary password has been sent', $accepted);
        $failed = $this->probe('reset-fail');
        self::assertStringContainsString('alert-danger', $failed);
        self::assertStringContainsString('Contact your administrator', $failed);
        self::assertStringNotContainsString('alert-success', $failed);
        self::assertStringNotContainsString('A temporary password has been sent', $failed);
        preg_match('/<!-- state:(.+) -->/', $failed, $matches);
        $state = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($state['reset']);
        self::assertTrue($state['consumed']);
        self::assertTrue($state['revoked']);
        self::assertContains('at_user_pwd_changed', $state['audit']);
    }
}

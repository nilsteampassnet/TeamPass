<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See the LICENSE file for details.
 */

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use TeampassClasses\EmailService\EmailService;
use TeampassClasses\EmailService\EmailSettings;

/** Exercise real PHPMailer message preparation without contacting a relay. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EmailServiceDeliveryTest extends TestCase
{
    protected function setUp(): void
    {
        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        define('TEAMPASS_FUNCTIONS_ONLY', true);
        require_once dirname(__DIR__, 2) . '/app/sources/main.functions.php';
    }

    public function testSuccessiveMessagesHaveIndependentRecipientsAndBodies(): void
    {
        [$service, $mailer, $settings] = $this->delivery();
        $body = '<p>R&amp;D O&#039;Brien &quot;quoted&quot; &lt;label&gt;</p>';

        self::assertSame('', $service->sendMail('First', $body, 'first@example.com, second@example.com', $settings, 'First text'));
        self::assertSame('', $service->sendMail('Second', '<p>Second body</p>', 'third@example.com', $settings));

        self::assertSame(['first@example.com', 'second@example.com'], $mailer->attempts[0]['recipients']);
        self::assertSame(['third@example.com'], $mailer->attempts[1]['recipients']);
        self::assertStringContainsString($body, $mailer->attempts[0]['body']);
        self::assertStringNotContainsString('R&amp;D', $mailer->attempts[1]['body']);
        self::assertSame('First text', rtrim($mailer->attempts[0]['alternative'], "\r\n"));
        self::assertSame('', $mailer->attempts[1]['alternative']);
    }

    public function testFailedDeliveryClosesTheConnectionAndDoesNotRetainRecipients(): void
    {
        [$service, $mailer, $settings] = $this->delivery();
        $mailer->reject = true;

        $result = json_decode($service->sendMail('First', 'First body', 'first@example.com', $settings), true);
        self::assertTrue($result['error']);
        self::assertSame(1, $mailer->closeCalls);

        $mailer->reject = false;
        self::assertSame('', $service->sendMail('Second', 'Second body', 'second@example.com', $settings));
        self::assertSame(['second@example.com'], $mailer->attempts[1]['recipients']);
        self::assertSame('', $service->getLastError());
        self::assertSame(2, $mailer->closeCalls);
    }

    /** @return array{EmailService, EmailDeliveryRecordingMailer, EmailSettings} */
    private function delivery(): array
    {
        $service = new EmailService();
        $mailer = new EmailDeliveryRecordingMailer(true);
        (new ReflectionProperty(EmailService::class, 'mailer'))->setValue($service, $mailer);

        return [$service, $mailer, new EmailSettings(['email_from' => 'sender@example.com'])];
    }
}

/** Record prepared messages at the transport boundary, including rejected sends. */
final class EmailDeliveryRecordingMailer extends PHPMailer
{
    public array $attempts = [];
    public bool $reject = false;
    public int $closeCalls = 0;

    /** Record the message instead of opening a network connection. */
    public function postSend()
    {
        $this->attempts[] = [
            'recipients' => array_column($this->getToAddresses(), 0),
            'body' => $this->Body,
            'alternative' => $this->AltBody,
        ];
        if ($this->reject) {
            $this->ErrorInfo = 'SMTP Error: data not accepted.';
            throw new MailerException($this->ErrorInfo);
        }

        return true;
    }

    /** Track cleanup even after a transport exception. */
    public function smtpClose()
    {
        $this->closeCalls++;
    }
}

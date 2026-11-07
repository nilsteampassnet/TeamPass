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
use PHPMailer\PHPMailer\SMTP;
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

    /** A backlog must never carry recipients or alternative text into the next message. */
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

    /** A failed send must release the connection and leave the service reusable. */
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

    /** Even low-level SMTP traces must exclude payloads and echoed credentials. */
    public function testDebugOutputKeepsProtocolStagesWithoutCredentialsOrMessageData(): void
    {
        [$service, $mailer, $settings] = $this->delivery();
        $service->configureMailer($settings, false, false);
        $debug = $mailer->Debugoutput;
        foreach ([
            'Connection: opening to smtp.example.com?secret=private-token',
            'CLIENT -> SERVER: AUTH LOGIN',
            'CLIENT -> SERVER: ' . base64_encode('private-password'),
            'CLIENT -> SERVER: RCPT TO:<private-recipient@example.com>',
            'CLIENT -> SERVER: DATA',
            'CLIENT -> SERVER: <p>private-message-body</p>',
            "SERVER -> CLIENT: 535 5.7.8 private-password\r\nprivate-message-body",
            'SMTP ERROR: private-error-detail',
            'Connection: closed',
        ] as $line) {
            $debug($line, SMTP::DEBUG_LOWLEVEL);
        }

        self::assertSame(
            "Connection: opening\nCLIENT -> SERVER: AUTH\nCLIENT -> SERVER: RCPT\nCLIENT -> SERVER: DATA\nSERVER -> CLIENT: 535 5.7.8\nConnection: closed",
            $service->getDebugOutput()
        );
    }

    /** Both the legacy JSON result and PHP log use the same safe diagnostic. */
    public function testErrorResponsesAndLogsExcludeProviderDetails(): void
    {
        [$service, $mailer, $settings] = $this->delivery();
        $smtp = $this->createMock(SMTP::class);
        $smtp->method('getError')->willReturn([
            'error' => 'private-error', 'detail' => 'private-password',
            'smtp_code' => '535', 'smtp_code_ex' => '5.7.8',
        ]);
        $mailer->setSMTPInstance($smtp);
        $mailer->reject = true;
        $mailer->failureMessage = 'SMTP Error: Could not authenticate. private-password ' . base64_encode('private-password');
        $log = tempnam(sys_get_temp_dir(), 'teampass-email-test-');
        self::assertNotFalse($log);
        ini_set('error_log', $log);
        try {
            $response = $service->sendMail('Test', 'private-body', 'recipient@example.com', $settings);
            $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('SMTP Error: Could not authenticate. [535 5.7.8]', $service->getLastError());
            self::assertSame($service->getLastError(), $result['message']);
            self::assertSame($service->getLastError(), $result['errorInfo']);
            $output = $response . file_get_contents($log);
            foreach (['private-password', base64_encode('private-password'), 'private-body', 'private-error'] as $secret) {
                self::assertStringNotContainsString($secret, $output);
            }
        } finally {
            ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
            unlink($log);
        }
    }

    /** Unknown provider text and malformed numeric fields are never displayed. */
    public function testUnknownErrorsAndMalformedSmtpCodesAreNotEchoed(): void
    {
        [$service, $mailer, $settings] = $this->delivery();
        $smtp = $this->createMock(SMTP::class);
        $smtp->method('getError')->willReturn(['smtp_code' => '535 private-code', 'smtp_code_ex' => '5.7.8 private-detail']);
        $mailer->setSMTPInstance($smtp);
        $mailer->reject = true;
        $mailer->failureMessage = 'private-unknown-error';

        $result = json_decode($service->sendMail('Test', 'Body', 'recipient@example.com', $settings), true);
        self::assertTrue($result['error']);
        self::assertSame('SMTP server error', $result['message']);
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
    public string $failureMessage = 'SMTP Error: data not accepted.';
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
            $this->ErrorInfo = $this->failureMessage;
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

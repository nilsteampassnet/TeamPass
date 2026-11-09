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

    /** PHPMailer's stream handler emits multiline OpenSSL failures with a trailing source path. */
    public function testLocalConnectionAndTlsErrorsRemainVisibleWithoutSourcePaths(): void
    {
        [$service, $mailer, $settings] = $this->delivery();
        $service->configureMailer($settings, false, false);
        $smtp = new SMTP();
        $smtp->do_debug = SMTP::DEBUG_CONNECTION;
        $smtp->Debugoutput = $mailer->Debugoutput;
        $errorHandler = new ReflectionMethod(SMTP::class, 'errorHandler');
        $messages = [
            'stream_socket_client(): Unable to connect to 127.0.0.1:1 (Connection refused)',
            "stream_socket_client(): SSL operation failed with code 1. OpenSSL Error messages:\nerror:0A000086:SSL routines::certificate verify failed",
        ];
        foreach ($messages as $message) {
            $errorHandler->invoke($smtp, 2, $message, '/private/path/SMTP.php', 421);
        }
        $debug = $mailer->Debugoutput;
        $debug('SMTP ERROR: Failed to connect to server: Connection refused (111)', SMTP::DEBUG_CLIENT);
        $debug('Connection: closing due to error', SMTP::DEBUG_CONNECTION);
        $debug('Connection: failed', SMTP::DEBUG_CONNECTION); // Not emitted by PHPMailer 6.12.

        self::assertSame(
            'Connection failed. Error #2: ' . $messages[0] . "\n"
            . 'Connection failed. Error #2: ' . $messages[1] . "\n"
            . "SMTP ERROR: Failed to connect to server: Connection refused (111)\nConnection: closing due to error",
            $service->getDebugOutput()
        );
    }

    /** PHPMailer names the failed command before appending a potentially sensitive reply. */
    public function testFailedSmtpCommandsKeepOnlyTheirLibraryLabels(): void
    {
        [$service, $mailer, $settings] = $this->delivery();
        $service->configureMailer($settings, false, false);
        $debug = $mailer->Debugoutput;
        $commands = ['EHLO', 'STARTTLS', 'AUTH', 'AUTH CRAM-MD5', 'Username', 'Password',
            'User & Password', 'MAIL FROM', 'RCPT TO', 'DATA', 'DATA END', 'RSET', 'QUIT'];
        foreach ($commands as $command) {
            $debug('SMTP ERROR: ' . $command . " command failed: 535 5.7.8 private-provider-response\r\nprivate-password",
                SMTP::DEBUG_CLIENT);
        }
        self::assertSame(implode("\n", array_map(
            static fn (string $command): string => 'SMTP ERROR: ' . $command . ' command failed',
            $commands
        )), $service->getDebugOutput());
    }

    /** Both socket failure labels use smtp_code for an errno, never an SMTP response. */
    public function testSocketErrorNumbersAreNotDisplayedAsSmtpReplyCodes(): void
    {
        foreach (['Failed to connect to server', 'Connection failed.'] as $error) {
            [$service, $mailer, $settings] = $this->delivery();
            $smtp = $this->createMock(SMTP::class);
            $smtp->method('getError')->willReturn([
                'error' => $error, 'smtp_code' => '111', 'smtp_code_ex' => 'Connection refused',
            ]);
            $mailer->setSMTPInstance($smtp);
            $mailer->reject = true;
            $mailer->failureMessage = 'SMTP Error: Could not connect to SMTP host.';
            $result = json_decode($service->sendMail('Test', 'Body', 'recipient@example.com', $settings), true);
            self::assertSame('SMTP Error: Could not connect to SMTP host.', $result['message']);
        }
    }

    /** Exercise the actual PHPMailer stream error path against a bound, non-listening local port. */
    public function testRealConnectionRefusalKeepsDiagnosticsWithoutFalseSmtpCodes(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND);
        self::assertIsResource($socket);
        try {
            $settings = new EmailSettings(['email_from' => 'sender@example.com', 'email_smtp_server' => '127.0.0.1',
                'email_port' => (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1),
                'email_debug_level' => SMTP::DEBUG_CONNECTION]);
            $settings->timeout = 2;
            $service = new EmailService();
            $result = json_decode($service->sendMail('Test', 'Body', 'recipient@example.com', $settings, null, false), true);
            self::assertTrue($result['error']);
            self::assertSame('SMTP Error: Could not connect to SMTP host.', $service->getLastError());
            self::assertStringContainsString('Connection failed. Error #2: stream_socket_client():', $service->getDebugOutput());
            self::assertStringContainsString('SMTP ERROR: Failed to connect to server:', $service->getDebugOutput());
            self::assertStringNotContainsString('SMTP.php line', $service->getDebugOutput());
        } finally {
            fclose($socket);
        }
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

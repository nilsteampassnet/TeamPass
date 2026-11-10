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
use Symfony\Component\Process\Process;
use TeampassClasses\EmailService\EmailService;
use TeampassClasses\EmailService\EmailSettings;
use TeampassClasses\EmailService\EmailSendResult;
use TeampassClasses\EmailService\EmailTransportInterface;

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

    /** The invitation's escaped query parameters survive the actual mail preparation pipeline. */
    public function testSecureSendInvitationKeepsItsCompleteLinkThroughMimePreparation(): void
    {
        require_once dirname(__DIR__, 2) . '/app/sources/secure_send_email.php';
        foreach (['english', 'french'] as $locale) {
            [$service, $mailer, $settings] = $this->delivery();
            $url = 'https://share.example.test/team/index.php?otv=1&code=lookup&key=link-secret-canary&stamp=1794244800';
            $message = secureSendEmailMessage(['recipient' => 'recipient@example.test', 'url' => $url],
                ['shared_globaly' => 0, 'time_limit' => 1794849600, 'encrypted' => 'content-canary', 'protected_key' => 'wrapped-canary'],
                ['default_language' => $locale, 'secure_send_allow_email' => 1, 'email_from' => 'team@example.test', 'email_smtp_server' => 'smtp.example.test',
                    'cpassman_url' => 'https://share.example.test/team']);
            $message = secureSendEmailPersonalize($message, 0, ['default_language' => $locale]);
            self::assertSame('', $service->sendMail($message['subject'], $message['body'], $message['receivers'], $settings));
            self::assertSame($locale === 'french' ? 'Quelqu’un vous a partagé un message sécurisé depuis TeamPass' : 'Someone shared a secure message with you via TeamPass', $mailer->Subject);
            self::assertStringNotContainsString('#sender_prefix#', $mailer->getSentMIMEMessage());
            $body = $mailer->attempts[0]['body'];
            self::assertStringContainsString('href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"', $body);
            foreach (['content-canary', 'wrapped-canary', '#link#', '#expires#'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $body);
            }
            self::assertSame(['recipient@example.test'], $mailer->attempts[0]['recipients']);
        }
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

    /** Existing installations implicitly select SMTP and retain its legacy success result. */
    public function testLegacyConfigurationUsesSmtpAndExposesStructuredAcceptance(): void
    {
        [$service, $mailer, $settings] = $this->delivery();
        self::assertSame('smtp', $settings->transport);
        self::assertSame('not_attempted', $service->getLastResult()->errorCode);
        self::assertSame('', $service->sendMail('Test', 'Body', 'recipient@example.com', $settings));
        self::assertTrue($service->getLastResult()->accepted);
        self::assertCount(1, $mailer->attempts);
    }

    /** Unsupported transports must fail explicitly without trying the SMTP relay. */
    public function testUnsupportedTransportDoesNotFallBackToSmtp(): void
    {
        [$service, $mailer, $settings] = $this->delivery();
        $settings->transport = 'unsupported';
        $response = json_decode($service->sendMail('Test', 'Body', 'recipient@example.com', $settings), true);
        self::assertTrue($response['error']);
        self::assertSame('configuration', $response['code']);
        self::assertSame([], $mailer->attempts);
        self::assertNotSame('email_transport_error_configuration', $response['message']);
    }

    /** API adapters receive the same completed MIME representation as SMTP. */
    public function testTransportReceivesPreparedMimeAndItsResultIsPreserved(): void
    {
        $transport = $this->createMock(EmailTransportInterface::class);
        $outcome = EmailSendResult::failure('throttled', true, 120);
        $transport->expects(self::once())->method('send')->willReturnCallback(function (PHPMailer $message) use ($outcome) {
            self::assertStringContainsString('Subject: Transport test', $message->getSentMIMEMessage());
            self::assertStringContainsString('recipient@example.com', $message->getSentMIMEMessage());
            self::assertStringContainsString('Body', $message->Body);
            return $outcome;
        });
        $service = new EmailService($transport);
        $response = json_decode($service->sendMail('Transport test', 'Body', 'recipient@example.com', new EmailSettings([])), true);
        self::assertSame($outcome, $service->getLastResult());
        self::assertSame(120, $response['retry_after']);
        self::assertTrue($response['retryable']);
        self::assertSame('throttled', $response['code']);
    }

    /** Exceptions from a provider never expose details or silently invoke another transport. */
    public function testUnexpectedTransportFailureIsIndeterminateAndPrivate(): void
    {
        $transport = $this->createMock(EmailTransportInterface::class);
        $transport->expects(self::once())->method('send')->willThrowException(new RuntimeException('private-provider-secret'));
        $service = new EmailService($transport);
        $log = tempnam(sys_get_temp_dir(), 'teampass-email-test-');
        self::assertNotFalse($log);
        ini_set('error_log', $log);
        try {
            $response = $service->sendMail('Test', 'private-body', 'recipient@example.com', new EmailSettings([]));
            self::assertTrue($service->getLastResult()->indeterminate);
            self::assertFalse($service->getLastResult()->retryable);
            $logged = file_get_contents($log);
            self::assertStringContainsString('Email transport failure: RuntimeException', $logged);
            foreach (['private-provider-secret', 'private-body', 'recipient@example.com'] as $secret) {
                self::assertStringNotContainsString($secret, $response . $logged);
            }
        } finally {
            ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
            unlink($log);
        }
    }

    /** Real RCPT refusals are definite only when PHPMailer never submits DATA. */
    public function testRecipientRejectionsFollowTheActualSmtpDataBoundary(): void
    {
        foreach ([['reject_all', 1], ['reject_all', 2], ['reject_partial', 2]] as [$mode, $count]) {
            $server = new Process([PHP_BINARY, dirname(__DIR__) . '/Fixtures/email_smtp_server.php', $mode]);
            $server->setTimeout(15);
            $server->start();
            try {
                self::assertTrue($server->waitUntil(static fn(string $type, string $output): bool => str_contains($output, '"port"')), $server->getErrorOutput());
                $ready = json_decode(strtok($server->getOutput(), "\n"), true);
                $settings = new EmailSettings(['email_from' => 'sender@example.test', 'email_smtp_server' => '127.0.0.1',
                    'email_port' => $ready['port'], 'email_smtp_auth_mode' => 'none']);
                $settings->timeout = 5;
                $service = new EmailService();
                $recipients = $count === 1 ? 'first@example.test' : 'first@example.test,second@example.test';
                $response = json_decode($service->sendMail('Test', 'Synthetic body', $recipients, $settings), true);
                self::assertTrue($response['error']);
                $partial = $mode === 'reject_partial';
                self::assertSame($partial ? 'partial_acceptance' : 'rejected', $service->getLastResult()->errorCode);
                self::assertSame($partial, $service->getLastResult()->indeterminate);
                self::assertFalse($service->getLastResult()->retryable);
                $server->wait();
                self::assertSame(0, $server->getExitCode(), $server->getErrorOutput());
                $lines = explode("\n", trim($server->getOutput()));
                $recorded = json_decode(end($lines), true);
                self::assertSame($count, $recorded['recipients']);
                self::assertSame($partial, $recorded['data_submitted']);
            } finally {
                $server->stop(0);
            }
        }
    }

    /** Provider text cannot turn an ambiguous DATA outcome into a retryable connection failure. */
    public function testProviderCannotMakeAnAmbiguousSendRetryable(): void
    {
        [$service, $mailer, $settings] = $this->delivery();
        $mailer->reject = true;
        $mailer->failureMessage = 'SMTP Error: data not accepted. SMTP Error: Could not connect to SMTP host.';
        $service->sendMail('Test', 'Body', 'recipient@example.com', $settings);
        self::assertTrue($service->getLastResult()->indeterminate);
        self::assertFalse($service->getLastResult()->retryable);
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
        $mailer = new EmailDeliveryRecordingMailer(true);
        $service = new EmailService(null, static fn(): PHPMailer => $mailer);

        return [$service, $mailer, new EmailSettings(['email_from' => 'sender@example.com'])];
    }

    /** Selecting another mode cannot retain OAuth, custom headers or attachments from a previous message. */
    public function testEachSendCreatesAnIndependentMessageBuilder(): void
    {
        $builders = [];
        $factory = static function () use (&$builders): PHPMailer {
            $mailer = new EmailDeliveryRecordingMailer(true);
            $builders[] = $mailer;
            return $mailer;
        };
        $service = new EmailService(null, $factory);
        $settings = new EmailSettings(['email_from' => 'sender@example.com']);
        self::assertSame('', $service->sendMail('First', 'Body', 'first@example.com', $settings));
        $builders[1]->addStringAttachment('private-attachment', 'private.txt');
        $builders[1]->AuthType = 'XOAUTH2';
        self::assertSame('', $service->sendMail('Second', 'Body', 'second@example.com', $settings));
        self::assertNotSame($builders[1], $builders[2]);
        self::assertSame([], $builders[2]->getAttachments());
        self::assertSame('', $builders[2]->AuthType);
        self::assertSame(['second@example.com'], $builders[2]->attempts[0]['recipients']);
    }

    /** Exchange OAuth always uses its fixed STARTTLS endpoint and certificate verification. */
    public function testExchangeOAuthCannotUseBasicCredentialsOrDisableTls(): void
    {
        [$service, $mailer, $settings] = $this->delivery();
        $settings->revision = str_repeat('a', 32);
        $settings->smtpAuthMode = 'microsoft_oauth';
        $settings->microsoftTenantId = '11111111-1111-1111-1111-111111111111';
        $settings->microsoftClientId = '22222222-2222-2222-2222-222222222222';
        $settings->microsoftClientSecret = 'private-client-secret';
        $settings->microsoftMailbox = 'sender@example.com';
        $settings->verifyCertificate = false;
        $settings->security = 'none';
        $settings->authPassword = 'private-basic-password';
        $service->configureMailer($settings, true, true);
        self::assertSame('smtp.office365.com', $mailer->Host);
        self::assertSame(587, $mailer->Port);
        self::assertSame('tls', $mailer->SMTPSecure);
        self::assertSame(EmailService::tlsOptions(true), $mailer->SMTPOptions);
        self::assertSame('XOAUTH2', $mailer->AuthType);
        self::assertSame('', $mailer->Password);
        self::assertNotNull($mailer->getOAuth());
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

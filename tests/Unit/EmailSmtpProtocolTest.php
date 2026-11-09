<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use TeampassClasses\EmailService\EmailAccessTokenProviderInterface;
use TeampassClasses\EmailService\MicrosoftSmtpOAuthProvider;
use TeampassClasses\EmailService\SmtpTransport;

final class EmailSmtpProtocolTest extends TestCase
{
    /** @return array<string,array{string}> */
    public static function modes(): array { return ['relay' => ['none'], 'password' => ['password'], 'Exchange SASL' => ['oauth']]; }

    /** Real SMTP commands exercise PHPMailer's relay, LOGIN and XOAUTH2 paths on a synthetic loopback server. @dataProvider modes */
    public function testPreparedMimeCrossesTheSmtpProtocolBoundary(string $mode): void
    {
        $server = new Process([PHP_BINARY, dirname(__DIR__) . '/Fixtures/email_smtp_server.php', $mode]);
        $server->setTimeout(15);
        $server->start();
        try {
            self::assertTrue($server->waitUntil(static fn(string $type, string $output): bool => str_contains($output, '"port"')), $server->getErrorOutput());
            $ready = json_decode(strtok($server->getOutput(), "\n"), true);
            $message = new PHPMailer(true);
            $message->isSMTP();
            $message->Host = '127.0.0.1';
            $message->Port = $ready['port'];
            $message->Timeout = 5;
            $message->SMTPAutoTLS = false;
            $message->SMTPAuth = $mode !== 'none';
            $message->Username = 'sender@example.test';
            if ($mode === 'password') {
                $message->AuthType = 'LOGIN';
                $message->Password = 'synthetic-password';
            } elseif ($mode === 'oauth') {
                $message->AuthType = 'XOAUTH2';
                $tokens = $this->createMock(EmailAccessTokenProviderInterface::class);
                $tokens->expects(self::once())->method('getAccessToken')->willReturn('synthetic-access-token');
                $message->setOAuth(new MicrosoftSmtpOAuthProvider('sender@example.test', $tokens));
            }
            $message->setFrom('sender@example.test');
            $message->addAddress('recipient@example.test');
            $message->addCC('copy@example.test');
            $message->addBCC('hidden@example.test');
            $message->isHTML(true);
            $message->Subject = 'Synthetic notification';
            $message->Body = '<p>Synthetic HTML body</p>';
            $message->AltBody = "Synthetic text body\r\n.line starting with a dot";
            $message->addStringAttachment('synthetic-attachment', 'sample.txt');
            $message->preSend();
            $result = (new SmtpTransport())->send($message);
            self::assertTrue($result->accepted);
            $server->wait();
            self::assertSame(0, $server->getExitCode(), $server->getErrorOutput());
            $lines = explode("\n", trim($server->getOutput()));
            $recorded = json_decode(end($lines), true);
            self::assertTrue($recorded['authenticated']);
            self::assertSame(3, $recorded['recipients']);
            self::assertSame(hash('sha256', rtrim($message->getSentMIMEMessage(), "\r\n")), $recorded['mime_hash']);
            self::assertStringNotContainsString('hidden@example.test', $message->getSentMIMEMessage());
        } finally {
            $server->stop(0);
        }
    }
}

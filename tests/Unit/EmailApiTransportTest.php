<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use TeampassClasses\EmailService\ApiTransport;
use TeampassClasses\EmailService\EmailAccessTokenProviderInterface;
use TeampassClasses\EmailService\EmailSettings;
use TeampassClasses\EmailService\MicrosoftSmtpOAuthProvider;

final class EmailApiTransportTest extends TestCase
{
    private function settings(string $transport): EmailSettings
    {
        $settings = new EmailSettings(['email_transport' => $transport]);
        $settings->microsoftMailbox = $settings->googleSender = 'sender@example.test';
        return $settings;
    }

    private function client(array $responses, array &$history): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));
        return new Client(['handler' => $stack]);
    }

    private function message(): PHPMailer
    {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom('sender@example.test', 'R&D');
        $mailer->addAddress('recipient@example.test');
        $mailer->addCC('copy@example.test');
        $mailer->addReplyTo('reply@example.test');
        $mailer->addCustomHeader('X-TeamPass-Test', 'preserved');
        $mailer->Subject = 'Transport émail';
        $mailer->isHTML(true);
        $mailer->Body = '<p>R&amp;D O&#039;Brien &lt;label&gt;</p>';
        $mailer->AltBody = "R&D O'Brien <label>";
        $mailer->addStringAttachment('attachment-content', 'test.txt');
        $mailer->preSend();
        return $mailer;
    }

    /** Providers receive exactly the same prepared MIME, including text, HTML and attachments. */
    public function testGraphPreservesMimeAndAcceptsOnly202(): void
    {
        $history = [];
        $tokens = $this->createMock(EmailAccessTokenProviderInterface::class);
        $tokens->expects(self::once())->method('getAccessToken')->willReturn('private-token');
        $message = $this->message();
        $transport = new ApiTransport($this->settings('microsoft_graph'), $tokens, $this->client([new Response(202)], $history));
        self::assertTrue($transport->send($message)->accepted);
        $request = $history[0]['request'];
        self::assertSame('https://graph.microsoft.com/v1.0/users/sender%40example.test/sendMail', (string) $request->getUri());
        self::assertSame($message->getSentMIMEMessage(), base64_decode((string) $request->getBody(), true));
        self::assertSame('text/plain', $request->getHeaderLine('Content-Type'));
        self::assertTrue($history[0]['options']['verify']);
        self::assertFalse($history[0]['options']['allow_redirects']);
    }

    /** Address the configured account rather than silently sending as a different consenting account. */
    public function testGmailUsesBase64urlAndConfiguredAccount(): void
    {
        $history = [];
        $tokens = $this->createMock(EmailAccessTokenProviderInterface::class);
        $tokens->method('getAccessToken')->willReturn('private-token');
        $message = $this->message();
        $transport = new ApiTransport($this->settings('gmail_api'), $tokens, $this->client([new Response(200, [], '{"id":"18ab9"}')], $history));
        $result = $transport->send($message);
        self::assertTrue($result->accepted);
        self::assertSame('18ab9', $result->providerMessageId);
        self::assertSame('https://gmail.googleapis.com/gmail/v1/users/sender%40example.test/messages/send', (string) $history[0]['request']->getUri());
        $json = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame($message->getSentMIMEMessage(), base64_decode(strtr($json['raw'], '-_', '+/'), true));
        self::assertDoesNotMatchRegularExpression('/[+=\/]/', $json['raw']);
    }

    /** Only an explicit 401 allows one retry with a renewed token. */
    public function testUnauthorizedRequestRenewsOnce(): void
    {
        $history = [];
        $tokens = $this->createMock(EmailAccessTokenProviderInterface::class);
        $tokens->expects(self::exactly(2))->method('getAccessToken')->willReturnOnConsecutiveCalls('old-token', 'new-token');
        $tokens->expects(self::once())->method('invalidateAccessToken')->with('old-token');
        $transport = new ApiTransport($this->settings('microsoft_graph'), $tokens, $this->client([new Response(401), new Response(202)], $history));
        self::assertTrue($transport->send($this->message())->accepted);
        self::assertCount(2, $history);
        self::assertSame('Bearer new-token', $history[1]['request']->getHeaderLine('Authorization'));
    }

    /** Never keep refreshing credentials when permissions or consent are missing. */
    public function testSecondUnauthorizedRequestStops(): void
    {
        $history = [];
        $tokens = $this->createMock(EmailAccessTokenProviderInterface::class);
        $tokens->expects(self::exactly(2))->method('getAccessToken')->willReturn('token');
        $tokens->expects(self::exactly(2))->method('invalidateAccessToken')->with('token');
        $transport = new ApiTransport($this->settings('microsoft_graph'), $tokens, $this->client([new Response(401), new Response(401)], $history));
        $result = $transport->send($this->message());
        self::assertSame('authentication', $result->errorCode);
        self::assertFalse($result->retryable);
    }

    /** A provider may accept a message before its error response is lost. */
    public function testAmbiguousResponsesNeverReplay(): void
    {
        foreach ([new Response(503), new Response(200, [], 'private-response'), new ConnectException('private-url-token', new Request('POST', 'https://example.test'), null, ['errno' => 28])] as $response) {
            $history = [];
            $tokens = $this->createMock(EmailAccessTokenProviderInterface::class);
            $tokens->expects(self::once())->method('getAccessToken')->willReturn('token');
            $transport = new ApiTransport($this->settings('gmail_api'), $tokens, $this->client([$response], $history));
            $result = $transport->send($this->message());
            self::assertTrue($result->indeterminate);
            self::assertFalse($result->retryable);
            self::assertStringNotContainsString('private', json_encode($result->toArray()));
            self::assertCount(1, $history);
        }
    }

    /** A confirmed throttle is deferred without sleeping or falling back to SMTP. */
    public function testThrottlingPreservesRetryAfter(): void
    {
        $history = [];
        $tokens = $this->createMock(EmailAccessTokenProviderInterface::class);
        $tokens->method('getAccessToken')->willReturn('token');
        $transport = new ApiTransport($this->settings('microsoft_graph'), $tokens, $this->client([new Response(429, ['Retry-After' => '7200'], 'private-provider-body')], $history));
        $result = $transport->send($this->message());
        self::assertSame('throttled', $result->errorCode);
        self::assertTrue($result->retryable);
        self::assertSame(7200, $result->retryAfter);
        self::assertCount(1, $history);
    }

    /** Unsupported envelope features must be refused before acquiring or sending a token. */
    public function testBccAndOversizedMessagesAreExplicitlyRejected(): void
    {
        $tokens = $this->createMock(EmailAccessTokenProviderInterface::class);
        $tokens->expects(self::never())->method('getAccessToken');
        $history = [];
        $transport = new ApiTransport($this->settings('gmail_api'), $tokens, $this->client([], $history));
        $bcc = $this->message();
        $bcc->addBCC('hidden@example.test');
        $bcc->preSend();
        self::assertSame('message', $transport->send($bcc)->errorCode);
        $large = $this->message();
        $large->addStringAttachment(str_repeat('x', ApiTransport::MAX_MIME_BYTES), 'large.txt');
        $large->preSend();
        self::assertSame('message', $transport->send($large)->errorCode);
        self::assertCount(0, $history);
    }

    /** PHPMailer uses app tokens in SASL, without an SMTP password. */
    public function testExchangeSaslPayload(): void
    {
        $tokens = $this->createMock(EmailAccessTokenProviderInterface::class);
        $tokens->expects(self::once())->method('getAccessToken')->willReturn('private-token');
        $provider = new MicrosoftSmtpOAuthProvider('sender@example.test', $tokens);
        self::assertSame("user=sender@example.test\x01auth=Bearer private-token\x01\x01", base64_decode($provider->getOauth64(), true));
    }
}

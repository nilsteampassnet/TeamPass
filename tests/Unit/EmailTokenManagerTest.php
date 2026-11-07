<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TeampassClasses\EmailService\EmailHttp;
use TeampassClasses\EmailService\EmailSettings;
use TeampassClasses\EmailService\EmailTokenManager;
use TeampassClasses\EmailService\EmailTokenStoreInterface;
use TeampassClasses\EmailService\EmailTransportException;

/** In-memory persistence boundary; the real store has separate database integration tests. */
final class EmailMemoryTokenStore implements EmailTokenStoreInterface
{
    public array $states = [];
    public bool $busy = false;
    public bool $writable = true;
    public int $claims = 0;
    public int $releases = 0;
    public array $invalidations = [];

    /** Read the state for this audience and revision. */
    public function load(string $cacheKey): ?array { return $this->states[$cacheKey] ?? null; }
    /** Model a competing renewal owner. */
    public function claim(string $cacheKey, string $owner, int $leaseSeconds): bool { ++$this->claims; return !$this->busy; }
    /** Model a configuration change during renewal. */
    public function save(string $cacheKey, string $owner, #[SensitiveParameter] array $token): bool
    {
        if (!$this->writable) { return false; }
        $this->states[$cacheKey] = $token;
        return true;
    }
    /** Track release after both success and failure. */
    public function release(string $cacheKey, string $owner): void { ++$this->releases; }
    /** Record the observed token without exposing it in diagnostics. */
    public function invalidateAccess(string $cacheKey, #[SensitiveParameter] string $observedAccessToken): void
    {
        $this->invalidations[] = [$cacheKey, $observedAccessToken];
    }
}

final class EmailTokenManagerTest extends TestCase
{
    private function settings(): EmailSettings
    {
        $settings = new EmailSettings([]);
        $settings->revision = str_repeat('a', 32);
        $settings->microsoftTenantId = '11111111-1111-1111-1111-111111111111';
        $settings->microsoftClientId = '22222222-2222-2222-2222-222222222222';
        $settings->microsoftClientSecret = 'private-client-secret';
        $settings->microsoftMailbox = 'sender@example.test';
        $settings->googleClientId = 'example.apps.googleusercontent.com';
        $settings->googleClientSecret = 'private-google-secret';
        $settings->googleSender = 'sender@example.test';
        return $settings;
    }

    private function client(array $responses, array &$history): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));
        return new Client(['handler' => $stack]);
    }

    private function token(string $access = 'access-token', ?string $refresh = null): Response
    {
        $body = ['access_token' => $access, 'expires_in' => 3600, 'token_type' => 'Bearer'];
        if ($refresh !== null) { $body['refresh_token'] = $refresh; }
        return new Response(200, [], json_encode($body));
    }

    /** Cache tokens, but never share a Graph token with the SMTP audience. */
    public function testMicrosoftAudiencesAreIsolatedAndTokensReused(): void
    {
        $history = [];
        $http = $this->client([$this->token('graph-token'), $this->token('smtp-token')], $history);
        $store = new EmailMemoryTokenStore();
        $graph = new EmailTokenManager($this->settings(), 'microsoft_graph', $store, $http);
        self::assertSame('graph-token', $graph->getAccessToken());
        self::assertSame('graph-token', $graph->getAccessToken());
        $smtp = new EmailTokenManager($this->settings(), 'microsoft_smtp', $store, $http);
        self::assertSame('smtp-token', $smtp->getAccessToken());
        self::assertCount(2, $history);
        parse_str((string) $history[0]['request']->getBody(), $graphForm);
        parse_str((string) $history[1]['request']->getBody(), $smtpForm);
        self::assertSame('https://graph.microsoft.com/.default', $graphForm['scope']);
        self::assertSame('https://outlook.office365.com/.default', $smtpForm['scope']);
        self::assertSame('client_credentials', $smtpForm['grant_type']);
        self::assertTrue($history[0]['options']['verify']);
        self::assertFalse($history[0]['options']['allow_redirects']);
        self::assertSame(2, $store->releases);
        self::assertCount(2, $store->states);
    }

    /** A live lease defers work and never makes a second token request. */
    public function testCompetingRenewalDefersWithoutHttp(): void
    {
        $history = [];
        $store = new EmailMemoryTokenStore();
        $store->busy = true;
        $manager = new EmailTokenManager($this->settings(), 'microsoft_graph', $store, $this->client([], $history));
        try { $manager->getAccessToken(); self::fail('Expected a busy renewal'); }
        catch (EmailTransportException $e) {
            self::assertSame('temporary', $e->result->errorCode);
            self::assertTrue($e->result->retryable);
            self::assertSame(2, $e->result->retryAfter);
        }
        self::assertCount(0, $history);
    }

    /** Gmail refresh responses need not repeat the refresh token; retain the encrypted authorization. */
    public function testGoogleRefreshRetainsAndRotatesAuthorization(): void
    {
        $history = [];
        $store = new EmailMemoryTokenStore();
        $http = $this->client([$this->token('first', 'refresh-one'), $this->token('second'), $this->token('third', 'refresh-two')], $history);
        $manager = new EmailTokenManager($this->settings(), 'gmail_api', $store, $http);
        $token = $manager->exchangeGoogleCode('code', str_repeat('v', 43), 'https://example.test/sources/emails_oauth.php');
        $manager->storeAuthorization($token);
        $key = array_key_first($store->states);
        $store->states[$key]['expires_at'] = time();
        self::assertSame('second', $manager->getAccessToken());
        self::assertSame('refresh-one', $store->states[$key]['refresh_token']);
        $store->states[$key]['expires_at'] = time();
        self::assertSame('third', $manager->getAccessToken());
        self::assertSame('refresh-two', $store->states[$key]['refresh_token']);
        parse_str((string) $history[1]['request']->getBody(), $form);
        self::assertSame('refresh_token', $form['grant_type']);
        self::assertSame('refresh-one', $form['refresh_token']);
        $manager->invalidateAccessToken('observed-old-token');
        self::assertSame([$key, 'observed-old-token'], $store->invalidations[0]);
    }

    /** A token obtained for a stale configuration must not be used to send. */
    public function testConfigurationChangedDuringAcquisition(): void
    {
        $history = [];
        $store = new EmailMemoryTokenStore();
        $store->writable = false;
        $manager = new EmailTokenManager($this->settings(), 'microsoft_graph', $store, $this->client([$this->token()], $history));
        try { $manager->getAccessToken(); self::fail('Expected stale configuration'); }
        catch (EmailTransportException $e) { self::assertSame('configuration', $e->result->errorCode); }
        self::assertSame(1, $store->releases);
    }

    /** Provider errors never become public diagnostics, and Retry-After is preserved. */
    public function testRateLimitContainsNoProviderBody(): void
    {
        $history = [];
        $store = new EmailMemoryTokenStore();
        $manager = new EmailTokenManager($this->settings(), 'microsoft_graph', $store, $this->client([new Response(429, ['Retry-After' => '172800'], 'private-client-secret')], $history));
        try { $manager->getAccessToken(); self::fail('Expected throttling'); }
        catch (EmailTransportException $e) {
            self::assertSame('throttled', $e->getMessage());
            self::assertSame(172800, $e->result->retryAfter);
            self::assertNull($e->getPrevious());
        }
        self::assertSame(1, $store->releases);
    }

    /** Invalid endpoint identities and redirect credentials are refused before any request. */
    public function testConsentUsesPkceAndOnlyTheSendScope(): void
    {
        $history = [];
        $manager = new EmailTokenManager($this->settings(), 'gmail_api', new EmailMemoryTokenStore(), $this->client([], $history));
        $provider = $manager->googleAuthorizationProvider('https://example.test/sources/emails_oauth.php');
        parse_str(parse_url($provider->getAuthorizationUrl(['access_type' => 'offline', 'prompt' => 'consent']), PHP_URL_QUERY), $query);
        self::assertSame('https://www.googleapis.com/auth/gmail.send', $query['scope']);
        self::assertSame('S256', $query['code_challenge_method']);
        self::assertNotEmpty($provider->getState());
        self::assertNotEmpty($provider->getPkceCode());
        $this->expectException(EmailTransportException::class);
        $manager->googleAuthorizationProvider('https://user@example.test/callback');
    }

    /** Bound success responses too: a proxy can return untrusted HTML or oversized JSON. */
    public function testMalformedAndOversizedResponsesAreRejected(): void
    {
        self::assertNull(EmailHttp::json(new Response(200, [], str_repeat('x', 65537))));
        self::assertNull(EmailHttp::json(new Response(200, [], '<html>private</html>')));
        self::assertNull(EmailHttp::retryAfter(new Response(429, ['Retry-After' => 'invalid'])));
        self::assertGreaterThanOrEqual(100, EmailHttp::retryAfter(new Response(429, ['Retry-After' => gmdate(DATE_RFC7231, time() + 120)])));
    }
}

<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * This program is free software under the GNU General Public License, version 3
 * or later, without any warranty. See <https://www.gnu.org/licenses/>.
 */

namespace TeampassClasses\EmailService;

use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Provider\GenericProvider;

/** Server-side token acquisition, independent of TeamPass user sign-in. */
final class EmailTokenManager implements EmailAccessTokenProviderInterface
{
    private string $cacheKey;

    /** Bind tokens to their audience, sender, application and immutable configuration revision. */
    public function __construct(
        #[\SensitiveParameter] private readonly EmailSettings $settings,
        private readonly string $purpose,
        private readonly EmailTokenStoreInterface $store,
        private readonly ClientInterface $http
    ) {
        if (!$settings->providerConfigurationIsValid($purpose)) {
            throw new EmailTransportException(EmailSendResult::failure('configuration'));
        }
        $this->cacheKey = hash('sha256', (string) json_encode([
            $purpose, $settings->revision, $settings->microsoftTenantId, $settings->microsoftClientId,
            strtolower($settings->microsoftMailbox), $settings->googleClientId, strtolower($settings->googleSender),
        ]));
    }

    /** Reuse a valid access token; serialize renewal without keeping a transaction open. */
    public function getAccessToken(): string
    {
        $state = $this->store->load($this->cacheKey);
        if ($this->isFresh($state)) {
            return $state['access_token'];
        }
        $owner = bin2hex(random_bytes(16));
        if (!$this->store->claim($this->cacheKey, $owner, max(60, min(600, (int) $this->settings->timeout + 15)))) {
            throw new EmailTransportException(EmailSendResult::failure('temporary', true, 2));
        }
        try {
            // Another worker may have renewed between the first read and our claim.
            $state = $this->store->load($this->cacheKey);
            if ($this->isFresh($state)) {
                return $state['access_token'];
            }
            if ($this->purpose === 'gmail_api') {
                if (empty($state['refresh_token'])) {
                    throw new EmailTransportException(EmailSendResult::failure('authentication'));
                }
                $token = $this->requestToken('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'refresh_token', 'client_id' => $this->settings->googleClientId,
                    'client_secret' => $this->settings->googleClientSecret, 'refresh_token' => $state['refresh_token'],
                ], $state['refresh_token']);
            } else {
                $scope = $this->purpose === 'microsoft_smtp' ? 'https://outlook.office365.com/.default' : 'https://graph.microsoft.com/.default';
                $token = $this->requestToken($this->microsoftTokenUrl(), [
                    'grant_type' => 'client_credentials', 'client_id' => $this->settings->microsoftClientId,
                    'client_secret' => $this->settings->microsoftClientSecret, 'scope' => $scope,
                ]);
            }
            if (!$this->store->save($this->cacheKey, $owner, $token)) {
                throw new EmailTransportException(EmailSendResult::failure('configuration'));
            }
            return $token['access_token'];
        } finally {
            $this->store->release($this->cacheKey, $owner);
        }
    }

    /** A stale provider response cannot invalidate a newer token. */
    public function invalidateAccessToken(#[\SensitiveParameter] string $accessToken): void
    {
        $this->store->invalidateAccess($this->cacheKey, $accessToken);
    }

    /** Build the existing League provider for one-time administrative consent with PKCE. */
    public function googleAuthorizationProvider(string $redirectUri): GenericProvider
    {
        if ($this->purpose !== 'gmail_api' || !$this->validRedirect($redirectUri)) {
            throw new EmailTransportException(EmailSendResult::failure('configuration'));
        }
        return new GenericProvider([
            'clientId' => $this->settings->googleClientId, 'clientSecret' => $this->settings->googleClientSecret,
            'redirectUri' => $redirectUri, 'urlAuthorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'urlAccessToken' => 'https://oauth2.googleapis.com/token',
            'urlResourceOwnerDetails' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scopes' => ['https://www.googleapis.com/auth/gmail.send'],
            'scopeSeparator' => ' ', 'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
        ], ['httpClient' => $this->http]);
    }

    /** Exchange an already state-validated administrative authorization, without persisting yet. */
    public function exchangeGoogleCode(#[\SensitiveParameter] string $code, #[\SensitiveParameter] string $verifier, string $redirectUri): array
    {
        if ($this->purpose !== 'gmail_api' || !$this->validRedirect($redirectUri)
            || preg_match('/\A[A-Za-z0-9._~-]{43,128}\z/', $verifier) !== 1 || $code === ''
        ) {
            throw new EmailTransportException(EmailSendResult::failure('configuration'));
        }
        $token = $this->requestToken('https://oauth2.googleapis.com/token', [
            'grant_type' => 'authorization_code', 'code' => $code, 'code_verifier' => $verifier,
            'client_id' => $this->settings->googleClientId, 'client_secret' => $this->settings->googleClientSecret,
            'redirect_uri' => $redirectUri,
        ]);
        if ($token['refresh_token'] === '') {
            throw new EmailTransportException(EmailSendResult::failure('authentication'));
        }
        return $token;
    }

    /** Persist an authorization only after its configuration revision has been activated atomically. */
    public function storeAuthorization(#[\SensitiveParameter] array $token): void
    {
        $owner = bin2hex(random_bytes(16));
        if (!$this->store->claim($this->cacheKey, $owner, 60)) {
            throw new EmailTransportException(EmailSendResult::failure('temporary', true, 2));
        }
        try {
            if (!$this->store->save($this->cacheKey, $owner, $token)) {
                throw new EmailTransportException(EmailSendResult::failure('configuration'));
            }
        } finally {
            $this->store->release($this->cacheKey, $owner);
        }
    }

    private function isFresh(?array $state): bool
    {
        return $state !== null && $state['access_token'] !== '' && $state['expires_at'] > time() + 60;
    }

    private function microsoftTokenUrl(): string
    {
        return 'https://login.microsoftonline.com/' . $this->settings->microsoftTenantId . '/oauth2/v2.0/token';
    }

    private function validRedirect(string $uri): bool
    {
        $parts = parse_url($uri);
        return is_array($parts) && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['fragment'])
            && isset($parts['host']) && (($parts['scheme'] ?? '') === 'https'
                || (($parts['scheme'] ?? '') === 'http' && in_array($parts['host'], ['localhost', '127.0.0.1', '[::1]'], true)));
    }

    /** @return array{access_token:string, refresh_token:string, expires_at:int} */
    private function requestToken(string $url, #[\SensitiveParameter] array $form, #[\SensitiveParameter] string $existingRefresh = ''): array
    {
        try {
            $response = $this->http->request('POST', $url, EmailHttp::options($this->settings, ['form_params' => $form]));
            $status = $response->getStatusCode();
            if ($status !== 200) {
                $retryAfter = EmailHttp::retryAfter($response);
                $response->getBody()->close();
                $result = match (true) {
                    $status === 429 => EmailSendResult::failure('throttled', true, $retryAfter),
                    $status >= 500 => EmailSendResult::failure('temporary', true, $retryAfter),
                    $status === 403 => EmailSendResult::failure('permission'),
                    $status === 400 || $status === 401 => EmailSendResult::failure('authentication'),
                    default => EmailSendResult::failure('configuration'),
                };
                throw new EmailTransportException($result);
            }
            $data = EmailHttp::json($response);
            $lifetime = filter_var($data['expires_in'] ?? null, FILTER_VALIDATE_INT);
            if (!is_string($data['access_token'] ?? null) || $data['access_token'] === '' || strlen($data['access_token']) > 32768
                || $lifetime === false || $lifetime <= 60 || $lifetime > PHP_INT_MAX - time()
                || !is_string($data['token_type'] ?? null) || strcasecmp($data['token_type'], 'Bearer') !== 0
                || !is_string($data['refresh_token'] ?? $existingRefresh)
            ) {
                throw new EmailTransportException(EmailSendResult::failure('authentication'));
            }
            return ['access_token' => $data['access_token'], 'refresh_token' => $data['refresh_token'] ?? $existingRefresh, 'expires_at' => time() + $lifetime];
        } catch (EmailTransportException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Token acquisition can be retried: no message has been submitted.
            throw new EmailTransportException(EmailSendResult::failure(EmailHttp::networkCode($e), true));
        }
    }
}

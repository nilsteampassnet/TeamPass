<?php

declare(strict_types=1);

namespace Webauthn\CeremonyStep;

use function count;
use function in_array;
use InvalidArgumentException;
use function is_array;
use function is_string;
use function sprintf;
use function trigger_deprecation;
use Webauthn\AuthenticationExtensions\AuthenticationExtensions;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\CredentialRecord;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;

final readonly class CheckAllowedOrigins implements CeremonyStep
{
    /**
     * Full origin entries (scheme://host[:port]) from allowed origins.
     *
     * @var string[]
     */
    private array $fullOrigins;

    /**
     * Non-URL facet identifiers (e.g. android:apk-key-hash:...) matched verbatim against clientDataJSON.origin.
     *
     * @var string[]
     */
    private array $rawOrigins;

    /**
     * @param string[] $allowedOrigins
     * @param string[] $securedRelyingPartyId RP IDs that are allowed to use HTTP (e.g. localhost for development)
     */
    public function __construct(
        array $allowedOrigins,
        private bool $allowSubdomains = false,
        private array $securedRelyingPartyId = [],
    ) {
        $fullOrigins = [];
        $rawOrigins = [];
        foreach ($allowedOrigins as $allowedOrigin) {
            $parsed = parse_url($allowedOrigin);
            $parsed !== false || throw new InvalidArgumentException(sprintf('Invalid origin: %s', $allowedOrigin));
            if (isset($parsed['scheme'], $parsed['host'])) {
                $fullOrigins[] = self::buildOrigin($parsed['scheme'], $parsed['host'], $parsed['port'] ?? null);
            } elseif (isset($parsed['scheme'])) {
                $rawOrigins[] = $allowedOrigin;
            } else {
                // Host-only entries are normalized to https:// since WebAuthn requires TLS
                $host = $parsed['host'] ?? $allowedOrigin;
                $fullOrigins[] = self::buildOrigin('https', $host, null);
            }
        }

        $this->fullOrigins = array_unique($fullOrigins);
        $this->rawOrigins = array_unique($rawOrigins);
    }

    public function process(
        CredentialRecord $credentialRecord,
        AuthenticatorAssertionResponse|AuthenticatorAttestationResponse $authenticatorResponse,
        PublicKeyCredentialRequestOptions|PublicKeyCredentialCreationOptions $publicKeyCredentialOptions,
        ?string $userHandle,
        string $host
    ): void {
        if ($credentialRecord instanceof PublicKeyCredentialSource) {
            trigger_deprecation(
                'web-auth/webauthn-lib',
                '5.3',
                'Passing a PublicKeyCredentialSource to "%s::process()" is deprecated, pass a CredentialRecord instead.',
                self::class
            );
        }
        $authData = $authenticatorResponse instanceof AuthenticatorAssertionResponse ? $authenticatorResponse->authenticatorData : $authenticatorResponse->attestationObject->authData;
        $C = $authenticatorResponse->clientDataJSON;

        if (in_array($C->origin, $this->rawOrigins, true)) {
            return;
        }

        $parsedOrigin = parse_url($C->origin);
        is_array($parsedOrigin) || throw AuthenticatorResponseVerificationException::create(
            'Invalid origin. Unable to parse the origin.'
        );
        $originHost = $parsedOrigin['host'] ?? $C->origin;

        $hasAllowedOrigins = count($this->fullOrigins) !== 0 || count($this->rawOrigins) !== 0;

        if ($hasAllowedOrigins) {
            // Full origin match (scheme + host + port)
            if (isset($parsedOrigin['scheme'], $parsedOrigin['host'])) {
                $normalizedOrigin = self::buildOrigin(
                    $parsedOrigin['scheme'],
                    $parsedOrigin['host'],
                    $parsedOrigin['port'] ?? null
                );
                if (in_array($normalizedOrigin, $this->fullOrigins, true)) {
                    return;
                }
            }

            // Subdomain matching
            $isSubDomain = $this->isSubdomainOfFullOrigins($parsedOrigin);

            if ($this->allowSubdomains && $isSubDomain) {
                return;
            }
            if (! $this->allowSubdomains && $isSubDomain) {
                throw AuthenticatorResponseVerificationException::create('Invalid origin. Subdomains are not allowed.');
            }
            throw AuthenticatorResponseVerificationException::create(
                'Invalid origin. Not in the list of allowed origins.'
            );
        }

        $rpId = $publicKeyCredentialOptions->rpId ?? $publicKeyCredentialOptions->rp->id ?? $host;
        $facetId = $this->getFacetId($rpId, $publicKeyCredentialOptions->extensions, $authData->extensions);

        if (! in_array($facetId, $this->securedRelyingPartyId, true)) {
            $scheme = $parsedOrigin['scheme'] ?? '';
            $scheme === 'https' || throw AuthenticatorResponseVerificationException::create(
                'Invalid scheme. HTTPS required.'
            );
        }
        $facetId !== '' || throw AuthenticatorResponseVerificationException::create(
            'Invalid origin. Unable to determine the facet ID.'
        );
        if ($originHost === $facetId) {
            return;
        }
        $isSubDomains = $this->isSubdomainOf($originHost, $facetId);
        if ($this->allowSubdomains && $isSubDomains) {
            return;
        }
        if (! $this->allowSubdomains && $isSubDomains) {
            throw AuthenticatorResponseVerificationException::create('Invalid origin. Subdomains are not allowed.');
        }
        throw AuthenticatorResponseVerificationException::create('Invalid origin.');
    }

    /**
     * @param array<string, mixed> $parsedOrigin Parsed origin from parse_url()
     */
    private function isSubdomainOfFullOrigins(array $parsedOrigin): bool
    {
        if (! isset($parsedOrigin['scheme'], $parsedOrigin['host'])) {
            return false;
        }
        /** @var string $originScheme */
        $originScheme = $parsedOrigin['scheme'];
        /** @var string $originHost */
        $originHost = $parsedOrigin['host'];
        $originPort = $parsedOrigin['port'] ?? null;

        foreach ($this->fullOrigins as $fullOrigin) {
            $parsedAllowed = parse_url($fullOrigin);
            if (! is_array($parsedAllowed) || ! isset($parsedAllowed['scheme'], $parsedAllowed['host'])) {
                continue;
            }
            /** @var string $allowedScheme */
            $allowedScheme = $parsedAllowed['scheme'];
            /** @var string $allowedHost */
            $allowedHost = $parsedAllowed['host'];
            if ($originScheme !== $allowedScheme) {
                continue;
            }
            $allowedPort = $parsedAllowed['port'] ?? null;
            if ($originPort !== $allowedPort) {
                continue;
            }
            if ($this->isSubdomainOf($originHost, $allowedHost)) {
                return true;
            }
        }

        return false;
    }

    private static function buildOrigin(string $scheme, string $host, ?int $port): string
    {
        if ($port === null) {
            return sprintf('%s://%s', $scheme, $host);
        }
        $defaultPorts = [
            'https' => 443,
            'http' => 80,
        ];
        if (isset($defaultPorts[$scheme]) && $port === $defaultPorts[$scheme]) {
            return sprintf('%s://%s', $scheme, $host);
        }
        return sprintf('%s://%s:%d', $scheme, $host, $port);
    }

    private function isSubdomainOf(string $subdomain, string $domain): bool
    {
        return str_ends_with('.' . $subdomain, '.' . $domain);
    }

    private function getFacetId(
        string $rpId,
        AuthenticationExtensions $AuthenticationExtensions,
        ?AuthenticationExtensions $authenticationExtensionsClientOutputs
    ): string {
        if ($authenticationExtensionsClientOutputs === null
            || ! $AuthenticationExtensions->has('appid')
            || ! $authenticationExtensionsClientOutputs->has('appid')) {
            return $rpId;
        }

        $appId = $AuthenticationExtensions->get('appid')
            ->value;
        $wasUsed = $authenticationExtensionsClientOutputs->get('appid')
            ->value;

        return (is_string($appId) && $wasUsed === true) ? $appId : $rpId;
    }
}

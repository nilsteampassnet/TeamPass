<?php

declare(strict_types=1);

namespace Webauthn\CeremonyStep;

use function in_array;
use function is_array;
use function is_string;
use function strlen;
use function trigger_deprecation;
use Webauthn\AuthenticationExtensions\AuthenticationExtensions;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\CredentialRecord;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;

/**
 * @deprecated since 5.2.0 and will be removed in 6.0.0. Will be replaced by CheckAllowedOrigins
 */
final readonly class CheckOrigin implements CeremonyStep
{
    /**
     * @param string[] $securedRelyingPartyId
     */
    public function __construct(
        private array $securedRelyingPartyId
    ) {
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
        $rpId = $publicKeyCredentialOptions->rpId ?? $publicKeyCredentialOptions->rp->id ?? $host;
        $facetId = $this->getFacetId($rpId, $publicKeyCredentialOptions->extensions, $authData->extensions);
        $parsedRelyingPartyId = parse_url($C->origin);
        is_array($parsedRelyingPartyId) || throw AuthenticatorResponseVerificationException::create(
            'Invalid origin'
        );
        if (! in_array($facetId, $this->securedRelyingPartyId, true)) {
            $scheme = $parsedRelyingPartyId['scheme'] ?? '';
            $scheme === 'https' || throw AuthenticatorResponseVerificationException::create(
                'Invalid scheme. HTTPS required.'
            );
        }
        $clientDataRpId = $parsedRelyingPartyId['host'] ?? '';
        $clientDataRpId !== '' || throw AuthenticatorResponseVerificationException::create('Invalid origin rpId.');
        $rpIdLength = strlen($facetId);

        substr(
            '.' . $clientDataRpId,
            -($rpIdLength + 1)
        ) === '.' . $facetId || throw AuthenticatorResponseVerificationException::create('rpId mismatch.');
    }

    private function getFacetId(
        string $rpId,
        AuthenticationExtensions $AuthenticationExtensions,
        null|AuthenticationExtensions $authenticationExtensionsClientOutputs
    ): string {
        if ($authenticationExtensionsClientOutputs === null || ! $AuthenticationExtensions->has(
            'appid'
        ) || ! $authenticationExtensionsClientOutputs->has('appid')) {
            return $rpId;
        }
        $appId = $AuthenticationExtensions->get('appid')
            ->value;
        $wasUsed = $authenticationExtensionsClientOutputs->get('appid')
            ->value;
        if (! is_string($appId) || $wasUsed !== true) {
            return $rpId;
        }
        return $appId;
    }
}

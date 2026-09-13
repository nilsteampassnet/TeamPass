<?php

declare(strict_types=1);

namespace Webauthn\CeremonyStep;

use function trigger_deprecation;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\CredentialRecord;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;

class CheckTopOrigin implements CeremonyStep
{
    public function __construct(
        private readonly null|TopOriginValidator $topOriginValidator = null
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
        $topOrigin = $authenticatorResponse->clientDataJSON->topOrigin;
        if ($topOrigin === null) {
            return;
        }
        if (! $authenticatorResponse->clientDataJSON->crossOrigin) {
            throw AuthenticatorResponseVerificationException::create('The response is not cross-origin.');
        }
        if ($this->topOriginValidator === null) {
            throw AuthenticatorResponseVerificationException::create(
                'A cross-origin response was received but no TopOriginValidator is configured. '
                . 'Configure one via CeremonyStepManagerFactory::enableTopOriginValidator() '
                . 'to opt in to cross-origin (iframe) ceremonies.'
            );
        }
        $this->topOriginValidator->validate($topOrigin);
    }
}

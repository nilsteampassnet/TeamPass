<?php

declare(strict_types=1);

namespace Webauthn\Event;

use Throwable;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\PublicKeyCredentialCreationOptions;

readonly class AuthenticatorAttestationResponseValidationFailedEvent
{
    public function __construct(
        public AuthenticatorAttestationResponse $authenticatorAttestationResponse,
        public PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions,
        public string $host,
        public Throwable $throwable
    ) {
    }
}

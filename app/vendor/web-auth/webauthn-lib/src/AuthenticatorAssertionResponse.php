<?php

declare(strict_types=1);

namespace Webauthn;

use Webauthn\AttestationStatement\AttestationObject;

/**
 * @see https://www.w3.org/TR/webauthn/#authenticatorassertionresponse
 */
class AuthenticatorAssertionResponse extends AuthenticatorResponse
{
    public function __construct(
        CollectedClientData $clientDataJSON,
        public readonly AuthenticatorData $authenticatorData,
        public readonly string $signature,
        public readonly ?string $userHandle,
        public readonly null|AttestationObject $attestationObject = null,
    ) {
        parent::__construct($clientDataJSON);
    }

    public static function create(
        CollectedClientData $clientDataJSON,
        AuthenticatorData $authenticatorData,
        string $signature,
        ?string $userHandle = null,
        null|AttestationObject $attestationObject = null,
    ): self {
        return new self($clientDataJSON, $authenticatorData, $signature, $userHandle, $attestationObject);
    }
}

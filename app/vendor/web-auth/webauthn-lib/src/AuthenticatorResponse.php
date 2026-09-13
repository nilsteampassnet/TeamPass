<?php

declare(strict_types=1);

namespace Webauthn;

/**
 * @see https://www.w3.org/TR/webauthn/#authenticatorresponse
 */
abstract class AuthenticatorResponse
{
    public function __construct(
        public readonly CollectedClientData $clientDataJSON
    ) {
    }
}

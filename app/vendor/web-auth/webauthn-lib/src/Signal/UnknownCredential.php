<?php

declare(strict_types=1);

namespace Webauthn\Signal;

use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialRpEntity;

readonly class UnknownCredential implements Signal
{
    public function __construct(
        public PublicKeyCredentialRpEntity $rp,
        public PublicKeyCredentialDescriptor $credential,
    ) {
    }
}

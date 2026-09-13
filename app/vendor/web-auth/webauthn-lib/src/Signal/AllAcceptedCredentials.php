<?php

declare(strict_types=1);

namespace Webauthn\Signal;

use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

readonly class AllAcceptedCredentials implements Signal
{
    /**
     * @param PublicKeyCredentialDescriptor[] $allAcceptedCredentials
     */
    public function __construct(
        public PublicKeyCredentialRpEntity $rp,
        public PublicKeyCredentialUserEntity $user,
        public array $allAcceptedCredentials,
    ) {
    }
}

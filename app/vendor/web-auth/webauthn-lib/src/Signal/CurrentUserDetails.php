<?php

declare(strict_types=1);

namespace Webauthn\Signal;

use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

readonly class CurrentUserDetails implements Signal
{
    public function __construct(
        public PublicKeyCredentialRpEntity $rp,
        public PublicKeyCredentialUserEntity $user,
    ) {
    }
}

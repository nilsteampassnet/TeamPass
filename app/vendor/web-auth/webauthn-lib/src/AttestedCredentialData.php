<?php

declare(strict_types=1);

namespace Webauthn;

use Symfony\Component\Uid\Uuid;

/**
 * @see https://www.w3.org/TR/webauthn/#sec-attested-credential-data
 */
class AttestedCredentialData
{
    public function __construct(
        public Uuid $aaguid,
        public readonly string $credentialId,
        public readonly ?string $credentialPublicKey
    ) {
    }

    public static function create(Uuid $aaguid, string $credentialId, ?string $credentialPublicKey = null): self
    {
        return new self($aaguid, $credentialId, $credentialPublicKey);
    }
}

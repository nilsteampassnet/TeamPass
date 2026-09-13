<?php

declare(strict_types=1);

namespace Webauthn;

readonly class PublicKeyCredentialParameters
{
    public function __construct(
        public string $type,
        public int $alg
    ) {
    }

    public static function create(string $type, int $alg): self
    {
        return new self($type, $alg);
    }

    public static function createPk(int $alg): self
    {
        return self::create(PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY, $alg);
    }
}

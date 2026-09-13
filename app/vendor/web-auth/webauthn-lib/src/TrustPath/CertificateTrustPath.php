<?php

declare(strict_types=1);

namespace Webauthn\TrustPath;

final readonly class CertificateTrustPath implements TrustPath
{
    /**
     * @param string[] $certificates
     */
    public function __construct(
        public array $certificates
    ) {
    }

    /**
     * @param string[] $certificates
     */
    public static function create(array $certificates): self
    {
        return new self($certificates);
    }
}

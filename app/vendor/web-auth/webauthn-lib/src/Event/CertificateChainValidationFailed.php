<?php

declare(strict_types=1);

namespace Webauthn\Event;

final readonly class CertificateChainValidationFailed implements WebauthnEvent
{
    /**
     * @param string[] $untrustedCertificates
     */
    public function __construct(
        public array $untrustedCertificates,
        public string $trustedCertificate
    ) {
    }

    /**
     * @param string[] $untrustedCertificates
     */
    public static function create(array $untrustedCertificates, string $trustedCertificate): self
    {
        return new self($untrustedCertificates, $trustedCertificate);
    }
}

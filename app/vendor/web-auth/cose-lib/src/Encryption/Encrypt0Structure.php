<?php

declare(strict_types=1);

namespace Cose\Encryption;

use CBOR\ByteStringObject;
use CBOR\IndefiniteLengthByteStringObject;
use Cose\Structure\CoseStructure;

/**
 * The Enc_structure of a COSE_Encrypt0 (RFC 9052 section 5.3).
 *
 * Enc_structure = [ "Encrypt0", protected : empty_or_serialized_map, external_aad : bstr ]
 *
 * This is the additional authenticated data of the content encryption. Unlike the signature and MAC structures it
 * carries no payload: the content itself is what the AEAD encrypts, and this structure is what it authenticates
 * alongside it.
 *
 *
 * The fields a decoded message supplies are typed to accept the indefinite-length byte strings the cbor-php
 * accessors can hand back, and are kept exactly as they were given: a cryptographic structure has to embed the
 * protected bucket byte for byte, or the signature the sender computed over it no longer verifies.
 * @see https://www.rfc-editor.org/rfc/rfc9052#section-5.3
 * @see \Cose\Tests\Structure\CoseStructureTest
 */
final class Encrypt0Structure extends CoseStructure
{
    private readonly ByteStringObject $externalAad;

    public function __construct(
        private readonly ByteStringObject|IndefiniteLengthByteStringObject $protectedHeader,
        ?ByteStringObject $externalAad = null
    ) {
        $this->externalAad = $externalAad ?? self::emptyExternalAad();
    }

    public static function create(
        ByteStringObject|IndefiniteLengthByteStringObject $protectedHeader,
        ?ByteStringObject $externalAad = null
    ): self {
        return new self($protectedHeader, $externalAad);
    }

    public function getProtectedHeader(): ByteStringObject|IndefiniteLengthByteStringObject
    {
        return $this->protectedHeader;
    }

    public function getExternalAad(): ByteStringObject
    {
        return $this->externalAad;
    }

    protected function context(): string
    {
        return 'Encrypt0';
    }

    protected function items(): array
    {
        return [$this->protectedHeader, $this->externalAad];
    }
}

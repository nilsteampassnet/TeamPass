<?php

declare(strict_types=1);

namespace Cose\Signature;

use CBOR\ByteStringObject;
use CBOR\IndefiniteLengthByteStringObject;
use Cose\Structure\CoseStructure;

/**
 * The Sig_structure of a COSE_Sign1 (RFC 9052 section 4.4).
 *
 * Sig_structure = [ "Signature1", body_protected : empty_or_serialized_map, external_aad : bstr, payload : bstr ]
 *
 * The payload is a parameter rather than something read back from the message so that a detached payload -- the nil
 * form of RFC 9052 section 4.2 -- is supplied by the application, which is what the RFC requires of it.
 *
 *
 * The fields a decoded message supplies are typed to accept the indefinite-length byte strings the cbor-php
 * accessors can hand back, and are kept exactly as they were given: a cryptographic structure has to embed the
 * protected bucket byte for byte, or the signature the sender computed over it no longer verifies.
 * @see https://www.rfc-editor.org/rfc/rfc9052#section-4.4
 * @see \Cose\Tests\Signature\CoseSign1CreateAndVerifyTest
 */
final class Signature1 extends CoseStructure
{
    private readonly ByteStringObject $externalAad;

    public function __construct(
        private readonly ByteStringObject|IndefiniteLengthByteStringObject $protectedHeader,
        private readonly ByteStringObject|IndefiniteLengthByteStringObject $payload,
        ?ByteStringObject $externalAad = null
    ) {
        $this->externalAad = $externalAad ?? self::emptyExternalAad();
    }

    public static function create(
        ByteStringObject|IndefiniteLengthByteStringObject $protectedHeader,
        ByteStringObject|IndefiniteLengthByteStringObject $payload,
        ?ByteStringObject $externalAad = null
    ): self {
        return new self($protectedHeader, $payload, $externalAad);
    }

    public function getProtectedHeader(): ByteStringObject|IndefiniteLengthByteStringObject
    {
        return $this->protectedHeader;
    }

    public function getPayload(): ByteStringObject|IndefiniteLengthByteStringObject
    {
        return $this->payload;
    }

    public function getExternalAad(): ByteStringObject
    {
        return $this->externalAad;
    }

    protected function context(): string
    {
        return 'Signature1';
    }

    protected function items(): array
    {
        return [$this->protectedHeader, $this->externalAad, $this->payload];
    }
}

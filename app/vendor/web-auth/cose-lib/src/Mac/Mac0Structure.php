<?php

declare(strict_types=1);

namespace Cose\Mac;

use CBOR\ByteStringObject;
use CBOR\IndefiniteLengthByteStringObject;
use Cose\Structure\CoseStructure;

/**
 * The MAC_structure of a COSE_Mac0 (RFC 9052 section 6.3).
 *
 * MAC_structure = [ "MAC0", protected : empty_or_serialized_map, external_aad : bstr, payload : bstr ]
 *
 * This is what the MAC algorithm authenticates. MACing the payload on its own instead binds the tag neither to the
 * protected header -- the algorithm and the key identifier the sender chose -- nor to the message type, so a tag
 * computed for a COSE_Mac0 would be accepted for a COSE_Mac and the reverse.
 *
 *
 * The fields a decoded message supplies are typed to accept the indefinite-length byte strings the cbor-php
 * accessors can hand back, and are kept exactly as they were given: a cryptographic structure has to embed the
 * protected bucket byte for byte, or the signature the sender computed over it no longer verifies.
 * @see https://www.rfc-editor.org/rfc/rfc9052#section-6.3
 * @see \Cose\Tests\Structure\CoseStructureTest
 */
final class Mac0Structure extends CoseStructure
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
        return 'MAC0';
    }

    protected function items(): array
    {
        return [$this->protectedHeader, $this->externalAad, $this->payload];
    }
}

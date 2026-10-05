<?php

declare(strict_types=1);

namespace Cose\Mac;

use CBOR\ByteStringObject;
use CBOR\IndefiniteLengthByteStringObject;
use Cose\Structure\CoseStructure;

/**
 * The MAC_structure of a COSE_Mac (RFC 9052 section 6.3).
 *
 * MAC_structure = [ "MAC", protected : empty_or_serialized_map, external_aad : bstr, payload : bstr ]
 *
 * This is what the MAC algorithm authenticates, over the body protected header of the message. The context string
 * is the only difference with {@see Mac0Structure}, and it is what keeps a tag computed for a multi-recipient
 * COSE_Mac from being accepted on a COSE_Mac0 built over the same header and payload.
 *
 *
 * The fields a decoded message supplies are typed to accept the indefinite-length byte strings the cbor-php
 * accessors can hand back, and are kept exactly as they were given: a cryptographic structure has to embed the
 * protected bucket byte for byte, or the signature the sender computed over it no longer verifies.
 * @see https://www.rfc-editor.org/rfc/rfc9052#section-6.3
 * @see \Cose\Tests\Structure\CoseStructureTest
 */
final class MacStructure extends CoseStructure
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
        return 'MAC';
    }

    protected function items(): array
    {
        return [$this->protectedHeader, $this->externalAad, $this->payload];
    }
}

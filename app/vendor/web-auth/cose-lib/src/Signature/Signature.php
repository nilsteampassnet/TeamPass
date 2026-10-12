<?php

declare(strict_types=1);

namespace Cose\Signature;

use CBOR\ByteStringObject;
use CBOR\IndefiniteLengthByteStringObject;
use Cose\Structure\CoseStructure;

/**
 * The Sig_structure of one signer of a COSE_Sign (RFC 9052 section 4.4).
 *
 * Sig_structure = [ "Signature", body_protected : empty_or_serialized_map, sign_protected : empty_or_serialized_map,
 * external_aad : bstr, payload : bstr ]
 *
 * The sign_protected field is what tells this structure apart from {@see Signature1}: a signature of a COSE_Sign
 * commits to the protected bucket of the message *and* to the one of its own COSE_Signature entry. Without it, a
 * per-signer signature would be byte-identical to a COSE_Sign1 signature over the same header and payload.
 *
 *
 * The fields a decoded message supplies are typed to accept the indefinite-length byte strings the cbor-php
 * accessors can hand back, and are kept exactly as they were given: a cryptographic structure has to embed the
 * protected bucket byte for byte, or the signature the sender computed over it no longer verifies.
 * @see https://www.rfc-editor.org/rfc/rfc9052#section-4.4
 * @see \Cose\Tests\Structure\CoseStructureTest
 */
final class Signature extends CoseStructure
{
    private readonly ByteStringObject $externalAad;

    public function __construct(
        private readonly ByteStringObject|IndefiniteLengthByteStringObject $bodyProtectedHeader,
        private readonly ByteStringObject|IndefiniteLengthByteStringObject $signProtectedHeader,
        private readonly ByteStringObject|IndefiniteLengthByteStringObject $payload,
        ?ByteStringObject $externalAad = null
    ) {
        $this->externalAad = $externalAad ?? self::emptyExternalAad();
    }

    public static function create(
        ByteStringObject|IndefiniteLengthByteStringObject $bodyProtectedHeader,
        ByteStringObject|IndefiniteLengthByteStringObject $signProtectedHeader,
        ByteStringObject|IndefiniteLengthByteStringObject $payload,
        ?ByteStringObject $externalAad = null
    ): self {
        return new self($bodyProtectedHeader, $signProtectedHeader, $payload, $externalAad);
    }

    public function getBodyProtectedHeader(): ByteStringObject|IndefiniteLengthByteStringObject
    {
        return $this->bodyProtectedHeader;
    }

    public function getSignProtectedHeader(): ByteStringObject|IndefiniteLengthByteStringObject
    {
        return $this->signProtectedHeader;
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
        return 'Signature';
    }

    protected function items(): array
    {
        return [$this->bodyProtectedHeader, $this->signProtectedHeader, $this->externalAad, $this->payload];
    }
}

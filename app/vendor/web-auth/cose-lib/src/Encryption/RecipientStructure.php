<?php

declare(strict_types=1);

namespace Cose\Encryption;

use CBOR\ByteStringObject;
use CBOR\IndefiniteLengthByteStringObject;
use Cose\Structure\CoseStructure;

/**
 * The Enc_structure of a recipient (RFC 9052 section 5.3).
 *
 * Enc_structure = [ context : "Enc_Recipient" / "Mac_Recipient" / "Rec_Recipient", protected :
 * empty_or_serialized_map, external_aad : bstr ]
 *
 * The three remaining contexts of Enc_structure name where the key being wrapped comes from: the recipient of a
 * COSE_Encrypt, the recipient of a COSE_Mac, or a recipient nested inside another recipient. Binding the context is
 * what stops a wrapped key from being replayed at a different level of the same structure, so it is a named
 * constructor rather than a free string.
 *
 *
 * The fields a decoded message supplies are typed to accept the indefinite-length byte strings the cbor-php
 * accessors can hand back, and are kept exactly as they were given: a cryptographic structure has to embed the
 * protected bucket byte for byte, or the signature the sender computed over it no longer verifies.
 * @see https://www.rfc-editor.org/rfc/rfc9052#section-5.3
 * @see \Cose\Tests\Structure\CoseStructureTest
 */
final class RecipientStructure extends CoseStructure
{
    /**
     * The recipient of a COSE_Encrypt.
     */
    public const CONTEXT_ENC_RECIPIENT = 'Enc_Recipient';

    /**
     * The recipient of a COSE_Mac.
     */
    public const CONTEXT_MAC_RECIPIENT = 'Mac_Recipient';

    /**
     * A recipient nested inside another recipient.
     */
    public const CONTEXT_REC_RECIPIENT = 'Rec_Recipient';

    private readonly ByteStringObject $externalAad;

    private function __construct(
        private readonly string $context,
        private readonly ByteStringObject|IndefiniteLengthByteStringObject $protectedHeader,
        ?ByteStringObject $externalAad = null
    ) {
        $this->externalAad = $externalAad ?? self::emptyExternalAad();
    }

    public static function forEncryptRecipient(
        ByteStringObject|IndefiniteLengthByteStringObject $protectedHeader,
        ?ByteStringObject $externalAad = null
    ): self {
        return new self(self::CONTEXT_ENC_RECIPIENT, $protectedHeader, $externalAad);
    }

    public static function forMacRecipient(
        ByteStringObject|IndefiniteLengthByteStringObject $protectedHeader,
        ?ByteStringObject $externalAad = null
    ): self {
        return new self(self::CONTEXT_MAC_RECIPIENT, $protectedHeader, $externalAad);
    }

    public static function forNestedRecipient(
        ByteStringObject|IndefiniteLengthByteStringObject $protectedHeader,
        ?ByteStringObject $externalAad = null
    ): self {
        return new self(self::CONTEXT_REC_RECIPIENT, $protectedHeader, $externalAad);
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
        return $this->context;
    }

    protected function items(): array
    {
        return [$this->protectedHeader, $this->externalAad];
    }
}

<?php

declare(strict_types=1);

namespace Cose\Signature;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\IndefiniteLengthByteStringObject;
use CBOR\IndefiniteLengthListObject;
use CBOR\IndefiniteLengthMapObject;
use CBOR\ListObject;
use CBOR\MapObject;
use Cose\Structure\CoseHeaders;
use Cose\Structure\HeaderMapHelper;
use InvalidArgumentException;

/**
 * One entry of the "signatures" list of a COSE_Sign (RFC 9052 section 4.1).
 *
 * COSE_Signature = [ Headers, signature : bstr ]
 *
 * The raw list a message carries says nothing about what is inside it, and neither cbor-php nor the deprecated
 * Cose\Signature\CoseSignTag looks: both accept an empty list and entries of any shape. This view is the checked
 * form, and it carries the same header reader as the message, because a signer of a COSE_Sign has a protected
 * bucket of its own that its signature commits to.
 *
 * ```php
 * foreach ($message->getSignatures() as $entry) {
 *     $signer = CoseSignature::create($entry);
 *     $toBeSigned = Signature::create(
 *         $message->getProtectedHeader(),
 *         $signer->getProtectedHeader(),
 *         $message->getPayload(),
 *     );
 * }
 * ```
 *
 * @see https://www.rfc-editor.org/rfc/rfc9052#section-4.1
 * @see \Cose\Tests\Structure\CoseSignatureTest
 */
final class CoseSignature
{
    private readonly ByteStringObject|IndefiniteLengthByteStringObject $protectedHeader;

    private readonly MapObject|IndefiniteLengthMapObject $unprotectedHeader;

    private readonly ByteStringObject|IndefiniteLengthByteStringObject $signature;

    public function __construct(ListObject|IndefiniteLengthListObject $signature)
    {
        HeaderMapHelper::assertSignatureList(ListObject::create([$signature]));

        /** @var ByteStringObject|IndefiniteLengthByteStringObject $protectedHeader */
        $protectedHeader = $signature->get(0);
        /** @var IndefiniteLengthMapObject|MapObject $unprotectedHeader */
        $unprotectedHeader = $signature->get(1);
        /** @var ByteStringObject|IndefiniteLengthByteStringObject $signatureValue */
        $signatureValue = $signature->get(2);

        $this->protectedHeader = $protectedHeader;
        $this->unprotectedHeader = $unprotectedHeader;
        $this->signature = $signatureValue;
    }

    public static function create(ListObject|IndefiniteLengthListObject $signature): self
    {
        return new self($signature);
    }

    /**
     * Every entry of a "signatures" list, checked and wrapped.
     *
     * @return list<self>
     */
    public static function all(ListObject|IndefiniteLengthListObject $signatures): array
    {
        HeaderMapHelper::assertSignatureList($signatures);

        $result = [];
        foreach ($signatures as $signature) {
            if (! $signature instanceof ListObject && ! $signature instanceof IndefiniteLengthListObject) {
                throw new InvalidArgumentException('Not a valid COSE_Signature object. It shall be a List object.');
            }
            $result[] = new self($signature);
        }

        return $result;
    }

    /**
     * The protected bucket of this signer, as it is carried: what its Sig_structure covers verbatim.
     */
    public function getProtectedHeader(): ByteStringObject|IndefiniteLengthByteStringObject
    {
        return $this->protectedHeader;
    }

    public function getUnprotectedHeader(): MapObject|IndefiniteLengthMapObject
    {
        return $this->unprotectedHeader;
    }

    /**
     * The headers of this signer, read the way RFC 9052 defines them.
     */
    public function headers(): CoseHeaders
    {
        return CoseHeaders::of($this->protectedHeader, $this->unprotectedHeader);
    }

    public function getProtectedHeaderParameter(int|string $label): ?CBORObject
    {
        return $this->headers()
            ->getProtectedHeaderParameter($label);
    }

    public function getUnprotectedHeaderParameter(int|string $label): ?CBORObject
    {
        return $this->headers()
            ->getUnprotectedHeaderParameter($label);
    }

    public function getSignature(): ByteStringObject|IndefiniteLengthByteStringObject
    {
        return $this->signature;
    }

    /**
     * The list this view was built from, rebuilt so that nothing the caller does to it reaches the message.
     */
    public function toListObject(): ListObject
    {
        return ListObject::create([$this->protectedHeader, $this->unprotectedHeader, $this->signature]);
    }
}

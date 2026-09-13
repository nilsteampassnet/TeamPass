<?php

declare(strict_types=1);

namespace Cose\Structure;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\IndefiniteLengthByteStringObject;
use CBOR\IndefiniteLengthListObject;
use CBOR\IndefiniteLengthMapObject;
use CBOR\ListObject;
use CBOR\MapObject;
use CBOR\OtherObject\NullObject;
use InvalidArgumentException;
use LogicException;

/**
 * One entry of the "recipients" list of a COSE_Encrypt or of a COSE_Mac (RFC 9052 sections 5.1 and 6.1).
 *
 * COSE_recipient = [ Headers, ciphertext : bstr / nil, ? recipients : [+COSE_recipient] ]
 *
 * The raw list a message carries says nothing about what is inside it, and neither cbor-php nor the deprecated
 * Cose\...Tag classes look: both accept an empty list and entries of any shape. This view is the checked form.
 *
 * A recipient may itself carry recipients, which is how RFC 9052 expresses key layering; getRecipients() walks that
 * tree one level at a time and each level is a view of the same kind.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9052#section-5.1
 * @see \Cose\Tests\Structure\CoseRecipientTest
 */
final class CoseRecipient
{
    private readonly ByteStringObject|IndefiniteLengthByteStringObject $protectedHeader;

    private readonly MapObject|IndefiniteLengthMapObject $unprotectedHeader;

    private readonly ByteStringObject|IndefiniteLengthByteStringObject|null $ciphertext;

    private readonly ListObject|IndefiniteLengthListObject|null $recipients;

    public function __construct(ListObject|IndefiniteLengthListObject $recipient)
    {
        HeaderMapHelper::assertRecipientList(ListObject::create([$recipient]));

        /** @var ByteStringObject|IndefiniteLengthByteStringObject $protectedHeader */
        $protectedHeader = $recipient->get(0);
        /** @var IndefiniteLengthMapObject|MapObject $unprotectedHeader */
        $unprotectedHeader = $recipient->get(1);
        // assertRecipientList() has just ruled out everything but a byte string and nil at this position.
        /** @var ByteStringObject|IndefiniteLengthByteStringObject|null $ciphertext */
        $ciphertext = HeaderMapHelper::isNil($recipient->get(2)) ? null : $recipient->get(2);

        $this->protectedHeader = $protectedHeader;
        $this->unprotectedHeader = $unprotectedHeader;
        $this->ciphertext = $ciphertext;

        $nested = $recipient->count() === 4 ? $recipient->get(3) : null;
        $this->recipients = $nested instanceof ListObject || $nested instanceof IndefiniteLengthListObject
            ? $nested
            : null;
    }

    public static function create(ListObject|IndefiniteLengthListObject $recipient): self
    {
        return new self($recipient);
    }

    /**
     * Every entry of a "recipients" list, checked and wrapped.
     *
     * @return list<self>
     */
    public static function all(ListObject|IndefiniteLengthListObject $recipients): array
    {
        HeaderMapHelper::assertRecipientList($recipients);

        $result = [];
        foreach ($recipients as $recipient) {
            if (! $recipient instanceof ListObject && ! $recipient instanceof IndefiniteLengthListObject) {
                throw new InvalidArgumentException('Not a valid COSE_recipient object. It shall be a List object.');
            }
            $result[] = new self($recipient);
        }

        return $result;
    }

    /**
     * The protected bucket of this recipient, as it is carried.
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
     * The headers of this recipient, read the way RFC 9052 defines them.
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

    /**
     * Whether the ciphertext of this recipient travels outside the message.
     */
    public function hasDetachedCiphertext(): bool
    {
        return $this->ciphertext === null;
    }

    /**
     * @throws LogicException when the ciphertext is detached; hasDetachedCiphertext() tells the two cases apart
     */
    public function getCiphertext(): ByteStringObject|IndefiniteLengthByteStringObject
    {
        return $this->ciphertext ?? throw new LogicException(
            'The ciphertext of this COSE_recipient is detached (RFC 9052 section 5.1); the application supplies it separately.'
        );
    }

    public function hasRecipients(): bool
    {
        return $this->recipients !== null;
    }

    /**
     * The recipients nested under this one, empty when it carries none.
     *
     * @return list<self>
     */
    public function getRecipients(): array
    {
        return $this->recipients === null ? [] : self::all($this->recipients);
    }

    /**
     * The list this view was built from, rebuilt so that nothing the caller does to it reaches the message.
     */
    public function toListObject(): ListObject
    {
        $items = [$this->protectedHeader, $this->unprotectedHeader, $this->ciphertext ?? NullObject::create()];
        if ($this->recipients !== null) {
            $items[] = $this->recipients;
        }

        return ListObject::create($items);
    }
}

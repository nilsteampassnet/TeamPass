<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\ASN1\Type\Tagged;

use function mb_strlen;
use SpomkyLabs\Pki\ASN1\Component\Identifier;
use SpomkyLabs\Pki\ASN1\Component\Length;
use SpomkyLabs\Pki\ASN1\Element;
use SpomkyLabs\Pki\ASN1\Exception\DecodeException;
use SpomkyLabs\Pki\ASN1\Feature\ElementBase;
use SpomkyLabs\Pki\ASN1\Type\TaggedType;
use SpomkyLabs\Pki\ASN1\Type\UnspecifiedType;

/**
 * Intermediate class to store tagged DER data.
 *
 * `implicit($tag)` or `explicit()` method is used to decode the actual element, which is only known by the abstract
 * syntax of data structure.
 *
 * May be encoded back to complete DER encoding.
 */
class DERTaggedType extends TaggedType implements ExplicitTagging, ImplicitTagging
{
    /**
     * @param Identifier $_identifier Pre-parsed identifier
     * @param string $_data DER data
     * @param int $_offset Offset to next byte after identifier
     * @param int $_valueOffset Offset to content
     * @param int $_valueLength Content length
     */
    final private function __construct(
        private readonly Identifier $_identifier,
        private readonly string $_data,
        private readonly int $_offset,
        private readonly int $_valueOffset,
        private readonly int $_valueLength,
        bool $indefinite_length
    ) {
        parent::__construct($_identifier->intTag(), $indefinite_length);
    }

    public static function create(
        Identifier $_identifier,
        string $_data,
        int $_offset,
        int $_valueOffset,
        int $_valueLength,
        bool $indefinite_length
    ): static {
        return new static($_identifier, $_data, $_offset, $_valueOffset, $_valueLength, $indefinite_length);
    }

    public function typeClass(): int
    {
        return $this->_identifier->typeClass();
    }

    public function isConstructed(): bool
    {
        return $this->_identifier->isConstructed();
    }

    public function implicit(int $tag, int $class = Identifier::CLASS_UNIVERSAL): UnspecifiedType
    {
        $identifier = $this->_identifier->withClass($class)
            ->withTag($tag);
        $cls = self::determineImplClass($identifier);
        $idx = $this->_offset;
        /** @var ElementBase $element */
        $element = $cls::decodeFromDER($identifier, $this->_data, $idx);
        return $element->asUnspecified();
    }

    public function explicit(): UnspecifiedType
    {
        $idx = $this->_valueOffset;
        return Element::fromDER($this->_data, $idx)->asUnspecified();
    }

    protected static function decodeFromDER(Identifier $identifier, string $data, int &$offset): ElementBase
    {
        $idx = $offset;
        $length = Length::expectFromDER($data, $idx);
        // offset to inner value
        $value_offset = $idx;
        if ($length->isIndefinite()) {
            if ($identifier->isPrimitive()) {
                throw new DecodeException('Primitive type with indefinite length is not supported.');
            }
            // An indefinite length states no size up front: the content runs until the matching end-of-contents.
            // Walking it is what moves the offset past the element, so that the caller resumes on the next
            // sibling instead of inside this one.
            $idx = self::skipIndefiniteContent($data, $idx);
            // EOC consists of two octets.
            $value_length = $idx - $value_offset - 2;
        } else {
            $value_length = $length->intLength();
            $idx += $value_length;
        }
        // late static binding since ApplicationType and PrivateType extend this class
        $type = static::create($identifier, $data, $offset, $value_offset, $value_length, $length->isIndefinite());
        $offset = $idx;
        return $type;
    }

    /**
     * Walk an indefinite length content and return the offset just past its end-of-contents octets.
     *
     * @param string $data DER data
     * @param int $offset Offset to the first content octet
     */
    private static function skipIndefiniteContent(string $data, int $offset): int
    {
        $end = mb_strlen($data, '8bit');
        $idx = $offset;
        while (true) {
            if ($idx >= $end) {
                throw new DecodeException('Unexpected end of data while decoding indefinite length element.');
            }
            $element = Element::fromDER($data, $idx);
            $idx ??= $end;
            if ($element->isType(Element::TYPE_EOC)) {
                return $idx;
            }
        }
    }

    protected function encodedAsDER(): string
    {
        return mb_substr($this->_data, $this->_valueOffset, $this->_valueLength, '8bit');
    }
}

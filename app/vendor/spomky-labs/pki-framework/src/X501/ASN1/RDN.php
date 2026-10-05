<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X501\ASN1;

use ArrayIterator;
use function count;
use Countable;
use IteratorAggregate;
use SpomkyLabs\Pki\ASN1\Type\Constructed\Set;
use SpomkyLabs\Pki\ASN1\Type\UnspecifiedType;
use SpomkyLabs\Pki\X501\ASN1\AttributeValue\AttributeValue;
use Stringable;
use UnexpectedValueException;

/**
 * Implements *RelativeDistinguishedName* ASN.1 type.
 *
 * @see https://www.itu.int/ITU-T/formal-language/itu-t/x/x501/2012/InformationFramework.html#InformationFramework.RelativeDistinguishedName
 */
final class RDN implements Countable, IteratorAggregate, Stringable
{
    /**
     * Attributes.
     *
     * @var AttributeTypeAndValue[]
     */
    private readonly array $_attribs;

    /**
     * @param AttributeTypeAndValue ...$attribs One or more attributes
     */
    private function __construct(AttributeTypeAndValue ...$attribs)
    {
        if (count($attribs) === 0) {
            throw new UnexpectedValueException('RDN must have at least one AttributeTypeAndValue.');
        }
        $this->_attribs = $attribs;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public static function create(AttributeTypeAndValue ...$attribs): self
    {
        return new self(...$attribs);
    }

    /**
     * Convenience method to initialize RDN from AttributeValue objects.
     *
     * @param AttributeValue ...$values One or more attributes
     */
    public static function fromAttributeValues(AttributeValue ...$values): self
    {
        $attribs = array_map(
            static fn (AttributeValue $value) => AttributeTypeAndValue::create(AttributeType::create(
                $value->oid()
            ), $value),
            $values
        );
        return self::create(...$attribs);
    }

    /**
     * Initialize from ASN.1.
     */
    public static function fromASN1(Set $set): self
    {
        $attribs = array_map(
            static fn (UnspecifiedType $el) => AttributeTypeAndValue::fromASN1($el->asSequence()),
            $set->elements()
        );
        return self::create(...$attribs);
    }

    /**
     * Generate ASN.1 structure.
     */
    public function toASN1(): Set
    {
        $elements = array_map(static fn (AttributeTypeAndValue $tv) => $tv->toASN1(), $this->_attribs);
        return Set::create(...$elements)->sortedSetOf();
    }

    /**
     * Get name-component string conforming to RFC 2253.
     *
     * @see https://tools.ietf.org/html/rfc2253#section-2.2
     */
    public function toString(): string
    {
        $parts = array_map(static fn (AttributeTypeAndValue $tv) => $tv->toString(), $this->_attribs);
        return implode('+', $parts);
    }

    /**
     * Check whether RDN is semantically equal to other.
     *
     * @param RDN $other Object to compare to
     */
    public function equals(self $other): bool
    {
        // if attribute count doesn't match
        if (count($this) !== count($other)) {
            return false;
        }
        // RFC 5280 sect. 7.1: an RDN is a SET, so two of them are equal when their attributes match as a multiset,
        // whatever the order. Sorting both sides in DER order and comparing position by position gets this wrong,
        // because the attribute values compare case insensitively while the DER sort does not: RDN{cn=a,cn=B} and
        // RDN{cn=A,cn=b} sort the other way round and were reported as different.
        //
        // Pairing the two sides off one at a time answers that correctly but costs a comparison for every pair, so
        // an RDN whose attributes an attacker put in the reverse order costs work quadratic in their number, each
        // step preparing a string afresh. Where the matching rules can name their values, the attributes are
        // grouped by that name instead and the answer takes one pass.
        $counts = self::countByKey($other->_attribs);
        if ($counts !== null) {
            foreach ($this->_attribs as $tv) {
                $key = $tv->comparisonKey();
                if ($key === null || ($counts[$key] ?? 0) === 0) {
                    return false;
                }
                --$counts[$key];
            }

            return true;
        }
        $unmatched = $other->_attribs;
        foreach ($this->_attribs as $tv1) {
            $matched = false;
            foreach ($unmatched as $idx => $tv2) {
                if ($tv1->equals($tv2)) {
                    unset($unmatched[$idx]);
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return false;
            }
        }
        return true;
    }

    /**
     * Count the attributes by the key their own matching rule gives them, or null if any of them has none.
     *
     * @param AttributeTypeAndValue[] $attributes
     *
     * @return null|array<string, int>
     */
    private static function countByKey(array $attributes): ?array
    {
        $counts = [];
        foreach ($attributes as $attribute) {
            $key = $attribute->comparisonKey();
            if ($key === null) {
                return null;
            }
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Get all AttributeTypeAndValue objects.
     *
     * @return AttributeTypeAndValue[]
     */
    public function all(): array
    {
        return $this->_attribs;
    }

    /**
     * Get all AttributeTypeAndValue objects of the given attribute type.
     *
     * @param string $name Attribute OID or name
     *
     * @return AttributeTypeAndValue[]
     */
    public function allOf(string $name): array
    {
        $oid = AttributeType::attrNameToOID($name);
        $attribs = array_filter($this->_attribs, static fn (AttributeTypeAndValue $tv) => $tv->oid() === $oid);
        return array_values($attribs);
    }

    /**
     * @see \Countable::count()
     */
    public function count(): int
    {
        return count($this->_attribs);
    }

    /**
     * @see \IteratorAggregate::getIterator()
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->_attribs);
    }
}

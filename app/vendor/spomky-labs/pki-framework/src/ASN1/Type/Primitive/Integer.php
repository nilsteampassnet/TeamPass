<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\ASN1\Type\Primitive;

use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;
use function gettype;
use InvalidArgumentException;
use function is_int;
use function is_scalar;
use function is_string;
use function mb_strlen;
use function ord;
use SpomkyLabs\Pki\ASN1\Component\Identifier;
use SpomkyLabs\Pki\ASN1\Component\Length;
use SpomkyLabs\Pki\ASN1\Element;
use SpomkyLabs\Pki\ASN1\Exception\DecodeException;
use SpomkyLabs\Pki\ASN1\Feature\ElementBase;
use SpomkyLabs\Pki\ASN1\Type\PrimitiveType;
use SpomkyLabs\Pki\ASN1\Type\UniversalClass;
use SpomkyLabs\Pki\ASN1\Util\BigInt;
use function sprintf;

/**
 * Implements *INTEGER* type.
 */
class Integer extends Element
{
    use UniversalClass;
    use PrimitiveType;

    /**
     * The number.
     */
    private readonly BigInt $_number;

    /**
     * @param BigInteger|int|string $number Base 10 integer
     */
    final protected function __construct(BigInteger|int|string $number, int $typeTag)
    {
        parent::__construct($typeTag);
        if (! self::validateNumber($number)) {
            $var = is_scalar($number) ? (string) $number : gettype($number);
            throw new InvalidArgumentException("'{$var}' is not a valid number.");
        }
        $this->_number = BigInt::create($number);
    }

    public static function create(BigInteger|int|string $number): static
    {
        return new static($number, self::TYPE_INTEGER);
    }

    /**
     * Get the number as a base 10.
     *
     * @return string Integer as a string
     */
    public function number(): string
    {
        return $this->_number->base10();
    }

    public function getValue(): BigInteger
    {
        return $this->_number->getValue();
    }

    /**
     * Get the number as an integer type.
     */
    public function intNumber(): int
    {
        try {
            return $this->_number->toInt();
        } catch (MathException $e) {
            // an arbitrary length integer is decoded happily, but a caller asking for an int is reading a field
            // that has to fit in one. Letting brick/math's overflow exception escape would break the decoding
            // contract of Element::fromDER() for every structure that reads a field this way.
            throw new DecodeException(sprintf('Integer %s is too large.', $this->_number->base10()), 0, $e);
        }
    }

    protected function encodedAsDER(): string
    {
        return $this->_number->signedOctets();
    }

    protected static function decodeFromDER(Identifier $identifier, string $data, int &$offset): ElementBase
    {
        $idx = $offset;
        $length = Length::expectFromDER($data, $idx)->expectIntLength();
        if ($length === 0) {
            throw new DecodeException('Integer must have at least one content octet.');
        }
        $bytes = mb_substr($data, $idx, $length, '8bit');
        self::checkMinimalEncoding($bytes);
        $idx += $length;
        $num = BigInt::fromSignedOctets($bytes)->getValue();
        $offset = $idx;
        // late static binding since enumerated extends integer type
        return static::create($num);
    }

    /**
     * Check that the content octets are the minimal two's complement encoding of the value (X.690 sect. 8.3.2).
     *
     * Without the check `02 02 00 01` and `02 01 01` both decode to 1, so a signed structure has more than one
     * valid encoding.
     */
    private static function checkMinimalEncoding(string $bytes): void
    {
        if (mb_strlen($bytes, '8bit') < 2) {
            return;
        }
        $first = ord($bytes[0]);
        $second = ord($bytes[1]);
        if (($first === 0x00 && ($second & 0x80) === 0) || ($first === 0xFF && ($second & 0x80) !== 0)) {
            throw new DecodeException('Integer value must be encoded in the minimum number of octets.');
        }
    }

    /**
     * Test that number is valid for this context.
     */
    private static function validateNumber(mixed $num): bool
    {
        if (is_int($num)) {
            return true;
        }
        if (is_string($num) && preg_match('/\A-?\d+\z/', $num) === 1) {
            return true;
        }
        if ($num instanceof BigInteger) {
            return true;
        }
        return false;
    }
}

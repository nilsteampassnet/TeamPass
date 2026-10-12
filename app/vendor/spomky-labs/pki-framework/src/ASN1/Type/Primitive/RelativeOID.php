<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\ASN1\Type\Primitive;

use Brick\Math\BigInteger;
use function chr;
use function is_int;
use function ord;
use RuntimeException;
use SpomkyLabs\Pki\ASN1\Component\Identifier;
use SpomkyLabs\Pki\ASN1\Component\Length;
use SpomkyLabs\Pki\ASN1\Element;
use SpomkyLabs\Pki\ASN1\Exception\DecodeException;
use SpomkyLabs\Pki\ASN1\Feature\ElementBase;
use SpomkyLabs\Pki\ASN1\Type\PrimitiveType;
use SpomkyLabs\Pki\ASN1\Type\UniversalClass;
use function sprintf;
use Throwable;
use UnexpectedValueException;

/**
 * Implements *RELATIVE-OID* type.
 */
final class RelativeOID extends Element
{
    /**
     * Largest number of octets a base 128 field may span.
     *
     * Each octet carries seven bits, so nine of them already exceed the range of a PHP integer. A tag number or a
     * sub-identifier beyond that can never name a type or an arc this library implements, and accepting more of them
     * is not free: the accumulator is a BigInteger that is rebuilt on every step, so an unbounded run of continuation
     * octets costs work quadratic in its length. A few kilobytes of them are minutes of CPU, spent before any
     * signature is checked.
     *
     * @var int
     */
    private const MAX_BASE128_OCTETS = 9;

    use UniversalClass;
    use PrimitiveType;

    /**
     * Object identifier split to sub ID's.
     *
     * @var BigInteger[]
     */
    private readonly array $subids;

    /**
     * @param string $oid OID in dotted format
     */
    private function __construct(
        private readonly string $oid
    ) {
        parent::__construct(self::TYPE_RELATIVE_OID);
        $this->subids = self::explodeDottedOID($oid);
    }

    public static function create(string $oid): self
    {
        return new self($oid);
    }

    /**
     * Get OID in dotted format.
     */
    public function oid(): string
    {
        return $this->oid;
    }

    protected function encodedAsDER(): string
    {
        return self::encodeSubIDs(...$this->subids);
    }

    protected static function decodeFromDER(Identifier $identifier, string $data, int &$offset): ElementBase
    {
        $idx = $offset;
        $len = Length::expectFromDER($data, $idx)->expectIntLength();
        if ($len === 0) {
            throw new DecodeException('Relative object identifier must have at least one content octet.');
        }
        $subids = self::decodeSubIDs(mb_substr($data, $idx, $len, '8bit'));
        $offset = $idx + $len;
        return self::create(self::implodeSubIDs(...$subids));
    }

    /**
     * Explode dotted OID to an array of sub ID's.
     *
     * @param string $oid OID in dotted format
     *
     * @return BigInteger[] Array of BigInteger numbers
     */
    protected static function explodeDottedOID(string $oid): array
    {
        $subids = [];
        if ($oid !== '') {
            foreach (explode('.', $oid) as $subid) {
                try {
                    $n = BigInteger::of($subid);
                    $subids[] = $n;
                } catch (Throwable $e) {
                    throw new UnexpectedValueException(sprintf('"%s" is not a number.', $subid), 0, $e);
                }
            }
        }
        return $subids;
    }

    /**
     * Implode an array of sub IDs to dotted OID format.
     */
    protected static function implodeSubIDs(BigInteger ...$subids): string
    {
        return implode('.', array_map(static fn ($num) => $num->toBase(10), $subids));
    }

    /**
     * Encode sub ID's to DER.
     */
    protected static function encodeSubIDs(BigInteger ...$subids): string
    {
        $data = '';
        foreach ($subids as $subid) {
            // if number fits to one base 128 byte
            if ($subid->isLessThan(128)) {
                $data .= chr($subid->toInt() & 0xFF);
            } else { // encode to multiple bytes
                $bytes = [];
                do {
                    array_unshift($bytes, 0x7F & $subid->toInt());
                    $subid = $subid->shiftedRight(7);
                } while ($subid->isGreaterThan(0));
                // all bytes except last must have bit 8 set to one
                foreach (array_splice($bytes, 0, -1) as $byte) {
                    $data .= chr(0x80 | $byte);
                }
                $byte = reset($bytes);
                if (! is_int($byte)) {
                    throw new RuntimeException('Encoding failed');
                }
                $data .= chr($byte);
            }
        }
        return $data;
    }

    /**
     * Decode sub ID's from DER data.
     *
     * @return BigInteger[] Array of BigInteger numbers
     */
    protected static function decodeSubIDs(string $data): array
    {
        $subids = [];
        $idx = 0;
        $end = mb_strlen($data, '8bit');
        while ($idx < $end) {
            $num = BigInteger::of(0);
            $first = true;
            $octets = 0;
            while (true) {
                if ($idx >= $end) {
                    throw new DecodeException('Unexpected end of data.');
                }
                if (++$octets > self::MAX_BASE128_OCTETS) {
                    throw new DecodeException('Sub-identifier is too long.');
                }
                $byte = ord($data[$idx++]);
                // leading zero bits are not part of a minimal sub-identifier (X.690 sect. 8.19.2)
                if ($first && $byte === 0x80) {
                    throw new DecodeException('Leading zero octet in an object identifier sub-identifier.');
                }
                $first = false;
                $num = $num->or($byte & 0x7F);
                // bit 8 of the last octet is zero
                if (0 === ($byte & 0x80)) {
                    break;
                }
                $num = $num->shiftedLeft(7);
            }
            $subids[] = $num;
        }
        return $subids;
    }
}

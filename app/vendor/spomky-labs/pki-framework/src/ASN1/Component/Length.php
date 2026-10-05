<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\ASN1\Component;

use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;
use function count;
use DomainException;
use function func_num_args;
use LogicException;
use function mb_strlen;
use function ord;
use SpomkyLabs\Pki\ASN1\Exception\DecodeException;
use SpomkyLabs\Pki\ASN1\Feature\Encodable;
use SpomkyLabs\Pki\ASN1\Util\BigInt;
use function sprintf;

/**
 * Class to represent BER/DER length octets.
 */
final class Length implements Encodable
{
    /**
     * Length.
     */
    private readonly BigInt $_length;

    /**
     * @param BigInteger|int $length Length
     * @param bool $_indefinite Whether length is indefinite
     */
    private function __construct(
        BigInteger|int $length,
        private readonly bool $_indefinite = false
    ) {
        $this->_length = BigInt::create($length);
    }

    public static function create(BigInteger|int $length, bool $_indefinite = false): self
    {
        return new self($length, $_indefinite);
    }

    /**
     * Decode length component from DER data.
     *
     * @param string $data DER encoded data
     * @param null|int $offset Reference to the variable that contains offset
     * into the data where to start parsing.
     * Variable is updated to the offset next to the
     * parsed length component. If null, start from offset 0.
     */
    public static function fromDER(string $data, ?int &$offset = null): self
    {
        $idx = $offset ?? 0;
        $datalen = mb_strlen($data, '8bit');
        if ($idx >= $datalen) {
            throw new DecodeException('Unexpected end of data while decoding length.');
        }
        $indefinite = false;
        $byte = ord($data[$idx++]);
        // bits 7 to 1
        $length = (0x7F & $byte);
        // long form
        if ((0x80 & $byte) !== 0) {
            if ($length === 0) {
                $indefinite = true;
            } else {
                if ($idx + $length > $datalen) {
                    throw new DecodeException('Unexpected end of data while decoding long form length.');
                }
                $length = self::decodeLongFormLength($length, $data, $idx);
            }
        }
        if (func_num_args() > 1) {
            $offset = $idx;
        }
        return self::create($length, $indefinite);
    }

    /**
     * Decode length from DER.
     *
     * Throws an exception if length doesn't match with expected or if data doesn't contain enough bytes.
     *
     * Requirement of definite length is relaxed contrary to the specification (sect. 10.1).
     *
     * @param string $data DER data
     * @param int $offset Reference to the offset variable
     * @param null|int $expected Expected length, null to bypass checking
     * @see self::fromDER
     */
    public static function expectFromDER(string $data, int &$offset, ?int $expected = null): self
    {
        $idx = $offset;
        $length = self::fromDER($data, $idx);
        // if certain length was expected
        if (isset($expected)) {
            if ($length->isIndefinite()) {
                throw new DecodeException(sprintf('Expected length %d, got indefinite.', $expected));
            }
            if ($expected !== $length->intLength()) {
                throw new DecodeException(sprintf('Expected length %d, got %d.', $expected, $length->intLength()));
            }
        }
        // check that enough data is available
        // the comparison is done on the big integer: a length encoded on many octets may not fit in an int, and
        // converting it first would let an IntegerOverflowException escape instead of a DecodeException
        if (! $length->isIndefinite()) {
            $remaining = mb_strlen($data, '8bit') - $idx;
            if ($length->_length->getValue()->isGreaterThan($remaining)) {
                throw new DecodeException(
                    sprintf('Length %s overflows data, %d bytes left.', $length->_length->base10(), $remaining)
                );
            }
        }
        $offset = $idx;
        return $length;
    }

    public function toDER(): string
    {
        $bytes = [];
        if ($this->_indefinite) {
            $bytes[] = 0x80;
        } else {
            $num = $this->_length->getValue();
            // long form
            if ($num->isGreaterThan(127)) {
                $octets = [];
                for (; $num->isGreaterThan(0); $num = $num->shiftedRight(8)) {
                    $octets[] = BigInteger::of(0xFF)->and($num)->toInt();
                }
                $count = count($octets);
                // first octet must not be 0xff
                if ($count >= 127) {
                    throw new DomainException('Too many length octets.');
                }
                $bytes[] = 0x80 | $count;
                foreach (array_reverse($octets) as $octet) {
                    $bytes[] = $octet;
                }
            } // short form
            else {
                $bytes[] = $num->toInt();
            }
        }
        return pack('C*', ...$bytes);
    }

    /**
     * Get the length.
     *
     * @return string Length as an integer string
     */
    public function length(): string
    {
        if ($this->_indefinite) {
            throw new LogicException('Length is indefinite.');
        }
        return $this->_length->base10();
    }

    /**
     * Get the length as an integer.
     */
    public function intLength(): int
    {
        if ($this->_indefinite) {
            throw new LogicException('Length is indefinite.');
        }
        try {
            return $this->_length->toInt();
        } catch (MathException $e) {
            // a length that does not fit in an int can never describe an in-memory string
            throw new DecodeException(
                sprintf('Length %s is too large.', $this->_length->base10()),
                0,
                $e
            );
        }
    }

    /**
     * Get the length as an integer, rejecting an indefinite length as a malformed encoding.
     *
     * Indefinite length is only permitted for constructed encodings (X.690 sect. 8.1.3.6), so a decoder that needs
     * a definite length is looking at hostile input rather than at a misuse of the API: intLength() would raise a
     * LogicException, which is outside the exception contract of fromDER().
     */
    public function expectIntLength(): int
    {
        if ($this->_indefinite) {
            throw new DecodeException('Length is indefinite, expected a definite length.');
        }
        return $this->intLength();
    }

    /**
     * Whether length is indefinite.
     */
    public function isIndefinite(): bool
    {
        return $this->_indefinite;
    }

    /**
     * @param int $length Number of octets
     * @param string $data Data
     * @param int $offset reference to the variable containing offset to the data
     */
    private static function decodeLongFormLength(int $length, string $data, int &$offset): BigInteger
    {
        // first octet must not be 0xff (spec 8.1.3.5c)
        if ($length === 127) {
            throw new DecodeException('Invalid number of length octets.');
        }
        // leading zero octets are not part of a minimal encoding (X.690 sect. 10.1)
        if (ord($data[$offset]) === 0x00) {
            throw new DecodeException('Leading zero octet in a long form length.');
        }
        $num = BigInteger::of(0);
        while (--$length >= 0) {
            $byte = ord($data[$offset++]);
            $num = $num->shiftedLeft(8)
                ->or($byte);
        }
        // a length below 128 must use the short form (X.690 sect. 10.1)
        if ($num->isLessThan(128)) {
            throw new DecodeException('Length must be encoded in the short form.');
        }

        return $num;
    }
}

<?php

declare(strict_types=1);

namespace Cose\Key;

use InvalidArgumentException;
use function ltrim;
use function ord;
use function sprintf;
use function strcmp;
use function strlen;
use Throwable;

/**
 * Checks an RSA key against the constraints the COSE and WebAuthn algorithm registrations rely on.
 *
 * RFC 8812 registers the RSA algorithms for WebAuthn and defers to RFC 8230, whose section 6.1 states that "a key
 * size of 2048 bits or larger MUST be used with these algorithms" and that implementations "SHOULD be able to encrypt
 * and decrypt with modulus between 2048 and 16K bits in length". The public exponent constraints come from RFC 8017,
 * section 3.1, which defines it as an odd integer between 3 and n - 1.
 *
 * Two families of constraints are applied by the RSA algorithms of this library to every key they are given, both
 * through Cose\Algorithm\Signature\RSA\RSA and Cose\Algorithm\Signature\RSA\PSSRSA: the public parameter constraints
 * of RFC 8017, section 3.1 (checkPublicParameters()), and the upper bounds on the size of the key
 * (checkLengthBounds()). Neither depends on a policy - an RSA operation costs an amount of CPU proportional to the
 * size of the key it is given, and that key is attacker supplied whenever it comes from the wire.
 *
 * The *minimum* modulus length is the policy choice, and the algorithms apply it too: each of them runs check() on
 * every key it is handed, with the validator it was created with or, by default, with create(). Legacy
 * authenticators holding 1024 bit keys exist, so the default bound only emits an E_USER_WARNING in 4.x and will
 * throw as of v5.0.0; a validator passed explicitly to the algorithm - RS256::create(RsaKeyValidator::create(
 * minimumModulusLength: 1024)) - is enforced with an exception right away, because the caller chose the bound.
 *
 * Every check below reads the parameters as the octet strings they are. Turning one into a number is a base
 * conversion, and brick/math falls back to a pure PHP calculator - whose generic base conversion is superlinear -
 * whenever neither ext-gmp nor ext-bcmath is loaded. A validator that runs before every operation must not grow
 * expensive with the size of the key it is handed.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc8812
 * @see https://www.rfc-editor.org/rfc/rfc8230#section-6.1
 * @see \Cose\Tests\Key\RsaKeyValidatorTest
 */
final class RsaKeyValidator
{
    /**
     * The smallest modulus length RFC 8230 allows, in bits.
     */
    public const MINIMUM_MODULUS_LENGTH = 2048;

    /**
     * The largest modulus length RFC 8230 expects implementations to cope with, in bits.
     */
    public const MAXIMUM_MODULUS_LENGTH = 16384;

    /**
     * The largest public exponent length any RSA operation of this library accepts, in bits.
     *
     * FIPS 186-5, appendix A.1.1 requires 2^16 < e < 2^256 of a generated key; RFC 8017, section 3.1 places no upper
     * bound at all, which is precisely why one has to be imposed here. The public operation is a modular
     * exponentiation whose cost is proportional to the length of the exponent, so an unbounded e is an unbounded
     * amount of work: at the largest modulus RFC 8230 asks for, e = n - 2 costs sixty four times as much as this
     * bound allows.
     */
    public const MAXIMUM_EXPONENT_LENGTH = 256;

    /**
     * The warning an RSA algorithm emits when the key it is given is rejected by the validator it defaulted to. Its
     * only placeholder receives the message of the underlying InvalidArgumentException.
     *
     * @see \Cose\Algorithm\Signature\RSA\RsaKeyPolicy
     */
    public const WEAK_KEY_MESSAGE = 'The RSA key does not satisfy RFC 8230 section 6.1: %s. Accept it explicitly with "RsaKeyValidator::create(minimumModulusLength: 1024)"; as of v5.0.0, it will be rejected with an exception.';

    private function __construct(
        private readonly int $minimumModulusLength,
        private readonly int $maximumModulusLength
    ) {
        if ($minimumModulusLength < 1) {
            throw new InvalidArgumentException('The minimum modulus length shall be a positive integer');
        }
        if ($maximumModulusLength < $minimumModulusLength) {
            throw new InvalidArgumentException(
                'The maximum modulus length shall be greater than or equal to the minimum modulus length'
            );
        }
    }

    public static function create(
        int $minimumModulusLength = self::MINIMUM_MODULUS_LENGTH,
        int $maximumModulusLength = self::MAXIMUM_MODULUS_LENGTH
    ): self {
        return new self($minimumModulusLength, $maximumModulusLength);
    }

    /**
     * Returns the length of the modulus of the key, in bits.
     */
    public static function modulusLength(RsaKey $key): int
    {
        return self::bitLength($key->n());
    }

    /**
     * Returns the length of the public exponent of the key, in bits.
     */
    public static function exponentLength(RsaKey $key): int
    {
        return self::bitLength($key->e());
    }

    /**
     * The upper bounds on the size of a key that every RSA algorithm of this library applies before it does anything
     * with it. They are not a policy the caller opts into: an RSA operation costs an amount of CPU proportional to
     * the size of the modulus and of the exponent it is given, both of which are attacker supplied whenever the key
     * travels on the wire, and that cost is paid before anything is known about the signature. RFC 8230, section 6.1
     * asks for exactly this: "It is highly recommended that checks on the key length be done before starting a
     * cryptographic operation."
     *
     * No minimum is applied here, so that this method never rejects a key an earlier release accepted.
     *
     * @throws InvalidArgumentException when the key is larger than this library is willing to compute with
     */
    public static function checkLengthBounds(RsaKey $key): void
    {
        self::checkMaximumModulusLength($key, self::MAXIMUM_MODULUS_LENGTH);
        self::checkMaximumExponentLength($key);
    }

    /**
     * @throws InvalidArgumentException when the key does not satisfy the constraints
     */
    public function check(RsaKey $key): void
    {
        $modulusLength = self::modulusLength($key);
        if ($modulusLength < $this->minimumModulusLength) {
            throw new InvalidArgumentException(sprintf(
                'The modulus of the key is %d bits long; at least %d bits are required',
                $modulusLength,
                $this->minimumModulusLength
            ));
        }
        self::checkMaximumModulusLength($key, $this->maximumModulusLength);

        self::checkPublicParameters($key);
    }

    /**
     * The constraints RFC 8017, section 3.1 places on the public parameters of an RSA key: an odd modulus and a
     * public exponent that is an odd integer between 3 and n - 1. Both primitives of section 5.2 are defined under
     * the assumption that the key satisfies them, and none of their guarantees survives a key that does not: with
     * e = 1 the public operation is the identity map, so a padded message encoding - data that is entirely public -
     * is accepted as a signature of the message it encodes.
     *
     * Unlike the modulus length bounds, these constraints depend on no policy and admit no legitimate exception,
     * which is why the RSA algorithms apply them on their own.
     *
     * @throws InvalidArgumentException when the key does not satisfy the constraints
     */
    public static function checkPublicParameters(RsaKey $key): void
    {
        self::checkModulus($key);
        self::checkExponent($key);
    }

    public function isValid(RsaKey $key): bool
    {
        try {
            $this->check($key);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * The modulus is the product of at least two odd primes (RFC 8017, section 3.1), hence odd and non-zero. An even
     * modulus is not merely out of specification: taking e-th roots modulo a power of two is easy, so a signature
     * under an attacker chosen even modulus can be computed without any private key.
     */
    private static function checkModulus(RsaKey $key): void
    {
        $rawModulus = ltrim($key->n(), "\x00");
        if ($rawModulus === '') {
            throw new InvalidArgumentException('The modulus of the key shall not be zero');
        }
        if ((ord($rawModulus[strlen($rawModulus) - 1]) & 1) !== 1) {
            throw new InvalidArgumentException('The modulus of the key shall be odd');
        }
    }

    private static function checkExponent(RsaKey $key): void
    {
        $exponent = ltrim($key->e(), "\x00");
        if ($exponent === '' || (ord($exponent[strlen($exponent) - 1]) & 1) !== 1) {
            throw new InvalidArgumentException('The public exponent of the key shall be odd');
        }
        if (strlen($exponent) === 1 && ord($exponent[0]) < 3) {
            throw new InvalidArgumentException('The public exponent of the key shall be greater than or equal to 3');
        }
        if (self::compareMagnitudes($exponent, ltrim($key->n(), "\x00")) >= 0) {
            throw new InvalidArgumentException('The public exponent of the key shall be lower than its modulus');
        }
        self::checkMaximumExponentLength($key);
    }

    private static function checkMaximumModulusLength(RsaKey $key, int $maximumModulusLength): void
    {
        $modulusLength = self::modulusLength($key);
        if ($modulusLength > $maximumModulusLength) {
            throw new InvalidArgumentException(sprintf(
                'The modulus of the key is %d bits long; at most %d bits are allowed',
                $modulusLength,
                $maximumModulusLength
            ));
        }
    }

    private static function checkMaximumExponentLength(RsaKey $key): void
    {
        $exponentLength = self::exponentLength($key);
        if ($exponentLength > self::MAXIMUM_EXPONENT_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'The public exponent of the key is %d bits long; at most %d bits are allowed',
                $exponentLength,
                self::MAXIMUM_EXPONENT_LENGTH
            ));
        }
    }

    /**
     * Compares two non-negative integers given as big-endian octet strings stripped of their leading zero octets: the
     * longer one is the larger, and equal lengths are decided octet by octet.
     */
    private static function compareMagnitudes(string $left, string $right): int
    {
        $byLength = strlen($left) <=> strlen($right);

        return $byLength === 0 ? strcmp($left, $right) : $byLength;
    }

    /**
     * The length in bits of the non-negative integer whose big-endian octet string is $value.
     */
    private static function bitLength(string $value): int
    {
        $value = ltrim($value, "\x00");
        if ($value === '') {
            return 0;
        }

        $length = (strlen($value) - 1) * 8;
        for ($mostSignificantByte = ord($value[0]); $mostSignificantByte > 0; $mostSignificantByte >>= 1) {
            ++$length;
        }

        return $length;
    }
}

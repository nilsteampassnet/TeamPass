<?php

declare(strict_types=1);

namespace Cose\Key;

use InvalidArgumentException;
use function is_string;
use function sprintf;
use function strlen;
use Throwable;

/**
 * Checks a symmetric key against the constraints the COSE MAC algorithms rely on.
 *
 * RFC 9053, section 3.1 requires implementations to "validate that the key type, key length, and algorithm are
 * correct and appropriate for the entities involved". The key type and the byte string nature of `k` admit no
 * exception and are therefore applied by the algorithms themselves: Cose\Algorithm\Mac\Hmac rejects a key that is
 * not symmetric, whose `k` is missing, is not a PHP string, or is empty.
 *
 * The *minimum* key length is the policy choice, and it stays opt-in, exactly like the minimum modulus length of
 * RsaKeyValidator: RFC 2104, section 3 only states that a key shorter than the output of the hash function is
 * "strongly discouraged", and existing deployments do key HS384/HS512 with 32 bytes. Run this validator explicitly
 * on a key before handing it to an algorithm to turn that discouragement into a hard failure today, instead of the
 * E_USER_WARNING the algorithms emit.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9053#section-3.1
 * @see https://www.rfc-editor.org/rfc/rfc2104#section-3
 * @see \Cose\Tests\Key\SymmetricKeyValidatorTest
 */
final class SymmetricKeyValidator
{
    /**
     * The output length of SHA-256, in bytes: the shortest key RFC 2104, section 3 does not discourage for HS256 and
     * HS256/64, and the smallest of the three lengths the MAC algorithms of this library use.
     *
     * Cose\Algorithm\Mac\Hmac::minimumKeyLength() gives the value that matches a given algorithm (32, 48 or 64).
     */
    public const MINIMUM_KEY_LENGTH = 32;

    private function __construct(
        private readonly int $minimumKeyLength
    ) {
        if ($minimumKeyLength < 1) {
            throw new InvalidArgumentException('The minimum key length shall be a positive integer');
        }
    }

    public static function create(int $minimumKeyLength = self::MINIMUM_KEY_LENGTH): self
    {
        return new self($minimumKeyLength);
    }

    /**
     * Returns the length of the key value, in bytes.
     *
     * @throws InvalidArgumentException when the key is not a usable symmetric key
     */
    public static function keyLength(Key $key): int
    {
        return strlen(self::keyValue($key));
    }

    /**
     * The constraints every MAC algorithm of this library applies on its own: a symmetric key whose `k` is present,
     * is a byte string and is not empty. None of them depends on a policy - a `k` that is not a byte string is
     * outside RFC 9053, section 7.3, and an empty key makes every tag computable by anyone.
     *
     * @throws InvalidArgumentException when the key does not satisfy the constraints
     */
    public static function checkKeyValue(Key $key): void
    {
        self::keyValue($key);
    }

    /**
     * @throws InvalidArgumentException when the key does not satisfy the constraints
     */
    public function check(Key $key): void
    {
        $keyLength = strlen(self::keyValue($key));
        if ($keyLength < $this->minimumKeyLength) {
            throw new InvalidArgumentException(sprintf(
                'The key is %d bytes long; at least %d bytes are required',
                $keyLength,
                $this->minimumKeyLength
            ));
        }
    }

    public function isValid(Key $key): bool
    {
        try {
            $this->check($key);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * @throws InvalidArgumentException when the key does not satisfy the constraints
     */
    private static function keyValue(Key $key): string
    {
        if ($key->type() !== Key::TYPE_OCT && $key->type() !== Key::TYPE_NAME_OCT) {
            throw new InvalidArgumentException('Invalid key. Must be of type symmetric');
        }
        if (! $key->has(SymmetricKey::DATA_K)) {
            throw new InvalidArgumentException('Invalid key. The value of the key is missing');
        }
        $k = $key->get(SymmetricKey::DATA_K);
        if (! is_string($k)) {
            throw new InvalidArgumentException(
                'Invalid key. The value of the key must be a byte string (CBOR objects shall be normalized first)'
            );
        }
        if ($k === '') {
            throw new InvalidArgumentException('Invalid key. The value of the key is empty');
        }

        return $k;
    }
}

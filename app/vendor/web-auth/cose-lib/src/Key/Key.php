<?php

declare(strict_types=1);

namespace Cose\Key;

use function array_key_exists;
use function array_values;
use const FILTER_VALIDATE_INT;
use function filter_var;
use function implode;
use function in_array;
use InvalidArgumentException;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function sprintf;

class Key
{
    public const TYPE = 1;

    public const TYPE_OKP = 1;

    public const TYPE_EC2 = 2;

    public const TYPE_RSA = 3;

    public const TYPE_OCT = 4;

    public const TYPE_NAME_OKP = 'OKP';

    public const TYPE_NAME_EC2 = 'EC';

    public const TYPE_NAME_RSA = 'RSA';

    public const TYPE_NAME_OCT = 'oct';

    public const KID = 2;

    public const ALG = 3;

    public const KEY_OPS = 4;

    public const BASE_IV = 5;

    /**
     * Key operations, RFC 9052 section 7.1, Table 5. They are the values the "key_ops" parameter (label 4) is made of.
     */
    public const OP_SIGN = 1;

    public const OP_VERIFY = 2;

    public const OP_ENCRYPT = 3;

    public const OP_DECRYPT = 4;

    public const OP_WRAP_KEY = 5;

    public const OP_UNWRAP_KEY = 6;

    public const OP_DERIVE_KEY = 7;

    public const OP_DERIVE_BITS = 8;

    public const OP_MAC_CREATE = 9;

    public const OP_MAC_VERIFY = 10;

    /**
     * The names of Table 5. A COSE key carries the integer values, but a key converted from a JWK may carry the text
     * names JOSE uses for the same operations, so both are accepted when "key_ops" is read.
     *
     * @var array<int, string>
     */
    private const OP_NAMES = [
        self::OP_SIGN => 'sign',
        self::OP_VERIFY => 'verify',
        self::OP_ENCRYPT => 'encrypt',
        self::OP_DECRYPT => 'decrypt',
        self::OP_WRAP_KEY => 'wrap key',
        self::OP_UNWRAP_KEY => 'unwrap key',
        self::OP_DERIVE_KEY => 'derive key',
        self::OP_DERIVE_BITS => 'derive bits',
        self::OP_MAC_CREATE => 'MAC create',
        self::OP_MAC_VERIFY => 'MAC verify',
    ];

    /**
     * @var array<int|string, mixed>
     */
    private readonly array $data;

    /**
     * @param array<int|string, mixed> $data
     */
    public function __construct(array $data)
    {
        if (! array_key_exists(self::TYPE, $data)) {
            throw new InvalidArgumentException('Invalid key: the type is not defined');
        }
        // The key type is normalised for every key, the generic one included, so that type() answers with the
        // registry value whether the key was decoded from CBOR - where spomky-labs/cbor-php renders an integer as a
        // numeric string - or built by hand. Everything that checks a key type, from the subclasses below to the
        // MAC algorithms, then compares against Key::TYPE_* and sees the same value on both paths.
        $this->data = self::normalizeIntegerEntries($data, self::TYPE);
    }

    /**
     * @param array<int|string, mixed> $data
     */
    public static function create(array $data): self
    {
        return new self($data);
    }

    /**
     * Builds the key class the "kty" of the data designates.
     *
     * The data is expected to be the output of spomky-labs/cbor-php's normalize(), which renders a CBOR integer as a
     * numeric string, or an equivalent PHP array: both the string and the native integer form of a registered key
     * type are dispatched, as is its name (RFC 9052, section 7.1, table 4 types "kty" as "tstr / int").
     *
     * @param array<int|string, mixed> $data
     */
    public static function createFromData(array $data): self
    {
        if (! array_key_exists(self::TYPE, $data)) {
            throw new InvalidArgumentException('Invalid key: the type is not defined');
        }

        return match ($data[self::TYPE]) {
            self::TYPE_OKP, '1', self::TYPE_NAME_OKP => new OkpKey($data),
            self::TYPE_EC2, '2', self::TYPE_NAME_EC2 => new Ec2Key($data),
            self::TYPE_RSA, '3', self::TYPE_NAME_RSA => new RsaKey($data),
            self::TYPE_OCT, '4', self::TYPE_NAME_OCT => new SymmetricKey($data),
            default => self::create($data),
        };
    }

    public function type(): int|string
    {
        return $this->data[self::TYPE];
    }

    /**
     * The algorithm identifier the key is restricted to, RFC 9052 section 7.1, label 3.
     *
     * @throws InvalidArgumentException when the key has no "alg", or when its value is not an algorithm identifier.
     *                                  A text value such as "RS256" is not one: the COSE registry is made of
     *                                  integers, and casting it would silently yield 0, an identifier no algorithm
     *                                  is registered under.
     */
    public function alg(): int
    {
        $alg = $this->get(self::ALG);
        if (is_int($alg)) {
            return $alg;
        }
        // An integer written as a string is accepted, as the key constructors do for "kty" and "crv".
        if (is_string($alg) && filter_var($alg, FILTER_VALIDATE_INT) !== false) {
            return (int) $alg;
        }

        throw new InvalidArgumentException(
            'Invalid key: the "alg" parameter must be an integer algorithm identifier'
        );
    }

    /**
     * The operations the key is restricted to, RFC 9052 section 7.1, label 4, or null when it carries no such
     * restriction.
     *
     * @throws InvalidArgumentException when "key_ops" is present but is not an array of integers or text names
     * @return list<int|string>|null
     */
    public function keyOps(): ?array
    {
        if (! $this->has(self::KEY_OPS)) {
            return null;
        }
        $keyOps = $this->get(self::KEY_OPS);
        if (! is_array($keyOps)) {
            throw new InvalidArgumentException('Invalid key: the "key_ops" parameter must be an array');
        }
        foreach ($keyOps as $keyOp) {
            if (! is_int($keyOp) && ! is_string($keyOp)) {
                throw new InvalidArgumentException(
                    'Invalid key: the "key_ops" parameter must only contain integers or text names'
                );
            }
        }

        return array_values($keyOps);
    }

    /**
     * Whether the restrictions the key carries allow the operation to be performed with the given algorithm.
     *
     * @see self::assertUsableWith() for the conditions and the specifications behind them
     */
    public function isUsableWith(int $algorithmIdentifier, int $operation): bool
    {
        try {
            $this->assertUsableWith($algorithmIdentifier, $operation);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * RFC 9052, section 7.1: a key that carries "alg" (label 3) MUST NOT be used with another algorithm - "If the
     * algorithms do not match, then this key object MUST NOT be used to perform the cryptographic operation" - and
     * one that carries "key_ops" (label 4) MUST list the operation being performed. RFC 9053 sections 2.1, 2.2 and
     * 3.1 repeat both as a per-algorithm requirement for ECDSA, EdDSA and HMAC.
     *
     * The comparison is a plain equality of identifiers: the fully-specified identifiers of RFC 9864 and their
     * polymorphic counterparts are distinct values here, ES256 (-7) and ESP256 (-9) among them. Section 7 of that
     * RFC asks for it - "A cryptographic key MUST be used with only a single algorithm unless the use of the same
     * key with different algorithms is proven secure." A caller who wants one key to serve both removes label 3
     * from the key data, which is an explicit act.
     *
     * A key that carries neither parameter is usable with every algorithm and for every operation.
     *
     * @throws InvalidArgumentException when the key forbids the combination
     *
     * @see https://www.rfc-editor.org/rfc/rfc9052.html#section-7.1
     */
    public function assertUsableWith(int $algorithmIdentifier, int $operation): void
    {
        if ($this->has(self::ALG) && $this->alg() !== $algorithmIdentifier) {
            throw new InvalidArgumentException(sprintf(
                'The key is restricted to the algorithm %d and cannot be used with the algorithm %d',
                $this->alg(),
                $algorithmIdentifier
            ));
        }

        $keyOps = $this->keyOps();
        if ($keyOps === null) {
            return;
        }
        $name = self::OP_NAMES[$operation] ?? throw new InvalidArgumentException(sprintf(
            'Unknown key operation %d. Expected one of: %s',
            $operation,
            implode(', ', self::OP_NAMES)
        ));
        if (! in_array($operation, $keyOps, true) && ! in_array($name, $keyOps, true)) {
            throw new InvalidArgumentException(sprintf('The key does not allow the "%s" operation', $name));
        }
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(int|string $key): mixed
    {
        if (! array_key_exists($key, $this->data)) {
            throw new InvalidArgumentException(sprintf('The key has no data at index %d', $key));
        }

        return $this->data[$key];
    }

    /**
     * spomky-labs/cbor-php normalises a CBOR integer to a numeric string (UnsignedIntegerObject::normalize()), so a
     * key decoded from CBOR carries its "kty" and its "crv" as such a string. Each listed entry that holds one is
     * replaced by the integer it denotes, before anything compares it against a registry value.
     *
     * Only an integer-looking string is converted. A float, or a string such as "1.5", " 1" or "4abc", is left as it
     * is so that the checks that follow reject it, instead of a lenient cast silently turning it into a valid
     * identifier.
     *
     * @param array<int|string, mixed> $data
     * @return array<int|string, mixed>
     */
    protected static function normalizeIntegerEntries(array $data, int|string ...$keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && is_string($data[$key])
                && preg_match('/^-?\\d+$/', $data[$key]) === 1) {
                $data[$key] = (int) $data[$key];
            }
        }

        return $data;
    }

    protected function pem(string $type, string $der): string
    {
        return sprintf("-----BEGIN %s-----\n", strtoupper($type)) .
            chunk_split(base64_encode($der), 64, "\n") .
            sprintf("-----END %s-----\n", strtoupper($type));
    }
}

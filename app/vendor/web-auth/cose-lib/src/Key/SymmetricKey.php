<?php

declare(strict_types=1);

namespace Cose\Key;

use InvalidArgumentException;
use function is_string;

/**
 * A symmetric COSE key (RFC 9053, section 7.3).
 *
 * Table 21 of that section types the key value `k` as a `bstr`, which is what the k() accessor of this class has
 * always returned. The constructor enforces the same contract: a `k` that is not a PHP string, or that is empty, is
 * rejected where the mistake is easiest to attribute rather than at the first cryptographic operation.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9053#section-7.3
 * @see \Cose\Tests\Key\SymmetricKeyTest
 *
 * @final
 */
class SymmetricKey extends Key
{
    final public const DATA_K = -1;

    /**
     * @param array<int|string, mixed> $data
     */
    public function __construct(array $data)
    {
        // The three sibling key classes normalise and store the key type; this one used to cast it inside its own
        // comparison only, so a key decoded from CBOR kept the string "4" that every HMAC algorithm then rejected.
        $data = self::normalizeIntegerEntries($data, self::TYPE);
        parent::__construct($data);
        if ($data[self::TYPE] !== self::TYPE_OCT && $data[self::TYPE] !== self::TYPE_NAME_OCT) {
            throw new InvalidArgumentException(
                'Invalid symmetric key. The key type does not correspond to a symmetric key'
            );
        }
        if (! isset($data[self::DATA_K])) {
            throw new InvalidArgumentException('Invalid symmetric key. The parameter "k" is missing');
        }
        if (! is_string($data[self::DATA_K])) {
            throw new InvalidArgumentException(
                'Invalid symmetric key. The parameter "k" shall be a byte string (CBOR objects shall be normalized first)'
            );
        }
        if ($data[self::DATA_K] === '') {
            throw new InvalidArgumentException('Invalid symmetric key. The parameter "k" is empty');
        }
    }

    /**
     * @param array<int|string, mixed> $data
     */
    public static function create(array $data): self
    {
        return new self($data);
    }

    public function k(): string
    {
        return $this->get(self::DATA_K);
    }
}

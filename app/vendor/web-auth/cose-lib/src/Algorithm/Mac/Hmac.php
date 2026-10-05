<?php

declare(strict_types=1);

namespace Cose\Algorithm\Mac;

use Cose\Algorithm\KeyRestrictionAware;
use Cose\Algorithm\KeyRestrictionEnforcement;
use Cose\Key\Key;
use Cose\Key\SymmetricKey;
use const E_USER_WARNING;
use function hash;
use function hash_hmac;
use InvalidArgumentException;
use function is_string;
use function sprintf;
use function strlen;
use function trigger_error;

/**
 * HMAC-based Message Authentication Codes (RFC 9053, section 3.1).
 *
 * RFC 9053, section 3.1 requires that "implementations creating and validating MAC values MUST validate that the key
 * type, key length, and algorithm are correct and appropriate for the entities involved", and section 7.3 types the
 * key value `k` as a byte string. Accordingly, a key whose `k` is absent, not a PHP string or empty is rejected: none
 * of them is ever a legitimate HMAC key, and coercing such a value would silently key the MAC with a constant an
 * outsider can guess ("Array", "1", the empty string, …).
 *
 * The key length is a softer matter. RFC 2104, section 3 states that a key shorter than the output of the hash
 * function is "strongly discouraged", and RFC 9053, section 3.1 only makes the hash output size a SHOULD for keys
 * that are transported or derived by a recipient algorithm - neither of which this library implements. A shorter key
 * is therefore accepted, but it emits an E_USER_WARNING unless the caller explicitly acknowledges the risk by
 * creating the algorithm with `acknowledgeShortKey: true`, following the same shape as
 * Cose\Algorithm\Signature\RSA\RS1. From v5.0.0 the same call without that acknowledgement will throw an
 * InvalidArgumentException instead of warning.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9053#section-3.1
 * @see https://www.rfc-editor.org/rfc/rfc2104#section-3
 * @see \Cose\Tests\Algorithm\Mac\HmacTest
 */
abstract class Hmac implements Mac, KeyRestrictionAware
{
    use KeyRestrictionEnforcement;

    public const SHORT_KEY_MESSAGE = 'The HMAC key is %d bytes long, shorter than the %d-byte hash output (RFC 2104, section 3). Create the algorithm with "acknowledgeShortKey: true" to acknowledge it; as of v5.0.0 this will throw.';

    public function __construct(
        private readonly bool $acknowledgeShortKey = false
    ) {
    }

    public function hash(string $data, Key $key): string
    {
        // RFC 9053, section 3.1: "If the 'key_ops' field is present, it MUST include 'MAC create' when creating an
        // HMAC authentication tag."
        return $this->compute($data, $this->checKey($key, Key::OP_MAC_CREATE));
    }

    public function verify(string $data, Key $key, string $signature): bool
    {
        // ... and it MUST include 'MAC verify' when verifying one, so the two operations cannot share a code path.
        return hash_equals($this->compute($data, $this->checKey($key, Key::OP_MAC_VERIFY)), $signature);
    }

    /**
     * The length, in bytes, of the output of the underlying hash function: the shortest key RFC 2104, section 3 does
     * not discourage for this algorithm.
     */
    public function minimumKeyLength(): int
    {
        return strlen(hash($this->getHashAlgorithm(), '', true));
    }

    abstract protected function getHashAlgorithm(): string;

    abstract protected function getSignatureLength(): int;

    private function compute(string $data, string $k): string
    {
        $signature = hash_hmac($this->getHashAlgorithm(), $data, $k, true);

        return substr($signature, 0, intdiv($this->getSignatureLength(), 8));
    }

    /**
     * @param int $operation the Key::OP_* constant of the operation the key is about to be used for
     *
     * @throws InvalidArgumentException when the key cannot be used with this algorithm
     */
    private function checKey(Key $key, int $operation): string
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

        $minimumKeyLength = $this->minimumKeyLength();
        if (strlen($k) < $minimumKeyLength && ! $this->acknowledgeShortKey) {
            trigger_error(sprintf(self::SHORT_KEY_MESSAGE, strlen($k), $minimumKeyLength), E_USER_WARNING);
        }

        $this->checkKeyRestrictions($key, $operation);

        return $k;
    }
}

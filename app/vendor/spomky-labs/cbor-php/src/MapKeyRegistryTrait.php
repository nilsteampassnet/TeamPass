<?php

declare(strict_types=1);

namespace CBOR;

use function get_debug_type;
use InvalidArgumentException;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Shared key bookkeeping for the two map objects.
 *
 * Map entries are stored in a native PHP array keyed by the normalized key. PHP casts numeric strings to integers
 * when they are used as an array offset, so several structurally distinct CBOR keys used to land on the same slot
 * and silently overwrite one another: the integer 1, the text string "1" and the byte string h'31' all normalize to
 * the string '1'. Keys that do not normalize to a scalar -- an array or a map, which RFC 8949 permits -- reached the
 * offset as an array and raised a TypeError.
 *
 * The registry records, for every occupied offset, the major type the key came from. That is enough to tell a
 * genuine duplicate from two distinct keys colliding on one offset, and to reject both explicitly.
 *
 * @internal
 */
trait MapKeyRegistryTrait
{
    /**
     * Occupied offset => "<major type>:<normalized key>".
     *
     * @var array<int|string, string>
     */
    private array $keyIdentities = [];

    /**
     * @return int|string the offset the entry shall be stored at
     */
    private function registerKey(CBORObject $key, bool $allowReplace): int|string
    {
        $offset = self::assertNormalizableToScalar($key);
        $identity = $key->getMajorType() . ':' . $offset;
        $existing = $this->keyIdentities[$offset] ?? null;

        if ($existing !== null && $existing !== $identity) {
            throw new InvalidArgumentException(sprintf(
                'Invalid key. A key of major type %s and a key of major type %s both resolve to the offset "%s".',
                explode(':', $existing)[0],
                $key->getMajorType(),
                (string) $offset
            ));
        }

        if ($existing !== null && ! $allowReplace) {
            throw new InvalidArgumentException(sprintf(
                'Invalid key. The key "%s" is defined more than once in the map.',
                (string) $offset
            ));
        }

        $this->keyIdentities[$offset] = $identity;

        return $offset;
    }

    private function unregisterKey(int|string $offset): void
    {
        unset($this->keyIdentities[$offset]);
    }

    /**
     * @param MapItem[] $data
     *
     * @return MapItem[] the entries, re-keyed by their normalized key
     */
    private function registerKeys(array $data): array
    {
        $entries = [];
        foreach ($data as $item) {
            if (! $item instanceof MapItem) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid item. A map shall only contain "%s" objects, got "%s".',
                    MapItem::class,
                    get_debug_type($item)
                ));
            }
            $entries[$this->registerKey($item->getKey(), false)] = $item;
        }

        return $entries;
    }

    /**
     * @return int|string
     */
    private static function assertNormalizableToScalar(CBORObject $key)
    {
        if (! $key instanceof Normalizable) {
            throw new InvalidArgumentException('Invalid key. Shall be normalizable');
        }

        // A list or a map never normalizes to a scalar, so the answer is already known from the head of the item.
        // Reading it first matters for more than the message: normalizing a container walks the whole sub-structure,
        // and a hostile document can make that arbitrarily expensive for a key that is turned down anyway.
        $majorType = $key->getMajorType();
        if ($majorType === CBORObject::MAJOR_TYPE_LIST || $majorType === CBORObject::MAJOR_TYPE_MAP) {
            throw new InvalidArgumentException(
                'Invalid key. A map key shall normalize to an integer or a string, got "array".'
            );
        }

        $normalized = $key->normalize();
        if (! is_int($normalized) && ! is_string($normalized)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid key. A map key shall normalize to an integer or a string, got "%s".',
                get_debug_type($normalized)
            ));
        }

        return $normalized;
    }
}

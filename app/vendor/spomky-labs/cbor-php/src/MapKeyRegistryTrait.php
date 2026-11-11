<?php

declare(strict_types=1);

namespace CBOR;

use function array_key_exists;
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
 * the string '1'. The registry records, for every occupied offset, the major type of the key that owns it, which is
 * enough to tell a genuine duplicate -- rejected -- from two distinct keys meeting on one offset.
 *
 * RFC 8949 section 3.1 allows any data item as a key, and a PHP array offset can only stand for some of them. A key
 * that does not normalize to an integer or a string -- a float, a boolean, null, a list, a map, a tag that expands
 * to an object -- or that resolves to an offset a key of another major type already owns is kept all the same, in
 * order, under an offset derived from its encoded bytes. Such a key is "opaque": the map decodes, iterates and
 * writes back exactly as it was read, and a repeated key is still caught. What cannot be done is to normalize the
 * map to a PHP array, or to reach the entry through get(), has() and remove(), whose offsets are the integers and
 * strings the other keys normalize to. An offset that keys of two major types resolve to is ambiguous and is not
 * reachable that way either.
 *
 * @internal
 */
trait MapKeyRegistryTrait
{
    /**
     * Occupied offset => "<major type>:<offset>" of the key that owns it.
     *
     * @var array<int|string, string>
     */
    private array $keyIdentities = [];

    /**
     * The offsets of the opaque keys.
     *
     * @var array<int|string, true>
     */
    private array $opaqueOffsets = [];

    /**
     * Scalar offset => major type of the second kind of key that resolved to it.
     *
     * @var array<int|string, int>
     */
    private array $ambiguousOffsets = [];

    /**
     * @return int|string the offset the entry shall be stored at
     */
    private function registerKey(CBORObject $key, bool $allowReplace): int|string
    {
        $majorType = $key->getMajorType();
        [$offset, $opaque] = self::offsetOf($key);
        $existing = $this->keyIdentities[$offset] ?? null;

        if (! $opaque && $existing !== null && $existing !== $majorType . ':' . $offset) {
            // A key of another major type owns the offset. Neither can be told from the other through a PHP
            // offset, so this one is stored opaque and the offset is marked as ambiguous.
            $this->ambiguousOffsets[$offset] = $majorType;
            $offset = self::opaqueOffsetOf($key);
            $opaque = true;
            $existing = $this->keyIdentities[$offset] ?? null;
        }

        $identity = $majorType . ':' . $offset;
        if ($existing !== null && $existing !== $identity) {
            throw new InvalidArgumentException(sprintf(
                'Invalid key. A key of major type %s and a key of major type %s both resolve to the offset "%s".',
                explode(':', $existing)[0],
                $majorType,
                (string) $offset
            ));
        }

        if ($existing !== null && ! $allowReplace) {
            throw new InvalidArgumentException(sprintf(
                'Invalid key. The key "%s" is defined more than once in the map.',
                $opaque ? bin2hex(substr((string) $offset, 1)) : (string) $offset
            ));
        }

        $this->keyIdentities[$offset] = $identity;
        if ($opaque) {
            $this->opaqueOffsets[$offset] = true;
        }

        return $offset;
    }

    private function unregisterKey(int|string $offset): void
    {
        unset($this->keyIdentities[$offset], $this->opaqueOffsets[$offset], $this->ambiguousOffsets[$offset]);
    }

    /**
     * Whether the offset addresses exactly one entry.
     *
     * @param MapItem[] $data
     */
    private function isAddressable(array $data, int|string $offset): bool
    {
        return array_key_exists($offset, $data) && ! isset($this->ambiguousOffsets[$offset]);
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
     * Turns the entries into a PHP array, or explains which key stands in the way.
     *
     * @param MapItem[] $data
     *
     * @return array<int|string, mixed>
     */
    private function normalizeEntries(array $data): array
    {
        $normalized = [];
        foreach ($data as $offset => $item) {
            if (isset($this->ambiguousOffsets[$offset])) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid key. A key of major type %s and a key of major type %s both resolve to the offset "%s".',
                    explode(':', $this->keyIdentities[$offset])[0],
                    $this->ambiguousOffsets[$offset],
                    (string) $offset
                ));
            }
            if (isset($this->opaqueOffsets[$offset])) {
                throw self::opaqueKeyException($item->getKey());
            }
            $valueObject = $item->getValue();
            $normalized[$offset] = $valueObject instanceof Normalizable ? $valueObject->normalize() : $valueObject;
        }

        return $normalized;
    }

    /**
     * The PHP array offset of the key.
     *
     * A list or a map never normalizes to a scalar, so the answer is already known from the head of the item.
     * Reading it first matters: normalizing a container walks the whole sub-structure, and a hostile document can
     * make that arbitrarily expensive for a key whose value nobody can address anyway.
     *
     * @return array{int|string, bool} the offset, and whether the key is opaque
     */
    private static function offsetOf(CBORObject $key): array
    {
        $majorType = $key->getMajorType();
        if ($majorType !== CBORObject::MAJOR_TYPE_LIST && $majorType !== CBORObject::MAJOR_TYPE_MAP && $key instanceof Normalizable) {
            $normalized = $key->normalize();
            if (is_int($normalized) || is_string($normalized)) {
                return [$normalized, false];
            }
        }

        return [self::opaqueOffsetOf($key), true];
    }

    /**
     * The encoded bytes of the key behind a NUL byte: an offset no normalized key produces, since an integer never
     * starts with NUL and a text string never contains it. A byte string that happens to hold the same bytes would
     * meet the opaque key on that offset, and the registry then reports the two as it does any other collision.
     */
    private static function opaqueOffsetOf(CBORObject $key): string
    {
        return "\0" . $key;
    }

    /**
     * The exception an opaque key raises when the map is normalized, naming what the key would have become.
     */
    private static function opaqueKeyException(CBORObject $key): InvalidArgumentException
    {
        $majorType = $key->getMajorType();
        if ($majorType === CBORObject::MAJOR_TYPE_LIST || $majorType === CBORObject::MAJOR_TYPE_MAP) {
            $type = 'array';
        } elseif ($key instanceof Normalizable) {
            $type = get_debug_type($key->normalize());
        } else {
            $type = get_debug_type($key);
        }

        return new InvalidArgumentException(sprintf(
            'Invalid key. A map key shall normalize to an integer or a string, got "%s".',
            $type
        ));
    }
}

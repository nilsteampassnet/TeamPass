<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\Tag;

/**
 * Tag 55799: Self-Described CBOR
 *
 * This tag is used to mark CBOR data to enable a decoder to rapidly identify
 * that the data item is in CBOR encoding. This tag is intentionally placed at
 * a high tag number to minimize collision with other applications.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc8949#section-3.4.6
 *
 * @deprecated since 3.3.5, use {@see CBORTag} instead. Will be removed in 4.0.0.
 *
 * Both classes declare tag 55799, but a tag manager can only register one class per tag number.
 * `CBORTag` is the one registered by the default decoder, so a decoded self-described document is
 * always a `CBORTag`, never a `SelfDescribeCBORTag`. `CBORTag` also implements `Normalizable`.
 * `getCBORObject()` has no equivalent on `CBORTag`; use the inherited `getValue()`, which it duplicates.
 */
final class SelfDescribeCBORTag extends Tag
{
    public static function getTagId(): int
    {
        return self::TAG_CBOR;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_CBOR);

        return new self($ai, $data, $object);
    }

    /**
     * Get the wrapped CBOR object
     */
    public function getCBORObject(): CBORObject
    {
        return $this->object;
    }
}

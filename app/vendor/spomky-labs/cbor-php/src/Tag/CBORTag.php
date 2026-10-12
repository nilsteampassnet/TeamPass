<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\Normalizable;
use CBOR\Tag;

/**
 * Tag 55799: Self-Described CBOR
 *
 * This tag is used to mark CBOR data to enable a decoder to rapidly identify
 * that the data item is in CBOR encoding. This tag is intentionally placed at
 * a high tag number to minimize collision with other applications.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc8949#section-3.4.6
 */
final class CBORTag extends Tag implements Normalizable
{
    public static function getTagId(): int
    {
        return self::TAG_CBOR;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): Tag
    {
        [$ai, $data] = self::determineComponents(self::TAG_CBOR);

        return new self($ai, $data, $object);
    }

    /**
     * @return mixed|CBORObject|null
     */
    public function normalize()
    {
        return $this->object instanceof Normalizable ? $this->object->normalize() : $this->object;
    }
}

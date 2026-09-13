<?php

declare(strict_types=1);

namespace CBOR\Tag\TypedArray;

use CBOR\CBORObject;
use CBOR\Tag;

/**
 * The two factories every typed array tag shares.
 *
 * They live in a trait rather than in AbstractTypedArrayTag because they need "new static": in the abstract
 * class nothing rules out a subclass that takes different constructor arguments, whereas the final classes that
 * use the trait are the end of the line.
 */
trait TypedArrayFactoryTrait
{
    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new static($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): static
    {
        [$ai, $data] = self::determineComponents(static::getTagId());

        return new static($ai, $data, $object);
    }
}

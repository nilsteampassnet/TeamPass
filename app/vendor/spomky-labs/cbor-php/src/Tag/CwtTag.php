<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\IndefiniteLengthListObject;
use CBOR\IndefiniteLengthMapObject;
use CBOR\ListObject;
use CBOR\MapObject;
use CBOR\Normalizable;
use CBOR\Tag;
use InvalidArgumentException;

/**
 * Tag 61: a CBOR Web Token (RFC 8392).
 *
 * The tag says "what follows is a CWT" and nothing more; the token itself is a COSE structure -- tagged, as
 * CoseSign1Tag and its siblings, or untagged, in which case it is the bare array COSE defines -- or, for an
 * unsecured token, the claims set as a plain map. All three are accepted; none is verified here, since
 * validating a signature needs keys this library knows nothing about.
 *
 * @see \CBOR\Test\Tag\CoseTagTest
 * @see https://datatracker.ietf.org/doc/html/rfc8392#section-6
 */
final class CwtTag extends Tag implements Normalizable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof MapObject && ! $object instanceof IndefiniteLengthMapObject && ! $object instanceof ListObject && ! $object instanceof IndefiniteLengthListObject && ! $object instanceof TagInterface) {
            throw new InvalidArgumentException('This tag only accepts a Map object, a List object or a COSE tag.');
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_CWT;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_CWT);

        return new self($ai, $data, $object);
    }

    public function normalize(): mixed
    {
        $object = $this->object;

        return $object instanceof Normalizable ? $object->normalize() : $object;
    }
}

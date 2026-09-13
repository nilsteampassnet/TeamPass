<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\IndefiniteLengthTextStringObject;
use CBOR\Normalizable;
use CBOR\Tag;
use CBOR\TextStringObject;
use InvalidArgumentException;

/**
 * Tag 35: a regular expression.
 *
 * RFC 7049 defined the tag and RFC 8949 dropped it, but the IANA registry keeps it and documents still carry it.
 * The dialect -- PCRE, ECMA-262, POSIX -- is not part of the tag, so the pattern is carried as written and never
 * compiled here: handing an untrusted pattern to preg_match() is a decision for the application, not the decoder.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 */
final class RegexpTag extends Tag implements Normalizable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof TextStringObject && ! $object instanceof IndefiniteLengthTextStringObject) {
            throw new InvalidArgumentException('This tag only accepts a Text String object.');
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_REGULAR_EXPRESSION;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_REGULAR_EXPRESSION);

        return new self($ai, $data, $object);
    }

    public function normalize(): string
    {
        /** @var IndefiniteLengthTextStringObject|TextStringObject $object */
        $object = $this->object;

        return $object->normalize();
    }
}

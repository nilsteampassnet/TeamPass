<?php

declare(strict_types=1);

namespace CBOR\Tag;

use CBOR\CBORObject;
use CBOR\IndefiniteLengthListObject;
use CBOR\IndefiniteLengthTextStringObject;
use CBOR\ListObject;
use CBOR\Normalizable;
use CBOR\Tag;
use CBOR\TextStringObject;
use function count;
use InvalidArgumentException;

/**
 * Tag 38: a string together with the language it is written in, as the two element array [language, text].
 *
 * The language is a BCP 47 tag such as "fr-CA". It is carried as written rather than parsed: matching and
 * fallback are the application's business.
 *
 * @see \CBOR\Test\Tag\RegistryTagsTest
 */
final class LanguageTaggedStringTag extends Tag implements Normalizable
{
    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        if (! $object instanceof ListObject && ! $object instanceof IndefiniteLengthListObject) {
            throw new InvalidArgumentException('This tag only accepts a List object.');
        }
        if (count($object) !== 2) {
            throw new InvalidArgumentException('This tag only accepts a List object that contains 2 items.');
        }
        foreach ([$object->get(0), $object->get(1)] as $item) {
            if (! $item instanceof TextStringObject && ! $item instanceof IndefiniteLengthTextStringObject) {
                throw new InvalidArgumentException('This tag only accepts Text String objects.');
            }
        }

        parent::__construct($additionalInformation, $data, $object);
    }

    public static function getTagId(): int
    {
        return self::TAG_LANGUAGE_TAGGED_STRING;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): Tag
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(CBORObject $object): self
    {
        [$ai, $data] = self::determineComponents(self::TAG_LANGUAGE_TAGGED_STRING);

        return new self($ai, $data, $object);
    }

    public static function createFromLanguageAndText(string $language, string $text): self
    {
        return self::create(ListObject::create([
            TextStringObject::create($language),
            TextStringObject::create($text),
        ]));
    }

    public function getLanguage(): string
    {
        return $this->textAt(0);
    }

    public function getText(): string
    {
        return $this->textAt(1);
    }

    /**
     * @return array{0: string, 1: string} the language tag and the text, in the order they are encoded
     */
    public function normalize(): array
    {
        return [$this->getLanguage(), $this->getText()];
    }

    private function textAt(int $index): string
    {
        /** @var IndefiniteLengthListObject|ListObject $object */
        $object = $this->object;
        /** @var IndefiniteLengthTextStringObject|TextStringObject $item */
        $item = $object->get($index);

        return $item->normalize();
    }
}

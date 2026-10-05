<?php

declare(strict_types=1);

namespace CBOR\Tag;

use function array_key_exists;
use CBOR\CBORObject;
use CBOR\Utils;
use InvalidArgumentException;

final class TagManager implements TagManagerInterface
{
    /**
     * @var array<int, class-string<TagInterface>>
     */
    private array $classes = [];

    /**
     * @param class-string<TagInterface>[] $classes
     */
    public function __construct(array $classes = [])
    {
        foreach ($classes as $class) {
            $this->add($class);
        }
    }

    /**
     * @param array<class-string<TagInterface>> $classes
     */
    public static function create(array $classes = []): self
    {
        return new self($classes);
    }

    /**
     * @param class-string<TagInterface> $class
     */
    public function add(string $class): self
    {
        if ($class::getTagId() < 0) {
            throw new InvalidArgumentException('Invalid tag ID.');
        }
        $this->classes[$class::getTagId()] = $class;

        return $this;
    }

    /**
     * Registers a class under a tag number given up front, rather than asked of the class itself.
     *
     * add() has to load the class to call getTagId() on it. A manager that carries the whole IANA registry the
     * library implements would therefore load every one of those classes before a single byte is decoded, when
     * a document mentions two or three of them at most. Here the class is only named, and the autoloader is
     * left alone until the tag actually turns up in a document.
     *
     * The tag number is taken at face value: nothing checks that the class agrees with it, since checking is
     * exactly what would load the class.
     *
     * @param class-string<TagInterface> $class
     */
    public function register(int $tagId, string $class): self
    {
        if ($tagId < 0) {
            throw new InvalidArgumentException('Invalid tag ID.');
        }
        $this->classes[$tagId] = $class;

        return $this;
    }

    /**
     * @return class-string<TagInterface>
     */
    public function getClassForValue(int $value): string
    {
        return array_key_exists($value, $this->classes) ? $this->classes[$value] : GenericTag::class;
    }

    public function createObjectForValue(int $additionalInformation, ?string $data, CBORObject $object): TagInterface
    {
        $value = $additionalInformation;
        if ($additionalInformation >= 24) {
            Utils::assertString($data, 'Invalid data');
            $value = Utils::binToInt($data);
        }
        $class = $this->getClassForValue($value);

        return $class::createFromLoadedData($additionalInformation, $data, $object);
    }
}

<?php

declare(strict_types=1);

namespace CBOR;

use function array_key_exists;
use ArrayAccess;
use ArrayIterator;
use function count;
use Countable;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;

/**
 * @phpstan-implements ArrayAccess<int, CBORObject>
 * @phpstan-implements IteratorAggregate<int, MapItem>
 * @final
 */
class IndefiniteLengthMapObject extends AbstractCBORObject implements Countable, IteratorAggregate, Normalizable, ArrayAccess
{
    use MapKeyRegistryTrait;

    private const MAJOR_TYPE = self::MAJOR_TYPE_MAP;

    private const ADDITIONAL_INFORMATION = self::LENGTH_INDEFINITE;

    /**
     * @var MapItem[]
     */
    private array $data = [];

    public function __construct()
    {
        parent::__construct(self::MAJOR_TYPE, self::ADDITIONAL_INFORMATION);
    }

    public function __toString(): string
    {
        $result = parent::__toString();
        foreach ($this->data as $object) {
            $result .= (string) $object->getKey();
            $result .= (string) $object->getValue();
        }

        return $result . "\xFF";
    }

    public static function create(): self
    {
        return new self();
    }

    public function add(CBORObject $key, CBORObject $value): self
    {
        if (! $key instanceof Normalizable) {
            throw new InvalidArgumentException('Invalid key. Shall be normalizable');
        }
        $this->data[$this->registerKey($key, false)] = MapItem::create($key, $value);

        return $this;
    }

    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(int|string $index): self
    {
        if (! $this->has($index)) {
            return $this;
        }
        unset($this->data[$index]);
        $this->unregisterKey($index);

        return $this;
    }

    public function get(int|string $index): CBORObject
    {
        if (! $this->has($index)) {
            throw new InvalidArgumentException('Index not found.');
        }

        return $this->data[$index]->getValue();
    }

    public function set(MapItem $object): self
    {
        $this->data[$this->registerKey($object->getKey(), true)] = $object;

        return $this;
    }

    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @return Iterator<int, MapItem>
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->data);
    }

    /**
     * Items that do not implement Normalizable -- the encoding tags or the "break" simple value, for instance -- have
     * no native counterpart and are returned as the CBORObject they are.
     *
     * @return array<int|string, mixed>
     */
    public function normalize(): array
    {
        $normalized = [];
        foreach ($this->data as $item) {
            $valueObject = $item->getValue();
            $normalized[self::assertNormalizableToScalar(
                $item->getKey()
            )] = $valueObject instanceof Normalizable ? $valueObject->normalize() : $valueObject;
        }

        return $normalized;
    }

    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet($offset): CBORObject
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        if (! $offset instanceof CBORObject) {
            throw new InvalidArgumentException('Invalid key');
        }
        if (! $value instanceof CBORObject) {
            throw new InvalidArgumentException('Invalid value');
        }

        $this->set(MapItem::create($offset, $value));
    }

    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }
}

<?php

declare(strict_types=1);

namespace CBOR;

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
 */
final class MapObject extends AbstractCBORObject implements Countable, IteratorAggregate, Normalizable, ArrayAccess
{
    use MapKeyRegistryTrait;

    private const MAJOR_TYPE = self::MAJOR_TYPE_MAP;

    /**
     * @var MapItem[]
     */
    private array $data;

    private ?string $length;

    private bool $lengthStale = false;

    /**
     * @param MapItem[] $data
     */
    public function __construct(array $data = [])
    {
        $entries = $this->registerKeys($data);
        [$additionalInformation, $length] = LengthCalculator::getLengthOfArray($entries);
        parent::__construct(self::MAJOR_TYPE, $additionalInformation);
        $this->data = $entries;
        $this->length = $length;
    }

    public function __toString(): string
    {
        $this->refreshLength();
        $result = parent::__toString();
        $result .= $this->length ?? '';
        foreach ($this->data as $object) {
            $result .= (string) $object->getKey();
            $result .= (string) $object->getValue();
        }

        return $result;
    }

    public function getAdditionalInformation(): int
    {
        $this->refreshLength();

        return parent::getAdditionalInformation();
    }

    /**
     * The head carries the item count, so every insertion or removal invalidates it. Recomputing it there made the
     * cost of building a container quadratic in call count; it is only ever observed when the object is written out.
     */
    private function refreshLength(): void
    {
        if (! $this->lengthStale) {
            return;
        }

        [$this->additionalInformation, $this->length] = LengthCalculator::getLengthOfArray($this->data);
        $this->lengthStale = false;
    }

    /**
     * @param MapItem[] $data
     */
    public static function create(array $data = []): self
    {
        return new self($data);
    }

    public function add(CBORObject $key, CBORObject $value): self
    {
        $this->data[$this->registerKey($key, false)] = MapItem::create($key, $value);
        $this->lengthStale = true;

        return $this;
    }

    /**
     * Whether an entry is reachable at that offset: a key that is opaque, or that shares its offset with a key of
     * another major type, is only reached by iterating the map.
     */
    public function has(int|string $key): bool
    {
        return $this->isAddressable($this->data, $key);
    }

    public function remove(int|string $index): self
    {
        if (! $this->has($index)) {
            return $this;
        }
        unset($this->data[$index]);
        $this->unregisterKey($index);
        $this->lengthStale = true;

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
        $this->lengthStale = true;

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
     * A key has to become a PHP array offset, which only an integer or a string can. A map holding any other kind of
     * key -- a float, a boolean, null, a list, a map -- or two keys of different major types that resolve to the
     * same offset, decodes and iterates, but cannot be normalized.
     *
     * @return array<int|string, mixed>
     */
    public function normalize(): array
    {
        return $this->normalizeEntries($this->data);
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

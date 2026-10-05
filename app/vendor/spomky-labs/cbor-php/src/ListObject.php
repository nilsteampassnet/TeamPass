<?php

declare(strict_types=1);

namespace CBOR;

use function array_key_exists;
use ArrayAccess;
use ArrayIterator;
use function count;
use Countable;
use function get_debug_type;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use function sprintf;

/**
 * @phpstan-implements ArrayAccess<int, CBORObject>
 * @phpstan-implements IteratorAggregate<int, CBORObject>
 * @see \CBOR\Test\ListObjectTest
 */
class ListObject extends AbstractCBORObject implements Countable, IteratorAggregate, Normalizable, ArrayAccess
{
    private const MAJOR_TYPE = self::MAJOR_TYPE_LIST;

    /**
     * @var array<int, CBORObject>
     */
    private array $data;

    private ?string $length;

    private bool $lengthStale = false;

    /**
     * @param CBORObject[] $data
     */
    public function __construct(array $data = [])
    {
        [$additionalInformation, $length] = LengthCalculator::getLengthOfArray($data);
        foreach ($data as $index => $item) {
            if (! $item instanceof CBORObject) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid item at index "%s". Expected a CBORObject, got "%s".',
                    $index,
                    get_debug_type($item)
                ));
            }
        }

        parent::__construct(self::MAJOR_TYPE, $additionalInformation);
        $this->data = array_values($data);
        $this->length = $length;
    }

    public function __toString(): string
    {
        $this->refreshLength();
        $result = parent::__toString();
        $result .= $this->length ?? '';
        foreach ($this->data as $object) {
            $result .= (string) $object;
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
     * @param CBORObject[] $data
     */
    public static function create(array $data = []): self
    {
        return new self($data);
    }

    public function add(CBORObject $object): self
    {
        $this->data[] = $object;
        $this->lengthStale = true;

        return $this;
    }

    public function has(int $index): bool
    {
        return array_key_exists($index, $this->data);
    }

    public function remove(int $index): self
    {
        if (! $this->has($index)) {
            return $this;
        }
        unset($this->data[$index]);
        $this->data = array_values($this->data);
        $this->lengthStale = true;

        return $this;
    }

    public function get(int $index): CBORObject
    {
        if (! $this->has($index)) {
            throw new InvalidArgumentException('Index not found.');
        }

        return $this->data[$index];
    }

    public function set(int $index, CBORObject $object): self
    {
        if (! $this->has($index)) {
            throw new InvalidArgumentException('Index not found.');
        }

        $this->data[$index] = $object;
        $this->lengthStale = true;

        return $this;
    }

    /**
     * Items that do not implement Normalizable -- the encoding tags or the "break" simple value, for instance -- have
     * no native counterpart and are returned as the CBORObject they are.
     *
     * @return array<int, mixed>
     */
    public function normalize(): array
    {
        return array_map(
            static fn (CBORObject $object) => $object instanceof Normalizable ? $object->normalize() : $object,
            $this->data
        );
    }

    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @return Iterator<int, CBORObject>
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->data);
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
        if ($offset === null) {
            $this->add($value);

            return;
        }

        $this->set($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }
}

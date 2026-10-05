<?php

declare(strict_types=1);

namespace Webauthn\AuthenticationExtensions;

use function array_key_exists;
use ArrayAccess;
use ArrayIterator;
use function count;
use const COUNT_NORMAL;
use Countable;
use function is_string;
use Iterator;
use IteratorAggregate;
use function sprintf;
use Webauthn\Exception\AuthenticationExtensionException;

/**
 * @implements IteratorAggregate<string, AuthenticationExtension>
 * @implements ArrayAccess<string, AuthenticationExtension>
 */
final class AuthenticationExtensions implements Countable, IteratorAggregate, ArrayAccess
{
    /**
     * @var array<string, AuthenticationExtension>
     */
    public array $extensions;

    /**
     * @param array<array-key, mixed|AuthenticationExtension> $extensions
     */
    public function __construct(array $extensions = [])
    {
        $list = [];
        foreach ($extensions as $key => $extension) {
            if ($extension instanceof AuthenticationExtension) {
                $list[$extension->name] = $extension;

                continue;
            }
            if (is_string($key)) {
                $list[$key] = AuthenticationExtension::create($key, $extension);
                continue;
            }
            throw new AuthenticationExtensionException('Invalid extension');
        }
        $this->extensions = $list;
    }

    /**
     * @param array<array-key, AuthenticationExtension> $extensions
     */
    public static function create(array $extensions = []): static
    {
        return new static($extensions);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->extensions);
    }

    public function get(string $key): AuthenticationExtension
    {
        $this->has($key) || throw AuthenticationExtensionException::create(sprintf(
            'The extension with key "%s" is not available',
            $key
        ));

        return $this->extensions[$key];
    }

    /**
     * @return Iterator<string, AuthenticationExtension>
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->extensions);
    }

    /**
     * @param 0|1 $mode
     */
    public function count(int $mode = COUNT_NORMAL): int
    {
        return count($this->extensions, $mode);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->extensions);
    }

    public function offsetGet(mixed $offset): AuthenticationExtension
    {
        return $this->extensions[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($value === null) {
            return;
        }
        if ($value instanceof AuthenticationExtension) {
            $this->extensions[$value->name] = $value;
            return;
        }
        if (is_string($offset)) {
            $this->extensions[$offset] = AuthenticationExtension::create($offset, $value);
            return;
        }
        throw new AuthenticationExtensionException('Invalid extension');
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->extensions[$offset]);
    }
}

<?php

declare(strict_types=1);

namespace Cose\Algorithm;

use function array_key_exists;
use const E_USER_WARNING;
use InvalidArgumentException;
use function sprintf;
use function trigger_error;

/**
 * @see \Cose\Tests\Algorithm\ManagerFactoryTest
 */
final class ManagerFactory
{
    /**
     * @var array<string, Algorithm>
     */
    private array $algorithms = [];

    public static function create(): self
    {
        return new self();
    }

    /**
     * Registers an algorithm under an alias.
     *
     * A later registration for an alias that is already taken replaces the earlier one. When the replacement is an
     * instance of another class, that is a misconfiguration rather than an intent, so it emits an E_USER_WARNING; as
     * of v5.0.0 it will throw an InvalidArgumentException. Re-registering the same class under the same alias stays
     * silent.
     *
     * Two aliases may legitimately point at the same algorithm, but two aliases whose algorithms share an identifier
     * collide as soon as generate() is given both: the Manager it builds keeps only one of them, and warns about the
     * replacement.
     */
    public function add(string $alias, Algorithm $algorithm): self
    {
        $registered = $this->algorithms[$alias] ?? null;
        if ($registered !== null && $registered::class !== $algorithm::class) {
            trigger_error(sprintf(
                'The alias "%s" is already registered with "%s" and is being replaced by "%s". As of v5.0.0, this will throw an exception.',
                $alias,
                $registered::class,
                $algorithm::class
            ), E_USER_WARNING);
        }
        $this->algorithms[$alias] = $algorithm;

        return $this;
    }

    /**
     * @return string[]
     */
    public function list(): iterable
    {
        yield from array_keys($this->algorithms);
    }

    /**
     * @return Algorithm[]
     */
    public function all(): iterable
    {
        yield from $this->algorithms;
    }

    public function generate(string ...$aliases): Manager
    {
        $manager = Manager::create();
        foreach ($aliases as $alias) {
            if (! array_key_exists($alias, $this->algorithms)) {
                throw new InvalidArgumentException(sprintf('The algorithm with alias "%s" is not supported', $alias));
            }
            $manager->add($this->algorithms[$alias]);
        }

        return $manager;
    }
}

<?php

declare(strict_types=1);

namespace Cose\Algorithm;

use function array_key_exists;
use const E_USER_WARNING;
use InvalidArgumentException;
use function sprintf;
use function trigger_error;

/**
 * @see \Cose\Tests\Algorithm\ManagerTest
 */
final class Manager
{
    /**
     * @var array<int, Algorithm>
     */
    private array $algorithms = [];

    public static function create(): self
    {
        return new self();
    }

    /**
     * Registers algorithms, each under the identifier it declares.
     *
     * A later registration for an identifier that is already taken replaces the earlier one. When the replacement is
     * an instance of another class, that is a misconfiguration rather than an intent: nothing in the Algorithm
     * contract reserves an identifier, list() keeps reporting one entry, and the verifier that ends up answering for
     * the identifier is decided by registration order alone - which, for the Symfony bundle, is decided by the
     * service container. Such a replacement therefore emits an E_USER_WARNING; as of v5.0.0 it will throw an
     * InvalidArgumentException, and an explicit replace() will be the way to override a registration on purpose.
     *
     * Re-registering the same class stays silent, so that a container that autoconfigures the same algorithm twice
     * keeps working.
     */
    public function add(Algorithm ...$algorithms): self
    {
        foreach ($algorithms as $algorithm) {
            $identifier = $algorithm::identifier();
            $registered = $this->algorithms[$identifier] ?? null;
            if ($registered !== null && $registered::class !== $algorithm::class) {
                trigger_error(sprintf(
                    'The algorithm identifier %d is already registered with "%s" and is being replaced by "%s". As of v5.0.0, this will throw an exception.',
                    $identifier,
                    $registered::class,
                    $algorithm::class
                ), E_USER_WARNING);
            }
            $this->algorithms[$identifier] = $algorithm;
        }

        return $this;
    }

    /**
     * @return iterable<int>
     */
    public function list(): iterable
    {
        yield from array_keys($this->algorithms);
    }

    /**
     * @return iterable<int, Algorithm>
     */
    public function all(): iterable
    {
        yield from $this->algorithms;
    }

    /**
     * Returns the same set of algorithms, each enforcing - or no longer enforcing - the "alg" and "key_ops"
     * restrictions of the keys it is given, as RFC 9052, section 7.1 requires. Algorithms that cannot enforce them
     * are carried over unchanged. This manager is left untouched.
     *
     * @see KeyRestrictionAware
     */
    public function withKeyRestrictionsEnforced(bool $enforce = true): self
    {
        $manager = self::create();
        foreach ($this->algorithms as $algorithm) {
            $manager->add(
                $algorithm instanceof KeyRestrictionAware
                    ? $algorithm->withKeyRestrictionsEnforced($enforce)
                    : $algorithm
            );
        }

        return $manager;
    }

    public function has(int $identifier): bool
    {
        return array_key_exists($identifier, $this->algorithms);
    }

    public function get(int $identifier): Algorithm
    {
        if (! $this->has($identifier)) {
            throw new InvalidArgumentException('Unsupported algorithm');
        }

        return $this->algorithms[$identifier];
    }
}

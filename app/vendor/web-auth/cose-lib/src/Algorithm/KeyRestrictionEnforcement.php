<?php

declare(strict_types=1);

namespace Cose\Algorithm;

use Cose\Key\Key;
use InvalidArgumentException;

/**
 * The opt-in enforcement of the key restrictions of RFC 9052, section 7.1, shared by every algorithm.
 *
 * @see KeyRestrictionAware
 *
 * @phpstan-require-implements KeyRestrictionAware
 */
trait KeyRestrictionEnforcement
{
    private bool $enforceKeyRestrictions = false;

    public function withKeyRestrictionsEnforced(bool $enforce = true): static
    {
        $clone = clone $this;
        $clone->enforceKeyRestrictions = $enforce;

        return $clone;
    }

    public function enforcesKeyRestrictions(): bool
    {
        return $this->enforceKeyRestrictions;
    }

    /**
     * @param int $operation one of the `Key::OP_*` constants
     *
     * @throws InvalidArgumentException when enforcement is on and the key is restricted to another algorithm, or
     *                                  does not allow the operation
     */
    private function checkKeyRestrictions(Key $key, int $operation): void
    {
        if (! $this->enforceKeyRestrictions) {
            return;
        }

        // static::identifier() is the identifier of the concrete algorithm, so ES256 and ESP256, or EdDSA (-8) and
        // the fully-specified Ed25519 (-19), each enforce their own value.
        $key->assertUsableWith(static::identifier(), $operation);
    }
}

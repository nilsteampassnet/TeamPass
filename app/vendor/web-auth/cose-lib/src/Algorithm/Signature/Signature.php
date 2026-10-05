<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature;

use Cose\Algorithm\Algorithm;
use Cose\Key\Key;
use InvalidArgumentException;

interface Signature extends Algorithm
{
    /**
     * @throws InvalidArgumentException when the key cannot be used to sign with this algorithm (wrong key type, wrong
     *                                  curve, public key, key material the crypto layer refuses, or - when the
     *                                  algorithm enforces them, see KeyRestrictionAware - an "alg" or a "key_ops"
     *                                  that forbids signing with it)
     */
    public function sign(string $data, Key $key): string;

    /**
     * Verification is total for every condition the underlying specification defines as an "invalid signature"
     * outcome: a malformed, truncated, over-long or out-of-range signature, and key material no verification can be
     * performed with (an off-curve point, a public key that is not a valid group element, an RSA key whose public
     * parameters are not those RFC 8017 defines, …) all yield false.
     *
     * @throws InvalidArgumentException when $key cannot be used with this algorithm at all, i.e. its key type or its
     *                                  curve does not match, or - when the algorithm enforces them, see
     *                                  KeyRestrictionAware - its "alg" or its "key_ops" forbids the verification.
     *                                  Structurally invalid key components are rejected earlier, by the Key
     *                                  constructors.
     */
    public function verify(string $data, Key $key, string $signature): bool;
}

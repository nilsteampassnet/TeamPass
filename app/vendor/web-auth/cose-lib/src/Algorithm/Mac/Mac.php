<?php

declare(strict_types=1);

namespace Cose\Algorithm\Mac;

use Cose\Algorithm\Algorithm;
use Cose\Key\Key;
use InvalidArgumentException;

interface Mac extends Algorithm
{
    /**
     * Computes the authentication tag of the data, the "MAC create" operation of RFC 9052, section 7.1, Table 5.
     *
     * @throws InvalidArgumentException when the key cannot be used with this algorithm (wrong key type, missing key
     *                                  value, or - when the algorithm enforces them, see KeyRestrictionAware - an
     *                                  "alg" or a "key_ops" that forbids creating a tag with it)
     */
    public function hash(string $data, Key $key): string;

    /**
     * Verifies an authentication tag, the "MAC verify" operation of RFC 9052, section 7.1, Table 5. It is a distinct
     * operation from the one above: a key may be allowed to perform one and not the other.
     *
     * @throws InvalidArgumentException when the key cannot be used with this algorithm, as above
     */
    public function verify(string $data, Key $key, string $signature): bool;
}

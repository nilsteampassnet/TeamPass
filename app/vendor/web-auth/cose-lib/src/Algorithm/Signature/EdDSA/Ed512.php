<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature\EdDSA;

use Cose\Key\Key;
use const E_USER_WARNING;
use function hash;
use function trigger_error;

/**
 * Pure Ed25519 over the SHA-512 digest of the payload.
 *
 * Despite its name this is not EdDSA with Curve448, and it rejects an Ed448 key: the signature is computed by
 * {@see EdDSA}, whose curve check accepts Ed25519 only. EdDSA with Curve448, RFC 8032 section 5.2, is
 * {@see \Cose\Algorithm\Signature\FullySpecified\Ed448} (-53).
 *
 * The construction is defined by no specification. It is neither the EdDSA of RFC 8032, section 5.1 - which signs the
 * message itself - nor Ed25519ph of the same section, whose challenge covers a "dom2" prefix this class does not add.
 * RFC 9053, section 2.2 is explicit that "for use with COSE, only the pure EdDSA version is used", and RFC 8032,
 * section 8.5 warns that prehashing "makes the functions greatly more vulnerable to weaknesses in hash functions
 * used. These variants SHOULD NOT be used."
 *
 * Worse, the identifier it carries is not free: IANA has since assigned -261 to TurboSHAKE128 (RFC 9861, section 6),
 * so a COSE object signed here is read as TurboSHAKE128 by every conforming implementation, and a genuine
 * TurboSHAKE128 object is routed to this class. No WebAuthn authenticator emits -261, so the only deployments this
 * construction can serve are those that use it on both ends.
 *
 * Because of that, creating this algorithm emits an E_USER_WARNING unless the caller explicitly acknowledges what it
 * is by passing `acknowledgeNonStandardAlgorithm: true`. From v5.0.0 the same call without that acknowledgement will
 * throw an InvalidArgumentException instead of warning, and the identifier will move out of the range IANA
 * administers.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9861#section-6
 * @see https://www.rfc-editor.org/rfc/rfc8032#section-8.5
 * @see \Cose\Tests\Algorithm\Signature\EdDSA\PrehashedEdDSATest
 */
final class Ed512 extends EdDSA
{
    public const ID = -261;

    public const NON_STANDARD_ALGORITHM_MESSAGE = 'The algorithm Ed512 (-261) signs the SHA-512 digest of the payload with pure Ed25519, a construction no specification defines, and its identifier is assigned by IANA to TurboSHAKE128. If you know what you are doing, create it with "acknowledgeNonStandardAlgorithm: true"; as of v5.0.0, omitting that acknowledgement will throw an exception.';

    public function __construct(bool $acknowledgeNonStandardAlgorithm = false)
    {
        parent::__construct();
        if (! $acknowledgeNonStandardAlgorithm) {
            trigger_error(self::NON_STANDARD_ALGORITHM_MESSAGE, E_USER_WARNING);
        }
    }

    public static function create(bool $acknowledgeNonStandardAlgorithm = false): self
    {
        return new self($acknowledgeNonStandardAlgorithm);
    }

    public static function identifier(): int
    {
        return self::ID;
    }

    public function sign(string $data, Key $key): string
    {
        $hashedData = hash('sha512', $data, true);

        return parent::sign($hashedData, $key);
    }

    public function verify(string $data, Key $key, string $signature): bool
    {
        $hashedData = hash('sha512', $data, true);

        return parent::verify($hashedData, $key, $signature);
    }
}

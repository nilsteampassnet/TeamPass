<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature\RSA;

use Cose\Key\RsaKeyValidator;
use const E_USER_WARNING;
use const OPENSSL_ALGO_SHA1;
use function trigger_error;

/**
 * RSASSA-PKCS1-v1_5 using SHA-1.
 *
 * SHA-1 is no longer acceptable for digital signatures: NIST has recommended against generating signatures with it
 * since the end of 2010 and forbidden its use by U.S. federal agencies for sensitive but unclassified information
 * since the end of 2013 (see RFC 6194 and NIST SP 800-131A). The algorithm is kept for the legacy authenticators
 * that still rely on it.
 *
 * Because of that, creating this algorithm emits an E_USER_WARNING unless the caller explicitly acknowledges the risk
 * by passing `acknowledgeInsecureAlgorithm: true`. From v5.0.0 the same call without that acknowledgement will throw
 * an InvalidArgumentException instead of warning.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6194
 */
final class RS1 extends RSA
{
    public const ID = -65535;

    public const INSECURE_ALGORITHM_MESSAGE = 'The algorithm RS1 (RSASSA-PKCS1-v1_5 with SHA-1) is not secure and shall only be used to verify signatures produced by legacy authenticators. If you know what you are doing, create it with "acknowledgeInsecureAlgorithm: true"; as of v5.0.0, omitting that acknowledgement will throw an exception.';

    public function __construct(bool $acknowledgeInsecureAlgorithm = false, ?RsaKeyValidator $keyValidator = null)
    {
        parent::__construct($keyValidator);
        if (! $acknowledgeInsecureAlgorithm) {
            trigger_error(self::INSECURE_ALGORITHM_MESSAGE, E_USER_WARNING);
        }
    }

    public static function create(
        bool $acknowledgeInsecureAlgorithm = false,
        ?RsaKeyValidator $keyValidator = null
    ): self {
        return new self($acknowledgeInsecureAlgorithm, $keyValidator);
    }

    public static function identifier(): int
    {
        return self::ID;
    }

    protected function getHashAlgorithm(): int
    {
        return OPENSSL_ALGO_SHA1;
    }
}

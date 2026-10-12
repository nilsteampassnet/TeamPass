<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\CertificationPath\PathValidation;

use Brick\Math\BigInteger;
use function in_array;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\AlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Feature\SignatureAlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\PublicKeyInfo;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\RSA\RSAPublicKey;

/**
 * The parts of a validation policy that apply to any signature, wherever it is verified.
 *
 * Both the certification path validation and the attribute certificate validation answer the same two questions
 * before trusting a signature: is the algorithm one the caller accepts, and is the key that made it large enough to
 * be worth verifying. They are asked here so that a single implementation answers them.
 *
 * @internal
 */
final class SignaturePolicy
{
    /**
     * Whether the algorithm is one the caller accepts.
     *
     * A signature is only as good as its digest. Without this check MD5 is accepted, against which chosen prefix
     * collisions are practical, and the application has no way to say no.
     *
     * @param list<string>|string[] $allowed Accepted algorithm OID's
     */
    public static function isAlgorithmAllowed(SignatureAlgorithmIdentifier $algo, array $allowed): bool
    {
        return in_array($algo->oid(), $allowed, true);
    }

    /**
     * Size of the RSA modulus when it falls below the minimum, null when the key is acceptable.
     *
     * Only RSA is covered: an EC key's strength is fixed by its named curve, and the curves this library knows are
     * all above the line. A modulus below the floor makes the signature meaningless however well OpenSSL verifies
     * it.
     *
     * @param int $minimum Minimum modulus size in bits, zero to disable the check
     */
    public static function rsaKeySizeBelowMinimum(PublicKeyInfo $pubkey_info, int $minimum): ?int
    {
        if ($minimum === 0) {
            return null;
        }
        if ($pubkey_info->algorithmIdentifier()->oid() !== AlgorithmIdentifier::OID_RSA_ENCRYPTION) {
            return null;
        }
        $pubkey = $pubkey_info->publicKey();
        if (! $pubkey instanceof RSAPublicKey) {
            return null;
        }
        $bits = BigInteger::of($pubkey->modulus())->getBitLength();

        return $bits < $minimum ? $bits : null;
    }
}

<?php

declare(strict_types=1);

namespace Cose;

use function array_key_exists;
use Cose\Algorithm\Signature\RSA\RS1;
use const E_USER_WARNING;
use InvalidArgumentException;
use const OPENSSL_ALGO_SHA1;
use const OPENSSL_ALGO_SHA256;
use const OPENSSL_ALGO_SHA384;
use const OPENSSL_ALGO_SHA512;
use function trigger_error;

/**
 * @see https://www.iana.org/assignments/cose/cose.xhtml#algorithms
 */
abstract class Algorithms
{
    final public const COSE_ALGORITHM_AES_CCM_64_128_256 = 33;

    final public const COSE_ALGORITHM_AES_CCM_64_128_128 = 32;

    final public const COSE_ALGORITHM_AES_CCM_16_128_256 = 31;

    final public const COSE_ALGORITHM_AES_CCM_16_128_128 = 30;

    final public const COSE_ALGORITHM_AES_MAC_256_128 = 26;

    final public const COSE_ALGORITHM_AES_MAC_128_128 = 25;

    final public const COSE_ALGORITHM_CHACHA20_POLY1305 = 24;

    final public const COSE_ALGORITHM_AES_MAC_256_64 = 15;

    final public const COSE_ALGORITHM_AES_MAC_128_64 = 14;

    final public const COSE_ALGORITHM_AES_CCM_64_64_256 = 13;

    final public const COSE_ALGORITHM_AES_CCM_64_64_128 = 12;

    final public const COSE_ALGORITHM_AES_CCM_16_64_256 = 11;

    final public const COSE_ALGORITHM_AES_CCM_16_64_128 = 10;

    final public const COSE_ALGORITHM_HS512 = 7;

    final public const COSE_ALGORITHM_HS384 = 6;

    final public const COSE_ALGORITHM_HS256 = 5;

    final public const COSE_ALGORITHM_HS256_64 = 4;

    final public const COSE_ALGORITHM_A256GCM = 3;

    final public const COSE_ALGORITHM_A192GCM = 2;

    final public const COSE_ALGORITHM_A128GCM = 1;

    final public const COSE_ALGORITHM_A128KW = -3;

    final public const COSE_ALGORITHM_A192KW = -4;

    final public const COSE_ALGORITHM_A256KW = -5;

    final public const COSE_ALGORITHM_DIRECT = -6;

    final public const COSE_ALGORITHM_ES256 = -7;

    /**
     * @deprecated since v4.0.6. Please use COSE_ALGORITHM_EDDSA instead. Will be removed in v5.0.0
     */
    final public const COSE_ALGORITHM_EdDSA = -8;

    final public const COSE_ALGORITHM_EDDSA = -8;

    /**
     * Ed25519 over a SHA-256 (-260) or SHA-512 (-261) digest of the message. These pre-hash variants are not
     * registered anywhere and are not EdDSA identifiers: the IANA COSE Algorithms registry assigns -260 to WalnutDSA
     * and -261 to TurboSHAKE128. They are kept for the authenticators that already produce them; new code should use
     * COSE_ALGORITHM_ED25519 (-19) or COSE_ALGORITHM_ED448 (-53).
     */
    final public const COSE_ALGORITHM_ED256 = -260;

    final public const COSE_ALGORITHM_ED512 = -261;

    final public const COSE_ALGORITHM_DIRECT_HKDF_SHA_256 = -10;

    final public const COSE_ALGORITHM_DIRECT_HKDF_SHA_512 = -11;

    final public const COSE_ALGORITHM_DIRECT_HKDF_AES_128 = -12;

    final public const COSE_ALGORITHM_DIRECT_HKDF_AES_256 = -13;

    final public const COSE_ALGORITHM_ECDH_ES_HKDF_256 = -25;

    final public const COSE_ALGORITHM_ECDH_ES_HKDF_512 = -26;

    final public const COSE_ALGORITHM_ECDH_SS_HKDF_256 = -27;

    final public const COSE_ALGORITHM_ECDH_SS_HKDF_512 = -28;

    final public const COSE_ALGORITHM_ECDH_ES_A128KW = -29;

    final public const COSE_ALGORITHM_ECDH_ES_A192KW = -30;

    final public const COSE_ALGORITHM_ECDH_ES_A256KW = -31;

    final public const COSE_ALGORITHM_ECDH_SS_A128KW = -32;

    final public const COSE_ALGORITHM_ECDH_SS_A192KW = -33;

    final public const COSE_ALGORITHM_ECDH_SS_A256KW = -34;

    final public const COSE_ALGORITHM_ES384 = -35;

    final public const COSE_ALGORITHM_ES512 = -36;

    final public const COSE_ALGORITHM_PS256 = -37;

    final public const COSE_ALGORITHM_PS384 = -38;

    final public const COSE_ALGORITHM_PS512 = -39;

    final public const COSE_ALGORITHM_RSAES_OAEP = -40;

    final public const COSE_ALGORITHM_RSAES_OAEP_256 = -41;

    final public const COSE_ALGORITHM_RSAES_OAEP_512 = -42;

    final public const COSE_ALGORITHM_ES256K = -47;

    final public const COSE_ALGORITHM_RS256 = -257;

    final public const COSE_ALGORITHM_RS384 = -258;

    final public const COSE_ALGORITHM_RS512 = -259;

    final public const COSE_ALGORITHM_RS1 = -65535;

    /**
     * Fully-specified algorithm identifiers: unlike the polymorphic identifiers above, these determine the curve and
     * the hash on their own instead of leaving them to the other parameters of the key.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9864.html
     */
    final public const COSE_ALGORITHM_ESP256 = -9;

    final public const COSE_ALGORITHM_ESP384 = -51;

    final public const COSE_ALGORITHM_ESP512 = -52;

    final public const COSE_ALGORITHM_ESB256 = -265;

    final public const COSE_ALGORITHM_ESB320 = -266;

    final public const COSE_ALGORITHM_ESB384 = -267;

    final public const COSE_ALGORITHM_ESB512 = -268;

    final public const COSE_ALGORITHM_ED25519 = -19;

    final public const COSE_ALGORITHM_ED448 = -53;

    /**
     * The digest to hand to openssl_sign() / openssl_verify() for the ECDSA and RSASSA-PKCS1-v1_5 algorithms, and
     * for those only.
     *
     * openssl_verify() called with an OPENSSL_ALGO_* digest implies PKCS #1 v1.5 padding, so PS256, PS384 and PS512
     * (RSASSA-PSS) are deliberately absent, and so are EdDSA (-8), Ed25519 (-19) and Ed448 (-53), which are one-shot
     * schemes that hash the message themselves. Verifying with one of those identifiers goes through the matching
     * Cose\Algorithm\Signature\Signature class instead - Cose\Algorithm\Signature\CertificateSignatureVerifier
     * does exactly that for a signature made by the key of an X.509 certificate.
     *
     * @internal this constant bypasses getOpensslAlgorithmFor() and the acknowledgement it requires for RS1; it will
     * become private in the next major version
     * @var array<int, int>
     */
    final public const COSE_ALGORITHM_MAP = [
        self::COSE_ALGORITHM_ES256 => OPENSSL_ALGO_SHA256,
        self::COSE_ALGORITHM_ES256K => OPENSSL_ALGO_SHA256,
        self::COSE_ALGORITHM_ES384 => OPENSSL_ALGO_SHA384,
        self::COSE_ALGORITHM_ES512 => OPENSSL_ALGO_SHA512,
        self::COSE_ALGORITHM_ESP256 => OPENSSL_ALGO_SHA256,
        self::COSE_ALGORITHM_ESP384 => OPENSSL_ALGO_SHA384,
        self::COSE_ALGORITHM_ESP512 => OPENSSL_ALGO_SHA512,
        self::COSE_ALGORITHM_ESB256 => OPENSSL_ALGO_SHA256,
        self::COSE_ALGORITHM_ESB320 => OPENSSL_ALGO_SHA384,
        self::COSE_ALGORITHM_ESB384 => OPENSSL_ALGO_SHA384,
        self::COSE_ALGORITHM_ESB512 => OPENSSL_ALGO_SHA512,
        self::COSE_ALGORITHM_RS256 => OPENSSL_ALGO_SHA256,
        self::COSE_ALGORITHM_RS384 => OPENSSL_ALGO_SHA384,
        self::COSE_ALGORITHM_RS512 => OPENSSL_ALGO_SHA512,
        self::COSE_ALGORITHM_RS1 => OPENSSL_ALGO_SHA1,
    ];

    /**
     * The name of the digest each algorithm identifier uses, as hash() spells it.
     *
     * The EdDSA identifiers (-8, -19, -53) are absent: they name one-shot signature schemes with no separately
     * applicable digest.
     *
     * @internal this constant bypasses getHashAlgorithmFor() and the acknowledgement it requires for RS1; it will
     * become private in the next major version
     * @var array<int, string>
     */
    final public const COSE_HASH_MAP = [
        self::COSE_ALGORITHM_ES256K => 'sha256',
        self::COSE_ALGORITHM_ES256 => 'sha256',
        self::COSE_ALGORITHM_ES384 => 'sha384',
        self::COSE_ALGORITHM_ES512 => 'sha512',
        self::COSE_ALGORITHM_ESP256 => 'sha256',
        self::COSE_ALGORITHM_ESP384 => 'sha384',
        self::COSE_ALGORITHM_ESP512 => 'sha512',
        self::COSE_ALGORITHM_ESB256 => 'sha256',
        self::COSE_ALGORITHM_ESB320 => 'sha384',
        self::COSE_ALGORITHM_ESB384 => 'sha384',
        self::COSE_ALGORITHM_ESB512 => 'sha512',
        self::COSE_ALGORITHM_RS256 => 'sha256',
        self::COSE_ALGORITHM_RS384 => 'sha384',
        self::COSE_ALGORITHM_RS512 => 'sha512',
        self::COSE_ALGORITHM_PS256 => 'sha256',
        self::COSE_ALGORITHM_PS384 => 'sha384',
        self::COSE_ALGORITHM_PS512 => 'sha512',
        self::COSE_ALGORITHM_RS1 => 'sha1',
    ];

    /**
     * The digest to hand to openssl_sign() / openssl_verify() for an ECDSA or RSASSA-PKCS1-v1_5 identifier.
     *
     * @see self::COSE_ALGORITHM_MAP for the identifiers this covers, and for those it deliberately does not
     *
     * @param bool $acknowledgeInsecureAlgorithm see Cose\Algorithm\Signature\RSA\RS1: SHA-1 is only handed out
     * against an explicit acknowledgement, as creating the RS1 algorithm itself is
     *
     * @throws InvalidArgumentException when the identifier is not one this map describes
     */
    public static function getOpensslAlgorithmFor(
        int $algorithmIdentifier,
        bool $acknowledgeInsecureAlgorithm = false
    ): int {
        if (! array_key_exists($algorithmIdentifier, self::COSE_ALGORITHM_MAP)) {
            throw new InvalidArgumentException('The specified algorithm identifier is not supported');
        }
        self::checkInsecureAlgorithm($algorithmIdentifier, $acknowledgeInsecureAlgorithm);

        return self::COSE_ALGORITHM_MAP[$algorithmIdentifier];
    }

    /**
     * The name of the digest an identifier uses, as hash() spells it.
     *
     * @see self::COSE_HASH_MAP for the identifiers this covers, and for those it deliberately does not
     *
     * @param bool $acknowledgeInsecureAlgorithm see Cose\Algorithm\Signature\RSA\RS1: SHA-1 is only handed out
     * against an explicit acknowledgement, as creating the RS1 algorithm itself is
     *
     * @throws InvalidArgumentException when the identifier is not one this map describes
     */
    public static function getHashAlgorithmFor(
        int $algorithmIdentifier,
        bool $acknowledgeInsecureAlgorithm = false
    ): string {
        if (! array_key_exists($algorithmIdentifier, self::COSE_HASH_MAP)) {
            throw new InvalidArgumentException('The specified algorithm identifier is not supported');
        }
        self::checkInsecureAlgorithm($algorithmIdentifier, $acknowledgeInsecureAlgorithm);

        return self::COSE_HASH_MAP[$algorithmIdentifier];
    }

    /**
     * The RS1 class refuses to be created without an acknowledgement, but the two accessors above expose the very
     * same primitive - SHA-1 with PKCS #1 v1.5 padding - without any object ever being built, and their result is
     * handed straight to openssl_verify(). The policy therefore has to hold here too, otherwise the operator's
     * decision not to register RS1 has no effect on the code paths that go through these maps.
     *
     * Unlike RS1::__construct(), this fires per verification rather than once at configuration time, which is
     * precisely what makes an unacknowledged SHA-1 verification visible in the logs.
     */
    private static function checkInsecureAlgorithm(int $algorithmIdentifier, bool $acknowledgeInsecureAlgorithm): void
    {
        if ($algorithmIdentifier === self::COSE_ALGORITHM_RS1 && ! $acknowledgeInsecureAlgorithm) {
            // As of v5.0.0, this will throw an InvalidArgumentException instead of warning.
            trigger_error(RS1::INSECURE_ALGORITHM_MESSAGE, E_USER_WARNING);
        }
    }
}

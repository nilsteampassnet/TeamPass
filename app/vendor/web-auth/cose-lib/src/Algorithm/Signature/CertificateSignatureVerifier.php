<?php

declare(strict_types=1);

namespace Cose\Algorithm\Signature;

use Cose\Algorithm\Manager;
use Cose\Key\Key;
use Cose\Key\PublicKeyLoader;
use InvalidArgumentException;
use function sprintf;

/**
 * Verifies a signature made by the public key of an X.509 certificate, with the algorithm a COSE identifier names.
 *
 * WebAuthn Level 3 sections 8.2 to 8.4 ask a relying party to verify a packed (x5c), TPM or android-key attestation
 * statement "with the algorithm specified in alg", against the key of the attestation certificate. The obvious route,
 * openssl_verify() with Algorithms::getOpensslAlgorithmFor(), only reaches the algorithms an OPENSSL_ALGO_* digest can
 * describe - ECDSA and RSASSA-PKCS1-v1_5 - because that digest implies PKCS #1 v1.5 padding; ES256K is missing from
 * the map altogether, and RSASSA-PSS, EdDSA, Ed25519 and Ed448 cannot be expressed by it at all.
 *
 * This class takes the other route: the certificate's key becomes a Cose\Key\Key and the Signature class registered
 * for the identifier verifies with it. Three consequences are worth stating.
 *
 * First, the set of acceptable algorithms is the Manager the operator built, not a constant of this library: an
 * identifier that was never registered is refused, so `alg`, which comes from the wire, cannot select a verifier the
 * operator did not choose. RS1 in particular is only reachable when the operator registered an RS1 instance, which
 * they can only build by acknowledging what SHA-1 is.
 *
 * Second, every policy a registered algorithm carries applies unchanged, because it is that very instance which
 * verifies: the minimum modulus length an RSA algorithm was created with (RsaKeyPolicy, RFC 8230 section 6.1) is the
 * one enforced against the key of the certificate. Nothing here overrides it, and nothing has to be configured twice.
 *
 * Third, verify() is as total as the Signature contract is: it returns false for every signature the algorithm
 * rejects, and throws an InvalidArgumentException when the certificate, the identifier or the key type make the
 * verification impossible to even attempt.
 *
 * @see https://www.w3.org/TR/webauthn-3/#sctn-packed-attestation
 * @see \Cose\Tests\Algorithm\Signature\CertificateSignatureVerifierTest
 */
final class CertificateSignatureVerifier
{
    private function __construct(
        private readonly Manager $manager
    ) {
    }

    public static function create(Manager $manager): self
    {
        return new self($manager);
    }

    /**
     * @param string $certificate a PEM or DER encoded X.509 certificate
     *
     * @throws InvalidArgumentException when the certificate cannot be read, when no signature algorithm is
     * registered for the identifier, or when the key of the certificate cannot be used with that algorithm
     */
    public function verify(int $algorithmIdentifier, string $certificate, string $data, string $signature): bool
    {
        return $this->verifyWithKey(
            $algorithmIdentifier,
            PublicKeyLoader::fromCertificate($certificate),
            $data,
            $signature
        );
    }

    /**
     * The same verification against a bare SubjectPublicKeyInfo (RFC 5280, section 4.1.2.7), PEM or DER encoded, for
     * the callers that already hold the key rather than the certificate carrying it.
     *
     * @throws InvalidArgumentException see verify()
     */
    public function verifySubjectPublicKeyInfo(
        int $algorithmIdentifier,
        string $subjectPublicKeyInfo,
        string $data,
        string $signature
    ): bool {
        return $this->verifyWithKey(
            $algorithmIdentifier,
            PublicKeyLoader::fromSubjectPublicKeyInfo($subjectPublicKeyInfo),
            $data,
            $signature
        );
    }

    private function verifyWithKey(int $algorithmIdentifier, Key $key, string $data, string $signature): bool
    {
        $algorithm = $this->manager->get($algorithmIdentifier);
        if (! $algorithm instanceof Signature) {
            throw new InvalidArgumentException(sprintf(
                'The algorithm identifier %d is not registered with a signature algorithm',
                $algorithmIdentifier
            ));
        }
        return $algorithm->verify($data, $key, $signature);
    }
}

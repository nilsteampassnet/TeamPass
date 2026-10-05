<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\CertificationPath\PathValidation;

use function array_values;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\AlgorithmIdentifier;
use SpomkyLabs\Pki\X509\Certificate\Certificate;
use SpomkyLabs\Pki\X509\Certificate\Extension\CertificatePolicy\PolicyInformation;

/**
 * Configuration for the certification path validation process.
 *
 * @see https://tools.ietf.org/html/rfc5280#section-6.1.1
 */
final class PathValidationConfig
{
    /**
     * Signature algorithm OIDs accepted by default.
     *
     * MD2, MD4 and MD5 are left out. Chosen prefix collisions against MD5 have been practical since 2009, which
     * is enough to forge a certificate that verifies, and no certificate authority in service still signs with
     * them: refusing them costs nothing and an opt-in API would protect only those who already knew to ask.
     *
     * SHA-1 is still in, despite chosen prefix collisions against it being practical since 2020. Legacy
     * enterprise and device PKIs continue to rely on it, and cutting them off silently is not this library's
     * call to make. Use HARDENED_ALLOWED_SIGNATURE_ALGORITHMS to refuse it.
     *
     * RSASSA-PSS is left out because it is not implemented: a PSS signed certificate is refused when it is
     * parsed, so allowing the OID here advertised a capability that does not exist. When PSS is implemented the
     * parameters have to be bound as well as the OID, since Certificate::fromASN1() only compares the OID of the
     * outer and the signed algorithm identifiers, which would leave a hash, MGF and salt length downgrade open.
     *
     * Whether Ed25519 and Ed448 can actually be checked depends on the runtime: the OpenSSL extension only grew
     * EdDSA recently, and ext-sodium covers Ed25519 alone. Validation fails closed where it cannot, and
     * OpenSSLCrypto::supportsSignatureAlgorithm() answers the question up front for an application that would
     * rather narrow this set than have a chain refused later.
     *
     * @var string[]
     */
    public const DEFAULT_ALLOWED_SIGNATURE_ALGORITHMS = [
        AlgorithmIdentifier::OID_SHA1_WITH_RSA_ENCRYPTION,
        AlgorithmIdentifier::OID_SHA224_WITH_RSA_ENCRYPTION,
        AlgorithmIdentifier::OID_SHA256_WITH_RSA_ENCRYPTION,
        AlgorithmIdentifier::OID_SHA384_WITH_RSA_ENCRYPTION,
        AlgorithmIdentifier::OID_SHA512_WITH_RSA_ENCRYPTION,
        AlgorithmIdentifier::OID_ECDSA_WITH_SHA1,
        AlgorithmIdentifier::OID_ECDSA_WITH_SHA224,
        AlgorithmIdentifier::OID_ECDSA_WITH_SHA256,
        AlgorithmIdentifier::OID_ECDSA_WITH_SHA384,
        AlgorithmIdentifier::OID_ECDSA_WITH_SHA512,
        AlgorithmIdentifier::OID_ED25519,
        AlgorithmIdentifier::OID_ED448,
    ];

    /**
     * Signature algorithm OIDs for deployments that can refuse SHA-1.
     *
     * Pass it to withAllowedSignatureAlgorithms(). This is the set the default will narrow to in a future major
     * release.
     *
     * @var string[]
     */
    public const HARDENED_ALLOWED_SIGNATURE_ALGORITHMS = [
        AlgorithmIdentifier::OID_SHA224_WITH_RSA_ENCRYPTION,
        AlgorithmIdentifier::OID_SHA256_WITH_RSA_ENCRYPTION,
        AlgorithmIdentifier::OID_SHA384_WITH_RSA_ENCRYPTION,
        AlgorithmIdentifier::OID_SHA512_WITH_RSA_ENCRYPTION,
        AlgorithmIdentifier::OID_ECDSA_WITH_SHA224,
        AlgorithmIdentifier::OID_ECDSA_WITH_SHA256,
        AlgorithmIdentifier::OID_ECDSA_WITH_SHA384,
        AlgorithmIdentifier::OID_ECDSA_WITH_SHA512,
        AlgorithmIdentifier::OID_ED25519,
        AlgorithmIdentifier::OID_ED448,
    ];

    /**
     * Minimum RSA modulus size, in bits, accepted by default.
     *
     * RSA-512 has been factorable for decades and RSA-768 was factored in 2009, so a signature made with such a
     * key proves nothing; the default refuses them. It stops at 1024 rather than at the 2048 bits the CA/Browser
     * Forum has required since 2014, because raising the floor that far in a patch release would cut off legacy
     * enterprise and device PKIs, the same reasoning that keeps SHA-1 in the default algorithm set.
     */
    public const DEFAULT_MINIMUM_RSA_KEY_SIZE = 1024;

    /**
     * Minimum RSA modulus size, in bits, for deployments that can require 2048 bits.
     *
     * Pass it to withMinimumRSAKeySize(). This is the floor the default will rise to in a future major release.
     */
    public const HARDENED_MINIMUM_RSA_KEY_SIZE = 2048;

    /**
     * List of acceptable policy identifiers.
     *
     * @var string[]
     */
    private array $policySet;

    /**
     * Trust anchor certificate.
     *
     * If not set, path validation uses the first certificate of the path.
     */
    private ?Certificate $trustAnchor = null;

    /**
     * Signature algorithm OIDs accepted when verifying certificate signatures.
     *
     * @var string[]
     */
    private array $allowedSignatureAlgorithms;

    /**
     * Minimum RSA modulus size, in bits, accepted when verifying certificate signatures.
     */
    private int $minimumRSAKeySize;

    /**
     * Whether policy mapping in inhibited.
     *
     * Setting this to true disallows policy mapping.
     */
    private bool $policyMappingInhibit;

    /**
     * Whether the path must be valid for at least one policy in the initial policy set.
     */
    private bool $explicitPolicy;

    /**
     * Whether anyPolicy OID processing should be inhibited.
     *
     * Setting this to true disallows the usage of anyPolicy.
     */
    private bool $anyPolicyInhibit;

    /**
     * OID's of the critical certificate extensions the application processes by its own means.
     *
     * They are accepted by the validator in addition to the ones it processes itself.
     *
     * @var list<string>
     */
    private array $additionalCriticalExtensions;

    /**
     * Whether version 1 and version 2 certificates may act as CA certificates in the path.
     *
     * RFC 5280 section 6.1.4 (k) allows such a certificate only once the application has confirmed it is a CA
     * certificate through out-of-band means. This flag is how the application states that it did.
     */
    private bool $legacyV1IntermediatesTrusted;

    /**
     * @param DateTimeImmutable $dateTime Reference date and time
     * @param int $maxLength Maximum certification path length
     */
    private function __construct(
        private DateTimeImmutable $dateTime,
        private int $maxLength
    ) {
        $this->policySet = [PolicyInformation::OID_ANY_POLICY];
        $this->allowedSignatureAlgorithms = self::DEFAULT_ALLOWED_SIGNATURE_ALGORITHMS;
        $this->minimumRSAKeySize = self::DEFAULT_MINIMUM_RSA_KEY_SIZE;
        $this->policyMappingInhibit = false;
        $this->explicitPolicy = false;
        $this->anyPolicyInhibit = false;
        $this->additionalCriticalExtensions = [];
        $this->legacyV1IntermediatesTrusted = false;
    }

    public static function create(DateTimeImmutable $dateTime, int $maxLength): self
    {
        return new self($dateTime, $maxLength);
    }

    /**
     * Get default configuration.
     */
    public static function defaultConfig(): self
    {
        return self::create(new DateTimeImmutable(), 3);
    }

    /**
     * Get self with maximum path length.
     */
    public function withMaxLength(int $length): self
    {
        $obj = clone $this;
        $obj->maxLength = $length;
        return $obj;
    }

    /**
     * Get self with reference date and time.
     */
    public function withDateTime(DateTimeImmutable $dt): self
    {
        $obj = clone $this;
        $obj->dateTime = $dt;
        return $obj;
    }

    /**
     * Get self with trust anchor certificate.
     */
    public function withTrustAnchor(Certificate $ca): self
    {
        $obj = clone $this;
        $obj->trustAnchor = $ca;
        return $obj;
    }

    /**
     * Get self with initial-policy-mapping-inhibit set.
     */
    public function withPolicyMappingInhibit(bool $flag): self
    {
        $obj = clone $this;
        $obj->policyMappingInhibit = $flag;
        return $obj;
    }

    /**
     * Get self with initial-explicit-policy set.
     */
    public function withExplicitPolicy(bool $flag): self
    {
        $obj = clone $this;
        $obj->explicitPolicy = $flag;
        return $obj;
    }

    /**
     * Get self with initial-any-policy-inhibit set.
     */
    public function withAnyPolicyInhibit(bool $flag): self
    {
        $obj = clone $this;
        $obj->anyPolicyInhibit = $flag;
        return $obj;
    }

    /**
     * Get self with the OID's of the critical extensions the application processes by its own means.
     *
     * The path validation rejects any certificate carrying a critical extension it cannot process. Use this method to
     * declare the extensions handled outside of the validator, so that they no longer cause a rejection.
     *
     * @param string ...$oids List of extension OID's
     */
    public function withAdditionalCriticalExtensions(string ...$oids): self
    {
        $obj = clone $this;
        $obj->additionalCriticalExtensions = array_values($oids);
        return $obj;
    }

    /**
     * Get self with version 1 and version 2 certificates allowed to act as CA certificates.
     *
     * Such a certificate carries no extensions, hence neither basicConstraints nor keyUsage, so nothing in it states
     * that it may sign certificates. RFC 5280 section 6.1.4 (k) requires the application to either confirm this
     * out-of-band or reject the certificate; the default is to reject.
     *
     * Only enable this if the certificates in question have been vouched for by other means. Any end-entity
     * certificate a trusted CA ever issued in version 1 becomes usable as an intermediate CA for any identity.
     */
    public function withLegacyV1IntermediatesTrusted(bool $flag): self
    {
        $obj = clone $this;
        $obj->legacyV1IntermediatesTrusted = $flag;
        return $obj;
    }

    /**
     * Get self with user-initial-policy-set set to policy OIDs.
     *
     * @param string ...$policies List of policy OIDs
     */
    public function withPolicySet(string ...$policies): self
    {
        $obj = clone $this;
        $obj->policySet = $policies;
        return $obj;
    }

    /**
     * Get self with the set of accepted signature algorithms.
     *
     * Replaces the default set entirely. Pass HARDENED_ALLOWED_SIGNATURE_ALGORITHMS to also refuse SHA-1.
     *
     * @param string ...$oids Signature algorithm OIDs
     */
    public function withAllowedSignatureAlgorithms(string ...$oids): self
    {
        $obj = clone $this;
        $obj->allowedSignatureAlgorithms = $oids;
        return $obj;
    }

    /**
     * Get the signature algorithm OIDs accepted when verifying certificate signatures.
     *
     * @return string[]
     */
    public function allowedSignatureAlgorithms(): array
    {
        return $this->allowedSignatureAlgorithms;
    }

    /**
     * Get self with the minimum RSA modulus size, in bits, accepted when verifying certificate signatures.
     *
     * Pass HARDENED_MINIMUM_RSA_KEY_SIZE to require 2048 bits. A size of zero disables the check.
     *
     * @param int $bits Minimum modulus size in bits
     */
    public function withMinimumRSAKeySize(int $bits): self
    {
        if ($bits < 0) {
            throw new InvalidArgumentException('Minimum RSA key size must not be negative.');
        }
        $obj = clone $this;
        $obj->minimumRSAKeySize = $bits;
        return $obj;
    }

    /**
     * Get the minimum RSA modulus size, in bits, accepted when verifying certificate signatures.
     */
    public function minimumRSAKeySize(): int
    {
        return $this->minimumRSAKeySize;
    }

    /**
     * Get maximum certification path length.
     */
    public function maxLength(): int
    {
        return $this->maxLength;
    }

    /**
     * Get reference date and time.
     */
    public function dateTime(): DateTimeImmutable
    {
        return $this->dateTime;
    }

    /**
     * Get user-initial-policy-set.
     *
     * @return string[] Array of OID's
     */
    public function policySet(): array
    {
        return $this->policySet;
    }

    /**
     * Check whether trust anchor certificate is set.
     */
    public function hasTrustAnchor(): bool
    {
        return isset($this->trustAnchor);
    }

    /**
     * Get trust anchor certificate.
     */
    public function trustAnchor(): Certificate
    {
        if (! $this->hasTrustAnchor()) {
            throw new LogicException('No trust anchor.');
        }
        return $this->trustAnchor;
    }

    public function policyMappingInhibit(): bool
    {
        return $this->policyMappingInhibit;
    }

    public function explicitPolicy(): bool
    {
        return $this->explicitPolicy;
    }

    public function anyPolicyInhibit(): bool
    {
        return $this->anyPolicyInhibit;
    }

    /**
     * Get the OID's of the critical extensions the application processes by its own means.
     *
     * @return list<string> Array of OID's
     */
    public function additionalCriticalExtensions(): array
    {
        return $this->additionalCriticalExtensions;
    }

    /**
     * Whether version 1 and version 2 certificates may act as CA certificates in the path.
     */
    public function legacyV1IntermediatesTrusted(): bool
    {
        return $this->legacyV1IntermediatesTrusted;
    }
}

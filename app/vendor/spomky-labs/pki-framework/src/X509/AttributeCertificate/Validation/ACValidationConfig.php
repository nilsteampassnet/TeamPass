<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\AttributeCertificate\Validation;

use function array_values;
use DateTimeImmutable;
use SpomkyLabs\Pki\X509\Certificate\Certificate;
use SpomkyLabs\Pki\X509\Certificate\CertificateBundle;
use SpomkyLabs\Pki\X509\Certificate\Extension\Target\Target;
use SpomkyLabs\Pki\X509\CertificationPath\CertificationPath;
use SpomkyLabs\Pki\X509\CertificationPath\PathValidation\PathValidationConfig;

/**
 * Provides configuration context for the attribute certificate validation.
 */
final class ACValidationConfig
{
    /**
     * Evaluation reference time, or null to resolve it on every evaluation.
     */
    private ?DateTimeImmutable $evalTime;

    /**
     * Certificates of the attribute authorities the caller directly trusts, or null when the caller takes
     * responsibility for RFC 5755 sect. 5 check 4 itself.
     */
    private ?CertificateBundle $trustedAttributeAuthorities;

    /**
     * Whether the caller supplied the path validation configuration.
     */
    private bool $pathValidationConfigProvided;

    /**
     * Permitted targets.
     *
     * @var Target[]
     */
    private array $targets;

    /**
     * Configuration applied when validating the holder and issuer certification paths.
     */
    private PathValidationConfig $pathValidationConfig;

    /**
     * OID's of the critical attribute certificate extensions the application processes by its own means.
     *
     * They are accepted by the validator in addition to the ones it processes itself.
     *
     * @var list<string>
     */
    private array $additionalCriticalExtensions = [];

    /**
     * @param CertificationPath $holderPath Certification path of the AC holder
     * @param CertificationPath $issuerPath Certification path of the AC issuer
     */
    private function __construct(
        private readonly CertificationPath $holderPath,
        private readonly CertificationPath $issuerPath
    ) {
        // resolved on every evaluation rather than snapshotted here: a config object shared by a DI container or
        // held across requests in a worker runtime would otherwise keep validating expired credentials and
        // expired certificates for the lifetime of the process
        $this->evalTime = null;
        $this->targets = [];
        $this->trustedAttributeAuthorities = null;
        $this->pathValidationConfig = PathValidationConfig::defaultConfig();
        $this->pathValidationConfigProvided = false;
    }

    public static function create(CertificationPath $holderPath, CertificationPath $issuerPath): self
    {
        return new self($holderPath, $issuerPath);
    }

    /**
     * Get certification path of the AC's holder.
     */
    public function holderPath(): CertificationPath
    {
        return $this->holderPath;
    }

    /**
     * Get certification path of the AC's issuer.
     */
    public function issuerPath(): CertificationPath
    {
        return $this->issuerPath;
    }

    /**
     * Get self with the configuration used to validate the holder and issuer certification paths.
     *
     * Without this, those two paths are validated on the default configuration and the caller cannot express any
     * policy for them, including which signature algorithms are acceptable. The maximum path length and the
     * evaluation time are still derived from this object.
     */
    public function withPathValidationConfig(PathValidationConfig $config): self
    {
        $obj = clone $this;
        $obj->pathValidationConfig = $config;
        $obj->pathValidationConfigProvided = true;
        return $obj;
    }

    /**
     * Get self with the OID's of the critical extensions the application processes by its own means.
     *
     * The validation rejects an attribute certificate carrying a critical extension it cannot process. Use this
     * method to declare the extensions handled outside of the validator, so that they no longer cause a rejection.
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
     * Get the OID's of the critical extensions the application processes by its own means.
     *
     * @return list<string> Array of OID's
     */
    public function additionalCriticalExtensions(): array
    {
        return $this->additionalCriticalExtensions;
    }

    /**
     * Get the configuration used to validate the holder and issuer certification paths.
     */
    public function pathValidationConfig(): PathValidationConfig
    {
        return $this->pathValidationConfig;
    }

    /**
     * Whether the caller supplied the path validation configuration.
     *
     * The validator derives a maximum path length from the length of the path it was handed when the caller did
     * not, and leaves the caller's own limit alone when they did.
     */
    public function hasPathValidationConfig(): bool
    {
        return $this->pathValidationConfigProvided;
    }

    /**
     * Get self with given evaluation reference time.
     */
    public function withEvaluationTime(DateTimeImmutable $dt): self
    {
        $obj = clone $this;
        $obj->evalTime = $dt;
        return $obj;
    }

    /**
     * Get the evaluation reference time.
     *
     * Unless withEvaluationTime() pinned it, this is the instant the call is made rather than the instant this
     * object was built.
     */
    public function evaluationTime(): DateTimeImmutable
    {
        return $this->evalTime ?? new DateTimeImmutable();
    }

    /**
     * Get self with the attribute authorities that are directly trusted to issue attribute certificates.
     *
     * RFC 5755 sect. 5 check 4 requires the AC issuer to be directly trusted as an attribute authority. Without
     * this list the validator accepts whatever end-entity certificate the issuer path ends in, so a caller who
     * locates the attribute authority by matching the issuer name against a bundle turns every end-entity
     * certificate under the trust anchor into an attribute authority.
     *
     * @param Certificate ...$certs Certificates of the trusted attribute authorities
     */
    public function withTrustedAttributeAuthorities(Certificate ...$certs): self
    {
        $obj = clone $this;
        $obj->trustedAttributeAuthorities = CertificateBundle::create(...$certs);
        return $obj;
    }

    /**
     * Get the attribute authorities that are directly trusted, or null when the caller has not named any.
     */
    public function trustedAttributeAuthorities(): ?CertificateBundle
    {
        return $this->trustedAttributeAuthorities;
    }

    /**
     * Get self with permitted targets.
     */
    public function withTargets(Target ...$targets): self
    {
        $obj = clone $this;
        $obj->targets = $targets;
        return $obj;
    }

    /**
     * Get array of permitted targets.
     *
     * @return Target[]
     */
    public function targets(): array
    {
        return $this->targets;
    }
}

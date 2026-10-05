<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\AttributeCertificate\Validation;

use function count;
use function in_array;
use SpomkyLabs\Pki\CryptoBridge\Crypto;
use SpomkyLabs\Pki\X509\AttributeCertificate\AttributeCertificate;
use SpomkyLabs\Pki\X509\AttributeCertificate\Validation\Exception\ACValidationException;
use SpomkyLabs\Pki\X509\Certificate\Certificate;
use SpomkyLabs\Pki\X509\Certificate\Extension\Extension;
use SpomkyLabs\Pki\X509\Certificate\Extension\Target\Targets;
use SpomkyLabs\Pki\X509\Certificate\Extension\TargetInformationExtension;
use SpomkyLabs\Pki\X509\CertificationPath\CertificationPath;
use SpomkyLabs\Pki\X509\CertificationPath\Exception\PathValidationException;
use SpomkyLabs\Pki\X509\CertificationPath\PathValidation\PathValidationConfig;
use SpomkyLabs\Pki\X509\CertificationPath\PathValidation\SignaturePolicy;
use function sprintf;

/**
 * Implements attribute certificate validation conforming to RFC 5755.
 *
 * RFC 5755 sect. 5 check 4 requires the AC issuer to be directly trusted as an attribute authority. Name the
 * trusted authorities with ACValidationConfig::withTrustedAttributeAuthorities() to have that check run here;
 * without it the check is the caller's responsibility, and this validator only establishes that the issuer
 * certificate chains to the trust anchor and carries a profile that permits signing.
 *
 * @see https://tools.ietf.org/html/rfc5755#section-5
 */
final class ACValidator
{
    /**
     * OID's of the attribute certificate extensions this validator is able to process.
     *
     * A critical extension whose OID is not listed here cannot be honoured, and therefore makes the validation fail
     * as required by RFC 5755 section 5, check 7. An application that processes further extensions by its own means
     * may declare them through `ACValidationConfig::withAdditionalCriticalExtensions()`.
     *
     * @var list<string>
     */
    private const PROCESSED_EXTENSIONS = [
        Extension::OID_TARGET_INFORMATION,
        Extension::OID_NO_REV_AVAIL,
    ];

    /**
     * Crypto engine.
     */
    private readonly Crypto $crypto;

    /**
     * @param AttributeCertificate $ac Attribute certificate to validate
     * @param ACValidationConfig $config Validation configuration
     * @param null|Crypto $crypto Crypto engine, use default if not set
     */
    private function __construct(
        private readonly AttributeCertificate $ac,
        private readonly ACValidationConfig $config,
        ?Crypto $crypto
    ) {
        $this->crypto = $crypto ?? Crypto::getDefault();
    }

    public static function create(
        AttributeCertificate $ac,
        ACValidationConfig $config,
        ?Crypto $crypto = null
    ): self {
        return new self($ac, $config, $crypto);
    }

    /**
     * Validate attribute certificate.
     *
     * @return AttributeCertificate Validated AC
     */
    public function validate(): AttributeCertificate
    {
        $this->validateHolder();
        $issuer = $this->verifyIssuer();
        $this->validateIssuerProfile($issuer);
        $this->validateTime();
        $this->validateTargeting();
        $this->validateExtensions();
        return $this->ac;
    }

    /**
     * Check that every critical extension of the attribute certificate is one this validator honours.
     *
     * An attribute certificate carries authorisation, and a critical extension is how its issuer narrows that
     * authorisation. Accepting one that cannot be processed grants the attributes unconditionally, which is why
     * RFC 5755 section 5, check 7 requires the certificate to be rejected instead.
     *
     * @see https://tools.ietf.org/html/rfc5755#section-5
     */
    private function validateExtensions(): void
    {
        $recognized = [...self::PROCESSED_EXTENSIONS, ...$this->config->additionalCriticalExtensions()];
        foreach ($this->ac->acinfo()->extensions() as $extension) {
            if (! $extension->isCritical()) {
                continue;
            }
            if (! in_array($extension->oid(), $recognized, true)) {
                throw new ACValidationException(sprintf(
                    'Attribute certificate contains an unhandled critical extension: %s.',
                    $extension->extensionName()
                ));
            }
        }
    }

    /**
     * Validate AC holder's certification.
     *
     * @return Certificate Certificate of the AC's holder
     */
    private function validateHolder(): Certificate
    {
        $path = $this->config->holderPath();
        $config = $this->pathConfigFor($path);
        try {
            $holder = $path->validate($config, $this->crypto)
                ->certificate();
        } catch (PathValidationException $e) {
            throw new ACValidationException("Failed to validate holder PKC's certification path.", 0, $e);
        }
        if (! $this->ac->isHeldBy($holder)) {
            throw new ACValidationException("Name mismatch of AC's holder PKC.");
        }
        return $holder;
    }

    /**
     * Verify AC's signature and issuer's certification.
     *
     * @return Certificate Certificate of the AC's issuer
     */
    private function verifyIssuer(): Certificate
    {
        $path = $this->config->issuerPath();
        $config = $this->pathConfigFor($path);
        try {
            $issuer = $path->validate($config, $this->crypto)
                ->certificate();
        } catch (PathValidationException $e) {
            throw new ACValidationException("Failed to validate issuer PKC's certification path.", 0, $e);
        }
        if (! $this->ac->isIssuedBy($issuer)) {
            throw new ACValidationException("Name mismatch of AC's issuer PKC.");
        }
        $pubkey_info = $issuer->tbsCertificate()
            ->subjectPublicKeyInfo();
        // The certification paths above are validated under a policy that names the acceptable signature algorithms
        // and the smallest acceptable RSA modulus. The attribute certificate's own signature is the one that
        // carries the authorisation, so the same policy has to reach it: the crypto engine will otherwise verify an
        // MD5 signature, and the attribute authority's own key is never a working key of either path, so its size
        // was never measured.
        $config = $this->config->pathValidationConfig();
        $algo = $this->ac->signatureAlgorithm();
        if (! SignaturePolicy::isAlgorithmAllowed($algo, $config->allowedSignatureAlgorithms())) {
            throw new ACValidationException(sprintf('Signature algorithm %s is not allowed.', $algo->name()));
        }
        $minimum = $config->minimumRSAKeySize();
        $bits = SignaturePolicy::rsaKeySizeBelowMinimum($pubkey_info, $minimum);
        if ($bits !== null) {
            throw new ACValidationException(
                sprintf('RSA key size %d is below the minimum of %d bits.', $bits, $minimum)
            );
        }
        if (! $this->ac->verify($pubkey_info, $this->crypto)) {
            throw new ACValidationException('Failed to verify signature.');
        }
        return $issuer;
    }

    /**
     * Get the path validation configuration to use for one of the two certification paths.
     *
     * The maximum path length is derived from the path itself only when the caller did not supply a
     * configuration of their own: overriding it unconditionally made PathValidationConfig::maxLength() a no-op
     * for both paths, so a caller who set a limit did not get it.
     */
    private function pathConfigFor(CertificationPath $path): PathValidationConfig
    {
        $config = $this->config->pathValidationConfig()
            ->withDateTime($this->config->evaluationTime());
        if (! $this->config->hasPathValidationConfig()) {
            $config = $config->withMaxLength(count($path));
        }
        return $config;
    }

    /**
     * Validate AC issuer's profile.
     *
     * @see https://tools.ietf.org/html/rfc5755#section-4.5
     */
    private function validateIssuerProfile(Certificate $cert): void
    {
        $exts = $cert->tbsCertificate()
            ->extensions();
        // sect. 4.5 permits either bit: an attribute authority that asserts non-repudiation only is conforming
        if ($exts->hasKeyUsage()
            && ! $exts->keyUsage()
                ->isDigitalSignature()
            && ! $exts->keyUsage()
                ->isNonRepudiation()) {
            throw new ACValidationException(
                "Issuer PKC's Key Usage extension doesn't permit" .
                ' verification of digital signatures.'
            );
        }
        if ($exts->hasBasicConstraints() && $exts->basicConstraints()->isCA()) {
            throw new ACValidationException('Issuer PKC must not be a CA.');
        }
        // RFC 5755 sect. 5, check 4: the AC issuer must be directly trusted as an attribute authority
        $authorities = $this->config->trustedAttributeAuthorities();
        if ($authorities !== null && ! $authorities->contains($cert)) {
            throw new ACValidationException('Issuer PKC is not a trusted attribute authority.');
        }
    }

    /**
     * Validate AC's validity period.
     */
    private function validateTime(): void
    {
        $t = $this->config->evaluationTime();
        $validity = $this->ac->acinfo()
            ->validityPeriod();
        if ($validity->notBeforeTime()->diff($t)->invert === 1) {
            throw new ACValidationException('Validity period has not started.');
        }
        if ($t->diff($validity->notAfterTime())->invert === 1) {
            throw new ACValidationException('Attribute certificate has expired.');
        }
    }

    /**
     * Validate AC's target information.
     */
    private function validateTargeting(): void
    {
        $exts = $this->ac->acinfo()
            ->extensions();
        // if target information extension is not present
        if (! $exts->has(Extension::OID_TARGET_INFORMATION)) {
            return;
        }
        $ext = $exts->get(Extension::OID_TARGET_INFORMATION);
        if ($ext instanceof TargetInformationExtension &&
            ! $this->_hasMatchingTarget($ext->targets())) {
            throw new ACValidationException("Attribute certificate doesn't have a matching target.");
        }
    }

    /**
     * Check whether validation configuration has matching targets.
     *
     * @param Targets $targets Set of eligible targets
     */
    private function _hasMatchingTarget(Targets $targets): bool
    {
        foreach ($this->config->targets() as $target) {
            if ($targets->hasTarget($target)) {
                return true;
            }
        }
        return false;
    }
}

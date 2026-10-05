<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\CertificationPath\PathValidation;

use function array_values;
use function count;
use function in_array;
use LogicException;
use RuntimeException;
use SpomkyLabs\Pki\CryptoBridge\Crypto;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\PublicKeyInfo;
use SpomkyLabs\Pki\X501\StringPrep\Exception\StringPreparationException;
use SpomkyLabs\Pki\X509\Certificate\Certificate;
use SpomkyLabs\Pki\X509\Certificate\Extension\CertificatePolicy\PolicyInformation;
use SpomkyLabs\Pki\X509\Certificate\Extension\Extension;
use SpomkyLabs\Pki\X509\Certificate\Extension\NameConstraints\GeneralSubtrees;
use SpomkyLabs\Pki\X509\Certificate\TBSCertificate;
use SpomkyLabs\Pki\X509\CertificationPath\Exception\PathValidationException;
use SpomkyLabs\Pki\X509\GeneralName\DirectoryName;
use SpomkyLabs\Pki\X509\GeneralName\GeneralName;
use SpomkyLabs\Pki\X509\GeneralName\RFC822Name;
use function sprintf;

/**
 * Implements certification path validation.
 *
 * @see https://tools.ietf.org/html/rfc5280#section-6
 */
final class PathValidator
{
    /**
     * OID's of the certificate extensions this validator is able to process.
     *
     * A critical extension whose OID is not listed here cannot be honoured, and therefore makes the path validation
     * fail as required by RFC 5280 section 6.1.4 (o) and section 6.1.5 (f).
     *
     * `subjectAltName` belongs to that set: RFC 5280 section 6.1.3 (b) and (c) define its processing during path
     * validation as the matching of its names against the name constraints, which this validator performs. Marking it
     * critical is moreover what RFC 5280 section 4.2.1.6 requires of a certificate carrying an empty subject, so
     * rejecting it would turn away the very certificates the specification mandates.
     *
     * An extension outside of this set may still be declared by an application enforcing it by its own means, through
     * `PathValidationConfig::withAdditionalCriticalExtensions()`.
     *
     * @var list<string>
     */
    private const PROCESSED_EXTENSIONS = [
        Extension::OID_BASIC_CONSTRAINTS,
        Extension::OID_KEY_USAGE,
        Extension::OID_CERTIFICATE_POLICIES,
        Extension::OID_POLICY_MAPPINGS,
        Extension::OID_POLICY_CONSTRAINTS,
        Extension::OID_INHIBIT_ANY_POLICY,
        Extension::OID_NAME_CONSTRAINTS,
        Extension::OID_SUBJECT_ALT_NAME,
    ];

    /**
     * Attribute holding an electronic mail address embedded in a subject distinguished name.
     *
     * @see https://tools.ietf.org/html/rfc5280#section-4.1.2.6
     */
    private const ATTR_EMAIL_ADDRESS = 'emailAddress';

    /**
     * Certification path.
     *
     * @var list<Certificate>
     */
    private readonly array $certificates;

    /**
     * Certification path trust anchor.
     */
    private readonly Certificate $trustAnchor;

    /**
     * @param Crypto $crypto Crypto engine
     * @param PathValidationConfig $config Validation config
     * @param Certificate ...$certificates Certificates from the trust anchor to
     * the end-entity certificate
     */
    private function __construct(
        private readonly Crypto $crypto,
        private readonly PathValidationConfig $config,
        Certificate ...$certificates
    ) {
        if (count($certificates) === 0) {
            throw new LogicException('No certificates.');
        }
        $this->certificates = array_values($certificates);
        // if trust anchor is explicitly given in configuration
        if ($config->hasTrustAnchor()) {
            $this->trustAnchor = $config->trustAnchor();
        } else {
            $this->trustAnchor = $certificates[0];
        }
    }

    public static function create(
        Crypto $crypto,
        PathValidationConfig $config,
        Certificate ...$certificates
    ): self {
        return new self($crypto, $config, ...$certificates);
    }

    /**
     * Validate certification path.
     *
     * A name comparison is a security decision here, so a name that cannot be prepared for comparison fails the
     * validation rather than being reported as not equal, which would let a name escape an excluded subtree. The
     * failure is reported as a PathValidationException like every other, instead of escaping as the
     * StringPreparationException raised deep inside the comparison.
     *
     * @throws PathValidationException If the path does not validate.
     */
    public function validate(): PathValidationResult
    {
        try {
            return $this->process();
        } catch (StringPreparationException $e) {
            throw new PathValidationException(
                'A name of the certification path cannot be prepared for comparison: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Run the validation algorithm of RFC 5280 section 6.1.
     */
    private function process(): PathValidationResult
    {
        $n = count($this->certificates);
        $state = ValidatorState::initialize($this->config, $this->trustAnchor, $n);
        foreach ($this->certificates as $i => $iValue) {
            $state = $state->withIndex($i + 1);
            $cert = $iValue;
            // process certificate (section 6.1.3.)
            $state = $this->processCertificate($state, $cert);
            if (! $state->isFinal()) {
                // prepare next certificate (section 6.1.4.)
                $state = $this->prepareNext($state, $cert);
            }
        }
        if (! isset($cert)) {
            throw new LogicException('No certificates.');
        }
        // wrap-up (section 6.1.5.)
        $state = $this->wrapUp($state, $cert);
        // return outputs
        return $state->getResult($this->certificates);
    }

    /**
     * Apply basic certificate processing according to RFC 5280 section 6.1.3.
     *
     * @see https://tools.ietf.org/html/rfc5280#section-6.1.3
     */
    private function processCertificate(ValidatorState $state, Certificate $cert): ValidatorState
    {
        // (a.1) verify signature
        $this->verifySignature($state, $cert);
        // (a.2) check validity period
        $this->checkValidity($cert);
        // (a.3) check that certificate is not revoked
        $this->checkRevocation();
        // (a.4) check issuer
        $this->checkIssuer($state, $cert);
        // (b)(c) if certificate is self-issued and it is not
        // the final certificate in the path, skip this step
        if (! ($cert->isSelfIssued() && ! $state->isFinal())) {
            // (b) check permitted subtrees
            $this->checkPermittedSubtrees($state, $cert);
            // (c) check excluded subtrees
            $this->checkExcludedSubtrees($state, $cert);
        }
        $extensions = $cert->tbsCertificate()
            ->extensions();
        if ($extensions->hasCertificatePolicies()) {
            // (d) process policy information
            if ($state->hasValidPolicyTree()) {
                $state = $state->validPolicyTree()
                    ->processPolicies($state, $cert);
            }
        } else {
            // (e) certificate policies extension not present,
            // set the valid_policy_tree to NULL
            $state = $state->withoutValidPolicyTree();
        }
        // (f) check that explicit_policy > 0 or valid_policy_tree is set
        if (! ($state->explicitPolicy() > 0 || $state->hasValidPolicyTree())) {
            throw new PathValidationException('No valid policies.');
        }
        return $state;
    }

    /**
     * Apply preparation for the certificate i+1 according to rfc5280 section 6.1.4.
     *
     * @see https://tools.ietf.org/html/rfc5280#section-6.1.4
     */
    private function prepareNext(ValidatorState $state, Certificate $cert): ValidatorState
    {
        // (a)(b) if policy mappings extension is present
        $state = $this->preparePolicyMappings($state, $cert);
        // (c) assign working_issuer_name
        $state = $state->withWorkingIssuerName($cert->tbsCertificate()->subject());
        // (d)(e)(f)
        $state = $this->setPublicKeyState($state, $cert);
        // (g) if name constraints extension is present
        $state = $this->prepareNameConstraints($state, $cert);
        // (h) if certificate is not self-issued
        if (! $cert->isSelfIssued()) {
            $state = $this->prepareNonSelfIssued($state);
        }
        // (i) if policy constraints extension is present
        $state = $this->preparePolicyConstraints($state, $cert);
        // (j) if inhibit any policy extension is present
        $state = $this->prepareInhibitAnyPolicy($state, $cert);
        // (k) check basic constraints
        $this->processBasicContraints($cert);
        // (l) verify max_path_length
        $state = $this->verifyMaxPathLength($state, $cert);
        // (m) check pathLenContraint
        $state = $this->processPathLengthContraint($state, $cert);
        // (n) check key usage
        $this->checkKeyUsage($cert);
        // (o) process relevant extensions
        return $this->processExtensions($state, $cert);
    }

    /**
     * Apply wrap-up procedure according to RFC 5280 section 6.1.5.
     *
     * @see https://tools.ietf.org/html/rfc5280#section-6.1.5
     */
    private function wrapUp(ValidatorState $state, Certificate $cert): ValidatorState
    {
        $tbs_cert = $cert->tbsCertificate();
        $extensions = $tbs_cert->extensions();
        // (a)
        if ($state->explicitPolicy() > 0) {
            $state = $state->withExplicitPolicy($state->explicitPolicy() - 1);
        }
        // (b)
        if ($extensions->hasPolicyConstraints()) {
            $ext = $extensions->policyConstraints();
            if ($ext->hasRequireExplicitPolicy() &&
                $ext->requireExplicitPolicy() === 0) {
                $state = $state->withExplicitPolicy(0);
            }
        }
        // (c)(d)(e)
        $state = $this->setPublicKeyState($state, $cert);
        // (f) process relevant extensions
        $state = $this->processExtensions($state, $cert);
        // (g) intersection of valid_policy_tree and the initial-policy-set
        $state = $this->calculatePolicyIntersection($state);
        // check that explicit_policy > 0 or valid_policy_tree is set
        if (! ($state->explicitPolicy() > 0 || $state->hasValidPolicyTree())) {
            throw new PathValidationException('No valid policies.');
        }
        // path validation succeeded
        return $state;
    }

    /**
     * Update working_public_key, working_public_key_parameters and working_public_key_algorithm state variables from
     * certificate.
     */
    private function setPublicKeyState(ValidatorState $state, Certificate $cert): ValidatorState
    {
        $pk_info = $cert->tbsCertificate()
            ->subjectPublicKeyInfo();
        // assign working_public_key
        $state = $state->withWorkingPublicKey($pk_info);
        // assign working_public_key_parameters
        $params = ValidatorState::getAlgorithmParameters($pk_info->algorithmIdentifier());
        if ($params !== null) {
            $state = $state->withWorkingPublicKeyParameters($params);
        } else {
            // if algorithms differ, set parameters to null
            if ($pk_info->algorithmIdentifier()->oid() !==
                $state->workingPublicKeyAlgorithm()
                    ->oid()) {
                $state = $state->withWorkingPublicKeyParameters();
            }
        }
        // assign working_public_key_algorithm
        return $state->withWorkingPublicKeyAlgorithm($pk_info->algorithmIdentifier());
    }

    /**
     * Verify certificate signature.
     */
    private function verifySignature(ValidatorState $state, Certificate $cert): void
    {
        // A signature is only as good as its digest. Without this check the validator accepts MD5, against which
        // chosen prefix collisions are practical, and the application has no way to say no.
        $algo = $cert->signatureAlgorithm();
        if (! SignaturePolicy::isAlgorithmAllowed($algo, $this->config->allowedSignatureAlgorithms())) {
            throw new PathValidationException(sprintf('Signature algorithm %s is not allowed.', $algo->name()));
        }
        $this->checkIssuerKeySize($state->workingPublicKey());
        try {
            $valid = $cert->verify($state->workingPublicKey(), $this->crypto);
        } catch (RuntimeException $e) {
            throw new PathValidationException('Failed to verify signature: ' . $e->getMessage(), 0, $e);
        }
        if (! $valid) {
            throw new PathValidationException("Certificate signature doesn't match.");
        }
    }

    /**
     * Check that the key which signed the certificate is large enough to be worth verifying.
     *
     * Only RSA is covered: an EC key's strength is fixed by its named curve, and the curves this library knows
     * are all above the line. A modulus below the floor makes the signature meaningless however well OpenSSL
     * verifies it.
     */
    private function checkIssuerKeySize(PublicKeyInfo $pubkey_info): void
    {
        $minimum = $this->config->minimumRSAKeySize();
        $bits = SignaturePolicy::rsaKeySizeBelowMinimum($pubkey_info, $minimum);
        if ($bits !== null) {
            throw new PathValidationException(
                sprintf('RSA key size %d is below the minimum of %d bits.', $bits, $minimum)
            );
        }
    }

    /**
     * Check certificate validity.
     */
    private function checkValidity(Certificate $cert): void
    {
        $refdt = $this->config->dateTime();
        $validity = $cert->tbsCertificate()
            ->validity();
        if ($validity->notBefore()->dateTime()->diff($refdt)->invert !== 0) {
            throw new PathValidationException('Certificate validity period has not started.');
        }
        if ($refdt->diff($validity->notAfter()->dateTime())->invert !== 0) {
            throw new PathValidationException('Certificate has expired.');
        }
    }

    /**
     * Check certificate revocation.
     */
    private function checkRevocation(): void
    {
        // @todo Implement CRL handling
    }

    /**
     * Check certificate issuer.
     */
    private function checkIssuer(ValidatorState $state, Certificate $cert): void
    {
        if (! $cert->tbsCertificate()->issuer()->equals($state->workingIssuerName())) {
            throw new PathValidationException('Certification issuer mismatch.');
        }
    }

    /**
     * Check that every name of the certificate falls within the permitted subtrees.
     */
    private function checkPermittedSubtrees(ValidatorState $state, Certificate $cert): void
    {
        $permitted = $state->permittedSubtrees();
        if (count($permitted) === 0) {
            return;
        }
        foreach ($this->certificateNames($cert) as $name) {
            // the state holds the intersection of the constraints of each certificate processed so far,
            // so the name must fall within every one of them
            foreach ($permitted as $subtrees) {
                $this->checkPermittedName($name, $subtrees);
            }
        }
    }

    /**
     * Check that a name falls within one of the subtrees constraining its own type.
     *
     * RFC 5280 4.2.1.10: restrictions apply only when the constrained name form is present, hence a name of a type
     * that no subtree constrains is unrestricted.
     */
    private function checkPermittedName(GeneralName $name, GeneralSubtrees $subtrees): void
    {
        $constrained = false;
        foreach ($subtrees->all() as $subtree) {
            if ($subtree->base()->tag() !== $name->tag()) {
                continue;
            }
            $constrained = true;
            if (NameConstraintsMatcher::matches($subtree, $name)) {
                return;
            }
        }
        if ($constrained) {
            throw new PathValidationException(
                "Name '{$name->string()}' is not within the permitted subtrees."
            );
        }
    }

    /**
     * Check that no name of the certificate falls within the excluded subtrees.
     */
    private function checkExcludedSubtrees(ValidatorState $state, Certificate $cert): void
    {
        $excluded = $state->excludedSubtrees();
        if ($excluded === null) {
            return;
        }
        foreach ($this->certificateNames($cert) as $name) {
            foreach ($excluded->all() as $subtree) {
                if ($subtree->base()->tag() !== $name->tag()) {
                    continue;
                }
                if (NameConstraintsMatcher::matches($subtree, $name)) {
                    throw new PathValidationException(
                        "Name '{$name->string()}' is within an excluded subtree."
                    );
                }
            }
        }
    }

    /**
     * Get the names of a certificate that are subject to name constraints.
     *
     * These are the subject distinguished name, as a directoryName, and every name of the subjectAltName extension.
     * For a certificate carrying no subjectAltName extension, RFC 5280 4.2.1.10 additionally submits the legacy
     * emailAddress attributes of the subject to the rfc822Name constraints.
     *
     * @return list<GeneralName>
     */
    private function certificateNames(Certificate $cert): array
    {
        $tbsCert = $cert->tbsCertificate();
        $names = [];
        $subject = $tbsCert->subject();
        // an empty subject carries no identity and is constrained by the subjectAltName extension alone
        if (count($subject) !== 0) {
            $names[] = DirectoryName::create($subject);
        }
        $extensions = $tbsCert->extensions();
        if ($extensions->hasSubjectAlternativeName()) {
            foreach ($extensions->subjectAlternativeName()->names()->all() as $name) {
                $names[] = $name;
            }
            return $names;
        }
        foreach ($subject->all() as $rdn) {
            foreach ($rdn->allOf(self::ATTR_EMAIL_ADDRESS) as $attribute) {
                $names[] = RFC822Name::create($attribute->value()->stringValue());
            }
        }
        return $names;
    }

    /**
     * Apply policy mappings handling for the preparation step.
     */
    private function preparePolicyMappings(ValidatorState $state, Certificate $cert): ValidatorState
    {
        $extensions = $cert->tbsCertificate()
            ->extensions();
        if ($extensions->hasPolicyMappings()) {
            // (a) verify that anyPolicy mapping is not used
            if ($extensions->policyMappings()->hasAnyPolicyMapping()) {
                throw new PathValidationException('anyPolicy mapping found.');
            }
            // (b) process policy mappings
            if ($state->hasValidPolicyTree()) {
                $state = $state->validPolicyTree()
                    ->processMappings($state, $cert);
            }
        }
        return $state;
    }

    /**
     * Apply name constraints handling for the preparation step.
     */
    private function prepareNameConstraints(ValidatorState $state, Certificate $cert): ValidatorState
    {
        $extensions = $cert->tbsCertificate()
            ->extensions();
        if ($extensions->hasNameConstraints()) {
            $state = $this->processNameConstraints($state, $cert);
        }
        return $state;
    }

    /**
     * Apply preparation for a non-self-signed certificate.
     */
    private function prepareNonSelfIssued(ValidatorState $state): ValidatorState
    {
        // (h.1)
        if ($state->explicitPolicy() > 0) {
            $state = $state->withExplicitPolicy($state->explicitPolicy() - 1);
        }
        // (h.2)
        if ($state->policyMapping() > 0) {
            $state = $state->withPolicyMapping($state->policyMapping() - 1);
        }
        // (h.3)
        if ($state->inhibitAnyPolicy() > 0) {
            $state = $state->withInhibitAnyPolicy($state->inhibitAnyPolicy() - 1);
        }
        return $state;
    }

    /**
     * Apply policy constraints handling for the preparation step.
     */
    private function preparePolicyConstraints(ValidatorState $state, Certificate $cert): ValidatorState
    {
        $extensions = $cert->tbsCertificate()
            ->extensions();
        if (! $extensions->hasPolicyConstraints()) {
            return $state;
        }
        $ext = $extensions->policyConstraints();
        // (i.1)
        if ($ext->hasRequireExplicitPolicy() &&
            $ext->requireExplicitPolicy() < $state->explicitPolicy()) {
            $state = $state->withExplicitPolicy($ext->requireExplicitPolicy());
        }
        // (i.2)
        if ($ext->hasInhibitPolicyMapping() &&
            $ext->inhibitPolicyMapping() < $state->policyMapping()) {
            $state = $state->withPolicyMapping($ext->inhibitPolicyMapping());
        }
        return $state;
    }

    /**
     * Apply inhibit any-policy handling for the preparation step.
     */
    private function prepareInhibitAnyPolicy(ValidatorState $state, Certificate $cert): ValidatorState
    {
        $extensions = $cert->tbsCertificate()
            ->extensions();
        if ($extensions->hasInhibitAnyPolicy()) {
            $ext = $extensions->inhibitAnyPolicy();
            if ($ext->skipCerts() < $state->inhibitAnyPolicy()) {
                $state = $state->withInhibitAnyPolicy($ext->skipCerts());
            }
        }
        return $state;
    }

    /**
     * Verify maximum certification path length for the preparation step.
     */
    private function verifyMaxPathLength(ValidatorState $state, Certificate $cert): ValidatorState
    {
        if (! $cert->isSelfIssued()) {
            if ($state->maxPathLength() <= 0) {
                throw new PathValidationException('Certification path length exceeded.');
            }
            $state = $state->withMaxPathLength($state->maxPathLength() - 1);
        }
        return $state;
    }

    /**
     * Check key usage extension for the preparation step.
     */
    private function checkKeyUsage(Certificate $cert): void
    {
        $extensions = $cert->tbsCertificate()
            ->extensions();
        if ($extensions->hasKeyUsage()) {
            $ext = $extensions->keyUsage();
            if (! $ext->isKeyCertSign()) {
                throw new PathValidationException('keyCertSign usage not set.');
            }
        }
    }

    /**
     * Intersect the permitted subtrees and unite the excluded subtrees of the certificate into the state.
     *
     * @see https://tools.ietf.org/html/rfc5280#section-6.1.4
     */
    private function processNameConstraints(ValidatorState $state, Certificate $cert): ValidatorState
    {
        $ext = $cert->tbsCertificate()
            ->extensions()
            ->nameConstraints();
        if (! $ext->hasPermittedSubtrees() && ! $ext->hasExcludedSubtrees()) {
            throw new PathValidationException('Name constraints extension must contain at least one subtree.');
        }
        if ($ext->hasPermittedSubtrees()) {
            $subtrees = $ext->permittedSubtrees();
            $this->assertSubtreesSupported($subtrees);
            $state = $state->withAdditionalPermittedSubtrees($subtrees);
        }
        if ($ext->hasExcludedSubtrees()) {
            $subtrees = $ext->excludedSubtrees();
            $this->assertSubtreesSupported($subtrees);
            $state = $state->withAdditionalExcludedSubtrees($subtrees);
        }
        return $state;
    }

    /**
     * Reject constraints that cannot be enforced instead of letting the names they cover through unchecked.
     */
    private function assertSubtreesSupported(GeneralSubtrees $subtrees): void
    {
        foreach ($subtrees->all() as $subtree) {
            NameConstraintsMatcher::assertSupported($subtree);
        }
    }

    /**
     * Process basic constraints extension.
     */
    private function processBasicContraints(Certificate $cert): void
    {
        // a v1 or v2 certificate carries no extensions, so it asserts nothing about being a CA. RFC 5280 section
        // 6.1.4 (k) requires it to be either confirmed as a CA certificate out-of-band or rejected; the application
        // states that confirmation through the configuration.
        if ($cert->tbsCertificate()->version() !== TBSCertificate::VERSION_3) {
            if (! $this->config->legacyV1IntermediatesTrusted()) {
                throw new PathValidationException(
                    'Certificate is not a v3 certificate and cannot be verified as a CA certificate.'
                );
            }
            return;
        }
        $extensions = $cert->tbsCertificate()
            ->extensions();
        if (! $extensions->hasBasicConstraints()) {
            throw new PathValidationException('v3 certificate must have basicConstraints extension.');
        }
        // verify that cA is set to TRUE
        if (! $extensions->basicConstraints()->isCA()) {
            throw new PathValidationException('Certificate is not a CA certificate.');
        }
    }

    /**
     * Process pathLenConstraint.
     */
    private function processPathLengthContraint(ValidatorState $state, Certificate $cert): ValidatorState
    {
        $extensions = $cert->tbsCertificate()
            ->extensions();
        if ($extensions->hasBasicConstraints()) {
            $ext = $extensions->basicConstraints();
            if ($ext->hasPathLen()) {
                if ($ext->pathLen() < $state->maxPathLength()) {
                    $state = $state->withMaxPathLength($ext->pathLen());
                }
            }
        }
        return $state;
    }

    /**
     * Process the extensions of the current certificate.
     *
     * Every critical extension must be recognized and processed, otherwise the certificate must be rejected.
     *
     * @see https://tools.ietf.org/html/rfc5280#section-4.2
     */
    private function processExtensions(ValidatorState $state, Certificate $cert): ValidatorState
    {
        $recognized = [...self::PROCESSED_EXTENSIONS, ...$this->config->additionalCriticalExtensions()];
        foreach ($cert->tbsCertificate()->extensions() as $extension) {
            if (! $extension->isCritical()) {
                continue;
            }
            if (! in_array($extension->oid(), $recognized, true)) {
                throw new PathValidationException(sprintf(
                    'Certificate contains an unhandled critical extension: %s.',
                    $extension->extensionName()
                ));
            }
        }
        return $state;
    }

    private function calculatePolicyIntersection(ValidatorState $state): ValidatorState
    {
        // (i) If the valid_policy_tree is NULL, the intersection is NULL
        if (! $state->hasValidPolicyTree()) {
            return $state;
        }
        // (ii) If the valid_policy_tree is not NULL and
        // the user-initial-policy-set is any-policy, the intersection
        // is the entire valid_policy_tree
        $initial_policies = $this->config->policySet();
        if (in_array(PolicyInformation::OID_ANY_POLICY, $initial_policies, true)) {
            return $state;
        }
        // (iii) If the valid_policy_tree is not NULL and the
        // user-initial-policy-set is not any-policy, calculate
        // the intersection of the valid_policy_tree and the
        // user-initial-policy-set as follows
        return $state->validPolicyTree()
            ->calculateIntersection($state, $initial_policies);
    }
}

<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\CertificationPath;

use ArrayIterator;
use function count;
use Countable;
use const E_USER_DEPRECATED;
use IteratorAggregate;
use LogicException;
use SpomkyLabs\Pki\CryptoBridge\Crypto;
use SpomkyLabs\Pki\X509\Certificate\Certificate;
use SpomkyLabs\Pki\X509\Certificate\CertificateBundle;
use SpomkyLabs\Pki\X509\Certificate\CertificateChain;
use SpomkyLabs\Pki\X509\CertificationPath\Exception\PathValidationException;
use SpomkyLabs\Pki\X509\CertificationPath\PathBuilding\CertificationPathBuilder;
use SpomkyLabs\Pki\X509\CertificationPath\PathValidation\PathValidationConfig;
use SpomkyLabs\Pki\X509\CertificationPath\PathValidation\PathValidationResult;
use SpomkyLabs\Pki\X509\CertificationPath\PathValidation\PathValidator;

/**
 * Implements certification path structure.
 *
 * Certification path is a list of certificates from the trust anchor to the end entity certificate, possibly spanning
 * over multiple intermediate certificates.
 *
 * @see https://tools.ietf.org/html/rfc5280#section-3.2
 */
final class CertificationPath implements Countable, IteratorAggregate
{
    /**
     * Certification path.
     *
     * @var Certificate[]
     */
    private readonly array $certificates;

    /**
     * Whether the path was assembled from a chain that a peer supplied.
     *
     * The first certificate of such a path is whatever the peer put there, so it must never serve as the trust
     * anchor.
     */
    private bool $fromPeerSuppliedChain = false;

    /**
     * Whether the path's first certificate came out of a trust list the application supplied.
     *
     * Such a head is a trust anchor the application chose, so validating against it is sound and needs no warning.
     */
    private bool $anchoredInTrustList = false;

    /**
     * @param Certificate ...$certificates Certificates from the trust anchor
     * to the target end-entity certificate
     */
    private function __construct(Certificate ...$certificates)
    {
        $this->certificates = $certificates;
    }

    /**
     * Initialize from certificates ordered from the trust anchor to the target end-entity certificate.
     *
     * When no trust anchor is set on the configuration, validate() falls back to the first certificate given here.
     * Only pass certificates in an order you control: a path built out of what a peer sent, with its own root at the
     * head, would then be validated against that root. Use fromCertificateChain() for peer-supplied material, or
     * toTarget() to build the path from a bundle of trust anchors.
     */
    public static function create(Certificate ...$certificates): self
    {
        return new self(...$certificates);
    }

    /**
     * Initialize from certificates whose first one came out of a trust list the application supplied.
     *
     * @internal Used by CertificationPathBuilder, which only ever heads a path with a certificate taken from the
     * trust list it was given.
     */
    public static function fromTrustList(Certificate ...$certificates): self
    {
        $path = self::create(...$certificates);
        $path->anchoredInTrustList = true;
        return $path;
    }

    /**
     * Initialize from a certificate chain.
     *
     * A chain comes from a peer, so its topmost certificate is attacker-controlled and cannot be trusted. The
     * resulting path therefore refuses to validate unless the configuration carries an explicit trust anchor.
     */
    public static function fromCertificateChain(CertificateChain $chain): self
    {
        $path = self::create(...array_reverse($chain->certificates(), false));
        $path->fromPeerSuppliedChain = true;
        return $path;
    }

    /**
     * Build certification path to given target.
     *
     * @param Certificate $target Target end-entity certificate
     * @param CertificateBundle $trust_anchors List of trust anchors
     * @param null|CertificateBundle $intermediate Optional intermediate certificates
     */
    public static function toTarget(
        Certificate $target,
        CertificateBundle $trust_anchors,
        ?CertificateBundle $intermediate = null
    ): self {
        return CertificationPathBuilder::create($trust_anchors)->shortestPathToTarget($target, $intermediate);
    }

    /**
     * Build certification path from given trust anchor to target certificate, using intermediate certificates from
     * given bundle.
     *
     * @param Certificate $trust_anchor Trust anchor certificate
     * @param Certificate $target Target end-entity certificate
     * @param null|CertificateBundle $intermediate Optional intermediate certificates
     */
    public static function fromTrustAnchorToTarget(
        Certificate $trust_anchor,
        Certificate $target,
        ?CertificateBundle $intermediate = null
    ): self {
        return self::toTarget($target, CertificateBundle::create($trust_anchor), $intermediate);
    }

    /**
     * Get certificates.
     *
     * @return Certificate[]
     */
    public function certificates(): array
    {
        return $this->certificates;
    }

    /**
     * Get the first certificate of the path.
     *
     * This is the certificate validate() falls back to when the configuration names no trust anchor. It is the path's
     * head, not a certificate the library has established any trust in.
     */
    public function trustAnchorCertificate(): Certificate
    {
        if (count($this->certificates) === 0) {
            throw new LogicException('No certificates.');
        }
        return $this->certificates[0];
    }

    /**
     * Get the end-entity certificate from the path.
     */
    public function endEntityCertificate(): Certificate
    {
        if (count($this->certificates) === 0) {
            throw new LogicException('No certificates.');
        }
        return $this->certificates[count($this->certificates) - 1];
    }

    /**
     * Get certification path as a certificate chain.
     */
    public function certificateChain(): CertificateChain
    {
        return CertificateChain::create(...array_reverse($this->certificates, false));
    }

    /**
     * Check whether certification path starts with one ore more given certificates in parameter order.
     *
     * @param Certificate ...$certs Certificates
     */
    public function startsWith(Certificate ...$certs): bool
    {
        $n = count($certs);
        if ($n > count($this->certificates)) {
            return false;
        }
        for ($i = 0; $i < $n; ++$i) {
            if (! $certs[$i]->equals($this->certificates[$i])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Validate certification path.
     *
     * The trust anchor is an input to the validation process, not a part of the path: set it on the configuration
     * with PathValidationConfig::withTrustAnchor(). When it is left unset, the first certificate of the path is used
     * instead, which is only sound for a path whose head the application chose itself.
     *
     * A path built by fromCertificateChain() never qualifies, since a peer decided what its first certificate is, and
     * validating it against its own root would verify nothing. Such a path requires an explicit trust anchor and
     * throws without one.
     *
     * @param null|Crypto $crypto Crypto engine, use default if not set
     */
    public function validate(PathValidationConfig $config, ?Crypto $crypto = null): PathValidationResult
    {
        if (! $config->hasTrustAnchor()) {
            if ($this->fromPeerSuppliedChain) {
                throw new PathValidationException(
                    'The path was built from a peer-supplied certificate chain, so its first certificate cannot '
                    . 'serve as the trust anchor. Set one with PathValidationConfig::withTrustAnchor(), or build '
                    . 'the path with CertificationPath::toTarget() from a bundle of trusted certificates.'
                );
            }
            if (! $this->anchoredInTrustList) {
                // The guard above is attached to the constructor that was used rather than to the invariant it
                // protects, and create() is the constructor an integrator reaches for first. Nothing in the
                // result tells a chain anchored in the caller's trust store from one anchored in the attacker's,
                // so the fallback is announced here and will be removed in the next major release.
                @trigger_error(
                    'Validating a certification path without an explicit trust anchor is deprecated and will '
                    . 'throw in the next major release. The first certificate of the path is being used as the '
                    . 'trust anchor. Name the anchor with PathValidationConfig::withTrustAnchor(), or build the '
                    . 'path with CertificationPath::toTarget() from a bundle of trusted certificates.',
                    E_USER_DEPRECATED
                );
            }
        }
        $crypto ??= Crypto::getDefault();
        return PathValidator::create($crypto, $config, ...$this->certificates)->validate();
    }

    /**
     * @see \Countable::count()
     */
    public function count(): int
    {
        return count($this->certificates);
    }

    /**
     * Get iterator for certificates.
     *
     * @see \IteratorAggregate::getIterator()
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->certificates);
    }
}

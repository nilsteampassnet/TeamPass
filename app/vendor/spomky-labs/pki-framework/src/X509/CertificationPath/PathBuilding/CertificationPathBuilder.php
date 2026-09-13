<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\CertificationPath\PathBuilding;

use function count;
use function hash;
use function spl_object_id;
use SpomkyLabs\Pki\X501\StringPrep\Exception\StringPreparationException;
use SpomkyLabs\Pki\X509\Certificate\Certificate;
use SpomkyLabs\Pki\X509\Certificate\CertificateBundle;
use SpomkyLabs\Pki\X509\CertificationPath\CertificationPath;
use SpomkyLabs\Pki\X509\CertificationPath\Exception\PathBuildingException;

/**
 * Class for resolving certification paths.
 *
 * @see https://tools.ietf.org/html/rfc4158
 */
final class CertificationPathBuilder
{
    /**
     * Maximum number of certificates a path may hold while being resolved.
     *
     * The limit bounds the depth of the exploration, on top of the loop detection.
     */
    private const MAX_PATH_LENGTH = 10;

    /**
     * Maximum number of certification paths a single resolution step may produce.
     *
     * A bundle of mutually compatible certificates yields an exponential number of paths even when it holds no loop,
     * so the number of paths must be bounded on its own.
     */
    private const MAX_PATHS = 100;

    /**
     * Maximum number of certificates the resolution may examine before giving up.
     *
     * Counting the paths that come out bounds nothing when none do: a sub-call that returns no path leaves the count
     * untouched, having already explored its whole subtree, and a bundle whose certificates chain to no trust anchor
     * is exactly that shape. The work has to be counted where it is done.
     */
    private const MAX_VISITED_NODES = 1000;

    /**
     * Number of certificates examined by the resolution in progress.
     */
    private int $visitedNodes = 0;

    /**
     * Identity of each certificate seen by the resolution in progress, keyed by object id.
     *
     * @var array<int, string>
     */
    private array $identities = [];

    /**
     * @param CertificateBundle $trustList List of trust anchors
     */
    private function __construct(
        private readonly CertificateBundle $trustList
    ) {
    }

    public static function create(CertificateBundle $trustList): self
    {
        return new self($trustList);
    }

    /**
     * Get all certification paths to given target certificate from any trust anchor.
     *
     * @param Certificate $target Target certificate
     * @param null|CertificateBundle $intermediate Optional intermediate certificates
     *
     * @return CertificationPath[]
     */
    public function allPathsToTarget(Certificate $target, ?CertificateBundle $intermediate = null): array
    {
        $this->visitedNodes = 0;
        $this->identities = [];
        try {
            $paths = $this->resolvePathsToTarget($target, $intermediate);
        } catch (StringPreparationException $e) {
            // matching an issuer name is a name comparison, and a name that cannot be prepared for comparison
            // stops the resolution rather than being reported as not equal. Report it as a building failure like
            // every other, instead of letting the comparison's own exception escape.
            throw new PathBuildingException(
                'A certificate name cannot be prepared for comparison: ' . $e->getMessage(),
                0,
                $e
            );
        }
        // every path is headed by a certificate taken from the trust list this builder was given, so its head is
        // a trust anchor the application chose
        return array_map(static fn ($certs) => CertificationPath::fromTrustList(...$certs), $paths);
    }

    /**
     * Get the shortest path to given target certificate from any trust anchor.
     *
     * @param Certificate $target Target certificate
     * @param null|CertificateBundle $intermediate Optional intermediate certificates
     */
    public function shortestPathToTarget(
        Certificate $target,
        ?CertificateBundle $intermediate = null
    ): CertificationPath {
        $paths = $this->allPathsToTarget($target, $intermediate);
        if (count($paths) === 0) {
            throw new PathBuildingException('No certification paths.');
        }
        // a comparison that never returns 0 leaves the order among equal length paths to PHP's sort; the
        // spaceship operator makes the choice deterministic instead. When more than one path is possible, use
        // allPathsToTarget() and validate them in turn rather than committing to this one.
        usort($paths, static fn ($a, $b) => count($a) <=> count($b));
        return reset($paths);
    }

    /**
     * Find all issuers of the target certificate from a given bundle.
     *
     * @param Certificate $target Target certificate
     * @param CertificateBundle $bundle Certificates to search
     *
     * @return Certificate[]
     */
    private function findIssuers(Certificate $target, CertificateBundle $bundle): array
    {
        $issuer_name = $target->tbsCertificate()
            ->issuer();
        $extensions = $target->tbsCertificate()
            ->extensions();
        // find by authority key identifier
        $candidates = [];
        if ($extensions->hasAuthorityKeyIdentifier()) {
            $ext = $extensions->authorityKeyIdentifier();
            if ($ext->hasKeyIdentifier()) {
                $candidates = $bundle->allBySubjectKeyIdentifier($ext->keyIdentifier());
            }
        }
        if (count($candidates) === 0) {
            // RFC 5280 requires the authority key identifier on a CA-issued certificate, but plenty of real
            // certificates omit it and the issuer name identifies the issuer on its own. Without the fallback
            // such a certificate can never be chained, which pushes integrators towards CertificationPath's
            // unsafe constructor. RFC 4158 sect. 3.5.12 expects a builder to match on the name.
            $candidates = $bundle->all();
        }
        $issuers = [];
        foreach ($candidates as $issuer) {
            // check that issuer name matches
            if ($issuer->tbsCertificate()->subject()->equals($issuer_name)) {
                $issuers[] = $issuer;
            }
        }
        return $issuers;
    }

    /**
     * Resolve all possible certification paths from any trust anchor to the target certificate, using optional
     * intermediate certificates.
     *
     * Helper method for allPathsToTarget to be called recursively.
     *
     * @param array<string, true> $visited DER encoding of the certificates already on the path being resolved
     *
     * @return array<int, array<Certificate>> Array of arrays containing path certificates
     */
    private function resolvePathsToTarget(
        Certificate $target,
        ?CertificateBundle $intermediate = null,
        array $visited = []
    ): array {
        // count the work where it is done, so that a subtree producing no path is still bounded
        if (++$this->visitedNodes > self::MAX_VISITED_NODES) {
            throw new PathBuildingException('Too many certificates examined while resolving a certification path.');
        }
        // array of possible paths
        $paths = [];
        // signed by certificate in the trust list
        foreach ($this->findIssuers($target, $this->trustList) as $issuer) {
            // if target is self-signed, path consists of only
            // the target certificate
            if ($target->equals($issuer)) {
                $paths[] = [$target];
            } else {
                $paths[] = [$issuer, $target];
            }
        }
        if (isset($intermediate)) {
            // the target now belongs to the path being resolved
            $visited[$this->identity($target)] = true;
            // stop exploring once the path has grown past the maximum length
            if (count($visited) < self::MAX_PATH_LENGTH) {
                // signed by intermediate certificate
                foreach ($this->findIssuers($target, $intermediate) as $issuer) {
                    // intermediate certificate must not be self-signed
                    if ($issuer->isSelfIssued()) {
                        continue;
                    }
                    // an issuer already on the path being resolved would close a loop
                    if (isset($visited[$this->identity($issuer)])) {
                        continue;
                    }
                    // resolve paths to issuer
                    $subpaths = $this->resolvePathsToTarget($issuer, $intermediate, $visited);
                    foreach ($subpaths as $path) {
                        $paths[] = array_merge($path, [$target]);
                        $this->assertPathCount($paths);
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * Ensure that the resolution has not produced more paths than allowed.
     *
     * The check happens as the paths are collected, so that a bundle crafted to blow up combinatorially is caught
     * before the exploration has done the work.
     *
     * @param array<int, array<Certificate>> $paths
     */
    private function assertPathCount(array $paths): void
    {
        if (count($paths) > self::MAX_PATHS) {
            throw new PathBuildingException('Too many certification paths.');
        }
    }

    /**
     * Identity of a certificate for the loop detection, computed once per certificate.
     *
     * The loop check ran on a fresh DER encoding of the same certificate at every node and for every candidate
     * issuer, which is far more expensive than the comparison it guards. The encodings are kept in a map keyed by
     * object, so each certificate is encoded once for the lifetime of the builder.
     */
    private function identity(Certificate $certificate): string
    {
        // every certificate the resolution sees comes from the target or from a bundle the caller holds, so none
        // of them is collected while the resolution runs and an object id cannot be reused underneath the cache
        $id = spl_object_id($certificate);
        if (! isset($this->identities[$id])) {
            $this->identities[$id] = hash('sha256', $certificate->toDER(), true);
        }

        return $this->identities[$id];
    }
}

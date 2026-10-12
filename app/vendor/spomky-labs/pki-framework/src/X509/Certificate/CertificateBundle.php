<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\Certificate;

use ArrayIterator;
use function count;
use Countable;
use IteratorAggregate;
use SpomkyLabs\Pki\CryptoEncoding\PEM;
use SpomkyLabs\Pki\CryptoEncoding\PEMBundle;

/**
 * Implements a list of certificates.
 */
final class CertificateBundle implements Countable, IteratorAggregate
{
    /**
     * Certificates.
     *
     * @var Certificate[]
     */
    private array $certs;

    /**
     * Mapping from public key id to array of certificates.
     *
     * @var null|(Certificate[])[]
     */
    private ?array $keyIdMap = null;

    /**
     * @param Certificate ...$certs Certificate objects
     */
    private function __construct(Certificate ...$certs)
    {
        $this->certs = $certs;
    }

    /**
     * Reset internal cached variables on clone.
     */
    public function __clone()
    {
        $this->keyIdMap = null;
    }

    public static function create(Certificate ...$certs): self
    {
        return new self(...$certs);
    }

    /**
     * Initialize from PEMs.
     *
     * @param PEM ...$pems PEM objects
     */
    public static function fromPEMs(PEM ...$pems): self
    {
        $certs = array_map(Certificate::fromPEM(...), $pems);
        return self::create(...$certs);
    }

    /**
     * Initialize from PEM bundle.
     */
    public static function fromPEMBundle(PEMBundle $pem_bundle): self
    {
        return self::fromPEMs(...$pem_bundle->all());
    }

    /**
     * Get self with certificates added.
     */
    public function withCertificates(Certificate ...$cert): self
    {
        $obj = clone $this;
        $obj->certs = array_merge($obj->certs, $cert);
        return $obj;
    }

    /**
     * Get self with certificates from PEMBundle added.
     */
    public function withPEMBundle(PEMBundle $pem_bundle): self
    {
        $certs = $this->certs;
        foreach ($pem_bundle as $pem) {
            $certs[] = Certificate::fromPEM($pem);
        }
        return self::create(...$certs);
    }

    /**
     * Get self with single certificate from PEM added.
     */
    public function withPEM(PEM $pem): self
    {
        $certs = $this->certs;
        $certs[] = Certificate::fromPEM($pem);
        return self::create(...$certs);
    }

    /**
     * Check whether bundle contains a given certificate.
     */
    public function contains(Certificate $cert): bool
    {
        // the computed identifier is derived from the key itself, so a certificate is always registered under it
        $id = $cert->tbsCertificate()
            ->subjectPublicKeyInfo()
            ->keyIdentifier();
        $map = $this->_getKeyIdMap();
        if (! isset($map[$id])) {
            return false;
        }
        foreach ($map[$id] as $c) {
            /** @var Certificate $c */
            if ($cert->equals($c)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all certificates that have given subject key identifier.
     *
     * A certificate is indexed both under the identifier computed from its subjectPublicKeyInfo and under the one
     * its subjectKeyIdentifier extension declares, when the two differ. The declared value is arbitrary data that
     * is never checked against the key, so indexing on it alone let a single certificate whose extension repeats
     * a real intermediate's identifier displace that intermediate and deny validation of a good chain.
     *
     * The certificates whose computed identifier matches come first, ahead of those that only claim the value, so
     * a caller that takes the first match takes the one that actually holds the key.
     *
     * @return Certificate[]
     */
    public function allBySubjectKeyIdentifier(string $id): array
    {
        $map = $this->_getKeyIdMap();
        if (! isset($map[$id])) {
            return [];
        }
        $computed = [];
        $declared = [];
        foreach ($map[$id] as $cert) {
            $keyId = $cert->tbsCertificate()
                ->subjectPublicKeyInfo()
                ->keyIdentifier();
            if ($keyId === $id) {
                $computed[] = $cert;
            } else {
                $declared[] = $cert;
            }
        }
        return array_merge($computed, $declared);
    }

    /**
     * Get all certificates in a bundle.
     *
     * @return Certificate[]
     */
    public function all(): array
    {
        return $this->certs;
    }

    /**
     * @see \Countable::count()
     */
    public function count(): int
    {
        return count($this->certs);
    }

    /**
     * Get iterator for certificates.
     *
     * @see \IteratorAggregate::getIterator()
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->certs);
    }

    /**
     * Get certificate mapping by public key id.
     *
     * @return (Certificate[])[]
     */
    private function _getKeyIdMap(): array
    {
        // lazily build mapping
        if (! isset($this->keyIdMap)) {
            $this->keyIdMap = [];
            foreach ($this->certs as $cert) {
                foreach (self::_getCertKeyIds($cert) as $id) {
                    if (! isset($this->keyIdMap[$id])) {
                        $this->keyIdMap[$id] = [];
                    }
                    $this->keyIdMap[$id][] = $cert;
                }
            }
        }
        return $this->keyIdMap;
    }

    /**
     * Get the public key ids the certificate is to be indexed under.
     *
     * The identifier computed from the subjectPublicKeyInfo comes first: it is derived from the key and cannot be
     * chosen. The subjectKeyIdentifier extension is added alongside it, so a lookup on the value another
     * certificate's authorityKeyIdentifier declares still finds this one, without letting that claim be the only
     * way the certificate can be found.
     *
     * @return list<string>
     */
    private static function _getCertKeyIds(Certificate $cert): array
    {
        $ids = [$cert->tbsCertificate()
            ->subjectPublicKeyInfo()
            ->keyIdentifier()];
        $exts = $cert->tbsCertificate()
            ->extensions();
        if ($exts->hasSubjectKeyIdentifier()) {
            $declared = $exts->subjectKeyIdentifier()
                ->keyIdentifier();
            if ($declared !== $ids[0]) {
                $ids[] = $declared;
            }
        }
        return $ids;
    }
}

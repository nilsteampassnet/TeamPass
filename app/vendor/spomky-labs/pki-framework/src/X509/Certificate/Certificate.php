<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\Certificate;

use RuntimeException;
use SpomkyLabs\Pki\ASN1\Type\Constructed\Sequence;
use SpomkyLabs\Pki\CryptoBridge\Crypto;
use SpomkyLabs\Pki\CryptoEncoding\PEM;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\AlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Feature\SignatureAlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\PublicKeyInfo;
use SpomkyLabs\Pki\CryptoTypes\Signature\Signature;
use SpomkyLabs\Pki\X509\Feature\SignedDER;
use Stringable;
use UnexpectedValueException;

/**
 * Implements *Certificate* ASN.1 type.
 *
 * @see https://tools.ietf.org/html/rfc5280#section-4.1
 */
final class Certificate implements Stringable
{
    use SignedDER;

    /**
     * @param TBSCertificate $tbsCertificate "To be signed" certificate information.
     * @param SignatureAlgorithmIdentifier $signatureAlgorithm Signature algorithm.
     * @param Signature $signatureValue Signature value.
     * @param null|string $tbsCertificateDER Encoding of the tbsCertificate as it was received, when decoded from
     * DER.
     */
    private function __construct(
        private readonly TBSCertificate $tbsCertificate,
        private readonly SignatureAlgorithmIdentifier $signatureAlgorithm,
        private readonly Signature $signatureValue,
        private readonly ?string $tbsCertificateDER = null
    ) {
    }

    /**
     * Get certificate as a PEM formatted string.
     */
    public function __toString(): string
    {
        return $this->toPEM()
            ->string();
    }

    public static function create(
        TBSCertificate $tbsCertificate,
        SignatureAlgorithmIdentifier $signatureAlgorithm,
        Signature $signatureValue
    ): self {
        return new self($tbsCertificate, $signatureAlgorithm, $signatureValue);
    }

    /**
     * Initialize from ASN.1.
     */
    public static function fromASN1(Sequence $seq): self
    {
        $tbsCert = TBSCertificate::fromASN1($seq->at(0)->asSequence());
        $algo = AlgorithmIdentifier::fromASN1($seq->at(1)->asSequence());
        if (! $algo instanceof SignatureAlgorithmIdentifier) {
            throw new UnexpectedValueException('Unsupported signature algorithm ' . $algo->oid() . '.');
        }
        // RFC 5280 section 4.1.1.2: signatureAlgorithm sits outside the signed part, so anyone can change it. It
        // must repeat the algorithm of the tbsCertificate, which is signed.
        if ($algo->oid() !== $tbsCert->signature()->oid()) {
            throw new UnexpectedValueException(
                'Signature algorithm ' . $algo->oid() . ' does not match the algorithm ' .
                $tbsCert->signature()
                    ->oid() . ' of the tbsCertificate.'
            );
        }
        $signature = Signature::fromSignatureData($seq->at(2)->asBitString()->string(), $algo);
        return self::create($tbsCert, $algo, $signature);
    }

    /**
     * Initialize from DER.
     */
    public static function fromDER(string $data): self
    {
        [$seq, $tbsCertificateDER] = self::decodeSignedDER($data, 'Certificate');
        $cert = self::fromASN1($seq);
        // Keep the tbsCertificate exactly as it arrived, so that verify() checks the signature over the bytes the
        // issuer signed rather than over what the parser produced.
        return new self($cert->tbsCertificate, $cert->signatureAlgorithm, $cert->signatureValue, $tbsCertificateDER);
    }

    /**
     * Initialize from PEM.
     */
    public static function fromPEM(PEM $pem): self
    {
        if ($pem->type() !== PEM::TYPE_CERTIFICATE) {
            throw new UnexpectedValueException('Invalid PEM type.');
        }
        return self::fromDER($pem->data());
    }

    /**
     * Get certificate information.
     */
    public function tbsCertificate(): TBSCertificate
    {
        return $this->tbsCertificate;
    }

    /**
     * Get signature algorithm.
     */
    public function signatureAlgorithm(): SignatureAlgorithmIdentifier
    {
        return $this->signatureAlgorithm;
    }

    /**
     * Get signature value.
     */
    public function signatureValue(): Signature
    {
        return $this->signatureValue;
    }

    /**
     * Check whether certificate is self-issued.
     */
    public function isSelfIssued(): bool
    {
        return $this->tbsCertificate->subject()
            ->equals($this->tbsCertificate->issuer());
    }

    /**
     * Check whether this is the very same certificate as another, comparing the encodings octet by octet.
     *
     * The comparison used to be on the serial number, the key identifier of the subject public key and the subject
     * DN alone, which are three public values an attacker is free to repeat: a certificate agreeing on them but
     * differing in issuer, validity, every extension and the signature was reported as the same certificate, and
     * `CertificateBundle::contains()` answered "yes" for a certificate that was not in the bundle.
     *
     * @param Certificate $cert Certificate to compare to
     */
    public function equals(self $cert): bool
    {
        return hash_equals($this->toDER(), $cert->toDER());
    }

    /**
     * Check whether the certificate names the same subject, holding the same key, with the same serial number.
     *
     * This is not an identity check: two certificates may agree on all three and still be issued by different
     * issuers, carry different extensions and be signed by different keys. Use `equals()` to answer "is this the
     * certificate I have", and this predicate only to group certificates that were issued for the same subject
     * key, such as a re-issued certificate and the one it replaces.
     *
     * @param Certificate $cert Certificate to compare to
     */
    public function hasEqualSubjectIdentity(self $cert): bool
    {
        return $this->_hasEqualSerialNumber($cert) &&
            $this->_hasEqualPublicKey($cert) && $this->_hasEqualSubject($cert);
    }

    /**
     * Generate ASN.1 structure.
     */
    public function toASN1(): Sequence
    {
        return Sequence::create(
            $this->tbsCertificate->toASN1(),
            $this->signatureAlgorithm->toASN1(),
            $this->signatureValue->bitString()
        );
    }

    /**
     * Get certificate as a DER.
     */
    public function toDER(): string
    {
        return $this->toASN1()
            ->toDER();
    }

    /**
     * Get certificate as a PEM.
     */
    public function toPEM(): PEM
    {
        return PEM::create(PEM::TYPE_CERTIFICATE, $this->toDER());
    }

    /**
     * Verify certificate signature.
     *
     * @param PublicKeyInfo $pubkey_info Issuer's public key
     * @param null|Crypto $crypto Crypto engine, use default if not set
     *
     * @return bool True if certificate signature is valid
     */
    public function verify(PublicKeyInfo $pubkey_info, ?Crypto $crypto = null): bool
    {
        $crypto ??= Crypto::getDefault();
        // A certificate built in memory was never received as bytes; re-encoding it is then the only option, and
        // the correct one.
        $data = $this->tbsCertificateDER ?? $this->tbsCertificate->toASN1()
            ->toDER();
        // a bool returning predicate must not throw on a signature it cannot check: the algorithm is named by
        // the certificate, so an attacker picks it, and `if (! $cert->verify($key))` in application code would
        // otherwise become an unhandled fatal. An unsupported algorithm, a key the algorithm does not match and
        // an engine level failure all mean the same thing to the caller: the signature was not verified.
        try {
            return $crypto->verify($data, $this->signatureValue, $pubkey_info, $this->signatureAlgorithm);
        } catch (RuntimeException|UnexpectedValueException) {
            return false;
        }
    }

    /**
     * Check whether certificate has serial number equal to another.
     */
    private function _hasEqualSerialNumber(self $cert): bool
    {
        $sn1 = $this->tbsCertificate->serialNumber();
        $sn2 = $cert->tbsCertificate->serialNumber();
        return $sn1 === $sn2;
    }

    /**
     * Check whether certificate has public key equal to another.
     */
    private function _hasEqualPublicKey(self $cert): bool
    {
        $kid1 = $this->tbsCertificate->subjectPublicKeyInfo()
            ->keyIdentifier();
        $kid2 = $cert->tbsCertificate->subjectPublicKeyInfo()
            ->keyIdentifier();
        return $kid1 === $kid2;
    }

    /**
     * Check whether certificate has subject equal to another.
     */
    private function _hasEqualSubject(self $cert): bool
    {
        $dn1 = $this->tbsCertificate->subject();
        $dn2 = $cert->tbsCertificate->subject();
        return $dn1->equals($dn2);
    }
}

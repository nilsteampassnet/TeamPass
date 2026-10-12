<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\AttributeCertificate;

use RuntimeException;
use SpomkyLabs\Pki\ASN1\Type\Constructed\Sequence;
use SpomkyLabs\Pki\CryptoBridge\Crypto;
use SpomkyLabs\Pki\CryptoEncoding\PEM;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\AlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Feature\SignatureAlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\PublicKeyInfo;
use SpomkyLabs\Pki\CryptoTypes\Signature\Signature;
use SpomkyLabs\Pki\X509\Certificate\Certificate;
use SpomkyLabs\Pki\X509\Feature\SignedDER;
use Stringable;
use UnexpectedValueException;

/**
 * Implements *AttributeCertificate* ASN.1 type.
 *
 * @see https://tools.ietf.org/html/rfc5755#section-4.1
 */
final class AttributeCertificate implements Stringable
{
    use SignedDER;

    /**
     * @param AttributeCertificateInfo $acInfo Attribute certificate information.
     * @param SignatureAlgorithmIdentifier $signatureAlgorithm Signature algorithm.
     * @param Signature $signatureValue Signature value.
     * @param null|string $acInfoDER Encoding of the acinfo as it was received, when decoded from DER.
     */
    private function __construct(
        private readonly AttributeCertificateInfo $acInfo,
        private readonly SignatureAlgorithmIdentifier $signatureAlgorithm,
        private readonly Signature $signatureValue,
        private readonly ?string $acInfoDER = null
    ) {
    }

    /**
     * Get attribute certificate as a PEM formatted string.
     */
    public function __toString(): string
    {
        return $this->toPEM()
            ->string();
    }

    public static function create(
        AttributeCertificateInfo $acInfo,
        SignatureAlgorithmIdentifier $signatureAlgorithm,
        Signature $signatureValue
    ): self {
        return new self($acInfo, $signatureAlgorithm, $signatureValue);
    }

    /**
     * Initialize from ASN.1.
     */
    public static function fromASN1(Sequence $seq): self
    {
        $acinfo = AttributeCertificateInfo::fromASN1($seq->at(0)->asSequence());
        $algo = AlgorithmIdentifier::fromASN1($seq->at(1)->asSequence());
        if (! $algo instanceof SignatureAlgorithmIdentifier) {
            throw new UnexpectedValueException('Unsupported signature algorithm ' . $algo->oid() . '.');
        }
        // RFC 5755 section 4.1: signatureAlgorithm sits outside the signed part, so anyone can change it. It must
        // repeat the algorithm of the acinfo, which is signed.
        if ($algo->oid() !== $acinfo->signature()->oid()) {
            throw new UnexpectedValueException(
                'Signature algorithm ' . $algo->oid() . ' does not match the algorithm ' .
                $acinfo->signature()
                    ->oid() . ' of the acinfo.'
            );
        }
        $signature = Signature::fromSignatureData($seq->at(2)->asBitString()->string(), $algo);
        return self::create($acinfo, $algo, $signature);
    }

    /**
     * Initialize from DER data.
     */
    public static function fromDER(string $data): self
    {
        [$seq, $acInfoDER] = self::decodeSignedDER($data, 'AttributeCertificate');
        $ac = self::fromASN1($seq);
        // Keep the acinfo exactly as it arrived, so that verify() checks the signature over the bytes the issuer
        // signed rather than over what the parser produced.
        return new self($ac->acInfo, $ac->signatureAlgorithm, $ac->signatureValue, $acInfoDER);
    }

    /**
     * Initialize from PEM.
     */
    public static function fromPEM(PEM $pem): self
    {
        if ($pem->type() !== PEM::TYPE_ATTRIBUTE_CERTIFICATE) {
            throw new UnexpectedValueException('Invalid PEM type.');
        }
        return self::fromDER($pem->data());
    }

    /**
     * Get attribute certificate info.
     */
    public function acinfo(): AttributeCertificateInfo
    {
        return $this->acInfo;
    }

    /**
     * Get signature algorithm identifier.
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
     * Get ASN.1 structure.
     */
    public function toASN1(): Sequence
    {
        return Sequence::create(
            $this->acInfo->toASN1(),
            $this->signatureAlgorithm->toASN1(),
            $this->signatureValue->bitString()
        );
    }

    /**
     * Get attribute certificate as a DER.
     */
    public function toDER(): string
    {
        return $this->toASN1()
            ->toDER();
    }

    /**
     * Get attribute certificate as a PEM.
     */
    public function toPEM(): PEM
    {
        return PEM::create(PEM::TYPE_ATTRIBUTE_CERTIFICATE, $this->toDER());
    }

    /**
     * Check whether attribute certificate is issued to the subject identified by given public key certificate.
     *
     * @param Certificate $cert Certificate
     */
    public function isHeldBy(Certificate $cert): bool
    {
        if (! $this->acInfo->holder()->identifiesPKC($cert)) {
            return false;
        }
        return true;
    }

    /**
     * Check whether attribute certificate is issued by given public key certificate.
     *
     * @param Certificate $cert Certificate
     */
    public function isIssuedBy(Certificate $cert): bool
    {
        if (! $this->acInfo->issuer()->identifiesPKC($cert)) {
            return false;
        }
        return true;
    }

    /**
     * Verify signature.
     *
     * @param PublicKeyInfo $pubkey_info Signer's public key
     * @param null|Crypto $crypto Crypto engine, use default if not set
     */
    public function verify(PublicKeyInfo $pubkey_info, ?Crypto $crypto = null): bool
    {
        $crypto ??= Crypto::getDefault();
        // An attribute certificate built in memory was never received as bytes; re-encoding it is then the only
        // option, and the correct one.
        $data = $this->acInfoDER ?? $this->acInfo->toASN1()
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
}

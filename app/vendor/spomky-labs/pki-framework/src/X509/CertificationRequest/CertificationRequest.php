<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\CertificationRequest;

use RuntimeException;
use SpomkyLabs\Pki\ASN1\Type\Constructed\Sequence;
use SpomkyLabs\Pki\CryptoBridge\Crypto;
use SpomkyLabs\Pki\CryptoEncoding\PEM;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\AlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Feature\SignatureAlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\Signature\Signature;
use SpomkyLabs\Pki\X509\Feature\SignedDER;
use Stringable;
use UnexpectedValueException;

/**
 * Implements *CertificationRequest* ASN.1 type.
 *
 * @see https://tools.ietf.org/html/rfc2986#section-4
 */
final class CertificationRequest implements Stringable
{
    use SignedDER;

    /**
     * @param CertificationRequestInfo $certificationRequestInfo Certification request information.
     * @param SignatureAlgorithmIdentifier $signatureAlgorithm Signature algorithm.
     * @param Signature $signature Signature value.
     * @param null|string $certificationRequestInfoDER Encoding of the certificationRequestInfo as it was received,
     * when decoded from DER.
     */
    private function __construct(
        private readonly CertificationRequestInfo $certificationRequestInfo,
        private readonly SignatureAlgorithmIdentifier $signatureAlgorithm,
        private readonly Signature $signature,
        private readonly ?string $certificationRequestInfoDER = null
    ) {
    }

    /**
     * Get certification request as a PEM formatted string.
     */
    public function __toString(): string
    {
        return $this->toPEM()
            ->string();
    }

    public static function create(
        CertificationRequestInfo $_certificationRequestInfo,
        SignatureAlgorithmIdentifier $_signatureAlgorithm,
        Signature $_signature
    ): self {
        return new self($_certificationRequestInfo, $_signatureAlgorithm, $_signature);
    }

    /**
     * Initialize from ASN.1.
     */
    public static function fromASN1(Sequence $seq): self
    {
        $info = CertificationRequestInfo::fromASN1($seq->at(0)->asSequence());
        $algo = AlgorithmIdentifier::fromASN1($seq->at(1)->asSequence());
        if (! $algo instanceof SignatureAlgorithmIdentifier) {
            throw new UnexpectedValueException('Unsupported signature algorithm ' . $algo->oid() . '.');
        }
        $signature = Signature::fromSignatureData($seq->at(2)->asBitString()->string(), $algo);
        return self::create($info, $algo, $signature);
    }

    /**
     * Initialize from DER.
     */
    public static function fromDER(string $data): self
    {
        [$seq, $infoDER] = self::decodeSignedDER($data, 'CertificationRequest');
        $csr = self::fromASN1($seq);
        // Keep the certificationRequestInfo exactly as it arrived, so that verify() checks the signature over the
        // bytes the requester signed rather than over what the parser produced.
        return new self($csr->certificationRequestInfo, $csr->signatureAlgorithm, $csr->signature, $infoDER);
    }

    /**
     * Initialize from PEM.
     */
    public static function fromPEM(PEM $pem): self
    {
        if ($pem->type() !== PEM::TYPE_CERTIFICATE_REQUEST) {
            throw new UnexpectedValueException('Invalid PEM type.');
        }
        return self::fromDER($pem->data());
    }

    /**
     * Get certification request info.
     */
    public function certificationRequestInfo(): CertificationRequestInfo
    {
        return $this->certificationRequestInfo;
    }

    /**
     * Get signature algorithm.
     */
    public function signatureAlgorithm(): SignatureAlgorithmIdentifier
    {
        return $this->signatureAlgorithm;
    }

    public function signature(): Signature
    {
        return $this->signature;
    }

    /**
     * Generate ASN.1 structure.
     */
    public function toASN1(): Sequence
    {
        return Sequence::create(
            $this->certificationRequestInfo->toASN1(),
            $this->signatureAlgorithm->toASN1(),
            $this->signature->bitString()
        );
    }

    /**
     * Get certification request as a DER.
     */
    public function toDER(): string
    {
        return $this->toASN1()
            ->toDER();
    }

    /**
     * Get certification request as a PEM.
     */
    public function toPEM(): PEM
    {
        return PEM::create(PEM::TYPE_CERTIFICATE_REQUEST, $this->toDER());
    }

    /**
     * Verify certification request signature.
     *
     * @param null|Crypto $crypto Crypto engine, use default if not set
     *
     * @return bool True if signature matches
     */
    public function verify(?Crypto $crypto = null): bool
    {
        $crypto ??= Crypto::getDefault();
        // A request built in memory was never received as bytes; re-encoding it is then the only option, and the
        // correct one.
        $data = $this->certificationRequestInfoDER ?? $this->certificationRequestInfo->toASN1()
            ->toDER();
        $pk_info = $this->certificationRequestInfo->subjectPKInfo();
        // a bool returning predicate must not throw on a signature it cannot check: the algorithm is named by
        // the certificate, so an attacker picks it, and `if (! $cert->verify($key))` in application code would
        // otherwise become an unhandled fatal. An unsupported algorithm, a key the algorithm does not match and
        // an engine level failure all mean the same thing to the caller: the signature was not verified.
        try {
            return $crypto->verify($data, $this->signature, $pk_info, $this->signatureAlgorithm);
        } catch (RuntimeException|UnexpectedValueException) {
            return false;
        }
    }
}

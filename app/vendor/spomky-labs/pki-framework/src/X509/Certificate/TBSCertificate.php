<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\Certificate;

use Brick\Math\BigInteger;
use function chr;
use function count;
use const E_USER_DEPRECATED;
use function func_num_args;
use function implode;
use function in_array;
use InvalidArgumentException;
use LogicException;
use function ord;
use const PHP_INT_MAX;
use const PHP_INT_MIN;
use SpomkyLabs\Pki\ASN1\Element;
use SpomkyLabs\Pki\ASN1\Exception\DecodeException;
use SpomkyLabs\Pki\ASN1\Type\Constructed\Sequence;
use SpomkyLabs\Pki\ASN1\Type\Primitive\Integer;
use SpomkyLabs\Pki\ASN1\Type\Tagged\ExplicitlyTaggedType;
use SpomkyLabs\Pki\ASN1\Type\Tagged\ImplicitlyTaggedType;
use SpomkyLabs\Pki\CryptoBridge\Crypto;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\AlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Feature\SignatureAlgorithmIdentifier;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\PrivateKeyInfo;
use SpomkyLabs\Pki\CryptoTypes\Asymmetric\PublicKeyInfo;
use SpomkyLabs\Pki\X501\ASN1\Name;
use SpomkyLabs\Pki\X509\Certificate\Extension\AuthorityKeyIdentifierExtension;
use SpomkyLabs\Pki\X509\Certificate\Extension\Extension;
use SpomkyLabs\Pki\X509\Certificate\Extension\SubjectKeyIdentifierExtension;
use SpomkyLabs\Pki\X509\Certificate\Extension\UnknownExtension;
use SpomkyLabs\Pki\X509\CertificationRequest\CertificationRequest;
use function sprintf;
use function strval;
use UnexpectedValueException;

/**
 * Implements *TBSCertificate* ASN.1 type.
 *
 * @see https://tools.ietf.org/html/rfc5280#section-4.1.2
 */
final class TBSCertificate
{
    // Certificate version enumerations
    public const VERSION_1 = 0;

    public const VERSION_2 = 1;

    public const VERSION_3 = 2;

    /**
     * Certificate version.
     */
    private ?int $version = null;

    /**
     * Serial number.
     */
    private ?string $serialNumber = null;

    /**
     * Signature algorithm.
     */
    private ?SignatureAlgorithmIdentifier $signature = null;

    /**
     * Issuer unique identifier.
     */
    private ?UniqueIdentifier $issuerUniqueID = null;

    /**
     * Subject unique identifier.
     */
    private ?UniqueIdentifier $subjectUniqueID = null;

    /**
     * Extensions.
     */
    private Extensions $extensions;

    /**
     * @param Name $subject Certificate subject
     * @param PublicKeyInfo $subjectPublicKeyInfo Subject public key
     * @param Name $issuer Certificate issuer
     * @param Validity $validity Validity period
     */
    private function __construct(
        private Name $subject,
        private PublicKeyInfo $subjectPublicKeyInfo,
        private Name $issuer,
        private Validity $validity
    ) {
        $this->extensions = Extensions::create();
    }

    /**
     * Extensions that are never taken from a certification request.
     *
     * These decide what the certificate is allowed to do, so they belong to the issuer.
     *
     * @var string[]
     */
    public const FORBIDDEN_CSR_EXTENSIONS = [
        Extension::OID_BASIC_CONSTRAINTS,
        Extension::OID_KEY_USAGE,
        Extension::OID_EXT_KEY_USAGE,
        Extension::OID_NAME_CONSTRAINTS,
        Extension::OID_POLICY_CONSTRAINTS,
        Extension::OID_POLICY_MAPPINGS,
        Extension::OID_INHIBIT_ANY_POLICY,
        Extension::OID_CERTIFICATE_POLICIES,
        Extension::OID_AUTHORITY_KEY_IDENTIFIER,
    ];

    /**
     * Smallest random serial number that meets the CA/Browser Forum Baseline Requirements, in octets.
     *
     * They ask for at least 64 bits of CSPRNG output. The sign bit costs one, so eight octets fall just short
     * and nine are needed. Smaller sizes are still accepted, with a deprecation notice.
     *
     * @var int
     */
    public const MIN_RANDOM_SERIAL_SIZE = 9;

    /**
     * Default random serial number size, in octets. RFC 5280 section 4.1.2.2 caps serial numbers at 20 octets.
     *
     * @var int
     */
    public const DEFAULT_RANDOM_SERIAL_SIZE = 20;

    public static function create(
        Name $subject,
        PublicKeyInfo $subjectPublicKeyInfo,
        Name $issuer,
        Validity $validity
    ): self {
        return new self($subject, $subjectPublicKeyInfo, $issuer, $validity);
    }

    /**
     * Initialize from ASN.1.
     */
    public static function fromASN1(Sequence $seq): self
    {
        // the optional fields of a TBSCertificate are distinct, so a repeated tag is not a field but a second copy
        // of one; hasTagged() would keep only the last of them and silently drop what came before
        $seq->assertUniqueTaggedElements('TBSCertificate');
        $idx = 0;
        if ($seq->hasTagged(0)) {
            ++$idx;
            $number = $seq->getTagged(0)
                ->asExplicit()
                ->asInteger()
                ->getValue();
            // calling intNumber() straight away would let brick/math's IntegerOverflowException escape the
            // decoding contract; a version that does not fit in an int cannot be a supported version anyway
            if ($number->isLessThan(PHP_INT_MIN) || $number->isGreaterThan(PHP_INT_MAX)) {
                throw new UnexpectedValueException(
                    sprintf('Unsupported certificate version %s.', $number->toBase(10))
                );
            }
            $version = $number->toInt();
        } else {
            $version = self::VERSION_1;
        }
        $serial = $seq->at($idx++)
            ->asInteger()
            ->number();
        $algo = AlgorithmIdentifier::fromASN1($seq->at($idx++)->asSequence());
        if (! $algo instanceof SignatureAlgorithmIdentifier) {
            throw new UnexpectedValueException('Unsupported signature algorithm ' . $algo->name() . '.');
        }
        $issuer = Name::fromASN1($seq->at($idx++)->asSequence());
        $validity = Validity::fromASN1($seq->at($idx++)->asSequence());
        $subject = Name::fromASN1($seq->at($idx++)->asSequence());
        $pki = PublicKeyInfo::fromASN1($seq->at($idx++)->asSequence());
        $tbs_cert = self::create($subject, $pki, $issuer, $validity)
            ->withVersion($version)
            ->withSerialNumber($serial)
            ->withSignature($algo)
        ;
        if ($seq->hasTagged(1)) {
            $tbs_cert = $tbs_cert->withIssuerUniqueID(UniqueIdentifier::fromASN1(
                $seq->getTagged(1)
                    ->asImplicit(Element::TYPE_BIT_STRING)
                    ->asBitString()
            ));
        }
        if ($seq->hasTagged(2)) {
            $tbs_cert = $tbs_cert->withSubjectUniqueID(UniqueIdentifier::fromASN1(
                $seq->getTagged(2)
                    ->asImplicit(Element::TYPE_BIT_STRING)
                    ->asBitString()
            ));
        }
        if ($seq->hasTagged(3)) {
            $tbs_cert = $tbs_cert->withExtensions(Extensions::fromASN1($seq->getTagged(3)->asExplicit()->asSequence()));
        }
        self::assertOptionalTrailingFields($seq, $idx);
        return $tbs_cert;
    }

    /**
     * Assert that everything past subjectPublicKeyInfo is at most one [1], one [2] and one [3], in that order.
     *
     * The fixed fields are read by position and the optional ones by tag, so nothing else looks at the elements in
     * between or after. An element the template does not define is then carried along unnoticed, and the encoding
     * no longer means what a conforming decoder reads from it.
     *
     * @param int $offset Index of the first element past subjectPublicKeyInfo
     *
     * @throws DecodeException If any other element is present.
     */
    private static function assertOptionalTrailingFields(Sequence $seq, int $offset): void
    {
        $previous = 0;
        $count = count($seq);
        for ($i = $offset; $i < $count; ++$i) {
            $element = $seq->at($i)
                ->asElement();
            $tag = $element->isTagged() ? $element->tag() : -1;
            if ($tag <= $previous || $tag > 3) {
                throw new DecodeException(
                    sprintf('TBSCertificate has an unexpected element at index %d.', $i)
                );
            }
            $previous = $tag;
        }
    }

    /**
     * Initialize from certification request.
     *
     * The extensions a request asks for are chosen by the requester. Those in FORBIDDEN_CSR_EXTENSIONS decide
     * what a certificate is allowed to do, so they are never copied: a requester asking for basicConstraints
     * cA:TRUE and keyUsage keyCertSign is asking to become a certificate authority. The issuer sets those
     * itself.
     *
     * Name the extensions to copy in $allowedExtensionOids. Leaving the argument out copies every requested
     * extension that is not forbidden, which is a deny list: the set of extensions that matter grows over time
     * and every future one would be copied by default. A request can currently carry a subjectAltName naming an
     * identity the subject DN says nothing about, or an authorityInformationAccess choosing the OCSP responder a
     * relying party will ask. That default is deprecated and becomes the empty allow list in the next major
     * release; pass null explicitly to ask for it.
     *
     * An unknown extension marked critical is never copied, whatever the allow list says: it would be signed
     * verbatim and then no RFC 5280 validator would accept the certificate, this library's own included.
     *
     * Note that signature is not verified and must be done by the caller.
     *
     * @param CertificationRequest $cr Certification request
     * @param null|string[] $allowedExtensionOids OIDs of the requested extensions to copy, null for all of them
     * except the forbidden ones
     */
    public static function fromCSR(CertificationRequest $cr, ?array $allowedExtensionOids = null): self
    {
        $allowListGiven = func_num_args() > 1;
        if ($allowedExtensionOids !== null) {
            $forbidden = array_intersect($allowedExtensionOids, self::FORBIDDEN_CSR_EXTENSIONS);
            if (count($forbidden) !== 0) {
                throw new InvalidArgumentException(sprintf(
                    'Extensions %s cannot be taken from a certification request.',
                    implode(', ', $forbidden)
                ));
            }
        }
        $cri = $cr->certificationRequestInfo();
        $tbs_cert = self::create(
            $cri->subject(),
            $cri->subjectPKInfo(),
            Name::create(),
            Validity::fromStrings(null, null)
        );
        // if CSR has Extension Request attribute
        if ($cri->hasAttributes()) {
            $attribs = $cri->attributes();
            if ($attribs->hasExtensionRequest()) {
                $requested = $attribs->extensionRequest()
                    ->extensions();
                $accepted = [];
                foreach ($requested as $extension) {
                    $oid = $extension->oid();
                    if (in_array($oid, self::FORBIDDEN_CSR_EXTENSIONS, true)) {
                        continue;
                    }
                    if ($allowedExtensionOids !== null && ! in_array($oid, $allowedExtensionOids, true)) {
                        continue;
                    }
                    // an extension this library cannot even parse, signed as critical, produces a certificate
                    // that is dead on arrival: no conforming validator will accept it
                    if ($extension->isCritical() && $extension instanceof UnknownExtension) {
                        continue;
                    }
                    $accepted[] = $extension;
                }
                if (! $allowListGiven && count($accepted) !== 0) {
                    @trigger_error(sprintf(
                        'Copying requested extensions from a certification request without naming them is '
                        . 'deprecated and will stop copying anything in the next major release. %s '
                        . 'were taken from the request. Pass the OIDs to copy as the second argument of '
                        . 'fromCSR(), or pass null explicitly to keep the current behaviour.',
                        implode(', ', array_map(static fn (Extension $e) => $e->oid(), $accepted))
                    ), E_USER_DEPRECATED);
                }
                $tbs_cert = $tbs_cert->withExtensions(Extensions::create(...$accepted));
            }
        }
        // add Subject Key Identifier extension
        return $tbs_cert->withAdditionalExtensions(
            SubjectKeyIdentifierExtension::create(false, $cri->subjectPKInfo()->keyIdentifier())
        );
    }

    /**
     * Get self with fields set from the issuer's certificate.
     *
     * Issuer shall be set to issuing certificate's subject. Authority key identifier extensions shall be added with a
     * key identifier set to issuing certificate's public key identifier.
     *
     * @param Certificate $cert Issuing party's certificate
     */
    public function withIssuerCertificate(Certificate $cert): self
    {
        $obj = clone $this;
        // set issuer DN from cert's subject
        $obj->issuer = $cert->tbsCertificate()
            ->subject();
        // add authority key identifier extension
        $key_id = $cert->tbsCertificate()
            ->subjectPublicKeyInfo()
            ->keyIdentifier();
        $obj->extensions = $obj->extensions->withExtensions(AuthorityKeyIdentifierExtension::create(false, $key_id));
        return $obj;
    }

    /**
     * Get self with given version.
     *
     * If version is not set, appropriate version is automatically determined during signing.
     */
    public function withVersion(int $version): self
    {
        $obj = clone $this;
        $obj->version = $version;
        return $obj;
    }

    /**
     * Get self with given serial number.
     *
     * @param int|string $serial Base 10 number
     */
    public function withSerialNumber(int|string $serial): self
    {
        $obj = clone $this;
        $obj->serialNumber = strval($serial);
        return $obj;
    }

    /**
     * Get self with random positive serial number.
     *
     * @param int $size Number of random bytes
     */
    public function withRandomSerialNumber(int $size = self::DEFAULT_RANDOM_SERIAL_SIZE): self
    {
        return $this->withSerialNumber(self::generateSerialNumber($size));
    }

    /**
     * Draw a serial number from a CSPRNG.
     *
     * The most significant bit is cleared so the DER INTEGER encodes a positive value, which is why 64 bits of
     * entropy need nine octets rather than eight.
     *
     * @param int $size Number of random octets
     */
    private static function generateSerialNumber(int $size): string
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Serial number size must be at least one octet.');
        }
        if ($size < self::MIN_RANDOM_SERIAL_SIZE) {
            @trigger_error(sprintf(
                'A %d octet serial number carries %.2f bits of entropy, below the 64 bits the CA/Browser Forum'
                . ' Baseline Requirements ask for. Use at least %d octets.',
                $size,
                8 * $size - 1,
                self::MIN_RANDOM_SERIAL_SIZE
            ), E_USER_DEPRECATED);
        }
        $octets = random_bytes($size);
        $octets[0] = chr(ord($octets[0]) & 0x7F);
        $num = BigInteger::fromBytes($octets, false);

        return $num->isZero() ? '1' : $num->toBase(10);
    }

    /**
     * Get self with given signature algorithm.
     */
    public function withSignature(SignatureAlgorithmIdentifier $algo): self
    {
        $obj = clone $this;
        $obj->signature = $algo;
        return $obj;
    }

    /**
     * Get self with given issuer.
     */
    public function withIssuer(Name $issuer): self
    {
        $obj = clone $this;
        $obj->issuer = $issuer;
        return $obj;
    }

    /**
     * Get self with given validity.
     */
    public function withValidity(Validity $validity): self
    {
        $obj = clone $this;
        $obj->validity = $validity;
        return $obj;
    }

    /**
     * Get self with given subject.
     */
    public function withSubject(Name $subject): self
    {
        $obj = clone $this;
        $obj->subject = $subject;
        return $obj;
    }

    /**
     * Get self with given subject public key info.
     */
    public function withSubjectPublicKeyInfo(PublicKeyInfo $pub_key_info): self
    {
        $obj = clone $this;
        $obj->subjectPublicKeyInfo = $pub_key_info;
        return $obj;
    }

    /**
     * Get self with issuer unique ID.
     */
    public function withIssuerUniqueID(UniqueIdentifier $id): self
    {
        $obj = clone $this;
        $obj->issuerUniqueID = $id;
        return $obj;
    }

    /**
     * Get self with subject unique ID.
     */
    public function withSubjectUniqueID(UniqueIdentifier $id): self
    {
        $obj = clone $this;
        $obj->subjectUniqueID = $id;
        return $obj;
    }

    /**
     * Get self with given extensions.
     */
    public function withExtensions(Extensions $extensions): self
    {
        $obj = clone $this;
        $obj->extensions = $extensions;
        return $obj;
    }

    /**
     * Get self with extensions added.
     *
     * @param Extension ...$exts One or more Extension objects
     */
    public function withAdditionalExtensions(Extension ...$exts): self
    {
        $obj = clone $this;
        $obj->extensions = $obj->extensions->withExtensions(...$exts);
        return $obj;
    }

    /**
     * Check whether version is set.
     */
    public function hasVersion(): bool
    {
        return isset($this->version);
    }

    /**
     * Get certificate version.
     */
    public function version(): int
    {
        if (! $this->hasVersion()) {
            throw new LogicException('version not set.');
        }
        return $this->version;
    }

    /**
     * Check whether serial number is set.
     */
    public function hasSerialNumber(): bool
    {
        return isset($this->serialNumber);
    }

    /**
     * Get serial number.
     *
     * @return string Base 10 integer
     */
    public function serialNumber(): string
    {
        if (! $this->hasSerialNumber()) {
            throw new LogicException('serialNumber not set.');
        }
        return $this->serialNumber;
    }

    /**
     * Check whether signature algorithm is set.
     */
    public function hasSignature(): bool
    {
        return isset($this->signature);
    }

    /**
     * Get signature algorithm.
     */
    public function signature(): SignatureAlgorithmIdentifier
    {
        if (! $this->hasSignature()) {
            throw new LogicException('signature not set.');
        }
        return $this->signature;
    }

    public function issuer(): Name
    {
        return $this->issuer;
    }

    /**
     * Get validity period.
     */
    public function validity(): Validity
    {
        return $this->validity;
    }

    public function subject(): Name
    {
        return $this->subject;
    }

    /**
     * Get subject public key.
     */
    public function subjectPublicKeyInfo(): PublicKeyInfo
    {
        return $this->subjectPublicKeyInfo;
    }

    /**
     * Whether issuer unique identifier is present.
     */
    public function hasIssuerUniqueID(): bool
    {
        return isset($this->issuerUniqueID);
    }

    public function issuerUniqueID(): UniqueIdentifier
    {
        if (! $this->hasIssuerUniqueID()) {
            throw new LogicException('issuerUniqueID not set.');
        }
        return $this->issuerUniqueID;
    }

    /**
     * Whether subject unique identifier is present.
     */
    public function hasSubjectUniqueID(): bool
    {
        return isset($this->subjectUniqueID);
    }

    public function subjectUniqueID(): UniqueIdentifier
    {
        if (! $this->hasSubjectUniqueID()) {
            throw new LogicException('subjectUniqueID not set.');
        }
        return $this->subjectUniqueID;
    }

    public function extensions(): Extensions
    {
        return $this->extensions;
    }

    /**
     * Generate ASN.1 structure.
     */
    public function toASN1(): Sequence
    {
        $elements = [];
        $version = $this->version();
        // if version is not default
        if ($version !== self::VERSION_1) {
            $elements[] = ExplicitlyTaggedType::create(0, Integer::create($version));
        }
        $serial = $this->serialNumber();
        $signature = $this->signature();
        // add required elements
        array_push(
            $elements,
            Integer::create($serial),
            $signature->toASN1(),
            $this->issuer->toASN1(),
            $this->validity->toASN1(),
            $this->subject->toASN1(),
            $this->subjectPublicKeyInfo->toASN1()
        );
        if (isset($this->issuerUniqueID)) {
            $elements[] = ImplicitlyTaggedType::create(1, $this->issuerUniqueID->toASN1());
        }
        if (isset($this->subjectUniqueID)) {
            $elements[] = ImplicitlyTaggedType::create(2, $this->subjectUniqueID->toASN1());
        }
        if (count($this->extensions) !== 0) {
            $elements[] = ExplicitlyTaggedType::create(3, $this->extensions->toASN1());
        }
        return Sequence::create(...$elements);
    }

    /**
     * Create signed certificate.
     *
     * @param SignatureAlgorithmIdentifier $algo Algorithm used for signing
     * @param PrivateKeyInfo $privkey_info Private key used for signing
     * @param null|Crypto $crypto Crypto engine, use default if not set
     */
    public function sign(
        SignatureAlgorithmIdentifier $algo,
        PrivateKeyInfo $privkey_info,
        ?Crypto $crypto = null
    ): Certificate {
        $crypto ??= Crypto::getDefault();
        $tbs_cert = clone $this;
        if (! isset($tbs_cert->version)) {
            $tbs_cert->version = $tbs_cert->_determineVersion();
        }
        if (! isset($tbs_cert->serialNumber)) {
            // RFC 5280 section 4.1.2.2 requires a positive integer, and a constant serial number removes the
            // unpredictability that protects issuance against collision attacks while breaking CRL revocation,
            // which identifies certificates by the (issuer, serial number) pair. Draw one rather than use zero.
            $tbs_cert->serialNumber = self::generateSerialNumber(self::DEFAULT_RANDOM_SERIAL_SIZE);
        }
        $tbs_cert->signature = $algo;
        $data = $tbs_cert->toASN1()
            ->toDER();
        $signature = $crypto->sign($data, $privkey_info, $algo);
        return Certificate::create($tbs_cert, $algo, $signature);
    }

    /**
     * Determine minimum version for the certificate.
     */
    private function _determineVersion(): int
    {
        // if extensions are present
        if (count($this->extensions) !== 0) {
            return self::VERSION_3;
        }
        // if UniqueIdentifier is present
        if (isset($this->issuerUniqueID) || isset($this->subjectUniqueID)) {
            return self::VERSION_2;
        }
        return self::VERSION_1;
    }
}

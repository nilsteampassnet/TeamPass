<?php

declare(strict_types=1);

namespace Webauthn\AttestationStatement;

use function array_key_exists;
use CBOR\Decoder;
use CBOR\MapObject;
use Cose\Algorithms;
use Cose\Key\Ec2Key;
use Cose\Key\Key;
use Cose\Key\OkpKey;
use Cose\Key\RsaKey;
use function count;
use DateTimeImmutable;
use function openssl_verify;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SpomkyLabs\Pki\ASN1\Type\Primitive\OctetString;
use SpomkyLabs\Pki\ASN1\Type\UnspecifiedType;
use SpomkyLabs\Pki\CryptoEncoding\PEM;
use SpomkyLabs\Pki\X509\Certificate\Certificate;
use SpomkyLabs\Pki\X509\Certificate\Extension\UnknownExtension;
use SpomkyLabs\Pki\X509\Certificate\TBSCertificate;
use function sprintf;
use Symfony\Component\Clock\NativeClock;
use function unpack;
use Webauthn\AuthenticatorData;
use Webauthn\Event\AttestationStatementLoaded;
use Webauthn\Event\CanDispatchEvents;
use Webauthn\Event\NullEventDispatcher;
use Webauthn\Exception\AttestationStatementLoadingException;
use Webauthn\Exception\AttestationStatementVerificationException;
use Webauthn\Exception\InvalidAttestationStatementException;
use Webauthn\MetadataService\CertificateChain\CertificateToolbox;
use Webauthn\StringStream;
use Webauthn\TrustPath\CertificateTrustPath;

final class TPMAttestationStatementSupport implements AttestationStatementSupport, CanDispatchEvents
{
    private const OID_FIDO_GEN_CE_AAGUID = '1.3.6.1.4.1.45724.1.1.4';

    private const OID_AIK_CERTIFICATE = '2.23.133.8.3';

    private EventDispatcherInterface $dispatcher;

    private readonly ClockInterface $clock;

    public function __construct(null|ClockInterface $clock = null)
    {
        $this->clock = $clock ?? new NativeClock();
        $this->dispatcher = new NullEventDispatcher();
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->dispatcher = $eventDispatcher;
    }

    public static function create(null|ClockInterface $clock = null): self
    {
        return new self($clock);
    }

    public function name(): string
    {
        return 'tpm';
    }

    /**
     * @param array<string, mixed> $attestation
     */
    public function load(array $attestation): AttestationStatement
    {
        array_key_exists('attStmt', $attestation) || throw AttestationStatementLoadingException::create(
            $attestation,
            'Invalid attestation object'
        );
        /** @var array<string, mixed> $attStmt */
        $attStmt = $attestation['attStmt'];
        foreach (['ver', 'ver', 'sig', 'alg', 'certInfo', 'pubArea'] as $key) {
            array_key_exists($key, $attStmt) || throw AttestationStatementLoadingException::create(
                $attestation,
                sprintf('The attestation statement value "%s" is missing.', $key)
            );
        }
        $attStmt['ver'] === '2.0' || throw AttestationStatementLoadingException::create(
            $attestation,
            'Invalid attestation object'
        );

        /** @var string $certInfoData */
        $certInfoData = $attStmt['certInfo'];
        $certInfo = $this->checkCertInfo($certInfoData);
        bin2hex($certInfo['type']) === '8017' || throw AttestationStatementLoadingException::create(
            $attestation,
            'Invalid attestation object'
        );

        /** @var string $pubAreaData */
        $pubAreaData = $attStmt['pubArea'];
        $pubArea = $this->checkPubArea($pubAreaData);
        $pubAreaAttestedNameAlg = substr($certInfo['attestedName'], 0, 2);
        /** @var string $pubAreaRaw */
        $pubAreaRaw = $attStmt['pubArea'];
        $pubAreaHash = hash($this->getTPMHash($pubAreaAttestedNameAlg), $pubAreaRaw, true);
        $attestedName = $pubAreaAttestedNameAlg . $pubAreaHash;
        $attestedName === $certInfo['attestedName'] || throw AttestationStatementLoadingException::create(
            $attestation,
            'Invalid attested name'
        );

        $attStmt['parsedCertInfo'] = $certInfo;
        $attStmt['parsedPubArea'] = $pubArea;

        /** @var array<string> $x5c */
        $x5c = $attStmt['x5c'];
        $certificates = CertificateToolbox::convertAllDERToPEM($x5c);
        count($certificates) > 0 || throw AttestationStatementLoadingException::create(
            $attestation,
            'The attestation statement value "x5c" must be a list with at least one certificate.'
        );

        $attestationStatement = AttestationStatement::createAttCA(
            $this->name(),
            $attStmt,
            CertificateTrustPath::create($certificates)
        );
        $this->dispatcher->dispatch(AttestationStatementLoaded::create($attestationStatement));

        return $attestationStatement;
    }

    public function isValid(
        string $clientDataJSONHash,
        AttestationStatement $attestationStatement,
        AuthenticatorData $authenticatorData
    ): bool {
        $attToBeSigned = $authenticatorData->authData . $clientDataJSONHash;
        /** @var int|string $algRaw */
        $algRaw = $attestationStatement->get('alg');
        $alg = (int) $algRaw;
        $attToBeSignedHash = hash(Algorithms::getHashAlgorithmFor($alg), $attToBeSigned, true);
        /** @var array{extraData: string} $parsedCertInfo */
        $parsedCertInfo = $attestationStatement->get('parsedCertInfo');
        $parsedCertInfo['extraData'] === $attToBeSignedHash || throw InvalidAttestationStatementException::create(
            $attestationStatement,
            'Invalid attestation hash'
        );
        $credentialPublicKey = $authenticatorData->attestedCredentialData?->credentialPublicKey;
        $credentialPublicKey !== null || throw InvalidAttestationStatementException::create(
            $attestationStatement,
            'Not credential public key available in the attested credential data'
        );
        /** @var array{unique: string} $parsedPubArea */
        $parsedPubArea = $attestationStatement->get('parsedPubArea');
        $this->checkUniquePublicKey($parsedPubArea['unique'], $credentialPublicKey);

        return match (true) {
            $attestationStatement->trustPath instanceof CertificateTrustPath => $this->processWithCertificate(
                $attestationStatement,
                $authenticatorData
            ),
            default => throw InvalidAttestationStatementException::create(
                $attestationStatement,
                'Unsupported attestation statement'
            ),
        };
    }

    private function checkUniquePublicKey(string $unique, string $cborPublicKey): void
    {
        $cborDecoder = Decoder::create();
        $publicKey = $cborDecoder->decode(new StringStream($cborPublicKey));
        $publicKey instanceof MapObject || throw AttestationStatementVerificationException::create(
            'Invalid public key'
        );
        $key = Key::create($publicKey->normalize());

        switch ($key->type()) {
            case Key::TYPE_OKP:
                $uniqueFromKey = (new OkpKey($key->getData()))->x();
                break;
            case Key::TYPE_EC2:
                $ec2Key = new Ec2Key($key->getData());
                $uniqueFromKey = "\x04" . $ec2Key->x() . $ec2Key->y();
                break;
            case Key::TYPE_RSA:
                $uniqueFromKey = (new RsaKey($key->getData()))->n();
                break;
            default:
                throw AttestationStatementVerificationException::create('Invalid or unsupported key type.');
        }

        $unique === $uniqueFromKey || throw AttestationStatementVerificationException::create(
            'Invalid pubArea.unique value'
        );
    }

    /**
     * @return mixed[]
     */
    /**
     * @return array{magic: string, type: string, qualifiedSigner: string, extraData: string, clockInfo: string, firmwareVersion: string, attestedName: string, attestedQualifiedName: string}
     */
    private function checkCertInfo(string $data): array
    {
        $certInfo = new StringStream($data);

        $magic = $certInfo->read(4);
        bin2hex($magic) === 'ff544347' || throw AttestationStatementVerificationException::create(
            'Invalid attestation object'
        );

        $type = $certInfo->read(2);

        /** @var array{1: int} $qualifiedSignerLengthData */
        $qualifiedSignerLengthData = unpack('n', $certInfo->read(2));
        $qualifiedSigner = $certInfo->read($qualifiedSignerLengthData[1]); // Ignored

        /** @var array{1: int} $extraDataLengthData */
        $extraDataLengthData = unpack('n', $certInfo->read(2));
        $extraData = $certInfo->read($extraDataLengthData[1]);

        $clockInfo = $certInfo->read(17); // Ignore

        $firmwareVersion = $certInfo->read(8);

        /** @var array{1: int} $attestedNameLengthData */
        $attestedNameLengthData = unpack('n', $certInfo->read(2));
        $attestedName = $certInfo->read($attestedNameLengthData[1]);

        /** @var array{1: int} $attestedQualifiedNameLengthData */
        $attestedQualifiedNameLengthData = unpack('n', $certInfo->read(2));
        $attestedQualifiedName = $certInfo->read($attestedQualifiedNameLengthData[1]); // Ignore
        $certInfo->isEOF() || throw AttestationStatementVerificationException::create(
            'Invalid certificate information. Presence of extra bytes.'
        );
        $certInfo->close();

        return [
            'magic' => $magic,
            'type' => $type,
            'qualifiedSigner' => $qualifiedSigner,
            'extraData' => $extraData,
            'clockInfo' => $clockInfo,
            'firmwareVersion' => $firmwareVersion,
            'attestedName' => $attestedName,
            'attestedQualifiedName' => $attestedQualifiedName,
        ];
    }

    /**
     * @return mixed[]
     */
    private function checkPubArea(string $data): array
    {
        $pubArea = new StringStream($data);

        $type = $pubArea->read(2);

        $nameAlg = $pubArea->read(2);

        $objectAttributes = $pubArea->read(4);

        /** @var array{1: int} $authPolicyLengthData */
        $authPolicyLengthData = unpack('n', $pubArea->read(2));
        $authPolicy = $pubArea->read($authPolicyLengthData[1]);

        $parameters = $this->getParameters($type, $pubArea);

        $unique = $this->getUnique($type, $pubArea);
        $pubArea->isEOF() || throw AttestationStatementVerificationException::create(
            'Invalid public area. Presence of extra bytes.'
        );
        $pubArea->close();

        return [
            'type' => $type,
            'nameAlg' => $nameAlg,
            'objectAttributes' => $objectAttributes,
            'authPolicy' => $authPolicy,
            'parameters' => $parameters,
            'unique' => $unique,
        ];
    }

    /**
     * @return mixed[]
     */
    private function getParameters(string $type, StringStream $stream): array
    {
        return match (bin2hex($type)) {
            '0001' => $this->getRsaParameters($stream),
            '0023' => [
                'symmetric' => $stream->read(2),
                'scheme' => $stream->read(2),
                'curveId' => $stream->read(2),
                'kdf' => $stream->read(2),
            ],
            default => throw AttestationStatementVerificationException::create('Unsupported type'),
        };
    }

    private function getUnique(string $type, StringStream $stream): string
    {
        switch (bin2hex($type)) {
            case '0001':
                /** @var array{1: int} $uniqueLengthData */
                $uniqueLengthData = unpack('n', $stream->read(2));
                return $stream->read($uniqueLengthData[1]);
            case '0023':
                /** @var array{1: int} $xLenData */
                $xLenData = unpack('n', $stream->read(2));
                $x = $stream->read($xLenData[1]);
                /** @var array{1: int} $yLenData */
                $yLenData = unpack('n', $stream->read(2));
                $y = $stream->read($yLenData[1]);
                return "\04" . $x . $y;
            default:
                throw AttestationStatementVerificationException::create('Unsupported type');
        }
    }

    /**
     * @return array{symmetric: string, scheme: string, keyBits: int, exponent: string}
     */
    private function getRsaParameters(StringStream $stream): array
    {
        $symmetric = $stream->read(2);
        $scheme = $stream->read(2);
        /** @var array{1: int} $keyBitsData */
        $keyBitsData = unpack('n', $stream->read(2));

        return [
            'symmetric' => $symmetric,
            'scheme' => $scheme,
            'keyBits' => $keyBitsData[1],
            'exponent' => $this->getExponent($stream->read(4)),
        ];
    }

    private function getExponent(string $exponent): string
    {
        return bin2hex($exponent) === '00000000' ? Base64UrlSafe::decodeNoPadding('AQAB') : $exponent;
    }

    private function getTPMHash(string $nameAlg): string
    {
        return match (bin2hex($nameAlg)) {
            '0004' => 'sha1',
            '000b' => 'sha256',
            '000c' => 'sha384',
            '000d' => 'sha512',
            default => throw AttestationStatementVerificationException::create('Unsupported hash algorithm'),
        };
    }

    private function processWithCertificate(
        AttestationStatement $attestationStatement,
        AuthenticatorData $authenticatorData
    ): bool {
        $trustPath = $attestationStatement->trustPath;
        $trustPath instanceof CertificateTrustPath || throw AttestationStatementVerificationException::create(
            'Invalid trust path'
        );

        $certificates = $trustPath->certificates;

        // Check certificate CA chain and returns the Attestation Certificate
        $this->checkCertificate($certificates[0], $authenticatorData);

        // Get the COSE algorithm identifier and the corresponding OpenSSL one
        /** @var int|string $algRaw */
        $algRaw = $attestationStatement->get('alg');
        $coseAlgorithmIdentifier = (int) $algRaw;
        $opensslAlgorithmIdentifier = Algorithms::getOpensslAlgorithmFor($coseAlgorithmIdentifier);

        /** @var string $certInfo */
        $certInfo = $attestationStatement->get('certInfo');
        /** @var string $sig */
        $sig = $attestationStatement->get('sig');
        $result = openssl_verify($certInfo, $sig, $certificates[0], $opensslAlgorithmIdentifier);

        return $result === 1;
    }

    // https://www.w3.org/TR/webauthn-3/#sctn-tpm-cert-requirements
    private function checkCertificate(string $attestnCert, AuthenticatorData $authenticatorData): void
    {
        $certificate = Certificate::fromPEM(PEM::fromString($attestnCert));
        $tbsCertificate = $certificate->tbsCertificate();

        // Version MUST be set to 3 (X.509 version 3 is encoded as 2)
        $tbsCertificate->version() === TBSCertificate::VERSION_3 || throw AttestationStatementVerificationException::create(
            'Invalid certificate version'
        );

        // Subject field MUST be set to empty
        $tbsCertificate->subject()
            ->count() === 0 || throw AttestationStatementVerificationException::create(
                'Invalid certificate name. The Subject should be empty'
            );

        // Check period of validity
        $validity = $tbsCertificate->validity();
        $startDate = DateTimeImmutable::createFromInterface($validity->notBefore()->dateTime());
        $startDate < $this->clock->now() || throw AttestationStatementVerificationException::create(
            'Invalid certificate start date.'
        );

        $endDate = DateTimeImmutable::createFromInterface($validity->notAfter()->dateTime());
        $endDate > $this->clock->now() || throw AttestationStatementVerificationException::create(
            'Invalid certificate end date.'
        );

        // Check extensions
        $extensions = $tbsCertificate->extensions();

        // Check Subject Alternative Name extension
        $extensions->hasSubjectAlternativeName() || throw AttestationStatementVerificationException::create(
            'The Subject Alternative Name extension must be set'
        );

        // Check Extended Key Usage extension MUST contain the OID 2.23.133.8.3
        $extensions->hasExtendedKeyUsage() || throw AttestationStatementVerificationException::create(
            'The Extended Key Usage extensions must contain ' . self::OID_AIK_CERTIFICATE,
        );
        $extendedKeyUsage = $extensions->extendedKeyUsage();
        $extendedKeyUsage->has(self::OID_AIK_CERTIFICATE) || throw AttestationStatementVerificationException::create(
            'The Extended Key Usage extensions must contain ' . self::OID_AIK_CERTIFICATE,
        );

        // The Basic Constraints extension MUST have the CA component set to false.
        $extensions->basicConstraints()
            ->isCA() === false || throw AttestationStatementVerificationException::create(
                'The Basic Constraints extension must have the CA component set to false'
            );

        // id-fido-gen-ce-aaguid OID check
        if ($extensions->has(self::OID_FIDO_GEN_CE_AAGUID)) {
            /** @var UnknownExtension $aaguidExtension */
            $aaguidExtension = $extensions->get(self::OID_FIDO_GEN_CE_AAGUID);
            $aaguidElement = UnspecifiedType::fromDER($aaguidExtension->extensionValue())->asElement();
            $aaguidElement instanceof OctetString || throw AttestationStatementVerificationException::create(
                'Invalid ' . self::OID_FIDO_GEN_CE_AAGUID . ' extension format'
            );
            $aaguidValue = $aaguidElement->string();
            hash_equals(
                $authenticatorData->attestedCredentialData
                    ?->aaguid
                    ->toBinary() ?? '',
                $aaguidValue
            ) || throw AttestationStatementVerificationException::create(
                'The value of the "aaguid" does not match with the certificate'
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace Webauthn\AttestationStatement;

use function array_key_exists;
use CBOR\Decoder;
use CBOR\MapObject;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\Signature;
use Cose\Algorithms;
use Cose\Key\Key;
use function count;
use function is_array;
use function is_string;
use function openssl_verify;
use Psr\EventDispatcher\EventDispatcherInterface;
use SpomkyLabs\Pki\ASN1\Type\Primitive\OctetString;
use SpomkyLabs\Pki\ASN1\Type\UnspecifiedType;
use SpomkyLabs\Pki\CryptoEncoding\PEM;
use SpomkyLabs\Pki\X501\ASN1\AttributeType;
use SpomkyLabs\Pki\X509\Certificate\Certificate;
use SpomkyLabs\Pki\X509\Certificate\Extension\UnknownExtension;
use SpomkyLabs\Pki\X509\Certificate\TBSCertificate;
use Webauthn\AuthenticatorData;
use Webauthn\Event\AttestationStatementLoaded;
use Webauthn\Event\CanDispatchEvents;
use Webauthn\Event\NullEventDispatcher;
use Webauthn\Exception\AttestationStatementLoadingException;
use Webauthn\Exception\AttestationStatementVerificationException;
use Webauthn\Exception\InvalidAttestationStatementException;
use Webauthn\Exception\InvalidDataException;
use Webauthn\MetadataService\CertificateChain\CertificateToolbox;
use Webauthn\StringStream;
use Webauthn\TrustPath\CertificateTrustPath;
use Webauthn\TrustPath\EmptyTrustPath;
use Webauthn\Util\CoseSignatureFixer;

final class PackedAttestationStatementSupport implements AttestationStatementSupport, CanDispatchEvents
{
    private const OID_FIDO_GEN_CE_AAGUID = '1.3.6.1.4.1.45724.1.1.4';

    private readonly Decoder $decoder;

    private EventDispatcherInterface $dispatcher;

    public function __construct(
        private readonly Manager $algorithmManager
    ) {
        $this->decoder = Decoder::create();
        $this->dispatcher = new NullEventDispatcher();
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->dispatcher = $eventDispatcher;
    }

    public static function create(Manager $algorithmManager): self
    {
        return new self($algorithmManager);
    }

    public function name(): string
    {
        return 'packed';
    }

    /**
     * @param array<string, mixed> $attestation
     */
    public function load(array $attestation): AttestationStatement
    {
        /** @var array<string, mixed> $attStmt */
        $attStmt = $attestation['attStmt'];
        array_key_exists('sig', $attStmt) || throw AttestationStatementLoadingException::create(
            $attestation,
            'The attestation statement value "sig" is missing.'
        );
        array_key_exists('alg', $attStmt) || throw AttestationStatementLoadingException::create(
            $attestation,
            'The attestation statement value "alg" is missing.'
        );
        is_string($attStmt['sig']) || throw AttestationStatementLoadingException::create(
            $attestation,
            'The attestation statement value "sig" is missing.'
        );

        return match (true) {
            array_key_exists('x5c', $attStmt) => $this->loadBasicType($attestation),
            default => $this->loadEmptyType($attestation),
        };
    }

    public function isValid(
        string $clientDataJSONHash,
        AttestationStatement $attestationStatement,
        AuthenticatorData $authenticatorData
    ): bool {
        $trustPath = $attestationStatement->trustPath;

        return match (true) {
            $trustPath instanceof CertificateTrustPath => $this->processWithCertificate(
                $clientDataJSONHash,
                $attestationStatement,
                $authenticatorData,
                $trustPath
            ),
            $trustPath instanceof EmptyTrustPath => $this->processWithSelfAttestation(
                $clientDataJSONHash,
                $attestationStatement,
                $authenticatorData
            ),
            default => throw InvalidAttestationStatementException::create(
                $attestationStatement,
                'Unsupported attestation statement'
            ),
        };
    }

    /**
     * @param mixed[] $attestation
     */
    private function loadBasicType(array $attestation): AttestationStatement
    {
        /** @var array<string, mixed> $attStmt */
        $attStmt = $attestation['attStmt'];
        /** @var array<string> $certificates */
        $certificates = $attStmt['x5c'];
        is_array($certificates) || throw AttestationStatementVerificationException::create(
            'The attestation statement value "x5c" must be a list with at least one certificate.'
        );
        count($certificates) > 0 || throw AttestationStatementVerificationException::create(
            'The attestation statement value "x5c" must be a list with at least one certificate.'
        );
        $certificates = CertificateToolbox::convertAllDERToPEM($certificates);

        /** @var string $fmt */
        $fmt = $attestation['fmt'];
        $attestationStatement = AttestationStatement::createBasic(
            $fmt,
            $attStmt,
            CertificateTrustPath::create($certificates)
        );
        $this->dispatcher->dispatch(AttestationStatementLoaded::create($attestationStatement));

        return $attestationStatement;
    }

    /**
     * @param mixed[] $attestation
     */
    private function loadEmptyType(array $attestation): AttestationStatement
    {
        /** @var string $fmt */
        $fmt = $attestation['fmt'];
        /** @var array<string, mixed> $attStmt */
        $attStmt = $attestation['attStmt'];
        $attestationStatement = AttestationStatement::createSelf($fmt, $attStmt, EmptyTrustPath::create());
        $this->dispatcher->dispatch(AttestationStatementLoaded::create($attestationStatement));

        return $attestationStatement;
    }

    // https://www.w3.org/TR/webauthn-3/#sctn-packed-attestation-cert-requirements
    private function checkCertificate(string $attestnCert, AuthenticatorData $authenticatorData): void
    {
        $certificate = Certificate::fromPEM(PEM::fromString($attestnCert));
        $tbsCertificate = $certificate->tbsCertificate();

        // Check version (X.509 version 3 is encoded as 2)
        $tbsCertificate->version() === TBSCertificate::VERSION_3 || throw AttestationStatementVerificationException::create(
            'Invalid certificate version'
        );

        // Check subject field
        $subject = $tbsCertificate->subject();
        $subject->countOfType(
            AttributeType::OID_COUNTRY_NAME
        ) > 0 || throw AttestationStatementVerificationException::create('Certificate Subject-C must be set');
        $subject->countOfType(
            AttributeType::OID_ORGANIZATION_NAME
        ) > 0 || throw AttestationStatementVerificationException::create('Certificate Subject-O must be set');
        $subject->countOfType(
            AttributeType::OID_ORGANIZATIONAL_UNIT_NAME
        ) > 0 || throw AttestationStatementVerificationException::create('Certificate Subject-OU must be set');
        $subject->countOfType(
            AttributeType::OID_COMMON_NAME
        ) > 0 || throw AttestationStatementVerificationException::create('Certificate Subject-CN must be set');
        $ouValue = $subject->firstValueOf('OU')
            ->stringValue();
        $ouValue === 'Authenticator Attestation' || throw AttestationStatementVerificationException::create(
            'Invalid certificate name. The Subject Organization Unit must be "Authenticator Attestation"'
        );

        // Check extensions
        $extensions = $tbsCertificate->extensions();

        // Check certificate is not a CA cert
        $extensions->hasBasicConstraints() || throw AttestationStatementVerificationException::create(
            'The Basic Constraints extension must have the CA component set to false'
        );
        ! $extensions->basicConstraints()
            ->isCA() || throw AttestationStatementVerificationException::create(
                'The Basic Constraints extension must have the CA component set to false'
            );

        $attestedCredentialData = $authenticatorData->attestedCredentialData;
        $attestedCredentialData !== null || throw AttestationStatementVerificationException::create(
            'No attested credential available'
        );

        // id-fido-gen-ce-aaguid OID check
        if ($extensions->has(self::OID_FIDO_GEN_CE_AAGUID)) {
            /** @var UnknownExtension $aaguidExtension */
            $aaguidExtension = $extensions->get(self::OID_FIDO_GEN_CE_AAGUID);
            ! $aaguidExtension->isCritical() || throw AttestationStatementVerificationException::create(
                'Extension ' . self::OID_FIDO_GEN_CE_AAGUID . ' must not be marked as critical'
            );

            $aaguidElement = UnspecifiedType::fromDER($aaguidExtension->extensionValue())->asElement();
            $aaguidElement instanceof OctetString || throw AttestationStatementVerificationException::create(
                'Invalid ' . self::OID_FIDO_GEN_CE_AAGUID . ' extension format'
            );
            $aaguidValue = $aaguidElement->string();
            hash_equals(
                $attestedCredentialData->aaguid
                    ->toBinary(),
                $aaguidValue
            ) || throw AttestationStatementVerificationException::create(
                'The value of the "aaguid" does not match with the certificate extension ' . self::OID_FIDO_GEN_CE_AAGUID
            );
        }
    }

    private function processWithCertificate(
        string $clientDataJSONHash,
        AttestationStatement $attestationStatement,
        AuthenticatorData $authenticatorData,
        CertificateTrustPath $trustPath
    ): bool {
        $certificates = $trustPath->certificates;

        // Check leaf certificate
        $this->checkCertificate($certificates[0], $authenticatorData);

        // Get the COSE algorithm identifier and the corresponding OpenSSL one
        /** @var int|string $algRaw */
        $algRaw = $attestationStatement->get('alg');
        $coseAlgorithmIdentifier = (int) $algRaw;
        $opensslAlgorithmIdentifier = Algorithms::getOpensslAlgorithmFor($coseAlgorithmIdentifier);

        // Verification of the signature
        $signedData = $authenticatorData->authData . $clientDataJSONHash;
        /** @var string $sig */
        $sig = $attestationStatement->get('sig');
        $result = openssl_verify($signedData, $sig, $certificates[0], $opensslAlgorithmIdentifier);

        return $result === 1;
    }

    private function processWithSelfAttestation(
        string $clientDataJSONHash,
        AttestationStatement $attestationStatement,
        AuthenticatorData $authenticatorData
    ): bool {
        $attestedCredentialData = $authenticatorData->attestedCredentialData;
        $attestedCredentialData !== null || throw AttestationStatementVerificationException::create(
            'No attested credential available'
        );
        $credentialPublicKey = $attestedCredentialData->credentialPublicKey;
        $credentialPublicKey !== null || throw AttestationStatementVerificationException::create(
            'No credential public key available'
        );
        $publicKeyStream = new StringStream($credentialPublicKey);
        $publicKey = $this->decoder->decode($publicKeyStream);
        $publicKeyStream->isEOF() || throw AttestationStatementVerificationException::create(
            'Invalid public key. Presence of extra bytes.'
        );
        $publicKeyStream->close();
        $publicKey instanceof MapObject || throw AttestationStatementVerificationException::create(
            'The attested credential data does not contain a valid public key.'
        );
        $publicKey = $publicKey->normalize();
        $publicKey = new Key($publicKey);
        /** @var int|string $algRaw */
        $algRaw = $attestationStatement->get('alg');
        $alg = (int) $algRaw;
        $publicKey->alg() === $alg || throw AttestationStatementVerificationException::create(
            'The algorithm of the attestation statement and the key are not identical.'
        );

        $dataToVerify = $authenticatorData->authData . $clientDataJSONHash;
        $algorithm = $this->algorithmManager->get($alg);
        if (! $algorithm instanceof Signature) {
            throw InvalidDataException::create($algorithm, 'Invalid algorithm');
        }
        /** @var string $sigForFix */
        $sigForFix = $attestationStatement->get('sig');
        $signature = CoseSignatureFixer::fix($sigForFix, $algorithm);

        return $algorithm->verify($dataToVerify, $publicKey, $signature);
    }
}

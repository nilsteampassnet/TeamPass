<?php

declare(strict_types=1);

namespace Webauthn\AttestationStatement;

use function array_key_exists;
use CBOR\Decoder;
use CBOR\Normalizable;
use Cose\Key\Ec2Key;
use Cose\Key\Key;
use Cose\Key\RsaKey;
use function count;
use Psr\EventDispatcher\EventDispatcherInterface;
use SpomkyLabs\Pki\ASN1\Type\Constructed\Sequence;
use SpomkyLabs\Pki\ASN1\Type\Primitive\OctetString;
use SpomkyLabs\Pki\ASN1\Type\Tagged\ExplicitTagging;
use SpomkyLabs\Pki\CryptoEncoding\PEM;
use SpomkyLabs\Pki\X509\Certificate\Certificate;
use SpomkyLabs\Pki\X509\Certificate\Extension\UnknownExtension;
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

final class AppleAttestationStatementSupport implements AttestationStatementSupport, CanDispatchEvents
{
    private const OID_APPLE = '1.2.840.113635.100.8.2';

    private readonly Decoder $decoder;

    private EventDispatcherInterface $dispatcher;

    public function __construct()
    {
        $this->decoder = Decoder::create();
        $this->dispatcher = new NullEventDispatcher();
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->dispatcher = $eventDispatcher;
    }

    public static function create(): self
    {
        return new self();
    }

    public function name(): string
    {
        return 'apple';
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
        array_key_exists('x5c', $attStmt) || throw AttestationStatementLoadingException::create(
            $attestation,
            'The attestation statement value "x5c" is missing.'
        );
        /** @var array<string> $certificates */
        $certificates = $attStmt['x5c'];
        (is_countable($certificates) ? count(
            $certificates
        ) : 0) > 0 || throw AttestationStatementLoadingException::create(
            $attestation,
            'The attestation statement value "x5c" must be a list with at least one certificate.'
        );
        $certificates = CertificateToolbox::convertAllDERToPEM($certificates);

        /** @var string $fmt */
        $fmt = $attestation['fmt'];
        $attestationStatement = AttestationStatement::createAnonymizationCA(
            $fmt,
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
        $trustPath = $attestationStatement->trustPath;
        $trustPath instanceof CertificateTrustPath || throw InvalidAttestationStatementException::create(
            $attestationStatement,
            'Invalid trust path'
        );

        $certificates = $trustPath->certificates;

        // Decode leaf attestation certificate
        $leaf = $certificates[0];

        $this->checkCertificateAndGetPublicKey($leaf, $clientDataJSONHash, $authenticatorData);

        return true;
    }

    /**
     * @see https://www.w3.org/TR/webauthn-3/#sctn-apple-anonymous-attestation
     */
    private function checkCertificateAndGetPublicKey(
        string $certificate,
        string $clientDataHash,
        AuthenticatorData $authenticatorData
    ): void {
        // Check that authData publicKey matches the public key in the attestation certificate
        $attestedCredentialData = $authenticatorData->attestedCredentialData;
        $attestedCredentialData !== null || throw AttestationStatementVerificationException::create(
            'No attested credential data found'
        );
        $publicKeyData = $attestedCredentialData->credentialPublicKey;
        $publicKeyData !== null || throw AttestationStatementVerificationException::create(
            'No attested public key found'
        );
        $publicDataStream = new StringStream($publicKeyData);
        $coseKey = $this->decoder->decode($publicDataStream);
        $coseKey instanceof Normalizable || throw AttestationStatementVerificationException::create(
            'Invalid attested public key found'
        );
        $publicDataStream->isEOF() || throw AttestationStatementVerificationException::create(
            'Invalid public key data. Presence of extra bytes.'
        );
        $publicDataStream->close();
        /** @var array<int, mixed> $coseKeyData */
        $coseKeyData = $coseKey->normalize();
        $publicKey = Key::createFromData($coseKeyData);

        ($publicKey instanceof Ec2Key) || ($publicKey instanceof RsaKey) || throw AttestationStatementVerificationException::create(
            'Unsupported key type'
        );

        /* --------------------------- */
        $cert = Certificate::fromPEM(PEM::fromString($certificate));

        // We check the attested key corresponds to the key in the certificate
        PEM::fromString($publicKey->asPEM())->string() === $cert->tbsCertificate()
            ->subjectPublicKeyInfo()
            ->toPEM()
            ->string() || throw AttestationStatementVerificationException::create('Invalid key');

        $extensions = $cert->tbsCertificate()
            ->extensions();

        // Find Apple Extension with OID "1.2.840.113635.100.8.2" in certificate extensions
        $extensions->has(self::OID_APPLE) || throw AttestationStatementVerificationException::create(
            'The certificate extension "' . self::OID_APPLE . '" is missing'
        );
        /** @var UnknownExtension $appleExtension */
        $appleExtension = $extensions->get(self::OID_APPLE);
        $extensionSequence = Sequence::fromDER($appleExtension->extensionValue());
        $extensionSequence->has(0) || throw AttestationStatementVerificationException::create(
            'The certificate extension "' . self::OID_APPLE . '" is message'
        );
        $firstExtension = $extensionSequence->at(0);
        $firstExtension->isTagged() || throw AttestationStatementVerificationException::create(
            'The certificate extension "' . self::OID_APPLE . '" is invalid'
        );
        $taggedExtension = $firstExtension->asTagged()
            ->asElement();
        $taggedExtension instanceof ExplicitTagging || throw AttestationStatementVerificationException::create(
            'The certificate extension "' . self::OID_APPLE . '" is invalid'
        );
        $explicitExtension = $taggedExtension->explicit()
            ->asElement();
        $explicitExtension instanceof OctetString || throw AttestationStatementVerificationException::create(
            'The certificate extension "' . self::OID_APPLE . '" is invalid'
        );
        $extensionData = $explicitExtension->string();

        $nonceToHash = $authenticatorData->authData . $clientDataHash;
        $nonce = hash('sha256', $nonceToHash, true);

        hash_equals($nonce, $extensionData) || throw AttestationStatementVerificationException::create(
            'The client data hash is not valid'
        );
    }
}

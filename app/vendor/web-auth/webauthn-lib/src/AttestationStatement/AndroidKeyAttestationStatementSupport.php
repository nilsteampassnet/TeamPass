<?php

declare(strict_types=1);

namespace Webauthn\AttestationStatement;

use function array_key_exists;
use function array_merge;
use CBOR\Decoder;
use CBOR\Normalizable;
use Cose\Algorithms;
use Cose\Key\Ec2Key;
use Cose\Key\Key;
use Cose\Key\RsaKey;
use function count;
use function in_array;
use function openssl_verify;
use Psr\EventDispatcher\EventDispatcherInterface;
use SpomkyLabs\Pki\ASN1\Type\Constructed\Sequence;
use SpomkyLabs\Pki\ASN1\Type\Primitive\OctetString;
use SpomkyLabs\Pki\ASN1\Type\Tagged\ExplicitTagging;
use SpomkyLabs\Pki\CryptoEncoding\PEM;
use SpomkyLabs\Pki\X509\Certificate\Certificate;
use SpomkyLabs\Pki\X509\Certificate\Extension\UnknownExtension;
use function sprintf;
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

final class AndroidKeyAttestationStatementSupport implements AttestationStatementSupport, CanDispatchEvents
{
    private const OID_ANDROID = '1.3.6.1.4.1.11129.2.1.17';

    /**
     * Tags of the AuthorizationList members that are inspected during the verification procedure.
     *
     * @see https://source.android.com/docs/security/features/keystore/attestation#schema
     */
    private const ANDROID_TAG_PURPOSE = 1;

    private const ANDROID_TAG_ALL_APPLICATIONS = 600;

    private const ANDROID_TAG_ORIGIN = 702;

    private const KM_PURPOSE_SIGN = 2;

    private const KM_ORIGIN_GENERATED = 0;

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
        return 'android-key';
    }

    /**
     * @param array<string, mixed> $attestation
     */
    public function load(array $attestation): AttestationStatement
    {
        array_key_exists('attStmt', $attestation) || throw AttestationStatementLoadingException::create($attestation);
        /** @var array<string, mixed> $attStmt */
        $attStmt = $attestation['attStmt'];
        foreach (['sig', 'x5c', 'alg'] as $key) {
            array_key_exists($key, $attStmt) || throw AttestationStatementLoadingException::create(
                $attestation,
                sprintf('The attestation statement value "%s" is missing.', $key)
            );
        }
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
        $attestationStatement = AttestationStatement::createBasic(
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
            'Invalid trust path. Shall contain certificates.'
        );

        $certificates = $trustPath->certificates;

        // Decode leaf attestation certificate
        $leaf = $certificates[0];
        $this->checkCertificate($leaf, $clientDataJSONHash, $authenticatorData);

        $signedData = $authenticatorData->authData . $clientDataJSONHash;
        /** @var int|string $algRaw */
        $algRaw = $attestationStatement->get('alg');
        $alg = (int) $algRaw;
        /** @var string $sig */
        $sig = $attestationStatement->get('sig');

        return openssl_verify($signedData, $sig, $leaf, Algorithms::getOpensslAlgorithmFor($alg)) === 1;
    }

    /**
     * @see https://www.w3.org/TR/webauthn-3/#sctn-android-key-attestation
     */
    private function checkCertificate(
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
        /**
         * @see https://w3c.github.io/webauthn/#sctn-key-attstn-cert-requirements
         * @see https://source.android.com/docs/security/features/keystore/attestation#attestation-certificate
         */
        $cert = Certificate::fromPEM(PEM::fromString($certificate));
        // We check the attested key corresponds to the key in the certificate
        PEM::fromString($publicKey->asPEM())->string() === $cert->tbsCertificate()
            ->subjectPublicKeyInfo()
            ->toPEM()
            ->string() || throw AttestationStatementVerificationException::create('Invalid key');

        $extensions = $cert->tbsCertificate()
            ->extensions();

        // Find Android KeyStore Extension with OID self::OID_ANDROID in certificate extensions
        $extensions->has(self::OID_ANDROID) || throw AttestationStatementVerificationException::create(
            'The certificate extension "' . self::OID_ANDROID . '" is missing'
        );
        /** @var UnknownExtension $androidExtension */
        $androidExtension = $extensions->get(self::OID_ANDROID);
        /**
         * Parse the Android extension value structure
         * @see https://source.android.com/docs/security/features/keystore/attestation#attestation-extension
         */
        $extensionAsAsn1 = Sequence::fromDER($androidExtension->extensionValue());

        // Check that attestationChallenge is set to the clientDataHash.
        $extensionAsAsn1->has(4) || throw AttestationStatementVerificationException::create(
            'The attestationChallenge field is missing'
        );
        $ext = $extensionAsAsn1->at(4)
            ->asElement();
        $ext instanceof OctetString || throw AttestationStatementVerificationException::create(
            'The attestationChallenge field must be an OctetString'
        );
        $clientDataHash === $ext->string() || throw AttestationStatementVerificationException::create(
            'The client data hash is not valid'
        );

        // Check that both teeEnforced and softwareEnforced structures don't contain allApplications(600) tag.
        $extensionAsAsn1->has(6) || throw AttestationStatementVerificationException::create(
            'The softwareEnforced field is missing'
        );

        $softwareEnforcedFlags = $extensionAsAsn1->at(6)
            ->asElement();
        $softwareEnforcedFlags instanceof Sequence || throw AttestationStatementVerificationException::create(
            'The softwareEnforced field must be a Sequence'
        );
        $this->checkAbsenceOfAllApplicationsTag($softwareEnforcedFlags);

        $extensionAsAsn1->has(7) || throw AttestationStatementVerificationException::create(
            'The teeEnforced field is missing'
        );
        $teeEnforcedFlags = $extensionAsAsn1->at(7)
            ->asElement();
        $teeEnforcedFlags instanceof Sequence || throw AttestationStatementVerificationException::create(
            'The teeEnforced field must be a Sequence'
        );
        $this->checkAbsenceOfAllApplicationsTag($teeEnforcedFlags);

        $this->checkKeyOriginAndPurpose($softwareEnforcedFlags, $teeEnforcedFlags);
    }

    /**
     * The union of both authorization lists is used, which is what the specification mandates unless the Relying Party
     * decides to accept keys from a trusted execution environment only.
     *
     * @see https://w3c.github.io/webauthn/#sctn-android-key-attestation
     */
    private function checkKeyOriginAndPurpose(Sequence $softwareEnforced, Sequence $teeEnforced): void
    {
        $origins = [];
        $purposes = [];
        foreach ([$softwareEnforced, $teeEnforced] as $authorizationList) {
            $origin = $this->findOrigin($authorizationList);
            if ($origin !== null) {
                $origins[] = $origin;
            }
            $purposes = array_merge($purposes, $this->findPurposes($authorizationList));
        }

        $origins !== [] || throw AttestationStatementVerificationException::create(
            'The origin field is missing from the authorization lists'
        );
        foreach ($origins as $origin) {
            $origin === self::KM_ORIGIN_GENERATED || throw AttestationStatementVerificationException::create(
                'The key was not generated by the authenticator'
            );
        }

        $purposes !== [] || throw AttestationStatementVerificationException::create(
            'The purpose field is missing from the authorization lists'
        );
        in_array(self::KM_PURPOSE_SIGN, $purposes, true) || throw AttestationStatementVerificationException::create(
            'The key is not allowed to sign'
        );
    }

    private function findOrigin(Sequence $authorizationList): null|int
    {
        $element = $this->findTaggedElement($authorizationList, self::ANDROID_TAG_ORIGIN);
        if ($element === null) {
            return null;
        }

        return $element->explicit()
            ->asInteger()
            ->intNumber();
    }

    /**
     * @return list<int>
     */
    private function findPurposes(Sequence $authorizationList): array
    {
        $element = $this->findTaggedElement($authorizationList, self::ANDROID_TAG_PURPOSE);
        if ($element === null) {
            return [];
        }

        $purposes = [];
        foreach ($element->explicit()->asSet()->elements() as $purpose) {
            $purposes[] = $purpose->asInteger()
                ->intNumber();
        }

        return $purposes;
    }

    private function findTaggedElement(Sequence $authorizationList, int $tag): null|ExplicitTagging
    {
        foreach ($authorizationList->elements() as $item) {
            $element = $item->asElement();
            $element instanceof ExplicitTagging || throw AttestationStatementVerificationException::create(
                'Invalid tag'
            );
            if ($element->tag() === $tag) {
                return $element;
            }
        }

        return null;
    }

    private function checkAbsenceOfAllApplicationsTag(Sequence $sequence): void
    {
        foreach ($sequence->elements() as $tag) {
            $element = $tag->asElement();
            $element instanceof ExplicitTagging || throw AttestationStatementVerificationException::create(
                'Invalid tag'
            );
            $element->tag() !== self::ANDROID_TAG_ALL_APPLICATIONS || throw AttestationStatementVerificationException::create(
                'The allApplications tag (' . self::ANDROID_TAG_ALL_APPLICATIONS . ') is forbidden - key must be bound to specific application'
            );
        }
    }
}

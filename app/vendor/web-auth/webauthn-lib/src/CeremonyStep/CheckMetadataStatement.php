<?php

declare(strict_types=1);

namespace Webauthn\CeremonyStep;

use function count;
use function in_array;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function sprintf;
use function trigger_deprecation;
use Webauthn\AttestationStatement\AttestationStatement;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\CredentialRecord;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\MetadataService\CanLogData;
use Webauthn\MetadataService\CertificateChain\CertificateChainValidator;
use Webauthn\MetadataService\CertificateChain\CertificateToolbox;
use Webauthn\MetadataService\MetadataStatementRepository;
use Webauthn\MetadataService\Statement\MetadataStatement;
use Webauthn\MetadataService\StatusReportRepository;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\TrustPath\CertificateTrustPath;

final class CheckMetadataStatement implements CeremonyStep, CanLogData
{
    private LoggerInterface $logger;

    private null|MetadataStatementRepository $metadataStatementRepository = null;

    private null|StatusReportRepository $statusReportRepository = null;

    private null|CertificateChainValidator $certificateChainValidator = null;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    public function enableMetadataStatementSupport(
        MetadataStatementRepository $metadataStatementRepository,
        StatusReportRepository $statusReportRepository,
        CertificateChainValidator $certificateChainValidator
    ): void {
        $this->metadataStatementRepository = $metadataStatementRepository;
        $this->statusReportRepository = $statusReportRepository;
        $this->certificateChainValidator = $certificateChainValidator;
    }

    public function enableCertificateChainValidator(CertificateChainValidator $certificateChainValidator): void
    {
        $this->certificateChainValidator = $certificateChainValidator;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function process(
        CredentialRecord $credentialRecord,
        AuthenticatorAssertionResponse|AuthenticatorAttestationResponse $authenticatorResponse,
        PublicKeyCredentialRequestOptions|PublicKeyCredentialCreationOptions $publicKeyCredentialOptions,
        ?string $userHandle,
        string $host
    ): void {
        if ($credentialRecord instanceof PublicKeyCredentialSource) {
            trigger_deprecation(
                'web-auth/webauthn-lib',
                '5.3',
                'Passing a PublicKeyCredentialSource to "%s::process()" is deprecated, pass a CredentialRecord instead.',
                self::class
            );
        }
        if (
            ! $publicKeyCredentialOptions instanceof PublicKeyCredentialCreationOptions
            || ! $authenticatorResponse instanceof AuthenticatorAttestationResponse
        ) {
            return;
        }

        $attestationStatement = $authenticatorResponse->attestationObject->attStmt;
        $attestedCredentialData = $authenticatorResponse->attestationObject->authData
            ->attestedCredentialData;
        $attestedCredentialData !== null || throw AuthenticatorResponseVerificationException::create(
            'No attested credential data found'
        );

        if (! $this->isAttestationVerificationRequested($publicKeyCredentialOptions)) {
            $this->logger->debug('No attestation verification requested by RP.');
            return;
        }

        if ($attestationStatement->type === AttestationStatement::TYPE_NONE) {
            $this->logger->debug('None attestation format. No metadata verification required.');
            return;
        }

        $aaguid = $attestedCredentialData->aaguid
            ->__toString();
        if ($this->isNullAaguid($aaguid)) {
            $this->logger->debug('Null AAGUID detected. Skipping metadata verification.', [
                'reason' => 'Privacy placeholder or U2F device',
            ]);
            return;
        }

        if ($attestationStatement->type === AttestationStatement::TYPE_SELF) {
            $this->logger->debug('Self attestation detected.', [
                'aaguid' => $aaguid,
            ]);
            $this->processSelfAttestation($aaguid);
            return;
        }

        $this->logger->debug('Processing attestation with full metadata validation.', [
            'type' => $attestationStatement->type,
            'aaguid' => $aaguid,
        ]);
        $this->processWithMetadata($attestationStatement, $aaguid);
    }

    /**
     * Check if RP requested attestation verification.
     * @see https://www.w3.org/TR/webauthn-3/#dom-attestationconveyancepreference-none
     */
    private function isAttestationVerificationRequested(
        PublicKeyCredentialCreationOptions $publicKeyCredentialOptions
    ): bool {
        return ! in_array(
            $publicKeyCredentialOptions->attestation,
            [null, PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE],
            true
        );
    }

    /**
     * Check if AAGUID is all-zeros (privacy placeholder or U2F device).
     *
     * All-zeros AAGUID: either privacy placeholder or U2F device (which predates AAGUID).
     * 1) Privacy Placeholder indicates the authenticator does not provide detailed information.
     * 2) U2F device cannot provide useful AAGUID.
     *
     * So Metadata Statement lookup by AAGUID not possible.
     *
     * @see https://www.w3.org/TR/webauthn-3/#sctn-createCredential
     * @see https://fidoalliance.org/specs/fido-v2.2-ps-20250714/fido-client-to-authenticator-protocol-v2.2-ps-20250714.html#u2f-authenticatorMakeCredential-interoperability
     */
    private function isNullAaguid(string $aaguid): bool
    {
        return $aaguid === '00000000-0000-0000-0000-000000000000';
    }

    /**
     * Process self attestation: check MDS for compromised devices.
     *
     * Self attestation: authenticator uses credential key pair to sign attestation.
     * No attestation certificate chain to validate, but we still check MDS for compromised devices.
     *
     * @see https://www.w3.org/TR/webauthn-3/#self
     */
    private function processSelfAttestation(string $aaguid): void
    {
        $metadataStatement = $this->metadataStatementRepository?->findOneByAAGUID($aaguid);
        if ($metadataStatement === null) {
            $this->logger->info('No metadata statement found for self attestation. Skipping MDS verification.', [
                'aaguid' => $aaguid,
            ]);
            return;
        }

        $this->logger->debug('Metadata statement found for self attestation. Checking status reports.', [
            'aaguid' => $aaguid,
        ]);
        $this->checkStatusReport($aaguid);
        // Note: We do NOT check attestationTypes for self attestation as the authenticator
        // is using its credential key (not attestation certificate), which may differ from
        // the declared attestation types in metadata (which describe certificate-based attestation).
    }

    /**
     * Process attestation with full metadata validation (Basic, AttCA, AnonCA).
     */
    private function processWithMetadata(AttestationStatement $attestationStatement, string $aaguid): void
    {
        $this->metadataStatementRepository !== null || throw AuthenticatorResponseVerificationException::create(
            'The Metadata Statement Repository is mandatory when requesting attestation objects.'
        );
        $metadataStatement = $this->metadataStatementRepository->findOneByAAGUID($aaguid);
        $metadataStatement !== null || throw AuthenticatorResponseVerificationException::create(
            sprintf('The Metadata Statement for the AAGUID "%s" is missing', $aaguid)
        );

        $this->logger->debug('Metadata statement found. Starting validation.', [
            'aaguid' => $aaguid,
            'attestation_type' => $attestationStatement->type,
        ]);

        $this->checkStatusReport($aaguid);
        $this->logger->debug('Status report verification completed.');

        $this->checkCertificateChain($attestationStatement, $metadataStatement);
        $this->logger->debug('Certificate chain verification completed.');

        $this->checkAttestationTypeIsAllowed($attestationStatement, $metadataStatement);
        $this->logger->debug('Attestation type verification completed.');
    }

    /**
     * Check if the attestation type is allowed for this authenticator.
     */
    private function checkAttestationTypeIsAllowed(
        AttestationStatement $attestationStatement,
        MetadataStatement $metadataStatement
    ): void {
        if (count($metadataStatement->attestationTypes) === 0) {
            $this->logger->debug('No attestation types restrictions in metadata statement.');
            return;
        }

        $type = $this->getAttestationType($attestationStatement);
        $this->logger->debug('Checking attestation type.', [
            'type' => $type,
            'allowed_types' => $metadataStatement->attestationTypes,
        ]);

        in_array(
            $type,
            $metadataStatement->attestationTypes,
            true
        ) || throw AuthenticatorResponseVerificationException::create(
            sprintf(
                'Invalid attestation statement. The attestation type "%s" is not allowed for this authenticator.',
                $type
            )
        );
    }

    private function getAttestationType(AttestationStatement $attestationStatement): string
    {
        return match ($attestationStatement->type) {
            AttestationStatement::TYPE_BASIC => MetadataStatement::ATTESTATION_BASIC_FULL,
            AttestationStatement::TYPE_SELF => MetadataStatement::ATTESTATION_BASIC_SURROGATE,
            AttestationStatement::TYPE_ATTCA => MetadataStatement::ATTESTATION_ATTCA,
            AttestationStatement::TYPE_ANONCA => MetadataStatement::ATTESTATION_ANONCA,
            default => throw AuthenticatorResponseVerificationException::create('Invalid attestation type'),
        };
    }

    private function checkStatusReport(string $aaguid): void
    {
        $statusReports = $this->statusReportRepository === null ? [] : $this->statusReportRepository->findStatusReportsByAAGUID(
            $aaguid
        );
        if (count($statusReports) === 0) {
            $this->logger->debug('No status reports found for authenticator.', [
                'aaguid' => $aaguid,
            ]);
            return;
        }

        $lastStatusReport = end($statusReports);
        $this->logger->debug('Status report found.', [
            'aaguid' => $aaguid,
            'status' => $lastStatusReport->status,
        ]);

        if ($lastStatusReport->isCompromised()) {
            $this->logger->warning('Authenticator is marked as compromised in MDS.', [
                'aaguid' => $aaguid,
                'status' => $lastStatusReport->status,
            ]);
            throw AuthenticatorResponseVerificationException::create(
                'The authenticator is compromised and cannot be used'
            );
        }
    }

    private function checkCertificateChain(
        AttestationStatement $attestationStatement,
        MetadataStatement $metadataStatement
    ): void {
        $trustPath = $attestationStatement->trustPath;
        $trustPath instanceof CertificateTrustPath || throw AuthenticatorResponseVerificationException::create(
            'Certificate trust path is required for attestation verification'
        );
        $authenticatorCertificates = $trustPath->certificates;
        $trustedCertificates = CertificateToolbox::fixPEMStructures(
            $metadataStatement->attestationRootCertificates
        );
        $this->certificateChainValidator?->check($authenticatorCertificates, $trustedCertificates);
    }
}

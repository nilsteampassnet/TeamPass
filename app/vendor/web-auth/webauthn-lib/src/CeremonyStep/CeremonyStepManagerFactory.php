<?php

declare(strict_types=1);

namespace Webauthn\CeremonyStep;

use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\Counter\CounterChecker;
use Webauthn\Counter\ThrowExceptionIfInvalid;
use Webauthn\MetadataService\CertificateChain\CertificateChainValidator;
use Webauthn\MetadataService\MetadataStatementRepository;
use Webauthn\MetadataService\StatusReportRepository;

final class CeremonyStepManagerFactory
{
    private CounterChecker $counterChecker;

    private Manager $algorithmManager;

    private null|MetadataStatementRepository $metadataStatementRepository = null;

    private null|StatusReportRepository $statusReportRepository = null;

    private null|CertificateChainValidator $certificateChainValidator = null;

    private null|TopOriginValidator $topOriginValidator = null;

    /**
     * @var string[]
     */
    private null|array $securedRelyingPartyId = null;

    /**
     * @var string[]
     */
    private null|array $allowedOrigins = null;

    private bool $allowSubdomains = false;

    private AttestationStatementSupportManager $attestationStatementSupportManager;

    private ExtensionOutputCheckerHandler $extensionOutputCheckerHandler;

    public function __construct()
    {
        $this->counterChecker = new ThrowExceptionIfInvalid();
        $this->algorithmManager = Manager::create()->add(ES256::create(), RS256::create());
        $this->attestationStatementSupportManager = new AttestationStatementSupportManager([
            new NoneAttestationStatementSupport(),
        ]);
        $this->extensionOutputCheckerHandler = new ExtensionOutputCheckerHandler();
    }

    public function setCounterChecker(CounterChecker $counterChecker): void
    {
        $this->counterChecker = $counterChecker;
    }

    /**
     * @deprecated since 5.2.0 and will be removed in 6.0.0. Use setAllowedOrigins instead.
     * @param string[] $securedRelyingPartyId
     */
    public function setSecuredRelyingPartyId(array $securedRelyingPartyId): void
    {
        $this->securedRelyingPartyId = $securedRelyingPartyId;
    }

    /**
     * @param string[] $allowedOrigins
     */
    public function setAllowedOrigins(array $allowedOrigins, bool $allowSubdomains = false): void
    {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowSubdomains = $allowSubdomains;
    }

    public function setExtensionOutputCheckerHandler(ExtensionOutputCheckerHandler $extensionOutputCheckerHandler): void
    {
        $this->extensionOutputCheckerHandler = $extensionOutputCheckerHandler;
    }

    public function setAttestationStatementSupportManager(
        AttestationStatementSupportManager $attestationStatementSupportManager
    ): void {
        $this->attestationStatementSupportManager = $attestationStatementSupportManager;
    }

    public function setAlgorithmManager(Manager $algorithmManager): void
    {
        $this->algorithmManager = $algorithmManager;
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

    public function enableTopOriginValidator(TopOriginValidator $topOriginValidator): void
    {
        $this->topOriginValidator = $topOriginValidator;
    }

    public function requestCeremony(): CeremonyStepManager
    {
        /* @see https://www.w3.org/TR/webauthn-3/#sctn-verifying-assertion */
        return new CeremonyStepManager([
            new CheckAllowedCredentialList(),
            new CheckUserHandle(),
            new CheckClientDataCollectorType(),
            new CheckChallenge(),
            $this->allowedOrigins === null ? new CheckOrigin(
                $this->securedRelyingPartyId ?? []
            ) : new CheckAllowedOrigins(
                $this->allowedOrigins,
                $this->allowSubdomains,
                $this->securedRelyingPartyId ?? []
            ),
            new CheckTopOrigin($this->topOriginValidator),
            new CheckRelyingPartyIdIdHash(),
            new CheckUserWasPresent(),
            new CheckUserVerification(),
            new CheckBackupBitsAreConsistent(),
            new CheckExtensions($this->extensionOutputCheckerHandler),
            new CheckSignature($this->algorithmManager),
            new CheckCounter($this->counterChecker),
        ]);
    }

    public function creationCeremony(): CeremonyStepManager
    {
        return $this->buildCreationCeremony(true);
    }

    /**
     * Create a ceremony manager for Conditional Create (auto-register)
     *
     * Use this when creating credentials with mediation: 'conditional',
     * where user presence may be false after password authentication.
     *
     * @see https://github.com/w3c/webauthn/wiki/Explainer:-Conditional-Create
     * @see https://github.com/web-auth/webauthn-framework/issues/719
     */
    public function conditionalCreateCeremony(): CeremonyStepManager
    {
        return $this->buildCreationCeremony(false);
    }

    private function buildCreationCeremony(bool $requireUserPresence): CeremonyStepManager
    {
        $metadataStatementChecker = new CheckMetadataStatement();
        if ($this->certificateChainValidator !== null) {
            $metadataStatementChecker->enableCertificateChainValidator($this->certificateChainValidator);
        }
        if ($this->metadataStatementRepository !== null && $this->statusReportRepository !== null && $this->certificateChainValidator !== null) {
            $metadataStatementChecker->enableMetadataStatementSupport(
                $this->metadataStatementRepository,
                $this->statusReportRepository,
                $this->certificateChainValidator,
            );
        }

        /* @see https://www.w3.org/TR/webauthn-3/#sctn-registering-a-new-credential */
        return new CeremonyStepManager([
            new CheckClientDataCollectorType(),
            new CheckChallenge(),
            $this->allowedOrigins === null ? new CheckOrigin(
                $this->securedRelyingPartyId ?? []
            ) : new CheckAllowedOrigins(
                $this->allowedOrigins,
                $this->allowSubdomains,
                $this->securedRelyingPartyId ?? []
            ),
            new CheckTopOrigin($this->topOriginValidator),
            new CheckRelyingPartyIdIdHash(),
            new CheckUserWasPresent($requireUserPresence),
            new CheckUserVerification($requireUserPresence),
            new CheckBackupBitsAreConsistent(),
            new CheckAlgorithm(),
            new CheckExtensions($this->extensionOutputCheckerHandler),
            new CheckAttestationFormatIsKnownAndValid($this->attestationStatementSupportManager),
            new CheckHasAttestedCredentialData(),
            $metadataStatementChecker,
            new CheckCredentialId(),
        ]);
    }
}

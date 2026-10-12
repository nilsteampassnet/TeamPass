<?php

declare(strict_types=1);

namespace Webauthn;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Webauthn\CeremonyStep\CeremonyStepManager;
use Webauthn\Event\AuthenticatorAttestationResponseValidationFailedEvent;
use Webauthn\Event\AuthenticatorAttestationResponseValidationSucceededEvent;
use Webauthn\Event\BackupEligibilityChangedEvent;
use Webauthn\Event\BackupStatusChangedEvent;
use Webauthn\Event\CanDispatchEvents;
use Webauthn\Event\NullEventDispatcher;
use Webauthn\Exception\AuthenticatorResponseVerificationException;
use Webauthn\MetadataService\CanLogData;

class AuthenticatorAttestationResponseValidator implements CanLogData, CanDispatchEvents
{
    private LoggerInterface $logger;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        private readonly CeremonyStepManager $ceremonyStepManager
    ) {
        $this->eventDispatcher = new NullEventDispatcher();
        $this->logger = new NullLogger();
    }

    public static function create(CeremonyStepManager $ceremonyStepManager): self
    {
        return new self($ceremonyStepManager);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @see https://www.w3.org/TR/webauthn/#registering-a-new-credential
     */
    public function check(
        AuthenticatorAttestationResponse $authenticatorAttestationResponse,
        PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions,
        string $host,
    ): CredentialRecord {
        try {
            $this->logger->info('Checking the authenticator attestation response', [
                'authenticatorAttestationResponse' => $authenticatorAttestationResponse,
                'publicKeyCredentialCreationOptions' => $publicKeyCredentialCreationOptions,
                'host' => $host,
            ]);

            $credentialRecord = $this->createCredentialRecord(
                $authenticatorAttestationResponse,
                $publicKeyCredentialCreationOptions
            );

            $this->ceremonyStepManager->process(
                $credentialRecord,
                $authenticatorAttestationResponse,
                $publicKeyCredentialCreationOptions,
                $publicKeyCredentialCreationOptions->user->id,
                $host
            );

            // Store previous backup state values to detect changes (will be null for new credentials)
            $previousBackupEligible = $credentialRecord->backupEligible;
            $previousBackupStatus = $credentialRecord->backupStatus;

            $credentialRecord->counter = $authenticatorAttestationResponse->attestationObject->authData->signCount;
            $credentialRecord->backupEligible = $authenticatorAttestationResponse->attestationObject->authData->isBackupEligible();
            $credentialRecord->backupStatus = $authenticatorAttestationResponse->attestationObject->authData->isBackedUp();
            $credentialRecord->uvInitialized = $authenticatorAttestationResponse->attestationObject->authData->isUserVerified();

            // Dispatch events if backup state changed
            if ($previousBackupEligible !== $credentialRecord->backupEligible) {
                $this->eventDispatcher->dispatch(
                    new BackupEligibilityChangedEvent(
                        $credentialRecord,
                        $previousBackupEligible,
                        $credentialRecord->backupEligible
                    )
                );
            }
            if ($previousBackupStatus !== $credentialRecord->backupStatus) {
                $this->eventDispatcher->dispatch(
                    new BackupStatusChangedEvent(
                        $credentialRecord,
                        $previousBackupStatus,
                        $credentialRecord->backupStatus
                    )
                );
            }

            $this->logger->info('The attestation is valid');
            $this->logger->debug('Credential Record', [
                'credentialRecord' => $credentialRecord,
            ]);
            $this->eventDispatcher->dispatch(
                $this->createAuthenticatorAttestationResponseValidationSucceededEvent(
                    $authenticatorAttestationResponse,
                    $publicKeyCredentialCreationOptions,
                    $host,
                    $credentialRecord
                )
            );
            return $credentialRecord;
        } catch (Throwable $throwable) {
            $this->logger->error('An error occurred', [
                'exception' => $throwable,
            ]);
            $this->eventDispatcher->dispatch(
                $this->createAuthenticatorAttestationResponseValidationFailedEvent(
                    $authenticatorAttestationResponse,
                    $publicKeyCredentialCreationOptions,
                    $host,
                    $throwable
                )
            );
            throw $throwable;
        }
    }

    protected function createAuthenticatorAttestationResponseValidationSucceededEvent(
        AuthenticatorAttestationResponse $authenticatorAttestationResponse,
        PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions,
        string $host,
        CredentialRecord $credentialRecord
    ): AuthenticatorAttestationResponseValidationSucceededEvent {
        return new AuthenticatorAttestationResponseValidationSucceededEvent(
            $authenticatorAttestationResponse,
            $publicKeyCredentialCreationOptions,
            $host,
            $credentialRecord
        );
    }

    protected function createAuthenticatorAttestationResponseValidationFailedEvent(
        AuthenticatorAttestationResponse $authenticatorAttestationResponse,
        PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions,
        string $host,
        Throwable $throwable
    ): AuthenticatorAttestationResponseValidationFailedEvent {
        return new AuthenticatorAttestationResponseValidationFailedEvent(
            $authenticatorAttestationResponse,
            $publicKeyCredentialCreationOptions,
            $host,
            $throwable
        );
    }

    private function createCredentialRecord(
        AuthenticatorAttestationResponse $authenticatorAttestationResponse,
        PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions,
    ): CredentialRecord {
        $attestationObject = $authenticatorAttestationResponse->attestationObject;
        $attestedCredentialData = $attestationObject->authData->attestedCredentialData;
        $attestedCredentialData !== null || throw AuthenticatorResponseVerificationException::create(
            'Not attested credential data'
        );
        $credentialId = $attestedCredentialData->credentialId;
        $credentialPublicKey = $attestedCredentialData->credentialPublicKey;
        $credentialPublicKey !== null || throw AuthenticatorResponseVerificationException::create(
            'Not credential public key available in the attested credential data'
        );
        $userHandle = $publicKeyCredentialCreationOptions->user->id;
        $transports = $authenticatorAttestationResponse->transports;

        return CredentialRecord::create(
            $credentialId,
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            $transports,
            $attestationObject->attStmt
                ->type,
            $attestationObject->attStmt
                ->trustPath,
            $attestedCredentialData->aaguid,
            $credentialPublicKey,
            $userHandle,
            $attestationObject->authData
                ->signCount,
        );
    }
}

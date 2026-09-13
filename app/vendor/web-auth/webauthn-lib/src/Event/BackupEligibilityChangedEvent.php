<?php

declare(strict_types=1);

namespace Webauthn\Event;

use LogicException;
use function sprintf;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialSource;

/**
 * Event dispatched when the backup eligibility flag (BE) changes.
 *
 * The BE flag indicates whether the authenticator is capable of backing up
 * the credential. A change in this flag may indicate device capabilities have changed.
 */
final readonly class BackupEligibilityChangedEvent implements WebauthnEvent
{
    public function __construct(
        public CredentialRecord $credentialRecord,
        public ?bool $previousValue,
        public ?bool $newValue
    ) {
    }

    /**
     * @deprecated since 5.3, use credentialRecord instead. Will be removed in 6.0.
     */
    public function __get(string $name): mixed
    {
        if ($name === 'publicKeyCredentialSource') {
            return $this->getPublicKeyCredentialSource();
        }

        throw new LogicException(sprintf('Undefined property: %s::$%s', self::class, $name));
    }

    /**
     * @deprecated since 5.3, use credentialRecord instead. Will be removed in 6.0.
     */
    public function getPublicKeyCredentialSource(): PublicKeyCredentialSource
    {
        if ($this->credentialRecord instanceof PublicKeyCredentialSource) {
            return $this->credentialRecord;
        }

        return new PublicKeyCredentialSource(
            $this->credentialRecord->publicKeyCredentialId,
            $this->credentialRecord->type,
            $this->credentialRecord->transports,
            $this->credentialRecord->attestationType,
            $this->credentialRecord->trustPath,
            $this->credentialRecord->aaguid,
            $this->credentialRecord->credentialPublicKey,
            $this->credentialRecord->userHandle,
            $this->credentialRecord->counter,
            $this->credentialRecord->otherUI,
            $this->credentialRecord->backupEligible,
            $this->credentialRecord->backupStatus,
            $this->credentialRecord->uvInitialized,
        );
    }
}

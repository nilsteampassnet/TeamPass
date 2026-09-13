<?php

declare(strict_types=1);

namespace Webauthn\Event;

use LogicException;
use function sprintf;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialSource;

class AuthenticatorAttestationResponseValidationSucceededEvent
{
    public function __construct(
        public readonly AuthenticatorAttestationResponse $authenticatorAttestationResponse,
        public readonly PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions,
        public readonly string $host,
        public readonly CredentialRecord $credentialRecord
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

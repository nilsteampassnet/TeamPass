<?php

declare(strict_types=1);

namespace Webauthn\Event;

use LogicException;
use function sprintf;
use Throwable;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;

class AuthenticatorAssertionResponseValidationFailedEvent
{
    public function __construct(
        public readonly CredentialRecord $credentialRecord,
        public readonly AuthenticatorAssertionResponse $authenticatorAssertionResponse,
        public readonly PublicKeyCredentialRequestOptions $publicKeyCredentialRequestOptions,
        public readonly string $host,
        public readonly ?string $userHandle,
        public readonly Throwable $throwable
    ) {
    }

    /**
     * @deprecated since 5.3, use credentialRecord instead. Will be removed in 6.0.
     */
    public function __get(string $name): mixed
    {
        if ($name === 'credentialSource') {
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

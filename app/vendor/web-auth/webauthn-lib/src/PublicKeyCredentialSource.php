<?php

declare(strict_types=1);

namespace Webauthn;

/**
 * @see https://www.w3.org/TR/webauthn/#iface-pkcredential
 *
 * @deprecated since 5.3, use CredentialRecord instead. Will be removed in 6.0.
 */
class PublicKeyCredentialSource extends CredentialRecord
{
    /**
     * @internal Bridge for legacy CanSaveCredentialSource repositories.
     *           Wraps a CredentialRecord into a PublicKeyCredentialSource without copying additional state
     *           (PublicKeyCredentialSource has no own properties).
     */
    public static function fromCredentialRecord(CredentialRecord $credentialRecord): self
    {
        if ($credentialRecord instanceof self) {
            return $credentialRecord;
        }

        return new self(
            $credentialRecord->publicKeyCredentialId,
            $credentialRecord->type,
            $credentialRecord->transports,
            $credentialRecord->attestationType,
            $credentialRecord->trustPath,
            $credentialRecord->aaguid,
            $credentialRecord->credentialPublicKey,
            $credentialRecord->userHandle,
            $credentialRecord->counter,
            $credentialRecord->otherUI,
            $credentialRecord->backupEligible,
            $credentialRecord->backupStatus,
            $credentialRecord->uvInitialized,
        );
    }
}

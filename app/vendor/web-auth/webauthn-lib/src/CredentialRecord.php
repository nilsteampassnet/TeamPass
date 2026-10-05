<?php

declare(strict_types=1);

namespace Webauthn;

use Symfony\Component\Uid\Uuid;
use Webauthn\TrustPath\TrustPath;

/**
 * Credential Record represents a stored credential on the server side.
 * It contains all the necessary data for validating authentication assertions.
 *
 * @see https://www.w3.org/TR/webauthn-3/#credential-record
 */
class CredentialRecord
{
    /**
     * @param string[] $transports
     * @param array<string, mixed>|null $otherUI
     */
    public function __construct(
        public string $publicKeyCredentialId,
        public string $type,
        public array $transports,
        public string $attestationType,
        public TrustPath $trustPath,
        public Uuid $aaguid,
        public string $credentialPublicKey,
        public string $userHandle,
        public int $counter,
        public ?array $otherUI = null,
        public ?bool $backupEligible = null,
        public ?bool $backupStatus = null,
        public ?bool $uvInitialized = null,
    ) {
    }

    /**
     * @param string[] $transports
     * @param array<string, mixed>|null $otherUI
     */
    public static function create(
        string $publicKeyCredentialId,
        string $type,
        array $transports,
        string $attestationType,
        TrustPath $trustPath,
        Uuid $aaguid,
        string $credentialPublicKey,
        string $userHandle,
        int $counter,
        ?array $otherUI = null,
        ?bool $backupEligible = null,
        ?bool $backupStatus = null,
        ?bool $uvInitialized = null,
    ): self {
        return new self(
            $publicKeyCredentialId,
            $type,
            $transports,
            $attestationType,
            $trustPath,
            $aaguid,
            $credentialPublicKey,
            $userHandle,
            $counter,
            $otherUI,
            $backupEligible,
            $backupStatus,
            $uvInitialized
        );
    }

    public function getPublicKeyCredentialDescriptor(): PublicKeyCredentialDescriptor
    {
        return PublicKeyCredentialDescriptor::create($this->type, $this->publicKeyCredentialId, $this->transports);
    }

    public function getAttestedCredentialData(): AttestedCredentialData
    {
        return AttestedCredentialData::create($this->aaguid, $this->publicKeyCredentialId, $this->credentialPublicKey);
    }
}

<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Statement;

use function in_array;
use Webauthn\Exception\MetadataStatementLoadingException;

readonly class StatusReport
{
    /**
     * @see AuthenticatorStatus
     */
    public function __construct(
        public string $status,
        public ?string $effectiveDate,
        public ?string $certificate,
        public ?string $url,
        public ?string $certificationDescriptor,
        public ?string $certificateNumber,
        public ?string $certificationPolicyVersion,
        public ?string $certificationRequirementsVersion
    ) {
        in_array($status, AuthenticatorStatus::STATUSES, true) || throw MetadataStatementLoadingException::create(
            'The value of the key "status" is not acceptable'
        );
    }

    public static function create(
        string $status,
        ?string $effectiveDate,
        ?string $certificate,
        ?string $url,
        ?string $certificationDescriptor,
        ?string $certificateNumber,
        ?string $certificationPolicyVersion,
        ?string $certificationRequirementsVersion
    ): self {
        return new self(
            $status,
            $effectiveDate,
            $certificate,
            $url,
            $certificationDescriptor,
            $certificateNumber,
            $certificationPolicyVersion,
            $certificationRequirementsVersion
        );
    }

    public function isCompromised(): bool
    {
        return in_array($this->status, [
            AuthenticatorStatus::ATTESTATION_KEY_COMPROMISE,
            AuthenticatorStatus::USER_KEY_PHYSICAL_COMPROMISE,
            AuthenticatorStatus::USER_KEY_REMOTE_COMPROMISE,
            AuthenticatorStatus::USER_VERIFICATION_BYPASS,
        ], true);
    }
}

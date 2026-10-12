<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Statement;

readonly class BiometricStatusReport
{
    private function __construct(
        public ?int $certLevel,
        public ?int $modality,
        public ?string $effectiveDate,
        public ?string $certificationDescriptor,
        public ?string $certificateNumber,
        public ?string $certificationPolicyVersion,
        public ?string $certificationRequirementsVersion,
    ) {
    }

    public static function create(
        ?int $certLevel,
        ?int $modality,
        ?string $effectiveDate,
        ?string $certificationDescriptor,
        ?string $certificateNumber,
        ?string $certificationPolicyVersion,
        ?string $certificationRequirementsVersion,
    ): self {
        return new self(
            $certLevel,
            $modality,
            $effectiveDate,
            $certificationDescriptor,
            $certificateNumber,
            $certificationPolicyVersion,
            $certificationRequirementsVersion,
        );
    }
}

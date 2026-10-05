<?php

declare(strict_types=1);

namespace Webauthn\MetadataService\Statement;

class VerificationMethodANDCombinations
{
    /**
     * @param VerificationMethodDescriptor[] $verificationMethods
     */
    public function __construct(
        /**
         * @readonly
         */
        public array $verificationMethods = []
    ) {
    }

    /**
     * @param VerificationMethodDescriptor[] $verificationMethods
     */
    public static function create(array $verificationMethods): self
    {
        return new self($verificationMethods);
    }
}

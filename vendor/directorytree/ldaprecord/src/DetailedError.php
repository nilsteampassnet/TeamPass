<?php

namespace LdapRecord;

class DetailedError
{
    /**
     * Constructor.
     */
    public function __construct(
        protected int $errorCode,
        protected string $errorMessage,
        protected ?string $diagnosticMessage
    ) {
    }

    /**
     * Returns the LDAP error code.
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * Returns the LDAP error message.
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * Returns the LDAP diagnostic message.
     */
    public function getDiagnosticMessage(): ?string
    {
        return $this->diagnosticMessage;
    }
}

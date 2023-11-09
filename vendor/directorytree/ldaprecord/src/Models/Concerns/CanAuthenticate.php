<?php

namespace LdapRecord\Models\Concerns;

/** @mixin \LdapRecord\Models\Model */
trait CanAuthenticate
{
    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return $this->guidKey;
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): ?string
    {
        return $this->getConvertedGuid();
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * Get the token value for the "remember me" session.
     */
    public function getRememberToken(): string
    {
        return '';
    }

    /**
     * Set the token value for the "remember me" session.
     */
    public function setRememberToken($value): void
    {
    }

    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }
}

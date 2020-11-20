<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\ConnectionException;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Attributes\Password;

trait HasPassword
{
    /**
     * The attribute to use for password changes.
     *
     * @var string
     */
    protected $passwordAttribute = 'unicodepwd';

    /**
     * The method to use for hashing / encoding user passwords.
     *
     * @var string
     */
    protected $passwordHashMethod = 'encode';

    /**
     * Set the password on the user.
     *
     * @param string|array $password
     *
     * @throws \LdapRecord\ConnectionException
     */
    public function setPasswordAttribute($password)
    {
        $this->validateSecureConnection();

        // If the password given is an array, we can assume we
        // are changing the password for the current user.
        if (is_array($password)) {
            $this->setChangedPassword(
                $this->getHashedPassword($password[0]),
                $this->getHashedPassword($password[1])
            );
        }
        // Otherwise, we will set the password normally.
        else {
            $this->setPassword($this->getHashedPassword($password));
        }
    }

    /**
     * Alias for setting the password on the user.
     *
     * @param string|array $password
     *
     * @throws \LdapRecord\ConnectionException
     */
    public function setUnicodepwdAttribute($password)
    {
        $this->setPasswordAttribute($password);
    }

    /**
     * Set the changed password.
     *
     * @param string $oldPassword
     * @param string $newPassword
     *
     * @return void
     */
    protected function setChangedPassword($oldPassword, $newPassword)
    {
        // Create batch modification for removing the old password.
        $this->addModification(
            $this->newBatchModification(
                $this->passwordAttribute,
                LDAP_MODIFY_BATCH_REMOVE,
                [$oldPassword]
            )
        );

        // Create batch modification for adding the new password.
        $this->addModification(
            $this->newBatchModification(
                $this->passwordAttribute,
                LDAP_MODIFY_BATCH_ADD,
                [$newPassword]
            )
        );
    }

    /**
     * Set the password on the model.
     *
     * @param string $password
     *
     * @return void
     */
    protected function setPassword($password)
    {
        $this->addModification(
            $this->newBatchModification(
                $this->passwordAttribute,
                LDAP_MODIFY_BATCH_REPLACE,
                [$password]
            )
        );
    }

    /**
     * Encode / hash the given password.
     *
     * @param string $password
     *
     * @throws LdapRecordException
     *
     * @return string
     */
    protected function getHashedPassword($password)
    {
        if (! method_exists(Password::class, $this->passwordHashMethod)) {
            throw new LdapRecordException("Password hashing method [{$this->passwordHashMethod}] does not exist.");
        }

        return Password::{$this->passwordHashMethod}($password);
    }

    /**
     * Validates that the current LDAP connection is secure.
     *
     * @throws ConnectionException
     *
     * @return void
     */
    protected function validateSecureConnection()
    {
        if (! $this->getConnection()->getLdapConnection()->canChangePasswords()) {
            throw new ConnectionException(
                'You must be connected to your LDAP server with TLS or SSL to perform this operation.'
            );
        }
    }
}

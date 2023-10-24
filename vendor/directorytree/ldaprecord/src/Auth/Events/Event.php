<?php

namespace LdapRecord\Auth\Events;

use LdapRecord\LdapInterface;

abstract class Event
{
    /**
     * The connection that the username and password is being bound on.
     */
    protected LdapInterface $connection;

    /**
     * The username that is being used for binding.
     */
    protected ?string $username;

    /**
     * The password that is being used for binding.
     */
    protected ?string $password;

    /**
     * Constructor.
     */
    public function __construct(LdapInterface $connection, string $username = null, string $password = null)
    {
        $this->connection = $connection;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Returns the events connection.
     */
    public function getConnection(): LdapInterface
    {
        return $this->connection;
    }

    /**
     * Returns the authentication events username.
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Returns the authentication events password.
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }
}

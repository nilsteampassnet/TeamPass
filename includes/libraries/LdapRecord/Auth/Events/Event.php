<?php

namespace LdapRecord\Auth\Events;

use LdapRecord\Ldap;

abstract class Event
{
    /**
     * The connection that the username and password is being bound on.
     *
     * @var Ldap
     */
    protected $connection;

    /**
     * The username that is being used for binding.
     *
     * @var string
     */
    protected $username;

    /**
     * The password that is being used for binding.
     *
     * @var string
     */
    protected $password;

    /**
     * Constructor.
     *
     * @param Ldap   $connection
     * @param string $username
     * @param string $password
     */
    public function __construct(Ldap $connection, $username, $password)
    {
        $this->connection = $connection;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Returns the events connection.
     *
     * @return Ldap
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Returns the authentication events username.
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Returns the authentication events password.
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }
}

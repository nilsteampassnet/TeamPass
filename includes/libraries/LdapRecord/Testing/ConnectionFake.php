<?php

namespace LdapRecord\Testing;

use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Model;

class ConnectionFake extends Connection
{
    /**
     * The underlying fake LDAP connection.
     *
     * @var LdapFake
     */
    protected $ldap;

    /**
     * Make a new fake LDAP connection instance.
     *
     * @param array $config
     *
     * @return static
     */
    public static function make(array $config = [])
    {
        return new static($config, new LdapFake());
    }

    /**
     * Set the user to authenticate as.
     *
     * @param Model|string $user
     *
     * @return $this
     */
    public function actingAs($user)
    {
        $this->ldap->shouldAuthenticateWith(
            $user instanceof Model ? $user->getDn() : $user
        );

        return $this;
    }

    /**
     * Create a new fake auth guard.
     *
     * @return AuthGuardFake
     */
    public function auth()
    {
        $guard = new AuthGuardFake($this->ldap, $this->configuration);

        $guard->setDispatcher(Container::getEventDispatcher());

        return $guard;
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected()
    {
        return true;
    }
}

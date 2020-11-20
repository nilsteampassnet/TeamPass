<?php

namespace LdapRecord;

use Closure;
use Throwable;
use Carbon\Carbon;
use LdapRecord\Auth\Guard;
use LdapRecord\Query\Cache;
use LdapRecord\Query\Builder;
use Psr\SimpleCache\CacheInterface;
use LdapRecord\Configuration\DomainConfiguration;

class Connection
{
    use DetectsErrors;

    /**
     * The underlying LDAP connection.
     *
     * @var Ldap
     */
    protected $ldap;

    /**
     * The domain configuration.
     *
     * @var DomainConfiguration
     */
    protected $configuration;

    /**
     * The cache driver.
     *
     * @var Cache|null
     */
    protected $cache;

    /**
     * The current host connected to.
     *
     * @var string
     */
    protected $host;

    /**
     * The configured domain hosts.
     *
     * @var array
     */
    protected $hosts = [];

    /**
     * The attempted hosts that failed connecting to.
     *
     * @var array
     */
    protected $attempted = [];

    /**
     * Constructor.
     *
     * @param array     $config
     * @param Ldap|null $ldap
     */
    public function __construct($config = [], Ldap $ldap = null)
    {
        $this->configuration = new DomainConfiguration($config);

        $this->hosts = $this->configuration->get('hosts');

        $this->host = reset($this->hosts);

        $this->setLdapConnection($ldap ?? new Ldap());
    }

    /**
     * Set the connection configuration.
     *
     * @param array $config
     *
     * @throws Configuration\ConfigurationException
     */
    public function setConfiguration($config = [])
    {
        $this->configuration = new DomainConfiguration($config);

        return $this;
    }

    /**
     * Set the LDAP connection.
     *
     * @param Ldap $ldap
     *
     * @return $this
     */
    public function setLdapConnection(Ldap $ldap)
    {
        $this->ldap = $ldap;

        $this->initialize();

        return $this;
    }

    /**
     * Initializes the LDAP connection.
     *
     * @return void
     */
    public function initialize()
    {
        $this->configure();

        $this->ldap->connect($this->host, $this->configuration->get('port'));
    }

    /**
     * Configure the LDAP connection.
     *
     * @return void
     */
    protected function configure()
    {
        if ($this->configuration->get('use_ssl')) {
            $this->ldap->ssl();
        } elseif ($this->configuration->get('use_tls')) {
            $this->ldap->tls();
        }

        $this->ldap->setOptions(array_replace(
            $this->configuration->get('options'),
            [
                LDAP_OPT_PROTOCOL_VERSION => $this->configuration->get('version'),
                LDAP_OPT_NETWORK_TIMEOUT  => $this->configuration->get('timeout'),
                LDAP_OPT_REFERRALS        => $this->configuration->get('follow_referrals'),
            ]
        ));
    }

    /**
     * Sets the cache store.
     *
     * @param CacheInterface $store
     *
     * @return $this
     */
    public function setCache(CacheInterface $store)
    {
        $this->cache = new Cache($store);

        return $this;
    }

    /**
     * Get the cache store.
     *
     * @return Cache|null
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Get the LDAP configuration instance.
     *
     * @return DomainConfiguration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * Get the LDAP connection instance.
     *
     * @return Ldap
     */
    public function getLdapConnection()
    {
        return $this->ldap;
    }

    /**
     * Bind to the LDAP server.
     *
     * If no username or password is specified, then the configured credentials are used.
     *
     * @param string|null $username
     * @param string|null $password
     *
     * @throws ConnectionException If upgrading the connection to TLS fails
     * @throws Auth\BindException  If binding to the LDAP server fails.
     *
     * @return Connection
     */
    public function connect($username = null, $password = null)
    {
        if (is_null($username) && is_null($password)) {
            $this->auth()->bindAsConfiguredUser();
        } else {
            $this->auth()->bind($username, $password);
        }

        return $this;
    }

    /**
     * Reconnect to the LDAP server.
     *
     * @throws Auth\BindException
     * @throws ConnectionException
     */
    public function reconnect()
    {
        $this->disconnect();

        $this->initialize();

        $this->connect();
    }

    /**
     * Disconnect from the LDAP server.
     *
     * @return void
     */
    public function disconnect()
    {
        $this->ldap->close();
    }

    /**
     * Get the attempted hosts that failed connecting to.
     *
     * @return array
     */
    public function attempted()
    {
        return $this->attempted;
    }

    /**
     * Perform the operation on the LDAP connection.
     *
     * @param Closure $operation
     *
     * @return mixed
     */
    public function run(Closure $operation)
    {
        try {
            // Before running the operation, we will check if the current
            // connection is bound and connect if necessary. Otherwise
            // some LDAP operations will not be executed properly.
            if (! $this->isConnected()) {
                $this->connect();
            }

            return $this->runOperationCallback($operation);
        } catch (LdapRecordException $e) {
            if ($exception = $this->getExceptionForCauseOfFailure($e)) {
                throw $exception;
            }

            return $this->tryAgainIfCausedByLostConnection($e, $operation);
        }
    }

    /**
     * Attempt to get an exception for the cause of failure.
     *
     * @param LdapRecordException $e
     *
     * @return mixed
     */
    protected function getExceptionForCauseOfFailure(LdapRecordException $e)
    {
        switch (true) {
            case $this->errorContainsMessage($e->getMessage(), 'Already exists'):
                return Exceptions\AlreadyExistsException::withDetailedError($e, $e->getDetailedError());
            case $this->errorContainsMessage($e->getMessage(), 'Insufficient access'):
                return Exceptions\InsufficientAccessException::withDetailedError($e, $e->getDetailedError());
            case $this->errorContainsMessage($e->getMessage(), 'Constraint violation'):
                return Exceptions\ConstraintViolationException::withDetailedError($e, $e->getDetailedError());
            default:
                return;
        }
    }

    /**
     * Run the operation callback on the current LDAP connection.
     *
     * @param Closure $operation
     *
     * @throws LdapRecordException
     *
     * @return mixed
     */
    protected function runOperationCallback(Closure $operation)
    {
        try {
            return $operation($this->ldap);
        } catch (Throwable $e) {
            throw LdapRecordException::withDetailedError(
                $e, $this->ldap->getDetailedError()
            );
        }
    }

    /**
     * Get a new auth guard instance.
     *
     * @return Auth\Guard
     */
    public function auth()
    {
        $guard = new Guard($this->ldap, $this->configuration);

        $guard->setDispatcher(Container::getEventDispatcher());

        return $guard;
    }

    /**
     * Get a new query builder for the connection.
     *
     * @return Query\Builder
     */
    public function query()
    {
        return (new Builder($this))
            ->setCache($this->cache)
            ->in($this->configuration->get('base_dn'));
    }

    /**
     * Determine if the LDAP connection is bound.
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->ldap->isBound();
    }

    /**
     * Attempt to retry an LDAP operation if due to a lost connection.
     *
     * @param LdapRecordException $e
     * @param Closure             $operation
     *
     * @throws LdapRecordException
     *
     * @return mixed
     */
    protected function tryAgainIfCausedByLostConnection(LdapRecordException $e, Closure $operation)
    {
        // If the operation failed due to a lost or failed connection,
        // we'll attempt reconnecting and running the operation again
        // underneath the same host, and then move onto the next.
        if ($this->causedByLostConnection($e->getMessage())) {
            return $this->retry($operation);
        }

        throw $e;
    }

    /**
     * Retry the operation on the current host.
     *
     * @param Closure $operation
     *
     * @throws LdapRecordException
     *
     * @return mixed
     */
    protected function retry(Closure $operation)
    {
        try {
            $this->reconnect();

            return $this->runOperationCallback($operation);
        } catch (LdapRecordException $e) {
            $this->attempted[$this->host] = Carbon::now();

            return $this->retryOnNextHost($e, $operation);
        }
    }

    /**
     * Attempt the operation again on the next host.
     *
     * @param LdapRecordException $e
     * @param Closure             $operation
     *
     * @throws LdapRecordException
     *
     * @return mixed
     */
    protected function retryOnNextHost(LdapRecordException $e, Closure $operation)
    {
        if (($key = array_search($this->host, $this->hosts)) !== false) {
            unset($this->hosts[$key]);
        }

        if (! $next = reset($this->hosts)) {
            throw $e;
        }

        $this->host = $next;

        return $this->tryAgainIfCausedByLostConnection($e, $operation);
    }
}

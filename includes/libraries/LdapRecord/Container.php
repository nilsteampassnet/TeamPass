<?php

namespace LdapRecord;

use Psr\Log\LoggerInterface;
use LdapRecord\Events\Logger;
use LdapRecord\Events\Dispatcher;
use LdapRecord\Events\DispatcherInterface;

class Container
{
    /**
     * The current container instance.
     *
     * @var Container
     */
    protected static $instance;

    /**
     * The logger instance.
     *
     * @var LoggerInterface|null
     */
    protected static $logger;

    /**
     * The event dispatcher instance.
     *
     * @var DispatcherInterface
     */
    protected static $dispatcher;

    /**
     * The added connections in the container instance.
     *
     * @var Connection[]
     */
    protected $connections = [];

    /**
     * The name of the default connection.
     *
     * @var string
     */
    protected $default = 'default';

    /**
     * The events to register listeners for during initialization.
     *
     * @var array
     */
    protected $listen = [
        'LdapRecord\Auth\Events\*',
        'LdapRecord\Query\Events\*',
        'LdapRecord\Models\Events\*',
    ];

    /**
     * Constructor.
     */
    public function __construct()
    {
        if (static::$logger) {
            $this->initEventLogger();
        }
    }

    /**
     * Initializes the event logger.
     *
     * @return void
     */
    public function initEventLogger()
    {
        $dispatcher = static::getEventDispatcher();

        $logger = $this->newEventLogger();

        foreach ($this->listen as $event) {
            $dispatcher->listen($event, function ($eventName, $events) use ($logger) {
                foreach ($events as $event) {
                    $logger->log($event);
                }
            });
        }
    }

    /**
     * Returns a new event logger instance.
     *
     * @return Logger
     */
    protected function newEventLogger()
    {
        return new Logger(static::$logger);
    }

    /**
     * Get or set the current instance of the container.
     *
     * @return Container
     */
    public static function getInstance()
    {
        return static::$instance ?? static::getNewInstance();
    }

    /**
     * Set and get a new instance of the container.
     *
     * @return Container
     */
    public static function getNewInstance()
    {
        return static::$instance = new static();
    }

    /**
     * Add a connection to the container.
     *
     * @param Connection  $connection
     * @param string|null $name
     *
     * @return static
     */
    public static function addConnection(Connection $connection, $name = null)
    {
        return static::getInstance()->add($connection, $name);
    }

    /**
     * Remove a connection from the container.
     *
     * @param string $name
     *
     * @return void
     */
    public static function removeConnection($name)
    {
        static::getInstance()->remove($name);
    }

    /**
     * Get a connection by name or return the default.
     *
     * @param string|null $name
     *
     * @return Connection
     *
     * @throws ContainerException If the given connection does not exist.
     */
    public static function getConnection($name = null)
    {
        return static::getInstance()->get($name);
    }

    /**
     * Set the default connection name.
     *
     * @param string|null $name
     *
     * @return static
     */
    public static function setDefaultConnection($name = null)
    {
        return static::getInstance()->setDefault($name);
    }

    /**
     * Get the default connection.
     *
     * @return Connection
     */
    public static function getDefaultConnection()
    {
        return static::getInstance()->getDefault();
    }

    /**
     * Flush all of the added connections.
     *
     * @return $this
     */
    public static function flushConnections()
    {
        return static::getInstance()->flush();
    }

    /**
     * Add a new connection into the container.
     *
     * @param Connection  $connection
     * @param string|null $name
     *
     * @return $this
     */
    public function add(Connection $connection, $name = null)
    {
        $this->connections[$name ?? $this->default] = $connection;

        return $this;
    }

    /**
     * Remove a connection from the container.
     *
     * @param $name
     *
     * @return $this
     */
    public function remove($name)
    {
        if ($this->exists($name)) {
            unset($this->connections[$name]);
        }

        return $this;
    }

    /**
     * Return all of the connections from the container.
     *
     * @return Connection[]
     */
    public function all()
    {
        return $this->connections;
    }

    /**
     * Remove all of the connections.
     *
     * @return $this
     */
    public function flush()
    {
        $this->connections = [];

        return $this;
    }

    /**
     * Get a connection by name or return the default.
     *
     * @param string|null $name
     *
     * @return Connection
     *
     * @throws ContainerException If the given connection does not exist.
     */
    public function get($name = null)
    {
        if ($this->exists($name = $name ?? $this->default)) {
            return $this->connections[$name];
        }

        throw new ContainerException("The LDAP connection [$name] does not exist.");
    }

    /**
     * Return the default connection.
     *
     * @return Connection
     */
    public function getDefault()
    {
        return $this->get($this->default);
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnectionName()
    {
        return $this->default;
    }

    /**
     * Checks if the connection exists.
     *
     * @param $name
     *
     * @return bool
     */
    public function exists($name)
    {
        return array_key_exists($name, $this->connections);
    }

    /**
     * Set the default connection name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setDefault($name = null)
    {
        $this->default = $name;

        return $this;
    }

    /**
     * Get the event dispatcher instance.
     *
     * @return DispatcherInterface
     */
    public static function getEventDispatcher()
    {
        // If no event dispatcher has been set, well instantiate and
        // set one here. This will be our singleton instance.
        if (! isset(static::$dispatcher)) {
            static::setEventDispatcher(new Dispatcher());
        }

        return static::$dispatcher;
    }

    /**
     * Set the event dispatcher instance.
     *
     * @param DispatcherInterface $dispatcher
     *
     * @return void
     */
    public static function setEventDispatcher(DispatcherInterface $dispatcher)
    {
        static::$dispatcher = $dispatcher;
    }

    /**
     * Unset the event dispatcher instance.
     *
     * @return void
     */
    public static function unsetEventDispatcher()
    {
        static::$dispatcher = null;
    }

    /**
     * Get the logger instance.
     *
     * @return LoggerInterface|null
     */
    public static function getLogger()
    {
        return static::$logger;
    }

    /**
     * Initialize the container without event logging.
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public static function setLogger(LoggerInterface $logger)
    {
        static::$logger = $logger;
    }

    /**
     * Unset the logger instance.
     *
     * @return void
     */
    public static function unsetLogger()
    {
        static::$logger = null;
    }
}

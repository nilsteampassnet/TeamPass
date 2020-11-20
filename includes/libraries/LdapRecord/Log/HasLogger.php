<?php

namespace LdapRecord\Log;

use Psr\Log\LoggerInterface;

trait HasLogger
{
    /**
     * The logger instance.
     *
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * Get the logger instance.
     *
     * @return LoggerInterface|null
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Set the logger instance.
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Unset the logger instance.
     *
     * @return void
     */
    public function unsetLogger()
    {
        $this->logger = null;
    }
}

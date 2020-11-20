<?php

namespace LdapRecord\Testing;

use Closure;
use LdapRecord\Ldap;
use LdapRecord\DetailedError;

class LdapFake extends Ldap
{
    /**
     * The distinguished name of the user that should pass authentication.
     *
     * @var string|null
     */
    protected $dn;

    protected $errNo = 1;

    protected $lastError = '';

    protected $diagnosticMessage = '';

    /**
     * Set the distinguished name of a user that will pass binding.
     *
     * @param string $dn
     *
     * @return $this
     */
    public function shouldAuthenticateWith($dn)
    {
        $this->dn = $dn;

        return $this;
    }

    /**
     * Set the error number of a failed bind attempt.
     *
     * @param int $number
     *
     * @return $this
     */
    public function shouldReturnErrorNumber($number = 1)
    {
        $this->errNo = $number;

        return $this;
    }

    /**
     * Set the last error of a failed bind attempt.
     *
     * @param string $message
     *
     * @return $this
     */
    public function shouldReturnError($message = '')
    {
        $this->lastError = $message;

        return $this;
    }

    /**
     * Set the diagnostic message of a failed bind attempt.
     *
     * @param string $message
     *
     * @return $this
     */
    public function shouldReturnDiagnosticMessage($message = '')
    {
        $this->diagnosticMessage = $message;

        return $this;
    }

    /**
     * Fake a bind attempt.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    public function bind($username, $password)
    {
        return $this->bound = $username == $this->dn;
    }

    /**
     * Return a fake error number.
     *
     * @return int
     */
    public function errNo()
    {
        return $this->errNo;
    }

    /**
     * Return a fake error.
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Return a fake detailed error.
     *
     * @return DetailedError
     */
    public function getDetailedError()
    {
        return new DetailedError($this->errNo, $this->lastError, $this->diagnosticMessage);
    }

    protected function executeFailableOperation(Closure $operation)
    {
        // Do nothing.
    }
}

<?php

namespace LdapRecord;

use Closure;
use ErrorException;

class Ldap
{
    use DetectsErrors;

    /**
     * The SSL LDAP protocol string.
     *
     * @var string
     */
    const PROTOCOL_SSL = 'ldaps://';

    /**
     * The standard LDAP protocol string.
     *
     * @var string
     */
    const PROTOCOL = 'ldap://';

    /**
     * The LDAP SSL port number.
     *
     * @var string
     */
    const PORT_SSL = 636;

    /**
     * The standard LDAP port number.
     *
     * @var string
     */
    const PORT = 389;

    /**
     * Various useful server control OID's.
     *
     * @see https://ldap.com/ldap-oid-reference-guide/
     * @see http://msdn.microsoft.com/en-us/library/cc223359.aspx
     */
    const OID_SERVER_START_TLS = '1.3.6.1.4.1.1466.20037';
    const OID_SERVER_PAGED_RESULTS = '1.2.840.113556.1.4.319';
    const OID_SERVER_SHOW_DELETED = '1.2.840.113556.1.4.417';
    const OID_SERVER_SORT = '1.2.840.113556.1.4.473';
    const OID_SERVER_CROSSDOM_MOVE_TARGET = '1.2.840.113556.1.4.521';
    const OID_SERVER_NOTIFICATION = '1.2.840.113556.1.4.528';
    const OID_SERVER_EXTENDED_DN = '1.2.840.113556.1.4.529';
    const OID_SERVER_LAZY_COMMIT = '1.2.840.113556.1.4.619';
    const OID_SERVER_SD_FLAGS = '1.2.840.113556.1.4.801';
    const OID_SERVER_TREE_DELETE = '1.2.840.113556.1.4.805';
    const OID_SERVER_DIRSYNC = '1.2.840.113556.1.4.841';
    const OID_SERVER_VERIFY_NAME = '1.2.840.113556.1.4.1338';
    const OID_SERVER_DOMAIN_SCOPE = '1.2.840.113556.1.4.1339';
    const OID_SERVER_SEARCH_OPTIONS = '1.2.840.113556.1.4.1340';
    const OID_SERVER_PERMISSIVE_MODIFY = '1.2.840.113556.1.4.1413';
    const OID_SERVER_ASQ = '1.2.840.113556.1.4.1504';
    const OID_SERVER_FAST_BIND = '1.2.840.113556.1.4.1781';
    const OID_SERVER_CONTROL_VLVREQUEST = '2.16.840.1.113730.3.4.9';

    /**
     * The LDAP host that is currently connected.
     *
     * @var string|null
     */
    protected $host;

    /**
     * The active LDAP connection.
     *
     * @var resource|null
     */
    protected $connection;

    /**
     * The bound status of the connection.
     *
     * @var bool
     */
    protected $bound = false;

    /**
     * Whether the connection must be bound over SSL.
     *
     * @var bool
     */
    protected $useSSL = false;

    /**
     * Whether the connection must be bound over TLS.
     *
     * @var bool
     */
    protected $useTLS = false;

    /**
     * Returns true / false if the current connection instance is using SSL.
     *
     * @return bool
     */
    public function isUsingSSL()
    {
        return $this->useSSL;
    }

    /**
     * Returns true / false if the current connection instance is using TLS.
     *
     * @return bool
     */
    public function isUsingTLS()
    {
        return $this->useTLS;
    }

    /**
     * Returns true / false if the current connection is bound.
     *
     * @return bool
     */
    public function isBound()
    {
        return $this->bound;
    }

    /**
     * Returns true / false if the current connection is able to modify passwords.
     *
     * @return bool
     */
    public function canChangePasswords()
    {
        return $this->isUsingSSL() || $this->isUsingTLS();
    }

    /**
     * Sets the current connection to use SSL.
     *
     * @param bool $enabled
     *
     * @return Ldap
     */
    public function ssl($enabled = true)
    {
        $this->useSSL = $enabled;

        return $this;
    }

    /**
     * Sets the current connection to use TLS.
     *
     * @param bool $enabled
     *
     * @return Ldap
     */
    public function tls($enabled = true)
    {
        $this->useTLS = $enabled;

        return $this;
    }

    /**
     * Returns the full LDAP host URL.
     *
     * Ex: ldap://192.168.1.1:386
     *
     * @return string|null
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Get the underlying connection resource.
     *
     * @return resource|null
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Retrieve the entries from a search result.
     *
     * @link http://php.net/manual/en/function.ldap-get-entries.php
     *
     * @param resource $searchResult
     *
     * @return array
     */
    public function getEntries($searchResults)
    {
        return $this->executeFailableOperation(function () use ($searchResults) {
            return ldap_get_entries($this->connection, $searchResults);
        });
    }

    /**
     * Retrieves the first entry from a search result.
     *
     * @link http://php.net/manual/en/function.ldap-first-entry.php
     *
     * @param resource $searchResult
     *
     * @return resource
     */
    public function getFirstEntry($searchResults)
    {
        return $this->executeFailableOperation(function () use ($searchResults) {
            return ldap_first_entry($this->connection, $searchResults);
        });
    }

    /**
     * Retrieves the next entry from a search result.
     *
     * @link http://php.net/manual/en/function.ldap-next-entry.php
     *
     * @param resource $entry
     *
     * @return resource
     */
    public function getNextEntry($entry)
    {
        return $this->executeFailableOperation(function () use ($entry) {
            return ldap_next_entry($this->connection, $entry);
        });
    }

    /**
     * Retrieves the ldap entry's attributes.
     *
     * @link http://php.net/manual/en/function.ldap-get-attributes.php
     *
     * @param resource $entry
     *
     * @return array|false
     */
    public function getAttributes($entry)
    {
        return $this->executeFailableOperation(function () use ($entry) {
            return ldap_get_attributes($this->connection, $entry);
        });
    }

    /**
     * Returns the number of entries from a search result.
     *
     * @link http://php.net/manual/en/function.ldap-count-entries.php
     *
     * @param resource $searchResult
     *
     * @return int
     */
    public function countEntries($searchResults)
    {
        return $this->executeFailableOperation(function () use ($searchResults) {
            return ldap_count_entries($this->connection, $searchResults);
        });
    }

    /**
     * Compare value of attribute found in entry specified with DN.
     *
     * @link http://php.net/manual/en/function.ldap-compare.php
     *
     * @param string $dn
     * @param string $attribute
     * @param string $value
     *
     * @return mixed
     */
    public function compare($dn, $attribute, $value)
    {
        return $this->executeFailableOperation(function () use ($dn, $attribute, $value) {
            return ldap_compare($this->connection, $dn, $attribute, $value);
        });
    }

    /**
     * Retrieve the last error on the current connection.
     *
     * @link http://php.net/manual/en/function.ldap-error.php
     *
     * @return string
     */
    public function getLastError()
    {
        return ldap_error($this->connection);
    }

    /**
     * Return detailed information about an error.
     *
     * Returns false when there was a successful last request.
     *
     * Returns DetailedError when there was an error.
     *
     * @return DetailedError|null
     */
    public function getDetailedError()
    {
        // If the returned error number is zero, the last LDAP operation
        // succeeded. In such case we won't return a detailed error.
        if ($number = $this->errNo()) {
            $this->getOption(LDAP_OPT_DIAGNOSTIC_MESSAGE, $message);

            return new DetailedError($number, $this->err2Str($number), $message);
        }
    }

    /**
     * Get all binary values from the specified result entry.
     *
     * @link http://php.net/manual/en/function.ldap-get-values-len.php
     *
     * @param $entry
     * @param $attribute
     *
     * @return array
     */
    public function getValuesLen($entry, $attribute)
    {
        return $this->executeFailableOperation(function () use ($entry, $attribute) {
            return ldap_get_values_len($this->connection, $entry, $attribute);
        });
    }

    /**
     * Sets an option on the current connection.
     *
     * @link http://php.net/manual/en/function.ldap-set-option.php
     *
     * @param int   $option
     * @param mixed $value
     *
     * @return bool
     */
    public function setOption($option, $value)
    {
        return ldap_set_option($this->connection, $option, $value);
    }

    /**
     * Sets options on the current connection.
     *
     * @param array $options
     *
     * @return void
     */
    public function setOptions(array $options = [])
    {
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }
    }

    /**
     * Get the value for the LDAP option.
     *
     * @link https://www.php.net/manual/en/function.ldap-get-option.php
     *
     * @param int   $option
     * @param mixed $value
     *
     * @return mixed
     */
    public function getOption($option, &$value = null)
    {
        ldap_get_option($this->connection, $option, $value);

        return $value;
    }

    /**
     * Set a callback function to do re-binds on referral chasing.
     *
     * @link http://php.net/manual/en/function.ldap-set-rebind-proc.php
     *
     * @param callable $callback
     *
     * @return bool
     */
    public function setRebindCallback(callable $callback)
    {
        return ldap_set_rebind_proc($this->connection, $callback);
    }

    /**
     * Starts a connection using TLS.
     *
     * @link http://php.net/manual/en/function.ldap-start-tls.php
     *
     * @throws \ErrorException If starting TLS fails.
     *
     * @return bool
     */
    public function startTLS()
    {
        return $this->executeFailableOperation(function () {
            return ldap_start_tls($this->connection);
        });
    }

    /**
     * Connects to the specified hostname using the specified port.
     *
     * @link http://php.net/manual/en/function.ldap-start-tls.php
     *
     * @param string|array $hosts
     * @param int          $port
     *
     * @return resource|false
     */
    public function connect($hosts = [], $port = 389)
    {
        $this->host = $this->getConnectionString($hosts, $this->getProtocol(), $port);

        $this->bound = false;

        return $this->connection = $this->executeFailableOperation(function () {
            return ldap_connect($this->host);
        });
    }

    /**
     * Closes the current connection.
     *
     * Returns false if no connection is present.
     *
     * @link http://php.net/manual/en/function.ldap-close.php
     *
     * @return bool
     */
    public function close()
    {
        $result = is_resource($this->connection) ? @ldap_close($this->connection) : false;

        $this->connection = null;
        $this->bound = false;
        $this->host = null;

        return $result;
    }

    /**
     * Performs a search on the current connection.
     *
     * @link http://php.net/manual/en/function.ldap-search.php
     *
     * @param string $dn
     * @param string $filter
     * @param array  $fields
     * @param bool   $onlyAttributes
     * @param int    $size
     * @param int    $time
     * @param int    $deref
     * @param array  $serverControls
     *
     * @return resource
     */
    public function search($dn, $filter, array $fields, $onlyAttributes = false, $size = 0, $time = 0, $deref = null, $serverControls = [])
    {
        return $this->executeFailableOperation(function () use (
            $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref, $serverControls
        ) {
            return $this->supportsServerControlsInMethods() && !empty($serverControls)
                ? ldap_search($this->connection, $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref, $serverControls)
                : ldap_search($this->connection, $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref);
        });
    }

    /**
     * Performs a single level search on the current connection.
     *
     * @link http://php.net/manual/en/function.ldap-list.php
     *
     * @param string $dn
     * @param string $filter
     * @param array  $attributes
     * @param bool   $onlyAttributes
     * @param int    $size
     * @param int    $time
     * @param int    $deref
     * @param array  $serverControls
     *
     * @return resource
     */
    public function listing($dn, $filter, array $fields, $onlyAttributes = false, $size = 0, $time = 0, $deref = null, $serverControls = [])
    {
        return $this->executeFailableOperation(function () use (
            $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref, $serverControls
        ) {
            return $this->supportsServerControlsInMethods() && !empty($serverControls)
                ? ldap_list($this->connection, $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref, $serverControls)
                : ldap_list($this->connection, $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref);
        });
    }

    /**
     * Reads an entry on the current connection.
     *
     * @link http://php.net/manual/en/function.ldap-read.php
     *
     * @param string $dn
     * @param string $filter
     * @param array  $fields
     * @param bool   $onlyAttributes
     * @param int    $size
     * @param int    $time
     * @param int    $deref
     * @param array  $serverControls
     *
     * @return resource
     */
    public function read($dn, $filter, array $fields, $onlyAttributes = false, $size = 0, $time = 0, $deref = null, $serverControls = [])
    {
        return $this->executeFailableOperation(function () use (
            $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref, $serverControls
        ) {
            return $this->supportsServerControlsInMethods() && !empty($serverControls)
                ? ldap_read($this->connection, $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref, $serverControls)
                : ldap_read($this->connection, $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref);
        });
    }

    /**
     * Extract information from an LDAP result.
     *
     * @link https://www.php.net/manual/en/function.ldap-parse-result.php
     *
     * @param resource $result
     * @param int      $errorCode
     * @param string   $dn
     * @param string   $errorMessage
     * @param array    $referrals
     * @param array    $serverControls
     *
     * @return bool
     */
    public function parseResult($result, &$errorCode, &$dn, &$errorMessage, &$referrals, &$serverControls = [])
    {
        return $this->executeFailableOperation(function () use (
            $result, &$errorCode, &$dn, &$errorMessage, &$referrals, &$serverControls
        ) {
            return $this->supportsServerControlsInMethods() && !empty($serverControls)
                ? ldap_parse_result($this->connection, $result, $errorCode, $dn, $errorMessage, $referrals, $serverControls)
                : ldap_parse_result($this->connection, $result, $errorCode, $dn, $errorMessage, $referrals);
        });
    }

    /**
     * Binds to the current connection using the specified username and password.
     * If sasl is true, the current connection is bound using SASL.
     *
     * @link http://php.net/manual/en/function.ldap-bind.php
     *
     * @param string $username
     * @param string $password
     *
     * @throws ConnectionException If starting TLS fails.
     *
     * @return bool
     */
    public function bind($username, $password)
    {
        return $this->bound = $this->executeFailableOperation(function () use ($username, $password) {
            return ldap_bind($this->connection, $username, html_entity_decode($password));
        });
    }

    /**
     * Adds an entry to the current connection.
     *
     * @link http://php.net/manual/en/function.ldap-add.php
     *
     * @param string $dn
     * @param array  $entry
     *
     * @return bool
     */
    public function add($dn, array $entry)
    {
        return $this->executeFailableOperation(function () use ($dn, $entry) {
            return ldap_add($this->connection, $dn, $entry);
        });
    }

    /**
     * Deletes an entry on the current connection.
     *
     * @link http://php.net/manual/en/function.ldap-delete.php
     *
     * @param string $dn
     *
     * @return bool
     */
    public function delete($dn)
    {
        return $this->executeFailableOperation(function () use ($dn) {
            return ldap_delete($this->connection, $dn);
        });
    }

    /**
     * Modify the name of an entry on the current connection.
     *
     * @link http://php.net/manual/en/function.ldap-rename.php
     *
     * @param string $dn
     * @param string $newRdn
     * @param string $newParent
     * @param bool   $deleteOldRdn
     *
     * @return bool
     */
    public function rename($dn, $newRdn, $newParent, $deleteOldRdn = false)
    {
        return $this->executeFailableOperation(function () use (
            $dn, $newRdn, $newParent, $deleteOldRdn
        ) {
            return ldap_rename($this->connection, $dn, $newRdn, $newParent, $deleteOldRdn);
        });
    }

    /**
     * Modifies an existing entry on the current connection.
     *
     * @link http://php.net/manual/en/function.ldap-modify.php
     *
     * @param string $dn
     * @param array  $entry
     *
     * @return bool
     */
    public function modify($dn, array $entry)
    {
        return $this->executeFailableOperation(function () use ($dn, $entry) {
            return ldap_modify($this->connection, $dn, $entry);
        });
    }

    /**
     * Batch modifies an existing entry on the current connection.
     *
     * @link http://php.net/manual/en/function.ldap-modify-batch.php
     *
     * @param string $dn
     * @param array  $values
     *
     * @return bool
     */
    public function modifyBatch($dn, array $values)
    {
        return $this->executeFailableOperation(function () use ($dn, $values) {
            return ldap_modify_batch($this->connection, $dn, $values);
        });
    }

    /**
     * Add attribute values to current attributes.
     *
     * @link http://php.net/manual/en/function.ldap-mod-add.php
     *
     * @param string $dn
     * @param array  $entry
     *
     * @return bool
     */
    public function modAdd($dn, array $entry)
    {
        return $this->executeFailableOperation(function () use ($dn, $entry) {
            return ldap_mod_add($this->connection, $dn, $entry);
        });
    }

    /**
     * Replaces attribute values with new ones.
     *
     * @link http://php.net/manual/en/function.ldap-mod-replace.php
     *
     * @param string $dn
     * @param array  $entry
     *
     * @return bool
     */
    public function modReplace($dn, array $entry)
    {
        return $this->executeFailableOperation(function () use ($dn, $entry) {
            return ldap_mod_replace($this->connection, $dn, $entry);
        });
    }

    /**
     * Delete attribute values from current attributes.
     *
     * @link http://php.net/manual/en/function.ldap-mod-del.php
     *
     * @param string $dn
     * @param array  $entry
     *
     * @return bool
     */
    public function modDelete($dn, array $entry)
    {
        return $this->executeFailableOperation(function () use ($dn, $entry) {
            return ldap_mod_del($this->connection, $dn, $entry);
        });
    }

    /**
     * Send LDAP pagination control.
     *
     * @link http://php.net/manual/en/function.ldap-control-paged-result.php
     *
     * @param int    $pageSize
     * @param bool   $isCritical
     * @param string $cookie
     *
     * @return bool
     */
    public function controlPagedResult($pageSize = 1000, $isCritical = false, $cookie = '')
    {
        return $this->executeFailableOperation(function () use ($pageSize, $isCritical, $cookie) {
            return ldap_control_paged_result($this->connection, $pageSize, $isCritical, $cookie);
        });
    }

    /**
     * Retrieve the LDAP pagination cookie.
     *
     * @link http://php.net/manual/en/function.ldap-control-paged-result-response.php
     *
     * @param resource $result
     * @param string   $cookie
     *
     * @return bool
     */
    public function controlPagedResultResponse($result, &$cookie)
    {
        return $this->executeFailableOperation(function () use ($result, &$cookie) {
            return ldap_control_paged_result_response($this->connection, $result, $cookie);
        });
    }

    /**
     * Frees up the memory allocated internally to store the result.
     *
     * @link https://www.php.net/manual/en/function.ldap-free-result.php
     *
     * @param resource $result
     *
     * @return bool
     */
    public function freeResult($result)
    {
        return ldap_free_result($result);
    }

    /**
     * Returns the error number of the last command
     * executed on the current connection.
     *
     * @link http://php.net/manual/en/function.ldap-errno.php
     *
     * @return int
     */
    public function errNo()
    {
        return ldap_errno($this->connection);
    }

    /**
     * Returns the extended error string of the last command.
     *
     * @return string
     */
    public function getExtendedError()
    {
        return $this->getDiagnosticMessage();
    }

    /**
     * Returns the extended error hex code of the last command.
     *
     * @return string|null
     */
    public function getExtendedErrorHex()
    {
        if (preg_match("/(?<=data\s).*?(?=\,)/", $this->getExtendedError(), $code)) {
            return $code[0];
        }
    }

    /**
     * Returns the extended error code of the last command.
     *
     * @return string
     */
    public function getExtendedErrorCode()
    {
        return $this->extractDiagnosticCode($this->getExtendedError());
    }

    /**
     * Returns the error string of the specified
     * error number.
     *
     * @link http://php.net/manual/en/function.ldap-err2str.php
     *
     * @param int $number
     *
     * @return string
     */
    public function err2Str($number)
    {
        return ldap_err2str($number);
    }

    /**
     * Return the diagnostic Message.
     *
     * @return string
     */
    public function getDiagnosticMessage()
    {
        $this->getOption(LDAP_OPT_ERROR_STRING, $message);

        return $message;
    }

    /**
     * Extract the diagnostic code from the message.
     *
     * @param string $message
     *
     * @return string|bool
     */
    public function extractDiagnosticCode($message)
    {
        preg_match('/^([\da-fA-F]+):/', $message, $matches);

        return isset($matches[1]) ? $matches[1] : false;
    }

    /**
     * Returns the LDAP protocol to utilize for the current connection.
     *
     * @return string
     */
    public function getProtocol()
    {
        return $this->isUsingSSL() ? $this::PROTOCOL_SSL : $this::PROTOCOL;
    }

    /**
     * Convert warnings to exceptions for the given operation.
     *
     * @param Closure $operation
     *
     * @throws ErrorException
     *
     * @return mixed
     */
    protected function executeFailableOperation(Closure $operation)
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            if (! $this->shouldBypassError($message)) {
                throw new ErrorException($message, $severity, $severity, $file, $line);
            }
        });

        try {
            return $operation();
        } catch (ErrorException $e) {
            throw $e;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Determine if the error should be bypassed.
     *
     * @param string $error
     *
     * @return bool
     */
    protected function shouldBypassError($error)
    {
        return $this->causedByPaginationSupport($error) || $this->causedBySizeLimit($error) || $this->causedByNoSuchObject($error);
    }

    /**
     * Determine if the current PHP version supports server controls.
     *
     * @return bool
     */
    public function supportsServerControlsInMethods()
    {
        return version_compare(PHP_VERSION, '7.3.0') >= 0;
    }

    /**
     * Generates an LDAP connection string for each host given.
     *
     * @param string|array $hosts
     * @param string       $protocol
     * @param string       $port
     *
     * @return string
     */
    protected function getConnectionString($hosts, $protocol, $port)
    {
        // If we are using SSL and using the default port, we
        // will override it to use the default SSL port.
        if ($this->isUsingSSL() && $port == 389) {
            $port = static::PORT_SSL;
        }

        $hosts = array_map(function ($host) use ($protocol, $port) {
            return "{$protocol}{$host}:{$port}";
        }, (array) $hosts);

        return implode(' ', $hosts);
    }
}

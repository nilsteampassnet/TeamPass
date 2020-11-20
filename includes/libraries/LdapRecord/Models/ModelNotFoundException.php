<?php

namespace LdapRecord\Models;

use LdapRecord\LdapRecordException;

class ModelNotFoundException extends LdapRecordException
{
    /**
     * The query filter that was used.
     *
     * @var string
     */
    protected $query;

    /**
     * The base DN of the query that was used.
     *
     * @var string
     */
    protected $baseDn;

    /**
     * Create a new exception for the executed filter.
     *
     * @param string $filter
     * @param null   $baseDn
     *
     * @return ModelNotFoundException
     */
    public static function forQuery($filter, $baseDn = null)
    {
        return (new static())->setQuery($filter, $baseDn);
    }

    /**
     * Sets the query that was used.
     *
     * @param string      $query
     * @param string|null $baseDn
     *
     * @return ModelNotFoundException
     */
    public function setQuery($query, $baseDn = null)
    {
        $this->query = $query;
        $this->baseDn = $baseDn;
        $this->message = "No LDAP query results for filter: [$query] in: [$baseDn]";

        return $this;
    }
}

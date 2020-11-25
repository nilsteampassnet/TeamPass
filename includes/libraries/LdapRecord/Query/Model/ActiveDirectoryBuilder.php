<?php

namespace LdapRecord\Query\Model;

use LdapRecord\Models\ModelNotFoundException;
use LdapRecord\Models\Attributes\AccountControl;

class ActiveDirectoryBuilder extends Builder
{
    /**
     * Finds a record by its Object SID.
     *
     * @param string       $sid
     * @param array|string $columns
     *
     * @return \LdapRecord\Models\ActiveDirectory\Entry|static|null
     */
    public function findBySid($sid, $columns = [])
    {
        try {
            return $this->findBySidOrFail($sid, $columns);
        } catch (ModelNotFoundException $e) {
            return;
        }
    }

    /**
     * Finds a record by its Object SID.
     *
     * Fails upon no records returned.
     *
     * @param string       $sid
     * @param array|string $columns
     *
     * @return \LdapRecord\Models\ActiveDirectory\Entry|static
     *
     * @throws ModelNotFoundException
     */
    public function findBySidOrFail($sid, $columns = [])
    {
        return $this->findByOrFail('objectsid', $sid, $columns);
    }

    /**
     * Adds a enabled filter to the current query.
     *
     * @return $this
     */
    public function whereEnabled()
    {
        return $this->notFilter(function ($query) {
            return $query->whereDisabled();
        });
    }

    /**
     * Adds a disabled filter to the current query.
     *
     * @return $this
     */
    public function whereDisabled()
    {
        return $this->rawFilter(
            (new AccountControl())->accountIsDisabled()->filter()
        );
    }

    /**
     * Adds a 'where member' filter to the current query.
     *
     * @param string $dn
     * @param bool   $nested
     *
     * @return $this
     */
    public function whereMember($dn, $nested = false)
    {
        return $this->whereEquals(
            $nested ? 'member:1.2.840.113556.1.4.1941:' : 'member',
            $dn
        );
    }

    /**
     * Adds an 'or where member' filter to the current query.
     *
     * @param string $dn
     * @param bool   $nested
     *
     * @return $this
     */
    public function orWhereMember($dn, $nested = false)
    {
        return $this->orWhereEquals(
            $nested ? 'member:1.2.840.113556.1.4.1941:' : 'member',
            $dn
        );
    }

    /**
     * Adds a 'where member of' filter to the current query.
     *
     * @param string $dn
     * @param bool   $nested
     *
     * @return $this
     */
    public function whereMemberOf($dn, $nested = false)
    {
        return $this->whereEquals(
            $nested ? 'memberof:1.2.840.113556.1.4.1941:' : 'memberof',
            $dn
        );
    }

    /**
     * Adds an 'or where member of' filter to the current query.
     *
     * @param string $dn
     * @param bool   $nested
     *
     * @return $this
     */
    public function orWhereMemberOf($dn, $nested = false)
    {
        return $this->orWhereEquals(
            $nested ? 'memberof:1.2.840.113556.1.4.1941:' : 'memberof',
            $dn
        );
    }

    /**
     * Adds a 'where manager' filter to the current query.
     *
     * @param string $dn
     * @param bool   $nested
     *
     * @return $this
     */
    public function whereManager($dn, $nested = false)
    {
        return $this->whereEquals(
            $nested ? 'manager:1.2.840.113556.1.4.1941:' : 'manager',
            $dn
        );
    }

    /**
     * Adds an 'or where manager' filter to the current query.
     *
     * @param string $dn
     * @param bool   $nested
     *
     * @return $this
     */
    public function orWhereManager($dn, $nested = false)
    {
        return $this->orWhereEquals(
            $nested ? 'manager:1.2.840.113556.1.4.1941:' : 'manager',
            $dn
        );
    }
}

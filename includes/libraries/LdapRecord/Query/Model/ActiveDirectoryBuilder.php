<?php

namespace LdapRecord\Query\Model;

use LdapRecord\Models\ModelNotFoundException;

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
     * @throws ModelNotFoundException
     *
     * @return \LdapRecord\Models\ActiveDirectory\Entry|static
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
        return $this->rawFilter('(!(UserAccountControl:1.2.840.113556.1.4.803:=2))');
    }

    /**
     * Adds a disabled filter to the current query.
     *
     * @return $this
     */
    public function whereDisabled()
    {
        return $this->rawFilter('(UserAccountControl:1.2.840.113556.1.4.803:=2)');
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

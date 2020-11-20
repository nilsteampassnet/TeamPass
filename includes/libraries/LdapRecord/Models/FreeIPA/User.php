<?php

namespace LdapRecord\Models\FreeIPA;

class User extends Entry
{
    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'inetorgperson',
        'inetuser',
        'posixaccount',
        'krbprincipalaux',
        'krbticketpolicyaux',
        'ipaobject',
        'ipasshuser',
        'ipaSshGroupOfPubKeys',
        'mepOriginEntry',
        'ipauserauthtypeclass',
    ];

    /**
     * Retrieve groups that the current user is apart of.
     *
     * @return \LdapRecord\Models\Relations\HasMany
     */
    public function groups()
    {
        return $this->hasMany(Group::class, 'member');
    }
}

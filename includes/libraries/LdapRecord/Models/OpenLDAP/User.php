<?php

namespace LdapRecord\Models\OpenLDAP;

use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Models\Concerns\CanAuthenticate;

class User extends Entry implements Authenticatable
{
    use CanAuthenticate;

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
    ];

    /**
     * The groups relationship.
     *
     * Retrieves groups that the user is apart of.
     *
     * @return \LdapRecord\Models\Relations\HasMany
     */
    public function groups()
    {
        return $this->hasMany(Group::class, 'memberuid', 'uid');
    }
}

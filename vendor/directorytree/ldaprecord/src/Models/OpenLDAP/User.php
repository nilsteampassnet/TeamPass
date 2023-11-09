<?php

namespace LdapRecord\Models\OpenLDAP;

use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Models\Concerns\CanAuthenticate;
use LdapRecord\Models\Concerns\HasPassword;
use LdapRecord\Models\Relations\HasMany;

class User extends Entry implements Authenticatable
{
    use HasPassword;
    use CanAuthenticate;

    /**
     * The password's attribute name.
     */
    protected string $passwordAttribute = 'userpassword';

    /**
     * The password's hash method.
     */
    protected string $passwordHashMethod = 'ssha';

    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'inetorgperson',
    ];

    /**
     * The groups relationship.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'uniquemember');
    }
}

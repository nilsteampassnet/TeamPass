<?php

namespace TheNetworg\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class AzureResourceOwner implements ResourceOwnerInterface
{
    /**
     * Response payload
     *
     * @var array
     */
    protected $data;

    /**
     * Creates new azure resource owner.
     *
     * @param array $data
     */
    public function __construct($data = [])
    {
        $this->data = $data;
    }

    /**
     * Retrieves id of resource owner.
     *
     * @return string|null
     */
    public function getId()
    {
        return $this->claim('oid');
    }

    /**
     * Retrieves first name of resource owner.
     *
     * @return string|null
     */
    public function getFirstName()
    {
        return $this->claim('given_name');
    }

    /**
     * Retrieves last name of resource owner.
     *
     * @return string|null
     */
    public function getLastName()
    {
        return $this->claim('family_name');
    }

    /**
     * Retrieves user principal name of resource owner.
     *
     * @return string|null
     */
    public function getUpn()
    {
        return $this->claim('upn');
    }

    /**
     * Retrieves tenant id of resource owner.
     *
     * @return string|null
     */
    public function getTenantId()
    {
        return $this->claim('tid');
    }

    /**
     * Returns a field from the parsed JWT data.
     *
     * @param string $name
     *
     * @return mixed|null
     */
    public function claim($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    /**
     * Returns all the data obtained about the user.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }
}

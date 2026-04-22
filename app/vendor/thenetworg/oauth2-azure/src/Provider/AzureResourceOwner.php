<?php

namespace TheNetworg\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use TheNetworg\OAuth2\Client\Token\AccessToken;

class AzureResourceOwner implements ResourceOwnerInterface
{
    /**
     * Response payload
     *
     * @var array
     */
    protected $data;

    /**
     * @var AccessToken
     */
    protected $token;

    /**
     * Creates new azure resource owner.
     *
     * @param array $data
     * @param AccessToken $token
     */
    public function __construct(array $data, AccessToken $token)
    {
        $this->data = $data;
        $this->token = $token;
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
     * Retrieves preferred username of resource owner.
     *
     * @return string|null
     */
    public function getPreferredUsername()
    {
        return $this->claim('preferred_username');
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
     * Retrieves email of resource owner.
     *
     * @return string|null
     */
    public function getEmail()
    {
        return $this->claim('email');
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

    /**
     * @return AccessToken
     */
    public function getToken()
    {
        return $this->token;
    }
}

<?php

namespace TheNetworg\OAuth2\Client\Token;

use Firebase\JWT\JWT;
use RuntimeException;
use TheNetworg\OAuth2\Client\Provider\Azure;

class AccessToken extends \League\OAuth2\Client\Token\AccessToken
{
    protected $idToken;

    protected $idTokenClaims;

    /**
     * @param Azure $provider
     */
    public function __construct(array $options, $provider)
    {
        parent::__construct($options);
        if (!empty($options['id_token'])) {
            $this->idToken = $options['id_token'];

            unset($this->values['id_token']);

            $keys          = $provider->getJwtVerificationKeys();
            $idTokenClaims = null;
            try {
                $tks = explode('.', $this->idToken);
                // Check if the id_token contains signature
                if (3 == count($tks) && !empty($tks[2])) {
                    $idTokenClaims = (array)JWT::decode($this->idToken, $keys);
                } else {
                    // The id_token is unsigned (coming from v1.0 endpoint) - https://msdn.microsoft.com/en-us/library/azure/dn645542.aspx

                    // Since idToken is not signed, we just do OAuth2 flow without validating the id_token
                    // // Validate the access_token signature first by parsing it as JWT into claims
                    // $accessTokenClaims = (array)JWT::decode($options['access_token'], $keys, ['RS256']);
                    // Then parse the idToken claims only without validating the signature
                    $idTokenClaims = (array)JWT::jsonDecode(JWT::urlsafeB64Decode($tks[1]));
                }
            } catch (JWT_Exception $e) {
                throw new RuntimeException('Unable to parse the id_token!');
            }

            $provider->validateTokenClaims($idTokenClaims);

            $this->idTokenClaims = $idTokenClaims;
        }
    }

    public function getIdToken()
    {
        return $this->idToken;
    }

    public function getIdTokenClaims()
    {
        return $this->idTokenClaims;
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        $parameters = parent::jsonSerialize();

        if ($this->idToken) {
            $parameters['id_token'] = $this->idToken;
        }

        return $parameters;
    }
}

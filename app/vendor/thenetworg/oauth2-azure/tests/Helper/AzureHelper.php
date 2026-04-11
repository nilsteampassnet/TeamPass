<?php

namespace TheNetworg\OAuth2\Client\Tests\Helper;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use TheNetworg\OAuth2\Client\Provider\Azure;
use TheNetworg\OAuth2\Client\Tests\Fakers\KeysFaker;
use TheNetworg\OAuth2\Client\Tests\Fakers\B2cTokenFaker;

class AzureHelper
{


    /** @var B2cTokenFaker */
    private $tokenFaker;

    /** @var KeysFaker */
    private $keysFaker;


    /** @var string */
    private $defaultClientId;


    /** @var string */
    private $defaultIss;

    /** @var string */
    private $defaultAuthEndpoint;

    /** @var string */
    private $defaultLogoutUrl;

    public function __construct(B2cTokenFaker $tokenFaker, KeysFaker $keysFaker)
    {
        $this->tokenFaker = $tokenFaker;
        $this->keysFaker = $keysFaker;

        $this->defaultClientId = 'client_id';
        $this->defaultIss = 'iss';
        $this->defaultAuthEndpoint = 'auth_endpoint';
        $this->defaultLogoutUrl = 'logout_url';
    }

    /**
     * @return void
     */
    private function setDefaultFakeData(): void
    {
        $this->tokenFaker->setFakeData('b2cId', true, $this->defaultClientId, $this->defaultIss);
    }

    /**
     * @return string[]
     */
    public function getConfig(): array
    {
        return array(
            'issuer' => $this->defaultIss,
            'authorization_endpoint' => $this->defaultAuthEndpoint,
            'end_session_endpoint' => $this->defaultLogoutUrl,
            'token_endpoint' => '',
            'jwks_uri' => ''
        );
    }

    /**
     * @param bool $defaultFakeData
     * @param bool $valid_token
     * @param bool $valid_key
     * @return MockHandler
     */
    private function getHandler(bool $defaultFakeData, bool $valid_token, bool $valid_key): MockHandler
    {
        if ($defaultFakeData) {
            $this->setDefaultFakeData();
        }
        $config = $this->getConfig();
        $tokenResponse = $valid_token ? $this->tokenFaker->getB2cTokenResponse() : [''];
        $keyResponse = $valid_key ? $this->keysFaker->getKeysResponse($this->tokenFaker->getPublicKey(), $this->tokenFaker->getModulus(), $this->tokenFaker->getExponent()) : ['keys' => [['']]];

        return new MockHandler([
            new Response(200, ['content-type' => 'application/json'], json_encode($config)),
            new Response(200, ['content-type' => 'application/json'], json_encode($tokenResponse)),
            new Response(200, ['content-type' => 'application/json'], json_encode($keyResponse)),
            new Response(200, ['content-type' => 'application/json'], json_encode($config)),
            new Response(200, ['content-type' => 'application/json'], json_encode($keyResponse)),
        ]);
    }


    /**
     * @param bool $defaultFakeData
     * @param bool $valid_token
     * @param bool $valid_key
     * @return Client
     */
    public function getMockHttpClient(bool $defaultFakeData = true, bool $valid_token = true, bool $valid_key = true): Client
    {
        return new Client(['handler' => $this->getHandler($defaultFakeData, $valid_token, $valid_key)]);
    }

    /**
     * @param Azure $azure
     * @return AccessTokenInterface
     * @throws IdentityProviderException
     */
    public function getAccessToken(Azure $azure): AccessTokenInterface
    {
        return $azure->getAccessToken('authorization_code', [
            'scope' => $azure->scope,
            'code' => 'authorization_code',
        ]);
    }


    /**
     * @return string
     */
    public function getDefaultClientId(): string
    {
        return $this->defaultClientId;
    }

    /**
     * @return string
     */
    public function getDefaultIss(): string
    {
        return $this->defaultIss;
    }

    /**
     * @return string
     */
    public function getDefaultAuthEndpoint(): string
    {
        return $this->defaultAuthEndpoint;
    }

    /**
     * @return string
     */
    public function getDefaultLogoutUrl(): string
    {
        return $this->defaultLogoutUrl;
    }

    /**
     * @return B2cTokenFaker
     */
    public function getTokenFaker(): B2cTokenFaker
    {
        return $this->tokenFaker;
    }
}
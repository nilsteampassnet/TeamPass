<?php

namespace TheNetworg\OAuth2\Client\Tests\Token;

use PHPUnit\Framework\TestCase;
use TheNetworg\OAuth2\Client\Provider\Azure;
use TheNetworg\OAuth2\Client\Tests\Fakers\KeysFaker;
use TheNetworg\OAuth2\Client\Tests\Fakers\B2cTokenFaker;
use TheNetworg\OAuth2\Client\Tests\Helper\AzureHelper;
use TheNetworg\OAuth2\Client\Token\AccessToken;

class AccessTokenTest extends TestCase
{


    /** @var Azure */
    private $azure;

    /** @var AzureHelper */
    private $helper;

    /**
     * @before
     */
    public function setup(): void
    {
        $this->helper = new AzureHelper(new B2cTokenFaker(), new KeysFaker());
    }

    /**
     * @test
     */
    public function it_passes_an_access_token(): void
    {
        $this->azure = new Azure(['clientId' => $this->helper->getDefaultClientId(), 'defaultAlgorithm' => 'RS256'], ['httpClient' => $this->helper->getMockHttpClient()]);

        /** @var AccessToken $token */
        $token = $this->helper->getAccessToken($this->azure);

        $this->assertNotNull($token->getToken());
        $this->assertNotEmpty($token->getToken());

        $this->assertNotNull($token->getIdToken());
        $this->assertNotEmpty($token->getIdToken());

        $this->assertNotNull($token->getIdTokenClaims());
        $this->assertNotEmpty($token->getIdTokenClaims());
    }

    /**
     * @test
     */
    public function it_correctly_serializes_the_access_token(): void
    {
        $this->azure = new Azure(['clientId' => $this->helper->getDefaultClientId(), 'defaultAlgorithm' => 'RS256'], ['httpClient' => $this->helper->getMockHttpClient()]);

        /** @var AccessToken $token */
        $token = $this->helper->getAccessToken($this->azure);

        $serializedToken = $token->jsonSerialize();

        $this->assertNotNull($serializedToken);
        $this->assertNotEmpty($serializedToken);

        $this->assertEquals($token->getIdToken(), $serializedToken['id_token']);
    }
}
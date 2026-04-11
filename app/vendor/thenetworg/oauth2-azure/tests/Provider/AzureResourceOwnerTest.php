<?php

namespace TheNetworg\OAuth2\Client\Tests\Provider;

use PHPUnit\Framework\TestCase;
use TheNetworg\OAuth2\Client\Provider\Azure;
use TheNetworg\OAuth2\Client\Provider\AzureResourceOwner;
use TheNetworg\OAuth2\Client\Tests\Fakers\KeysFaker;
use TheNetworg\OAuth2\Client\Tests\Fakers\B2cTokenFaker;
use TheNetworg\OAuth2\Client\Tests\Helper\AzureHelper;
use TheNetworg\OAuth2\Client\Token\AccessToken;

class AzureResourceOwnerTest extends TestCase
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
    public function it_creates_valid_resource_owner(): void
    {
        $this->azure = new Azure(['clientId' => $this->helper->getDefaultClientId(), 'defaultAlgorithm' => 'RS256'], ['httpClient' => $this->helper->getMockHttpClient()]);

        /** @var AccessToken $token */
        $token = $this->helper->getAccessToken($this->azure);

        /** @var AzureResourceOwner $owner */
        $owner = $this->azure->getResourceOwner($token);

        $this->assertEquals($this->helper->getDefaultIss(), $owner->claim('iss'));
        $this->assertEquals($this->helper->getDefaultClientId(), $owner->claim('aud'));

        $this->assertNull($owner->getId());
        $this->assertNull($owner->getFirstName());
        $this->assertNull($owner->getLastName());
        $this->assertNull($owner->getUpn());
        $this->assertNull($owner->getTenantId());
        $this->assertNotNull($owner->toArray());
    }

}
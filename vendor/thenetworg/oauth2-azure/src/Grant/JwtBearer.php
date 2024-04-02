<?php

namespace TheNetworg\OAuth2\Client\Grant;

class JwtBearer extends \League\OAuth2\Client\Grant\AbstractGrant
{
    protected function getName()
    {
        return 'urn:ietf:params:oauth:grant-type:jwt-bearer';
    }

    protected function getRequiredRequestParameters()
    {
        return [
            'requested_token_use',
            'assertion',
        ];
    }
}

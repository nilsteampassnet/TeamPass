<?php

namespace TheNetworg\OAuth2\Client\Grant;

class JwtBearer extends \League\OAuth2\Client\Grant\AbstractGrant
{
    protected function getName(): string
    {
        return 'urn:ietf:params:oauth:grant-type:jwt-bearer';
    }

    protected function getRequiredRequestParameters(): array
    {
        return [
            'requested_token_use',
            'assertion',
        ];
    }
}

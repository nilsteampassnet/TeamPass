<?php

namespace TheNetworg\OAuth2\Client\Tests\Fakers;


class KeysFaker
{
    /**
     * @param $publicKey
     * @param $modulus
     * @param $exponent
     * @return array<string, mixed>
     */
    public function getKeysResponse($publicKey, $modulus, $exponent): array
    {

        return array(
            'keys' => [
                array(
                    'kid' => $publicKey,
                    'nbf' => time(),
                    'use' => 'sig',
                    'kty' => 'RSA',
                    'e' => $exponent,
                    'n' => $modulus
                )
            ]
        );
    }


}

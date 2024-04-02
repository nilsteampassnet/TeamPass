<?php

namespace TheNetworg\OAuth2\Client\Tests\Fakers;

use Firebase\JWT\JWT;

class B2cTokenFaker
{

    /** @var string */
    private $publicKey;
    /** @var string */
    private $modulus;
    /** @var string */
    private $exponent;


    /** @var array<string, string> */
    private $fakeData;

    public function __construct()
    {
        $this->publicKey = 'pubkey';
        $this->modulus = 'n';
        $this->exponent = 'e';
    }

    /**
     * @return array<string, mixed>
     */
    public function getB2cTokenResponse(): array
    {
        return $this->createResponse();
    }

    /**
     * @param string $b2cId
     * @param bool $isSuccessful
     */
    public function setFakeData(
        string $b2cId,
        bool   $isSuccessful,
        string $clientId,
        string $issuer,
        ?int   $expires = null,
        ?int   $notBefore = null
    ): void
    {
        $this->fakeData = [
            'sub' => $b2cId,
            'is_b2c_successful' => $isSuccessful,
            'aud' => $clientId,
            'iss' => $issuer,
            'exp' => $expires ?? time() + 3600, // expires in one hour
            'nbf' => $notBefore ?? time(),
        ];
    }

    /**
     * @param array<string, string> $fakeData
     * @return array<string, mixed>
     */
    private function createResponse(): array
    {

        if ($this->fakeData['is_b2c_successful']) {

            $accessToken = array(
              'iss' => 'iss',
              'exp' => $this->fakeData['exp'],
              'nbf' => $this->fakeData['nbf'],
              'aud' => $this->fakeData['aud'],
              'idp_access_token' => '123',
              'idp' => 'idp',
              'sub' => $this->fakeData['sub'],
              'tfp' => 'tfp',
              'ver' => '1.0',
              'iat' => time()
            );

            $idToken = array(
                "exp" => $this->fakeData['exp'],
                "nbf" => $this->fakeData['nbf'],
                "ver" => "1.0",
                "iss" => $this->fakeData['iss'],
                "sub" => $this->fakeData['sub'],
                "aud" => $this->fakeData['aud'],
                "iat" => time(),
                "auth_time" => time(),
                "idp_access_token" => '123',
                "idp" => "idp",
                "tfp" => "tfp",
                "at_hash" => "rfz4eAdZL7I_G8tQBvHI5Q"
            );

            $encryptedAccessToken = $this->createJWT($accessToken);
            $encryptedIdToken = $this->createJWT($idToken);


            return array(
              'access_token' => $encryptedAccessToken,
              'id_token' => $encryptedIdToken,
              'token_type' => 'Bearer',
              'not_before' => time(),
              'expires_in' => 3600,
              'expires_on' => time() + 3600,
              'resource' => 'resource',
              'refresh_token' => $encryptedIdToken,
              'refresh_token_expires_in' => time() + 1209600
            );
        }

        return [];
    }


    /**
     * @param array<string, string> $payload
     * @return string
     */
    private function createJWT(array $payload): string
    {

        $private_key = openssl_pkey_new();
        $details = openssl_pkey_get_details($private_key);

        $publicKeyPEM = $details['key'];

        $modulus = $details['rsa']['n'];
        $exponent = $details['rsa']['e'];

        openssl_pkey_export($private_key, $privateKeyPEM);

        $this->publicKey = $publicKeyPEM;
        $this->modulus = $this->urlsafeBase64($modulus);
        $this->exponent = $this->urlsafeBase64($exponent);


        return JWT::encode($payload, $privateKeyPEM, 'RS256', $publicKeyPEM);
    }

    /**
     * @param string $string
     * @return string
     */
    private function urlsafeBase64(string $string): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($string));
    }

    /**
     * @return string
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * @return string
     */
    public function getModulus(): string
    {
        return $this->modulus;
    }

    /**
     * @return string
     */
    public function getExponent(): string
    {
        return $this->exponent;
    }

}

<?php

namespace TheNetworg\OAuth2\Client\Provider;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use League\OAuth2\Client\Grant\AbstractGrant;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TheNetworg\OAuth2\Client\Grant\JwtBearer;
use TheNetworg\OAuth2\Client\Token\AccessToken;

class Azure extends AbstractProvider
{
    const ENDPOINT_VERSION_1_0 = '1.0';
    const ENDPOINT_VERSION_2_0 = '2.0';
    const ENDPOINT_VERSIONS = [self::ENDPOINT_VERSION_1_0, self::ENDPOINT_VERSION_2_0];

    use BearerAuthorizationTrait;

    public $urlLogin = 'https://login.microsoftonline.com/';

    /** @var array|null */
    protected $openIdConfiguration;

    public $scope = [];

    public $scopeSeparator = ' ';

    public $tenant = 'common';

    public $defaultEndPointVersion = self::ENDPOINT_VERSION_1_0;

    public $urlAPI = 'https://graph.windows.net/';

    public $resource = '';

    public $API_VERSION = '1.6';

    public $authWithResource = true;

    public $defaultAlgorithm = null;

    /**
     * The contents of the private key used for app authentication
     * @var string
     */
    protected $clientCertificatePrivateKey = '';

    /**
     * The hexadecimal certificate thumbprint as displayed in the azure portal
     * @var string
     */
    protected $clientCertificateThumbprint = '';

    public function __construct(array $options = [], array $collaborators = [])
    {
        parent::__construct($options, $collaborators);
        if (isset($options['scopes'])) {
            $this->scope = array_merge($options['scopes'], $this->scope);
        }
        if (isset($options['defaultEndPointVersion']) &&
            in_array($options['defaultEndPointVersion'], self::ENDPOINT_VERSIONS, true)) {
            $this->defaultEndPointVersion = $options['defaultEndPointVersion'];
        }
        if (isset($options['defaultAlgorithm'])) {
            $this->defaultAlgorithm = $options['defaultAlgorithm'];
        }
        $this->grantFactory->setGrant('jwt_bearer', new JwtBearer());
    }

    /**
     * @param string $tenant
     * @param string $version
     */
    protected function getOpenIdConfiguration($tenant, $version) {
        if (!is_array($this->openIdConfiguration)) {
            $this->openIdConfiguration = [];
        }
        if (!array_key_exists($tenant, $this->openIdConfiguration)) {
            $this->openIdConfiguration[$tenant] = [];
        }
        if (!array_key_exists($version, $this->openIdConfiguration[$tenant])) {
            $versionInfix = $this->getVersionUriInfix($version);
	          $openIdConfigurationUri = $this->urlLogin . $tenant . $versionInfix . '/.well-known/openid-configuration?appid=' . $this->clientId;
            
            $factory = $this->getRequestFactory();
            $request = $factory->getRequestWithOptions(
                'get',
                $openIdConfigurationUri,
                []
            );
            $response = $this->getParsedResponse($request);
            $this->openIdConfiguration[$tenant][$version] = $response;
        }

        return $this->openIdConfiguration[$tenant][$version];
    }

    /**
     * @inheritdoc
     */
    public function getBaseAuthorizationUrl(): string
    {
        $openIdConfiguration = $this->getOpenIdConfiguration($this->tenant, $this->defaultEndPointVersion);
        return $openIdConfiguration['authorization_endpoint'];
    }

    /**
     * @inheritdoc
     */
    public function getBaseAccessTokenUrl(array $params): string
    {
        $openIdConfiguration = $this->getOpenIdConfiguration($this->tenant, $this->defaultEndPointVersion);
        return $openIdConfiguration['token_endpoint'];
    }

    protected function getAccessTokenRequest(array $params): RequestInterface
    {
      if ($this->clientCertificatePrivateKey && $this->clientCertificateThumbprint) {
        $header = [
          'x5t' => base64_encode(hex2bin($this->clientCertificateThumbprint)),
        ];
        $now = time();
        $payload = [
          'aud' => "https://login.microsoftonline.com/{$this->tenant}/oauth2/v2.0/token",
          'exp' => $now + 360,
          'iat' => $now,
          'iss' => $this->clientId,
          'jti' => bin2hex(random_bytes(20)),
          'nbf' => $now,
          'sub' => $this->clientId,
        ];
        $jwt = JWT::encode($payload, str_replace('\n', "\n", $this->clientCertificatePrivateKey), 'RS256', null, $header);

        unset($params['client_secret']);
        $params['client_assertion_type'] = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';
        $params['client_assertion'] = $jwt;
      }

      return parent::getAccessTokenRequest($params);
    }

    /**
     * @inheritdoc
     */
    public function getAccessToken($grant, array $options = []): AccessTokenInterface
    {
        if ($this->defaultEndPointVersion != self::ENDPOINT_VERSION_2_0) {
            // Version 2.0 does not support the resources parameter
            // https://docs.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-auth-code-flow
            // while version 1.0 does recommend it
            // https://docs.microsoft.com/en-us/azure/active-directory/develop/v1-protocols-oauth-code
            if ($this->authWithResource) {
                $options['resource'] = $this->resource ? $this->resource : $this->urlAPI;
            }
        }
        return parent::getAccessToken($grant, $options);
    }

    /**
     * @inheritdoc
     */
    public function getResourceOwner(\League\OAuth2\Client\Token\AccessToken $token): ResourceOwnerInterface
    {
        $data = $token->getIdTokenClaims();
        return $this->createResourceOwner($data, $token);
    }

    /**
     * @inheritdoc
     */
    public function getResourceOwnerDetailsUrl(\League\OAuth2\Client\Token\AccessToken $token): string
    {
        $openIdConfiguration = $this->getOpenIdConfiguration($this->tenant, $this->defaultEndPointVersion);
        return $openIdConfiguration['userinfo_endpoint'];
    }

    public function getObjects($tenant, $ref, &$accessToken, $headers = [])
    {
        $objects = [];

        $response = null;
        do {
            if (false === filter_var($ref, FILTER_VALIDATE_URL)) {
                $ref = $tenant . '/' . $ref;
            }

            $response = $this->request('get', $ref, $accessToken, ['headers' => $headers]);
            $values   = $response;
            if (isset($response['value'])) {
                $values = $response['value'];
            }
            foreach ($values as $value) {
                $objects[] = $value;
            }
            if (isset($response['odata.nextLink'])) {
                $ref = $response['odata.nextLink'];
            } elseif (isset($response['@odata.nextLink'])) {
                $ref = $response['@odata.nextLink'];
            } else {
                $ref = null;
            }
        } while (null != $ref);

        return $objects;
    }

    /**
     * @param $accessToken AccessToken|null
     * @return string
     */
    public function getRootMicrosoftGraphUri($accessToken)
    {
        if (is_null($accessToken)) {
            $tenant = $this->tenant;
            $version = $this->defaultEndPointVersion;
        } else {
            $idTokenClaims = $accessToken->getIdTokenClaims();
            $tenant = is_array($idTokenClaims) && array_key_exists('tid', $idTokenClaims) ? $idTokenClaims['tid'] : $this->tenant;
            $version = is_array($idTokenClaims) && array_key_exists('ver', $idTokenClaims) ? $idTokenClaims['ver'] : $this->defaultEndPointVersion;
        }
        $openIdConfiguration = $this->getOpenIdConfiguration($tenant, $version);
        return 'https://' . $openIdConfiguration['msgraph_host'];
    }

    public function get($ref, &$accessToken, $headers = [], $doNotWrap = false)
    {
        $response = $this->request('get', $ref, $accessToken, ['headers' => $headers]);

        return $doNotWrap ? $response : $this->wrapResponse($response);
    }

    public function post($ref, $body, &$accessToken, $headers = [])
    {
        $response = $this->request('post', $ref, $accessToken, ['body' => $body, 'headers' => $headers]);

        return $this->wrapResponse($response);
    }

    public function put($ref, $body, &$accessToken, $headers = [])
    {
        $response = $this->request('put', $ref, $accessToken, ['body' => $body, 'headers' => $headers]);

        return $this->wrapResponse($response);
    }

    public function delete($ref, &$accessToken, $headers = [])
    {
        $response = $this->request('delete', $ref, $accessToken, ['headers' => $headers]);

        return $this->wrapResponse($response);
    }

    public function patch($ref, $body, &$accessToken, $headers = [])
    {
        $response = $this->request('patch', $ref, $accessToken, ['body' => $body, 'headers' => $headers]);

        return $this->wrapResponse($response);
    }

    public function request($method, $ref, &$accessToken, $options = [])
    {
        if ($accessToken->hasExpired()) {
            $accessToken = $this->getAccessToken('refresh_token', [
                'refresh_token' => $accessToken->getRefreshToken(),
            ]);
        }

        $url = null;
        if (false !== filter_var($ref, FILTER_VALIDATE_URL)) {
            $url = $ref;
        } else {
            if (false !== strpos($this->urlAPI, 'graph.windows.net')) {
                $tenant = 'common';
                if (property_exists($this, 'tenant')) {
                    $tenant = $this->tenant;
                }
                $ref = "$tenant/$ref";

                $url = $this->urlAPI . $ref;

                $url .= (false === strrpos($url, '?')) ? '?' : '&';
                $url .= 'api-version=' . $this->API_VERSION;
            } else {
                $url = $this->urlAPI . $ref;
            }
        }

        if (isset($options['body']) && ('array' == gettype($options['body']) || 'object' == gettype($options['body']))) {
            $options['body'] = json_encode($options['body']);
        }
        if (!isset($options['headers']['Content-Type']) && isset($options['body'])) {
            $options['headers']['Content-Type'] = 'application/json';
        }

        $request  = $this->getAuthenticatedRequest($method, $url, $accessToken, $options);
        $response = $this->getParsedResponse($request);

        return $response;
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * Obtain URL for logging out the user.
     *
     * @param $post_logout_redirect_uri string The URL which the user should be redirected to after logout
     *
     * @return string
     */
    public function getLogoutUrl($post_logout_redirect_uri = "")
    {
        $openIdConfiguration = $this->getOpenIdConfiguration($this->tenant, $this->defaultEndPointVersion);
        $logoutUri = $openIdConfiguration['end_session_endpoint'];

        if (!empty($post_logout_redirect_uri)) {
            $logoutUri .= '?post_logout_redirect_uri=' . rawurlencode($post_logout_redirect_uri);
        }

        return $logoutUri;
    }

    /**
     * Validate the access token you received in your application.
     *
     * @param $accessToken string The access token you received in the authorization header.
     *
     * @return array
     */
    public function validateAccessToken($accessToken)
    {
        $keys        = $this->getJwtVerificationKeys();
        $tokenClaims = (array)JWT::decode($accessToken, $keys);

        $this->validateTokenClaims($tokenClaims);

        return $tokenClaims;
    }

    /**
     * Validate the access token claims from an access token you received in your application.
     *
     * @param $tokenClaims array The token claims from an access token you received in the authorization header.
     *
     * @return void
     */
    public function validateTokenClaims($tokenClaims) {
        if ($this->getClientId() != $tokenClaims['aud']) {
            throw new \RuntimeException('The client_id / audience is invalid!');
        }
        if ($tokenClaims['nbf'] > time() || $tokenClaims['exp'] < time()) {
            // Additional validation is being performed in firebase/JWT itself
            throw new \RuntimeException('The id_token is invalid!');
        }

        if ('common' == $this->tenant) {
            $this->tenant = $tokenClaims['tid'];
        }

        $version = array_key_exists('ver', $tokenClaims) ? $tokenClaims['ver'] : $this->defaultEndPointVersion;
        $tenant = $this->getTenantDetails($this->tenant, $version);
        if ($tokenClaims['iss'] != $tenant['issuer']) {
            throw new \RuntimeException('Invalid token issuer (tokenClaims[iss]' . $tokenClaims['iss'] . ', tenant[issuer] ' . $tenant['issuer'] . ')!');
        }
    }

    /**
     * Get JWT verification keys from Azure Active Directory.
     *
     * @return array
     */
    public function getJwtVerificationKeys()
    {
        $openIdConfiguration = $this->getOpenIdConfiguration($this->tenant, $this->defaultEndPointVersion);
        $keysUri = $openIdConfiguration['jwks_uri'];

        $factory = $this->getRequestFactory();
        $request = $factory->getRequestWithOptions('get', $keysUri, []);

        $response = $this->getParsedResponse($request);

        $keys = [];
        foreach ($response['keys'] as $i => $keyinfo) {
            if (isset($keyinfo['x5c']) && is_array($keyinfo['x5c'])) {
                foreach ($keyinfo['x5c'] as $encodedkey) {
                    $cert =
                        '-----BEGIN CERTIFICATE-----' . PHP_EOL
                        . chunk_split($encodedkey, 64,  PHP_EOL)
                        . '-----END CERTIFICATE-----' . PHP_EOL;

                    $cert_object = openssl_x509_read($cert);

                    if ($cert_object === false) {
                        throw new \RuntimeException('An attempt to read ' . $encodedkey . ' as a certificate failed.');
                    }

                    $pkey_object = openssl_pkey_get_public($cert_object);

                    if ($pkey_object === false) {
                        throw new \RuntimeException('An attempt to read a public key from a ' . $encodedkey . ' certificate failed.');
                    }

                    $pkey_array = openssl_pkey_get_details($pkey_object);

                    if ($pkey_array === false) {
                        throw new \RuntimeException('An attempt to get a public key as an array from a ' . $encodedkey . ' certificate failed.');
                    }

                    $publicKey = $pkey_array ['key'];

                    $keys[$keyinfo['kid']] = new Key($publicKey, 'RS256');
                }
            } else if (isset($keyinfo['n']) && isset($keyinfo['e'])) {
                $alg = $this->defaultAlgorithm;
                if (is_null($alg) && isset($keyinfo['kty'])) {
                    $alg = $keyinfo['kty'];
                }

                $pkey_object = JWK::parseKey($keyinfo, $alg);

                if ($pkey_object === false) {
                    throw new \RuntimeException('An attempt to read a public key from a ' . $keyinfo['n'] . ' certificate failed.');
                }

                $pkey_array = openssl_pkey_get_details($pkey_object->getKeyMaterial());

                if ($pkey_array === false) {
                    throw new \RuntimeException('An attempt to get a public key as an array from a ' . $keyinfo['n'] . ' certificate failed.');
                }

                $publicKey = $pkey_array ['key'];

               $keys[$keyinfo['kid']] = new Key($publicKey, 'RS256');;
            }
        }

        return $keys;
    }

    protected function getVersionUriInfix($version)
    {
        return
            ($version == self::ENDPOINT_VERSION_2_0)
                ? '/v' . self::ENDPOINT_VERSION_2_0
                : '';
    }

    /**
     * Get the specified tenant's details.
     *
     * @param string $tenant
     * @param string|null $version
     *
     * @return array
     * @throws IdentityProviderException
     */
    public function getTenantDetails($tenant, $version)
    {
        return $this->getOpenIdConfiguration($this->tenant, $this->defaultEndPointVersion);
    }

    /**
     * @inheritdoc
     */
    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if (isset($data['odata.error']) || isset($data['error'])) {
            if (isset($data['odata.error']['message']['value'])) {
                $message = $data['odata.error']['message']['value'];
            } elseif (isset($data['error']['message'])) {
                $message = $data['error']['message'];
            } elseif (isset($data['error']) && !is_array($data['error'])) {
                $message = $data['error'];
            } else {
                $message = $response->getReasonPhrase();
            }

            if (isset($data['error_description']) && !is_array($data['error_description'])) {
                $message .= PHP_EOL . $data['error_description'];
            }

            throw new IdentityProviderException(
                $message,
                $response->getStatusCode(),
                $response->getBody()
            );
        }
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultScopes(): array
    {
        return $this->scope;
    }

    /**
     * @inheritdoc
     */
    protected function getScopeSeparator(): string
    {
        return $this->scopeSeparator;
    }

    /**
     * @inheritdoc
     */
    protected function createAccessToken(array $response, AbstractGrant $grant): AccessToken
    {
        return new AccessToken($response, $this);
    }

    /**
     * @inheritdoc
     */
    protected function createResourceOwner(array $response, \League\OAuth2\Client\Token\AccessToken $token): AzureResourceOwner
    {
        return new AzureResourceOwner($response);
    }

    private function wrapResponse($response)
    {
        if (empty($response)) {
            return;
        } elseif (isset($response['value'])) {
            return $response['value'];
        }

        return $response;
    }
}

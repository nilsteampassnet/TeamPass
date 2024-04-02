# Azure Active Directory Provider for OAuth 2.0 Client
[![Latest Version](https://img.shields.io/github/release/thenetworg/oauth2-azure.svg?style=flat-square)](https://github.com/thenetworg/oauth2-azure/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/thenetworg/oauth2-azure.svg?style=flat-square)](https://packagist.org/packages/thenetworg/oauth2-azure)
[![Software License](https://img.shields.io/packagist/l/thenetworg/oauth2-azure.svg?style=flat-square)](LICENSE.md)

This package provides [Azure Active Directory](https://azure.microsoft.com/en-us/services/active-directory/) OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

## Table of Contents
- [Installation](#installation)
- [Usage](#usage)
    - [Authorization Code Flow](#authorization-code-flow)
        - [Advanced flow](#advanced-flow)
        - [Using custom parameters](#using-custom-parameters)
        - [**NEW** - Call on behalf of a token provided by another app](#call-on-behalf-of-a-token-provided-by-another-app)
    - [**NEW** - Logging out](#logging-out)
- [Making API Requests](#making-api-requests)
    - [Variables](#variables)
- [Resource Owner](#resource-owner)
- [**UPDATED** - Microsoft Graph](#microsoft-graph)
- [**NEW** - Protecting your API - *experimental*](#protecting-your-api---experimental)
- [Azure Active Directory B2C - *experimental*](#azure-active-directory-b2c---experimental)
- [Multipurpose refresh tokens - *experimental*](#multipurpose-refresh-tokens---experimental)
- [Known users](#known-users)
- [Contributing](#contributing)
- [Credits](#credits)
- [Support](#support)
- [License](#license)

## Installation

To install, use composer:

```
composer require thenetworg/oauth2-azure
```

## Usage

Usage is the same as The League's OAuth client, using `\TheNetworg\OAuth2\Client\Provider\Azure` as the provider.

### Authorization Code Flow

```php
$provider = new TheNetworg\OAuth2\Client\Provider\Azure([
    'clientId'          => '{azure-client-id}',
    'clientSecret'      => '{azure-client-secret}',
    'redirectUri'       => 'https://example.com/callback-url',
    //Optional using key pair instead of secret
    'clientCertificatePrivateKey' => '{azure-client-certificate-private-key}',
    //Optional using key pair instead of secret
    'clientCertificateThumbprint' => '{azure-client-certificate-thumbprint}',
    //Optional
    'scopes'            => ['openid'],
    //Optional
    'defaultEndPointVersion' => '2.0'
]);

// Set to use v2 API, skip the line or set the value to Azure::ENDPOINT_VERSION_1_0 if willing to use v1 API
$provider->defaultEndPointVersion = TheNetworg\OAuth2\Client\Provider\Azure::ENDPOINT_VERSION_2_0;

$baseGraphUri = $provider->getRootMicrosoftGraphUri(null);
$provider->scope = 'openid profile email offline_access ' . $baseGraphUri . '/User.Read';

if (isset($_GET['code']) && isset($_SESSION['OAuth2.state']) && isset($_GET['state'])) {
    if ($_GET['state'] == $_SESSION['OAuth2.state']) {
        unset($_SESSION['OAuth2.state']);

        // Try to get an access token (using the authorization code grant)
        /** @var AccessToken $token */
        $token = $provider->getAccessToken('authorization_code', [
            'scope' => $provider->scope,
            'code' => $_GET['code'],
        ]);

        // Verify token
        // Save it to local server session data
        
        return $token->getToken();
    } else {
        echo 'Invalid state';

        return null;
    }
} else {
    // // Check local server's session data for a token
    // // and verify if still valid 
    // /** @var ?AccessToken $token */
    // $token = // token cached in session data, null if not found;
    //
    // if (isset($token)) {
    //    $me = $provider->get($provider->getRootMicrosoftGraphUri($token) . '/v1.0/me', $token);
    //    $userEmail = $me['mail'];
    //
    //    if ($token->hasExpired()) {
    //        if (!is_null($token->getRefreshToken())) {
    //            $token = $provider->getAccessToken('refresh_token', [
    //                'scope' => $provider->scope,
    //                'refresh_token' => $token->getRefreshToken()
    //            ]);
    //        } else {
    //            $token = null;
    //        }
    //    }
    //}
    //
    // If the token is not found in 
    // if (!isset($token)) {
        $authorizationUrl = $provider->getAuthorizationUrl(['scope' => $provider->scope]);

        $_SESSION['OAuth2.state'] = $provider->getState();

        header('Location: ' . $authorizationUrl);

        exit;
    // }

    return $token->getToken();
}
```

#### Advanced flow

The [Authorization Code Grant Flow](https://msdn.microsoft.com/en-us/library/azure/dn645542.aspx) is a little bit different for Azure Active Directory. Instead of scopes, you specify the resource which you would like to access - there is a param `$provider->authWithResource` which will automatically populate the `resource` param of request with the value of either `$provider->resource` or `$provider->urlAPI`. This feature is mostly intended for v2.0 endpoint of Azure AD (see more [here](https://docs.microsoft.com/en-us/azure/active-directory/develop/azure-ad-endpoint-comparison#scopes-not-resources)).

#### Using custom parameters

With [oauth2-client](https://github.com/thephpleague/oauth2-client) of version 1.3.0 and higher, it is now possible to specify custom parameters for the authorization URL, so you can now make use of options like `prompt`, `login_hint` and similar. See the following example of obtaining an authorization URL which will force the user to reauthenticate:
```php
$authUrl = $provider->getAuthorizationUrl([
    'prompt' => 'login'
]);
```
You can find additional parameters [here](https://msdn.microsoft.com/en-us/library/azure/dn645542.aspx).

#### Using a certificate key pair instead of the shared secret

- Generate a key pair, e.g. with:
```bash
openssl genrsa -out private.key 2048
openssl req -new -x509 -key private.key -out publickey.cer -days 365
```
- Upload the `publickey.cer` to your app in the Azure portal
- Note the displayed thumbprint for the certificate (it looks like `B4A94A83092455AC4D3AC827F02B61646EAAC43D`)
- Put that thumbprint into the `clientCertificateThumbprint` constructor option
- Put the contents of `private.key` into the `clientCertificatePrivateKey` constructor option
- You can omit the `clientSecret` constructor option

### Logging out
If you need to quickly generate a logout URL for the user, you can do following:
```php
// Assuming you have provider properly initialized.
$post_logout_redirect_uri = 'https://www.msn.com'; // The logout destination after the user is logged out from their account.
$logoutUrl = $provider->getLogoutUrl($post_logout_redirect_uri);
header('Location: '.$logoutUrl); // Redirect the user to the generated URL
```

#### Call on behalf of a token provided by another app

```php
// Use token provided by the other app
// Make sure the other app mentioned this app in the scope when requesting the token
$suppliedToken = '';  

$provider = xxxxx;// Initialize provider

// Call this to get claims
// $claims = $provider->validateAccessToken($suppliedToken);

/** @var AccessToken $token */
$token = $provider->getAccessToken('jwt_bearer', [
    'scope' => $provider->scope,
    'assertion' => $suppliedToken,
    'requested_token_use' => 'on_behalf_of',
]);
```

## Making API Requests

This library also provides easy interface to make it easier to interact with [Azure Graph API](https://msdn.microsoft.com/en-us/library/azure/hh974476.aspx) and [Microsoft Graph](http://graph.microsoft.io), the following methods are available on `provider` object (it also handles automatic token refresh flow should it be needed during making the request):

- `get($ref, $accessToken, $headers = [])`
- `post($ref, $body, $accessToken, $headers = [])`
- `put($ref, $body, $accessToken, $headers = [])`
- `delete($ref, $body, $accessToken, $headers = [])`
- `patch($ref, $body, $accessToken, $headers = [])`
- `getObjects($tenant, $ref, $accessToken, $headers = [])` This is used for example for listing large amount of data - where you need to list all users for example - it automatically follows `odata.nextLink` until the end.
  - `$tenant` tenant has to be provided since the `odata.nextLink` doesn't contain it.
- `request($method, $ref, $accessToken, $options = [])` See [#36](https://github.com/TheNetworg/oauth2-azure/issues/36) for use case.

*Please note that if you need to create a custom request, the method getAuthenticatedRequest and getResponse can still be used.*

### Variables
- `$ref` The URL reference without the leading `/`, for example `myOrganization/groups`
- `$body` The contents of the request, make has to be either string (so make sure to use `json_encode` to encode the request)s or stream (see [Guzzle HTTP](http://docs.guzzlephp.org/en/latest/request-options.html#body))
- `$accessToken` The access token object obtained by using `getAccessToken` method
- `$headers` Ability to set custom headers for the request (see [Guzzle HTTP](http://docs.guzzlephp.org/en/latest/request-options.html#headers))

## Resource Owner
With version 1.1.0 and onward, the Resource Owner information is parsed from the JWT passed in `access_token` by Azure Active Directory. It exposes few attributes and one function.

**Example:**
```php
$resourceOwner = $provider->getResourceOwner($token);
echo 'Hello, '.$resourceOwner->getFirstName().'!';
```
The exposed attributes and function are:
- `getId()` - Gets user's object id - unique for each user
- `getFirstName()` - Gets user's first name
- `getLastName()` - Gets user's family name/surname
- `getTenantId()` - Gets id of tenant which the user is member of
- `getUpn()` - Gets user's User Principal Name, which can be also used as user's e-mail address
- `claim($name)` - Gets any other claim (specified as `$name`) from the JWT, full list can be found [here](https://azure.microsoft.com/en-us/documentation/articles/active-directory-token-and-claims/)

## Microsoft Graph
Calling [Microsoft Graph](http://graph.microsoft.io/) is very simple with this library. After provider initialization simply change the API URL followingly (replace `v1.0` with your desired version):
```php
// Mention Microsoft Graph scope when initializing the provider 
$baseGraphUri = $provider->getRootMicrosoftGraphUri(null);
$provider->scope = 'your scope ' . $baseGraphUri . '/User.Read';

// Call a query
$provider->get($provider->getRootMicrosoftGraphUri($token) . '/v1.0/me', $token);
```
After that, when requesting access token, refresh token or so, provide the `resource` with value `https://graph.microsoft.com/` in order to be able to make calls to the Graph (see more about `resource` [here](#advanced-flow)).

## Protecting your API - *experimental*
With version 1.2.0 you can now use this library to protect your API with Azure Active Directory authentication very easily. The Provider now also exposes `validateAccessToken(string $token)` which lets you pass an access token inside which you for example received in the `Authorization` header of the request on your API. You can use the function followingly (in vanilla PHP):
```php
// Assuming you have already initialized the $provider

// Obtain the accessToken - in this case, we are getting it from Authorization header
$headers = getallheaders();
// Assuming you got the value of Authorization header as "Bearer [the_access_token]" we parse it
$authorization = explode(' ', $headers['Authorization']);
$accessToken = $authorization[1];

try {
    $claims = $provider->validateAccessToken($accessToken);
} catch (Exception $e) {
    // Something happened, handle the error
}

// The access token is valid, you can now proceed with your code. You can also access the $claims as defined in JWT - for example roles, group memberships etc.
```

You may also need to access some other resource from the API like the Microsoft Graph to get some additional information. In order to do that, there is `urn:ietf:params:oauth:grant-type:jwt-bearer` grant available ([RFC](https://tools.ietf.org/html/draft-jones-oauth-jwt-bearer-03)). An example (assuming you have the code above working and you have the required permissions configured correctly in the Azure AD application):
```php
$graphAccessToken = $provider->getAccessToken('jwt_bearer', [
    'resource' => 'https://graph.microsoft.com/v1.0/',
    'assertion' => $accessToken,
    'requested_token_use' => 'on_behalf_of'
]);

$me = $provider->get('https://graph.microsoft.com/v1.0/me', $graphAccessToken);
print_r($me);
```
Just to make it easier so you don't have to remember entire name for `grant_type` (`urn:ietf:params:oauth:grant-type:jwt-bearer`), you just use short `jwt_bearer` instead.

## Azure Active Directory B2C - *experimental*
You can also now very simply make use of [Azure Active Directory B2C](https://azure.microsoft.com/en-us/documentation/articles/active-directory-b2c-reference-oauth-code/). Before authentication, change the endpoints using `pathAuthorize`, `pathToken` and `scope` and additionally specify your [login policy](https://azure.microsoft.com/en-gb/documentation/articles/active-directory-b2c-reference-policies/). **Please note that the B2C support is still experimental and wasn't fully tested.**
```php
$provider->pathAuthorize = "/oauth2/v2.0/authorize";
$provider->pathToken = "/oauth2/v2.0/token";
$provider->scope = ["idtoken"];

// Specify custom policy in our authorization URL
$authUrl = $provider->getAuthorizationUrl([
    'p' => 'b2c_1_siup'
]);
```

## Multipurpose refresh tokens - *experimental*
In cause that you need to access multiple resources (like your API and Microsoft Graph), you can use multipurpose [refresh tokens](https://msdn.microsoft.com/en-us/library/azure/dn645538.aspx). Once obtaining a token for first resource, you can simply request another token for different resource like so:
```php
$accessToken2 = $provider->getAccessToken('refresh_token', [
    'refresh_token' => $accessToken1->getRefreshToken(),
    'resource' => 'http://urlOfYourSecondResource'
]);
```
At the moment, there is one issue: When you make a call to your API and the token has expired, it will have the value of `$provider->urlAPI` which is obviously wrong for `$accessToken2`. The solution is very simple - set the `$provider->urlAPI` to the resource which you want to call. This issue will be addressed in future release. **Please note that this is experimental and wasn't fully tested.**

## Known users
If you are using this library and would like to be listed here, please let us know!
- [TheNetworg/DreamSpark-SSO](https://github.com/thenetworg/dreamspark-sso)

## Contributing
We accept contributions via [Pull Requests on Github](https://github.com/thenetworg/oauth2-azure).

## Credits
- [Jan Hajek](https://github.com/hajekj) ([TheNetw.org](https://thenetw.org))
- [Vittorio Bertocci](https://github.com/vibronet) (Microsoft)
    - Thanks for the splendid support while implementing #16
- [Martin Cetkovský](https://github.com/mcetkovsky) ([cetkovsky.eu](https://www.cetkovsky.eu)]
- [All Contributors](https://github.com/thenetworg/oauth2-azure/contributors)

## Support
If you find a bug or encounter any issue or have a problem/question with this library please create a [new issue](https://github.com/TheNetworg/oauth2-azure/issues).

## License
The MIT License (MIT). Please see [License File](https://github.com/thenetworg/oauth2-azure/blob/master/LICENSE) for more information.

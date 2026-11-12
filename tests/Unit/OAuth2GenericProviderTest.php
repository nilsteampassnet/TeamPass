<?php

declare(strict_types=1);

use League\OAuth2\Client\Provider\GenericProvider;
use PHPUnit\Framework\TestCase;
use TeampassClasses\OAuth2Controller\OAuth2Controller;
use TheNetworg\OAuth2\Client\Provider\Azure;

/**
 * Generic OpenID Connect provider support of OAuth2Controller (PR #5445).
 *
 * Covers the authorization request (space-separated scopes, redirect URI) and the
 * mapping of the UserInfo claims onto the keys the login flow reads. No network call:
 * building an authorization URL is local for GenericProvider, and the Azure provider is
 * only inspected, never asked for its (remote) OpenID configuration.
 */
class OAuth2GenericProviderTest extends TestCase
{
    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function settings(array $overrides = []): array
    {
        return array_merge([
            'cpassman_url' => 'https://teampass.example.com/',
            'oauth2_client_id' => 'client-id',
            'oauth2_client_secret' => 'client-secret',
            'oauth2_tenant_id' => '',
            'oauth2_client_endpoint' => 'https://authentik.example.com/application/o/authorize/',
            'oauth2_client_token' => 'https://authentik.example.com/application/o/token/',
            'oauth2_client_urlResourceOwnerDetails' => 'https://authentik.example.com/application/o/userinfo/',
            'oauth2_client_scopes' => 'openid, profile,email,',
        ], $overrides);
    }

    private function provider(OAuth2Controller $controller): object
    {
        $property = new ReflectionProperty(OAuth2Controller::class, 'provider');
        $property->setAccessible(true);

        return $property->getValue($controller);
    }

    /**
     * @return array<string, string>
     */
    private function authorizationQuery(OAuth2Controller $controller): array
    {
        $provider = $this->provider($controller);
        $this->assertInstanceOf(GenericProvider::class, $provider);

        parse_str((string) parse_url($provider->getAuthorizationUrl(), PHP_URL_QUERY), $query);

        return $query;
    }

    public function testGenericProviderRequestsSpaceSeparatedScopes(): void
    {
        $query = $this->authorizationQuery(new OAuth2Controller($this->settings()));

        $this->assertSame('openid profile email', $query['scope']);
    }

    public function testGenericRedirectUriIgnoresTrailingSlashOfTeampassUrl(): void
    {
        $withSlash = $this->authorizationQuery(new OAuth2Controller($this->settings()));
        $withoutSlash = $this->authorizationQuery(new OAuth2Controller($this->settings([
            'cpassman_url' => 'https://teampass.example.com',
        ])));

        $this->assertSame('https://teampass.example.com/index.php?post_type=oauth2', $withSlash['redirect_uri']);
        $this->assertSame($withSlash['redirect_uri'], $withoutSlash['redirect_uri']);
    }

    public function testEntraProviderReceivesCleanScopes(): void
    {
        $provider = $this->provider(new OAuth2Controller($this->settings([
            'oauth2_client_endpoint' => 'https://login.microsoftonline.com/tenant/oauth2/v2.0/authorize',
            'oauth2_tenant_id' => '00000000-0000-0000-0000-000000000000',
        ])));

        $this->assertInstanceOf(Azure::class, $provider);
        $this->assertSame(['openid', 'profile', 'email'], $provider->scope);
    }

    public function testAuthentikDefaultClaimsAreMapped(): void
    {
        $user = OAuth2Controller::mapGenericUserInfo([
            'sub' => '2d4e6f8a',
            'preferred_username' => 'jdoe',
            'nickname' => 'jdoe',
            'name' => 'John Doe',
            'given_name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'email_verified' => false,
            'groups' => ['TeamPass Users', 'Managers'],
        ]);

        $this->assertSame('jdoe', $user['userPrincipalName']);
        $this->assertSame('John Doe', $user['givenname']);
        $this->assertSame('', $user['surname']);
        $this->assertSame('john.doe@example.com', $user['mail']);
        $this->assertSame('2d4e6f8a', $user['sub'], 'sub must be kept: the login page derives the account password from it');
        $this->assertSame([
            ['id' => 'TeamPass Users', 'displayName' => 'TeamPass Users'],
            ['id' => 'Managers', 'displayName' => 'Managers'],
        ], $user['groups']);
    }

    public function testKeycloakRolesAndGroupsAreMergedWithoutDuplicates(): void
    {
        $user = OAuth2Controller::mapGenericUserInfo([
            'sub' => 'f3a1',
            'preferred_username' => 'alice@example.com',
            'given_name' => 'Alice',
            'family_name' => 'Martin',
            'groups' => ['Managers'],
            'realm_access' => ['roles' => ['Managers', 'offline_access']],
        ]);

        $this->assertSame('alice@example.com', $user['userPrincipalName']);
        $this->assertSame('Alice', $user['givenname']);
        $this->assertSame('Martin', $user['surname']);
        $this->assertSame([
            ['id' => 'Managers', 'displayName' => 'Managers'],
            ['id' => 'offline_access', 'displayName' => 'offline_access'],
        ], $user['groups']);
    }

    public function testGroupObjectsFromCustomMappingsAreAccepted(): void
    {
        $user = OAuth2Controller::mapGenericUserInfo([
            'sub' => 'a1',
            'preferred_username' => 'bob',
            'realm_access' => ['roles' => [
                ['id' => 'g-1', 'displayName' => 'Managers'],
                ['name' => 'Auditors'],
            ]],
        ]);

        $this->assertSame([
            ['id' => 'g-1', 'displayName' => 'Managers'],
            ['id' => 'Auditors', 'displayName' => 'Auditors'],
        ], $user['groups']);
    }

    public function testMalformedOptionalClaimsAreIgnored(): void
    {
        $user = OAuth2Controller::mapGenericUserInfo([
            'sub' => 'a1',
            'preferred_username' => 'bob',
            'given_name' => ['unexpected'],
            'email' => null,
            'groups' => 'Managers',
            'realm_access' => 'not-an-object',
        ]);

        $this->assertSame('', $user['givenname']);
        $this->assertSame('', $user['mail']);
        $this->assertSame([], $user['groups']);

        $user = OAuth2Controller::mapGenericUserInfo([
            'sub' => 'a1',
            'preferred_username' => 'bob',
            'groups' => [null, '', '  ', ['id' => 'no-name'], 42, 'Managers'],
        ]);

        $this->assertSame([
            ['id' => '42', 'displayName' => '42'],
            ['id' => 'Managers', 'displayName' => 'Managers'],
        ], $user['groups']);
    }

    public function testMissingSubjectIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('"sub"');

        OAuth2Controller::mapGenericUserInfo(['preferred_username' => 'bob']);
    }

    public function testMissingUsernameIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('"preferred_username"');

        OAuth2Controller::mapGenericUserInfo(['sub' => 'a1', 'preferred_username' => '  ']);
    }

    /**
     * Build an unsigned JWT carrying the given claims (the signature part is never read).
     *
     * @param array<string, mixed> $claims
     */
    private function idToken(array $claims): string
    {
        $encode = static fn (string $part): string => rtrim(strtr(base64_encode($part), '+/', '-_'), '=');

        return $encode('{"alg":"RS256"}') . '.' . $encode((string) json_encode($claims)) . '.signature';
    }

    public function testEntraIdentityIsTheLowercasedObjectIdUnderAFixedIssuer(): void
    {
        $identity = OAuth2Controller::entraIdentity([
            'id' => '0F8E1C2A-5B6D-4E7F-8A9B-0C1D2E3F4A5B',
            'userPrincipalName' => 'jdoe@a.example',
        ]);

        // Never the tenant: oauth2_tenant_id holds a GUID or a domain, so it is no stable key
        $this->assertSame('entra', $identity['identityIssuer']);
        $this->assertSame('0f8e1c2a-5b6d-4e7f-8a9b-0c1d2e3f4a5b', $identity['identitySubject']);
    }

    public function testEntraAnswerWithoutObjectIdGivesNoSubject(): void
    {
        $this->assertSame('', OAuth2Controller::entraIdentity(['userPrincipalName' => 'jdoe@a.example'])['identitySubject']);
    }

    public function testGenericIdentityUsesTheIssuerOfTheIdToken(): void
    {
        $user = OAuth2Controller::mapGenericUserInfo(['sub' => 'Ab12', 'preferred_username' => 'jdoe']);
        $identity = OAuth2Controller::genericIdentity(
            ['id_token' => $this->idToken(['iss' => 'https://authentik.example.com/application/o/teampass/', 'sub' => 'Ab12'])],
            $user,
            'https://authentik.example.com/application/o/authorize/'
        );

        $this->assertSame('https://authentik.example.com/application/o/teampass/', $identity['identityIssuer']);
        // sub is case-sensitive (OpenID Connect Core §2): kept as is
        $this->assertSame('Ab12', $identity['identitySubject']);
    }

    public function testGenericIdentityFallsBackToTheAuthorizationOrigin(): void
    {
        $user = OAuth2Controller::mapGenericUserInfo(['id' => 1234, 'preferred_username' => 'jdoe']);

        $withoutToken = OAuth2Controller::genericIdentity([], $user, 'HTTPS://SSO.Example.com:8443/oauth/authorize?x=1');
        $this->assertSame('https://sso.example.com:8443', $withoutToken['identityIssuer']);
        $this->assertSame('1234', $withoutToken['identitySubject']);

        $notAJwt = OAuth2Controller::genericIdentity(['id_token' => 'opaque'], $user, 'https://sso.example.com/authorize');
        $this->assertSame('https://sso.example.com', $notAJwt['identityIssuer']);
    }

    public function testGenericIdentityRefusesAUserInfoSubjectThatDiffersFromTheIdToken(): void
    {
        $user = OAuth2Controller::mapGenericUserInfo(['sub' => 'attacker', 'preferred_username' => 'jdoe']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('"sub"');

        OAuth2Controller::genericIdentity(
            ['id_token' => $this->idToken(['iss' => 'https://idp.example.com', 'sub' => 'victim'])],
            $user,
            'https://idp.example.com/authorize'
        );
    }
}

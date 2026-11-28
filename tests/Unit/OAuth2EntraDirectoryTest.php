<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use TeampassClasses\OAuth2Controller\OAuth2Controller;
use TheNetworg\OAuth2\Client\Provider\Azure;

/**
 * Tests the Entra queries for the OAuth diagnostics and directory synchronization.
 * Graph is replaced by canned answers keyed by URL.
 */
final class OAuth2EntraDirectoryTest extends TestCase
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0/';

    /** @var array<int, string> URLs requested, in order */
    private array $requested = [];

    /** @var array<string, string> ConsistencyLevel header sent for each URL */
    private array $consistency = [];

    /** @var array<int, string> Grants the access tokens were requested with */
    private array $grants = [];

    private string $log = '';
    private string $previousLog = '';

    protected function setUp(): void
    {
        $this->log = (string) tempnam(sys_get_temp_dir(), 'tp-entra-directory-');
        $this->previousLog = (string) ini_get('error_log');
        ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousLog);
        unlink($this->log);
    }

    /**
     * @param array<string, string> $changes
     * @return array<string, string>
     */
    private static function settings(array $changes = []): array
    {
        return array_merge([
            'cpassman_url' => 'https://teampass.example.com/',
            'oauth2_client_id' => 'client-id',
            'oauth2_client_secret' => 'client-secret',
            'oauth2_tenant_id' => '00000000-0000-0000-0000-000000000000',
            'oauth2_client_endpoint' => 'https://login.microsoftonline.com/tenant/oauth2/v2.0/authorize',
            'oauth2_client_token' => 'https://login.microsoftonline.com/tenant/oauth2/v2.0/token',
            'oauth2_client_urlResourceOwnerDetails' => '',
            'oauth2_client_scopes' => 'openid,profile,email,User.Read,Group.Read.All',
        ], $changes);
    }

    /**
     * Entra controller whose provider answers from $answers; an answer that is an exception is
     * thrown, as the provider does on a Graph error.
     *
     * @param array<string, array<string, mixed>|Exception> $answers
     */
    private function controller(array $answers): OAuth2Controller
    {
        $controller = new OAuth2Controller(self::settings());

        $provider = $this->getMockBuilder(Azure::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAccessToken', 'getAuthenticatedRequest', 'getParsedResponse'])
            ->getMock();
        $provider->method('getAccessToken')->willReturnCallback(
            function (string $grant, array $options): AccessToken {
                $this->grants[] = $grant;
                $this->assertSame('https://graph.microsoft.com/.default', $options['scope'] ?? null);

                return new AccessToken(['access_token' => 'app-token']);
            }
        );
        $provider->method('getAuthenticatedRequest')->willReturnCallback(
            function (string $method, string $url, string $token, array $options = []): RequestInterface {
                $this->assertSame('app-token', $token);
                $this->requested[] = $url;
                $this->consistency[$url] = $options['headers']['ConsistencyLevel'] ?? '';

                return new Request($method, $url);
            }
        );
        $provider->method('getParsedResponse')->willReturnCallback(
            static function (RequestInterface $request) use ($answers) {
                $answer = $answers[(string) $request->getUri()] ?? new RuntimeException('Unexpected URL ' . $request->getUri());
                if ($answer instanceof Exception) {
                    throw $answer;
                }

                return $answer;
            }
        );

        $property = new ReflectionProperty(OAuth2Controller::class, 'provider');
        $property->setAccessible(true);
        $property->setValue($controller, $provider);

        return $controller;
    }

    private static function usersUrl(string $prefix): string
    {
        return self::GRAPH . 'users?$filter=' . rawurlencode("startswith(userPrincipalName,'" . $prefix . "')")
            . '&$select=id,displayName,userPrincipalName,accountEnabled&$top=10';
    }

    private static function groupsByNameUrl(string $name): string
    {
        return self::GRAPH . 'groups?$filter=' . rawurlencode("displayName eq '" . $name . "'")
            . '&$select=id,displayName&$top=10';
    }

    private static function membersUrl(string $id): string
    {
        return self::GRAPH . 'groups/' . $id . '/transitiveMembers/microsoft.graph.user?$count=true'
            . '&$select=id,displayName,userPrincipalName,givenName,surname,mail,accountEnabled,lastPasswordChangeDateTime&$top=999';
    }

    /** @return array<string, mixed> */
    private static function group(string $id, string $name): array
    {
        return ['@odata.type' => '#microsoft.graph.group', 'id' => $id, 'displayName' => $name];
    }

    public function testEveryDirectoryUserOfTheLoginIsReturnedWithTheGroupsOfTheSignIn(): void
    {
        $result = $this->controller([
            self::usersUrl('jdoe@') => ['value' => [
                ['id' => 'U-A', 'displayName' => 'John A', 'userPrincipalName' => 'jdoe@a.example', 'accountEnabled' => true],
                ['id' => 'U-B', 'displayName' => 'John B', 'userPrincipalName' => 'JDoe@b.example', 'accountEnabled' => false],
            ]],
            self::GRAPH . 'users/U-A/transitiveMemberOf' => ['value' => [self::group('g1', 'TeamPass-Users')]],
            self::GRAPH . 'users/U-B/transitiveMemberOf' => ['value' => []],
        ])->describeDirectoryUsers('jdoe');

        $this->assertFalse($result['error'], $result['message']);
        $this->assertSame(['jdoe@a.example', 'JDoe@b.example'], array_column($result['users'], 'userPrincipalName'));
        $this->assertSame([['id' => 'g1', 'displayName' => 'TeamPass-Users']], $result['users'][0]['groups']);
        $this->assertFalse($result['users'][1]['accountEnabled']);
        // The application token, as for the OAuth2 synchronization
        $this->assertSame(['client_credentials'], $this->grants);
    }

    public function testTheUpnGivenComesFirst(): void
    {
        // Graph compares the filter ignoring case: the login is sent as typed
        $result = $this->controller([
            self::usersUrl('JDOE@') => ['value' => [
                ['id' => 'U-A', 'userPrincipalName' => 'jdoe@a.example'],
                ['id' => 'U-B', 'userPrincipalName' => 'jdoe@b.example'],
            ]],
            self::GRAPH . 'users/U-A/transitiveMemberOf' => ['value' => []],
            self::GRAPH . 'users/U-B/transitiveMemberOf' => ['value' => []],
        ])->describeDirectoryUsers('JDOE@B.example');

        $this->assertSame(['jdoe@b.example', 'jdoe@a.example'], array_column($result['users'], 'userPrincipalName'));
    }

    public function testOnlyTheUpnsOfTheSameLoginAreKept(): void
    {
        $result = $this->controller([
            self::usersUrl('jdoe@') => ['value' => [
                ['id' => 'U-A', 'userPrincipalName' => 'jdoe2@a.example'],
                ['id' => 'U-B', 'userPrincipalName' => 'no-at-sign'],
                ['userPrincipalName' => 'jdoe@no-id.example'],
                'not an object',
            ]],
        ])->describeDirectoryUsers('jdoe');

        $this->assertFalse($result['error'], $result['message']);
        $this->assertSame([], $result['users']);
    }

    public function testQuotesOfTheLoginAreEscapedInTheFilter(): void
    {
        $result = $this->controller([
            self::usersUrl("o''neil@") => ['value' => []],
        ])->describeDirectoryUsers("o'neil");

        $this->assertFalse($result['error'], $result['message']);
        $this->assertSame([self::usersUrl("o''neil@")], $this->requested);
    }

    public function testAGraphRefusalReportsItsStatus(): void
    {
        $result = $this->controller([
            self::usersUrl('jdoe@') => new IdentityProviderException('Insufficient privileges to complete the operation.', 403, ''),
        ])->describeDirectoryUsers('jdoe');

        $this->assertTrue($result['error']);
        $this->assertSame(403, $result['code']);
        $this->assertStringContainsString('Insufficient privileges', $result['message']);
        $this->assertSame([], $result['users']);
    }

    public function testAnEmptyLoginIsRefusedWithoutQueryingGraph(): void
    {
        $result = $this->controller([])->describeDirectoryUsers(' @contoso.com');

        $this->assertTrue($result['error']);
        $this->assertSame([], $this->requested);
    }

    public function testAGenericProviderCannotBeQueried(): void
    {
        $controller = new OAuth2Controller(self::settings([
            'oauth2_client_endpoint' => 'https://authentik.example.com/application/o/authorize/',
            'oauth2_client_token' => 'https://authentik.example.com/application/o/token/',
        ]));

        $this->assertTrue($controller->describeDirectoryUsers('jdoe')['error']);
        $this->assertTrue($controller->checkGroupNames(['Sales'])['error']);
        $this->assertFalse(OAuth2Controller::isEntraEndpoint('https://authentik.example.com/application/o/authorize/'));
        $this->assertTrue(OAuth2Controller::isEntraEndpoint(self::settings()['oauth2_client_endpoint']));
    }

    public function testEachGroupEntryIsLookedUpByNameOrById(): void
    {
        $id = '0a1b2c3d-0000-4000-8000-00000000abcd';
        $unknownId = '0a1b2c3d-0000-4000-8000-00000000ffff';

        $result = $this->controller([
            self::groupsByNameUrl('TeamPass-Users') => ['value' => [self::group('g1', 'TeamPass-Users')]],
            self::groupsByNameUrl('Sales') => ['value' => [self::group('g2', 'Sales'), self::group('g3', 'Sales')]],
            self::groupsByNameUrl("O''Neil team") => ['value' => []],
            self::GRAPH . 'groups/' . $id . '?$select=id,displayName' => ['id' => $id, 'displayName' => 'Helpdesk'],
            self::GRAPH . 'groups/' . $unknownId . '?$select=id,displayName' => new IdentityProviderException('Resource does not exist', 404, ''),
        ])->checkGroupNames(['TeamPass-Users', 'Sales', "O'Neil team", $id, $unknownId]);

        $this->assertFalse($result['error'], $result['message']);
        $this->assertSame(
            [1, 2, 0, 1, 0],
            array_map(static fn (array $entry): int => count($entry['matches']), $result['groups'])
        );
        $this->assertSame('Helpdesk', $result['groups'][3]['matches'][0]['displayName']);
        $this->assertSame(['client_credentials'], $this->grants);
    }

    public function testAGroupLookupRefusedByGraphFailsTheWholeCheck(): void
    {
        $result = $this->controller([
            self::groupsByNameUrl('Sales') => new IdentityProviderException('Insufficient privileges to complete the operation.', 403, ''),
        ])->checkGroupNames(['Sales']);

        $this->assertTrue($result['error']);
        $this->assertSame(403, $result['code']);
    }

    public function testFullSyncStillListsUsersWhenOneMembershipLookupFails(): void
    {
        $first = self::GRAPH . 'users?$select=id,displayName,userPrincipalName,givenName,surname,mail,accountEnabled,lastPasswordChangeDateTime&$top=999';
        $next = self::GRAPH . 'users?$skiptoken=more-users';
        $result = $this->controller([
            $first => ['value' => [['id' => 'u1', 'userPrincipalName' => 'sam@example.net']], '@odata.nextLink' => $next],
            self::GRAPH . 'users/u1/memberOf' => ['value' => [self::group('g1', 'Support')]],
            $next => ['value' => [['id' => 'u2', 'userPrincipalName' => 'jules@example.net']]],
            self::GRAPH . 'users/u2/memberOf' => new IdentityProviderException('Forbidden', 403, ''),
        ])->getAllUsers();

        $this->assertFalse($result['error'], $result['message']);
        $this->assertSame(['u1', 'u2'], array_column($result['users'], 'id'));
        $this->assertSame([['id' => 'g1', 'displayName' => 'Support']], $result['users'][0]['groups']);
        $this->assertSame([], $result['users'][1]['groups']);
    }

    public function testFilteredSyncReadsAllMatchingGroupsAndPagesAndEnrichesEachUserOnce(): void
    {
        $groupId = '0a1b2c3d-0000-4000-8000-00000000abcd';
        $missing = '0a1b2c3d-0000-4000-8000-00000000ffff';
        $groupsNext = self::GRAPH . 'groups?$skiptoken=more-groups';
        $membersNext = self::GRAPH . 'groups/first/transitiveMembers/microsoft.graph.user?$skiptoken=more-users';
        $rolesNext = self::GRAPH . 'users/u1/memberOf?$skiptoken=more-roles';
        $name = 'R&D "Lead" <Ops> O\'Brien';
        $firstUser = ['id' => 'u1', 'userPrincipalName' => 'sam@example.net', 'displayName' => 'Sam', 'accountEnabled' => false];
        $secondUser = ['id' => 'u2', 'userPrincipalName' => 'jules@example.net', 'mail' => 'jules@example.net'];
        $result = $this->controller([
            self::groupsByNameUrl('R&D "Lead" <Ops> O\'\'Brien') => [
                'value' => [self::group('first', $name)], '@odata.nextLink' => $groupsNext,
            ],
            $groupsNext => ['value' => [self::group($groupId, $name)]],
            self::GRAPH . 'groups/' . $groupId . '?$select=id,displayName' => self::group($groupId, $name),
            self::GRAPH . 'groups/' . $missing . '?$select=id,displayName' => new IdentityProviderException('Not found', 404, ''),
            self::membersUrl('first') => ['value' => [$firstUser], '@odata.nextLink' => $membersNext],
            $membersNext => ['value' => [$secondUser]],
            self::membersUrl($groupId) => ['value' => [$firstUser]],
            self::GRAPH . 'users/u1/memberOf' => [
                'value' => [self::group('first', $name)], '@odata.nextLink' => $rolesNext,
            ],
            $rolesNext => ['value' => [self::group('support', 'Support')]],
            self::GRAPH . 'users/u2/memberOf' => ['value' => []],
        ])->getUsersInGroups([$name, $groupId, $missing]);

        $this->assertFalse($result['error']);
        $this->assertSame([$missing], $result['unmatchedGroups']);
        $this->assertSame(['u1', 'u2'], array_column($result['users'], 'id'));
        $this->assertFalse($result['users'][0]['accountEnabled']);
        $this->assertSame('jules@example.net', $result['users'][1]['mail']);
        $this->assertSame([$name, 'Support'], array_column($result['users'][0]['groups'], 'displayName'));
        $this->assertSame(['client_credentials'], $this->grants);
        $this->assertSame('eventual', $this->consistency[self::membersUrl('first')]);
        $this->assertSame('eventual', $this->consistency[$membersNext]);
        $this->assertCount(1, array_keys($this->requested, self::GRAPH . 'users/u1/memberOf', true));
        $this->assertCount(1, array_keys($this->requested, self::membersUrl($groupId), true));
        $this->assertCount(10, $this->requested);
    }

    public function testFilteredSyncWithNoConfiguredGroupsIssuesNoGraphRequest(): void
    {
        $result = $this->controller([])->getUsersInGroups([]);

        $this->assertFalse($result['error']);
        $this->assertSame([], $result['users']);
        $this->assertSame([], $this->grants);
        $this->assertSame([], $this->requested);
    }

    public function testFilteredSyncReportsGraphFailureOnALaterPageWithoutPartialResults(): void
    {
        $next = self::GRAPH . 'groups/g1/transitiveMembers/microsoft.graph.user?$skiptoken=second';
        $result = $this->controller([
            self::groupsByNameUrl('Allowed') => ['value' => [self::group('g1', 'Allowed')]],
            self::membersUrl('g1') => ['value' => [['id' => 'u1']], '@odata.nextLink' => $next],
            $next => new IdentityProviderException('Too Many Requests', 429, ''),
        ])->getUsersInGroups(['Allowed']);

        $this->assertTrue($result['error']);
        $this->assertSame(429, $result['code']);
        $this->assertSame([], $result['users']);
        $this->assertSame($next, end($this->requested));
    }

    public function testFilteredSyncRejectsInvalidContinuationLinksBeforeSendingTheToken(): void
    {
        $result = $this->controller([
            self::groupsByNameUrl('Allowed') => ['value' => [self::group('g1', 'Allowed')]],
            self::membersUrl('g1') => ['value' => [], '@odata.nextLink' => 'https://directory.example.net/next'],
        ])->getUsersInGroups(['Allowed']);

        $this->assertTrue($result['error']);
        $this->assertSame([], $result['users']);
        $this->assertSame([self::groupsByNameUrl('Allowed'), self::membersUrl('g1')], $this->requested);
    }
}

<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use TeampassClasses\OAuth2Controller\OAuth2Controller;
use TheNetworg\OAuth2\Client\Provider\Azure;

/**
 * Groups OAuth2Controller reads from Microsoft Graph for the Entra user signing in: the input of
 * the self-registration check and of the role matching. Graph is replaced by canned answers
 * keyed by URL; no network call is made.
 */
final class OAuth2EntraGroupsTest extends TestCase
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0/';
    private const TRANSITIVE = self::GRAPH . 'me/transitiveMemberOf';
    private const DIRECT = self::GRAPH . 'me/memberOf';

    /** @var array<int, string> URLs requested, in order */
    private array $requested = [];

    /** @var array<string, mixed> */
    private array $savedGet = [];

    /** @var array<string, mixed> */
    private array $savedSession = [];

    private string $log = '';
    private string $previousLog = '';

    /** Capture the controller's error_log() lines instead of printing them on the test output. */
    protected function setUp(): void
    {
        $this->savedGet = $_GET;
        $this->savedSession = $_SESSION ?? [];
        $this->log = (string) tempnam(sys_get_temp_dir(), 'tp-entra-groups-');
        $this->previousLog = (string) ini_get('error_log');
        ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        $_GET = $this->savedGet;
        $_SESSION = $this->savedSession;
        ini_set('error_log', $this->previousLog);
        unlink($this->log);
    }

    /**
     * Entra controller whose provider answers from $answers; an answer that is an exception is
     * thrown, as the provider does on a Graph error.
     *
     * @param array<string, array<string, mixed>|Exception> $answers
     */
    private function controller(array $answers): OAuth2Controller
    {
        $controller = new OAuth2Controller([
            'cpassman_url' => 'https://teampass.example.com/',
            'oauth2_client_id' => 'client-id',
            'oauth2_client_secret' => 'client-secret',
            'oauth2_tenant_id' => '00000000-0000-0000-0000-000000000000',
            'oauth2_client_endpoint' => 'https://login.microsoftonline.com/tenant/oauth2/v2.0/authorize',
            'oauth2_client_token' => 'https://login.microsoftonline.com/tenant/oauth2/v2.0/token',
            'oauth2_client_urlResourceOwnerDetails' => '',
            'oauth2_client_scopes' => 'openid,profile,email,User.Read,Group.Read.All',
        ]);

        $provider = $this->getMockBuilder(Azure::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAccessToken', 'getAuthenticatedRequest', 'getParsedResponse'])
            ->getMock();
        $provider->method('getAccessToken')->willReturn(new AccessToken(['access_token' => 'token']));
        $provider->method('getAuthenticatedRequest')->willReturnCallback(
            function (string $method, string $url, string $token): RequestInterface {
                $this->assertSame('token', $token);
                $this->requested[] = $url;

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

    /**
     * @param array<string, array<string, mixed>|Exception> $answers
     * @return array<int, array<string, mixed>>
     */
    private function groupsOf(array $answers): array
    {
        $method = new ReflectionMethod(OAuth2Controller::class, 'entraUserGroups');
        $method->setAccessible(true);

        return $method->invoke($this->controller($answers), 'me', 'token');
    }

    /** @return array<string, string> */
    private static function group(string $id, ?string $name): array
    {
        return array_filter(
            ['@odata.type' => '#microsoft.graph.group', 'id' => $id, 'displayName' => $name],
            static fn ($value): bool => $value !== null
        );
    }

    public function testTheSignInReadsTheNestedMembershipsOfTheUser(): void
    {
        $_GET = ['state' => 'state', 'code' => 'code'];
        $_SESSION['oauth2state'] = 'state';

        $result = $this->controller([
            self::GRAPH . 'me' => ['id' => 'ABC-1', 'userPrincipalName' => 'jdoe@example.com'],
            self::TRANSITIVE => ['value' => [self::group('g1', 'TeamPass-Users')]],
        ])->callback();

        $this->assertFalse($result['error'], (string) ($result['message'] ?? ''));
        $this->assertSame([['id' => 'g1', 'displayName' => 'TeamPass-Users']], $result['userOauth2Info']['groups']);
        $this->assertSame([self::GRAPH . 'me', self::TRANSITIVE], $this->requested);
        // Lot 1: the identity is still published next to the groups
        $this->assertSame('abc-1', $result['userOauth2Info']['identitySubject']);
    }

    public function testEveryPageIsRead(): void
    {
        $next = self::TRANSITIVE . '?$skiptoken=page2';

        $groups = $this->groupsOf([
            self::TRANSITIVE => ['value' => [self::group('g1', 'Sales')], '@odata.nextLink' => $next],
            $next => ['value' => [self::group('g2', 'TeamPass-Users')]],
        ]);

        $this->assertSame(['Sales', 'TeamPass-Users'], array_column($groups, 'displayName'));
        $this->assertSame([self::TRANSITIVE, $next], $this->requested);
    }

    public function testOnlyGroupsAreKept(): void
    {
        $groups = $this->groupsOf([
            self::TRANSITIVE => ['value' => [
                ['@odata.type' => '#microsoft.graph.directoryRole', 'id' => 'r1', 'displayName' => 'Global Administrator'],
                ['@odata.type' => '#microsoft.graph.administrativeUnit', 'id' => 'u1', 'displayName' => 'Paris'],
                self::group('g1', 'Sales'),
                // No type: kept, as every entry was before
                ['id' => 'g2', 'displayName' => 'Untyped'],
                'not an object',
            ]],
        ]);

        $this->assertSame(['Sales', 'Untyped'], array_column($groups, 'displayName'));
    }

    public function testAGroupReachedTwiceIsListedOnce(): void
    {
        $next = self::TRANSITIVE . '?$skiptoken=page2';

        $groups = $this->groupsOf([
            self::TRANSITIVE => ['value' => [self::group('g1', 'Sales')], '@odata.nextLink' => $next],
            $next => ['value' => [self::group('g1', 'Sales'), self::group('g2', 'Finance')]],
        ]);

        $this->assertSame(['g1', 'g2'], array_column($groups, 'id'));
    }

    public function testAGroupGraphReturnsWithoutItsNameKeepsItsId(): void
    {
        // Token without a permission to read groups: Graph sends the type and the id only
        $groups = $this->groupsOf([self::TRANSITIVE => ['value' => [self::group('g1', null)]]]);

        $this->assertSame([['id' => 'g1', 'displayName' => null]], $groups);
    }

    public function testTheTokenIsNeverSentOutsideGraph(): void
    {
        $groups = $this->groupsOf([
            self::TRANSITIVE => [
                'value' => [self::group('g1', 'Sales')],
                '@odata.nextLink' => 'https://attacker.example/collect',
            ],
        ]);

        $this->assertSame(['Sales'], array_column($groups, 'displayName'));
        $this->assertSame([self::TRANSITIVE], $this->requested);
    }

    public function testTheDirectMembershipsAreUsedWhenTheNestedOnesAreRefused(): void
    {
        $groups = $this->groupsOf([
            self::TRANSITIVE => new RuntimeException('Authorization_RequestDenied'),
            self::DIRECT => ['value' => [self::group('g1', 'Sales')]],
        ]);

        $this->assertSame(['Sales'], array_column($groups, 'displayName'));
        $this->assertSame([self::TRANSITIVE, self::DIRECT], $this->requested);
        $this->assertStringContainsString('using direct memberships: Authorization_RequestDenied', (string) file_get_contents($this->log));
    }

    public function testTheSignInFailsAsBeforeWhenGraphRefusesBothQueries(): void
    {
        $_GET = ['state' => 'state', 'code' => 'code'];
        $_SESSION['oauth2state'] = 'state';

        $result = $this->controller([
            self::GRAPH . 'me' => ['id' => 'abc-1', 'userPrincipalName' => 'jdoe@example.com'],
            self::TRANSITIVE => new RuntimeException('Authorization_RequestDenied'),
            self::DIRECT => new RuntimeException('Authorization_RequestDenied'),
        ])->callback();

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Authorization_RequestDenied', $result['message']);
    }
}

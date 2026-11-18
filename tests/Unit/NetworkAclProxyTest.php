<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * This file is part of TeamPass, distributed under the GNU GPL version 3 or later.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class NetworkAclProxyTest extends TestCase
{
    protected function setUp(): void
    {
        define('TEAMPASS_FUNCTIONS_ONLY', true);
        define('DB_PREFIX', 'acl_fixture_');
        require_once __DIR__ . '/../Fixtures/network_acl_db.php';
        require_once __DIR__ . '/../../app/sources/main.functions.php';
        $_SERVER['SERVER_ADDR'] = '192.0.2.100';
    }

    /** @return iterable<string, array{string, ?string}> */
    public static function forwardedChains(): iterable
    {
        yield 'single client' => ['203.0.113.66', '203.0.113.66'];
        yield 'forged allowed prefix' => ['192.168.6.10, 203.0.113.66', '203.0.113.66'];
        yield 'several trusted proxies' => ['192.168.6.10, 203.0.113.66, 192.0.2.11', '203.0.113.66'];
        yield 'untrusted intermediate stops traversal' => ['192.168.6.10, 198.51.100.20, 192.0.2.11', '198.51.100.20'];
        yield 'invalid earlier value is not trusted' => ['forged, 203.0.113.66', '203.0.113.66'];
        yield 'missing header' => ['', null];
        yield 'invalid nearest client' => ['192.168.6.10, invalid', null];
        yield 'empty nearest client' => ['192.168.6.10,', null];
        yield 'IPv6 nearest client' => ['192.168.6.10, 2001:db8::66', null];
        yield 'all hops are proxies' => ['192.0.2.12, 192.0.2.11', '192.0.2.11'];
        yield 'single trusted client' => ['192.0.2.12', '192.0.2.12'];
        yield 'invalid earlier trusted chain' => ['invalid, 192.0.2.11', null];
        yield 'IPv6 earlier trusted chain' => ['2001:db8::66, 192.0.2.11', null];
        yield 'oversized header' => [str_repeat(' ', 8193) . '203.0.113.66', null];
        yield 'excessive hops' => [str_repeat('192.0.2.11,', 101) . '203.0.113.66', null];
    }

    /** Only proxies to the right of the first untrusted hop may establish client identity. */
    #[DataProvider('forwardedChains')]
    public function testForwardedChainsCannotChooseAnAllowedPrefix(string $header, ?string $expected): void
    {
        self::assertSame($expected, teampassExtractClientIpv4FromHeader($header, ['192.0.2.0/24']));
    }

    /** Direct access and untrusted peers cannot select an address through forwarded headers. */
    public function testUntrustedHeadersAreIgnored(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.66';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.6.10';
        foreach (['direct', 'reverse_proxy'] as $mode) {
            $context = teampassGetClientIpForSecurity(['network_security_mode' => $mode,
                'network_trusted_proxies' => '192.0.2.0/24']);
            self::assertSame('203.0.113.66', $context['detected_ip']);
            self::assertFalse($context['trusted_proxy_used']);
        }
    }

    /** Broad proxy ranges must not collapse distinct clients into an unknown IP identity. */
    public function testAllTrustedChainsKeepDistinctClientIdentitiesWithoutAclFiltering(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
        $settings = ['network_security_mode' => 'reverse_proxy', 'network_trusted_proxies' => '192.0.2.0/24'];
        foreach (['192.0.2.11', '192.0.2.12'] as $client) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.99, ' . $client;
            self::assertSame($client, teampassGetClientIpForSecurity($settings)['detected_ip']);
        }
    }

    /** A forged prefix cannot defeat a whitelist or disguise a blacklisted actual client. */
    public function testBothPoliciesUseTheVerifiedClient(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.6.10, 203.0.113.66';
        $settings = ['network_security_mode' => 'reverse_proxy', 'network_trusted_proxies' => '192.0.2.0/24'];
        DB::$rules = [['type' => 'whitelist', 'rule_definition' => '192.168.6.0/24', 'enabled' => 1]];
        self::assertSame('whitelist_no_match', teampassGetNetworkAccessDecision($settings + ['network_whitelist_enabled' => 1])['reason']);
        DB::$rules = [['type' => 'blacklist', 'rule_definition' => '203.0.113.66', 'enabled' => 1]];
        self::assertSame('blacklist_match', teampassGetNetworkAccessDecision($settings + ['network_blacklist_enabled' => 1])['reason']);
    }

    /** A missing or malformed trusted-proxy header never falls back to its allowed peer IP. */
    public function testMissingHeadersCannotAuthorizeTheProxyItself(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
        DB::$rules = [['type' => 'whitelist', 'rule_definition' => '192.0.2.10', 'enabled' => 1]];
        $settings = ['network_security_mode' => 'reverse_proxy', 'network_trusted_proxies' => '192.0.2.10',
            'network_whitelist_enabled' => 1];
        foreach (['', 'invalid', '192.168.6.10,', '2001:db8::66'] as $header) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = $header;
            $decision = teampassGetNetworkAccessDecision($settings);
            self::assertSame('invalid_client_ip', $decision['reason']);
            self::assertNull($decision['context']['detected_ip']);
        }
    }

    /** X-Real-IP is a single address and must be overwritten by the trusted proxy. */
    public function testRealIpDoesNotAcceptForwardedChains(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
        $_SERVER['HTTP_X_REAL_IP'] = '203.0.113.66';
        $settings = ['network_security_mode' => 'reverse_proxy', 'network_trusted_proxies' => '192.0.2.10',
            'network_security_header' => 'x-real-ip'];
        self::assertSame('203.0.113.66', teampassGetClientIpForSecurity($settings)['detected_ip']);
        $_SERVER['HTTP_X_REAL_IP'] = '192.168.6.10, 203.0.113.66';
        self::assertNull(teampassGetClientIpForSecurity($settings)['detected_ip']);
    }
}

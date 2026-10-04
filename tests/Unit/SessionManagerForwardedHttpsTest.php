<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\SessionManager\SessionManager;

/**
 * The session cookie gets its Secure flag from X-Forwarded-Proto only when a declared
 * trusted proxy sent it (Settings → Network, Reverse proxy / WAF mode).
 *
 * Without it, a TeamPass behind a TLS terminator, the official Docker image included,
 * issued a session cookie without Secure: TeamPass registers no Symfony trusted proxy, so
 * Request::isSecure() only saw the plain-HTTP hop to the backend.
 */
class SessionManagerForwardedHttpsTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool}>
     */
    public static function decisionProvider(): array
    {
        return [
            'declared proxy, https' => ['10.0.0.5', 'https', 'reverse_proxy', '10.0.0.5', true],
            'proxy inside a CIDR block' => ['172.18.0.1', 'https', 'reverse_proxy', '172.16.0.0/12', true],
            'list separated by new lines and semicolons' => ['192.0.2.10', 'https', 'reverse_proxy', "10.0.0.1\n203.0.113.0/24; 192.0.2.10", true],
            'mode and scheme are case-insensitive' => ['10.0.0.5', 'HTTPS', ' Reverse_Proxy ', '10.0.0.5', true],
            'first value of a proxy chain' => ['10.0.0.5', 'https, http', 'reverse_proxy', '10.0.0.5', true],
            'value accepted by Symfony' => ['10.0.0.5', 'on', 'reverse_proxy', '10.0.0.5', true],
            'direct mode ignores the header' => ['10.0.0.5', 'https', 'direct', '10.0.0.5', false],
            'empty mode ignores the header' => ['10.0.0.5', 'https', '', '10.0.0.5', false],
            'undeclared sender' => ['198.51.100.7', 'https', 'reverse_proxy', '10.0.0.5', false],
            'sender outside the CIDR block' => ['10.0.1.5', 'https', 'reverse_proxy', '10.0.0.0/24', false],
            'no trusted proxy declared' => ['10.0.0.5', 'https', 'reverse_proxy', '', false],
            'plain http forwarded' => ['10.0.0.5', 'http', 'reverse_proxy', '10.0.0.5', false],
            'header absent' => ['10.0.0.5', '', 'reverse_proxy', '10.0.0.5', false],
            'https only in a later hop' => ['10.0.0.5', 'http, https', 'reverse_proxy', '10.0.0.5', false],
            'invalid rules are ignored' => ['10.0.0.5', 'https', 'reverse_proxy', '10.0.0.5/33, 10.0.0.5/x, proxy.local', false],
            'IPv6 sender is not supported, like the client-IP resolver' => ['2001:db8::1', 'https', 'reverse_proxy', '2001:db8::/32', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('decisionProvider')]
    public function testForwardedHttpsDecision(
        string $remoteAddr,
        string $forwardedProto,
        string $mode,
        string $trustedProxies,
        bool $expected
    ): void {
        $this->assertSame(
            $expected,
            SessionManager::isTrustedForwardedHttps($remoteAddr, $forwardedProto, $mode, $trustedProxies)
        );
    }

    public function testSessionManagerCopiesAreByteIdentical(): void
    {
        $root = dirname(__DIR__, 2);
        $vendor = $root . '/app/vendor/teampassclasses/sessionmanager/src/SessionManager.php';
        $includes = $root . '/app/includes/libraries/teampassclasses/sessionmanager/src/SessionManager.php';

        $this->assertSame(
            hash_file('sha256', $vendor),
            hash_file('sha256', $includes),
            'The two SessionManager copies have diverged — edit both identically (see CLAUDE.md). '
            . 'Only the vendor copy is autoloaded.'
        );
    }
}

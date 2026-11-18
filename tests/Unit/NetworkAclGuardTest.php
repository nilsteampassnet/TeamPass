<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * This file is part of TeamPass, distributed under the GNU GPL version 3 or later.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NetworkAclGuardTest extends TestCase
{
    /** Exercise real HTTP headers that are unavailable under the CLI SAPI. */
    private function httpRequest(array $input): array
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertIsResource($socket, $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $log = tempnam(sys_get_temp_dir(), 'tp-acl-http-');
        $server = proc_open([PHP_BINARY, '-S', $address, '-t', __DIR__ . '/../Fixtures'],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes);
        self::assertIsResource($server);
        fclose($pipes[0]);
        try {
            $ready = false;
            for ($attempt = 0; $attempt < 100; ++$attempt) {
                $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
                if (is_resource($connection)) {
                    fclose($connection);
                    $ready = true;
                    break;
                }
                usleep(20000);
            }
            self::assertTrue($ready, (string) file_get_contents($log));
            $body = file_get_contents('http://' . $address . '/network_acl_guard_request.php?'
                . http_build_query(['input' => json_encode($input, JSON_THROW_ON_ERROR)]), false,
                stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]));
            self::assertIsString($body);
            self::assertJson($body, (string) file_get_contents($log));
            return ['headers' => $http_response_header, 'body' => json_decode($body, true, 32, JSON_THROW_ON_ERROR)];
        } finally {
            proc_terminate($server);
            proc_close($server);
            unlink($log);
        }
    }

    /** @return iterable<string, array{string, bool, int}> */
    public static function refusalResponses(): iterable
    {
        yield 'API denied' => ['api', false, 403];
        yield 'API unavailable' => ['api', true, 503];
        yield 'AJAX denied' => ['identify', false, 403];
    }

    /** API errors retain Problem Details and never issue a session cookie; AJAX stays compatible. */
    #[DataProvider('refusalResponses')]
    public function testHttpRefusalContracts(string $route, bool $unavailable, int $status): void
    {
        $result = $this->httpRequest(['route' => $route, 'storage_failure' => $unavailable]);
        $headers = strtolower(implode("\n", $result['headers']));
        self::assertStringContainsString(' ' . $status . ' ', $result['headers'][0]);
        self::assertSame('network_access_denied', $result['body']['code']);
        if ($route === 'api') {
            self::assertStringContainsString('content-type: application/problem+json', $headers);
            self::assertStringNotContainsString('set-cookie:', $headers);
            self::assertSame('about:blank', $result['body']['type']);
            self::assertSame($status === 503 ? 'Service Unavailable' : 'Forbidden', $result['body']['title']);
            self::assertSame($status, $result['body']['status']);
            self::assertNotEmpty($result['body']['detail']);
            self::assertSame($result['body']['detail'], $result['body']['error']);
        } else {
            self::assertStringContainsString('content-type: application/json', $headers);
            self::assertSame(['error', 'code'], array_keys($result['body']));
        }
        self::assertStringNotContainsString('synthetic', $result['body']['error']);
        self::assertStringNotContainsString('database-credential-canary', $result['body']['error']);
    }

    /** Execute the terminating production guard in its own process. */
    private function request(array $input): array
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/../Fixtures/network_acl_guard_request.php',
            json_encode($input, JSON_THROW_ON_ERROR)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        self::assertSame('', $errors);
        return json_decode($output, true, 32, JSON_THROW_ON_ERROR);
    }

    /** @return iterable<string, array{string}> */
    public static function protectedRoutes(): iterable
    {
        foreach (['login', 'identify', 'items', 'admin', 'download', 'api'] as $route) {
            yield $route => [$route];
        }
    }

    /** A page session and misleading public-route query parameters do not exempt an action. */
    #[DataProvider('protectedRoutes')]
    public function testExcludedClientsCannotReachProtectedActions(string $route): void
    {
        $result = $this->request(['route' => $route]);
        self::assertSame(403, $result['status']);
        self::assertStringNotContainsString('ACTION_REACHED', $result['body']);
        self::assertStringNotContainsString('192.168.', $result['body']);
        self::assertStringNotContainsString('synthetic-session-key', $result['body']);
        if ($route !== 'login') {
            self::assertSame('network_access_denied', json_decode($result['body'], true, 32, JSON_THROW_ON_ERROR)['code']);
        }
    }

    /** An allowed client reaches the handler without changing its application permissions. */
    #[DataProvider('protectedRoutes')]
    public function testAllowedClientsReachTheApplicationGuard(string $route): void
    {
        $result = $this->request(['route' => $route, 'ip' => '192.168.6.66']);
        self::assertSame(200, $result['status']);
        self::assertSame('ACTION_REACHED', $result['body']);
    }

    /** Keep required migrations and the terminating public recipient dispatch usable. */
    public function testOnlyExplicitSetupAndRecipientExceptionsSkipThePolicy(): void
    {
        foreach ([['route' => 'upgrade'], ['route' => 'login', 'recipient' => true]] as $input) {
            $result = $this->request($input + ['storage_failure' => true]);
            self::assertSame(200, $result['status']);
            self::assertSame('ACTION_REACHED', $result['body']);
        }
        $result = $this->request(['route' => 'items', 'recipient' => true]);
        self::assertSame(403, $result['status']);
        self::assertStringNotContainsString('ACTION_REACHED', $result['body']);
    }

    /** Storage errors cannot run an action, while disabled policies require no table. */
    public function testUnavailableStorageAndDisabledPoliciesHaveDifferentOutcomes(): void
    {
        $result = $this->request(['route' => 'identify', 'storage_failure' => true]);
        self::assertSame(503, $result['status']);
        self::assertStringNotContainsString('ACTION_REACHED', $result['body']);
        $result = $this->request(['route' => 'identify', 'storage_failure' => true, 'disabled' => true]);
        self::assertSame(200, $result['status']);
        self::assertSame('ACTION_REACHED', $result['body']);
    }

    /** API preflight requests are subject to the same enabled network policy. */
    public function testPreflightCannotSkipNetworkRestrictions(): void
    {
        self::assertSame(403, $this->request(['route' => 'api', 'method' => 'OPTIONS'])['status']);
    }

    /** The common bootstrap must enforce the policy before returning to any controller. */
    public function testRuntimeWiresTheGuardAndCliFunctionsStayIndependent(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/main.functions.php');
        $runtime = substr($source, strpos($source, "if (PHP_SAPI !== 'cli' ||"), strpos($source, 'function isHex(') - strpos($source, "if (PHP_SAPI !== 'cli' ||"));
        self::assertStringContainsString("if (PHP_SAPI !== 'cli')", $runtime);
        self::assertStringContainsString('teampassEnforceNetworkAccess($SETTINGS)', $runtime);
        $front = (string) file_get_contents(__DIR__ . '/../../public/index.php');
        $start = strpos($front, "if (isset(\$_GET['otv'])");
        $dispatch = substr($front, $start, strpos($front, '// initialise CSRFGuard', $start) - $start);
        self::assertStringContainsString("define('TEAMPASS_PUBLIC_SECURE_SEND', true)", $dispatch);
        self::assertStringContainsString("require_once TEAMPASS_APP . '/core/otv.php'", $dispatch);
        self::assertStringContainsString('exit;', $dispatch);
    }
}

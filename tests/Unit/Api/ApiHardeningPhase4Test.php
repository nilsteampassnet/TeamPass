<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: API hardening phase 4 (rate limiter, session-per-token, logout,
 * api_require_https) — static regression guards.
 *
 * Locked invariants:
 * - api_require_https defaults: '1' on NEW installs, '0' on upgrades (existing
 *   HTTP integrations must keep working after an upgrade);
 * - api_rate_limit_per_minute defaults: '120' on new installs, '0' on upgrades;
 * - one api_sessions row per issued JWT (jti), with per-request revocation check;
 * - get_user_keys is jti-aware with a legacy single-row fallback;
 * - rate limiter runs after JWT validation and returns 429 + Retry-After;
 * - both admin options are exposed on the Settings > API page.
 */
class ApiHardeningPhase4Test extends TestCase
{
    private function readSource(string $relativePath): string
    {
        $path = __DIR__ . '/../../..' . $relativePath;
        self::assertFileExists($path, "Source file '$relativePath' not found");
        $content = file_get_contents($path);
        self::assertIsString($content);
        return $content;
    }

    public function testInstallDefaultsAreStrict(): void
    {
        $src = $this->readSource('/public/install/install-steps/run.step5.php');

        self::assertStringContainsString("array('admin', 'api_require_https', '1')", $src, 'New installs must require HTTPS for the API by default');
        self::assertStringContainsString("array('admin', 'api_rate_limit_per_minute', '120')", $src, 'New installs must enable the API rate limiter by default');
        self::assertStringContainsString('api_sessions', $src);
        self::assertStringContainsString('api_rate_limit', $src);
    }

    public function testUpgradeDefaultsPreserveExistingUsage(): void
    {
        $src = $this->readSource('/public/install/upgrade_run_3.2.0.php');

        self::assertStringContainsString("VALUES ('admin', 'api_require_https', '0')", $src, 'Upgrades must NOT enforce HTTPS silently — existing HTTP integrations would break');
        self::assertStringContainsString("VALUES ('admin', 'api_rate_limit_per_minute', '0')", $src, 'Upgrades must NOT throttle existing API clients silently');
        self::assertStringContainsString('api_sessions', $src);
        self::assertStringContainsString('api_rate_limit', $src);
    }

    public function testSessionPerTokenWiring(): void
    {
        $authModel = $this->readSource('/app/api/Model/AuthModel.php');
        // One api_sessions row inserted per issued JWT, keyed by jti
        self::assertStringContainsString("DB::insert(prefixTable('api_sessions')", $authModel);
        self::assertStringContainsString("'jti' => \$jti", $authModel);

        $jwtUtils = $this->readSource('/app/api/inc/jwt_utils.php');
        // jti-aware key lookup with legacy fallback
        self::assertStringContainsString('function get_user_keys(int $userId, string $keyTempo, ?string $jti = null)', $jwtUtils);
        self::assertStringContainsString("prefixTable('api_sessions')", $jwtUtils);

        $index = $this->readSource('/app/api/index.php');
        // Per-request revocation check on every endpoint
        self::assertStringContainsString("SELECT revoked_at, expires_at FROM ' . prefixTable('api_sessions')", $index);
    }

    public function testLogoutEndpointExists(): void
    {
        $controller = $this->readSource('/app/api/Controller/Api/AuthController.php');
        self::assertStringContainsString('public function logoutAction(array $userData): void', $controller);
        self::assertStringContainsString("'revoked_at' => time()", $controller);

        $index = $this->readSource('/app/api/index.php');
        self::assertStringContainsString("\$controller === 'auth' && \$action === 'logout'", $index);
    }

    public function testRateLimiterWiring(): void
    {
        $bootstrap = $this->readSource('/app/api/inc/bootstrap.php');
        self::assertStringContainsString('function teampassApiRateLimitCheck(', $bootstrap);
        self::assertStringContainsString("prefixTable('api_rate_limit')", $bootstrap);

        $index = $this->readSource('/app/api/index.php');
        self::assertStringContainsString('teampassApiRateLimitCheck(', $index);
        self::assertStringContainsString('HTTP/1.1 429 Too Many Requests', $index);
        self::assertStringContainsString("header('Retry-After: '", $index);
    }

    public function testHttpsGateHonoursForwardedProto(): void
    {
        $index = $this->readSource('/app/api/index.php');
        self::assertStringContainsString("api_require_https", $index);
        self::assertStringContainsString('HTTP_X_FORWARDED_PROTO', $index, 'TLS-terminating reverse proxies must not be locked out');
        self::assertStringContainsString('HTTPS is required for API requests', $index);
    }

    public function testAdminOptionsExposed(): void
    {
        $page = $this->readSource('/app/pages/api.php');
        self::assertStringContainsString("id='api_require_https'", $page);
        self::assertStringContainsString("id='api_rate_limit_per_minute'", $page);
    }

    public function testProfileSessionsManagement(): void
    {
        $queries = $this->readSource('/app/sources/users.queries.php');
        self::assertStringContainsString("case 'list_api_sessions':", $queries);
        self::assertStringContainsString("case 'revoke_api_session':", $queries);
        // Key material columns must never be returned to the browser
        self::assertDoesNotMatchRegularExpression(
            '/SELECT[^;]*(session_aes_key|encrypted_private_key)[^;]*FROM\s*\'\s*\.\s*prefixTable\(\'api_sessions\'\)/s',
            $queries,
            'list_api_sessions must not expose key material'
        );
    }
}

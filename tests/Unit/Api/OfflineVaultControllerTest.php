<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      OfflineVaultControllerTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\ConfigManager\ConfigManager;

/**
 * The real OfflineVaultController: the HTTP contract of GET /offlineVault/policy.
 *
 * The policy decisions are covered by OfflineVaultApiTest. What matters here is that every
 * decision, refusals included, reaches the extension as a 200 — it erases its copy on
 * allowed=false, so an error status would leave the copy in place.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class OfflineVaultControllerTest extends TestCase
{
    private const USER = ['id' => 7, 'is_admin' => 0, 'is_read_only' => 0, 'roles' => '3;5', 'allowed_to_read' => 1];
    private const ENABLED = ['offline_vault_enabled' => '1'];

    protected function setUp(): void
    {
        if (!defined('API_ROOT_PATH')) {
            define('API_ROOT_PATH', dirname(__DIR__, 3) . '/app/api');
        }
        require_once __DIR__ . '/../../Fixtures/offline_vault_config_manager.php';
        require_once __DIR__ . '/../../../app/api/Controller/Api/BaseController.php';
        require_once __DIR__ . '/../../../app/api/Controller/Api/OfflineVaultController.php';
    }

    /**
     * Run policyAction() and capture what it sends.
     *
     * @param SymfonyRequest|null $request Synthetic request, null to read the real globals
     * @param array|null          $settings Settings, null to read them through ConfigManager
     * @return array{status: int, headers: array, body: array, activity: array}
     */
    private function call(?SymfonyRequest $request, ?array $settings = self::ENABLED, array $user = self::USER): array
    {
        $controller = new class ($request, $settings) extends OfflineVaultController {
            public array $headers = [];
            public string $output = '';
            public array $activity = [];

            public function __construct(private ?SymfonyRequest $testRequest, private ?array $testSettings)
            {
            }

            protected function settings(): array
            {
                return $this->testSettings ?? parent::settings();
            }

            protected function currentRequest(): SymfonyRequest
            {
                return $this->testRequest ?? parent::currentRequest();
            }

            // Appended, not replaced: a second answer after a refusal must show up.
            protected function sendOutput($data, array $httpHeaders = []): void
            {
                $this->output .= (string) $data;
                $this->headers = array_merge($this->headers, $httpHeaders);
            }

            protected function markApiFunctionalActivity(array $userData): void
            {
                $this->activity[] = (int) ($userData['id'] ?? 0);
            }
        };
        $controller->policyAction($user);
        $statusLines = array_values(preg_grep('/^HTTP\/1\.1 \d{3} /', $controller->headers));
        self::assertCount(1, $statusLines, 'one answer');

        return [
            'status' => (int) substr($statusLines[0], 9, 3),
            'headers' => $controller->headers,
            'body' => json_decode($controller->output, true, 512, JSON_THROW_ON_ERROR),
            'activity' => $controller->activity,
        ];
    }

    private static function request(string $method): SymfonyRequest
    {
        return SymfonyRequest::create('https://vault.example.com/api/index.php/v1/offlineVault/policy', $method);
    }

    public function testGetAnswersTheSettingsAsAPolicy(): void
    {
        $response = $this->call(self::request('GET'), self::ENABLED + [
            'offline_vault_roles' => '[5]',
            'offline_vault_max_retention_days' => '30',
            'offline_vault_totp_allowed' => '1',
            'offline_vault_personal_folders' => '0',
            'offline_vault_min_sync_minutes' => '120',
        ]);

        self::assertSame(200, $response['status']);
        self::assertContains('Content-Type: application/json', $response['headers']);
        self::assertContains('Cache-Control: no-store', $response['headers']);
        // The wire contract, member order included (docs/api/offline-vault.md, openapi.json).
        self::assertSame([
            'enabled' => true,
            'allowed' => true,
            'reason' => null,
            'retention_days' => ['max' => 30, 'default' => 7],
            'totp_allowed' => true,
            'personal_folders_allowed' => false,
            'min_sync_interval_minutes' => 120,
        ], $response['body']);
    }

    /** @return array<string, array{array, array, string}> */
    public static function refusals(): array
    {
        return [
            'feature off' => [[], self::USER, 'disabled'],
            'administrator' => [self::ENABLED, ['is_admin' => 1] + self::USER, 'admin'],
            'role outside the list' => [self::ENABLED + ['offline_vault_roles' => '[1,2]'], self::USER, 'role_not_allowed'],
        ];
    }

    #[DataProvider('refusals')]
    public function testRefusalsAreAPolicyNotAnError(array $settings, array $user, string $reason): void
    {
        $response = $this->call(self::request('GET'), $settings, $user);

        self::assertSame(200, $response['status']);
        self::assertContains('Content-Type: application/json', $response['headers']);
        self::assertFalse($response['body']['allowed']);
        self::assertSame($reason, $response['body']['reason']);
    }

    public function testOtherMethodsAreRefusedWithoutAPolicy(): void
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->call(self::request($method));

            self::assertSame(405, $response['status'], $method);
            self::assertContains('Allow: GET', $response['headers'], $method);
            self::assertContains('Content-Type: application/problem+json; charset=UTF-8', $response['headers'], $method);
            self::assertSame(405, $response['body']['status'], $method);
            self::assertArrayNotHasKey('allowed', $response['body'], $method);
        }
    }

    public function testReadingThePolicyIsNotUserActivity(): void
    {
        // The extension polls the policy on its own schedule: counting it would keep an
        // unattended account from ever being flagged inactive.
        self::assertSame([], $this->call(self::request('GET'))['activity']);
    }

    public function testDefaultSeamsReadTheRealRequestAndSettings(): void
    {
        ConfigManager::$settings = self::ENABLED + ['offline_vault_max_retention_days' => '3'];

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $response = $this->call(null, null);
        self::assertSame(200, $response['status']);
        self::assertTrue($response['body']['allowed']);
        self::assertSame(['max' => 3, 'default' => 3], $response['body']['retention_days']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        self::assertSame(405, $this->call(null, null)['status']);
    }
}

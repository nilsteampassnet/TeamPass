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
 * @file      OfflineVaultApiTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\TestCase;

/**
 * GET /offlineVault/policy decides whether the browser extension may keep an encrypted copy of
 * a user's items. The extension erases its copy on allowed=false, so the policy must fail
 * closed: a missing setting, an administrator or a role outside the list all refuse.
 */
final class OfflineVaultApiTest extends TestCase
{
    private const USER = ['id' => 7, 'is_admin' => 0, 'is_read_only' => 0, 'roles' => '3;5', 'allowed_to_read' => 1];

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../app/sources/offline_vault_api_logic.php';
    }

    private function source(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testMissingSettingsReadAsDisabled(): void
    {
        $policy = offlineVaultApiPolicy([], self::USER);

        self::assertFalse($policy['enabled']);
        self::assertFalse($policy['allowed']);
        self::assertSame('disabled', $policy['reason']);
        self::assertFalse($policy['totp_allowed']);
        self::assertTrue($policy['personal_folders_allowed']);
        self::assertSame(['max' => 14, 'default' => 7], $policy['retention_days']);
        self::assertSame(60, $policy['min_sync_interval_minutes']);
    }

    public function testOnlyTheValueOneEnables(): void
    {
        foreach (['0', '2', 'yes', ''] as $value) {
            self::assertFalse(offlineVaultApiPolicy(['offline_vault_enabled' => $value], self::USER)['allowed'], $value);
        }
        $policy = offlineVaultApiPolicy(['offline_vault_enabled' => '1'], self::USER);
        self::assertTrue($policy['allowed']);
        self::assertNull($policy['reason']);
    }

    public function testAdministratorsAreRefused(): void
    {
        $policy = offlineVaultApiPolicy(['offline_vault_enabled' => '1'], ['is_admin' => 1] + self::USER);

        self::assertTrue($policy['enabled']);
        self::assertFalse($policy['allowed']);
        self::assertSame('admin', $policy['reason']);
    }

    public function testReadOnlyAccountsMayKeepACopy(): void
    {
        self::assertTrue(offlineVaultApiPolicy(['offline_vault_enabled' => '1'], ['is_read_only' => 1] + self::USER)['allowed']);
    }

    public function testRoleRestriction(): void
    {
        $settings = ['offline_vault_enabled' => '1'];

        // Empty list, as saved by an emptied select2: every role.
        foreach (['', '[]'] as $roles) {
            self::assertTrue(offlineVaultApiPolicy($settings + ['offline_vault_roles' => $roles], self::USER)['allowed']);
        }
        self::assertTrue(offlineVaultApiPolicy($settings + ['offline_vault_roles' => '[1,5]'], self::USER)['allowed']);

        $refused = offlineVaultApiPolicy($settings + ['offline_vault_roles' => '[1,2]'], self::USER);
        self::assertFalse($refused['allowed']);
        self::assertSame('role_not_allowed', $refused['reason']);

        // A caller without any role is outside every non-empty list.
        self::assertFalse(offlineVaultApiPolicy($settings + ['offline_vault_roles' => '[1]'], ['roles' => ''] + self::USER)['allowed']);
    }

    public function testLimitsAreClamped(): void
    {
        self::assertSame(1, offlineVaultRetentionDays('0'));
        self::assertSame(365, offlineVaultRetentionDays('9999'));
        self::assertSame(14, offlineVaultRetentionDays('abc'));
        self::assertSame(15, offlineVaultMinSyncMinutes('1'));
        self::assertSame(1440, offlineVaultMinSyncMinutes('100000'));
        self::assertSame(60, offlineVaultMinSyncMinutes(null));

        // The default never exceeds what the administrator granted.
        $short = offlineVaultApiPolicy(['offline_vault_max_retention_days' => '3'], self::USER);
        self::assertSame(['max' => 3, 'default' => 3], $short['retention_days']);
    }

    public function testFreshInstallAndUpgradeSeedTheFeatureOff(): void
    {
        $install = $this->source('public/install/install-steps/run.step5.php');
        self::assertStringContainsString("array('admin', 'offline_vault_enabled', '0'),", $install);

        $upgrade = $this->source('public/install/upgrade_run_3.2.4.php');
        $section = substr($upgrade, (int) strpos($upgrade, '//--->BEGIN 3.2.4'),
            (int) strpos($upgrade, '//--->END 3.2.4') - (int) strpos($upgrade, '//--->BEGIN 3.2.4'));
        self::assertStringContainsString("'offline_vault_enabled' => '0',", $section);
        self::assertStringContainsString('INSERT IGNORE INTO', $section);
        // Recorded before the upgrade timestamp, so a failure leaves the upgrade replayable.
        self::assertLessThan(strpos($section, "'upgrade_timestamp'"), strpos($section, "'offline_vault_enabled'"));

        foreach (['offline_vault_roles', 'offline_vault_max_retention_days', 'offline_vault_totp_allowed',
            'offline_vault_personal_folders', 'offline_vault_min_sync_minutes'] as $key) {
            self::assertStringContainsString("'" . $key . "'", $install, $key);
            self::assertStringContainsString("'" . $key . "'", $section, $key);
        }
    }

    public function testSavedLimitsGoThroughTheSameClamps(): void
    {
        $queries = $this->source('app/sources/admin.queries.php');
        self::assertStringContainsString('offlineVaultRetentionDays($post_value)', $queries);
        self::assertStringContainsString('offlineVaultMinSyncMinutes($post_value)', $queries);
    }

    public function testRouteNeedsTheReadRight(): void
    {
        $bootstrap = $this->source('app/api/inc/bootstrap.php');
        self::assertStringContainsString('function checkOfflineVaultRights(', $bootstrap);
        self::assertStringContainsString("\$controller === 'offlineVault'", $this->source('app/api/index.php'));

        // bootstrap.php has side effects: load the function definition alone.
        if (function_exists('checkOfflineVaultRights') === false) {
            $start = (int) strpos($bootstrap, 'function checkOfflineVaultRights(');
            eval(substr($bootstrap, $start, (int) strpos($bootstrap, "\n}\n", $start) + 3 - $start));
        }
        self::assertTrue(checkOfflineVaultRights(['allowed_to_read' => 1], 'policy'));
        self::assertFalse(checkOfflineVaultRights(['allowed_to_read' => 0], 'policy'));
        self::assertFalse(checkOfflineVaultRights(['allowed_to_read' => 1], 'settings'));
    }

    public function testEnglishAndFrenchStringsExist(): void
    {
        $keys = ['offline_vault_title', 'offline_vault_title_tip', 'offline_vault_enabled', 'offline_vault_enabled_tip',
            'offline_vault_roles', 'offline_vault_roles_tip', 'offline_vault_max_retention_days',
            'offline_vault_max_retention_days_tip', 'offline_vault_min_sync_minutes', 'offline_vault_min_sync_minutes_tip',
            'offline_vault_personal_folders', 'offline_vault_personal_folders_tip', 'offline_vault_totp_allowed',
            'offline_vault_totp_allowed_tip'];
        foreach (['english', 'french'] as $language) {
            $strings = require dirname(__DIR__, 2) . '/app/includes/language/' . $language . '.php';
            foreach ($keys as $key) {
                self::assertNotSame('', trim((string) ($strings[$key] ?? '')), $language . ': ' . $key);
            }
        }
    }
}

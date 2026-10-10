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
 * @file      SecureSendAuditSchemaTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/secure_send_audit.php';

/** Keep the shared migration contract and its two installer entry points connected. */
class SecureSendAuditSchemaTest extends TestCase
{
    public function testSchemaUsesTypedMetadataAndNoCascadingForeignKeys(): void
    {
        $sql = secureSendAuditSchemaSql('custom_secure_send_audit');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `custom_secure_send_audit`', $sql);
        self::assertStringContainsString('ENGINE=InnoDB', $sql);
        self::assertStringContainsString('`actor_id` INT UNSIGNED NULL', $sql);
        self::assertStringContainsString('(`originator`, `occurred_at`)', $sql);
        self::assertStringContainsString('(`event`, `occurred_at`)', $sql);
        self::assertStringContainsString('(`send_id`, `id`)', $sql);
        self::assertStringContainsString('KEY `idx_retention_period` (`occurred_at`, `id`)', $sql);
        foreach (['FOREIGN KEY', 'encrypted', 'protected_key', 'password', '`code`', '`url`', '`label`'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $sql);
        }
    }

    public function testTableIdentifierCannotIntroduceSql(): void
    {
        $this->expectException(InvalidArgumentException::class);
        secureSendAuditSchemaSql('audit`; DROP TABLE users; --');
    }

    public function testFreshAndUpgradePathsUseTheSameReplayableDdl(): void
    {
        $root = dirname(__DIR__, 2);
        $install = (string) file_get_contents($root . '/public/install/install-steps/run.step5.php');
        $steps = (string) file_get_contents($root . '/public/install/install-steps/install.js');
        $upgrade = (string) file_get_contents($root . '/public/install/upgrade_run_3.2.3.php');
        $hotfix = (string) file_get_contents($root . '/public/install/upgrade_run_3.2.2.php');
        self::assertStringContainsString('private function secure_send_audit()', $install);
        self::assertStringContainsString("secureSendAuditSchemaSql(\$this->inputData['tablePrefix'] . 'secure_send_audit')", $install);
        self::assertStringContainsString("action: 'secure_send_audit'", $steps);
        self::assertSame(1, preg_match("/id: '(check\\d+)', action: 'secure_send_audit'/", $steps, $auditStep));
        self::assertSame(1, substr_count($steps, "id: '" . $auditStep[1] . "'"), 'The audit installer step must have its own identifier.');
        $ddl = strpos($upgrade, "secureSendAuditSchemaSql(prefixTable('secure_send_audit'))");
        self::assertIsInt($ddl);
        self::assertLessThan(strpos($upgrade, '// Save upgrade timestamp'), $ddl);
        self::assertStringContainsString("require_once TEAMPASS_ROOT . '/app/sources/secure_send_audit.php'", $upgrade);
        self::assertStringNotContainsString('secureSendAuditSchemaSql(', $hotfix);
        require_once $root . '/app/config/include.php';
        // Reusing the previous audit floor could skip the journal on instances upgraded to 3.2.2.8 since then.
        self::assertGreaterThan(1791351029, (int) UPGRADE_MIN_DATE);
    }
}

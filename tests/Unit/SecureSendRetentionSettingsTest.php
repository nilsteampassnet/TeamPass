<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * TeamPass is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See <https://www.gnu.org/licenses/> for the GNU General Public License.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

namespace TeamPass\Tests\SecureSendRetentionSettings;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TeampassClasses\Encryption\Encryption;

require_once __DIR__ . '/../../app/sources/secure_send_retention.php';
require_once __DIR__ . '/../../app/sources/secret_settings_logic.php';

/** Observe only persistence calls made by the real save_option_change handler. */
final class DB
{
    public static array $writes = [];
    public static array $queries = [];
    public static bool $existing = false;

    /** Record lookup predicates without booting a configured vault. */
    public static function query(string $sql, mixed ...$values): array
    {
        self::$queries[] = [$sql, $values];
        return [];
    }
    /** Exercise both insertion and update paths. */
    public static function count(): int { return self::$existing ? 1 : 0; }
    /** Record the new policy. */
    public static function insert(string $table, array $row): void { self::$writes[] = [$table, $row]; }
    /** Record the updated policy and its scope. */
    public static function update(string $table, array $row, string $where, mixed ...$values): void
    {
        self::$writes[] = [$table, $row, $where, $values];
    }
}

/** Observe the existing configuration invalidation rather than use live APCu. */
final class ConfigManager
{
    public static int $invalidations = 0;
    /** Count invalidations after successful writes only. */
    public static function invalidateCache(): void { ++self::$invalidations; }
}

/** Provide the real exchange wrapper's minimal session contract. */
final class SessionManager
{
    public static bool $encrypted = false;
    /** Return an isolated session adapter. */
    public static function getSession(): object
    {
        return new class {
            public function get(string $key): string|int
            {
                return $key === 'encryptClientServer' ? (int) SessionManager::$encrypted : 'session';
            }
        };
    }
}

/** Use a non-default prefix without installation secrets. */
function prefixTable(string $table): string { return 'retention_settings_' . $table; }

/** Execute the production setting handler, not a copy of its validation decisions. */
class SecureSendRetentionSettingsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/main.functions.php'));
        foreach (['teampassDecodeJsonPayload', 'prepareExchangedData'] as $function) {
            $start = strpos($source, 'function ' . $function . '(');
            $end = strpos($source, "\n}", $start) + 2;
            eval('namespace ' . __NAMESPACE__ . '; use TeampassClasses\\Encryption\\Encryption;' . substr($source, $start, $end - $start));
        }
    }

    private function request(mixed $value, bool $encrypted = false, string $key = 'session'): array
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/admin.queries.php');
        $start = strpos($source, "case 'save_option_change':");
        $end = strpos($source, "case 'get_values_for_statistics':", $start);
        $handler = substr($source, $start, $end - $start);
        $handler = str_replace(["require_once 'main.functions.php';", "require_once __DIR__ . '/secure_send_retention.php';"], '', $handler);
        SessionManager::$encrypted = $encrypted;
        $session = SessionManager::getSession();
        $lang = new class {
            public function get(string $key): string { return $key; }
        };
        $SETTINGS = [];
        $post_key = $key;
        $post_data = json_encode(['field' => 'secure_send_audit_retention_days', 'value' => $value], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        if ($encrypted) {
            $post_data = Encryption::encrypt($post_data, 'session');
        }
        DB::$queries = DB::$writes = [];
        ConfigManager::$invalidations = 0;
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . '; use InvalidArgumentException; switch (\'save_option_change\') {' . $handler . '}');
            return prepareExchangedData((string) ob_get_contents(), 'decode');
        } finally {
            ob_end_clean();
        }
    }

    public function testValidPoliciesUseExistingPersistenceAndInvalidateCache(): void
    {
        foreach ([false, true] as $encrypted) {
            foreach ([false, true] as $existing) {
                DB::$existing = $existing;
                foreach ([0, '30', '36500', ' 90 '] as $value) {
                    self::assertFalse($this->request($value, $encrypted)['error']);
                    self::assertCount(1, DB::$writes);
                    self::assertSame((string) (int) $value, DB::$writes[0][1]['valeur']);
                    self::assertSame('retention_settings_misc', DB::$writes[0][0]);
                    self::assertSame(1, ConfigManager::$invalidations);
                }
            }
        }
    }

    public function testInvalidRawValuesAndWrongSessionKeyCannotWrite(): void
    {
        foreach ([false, true] as $encrypted) {
            foreach ([null, false, true, [], ['30'], 1.0, -1, '-1', '', '01', '1e2', '36501'] as $value) {
                self::assertTrue($this->request($value, $encrypted)['error']);
                self::assertSame([], DB::$queries);
                self::assertSame([], DB::$writes);
                self::assertSame(0, ConfigManager::$invalidations);
            }
            self::assertTrue($this->request('30', $encrypted, 'wrong-key')['error']);
            self::assertSame([], DB::$writes);
        }
    }

    public function testExistingEndpointKeepsSessionAndAdminChecksAheadOfSave(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/admin.queries.php');
        self::assertStringContainsString("\$checkUserAccess->checkSession() === false || \$checkUserAccess->userAccessPage('admin') === false", $source);
        self::assertLessThan(strpos($source, "case 'save_option_change':"), strpos($source, "\$checkUserAccess->userAccessPage('admin')"));
    }
}

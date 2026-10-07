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
 * Execute the live activity handler with explicit database and exchange adapters.
 *
 * @file      AdminActivityEndpointTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

namespace TeamPass\Tests\AdminActivityEndpoint;

use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;
use TeampassClasses\Encryption\Encryption;

require_once __DIR__ . '/../../app/sources/admin_activity_logic.php';

/** Record actual handler queries without requiring an installation. */
final class DB
{
    public static array $queries = [];

    /** Return an empty page while retaining SQL and bound values. */
    public static function query(string $sql, ...$values): array
    {
        self::$queries[] = [$sql, $values];
        return [];
    }

    /** Return an observable count to distinguish count queries. */
    public static function queryFirstField(string $sql, ...$values): int
    {
        self::$queries[] = [$sql, $values];
        return 7;
    }
}

/** Keep table names independent of any real installation. */
function prefixTable(string $table): string
{
    return 'activity_test_' . $table;
}

/** Supply only the session values read by the real exchange adapter. */
final class SessionManager
{
    public static bool $encrypted = false;

    /** Return a session fixture without booting an installed vault. */
    public static function getSession(): object
    {
        return new class {
            public function get(string $key) { return $key === 'encryptClientServer' ? (int) SessionManager::$encrypted : 'session'; }
        };
    }
}

/** Exercise the production case body rather than reproducing its query decisions. */
final class AdminActivityEndpointTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Execute the production exchange wrapper and JSON-envelope decoder with the real cipher.
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/main.functions.php');
        foreach (['teampassDecodeJsonPayload', 'prepareExchangedData'] as $function) {
            $start = strpos($source, 'function ' . $function . '(');
            $end = strpos($source, "\n}", $start) + 2;
            eval('namespace ' . __NAMESPACE__ . '; use TeampassClasses\\Encryption\\Encryption;' . substr($source, $start, $end - $start));
        }
    }

    private function request(array $options, bool $encrypted = false, ?string $payload = null, string $key = 'session'): array
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/admin.queries.php');
        $start = strpos($source, "case 'get_live_activity':");
        $end = strpos($source, "case 'get_system_status':", $start);
        $handler = substr($source, $start, $end - $start);
        $handler = str_replace("require_once __DIR__ . '/admin_activity_logic.php';", '', $handler);
        SessionManager::$encrypted = $encrypted;
        $session = SessionManager::getSession();
        $lang = new class {
            public function get(string $key): string { return $key; }
        };
        $SETTINGS = ['enable_kb' => 1];
        $post_key = $key;
        $post_data = $payload ?? json_encode($options, JSON_THROW_ON_ERROR);
        if ($encrypted) {
            $post_data = Encryption::encrypt($post_data, 'session');
        }
        DB::$queries = [];
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . '; use JsonException; use InvalidArgumentException; switch (\'get_live_activity\') {' . $handler . '}');
            return prepareExchangedData((string) ob_get_contents(), 'decode');
        } finally {
            ob_end_clean();
        }
    }

    public function testHandlerUsesTheExchangeProtocolWithEncryptionOnAndOff(): void
    {
        foreach ([false, true] as $encrypted) {
            $response = $this->request(['categories' => ['failed']], $encrypted);
            self::assertFalse($response['error']);
            self::assertSame(7, $response['failed_count']);
            self::assertCount(2, DB::$queries);
            self::assertStringContainsString('log_system', DB::$queries[0][0]);
            self::assertStringNotContainsString('log_items', DB::$queries[0][0]);
            self::assertTrue($this->request([], $encrypted, 'invalid JSON')['error']);
            self::assertSame([], DB::$queries);
            self::assertTrue($this->request([], $encrypted, null, 'wrong-key')['error']);
            self::assertSame([], DB::$queries);
        }
    }

    public function testOlderPagesDoNotCountFailuresOrNewEvents(): void
    {
        $response = $this->request(['categories' => ['changes', 'failed'], 'expanded' => true,
            'before' => [time() - 10, 2, 20], 'after' => [time() - 20, 2, 10]]);
        self::assertFalse($response['error']);
        self::assertCount(1, DB::$queries);
        self::assertSame(0, $response['failed_count']);
        self::assertSame(0, $response['new_count']);
    }

    public function testLatestPageCountsFailuresButOnlyCountsNewEventsWhenRequested(): void
    {
        $options = ['categories' => ['changes', 'failed'], 'expanded' => true];
        $latest = $this->request($options);
        self::assertCount(2, DB::$queries);
        self::assertSame(7, $latest['failed_count']);
        self::assertSame(0, $latest['new_count']);
        $history = $this->request($options + ['after' => [time() - 10, 2, 10]]);
        self::assertCount(3, DB::$queries);
        self::assertSame(7, $history['new_count']);
        $this->request(['categories' => ['changes']]);
        self::assertCount(1, DB::$queries);
    }
}

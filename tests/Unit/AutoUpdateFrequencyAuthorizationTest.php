<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace AutoUpdateFrequencyRuntime;

use PHPUnit\Framework\TestCase;

/** Record the queries of the handler and answer them without a database. */
final class DB
{
    /** @var array<string, mixed>|null Item row returned by the liveness lookup. */
    public static ?array $item = ['id' => 42];

    /** @var list<array<int, mixed>> Arguments of every lookup. */
    public static array $lookups = [];

    /** @var list<array<int, mixed>> Arguments of every update. */
    public static array $updates = [];

    /** Answer the item lookup. */
    public static function queryFirstRow(mixed ...$args): ?array
    {
        self::$lookups[] = $args;
        return self::$item;
    }

    /** Record an update instead of running it. */
    public static function update(mixed ...$args): void
    {
        self::$updates[] = $args;
    }
}

/** Decide the edit-rights answer of the fixture and record each check. */
final class Access
{
    public static bool $canEdit = true;

    /** @var list<array{0: int, 1: int, 2: string}> */
    public static array $checks = [];
}

/** Return the table name unchanged. */
function prefixTable(string $table): string
{
    return $table;
}

/** Capture error responses without encryption. */
function prepareExchangedData(array|string $data, string $mode): string
{
    return (string) json_encode($data);
}

/** Answer the edit-rights check without a database. */
function userCanEditItem(int $userId, int $itemId, string $action = 'edit'): bool
{
    Access::$checks[] = [$userId, $itemId, $action];
    return Access::$canEdit;
}

/**
 * GHSA-ccc7-vc5r-qp79: server_auto_update_password_frequency updated the posted item id for any
 * signed-in user. It now requires the feature setting, a live item, edit rights on it, and one of
 * the frequencies the form offers.
 */
final class AutoUpdateFrequencyAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        DB::$item = ['id' => 42];
        DB::$lookups = [];
        DB::$updates = [];
        Access::$canEdit = true;
        Access::$checks = [];
    }

    /**
     * Run the handler case up to its success answer, with a simulated session.
     * Like the other authorization sentinels, it evaluates the shipped source, never external input.
     *
     * @return array{allowed: bool, error: ?string}
     */
    private function runCase(array $locals = [], array $settings = ['enable_server_password_change' => '1']): array
    {
        $session = new class {
            /** Read a simulated session value. */
            public function get(string $key): mixed
            {
                return ['key' => 'fixture-key', 'user-id' => 7][$key] ?? null;
            }
        };
        $lang = new class {
            /** Return stable error keys independently of locale. */
            public function get(string $key): string { return $key; }
        };
        $SETTINGS = $settings;
        $action = 'server_auto_update_password_frequency';
        $post_key = 'fixture-key';
        $post_id = '42';
        $post_freq = '2';
        extract($locals);

        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/utils.queries.php'));
        $start = strpos($source, "case '" . $action . "':");
        self::assertNotFalse($start, 'The case must exist.');
        $end = strpos($source, "echo '[{\"error\" : \"\"}]';", $start);
        self::assertNotFalse($end, 'The case must end with its success answer.');

        $allowed = false;
        ob_start();
        eval('namespace AutoUpdateFrequencyRuntime; switch ($action) {' . substr($source, $start, $end - $start) . '$allowed = true; }');
        $response = (string) ob_get_clean();
        $decoded = $response === '' ? null : json_decode($response, true);

        return ['allowed' => $allowed, 'error' => $decoded['message'] ?? null];
    }

    /** An item the caller cannot edit is never updated: the reporter's proof of concept. */
    public function testItemWithoutEditRightsIsRefused(): void
    {
        Access::$canEdit = false;
        $result = $this->runCase();

        self::assertFalse($result['allowed']);
        self::assertSame('error_not_allowed_to', $result['error']);
        self::assertSame([], DB::$updates);
    }

    /** The rights are checked for the caller on the posted item, without taking an edition lock. */
    public function testRightsAreCheckedWithoutEditionLock(): void
    {
        $this->runCase();

        self::assertSame([[7, 42, '']], Access::$checks);
    }

    /** A deleted, inactive or missing item is refused before any rights check. */
    public function testItemThatIsNotLiveIsRefused(): void
    {
        DB::$item = null;
        $result = $this->runCase();

        self::assertFalse($result['allowed']);
        self::assertSame('error_not_allowed_to', $result['error']);
        self::assertSame([], Access::$checks);
        self::assertStringContainsString('inactif = %i AND deleted_at IS NULL', (string) DB::$lookups[0][0]);
    }

    /** The feature setting gates the handler, not only the menu entry. */
    public function testDisabledFeatureIsRefused(): void
    {
        foreach ([[], ['enable_server_password_change' => '0']] as $settings) {
            $result = $this->runCase([], $settings);

            self::assertFalse($result['allowed']);
            self::assertSame('error_not_allowed_to', $result['error']);
        }
        self::assertSame([], DB::$lookups);
        self::assertSame([], DB::$updates);
    }

    /** Only the frequencies of the form select are accepted, and the id must be positive. */
    public function testOutOfRangeValuesAreRefused(): void
    {
        foreach ([['post_freq' => '5'], ['post_freq' => '-1'], ['post_freq' => ''], ['post_freq' => '99999999999999999999'],
            ['post_id' => '0'], ['post_id' => '-3'], ['post_id' => '']] as $locals) {
            $result = $this->runCase($locals);

            self::assertFalse($result['allowed'], (string) json_encode($locals));
            self::assertSame('error_not_allowed_to', $result['error']);
        }
        self::assertSame([], DB::$updates);
    }

    /** A wrong session key keeps its own refusal. */
    public function testWrongKeyIsRefused(): void
    {
        $result = $this->runCase(['post_key' => 'other-key']);

        self::assertFalse($result['allowed']);
        self::assertSame('key_is_not_correct', $result['error']);
    }

    /** An editor updates their item, with integer values only. */
    public function testEditorUpdatesTheCheckedItem(): void
    {
        $result = $this->runCase();

        self::assertTrue($result['allowed']);
        self::assertCount(1, DB::$updates);
        [$table, $values, $where, $id] = DB::$updates[0];
        self::assertSame('items', $table);
        self::assertSame(2, $values['auto_update_pwd_frequency']);
        self::assertIsInt($values['auto_update_pwd_next_date']);
        self::assertSame('id = %i', $where);
        self::assertSame(42, $id);
    }
}

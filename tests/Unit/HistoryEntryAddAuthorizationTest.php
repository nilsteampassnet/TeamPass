<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace HistoryEntryAddRuntime;

use PHPUnit\Framework\TestCase;

/** Record the queries of the handler and answer them without a database. */
final class DB
{
    /** @var array<string, mixed>|null Item row returned by the liveness lookup. */
    public static ?array $item = ['label' => 'Fixture item', 'id_tree' => 5];

    /** @var list<array<int, mixed>> Arguments of every lookup. */
    public static array $lookups = [];

    /** Answer the item lookup. */
    public static function queryFirstRow(mixed ...$args): ?array
    {
        self::$lookups[] = $args;
        return self::$item;
    }
}

/** Decide the access answer of the fixture and record each check and each log write. */
final class Fixture
{
    /** @var array{access: bool, edit: bool} */
    public static array $rights = ['access' => true, 'edit' => true];

    /** @var list<list<mixed>> */
    public static array $checks = [];

    /** @var list<list<mixed>> */
    public static array $logs = [];
}

/** Return the table name unchanged. */
function prefixTable(string $table): string
{
    return $table;
}

/** Hand the posted payload to the handler and capture its answers without encryption. */
function prepareExchangedData(array|string $data, string $mode): array|string
{
    if ($mode === 'decode') {
        return [
            'item_id' => '42',
            'label' => 'Manual entry',
            'date' => '01/01/2026',
            'time' => '12:00',
        ];
    }
    return (string) json_encode($data);
}

/** Answer the rights check without a database. */
function getCurrentAccessRights(mixed ...$args): array
{
    Fixture::$checks[] = $args;
    return Fixture::$rights;
}

/** Record the log write instead of running it. */
function logItems(mixed ...$args): void
{
    Fixture::$logs[] = $args;
}

/** Return a fixed timestamp. */
function dateToStamp(string $date, string $format): int
{
    return 1767268800;
}

/**
 * GHSA-4hrh-wjp5-wrph: history_entry_add wrote into the audit history of any item of a visible
 * folder. It ignored role-only restrictions, edit rights and the feature setting, and answered
 * with the item's newest log row whoever wrote it. It now requires the setting, a live item and
 * the edit right through getCurrentAccessRights(), and reads nothing back.
 */
final class HistoryEntryAddAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        DB::$item = ['label' => 'Fixture item', 'id_tree' => 5];
        DB::$lookups = [];
        Fixture::$rights = ['access' => true, 'edit' => true];
        Fixture::$checks = [];
        Fixture::$logs = [];
    }

    /**
     * Run the handler case with a simulated session.
     * Like the other authorization sentinels, it evaluates the shipped source, never external input.
     *
     * @return array<string, mixed>
     */
    private function runCase(string $postedKey = 'fixture-key', array $settings = ['insert_manual_entry_item_history' => '1']): array
    {
        $session = new class {
            /** Read a simulated session value. */
            public function get(string $key): mixed
            {
                return ['key' => 'fixture-key', 'user-id' => 7, 'user-login' => 'fixture'][$key] ?? null;
            }
        };
        $lang = new class {
            /** Return stable error keys independently of locale. */
            public function get(string $key): string { return $key; }
        };
        $SETTINGS = $settings + ['date_format' => 'd/m/Y', 'time_format' => 'H:i'];
        $action = 'history_entry_add';
        $inputData = ['key' => $postedKey, 'data' => 'encrypted-payload'];

        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/items.queries.php'));
        $start = strpos($source, "case '" . $action . "':");
        self::assertNotFalse($start, 'The case must exist.');
        $end = strpos($source, "case 'free_item_for_edition':", $start);
        self::assertNotFalse($end, 'The case must be followed by free_item_for_edition.');

        ob_start();
        eval('namespace HistoryEntryAddRuntime; switch ($action) {' . substr($source, $start, $end - $start) . '}');
        $response = (string) ob_get_clean();

        return (array) json_decode($response, true);
    }

    /** An item the caller cannot edit gets no entry: the reporter's role-restricted item, or a read-only folder. */
    public function testItemWithoutEditRightIsRefused(): void
    {
        foreach ([['access' => false, 'edit' => false], ['access' => true, 'edit' => false]] as $rights) {
            Fixture::$rights = $rights;
            $response = $this->runCase();

            self::assertTrue($response['error']);
            self::assertSame('error_not_allowed_to', $response['message']);
        }
        self::assertSame([], Fixture::$logs);
    }

    /** The rights are checked for the caller on the posted item, without taking an edition lock. */
    public function testRightsAreCheckedWithoutEditionLock(): void
    {
        $this->runCase();

        self::assertSame([[7, 42, 5]], Fixture::$checks);
    }

    /** A deleted, inactive or missing item is refused before any rights check. */
    public function testItemThatIsNotLiveIsRefused(): void
    {
        DB::$item = null;
        $response = $this->runCase();

        self::assertTrue($response['error']);
        self::assertSame([], Fixture::$checks);
        self::assertSame([], Fixture::$logs);
        self::assertStringContainsString('inactif = %i AND deleted_at IS NULL', (string) DB::$lookups[0][0]);
    }

    /** The feature setting gates the handler, not only the button. */
    public function testDisabledFeatureIsRefused(): void
    {
        foreach ([[], ['insert_manual_entry_item_history' => '0']] as $settings) {
            $response = $this->runCase('fixture-key', $settings);

            self::assertTrue($response['error']);
            self::assertSame('error_not_allowed_to', $response['message']);
        }
        self::assertSame([], DB::$lookups);
        self::assertSame([], Fixture::$logs);
    }

    /** A wrong session key keeps its own refusal. */
    public function testWrongKeyIsRefused(): void
    {
        $response = $this->runCase('other-key');

        self::assertSame('key_is_wrong', $response['error']);
        self::assertSame([], Fixture::$logs);
    }

    /** An editor adds the entry, and the answer carries no row of the item's history. */
    public function testEditorAddsEntryWithoutReadingHistoryBack(): void
    {
        $response = $this->runCase();

        self::assertSame(['error' => false, 'message' => ''], $response);
        self::assertCount(1, Fixture::$logs);
        [, $itemId, $itemLabel, $userId, $logAction, $login, $reason, , $date] = Fixture::$logs[0];
        self::assertSame(42, $itemId);
        self::assertSame('Fixture item', $itemLabel);
        self::assertSame(7, $userId);
        self::assertSame('at_manual', $logAction);
        self::assertSame('fixture', $login);
        self::assertSame('Manual entry', $reason);
        self::assertSame('1767268800', $date);
        self::assertCount(1, DB::$lookups, 'Only the item lookup runs: the log is never read back.');
    }
}

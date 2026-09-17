<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/../../app/vendor/sergeytsalkov/meekrodb/db.class.php';
require_once __DIR__ . '/../../app/sources/logs_filter_logic.php';

/**
 * Exercise the shipped log predicates against disposable rows, including actual deletions.
 * SQLite is only an in-memory test fixture; production continues to use MySQL/MariaDB.
 */
#[RequiresPhpExtension('sqlite3')]
class LogsFilteringTest extends TestCase
{
    private SQLite3 $database;
    private MeekroDB $parser;

    protected function setUp(): void
    {
        $this->database = new SQLite3(':memory:');
        $this->database->enableExceptions(true);
        // Keep MeekroDB's real placeholder/WhereClause handling, with SQLite string quoting.
        $this->parser = new class extends MeekroDB {
            public function escape($value)
            {
                return "'" . SQLite3::escapeString((string) $value) . "'";
            }
        };
        // NOCASE covers ASCII login case variants; it does not emulate MySQL's full Unicode collation.
        $this->database->exec('CREATE TABLE log_system (id INTEGER PRIMARY KEY, date INTEGER, type TEXT, label TEXT, qui TEXT, field_1 TEXT COLLATE NOCASE)');
        $this->database->exec('CREATE TABLE log_items (id INTEGER PRIMARY KEY, date INTEGER, id_user INTEGER, id_item INTEGER, action TEXT, raison TEXT)');
        $this->database->exec('CREATE TABLE items (id INTEGER, label TEXT, id_tree INTEGER)');
        $this->database->exec('CREATE TABLE users (id INTEGER, login TEXT, name TEXT, lastname TEXT)');
        $this->database->exec('CREATE TABLE nested_tree (id INTEGER, title TEXT, personal_folder INTEGER)');
    }

    protected function tearDown(): void
    {
        $this->database->close();
    }

    private function query(string $query, mixed ...$args): SQLite3Result
    {
        return $this->database->query($this->parser->parse($query, ...$args));
    }

    private function remainingIds(string $table): array
    {
        $result = $this->query('SELECT id FROM %l ORDER BY id', $table);
        $ids = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $ids[] = $row['id'];
        }
        return $ids;
    }

    /**
     * Use the canonical filter payload and the fixture tables, as the handler does.
     *
     * @param array<string, mixed> $input
     * @return array{table: string, where: WhereClause}|null
     */
    private function purgeScope(array $input, ?string $login = null): ?array
    {
        $filters = logsNormalizeFilters($input);
        // Keep the fixture's small timestamps; date parsing has separate unit coverage.
        $filters['date_from'] = 100;
        $filters['date_to'] = 200;

        return buildLogsPurgeFilter($filters, $login, [
            'items' => 'items',
            'users' => 'users',
            'nested_tree' => 'nested_tree',
        ]);
    }

    /** @param array<string, mixed> $input */
    private function purge(array $input, ?string $login = null): void
    {
        $scope = $this->purgeScope($input, $login);
        self::assertNotNull($scope);
        $this->query('DELETE FROM %l WHERE %l', $scope['table'], $scope['where']);
    }

    private function searchItems(mixed $column, string $searchValue): array
    {
        $lang = new Language('french', __DIR__ . '/../../app/includes/language');
        $where = buildItemLogSearchFilter($column, $searchValue, $lang);
        $result = $this->query(
            'SELECT l.id FROM log_items l JOIN items i ON i.id = l.id_item
            JOIN users u ON u.id = l.id_user JOIN nested_tree t ON t.id = i.id_tree WHERE %l ORDER BY l.id',
            $where
        );
        $ids = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $ids[] = $row['id'];
        }
        return $ids;
    }

    public function testUserSearchExcludesMatchesInOtherColumnsAndIncludesDisplayedNames(): void
    {
        $this->database->exec("INSERT INTO users VALUES (42, 'alice', 'Alice', 'Example'), (43, 'bob', 'Robert', 'Example')");
        $this->database->exec("INSERT INTO items VALUES (10, 'VPN', 1), (20, 'alice portal', 1)");
        $this->database->exec("INSERT INTO nested_tree VALUES (1, 'General', 0)");
        $this->database->exec("INSERT INTO log_items VALUES (1, 150, 42, 10, 'at_shown', ''), (2, 151, 43, 20, 'at_shown', '')");

        self::assertSame([1, 2], $this->searchItems('all', 'alice'));
        self::assertSame([1], $this->searchItems('u.login', 'alice'));
        self::assertSame([2], $this->searchItems('i.label', 'alice'));
        self::assertSame([2], $this->searchItems('u.login', 'Robert'));
        self::assertSame([1, 2], $this->searchItems('u.login', 'Example'));
        self::assertSame([], $this->searchItems('t.title', 'alice'));
        self::assertSame([1, 2], $this->searchItems('u.login', ''));
    }

    /** @return iterable<string, array{string, string}> */
    public static function systemTabs(): iterable
    {
        yield 'connections' => ['connections', 'user_connection'];
        yield 'errors' => ['errors', 'error'];
        yield 'admin actions' => ['admin', 'admin_action'];
        yield 'user management' => ['admin', 'user_mngt'];
    }

    #[DataProvider('systemTabs')]
    public function testSystemPurgeKeepsOtherUsersDatesAndTypes(string $tab, string $type): void
    {
        foreach ([[1, 100, $type, '42'], [2, 150, $type, '43'], [3, 150, 'other', '42'],
            [4, 99, $type, '42'], [5, 199, $type, '42'], [6, 200, $type, '42'], [7, 150, $type, '42.1.2.3']] as $row) {
            $this->query('INSERT INTO log_system (id, date, type, qui) VALUES (%i, %i, %s, %s)', ...$row);
        }
        $filters = ['source' => 'system', 'types' => [$tab], 'user_id' => 42];
        $scope = $this->purgeScope($filters);
        self::assertNotNull($scope);
        self::assertStringContainsString("qui = '42'", $this->parser->parse('%l', $scope['where']));
        $this->purge($filters);
        self::assertSame([2, 3, 4, 6, 7], $this->remainingIds('log_system'));
        $this->purge(['source' => 'system', 'types' => [$tab]]);
        self::assertSame([3, 4, 6], $this->remainingIds('log_system'));
    }

    public function testItemAndCopyPurgeCombineUserActionAndDates(): void
    {
        $this->database->exec("INSERT INTO users VALUES (42, 'alice', '', ''), (43, 'bob', '', '')");
        $this->database->exec("INSERT INTO items VALUES (10, 'VPN', 1), (20, 'Missing folder', 2)");
        $this->database->exec("INSERT INTO nested_tree VALUES (1, 'General', 0)");
        $this->database->exec("INSERT INTO log_items (id, date, id_user, id_item, action) VALUES
            (1, 150, 42, 10, 'at_copy'), (2, 150, 43, 10, 'at_copy'), (3, 150, 42, 10, 'at_shown'),
            (4, 99, 42, 10, 'at_copy'), (5, 200, 42, 10, 'at_copy'),
            (6, 150, 999, 10, 'at_copy'), (7, 150, 42, 999, 'at_copy'), (8, 150, 42, 20, 'at_copy')");
        $this->purge(['source' => 'items', 'user_id' => 42, 'actions' => ['at_copy']]);
        self::assertSame([2, 3, 4, 5, 6, 7, 8], $this->remainingIds('log_items'));
        $this->purge(['source' => 'items', 'actions' => ['at_shown']]);
        self::assertSame([2, 4, 5, 6, 7, 8], $this->remainingIds('log_items'));
        $this->purge(['source' => 'items', 'user_id' => 43]);
        self::assertSame([4, 5, 6, 7, 8], $this->remainingIds('log_items'));
        // A purge without user/action filters must still preserve orphaned rows hidden by the view.
        $this->purge(['source' => 'items']);
        self::assertSame([4, 5, 6, 7, 8], $this->remainingIds('log_items'));
    }

    public function testFailedLoginPurgeUsesCurrentLoginAndRecognizedApiMarkerInsteadOfIp(): void
    {
        foreach ([[1, 'alice', 'password_is_not_correct'], [2, 'bob', 'password_is_not_correct'],
            [3, 'alice | tp_src=api', 'api_invalid_credentials'], [4, 'alice2', 'password_is_not_correct'],
            [5, 'alice | tp_src=api', 'password_is_not_correct'], [6, 'alice | tp_src=api', 'bruteforce_account_locked'],
            [8, 'Alice', 'password_is_not_correct'], [9, 'ALICE | tp_src=api', 'api_invalid_credentials'],
            [10, 'previous-login', 'password_is_not_correct']] as $row) {
            $this->query("INSERT INTO log_system (id, date, type, field_1, label, qui) VALUES (%i, 150, 'failed_auth', %s, %s, '192.0.2.1')", ...$row);
        }
        $this->database->exec("INSERT INTO log_system VALUES (7, 200, 'failed_auth', 'password_is_not_correct', '192.0.2.1', 'alice')");
        $this->purge(['source' => 'system', 'types' => ['failed'], 'user_id' => 42], 'alice');
        self::assertSame([2, 4, 5, 7, 10], $this->remainingIds('log_system'));
        $this->purge(['source' => 'system', 'types' => ['failed']]);
        self::assertSame([7], $this->remainingIds('log_system'));
    }

    public function testLoginMetacharactersCannotBroadenThePurge(): void
    {
        $login = "a'_% OR 1=1 --";
        $this->query("INSERT INTO log_system VALUES (1, 150, 'failed_auth', 'password_is_not_correct', '192.0.2.1', %s)", $login);
        $this->database->exec("INSERT INTO log_system VALUES (2, 150, 'failed_auth', 'password_is_not_correct', '192.0.2.1', 'alice')");
        $this->purge(['source' => 'system', 'types' => ['failed'], 'user_id' => 42], $login);
        self::assertSame([2], $this->remainingIds('log_system'));
    }

    /** Verify the displayed action is searchable and raw timestamps cannot add matches. */
    public function testTranslatedActionSearchAndGlobalSearchExcludeTimestampNoise(): void
    {
        $this->database->exec("INSERT INTO users VALUES (1, 'user', '', '')");
        $this->database->exec("INSERT INTO items VALUES (42, 'Guide', 1), (50, 'Portal', 1)");
        $this->database->exec("INSERT INTO nested_tree VALUES (1, 'General', 0)");
        $this->database->exec("INSERT INTO log_items VALUES (1, 1700000000, 1, 42, 'at_shown', ''), (2, 1700042000, 1, 50, 'at_manual', '')");
        $lang = new Language('french', __DIR__ . '/../../app/includes/language');

        self::assertSame([1], $this->searchItems('all', '42'));
        self::assertSame([1], $this->searchItems('l.action', (string) $lang->get('at_shown')));
        self::assertSame([2], $this->searchItems('all', (string) $lang->get('at_manual')));
        self::assertSame([2], $this->searchItems('l.action', 'AT_MANUAL'));
        self::assertSame([], $this->searchItems('l.action', 'no-such-action'));
        self::assertSame([], $this->searchItems('all', "' OR 1=1 --"));
        self::assertSame([], $this->searchItems('all', date('d/m/Y', 1700042000)));
        self::assertSame([1, 2], $this->searchItems('l.action', ''));
    }
}

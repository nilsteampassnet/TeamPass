<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of the TeamPass project, distributed under the GNU GPL-3.0.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\FavouritesMutation;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/item_restriction_logic.php';

/** Execute production predicates and writes against disposable, non-default-prefix tables. */
class DB
{
    public static \SQLite3 $connection;
    public static array $folders = [10, 20];
    public static array $roles = [3];

    /** Translate MeekroDB binding without replacing the production authorization SQL. */
    public static function query(string $sql, int ...$values): array
    {
        $index = 0;
        $sql = str_replace('INSERT IGNORE INTO', 'INSERT OR IGNORE INTO', $sql);
        $sql = preg_replace_callback('/%i\b/', static function () use (&$index): string {
            return ':value' . $index++;
        }, $sql);
        $statement = self::$connection->prepare($sql);
        foreach ($values as $key => $value) {
            $statement->bindValue(':value' . $key, $value, SQLITE3_INTEGER);
        }
        $result = $statement->execute();
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** Preserve MeekroDB's null result when an item is absent or unauthorized. */
    public static function queryFirstField(string $sql, int ...$values): ?int
    {
        $row = self::query($sql, ...$values)[0] ?? null;
        return $row === null ? null : (int) array_values($row)[0];
    }
}

/** Exercise a non-default installation prefix. */
function prefixTable(string $table): string { return 'fav_fixture_' . $table; }

/** Supply the resolved folder scope; its grant/denial decisions have their own tests. */
function securityPostureAuthorizedFolderIds(int $userId): array { return DB::$folders; }

/** Supply the current roles to the production per-item restriction predicate. */
function securityPostureUserRoleIds(int $userId): array { return DB::$roles; }

#[RequiresPhpExtension('sqlite3')]
class FavouritesMutationTest extends TestCase
{
    /** Load the shipped helpers in an isolated namespace, without installation bootstrap. */
    public static function setUpBeforeClass(): void
    {
        foreach ([
            'main.functions.php' => ['securityPostureItemAccessSql', 'favouriteItemIsReadable', 'addUserFavorite', 'removeUserFavorite', 'getUserFavorites'],
        ] as $file => $functions) {
            $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/' . $file));
            foreach ($functions as $function) {
                $start = strpos($source, 'function ' . $function . '(');
                self::assertNotFalse($start);
                $end = strpos($source, "\n}", $start);
                self::assertNotFalse($end);
                eval('namespace ' . __NAMESPACE__ . ';' . substr($source, $start, $end + 2 - $start));
            }
        }
    }

    protected function setUp(): void
    {
        DB::$connection = new \SQLite3(':memory:');
        DB::$connection->enableExceptions(true);
        DB::$connection->createFunction('CONCAT', static fn (...$values): string => implode('', $values));
        DB::$folders = [10, 20];
        DB::$roles = [3];
        DB::$connection->exec('CREATE TABLE fav_fixture_items (id INTEGER PRIMARY KEY, id_tree INTEGER, inactif INTEGER DEFAULT 0, restricted_to TEXT DEFAULT "");
            CREATE TABLE fav_fixture_restriction_to_roles (item_id INTEGER, role_id INTEGER);
            CREATE TABLE fav_fixture_users_favorites (user_id INTEGER, item_id INTEGER, created_at INTEGER DEFAULT 0, UNIQUE(user_id,item_id));
            INSERT INTO fav_fixture_items (id,id_tree,inactif,restricted_to) VALUES
                (1,10,0,""),(2,20,0,""),(3,99,0,""),(4,10,1,""),
                (5,10,0,"42"),(6,10,0,"7"),(7,10,0,""),(8,10,0,""),(9,10,0,"17");
            INSERT INTO fav_fixture_restriction_to_roles VALUES (7,3),(8,4);
            INSERT INTO fav_fixture_users_favorites (user_id,item_id) VALUES (42,1);');
    }

    /** Shared, own-personal, direct-user and held-role access all allow personal bookmarks. */
    public function testReadableItemsCanBeBookmarkedIdempotently(): void
    {
        foreach ([1, 2, 6, 7] as $id) {
            self::assertTrue(favouriteItemIsReadable(7, $id));
            addUserFavorite(7, $id);
            addUserFavorite(7, $id);
        }
        self::assertSame([1, 2, 6, 7], array_map('intval', getUserFavorites(7)));
        self::assertSame([1], array_map('intval', getUserFavorites(42)), 'Other users keep their own favorites');
    }

    /** Missing/deleted items, denied/foreign folders and both restriction types fail closed. */
    public function testUnauthorizedItemsCannotBeBookmarked(): void
    {
        foreach ([0, -1, 999, 3, 4, 5, 8, 9] as $id) {
            self::assertFalse(favouriteItemIsReadable(7, $id), 'Item ' . $id);
        }
        self::assertFalse(favouriteItemIsReadable(0, 1));
        self::assertFalse(favouriteItemIsReadable(-1, 1));
        self::assertSame([], getUserFavorites(7));
        DB::$folders = [];
        self::assertFalse(favouriteItemIsReadable(7, 1));
    }

    /** Revocation between search and click is rechecked rather than trusting the rendered row. */
    public function testChangedFolderAndRoleRightsAreCheckedAgain(): void
    {
        DB::$folders = [10];
        self::assertFalse(favouriteItemIsReadable(7, 2));
        DB::$roles = [];
        self::assertFalse(favouriteItemIsReadable(7, 7));
        self::assertSame([], getUserFavorites(7));
    }

    /** Removing a stale bookmark remains possible and never removes another user's bookmark. */
    public function testRemovalWorksAfterAccessIsLost(): void
    {
        addUserFavorite(7, 1);
        DB::$folders = [];
        self::assertFalse(favouriteItemIsReadable(7, 1));
        foreach ([1, 1] as $id) {
            removeUserFavorite(7, $id);
        }
        self::assertSame([], getUserFavorites(7));
        self::assertSame([1], array_map('intval', getUserFavorites(42)));
    }

    /** Both controllers share the helper; Items keeps its separate read-only policy. */
    public function testBothControllersCheckReadabilityOnlyWhenAdding(): void
    {
        $favourites = (string) file_get_contents(__DIR__ . '/../../app/sources/favourites.queries.php');
        $items = (string) file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        self::assertStringContainsString('if (favouriteItemIsReadable($userId, $itemId) === false)', $favourites);
        self::assertStringContainsString(
            "if (\$action === 0 && favouriteItemIsReadable((int) \$session->get('user-id'), \$itemId) === false)",
            $items
        );
        self::assertStringContainsString(
            "if (\$session->get('user-read_only') === 1 || !isset(\$SETTINGS['pwd_maximum_length']))",
            $items
        );
    }
}

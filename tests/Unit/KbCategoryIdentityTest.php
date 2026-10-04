<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License v3.0.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\KbCategoryIdentity;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/** Run the production row lookup against a disposable SQL fixture. */
final class DB
{
    public static \SQLite3 $connection;

    /** Bind the lookup's integer placeholder without changing its joins or projection. */
    public static function queryFirstRow(string $query, int $id): ?array
    {
        $statement = self::$connection->prepare(str_replace('%i', ':id', $query));
        $statement->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $statement->execute()->fetchArray(SQLITE3_ASSOC);

        return $row === false ? null : $row;
    }
}

/** Keep the fixture independent of installation settings and the default table prefix. */
function prefixTable(string $table): string
{
    return 'kb_identity_fixture_' . $table;
}

/** Detail lookups must expose the same resolved category identity as list_kbs. */
#[RequiresPhpExtension('sqlite3')]
final class KbCategoryIdentityTest extends TestCase
{
    private string $listQuery;

    protected function setUp(): void
    {
        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/kb.queries.php'));
        if (!function_exists(__NAMESPACE__ . '\\kbLoadRow')) {
            // Load only the production lookup, without executing the AJAX controller.
            $start = strpos($source, 'function kbLoadRow(');
            self::assertNotFalse($start);
            $end = strpos($source, "\n}", $start);
            self::assertNotFalse($end);
            eval('namespace ' . __NAMESPACE__ . ';' . substr($source, $start, $end + 2 - $start));
        }
        $listStart = strpos($source, "case 'list_kbs':");
        self::assertNotFalse($listStart);
        $queryStart = strpos($source, '$rows = DB::query(', $listStart);
        self::assertNotFalse($queryStart);
        $queryEnd = strpos($source, "\n        );", $queryStart);
        self::assertNotFalse($queryEnd);
        // Evaluate only the shipped SELECT expression, retaining prefixTable() and both joins.
        $expressionStart = $queryStart + strlen('$rows = DB::query(');
        $this->listQuery = eval('namespace ' . __NAMESPACE__ . '; return ' . substr($source, $expressionStart, $queryEnd - $expressionStart) . ';');

        DB::$connection = new \SQLite3(':memory:');
        DB::$connection->enableExceptions(true);
        DB::$connection->exec(<<<'SQL'
            CREATE TABLE kb_identity_fixture_kb (id INTEGER PRIMARY KEY, category_id INTEGER,
                label TEXT, description TEXT, author_id INTEGER, anyone_can_modify INTEGER,
                allow_comments INTEGER, deleted_at INTEGER);
            CREATE TABLE kb_identity_fixture_kb_categories (id INTEGER PRIMARY KEY, category TEXT);
            CREATE TABLE kb_identity_fixture_users (id INTEGER PRIMARY KEY, login TEXT);
            INSERT INTO kb_identity_fixture_kb_categories VALUES (7, 'Network');
            INSERT INTO kb_identity_fixture_users VALUES (1, 'alice');
            INSERT INTO kb_identity_fixture_kb VALUES
                (1, 7, 'Valid', 'Body', 1, 0, 0, NULL),
                (2, 99, 'Orphan', 'Body', 1, 0, 0, NULL),
                (3, 0, 'Legacy', 'Body', 1, 0, 0, NULL),
                (4, 7, 'Deleted', 'Body', 1, 0, 0, 123);
            SQL);
    }

    protected function tearDown(): void
    {
        DB::$connection->close();
    }

    /** Read the actual list projection for comparison with the detail lookup. */
    private function listedArticle(int $id): array
    {
        $result = DB::$connection->query($this->listQuery);
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }

        self::fail('Article missing from the active list fixture');
    }

    /** A valid category keeps its identity in both entry points. */
    public function testExistingCategoryIdentityMatchesTheList(): void
    {
        $detail = kbLoadRow(1);
        self::assertSame(7, $detail['category_id']);
        self::assertSame('Network', $detail['category']);
        self::assertSame($this->listedArticle(1)['category_id'], $detail['category_id']);
    }

    /** Orphaned and legacy relations normalize to the same zero payload identity. */
    public function testUnresolvedCategoriesHaveNoResolvedIdentity(): void
    {
        foreach ([2, 3] as $id) {
            $detail = kbLoadRow($id);
            self::assertNull($detail['category_id']);
            self::assertNull($detail['category']);
            self::assertSame($this->listedArticle($id)['category_id'], $detail['category_id']);
            self::assertSame(0, (int) ($detail['category_id'] ?? 0));
        }
    }

    /** Removing a category changes the lookup identity without mutating the stored article. */
    public function testDeletedCategoryNoLongerExposesTheStoredForeignKey(): void
    {
        DB::$connection->exec('DELETE FROM kb_identity_fixture_kb_categories WHERE id = 7');
        self::assertSame(7, DB::$connection->querySingle('SELECT category_id FROM kb_identity_fixture_kb WHERE id = 1'));
        self::assertNull(kbLoadRow(1)['category_id']);
        self::assertNull($this->listedArticle(1)['category_id']);
    }

    /** Keep the existing missing/deleted article boundary intact. */
    public function testDeletedAndMissingArticlesRemainUnavailable(): void
    {
        self::assertNull(kbLoadRow(4));
        self::assertNull(kbLoadRow(999));
        self::assertSame(3, DB::$connection->querySingle('SELECT COUNT(*) FROM (' . $this->listQuery . ')'));
    }
}

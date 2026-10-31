<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\FacetedStoredTextSearch;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../Fixtures/stored_text_search_db.php';
require_once __DIR__ . '/../../app/sources/search.functions.php';
require_once __DIR__ . '/../../app/sources/find.functions.php';

if (!defined('TP_ONE_DAY_SECONDS')) {
    define('TP_ONE_DAY_SECONDS', 86400);
}

/** Run the shipped queries against isolated authorized and unauthorized fixtures. */
class DB extends \StoredTextSearchFixtureDatabase {}

/** Use a non-default prefix for all controller tables. */
function prefixTable(string $table): string { return 'faceted_fixture_' . $table; }

/** No health or renewal facet is enabled in these text/pagination fixtures. */
function securityPasswordHealthSql(string $alias): array { return ['weak' => '0']; }

/** Keep the unrelated policy expression deterministic. */
function renewalApplicablePeriodSql(array $settings): string { return 'i.renewal_period'; }

/** Avoid loading installation state to test text-only candidate selection. */
function renewalEligibleItemSql(array $settings): string { return '1 = 1'; }

/** Keep hydration separate while enforcing that every selected ID remains authorized. */
function searchHydrateFolderRows(array $rows, array $scope, int $userId, string $login): array
{
    return array_map(static function (array $row) use ($scope, $userId, $login): array {
        if (!in_array((int) $row['id'], $scope, true)) {
            throw new \RuntimeException('Unauthorized folder reached hydration');
        }
        return ['id' => (int) $row['id'], 'title' => \searchFolderDisplayTitle($row['title'], (int) $row['nlevel'], $userId, $login), 'path' => ''];
    }, $rows);
}

/** Counts and page boundaries execute the production SQL and streaming handler. */
#[RequiresPhpExtension('sqlite3')]
class FacetedStoredTextSearchTest extends TestCase
{
    protected function setUp(): void
    {
        DB::reset();
        DB::$connection->exec('CREATE TABLE faceted_fixture_nested_tree (id INTEGER PRIMARY KEY,title TEXT,nlevel INTEGER,nleft INTEGER);
            CREATE TABLE faceted_fixture_items (id INTEGER PRIMARY KEY,id_tree INTEGER,deleted_at INTEGER,restricted_to TEXT DEFAULT "",renewal_period INTEGER DEFAULT 0,created_at INTEGER DEFAULT 0);
            CREATE TABLE faceted_fixture_cache (id INTEGER PRIMARY KEY,label TEXT,login TEXT DEFAULT "",description TEXT DEFAULT "",tags TEXT DEFAULT "",url TEXT DEFAULT "",folder TEXT DEFAULT "",id_tree INTEGER,perso INTEGER DEFAULT 0,author TEXT DEFAULT "7",restricted_to TEXT DEFAULT "",renewal_period INTEGER DEFAULT 0,timestamp TEXT DEFAULT "0");
            CREATE TABLE faceted_fixture_restriction_to_roles (item_id INTEGER,role_id INTEGER);
            INSERT INTO faceted_fixture_nested_tree VALUES (10,"Shared",1,1),(99,"Hidden",1,2);');
        $item = DB::$connection->prepare('INSERT INTO faceted_fixture_items (id,id_tree) VALUES (:id,10)');
        $cache = DB::$connection->prepare('INSERT INTO faceted_fixture_cache (id,label,id_tree) VALUES (:id,:label,10)');
        foreach (range(1, 165) as $id) {
            $label = $id <= 100 ? 'R&amp;D &quot;Lab&quot;' : 'Ampere ' . sprintf('%05d', $id);
            $item->bindValue(':id', $id, SQLITE3_INTEGER);
            $item->execute();
            $cache->bindValue(':id', $id, SQLITE3_INTEGER);
            $cache->bindValue(':label', $label);
            $cache->execute();
        }
        $folder = DB::$connection->prepare('INSERT INTO faceted_fixture_nested_tree VALUES (:id,:title,2,:position)');
        foreach (range(1, 165) as $id) {
            $folder->bindValue(':id', 1000 + $id, SQLITE3_INTEGER);
            $folder->bindValue(':title', $id <= 100 ? 'R&amp;D &quot;Lab&quot;' : 'Ampere ' . sprintf('%05d', $id));
            $folder->bindValue(':position', 1000 + $id, SQLITE3_INTEGER);
            $folder->execute();
        }
        DB::$connection->exec("INSERT INTO faceted_fixture_nested_tree VALUES (7,'7',1,0),(5000,'&lt;Guide&gt;',2,5000);");
        DB::$connection->exec("INSERT INTO faceted_fixture_items (id,id_tree,deleted_at,restricted_to) VALUES
            (900,99,NULL,''),(901,10,1,''),(902,10,NULL,'42'),(500,10,NULL,''),(501,10,NULL,''),(600,10,NULL,''),(601,10,NULL,'');
            INSERT INTO faceted_fixture_cache (id,label,id_tree) VALUES (900,'Ampere hidden',99),(901,'Ampere deleted',10),
            (902,'Ampere restricted',10),(500,'O&apos;Brien',10),(501,'O&#039;Brien',10),(600,'&#90;ebra sort',10),(601,'Alpha sort',10);");
    }

    /** Execute the production count/page block without transport or rendering side effects. */
    private function search(array $rawFilters, int $start = 0, int $length = 25, string $direction = 'asc', array $scope = [10]): array
    {
        $filters = \searchNormalizeFilters($rawFilters);
        $folderScope = $scope;
        $userId = 7;
        $userLogin = 'test.user';
        $ownPersonalFolderIds = [];
        $userRoleIds = [];
        $featureCustomFields = $featureClassification = false;
        $SETTINGS = [];
        $request = Request::create('/', 'POST', ['start' => $start, 'length' => $length, 'order' => [['column' => 1, 'dir' => $direction]]]);
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/search.queries.php');
        $begin = strpos($source, '// Folder matches are a separate result type.');
        $end = strpos($source, '// Mass-operation rights,', $begin);
        self::assertNotFalse($begin);
        self::assertNotFalse($end);
        eval('namespace ' . __NAMESPACE__ . ';' . substr($source, $begin, $end - $begin));
        return ['total' => $iTotal, 'rows' => $rows, 'folders' => $folderResults, 'truncated' => $folderResultsTruncated];
    }

    /** Entity fragments and unreadable rows cannot consume pages or affect counts. */
    public function testCountsAndEveryPageContainOnlyReadableDisplayedMatches(): void
    {
        $filters = ['term' => 'amp', 'fields' => ['label']];
        $first = $this->search($filters);
        self::assertSame(65, $first['total']);
        self::assertSame(range(101, 125), array_map('intval', array_column($first['rows'], 'id')));
        self::assertSame(range(126, 150), array_map('intval', array_column($this->search($filters, 25)['rows'], 'id')));
        self::assertSame(range(151, 165), array_map('intval', array_column($this->search($filters, 50)['rows'], 'id')));
        self::assertSame([], $this->search($filters, 75)['rows']);
        self::assertSame(0, DB::$activeCursors, 'Page hydration must run after cursor cleanup');
    }

    /** The SQL prefilter accepts both encodings and the resulting count is exact. */
    public function testHtml5AndNumericApostrophesMatchWithoutEntityKeywords(): void
    {
        $result = $this->search(['term' => "O'Brien", 'fields' => ['label']]);
        self::assertSame(2, $result['total']);
        self::assertSame([500, 501], array_map('intval', array_column($result['rows'], 'id')));
        self::assertSame(0, $this->search(['term' => 'quot', 'fields' => ['label']])['total']);
    }

    /** Sort direction is applied before pagination, including deterministic ties. */
    public function testDescendingPagesRemainConsistent(): void
    {
        $page = $this->search(['term' => 'amp', 'fields' => ['label']], 20, 20, 'desc');
        self::assertSame(range(145, 126), array_map('intval', array_column($page['rows'], 'id')));
        self::assertSame(65, $page['total']);
    }

    /** Sorts are derived from displayed labels, not their entity prefixes. */
    public function testSortUsesDecodedLabelsBeforeChoosingAnItemPage(): void
    {
        $first = $this->search(['term' => 'sort', 'fields' => ['label']], 0, 1);
        self::assertSame(2, $first['total']);
        self::assertSame([601], array_map('intval', array_column($first['rows'], 'id')));
        self::assertSame([600], array_map('intval', array_column($this->search(['term' => 'sort', 'fields' => ['label']], 1, 1)['rows'], 'id')));
    }

    /** Folder-only matches stay separate from items and are limited after decoding. */
    public function testFolderResultsRejectFalseCandidatesBeforeTheirLimit(): void
    {
        $result = $this->search(['term' => 'amp', 'fields' => ['folder']], 0, 25, 'asc', array_merge([10], range(1001, 1165)));
        self::assertSame(0, $result['total']);
        self::assertSame(range(1101, 1120), array_column($result['folders'], 'id'));
        self::assertTrue($result['truncated']);
        $literal = $this->search(['term' => '<Guide>', 'fields' => ['folder']], 0, 25, 'asc', [5000]);
        self::assertSame([5000], array_column($literal['folders'], 'id'));
    }

    /** Execute the independent Select2 handler while intercepting its final exit only. */
    private function folderOptions(string $term, int $page, array $scope, int $selected = 0): array
    {
        $folderScope = $scope;
        $userId = 7;
        $userLogin = 'test.user';
        $request = Request::create('/', 'POST', ['type' => 'folder_options', 'term' => $term, 'page' => $page, 'selected_id' => $selected]);
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/search.queries.php');
        $start = strpos($source, "if (\$request->request->get('type') === 'folder_options')");
        $end = strpos($source, '// Remaining filter options.', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . ';' . str_replace('exit;', 'return;', substr($source, $start, $end - $start)));
            return json_decode((string) ob_get_contents(), true);
        } finally {
            ob_end_clean();
        }
    }

    /** Lookup paging and selected-ID restoration both remain ACL-bound. */
    public function testFolderOptionPagesAndPersonalRootNamesAreCorrect(): void
    {
        $scope = array_merge([10], range(1001, 1165));
        $first = $this->folderOptions('amp', 1, $scope);
        self::assertSame(range(1101, 1150), array_column($first['results'], 'id'));
        self::assertTrue($first['pagination']['more']);
        $second = $this->folderOptions('amp', 2, $scope);
        self::assertSame(range(1151, 1165), array_column($second['results'], 'id'));
        self::assertFalse($second['pagination']['more']);
        self::assertSame([], $this->folderOptions('', 1, [10], 99)['results']);
        self::assertSame([7], array_column($this->folderOptions('test.user', 1, [7])['results'], 'id'));
        self::assertSame([], $this->folderOptions('test.user', 1, [10])['results']);
    }

    /** One-to-one metadata joins do not need grouping that fails strict MariaDB mode. */
    public function testTextAndFacetOnlyQueriesAvoidNonAggregatedGrouping(): void
    {
        $this->search(['term' => 'amp', 'fields' => ['label']]);
        $this->search(['scope_perso' => 'shared', 'fields' => ['label']]);
        foreach (DB::$calls as [$sql]) {
            self::assertStringNotContainsString('GROUP BY c.id', $sql);
        }
    }

    /** Opening the hierarchy picker still reads only its requested SQL page. */
    public function testEmptyFolderLookupRetainsItsBoundedSqlQuery(): void
    {
        $result = $this->folderOptions('', 2, array_merge([10], range(1001, 1165)));
        self::assertSame(range(1050, 1099), array_column($result['results'], 'id'));
        self::assertTrue($result['pagination']['more']);
        self::assertCount(1, DB::$calls);
        self::assertStringContainsString('LIMIT %i_folder_option_limit OFFSET %i_folder_option_offset', DB::$calls[0][0]);
    }
}

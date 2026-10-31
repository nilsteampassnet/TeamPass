<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\PaletteStoredTextSearch;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../Fixtures/stored_text_search_db.php';
require_once __DIR__ . '/../../app/sources/search.functions.php';
require_once __DIR__ . '/../../app/sources/palette.functions.php';

/** Use the shared SQL fixture and reject queries while a streaming cursor is open. */
class DB extends \StoredTextSearchFixtureDatabase {}

/** Exercise prefix handling for every result family. */
function prefixTable(string $table): string { return 'palette_fixture_' . $table; }

/** Foreign personal folders override stale session grants. */
function getForeignPersonalFolderIds(int $userId): array { return [90]; }

/** Keep only transport encryption outside the test. */
function prepareExchangedData(array $payload, string $mode): string { return json_encode($payload); }

/** Path hydration deliberately runs another query to verify cursor cleanup. */
class NestedTree
{
    /** Accept the production tree configuration without bootstrapping the application. */
    public function __construct(...$arguments) {}

    /** Return a deterministic, database-backed path for the selected authorized node. */
    public function getPath(int $id, bool $includeSelf): array
    {
        return array_merge([(object) ['id' => 99, 'title' => 'Hidden ancestor']],
            array_map(static fn (array $row): object => (object) $row,
                DB::query('SELECT id,title FROM palette_fixture_nested_tree WHERE id=%i_path_id', ['path_id' => $id])));
    }
}

/** All three result limits must be applied after decoded matching and relevance. */
#[RequiresPhpExtension('sqlite3')]
class PaletteStoredTextSearchTest extends TestCase
{
    protected function setUp(): void
    {
        DB::reset();
        DB::$connection->exec('CREATE TABLE palette_fixture_nested_tree (id INTEGER PRIMARY KEY,title TEXT);
            CREATE TABLE palette_fixture_items (id INTEGER PRIMARY KEY,deleted_at INTEGER,restricted_to TEXT DEFAULT "");
            CREATE TABLE palette_fixture_cache (id INTEGER PRIMARY KEY,label TEXT,login TEXT DEFAULT "",url TEXT DEFAULT "",tags TEXT DEFAULT "",id_tree INTEGER DEFAULT 10,perso INTEGER DEFAULT 0,author TEXT DEFAULT "7");
            CREATE TABLE palette_fixture_restriction_to_roles (item_id INTEGER,role_id INTEGER);
            CREATE TABLE palette_fixture_kb (id INTEGER PRIMARY KEY,label TEXT,description TEXT,category_id INTEGER DEFAULT 1,deleted_at INTEGER);
            CREATE TABLE palette_fixture_kb_categories (id INTEGER PRIMARY KEY,category TEXT);
            INSERT INTO palette_fixture_kb_categories VALUES (1,"Test");
            INSERT INTO palette_fixture_nested_tree VALUES (10,"Shared"),(90,"Amp foreign"),(99,"Amp hidden");');
        foreach (range(1, 70) as $id) {
            $label = $id <= 60 ? 'R&amp;D &quot;Lab&quot;' : 'Ampere ' . sprintf('%03d', $id);
            $this->insert($id, $label);
        }
        $this->insert(999, 'Amp');
        $this->insert(100, 'O&apos;Brien');
        $this->insert(101, 'O&#039;Brien');
        $this->insert(102, "O'Brien");
        $this->insert(103, '100%_ literal');
        $this->insert(104, '100XX literal');
        $this->insert(910, 'Amp personal hidden');
        $this->insert(911, 'Amp deleted');
        $this->insert(912, 'Amp restricted');
        $this->insert(913, 'O&apos;Brien role');
        DB::$connection->exec('UPDATE palette_fixture_cache SET perso=1,author="42" WHERE id=910;
            UPDATE palette_fixture_items SET deleted_at=1 WHERE id=911;
            UPDATE palette_fixture_items SET restricted_to="42" WHERE id=912;
            INSERT INTO palette_fixture_restriction_to_roles VALUES (913,3);
            DELETE FROM palette_fixture_nested_tree WHERE id IN (1910,1911,1912,1913);
            DELETE FROM palette_fixture_kb WHERE id IN (910,911,912,913);');
    }

    /** Give each result family the same mixed storage encodings. */
    private function insert(int $id, string $label): void
    {
        $quoted = "'" . \SQLite3::escapeString($label) . "'";
        DB::$connection->exec('INSERT INTO palette_fixture_items (id) VALUES (' . $id . ');
            INSERT INTO palette_fixture_cache (id,label) VALUES (' . $id . ',' . $quoted . ');
            INSERT INTO palette_fixture_nested_tree (id,title) VALUES (' . (1000 + $id) . ',' . $quoted . ');
            INSERT INTO palette_fixture_kb (id,label,description) VALUES (' . $id . ',' . $quoted . ',\'<p>Body</p>\');');
    }

    /** Execute the real input-reading and controller case, leaving authorization gates intact. */
    private function request(string $term, array $overrides = [], bool $kbEnabled = true): array
    {
        $scope = array_map('intval', array_column(DB::query('SELECT id FROM palette_fixture_nested_tree WHERE id != 99'), 'id'));
        $session = new class ($overrides, $scope) {
            private array $values;
            /** Supply deterministic user and scope state. */
            public function __construct(array $overrides, array $scope)
            {
                $this->values = $overrides + ['user-id' => 7, 'user-admin' => 0, 'user-accessible_folders' => $scope,
                    'user-forbiden_personal_folders' => [], 'user-roles_array' => []];
            }
            /** Return only local fixture values. */
            public function get(string $key): mixed { return $this->values[$key] ?? null; }
        };
        $checkUserAccess = new class {
            /** Permit the KB page so its independent feature/admin gates are exercised. */
            public function userAccessPage(string $page): bool { return true; }
        };
        $SETTINGS = ['enable_kb' => $kbEnabled ? 1 : 0];
        $request = Request::create('/', 'POST', ['term' => $term]);
        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/palette.queries.php'));
        $inputStart = strpos($source, '$post_term =');
        $inputEnd = strpos($source, "\n\n", $inputStart);
        eval(substr($source, $inputStart, $inputEnd - $inputStart));
        $start = strpos($source, "case 'palette_search':");
        $end = strpos($source, 'default:', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . '; switch ("palette_search") {' . substr($source, $start, $end - $start) . '}');
            return json_decode((string) ob_get_contents(), true);
        } finally {
            ob_end_clean();
        }
    }

    /** Later exact matches outrank earlier prefixes after false entity candidates. */
    public function testAllFamiliesRankAndLimitActualDisplayedMatches(): void
    {
        $result = $this->request('amp');
        self::assertSame([999, 61, 62, 63, 64, 65, 66, 67], array_column($result['items'], 'id'));
        self::assertSame([1999, 1061, 1062, 1063, 1064], array_column($result['folders'], 'id'));
        self::assertSame([999, 61, 62, 63, 64], array_column($result['kb'], 'id'));
        foreach (array_merge($result['items'], $result['folders']) as $row) {
            self::assertStringNotContainsString('Hidden ancestor', $row['path']);
        }
        self::assertSame(0, DB::$activeCursors);
    }

    /** Input apostrophes and LIKE wildcards remain literal, with encoded response contracts preserved. */
    public function testInputTextIsNotHtmlEncodedBeforeSearching(): void
    {
        self::assertSame([100, 101, 102], array_column($this->request("O'Brien")['items'], 'id'));
        self::assertSame([103], array_column($this->request('100%_')['items'], 'id'));
        self::assertSame([], $this->request('quot')['items']);
    }

    /** Restrictions are applied in SQL and KB has its own feature/admin gates. */
    public function testPermissionsAndFeatureGatesApplyBeforeLimits(): void
    {
        self::assertSame([], $this->request("O'Brien role")['items']);
        self::assertSame([913], array_column($this->request("O'Brien role", ['user-roles_array' => [3]])['items'], 'id'));
        self::assertSame([], $this->request('amp', [], false)['kb']);
        self::assertSame([], $this->request('amp', ['user-admin' => 1])['kb']);
        $empty = $this->request('amp', ['user-accessible_folders' => []], false);
        self::assertSame([], $empty['items']);
        self::assertSame([], $empty['folders']);
    }
}

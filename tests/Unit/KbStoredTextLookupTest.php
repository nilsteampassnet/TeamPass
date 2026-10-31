<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\KbStoredTextLookup;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Fixtures/stored_text_search_db.php';
require_once __DIR__ . '/../../app/sources/search.functions.php';

/** Keep the controller's SQL scoped to a disposable database. */
class DB extends \StoredTextSearchFixtureDatabase {}

/** Supply a non-default prefix to every production query. */
function prefixTable(string $table): string { return 'lookup_fixture_' . $table; }

/** Exercise both allowed users and the early administrator gate. */
function kbIsAdmin(object $session): bool { return (int) $session->get('user-admin') === 1; }

/** Supply the session's visible folders, including a stale foreign personal grant. */
function kbAccessibleFolders(object $session): array { return $session->get('user-accessible_folders'); }

/** A foreign personal tree remains excluded even when stale session grants name it. */
function getForeignPersonalFolderIds(int $userId): array { return [90]; }

/** Bypass only transport encryption; response shapes remain those of the controller. */
function prepareExchangedData(mixed $payload, string $mode): mixed
{
    return $mode === 'decode' ? json_decode($payload, true) : json_encode($payload);
}

/** Execute both shipped suggestion cases with encoded data and real SQL predicates. */
#[RequiresPhpExtension('sqlite3')]
class KbStoredTextLookupTest extends TestCase
{
    protected function setUp(): void
    {
        DB::reset();
        DB::$connection->exec('CREATE TABLE lookup_fixture_kb_categories (id INTEGER PRIMARY KEY, category TEXT);
            CREATE TABLE lookup_fixture_items (id INTEGER PRIMARY KEY, label TEXT, id_tree INTEGER, inactif INTEGER DEFAULT 0,
                deleted_at INTEGER, restricted_to TEXT DEFAULT "");
            CREATE TABLE lookup_fixture_restriction_to_roles (item_id INTEGER, role_id INTEGER);');
        $insertCategory = DB::$connection->prepare('INSERT INTO lookup_fixture_kb_categories VALUES (:id, :label)');
        $insertItem = DB::$connection->prepare('INSERT INTO lookup_fixture_items (id,label,id_tree) VALUES (:id,:label,10)');
        foreach (range(1, 70) as $id) {
            $label = $id <= 45 ? 'R&amp;D &quot;Lab&quot;' : 'Ampere ' . sprintf('%02d', $id);
            foreach ([$insertCategory, $insertItem] as $statement) {
                $statement->bindValue(':id', $id, SQLITE3_INTEGER);
                $statement->bindValue(':label', $label);
                $statement->execute();
            }
        }
        DB::$connection->exec("INSERT INTO lookup_fixture_items (id,label,id_tree,restricted_to) VALUES
            (100,'O&apos;Brien',10,''), (101,'O&#039;Brien',10,''), (102,'O&apos;Brien hidden',99,''),
            (103,'O&apos;Brien foreign',90,''), (104,'O&apos;Brien restricted',10,'42');");
    }

    /** Run one controller case, preserving its checks, SQL and result formatting. */
    private function request(string $action, string $term, array $overrides = [], string $requestKey = 'session-key'): array
    {
        $session = new class ($overrides) {
            private array $values;
            /** Keep credentials and permissions local to this fixture. */
            public function __construct(array $overrides)
            {
                $this->values = $overrides + ['user-id' => 7, 'key' => 'session-key', 'user-admin' => 0,
                    'user-accessible_folders' => [10, 90], 'user-forbiden_personal_folders' => [], 'user-roles_array' => []];
            }
            /** Return the minimal session fields used by the production lookup. */
            public function get(string $key): mixed { return $this->values[$key] ?? null; }
        };
        $lang = new class {
            /** Keep error messages deterministic without loading a catalogue. */
            public function get(string $key): string { return $key; }
        };
        $key = $requestKey;
        $data = json_encode(['term' => $term]);
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/kb.queries.php');
        $start = strpos($source, "case 'search_categories':");
        $end = strpos($source, "case 'upload_attachment':", $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . '; switch ($action) {' . substr($source, $start, $end - $start) . '}');
            return json_decode((string) ob_get_contents(), true);
        } finally {
            ob_end_clean();
        }
    }

    /** False entity candidates cannot consume either suggestion limit. */
    public function testBothLookupsReturnTwentyRealMatchesAfterFalseCandidates(): void
    {
        $categories = $this->request('search_categories', 'amp');
        self::assertCount(20, $categories['categories']);
        self::assertSame('Ampere 46', $categories['categories'][0]);
        $items = $this->request('search_items', 'amp');
        self::assertSame(range(46, 65), array_column($items['results'], 'id'));
        self::assertSame(0, DB::$activeCursors);
    }

    /** Both apostrophe spellings match; inaccessible and restricted items never enter the stream. */
    public function testEncodedNamesMatchWithinTheAuthorizedScope(): void
    {
        $items = $this->request('search_items', "O'Brien");
        self::assertSame([100, 101], array_column($items['results'], 'id'));
        self::assertSame('O&apos;Brien', $items['results'][0]['text'], 'The storage/output contract stays unchanged');
        self::assertSame([], $this->request('search_items', 'quot')['results']);
        self::assertSame(0, DB::$activeCursors);
    }

    /** Authentication, admin and empty-scope gates happen before reading candidates. */
    public function testUnauthorizedRequestsNeverOpenACursor(): void
    {
        foreach ([['user-admin' => 1], ['user-accessible_folders' => []]] as $overrides) {
            DB::$calls = [];
            $this->request('search_items', 'amp', $overrides);
            self::assertSame([], DB::$calls);
        }
        DB::$calls = [];
        self::assertTrue($this->request('search_categories', 'amp', [], 'wrong-key')['error']);
        self::assertSame([], DB::$calls);
    }
}

<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace FolderParentLookupRuntime;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/folder_list_logic.php';

/** A database recorder scoped to this adapter test, never a production connection. */
class DB
{
    public static array $calls = [];

    /** Record the bounded complexity query. */
    public static function query(...$args): array
    {
        self::$calls[] = $args;
        return [['intitule' => 2, 'valeur' => 60]];
    }
}

/** Return a non-default prefix to exercise parameterized table selection. */
function prefixTable(string $table): string
{
    return 'fixture_' . $table;
}

/** Include a legacy personal descendant with an unset flag. */
function getPersonalFolderIdsWithDescendants(): array
{
    return [4, 5];
}

/** Capture the controller response without encryption or real sessions. */
function prepareExchangedData(array $data, string $mode): string
{
    return (string) json_encode($data);
}

/** Exercise the shipped controller, plus pagination and privacy decisions. */
class FolderParentLookupTest extends TestCase
{
    private function nodes(int $count = 8): array
    {
        $nodes = [];
        for ($id = 1; $id <= $count; $id++) {
            $nodes[$id] = (object) ['id' => $id, 'parent_id' => 0, 'title' => 'Folder ' . $id,
                'bloquer_creation' => 1, 'bloquer_modification' => 0];
        }
        return $nodes;
    }

    /** Check page boundaries across a production-sized authorized snapshot. */
    public function testPaginationIsBoundedAndDoesNotSkipOrRepeatResults(): void
    {
        $nodes = $this->nodes(3161);
        $first = \folderListParentPage($nodes, array_keys($nodes), [], true, 'Root', '', 1);
        $second = \folderListParentPage($nodes, array_keys($nodes), [], true, 'Root', '', 2);
        self::assertCount(30, $first['results']);
        self::assertCount(30, $second['results']);
        self::assertSame('0', $first['results'][0]['id']);
        self::assertSame('30', $second['results'][0]['id']);
        self::assertTrue($first['pagination']['more']);
        self::assertSame([], array_intersect(array_column($first['results'], 'id'), array_column($second['results'], 'id')));
        $last = \folderListParentPage($nodes, array_keys($nodes), [], true, 'Root', '', 106);
        self::assertCount(12, $last['results']);
        self::assertFalse($last['pagination']['more']);
    }

    /** Keep lookup results within the permitted scope and search complete paths. */
    public function testScopeMoveExclusionsUnicodeAndDuplicateNames(): void
    {
        $nodes = $this->nodes();
        $nodes[1]->title = 'Équipe';
        $nodes[2]->parent_id = 1;
        $nodes[2]->title = 'Production';
        $nodes[3]->parent_id = 2;
        $nodes[6]->title = 'Production';
        $result = \folderListParentPage($nodes, ['1', '2', '3', '4', '5', '6', '7'], [4, 5, 7], false, 'Root', '', 1, 2);
        self::assertSame(['1', '6'], array_column($result['results'], 'id'));
        $search = \folderListParentPage($nodes, [1, 2, 6], [], false, 'Root', 'équipe / prod', 1);
        self::assertSame(['2'], array_column($search['results'], 'id'));
        self::assertSame(1, $search['results'][0]['add_is_blocked']);
        $privateAncestor = \folderListParentPage($nodes, [2], [], false, 'Root', 'Équipe', 1);
        self::assertSame([], $privateAncestor['results']);
    }

    /** Search decoded names and paths while preserving encoded labels in the response. */
    public function testSearchMatchesSpecialCharactersWithoutMatchingEntityNames(): void
    {
        $nodes = $this->nodes(2);
        $nodes[1]->title = 'R&amp;D';
        $nodes[2]->parent_id = 1;
        $nodes[2]->title = 'O&#039;Brien &quot;Lab&quot; &lt;Test&gt;';
        foreach (['R&D', "O'Brien", '"Lab"', '<Test>', "R&D / O'Brien"] as $term) {
            $result = \folderListParentPage($nodes, [1, 2], [], false, 'Root', $term, 1);
            self::assertContains('2', array_column($result['results'], 'id'), $term);
            self::assertSame('R&amp;D / O&#039;Brien &quot;Lab&quot; &lt;Test&gt;', array_column($result['results'], 'text', 'id')[2]);
        }
        foreach (['amp', 'quot', '#039', '&lt;', '&gt;'] as $term) {
            self::assertSame([], \folderListParentPage($nodes, [1, 2], [], false, 'Root', $term, 1)['results'], $term);
        }
    }

    private function request(array $overrides = [], string $key = 'session-key', bool $pageAllowed = true, array $posted = []): array
    {
        DB::$calls = [];
        $values = array_merge(['key' => 'session-key', 'user-accessible_folders' => range(1, 8),
            'user-admin' => 0, 'user-manager' => 1, 'user-can_create_root_folder' => 0,
            'user-read_only' => 0, 'user-read_only_folders' => [6], 'user-no_access_folders' => [7]], $overrides);
        $session = new class($values) {
            /** Store the fixture session. */
            public function __construct(private array $values) {}
            /** Read a fixture session value. */
            public function get(string $key) { return $this->values[$key] ?? null; }
        };
        $checkUserAccess = new class($pageAllowed) {
            /** Store the fixture page permission. */
            public function __construct(private bool $allowed) {}
            /** Return the fixture page permission. */
            public function userAccessPage(string $page): bool { return $this->allowed; }
        };
        $tree = new class($this->nodes()) {
            public int $reads = 0;
            /** Store the fixture tree snapshot. */
            public function __construct(private array $nodes) {}
            /** Record each snapshot load. */
            public function getDescendants(): array { $this->reads++; return $this->nodes; }
        };
        $request = (object) ['request' => new class($posted) {
            /** Store the fixture request parameters. */
            public function __construct(private array $values) {}
            /** Read a fixture request parameter. */
            public function get(string $key, $default = null) { return $this->values[$key] ?? $default; }
        }];
        $lang = new class {
            /** Resolve a translation without loading real session state. */
            public function get(string $key): string { return $key; }
        };
        $post_key = $key;
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/folders.queries.php');
        $start = strpos($source, "case 'search_folder_parents':");
        $end = strpos($source, "\n    }", $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        ob_start();
        eval('namespace FolderParentLookupRuntime; switch ("search_folder_parents") {' . substr($source, $start, $end - $start) . '}');
        $result = json_decode((string) ob_get_clean(), true);
        $result['tree_reads'] = $tree->reads;
        return $result;
    }

    /** Reject unauthorized lookups before reading any folders. */
    public function testControllerEnforcesKeyPageAndReadOnlyGatesBeforeReadingFolders(): void
    {
        foreach ([[$this->request([], 'wrong')], [$this->request([], 'session-key', false)],
            [$this->request(['user-read_only' => 1])]] as [$result]) {
            self::assertTrue($result['error']);
            self::assertSame(0, $result['tree_reads']);
        }
    }

    /** Exercise target exclusions, root privileges and the bulk metadata query. */
    public function testControllerExcludesPersonalForbiddenAndReadOnlyTargetsAndBoundsQueries(): void
    {
        $result = $this->request();
        self::assertFalse($result['error']);
        self::assertSame(['0', '1', '2', '3', '8'], array_column($result['results'], 'id'));
        self::assertSame(60, $result['results'][2]['complexity']);
        self::assertSame(1, $result['tree_reads']);
        self::assertCount(1, DB::$calls);
        self::assertStringContainsString('fixture_misc', DB::$calls[0][0]);
        foreach ([4, 6, 7, 99] as $source) {
            self::assertTrue($this->request([], 'session-key', true, ['exclude_id' => $source])['error']);
        }
        $result = $this->request(['user-manager' => 0], 'session-key', true);
        self::assertNotContains('0', array_column($result['results'], 'id'));
    }
}

<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace RoleMatrixRuntime;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/role_matrix_logic.php';

/** Record the shipped controller's bulk reads without a database connection. */
class DB
{
    public static array $calls = [];
    public static array $permissions = [];

    /** Capture query arguments and return fixture permissions. */
    public static function query(...$args): array
    {
        self::$calls[] = $args;
        return self::$permissions;
    }

    /** Record transaction boundaries and writes. */
    public static function startTransaction(): void { self::$calls[] = ['begin']; }
    /** Record a successful transaction. */
    public static function commit(): void { self::$calls[] = ['commit']; }
    /** Record a failed transaction. */
    public static function rollback(): void { self::$calls[] = ['rollback']; }
    /** Record the scoped deletion of old permissions. */
    public static function delete(...$args): void { self::$calls[] = ['delete', ...$args]; }
    /** Record new permission rows. */
    public static function insert(...$args): void { self::$calls[] = ['insert', ...$args]; }
    /** No connected users are present in this fixture. */
    public static function queryFirstColumn(...$args): array { return []; }
}

/** Exercise a non-default table prefix. */
function prefixTable(string $name): string { return 'fixture_' . $name; }
/** Include a legacy personal descendant whose own flag is unset. */
function getPersonalFolderIdsWithDescendants(): array { return [4, 5]; }
/** Resolve role authorization without a production session. */
function callerGrantableRoleIds(): array { return [7]; }
/** Capture the encrypted-exchange contract as JSON. */
function prepareExchangedData($data, string $mode) { return $mode === 'decode' ? json_decode($data, true) : json_encode($data); }
/** Cache invalidation is outside the permission-write fixture. */
function invalidateCacheForFolderUsers(...$args): void {}

/** Regression coverage for matrix cost, authorization and propagation. */
class RoleMatrixTest extends TestCase
{
    private function nodes(int $count = 8): array
    {
        $nodes = [];
        for ($id = 1; $id <= $count; $id++) {
            $nodes[$id] = (object) ['id' => $id, 'parent_id' => $id === 1 ? 0 : 1,
                'nlevel' => $id === 1 ? 1 : 2, 'title' => 'Folder ' . $id];
        }
        return $nodes;
    }

    /** Keep labels encoded and omit personal, forbidden and inaccessible ancestor names. */
    public function testMatrixScopePathsAndAllPermissionTypes(): void
    {
        $nodes = $this->nodes();
        $nodes[1]->title = 'R&amp;D';
        $nodes[2]->title = 'O&#039;Brien &quot;Lab&quot; &lt;Test&gt;';
        $permissions = [];
        foreach (['W', 'R', 'ND', 'NE', 'NDNE'] as $index => $access) {
            $permissions[] = ['folder_id' => $index + 1, 'type' => $access];
        }
        $matrix = \roleMatrixBuild($nodes, array_map('strval', range(1, 8)), [4, 5, 7], $permissions);
        self::assertSame([1, 2, 3, 6, 8], array_column($matrix, 'id'));
        self::assertSame(['R&amp;D'], $matrix[1]['path']);
        self::assertSame([1], $matrix[1]['parents']);
        self::assertSame($nodes[2]->title, $matrix[1]['title']);
        self::assertSame(['W', 'R', 'ND', 'none', 'none'], array_column($matrix, 'access'));
        $privateParent = \roleMatrixBuild($nodes, [2], [], []);
        self::assertSame([], $privateParent[0]['path']);
        self::assertSame([], $privateParent[0]['parents']);
        self::assertSame(2, $privateParent[0]['level']);
        self::assertSame(1, $privateParent[0]['parentId']);
        self::assertSame(['NE', 'NDNE'], array_column(\roleMatrixBuild($nodes, [4, 5], [], $permissions), 'access'));
    }

    /** Hidden selections and propagation must never bypass the current server scope. */
    public function testTargetsValidateEveryExplicitSelectionAndBoundPropagation(): void
    {
        $matrix = \roleMatrixBuild($this->nodes(), range(1, 8), [4, 5, 7], []);
        self::assertSame([1, 2, 3, 6, 8], \roleMatrixTargets($matrix, ['1'], true));
        self::assertSame([1, 2], \roleMatrixTargets($matrix, ['1', 2, '2'], false));
        foreach ([[], [4], [1, 5], [7], [99], [0], [-1], ['1x'], [[]]] as $selection) {
            self::assertNull(\roleMatrixTargets($matrix, $selection, true));
        }
        $large = \roleMatrixBuild($this->nodes(3161), range(1, 3161), [], []);
        self::assertSame(range(1, 3161), \roleMatrixTargets($large, range(1, 3161), true));
    }

    private function request(int $count, array $overrides = [], string $key = 'fixture-key', int $role = 7,
        ?array $submission = null): array
    {
        DB::$calls = [];
        DB::$permissions = [['folder_id' => 2, 'type' => 'NDNE']];
        $session = new class(array_merge(['key' => 'fixture-key', 'user-read_only' => 0,
            'user-accessible_folders' => range(1, $count), 'user-no_access_folders' => [7]], $overrides)) {
            /** Hold fixture session values. */
            public function __construct(private array $values) {}
            /** Read a fixture session field. */
            public function get(string $key) { return $this->values[$key] ?? null; }
        };
        $tree = new class($this->nodes($count)) {
            public int $reads = 0;
            /** Hold the tree snapshot. */
            public function __construct(private array $nodes) {}
            /** Count bulk reads; per-folder methods intentionally do not exist. */
            public function getDescendants(): array { $this->reads++; return $this->nodes; }
        };
        $request = (object) ['request' => new class($role) {
            /** Hold the selected role. */
            public function __construct(private int $role) {}
            /** Return the fixture role ID. */
            public function get(string $key, $default = null): int { return $this->role; }
        }];
        $lang = new class {
            /** Resolve fixture translations. */
            public function get(string $key): string { return $key; }
        };
        $post_key = $key;
        $post_data = json_encode($submission);
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/roles.queries.php');
        $action = $submission === null ? 'build_matrix' : 'change_access_right_on_folder';
        $start = strpos($source, "case '" . $action . "':");
        $end = strpos($source, $submission === null ? "case 'change_access_right_on_folder':" : "case 'change_role_definition':", $start);
        ob_start();
        eval('namespace ' . __NAMESPACE__ . '; switch ("' . $action . '") {' . substr($source, $start, $end - $start) . '}');
        $response = json_decode((string) ob_get_clean(), true);
        return [$response, $tree->reads, DB::$calls];
    }

    /** The number of database/tree reads must not grow with the folder count. */
    public function testShippedControllerUsesOneTreeReadAndOnePermissionQueryAtScale(): void
    {
        foreach ([8, 3161] as $count) {
            [$response, $reads, $queries] = $this->request($count);
            self::assertFalse($response['error']);
            self::assertCount($count - 3, $response['matrix']);
            self::assertSame(1, $reads);
            self::assertSame([['SELECT folder_id, type FROM fixture_roles_values WHERE role_id = %i', 7]], $queries);
            self::assertSame('NDNE', $response['matrix'][1]['access']);
        }
    }

    /** Invalid keys, read-only sessions and out-of-scope roles must not load data. */
    public function testControllerRejectsUnauthorizedMatrixRequestsBeforeReads(): void
    {
        foreach ([[[], 'bad-key', 7], [['user-read_only' => 1], 'fixture-key', 7],
            [['user-read_only' => '1'], 'fixture-key', 7], [[], 'fixture-key', 99], [[], 'fixture-key', 0]] as $case) {
            [$response, $reads, $queries] = $this->request(8, ...$case);
            self::assertTrue($response['error']);
            self::assertSame(0, $reads);
            self::assertSame([], $queries);
        }
    }

    /** Direct submissions and propagated targets are revalidated by the shipped write handler. */
    public function testWriteControllerRejectsForgedTargetsAndBoundsPropagation(): void
    {
        $submission = ['roleId' => 7, 'selectedFolders' => ['1'], 'access' => 'NDNE', 'propagate' => 1];
        [$response, $reads, $queries] = $this->request(8, [], 'fixture-key', 7, $submission);
        self::assertFalse($response['error']);
        self::assertSame(1, $reads);
        self::assertSame(['delete', 'fixture_roles_values', 'role_id = %i AND folder_id IN %li', 7, [1, 2, 3, 6, 8]], $queries[1]);
        self::assertSame(['begin'], $queries[0]);
        self::assertSame(['commit'], $queries[count($queries) - 1]);
        foreach ([[4], [5], [7], [99], ['1x'], [1, 4]] as $ids) {
            [$response, , $queries] = $this->request(8, [], 'fixture-key', 7, array_replace($submission, ['selectedFolders' => $ids]));
            self::assertTrue($response['error']);
            self::assertSame([], $queries);
        }
        foreach ([[[], 'bad-key', 7], [['user-read_only' => '1'], 'fixture-key', 7], [[], 'fixture-key', 99]] as [$values, $key, $role]) {
            [$response, $reads, $queries] = $this->request(8, $values, $key, $role, array_replace($submission, ['roleId' => $role]));
            self::assertTrue($response['error']);
            self::assertSame(0, $reads);
            self::assertSame([], $queries);
        }
    }
}

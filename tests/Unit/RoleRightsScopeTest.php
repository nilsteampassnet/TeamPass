<?php

declare(strict_types=1);

namespace RoleRightsScopeRuntime;

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) behind the permission matrix and the rights write
// handler of the Roles page.
require_once __DIR__ . '/../../app/sources/role_matrix_logic.php';

/** Records the calls the evaluated handlers make, without a database. */
class DB
{
    public static array $calls = [];

    /** Record a read and return nothing. */
    public static function query(...$args): array { self::$calls[] = ['query', ...$args]; return []; }
    /** Record a single-row read and return nothing. */
    public static function queryFirstRow(...$args): ?array { self::$calls[] = ['queryFirstRow', ...$args]; return null; }
    /** No user holds the role in this fixture. */
    public static function queryFirstColumn(...$args): array { self::$calls[] = ['queryFirstColumn', ...$args]; return []; }
    /** Row count of the last read. */
    public static function count(): int { return 0; }
    /** Record a transaction start. */
    public static function startTransaction(): void { self::$calls[] = ['begin']; }
    /** Record a commit. */
    public static function commit(): void { self::$calls[] = ['commit']; }
    /** Record a rollback. */
    public static function rollback(): void { self::$calls[] = ['rollback']; }
    /** Record a deletion. */
    public static function delete(...$args): void { self::$calls[] = ['delete', ...$args]; }
    /** Record an insertion. */
    public static function insert(...$args): void { self::$calls[] = ['insert', ...$args]; }
    /** Record an update. */
    public static function update(...$args): void { self::$calls[] = ['update', ...$args]; }
    /** Id of the last insertion. */
    public static function insertId(): int { return 42; }
}

/** Fixture state shared with the namespaced stubs below. */
class Fixture
{
    public static int $roleIdInput = 0;
    public static array $session = [];
}

/** Use the default table prefix. */
function prefixTable(string $name): string { return 'teampass_' . $name; }
/** The caller holds or created role 7 only. */
function callerGrantableRoleIds(): array { return (int) (Fixture::$session['user-admin'] ?? 0) === 1 ? [7, 99] : [7]; }
/**
 * Tree 1 > (2, 3), 4 > 5, personal 6 > 7. The caller can access 1, 2, 3, 6, 7 and is denied 3.
 * The shipped matrix logic resolves what remains: folders 1 and 2.
 */
function getPersonalFolderIdsWithDescendants(): array { return [6, 7]; }
/** The exchange layer as plain JSON. */
function prepareExchangedData($data, string $mode) { return $mode === 'decode' ? json_decode((string) $data, true) : json_encode($data); }
/** Cache invalidation is outside these fixtures. */
function invalidateCacheForFolderUsers(...$args): void {}
/** No WebSocket in these fixtures. */
function emitWebSocketEvent(...$args): void {}
/** Folder categories are outside these fixtures. */
function handleFoldersCategories(...$args): void {}

/**
 * Regression tests for GHSA-j3ph-4847-695h: the handlers of roles.queries.php must keep a
 * manager within their own roles and folders, whatever the request contains.
 */
class RoleRightsScopeTest extends TestCase
{
    // The pure scope logic (roleMatrixBuild(), roleMatrixTargets()) is covered by RoleMatrixTest;
    // the tests below run the shipped handlers.

    public function testRightsChangeWritesOnlyAssignableFolders(): void
    {
        [$response, $calls] = $this->handle('change_access_right_on_folder', [], $this->rights([1], 1));

        $this->assertFalse($response['error']);
        $this->assertSame(['begin'], $calls[0]);
        $this->assertSame(['delete', 'teampass_roles_values', 'role_id = %i AND folder_id IN %li', 7, [1, 2]], $calls[1]);
        $this->assertSame([1, 2], array_column(array_column(array_filter($calls, fn ($call) => $call[0] === 'insert'), 2), 'folder_id'));
    }

    public function testRightsChangeRefusesForgedRequestsWithoutWriting(): void
    {
        $cases = [
            'role the caller may not grant' => $this->rights([1], 1, 99),
            'folder the caller cannot access' => $this->rights([4], 0),
            'personal folder' => $this->rights([6], 0),
            'folder denied to the caller' => $this->rights([3], 0),
            // NestedTree::getDescendants() returns the whole tree for an unknown node.
            'unknown folder with propagation' => $this->rights([999], 1),
            'one bad folder among good ones' => $this->rights([1, 4], 0),
            'malformed folder id' => $this->rights(['1 OR 1=1'], 0),
            'no folder' => $this->rights([], 1),
        ];
        foreach ($cases as $label => $submission) {
            [$response, $calls] = $this->handle('change_access_right_on_folder', [], $submission);
            $this->assertTrue($response['error'], $label);
            $this->assertSame([], $this->writes($calls), $label);
        }
    }

    public function testReadOnlySessionStoredAsStringIsRefused(): void
    {
        [$response, $calls] = $this->handle('change_access_right_on_folder', ['user-read_only' => '1'], $this->rights([1], 0));

        $this->assertTrue($response['error']);
        $this->assertSame([], $this->writes($calls));
    }

    public function testMatrixIsLimitedToGrantableRolesAndAssignableFolders(): void
    {
        Fixture::$roleIdInput = 99;
        [$response, $calls] = $this->handle('build_matrix', [], null);
        $this->assertTrue($response['error']);
        $this->assertSame([], $calls);

        Fixture::$roleIdInput = 7;
        [$response] = $this->handle('build_matrix', [], null);
        $this->assertFalse($response['error']);
        $this->assertSame([1, 2], array_map('intval', array_column($response['matrix'], 'id')));
    }

    public function testRoleOutsideScopeCannotBeEditedOrDeleted(): void
    {
        foreach (['edit_role', 'edit_folder'] as $action) {
            [$response, $calls] = $this->handle('change_role_definition', [], [
                'folderId' => 99, 'complexity' => 0, 'label' => 'Renamed', 'allowEdit' => 1, 'action' => $action,
            ]);
            $this->assertTrue($response['error'], $action);
            $this->assertSame([], $calls, $action);
        }

        [$response, $calls] = $this->handle('delete_role', [], ['roleId' => 99]);
        $this->assertTrue($response['error']);
        $this->assertSame([], $calls);

        [$response, $calls] = $this->handle('delete_role', [], ['roleId' => 7]);
        $this->assertFalse($response['error']);
        $this->assertSame(['delete', 'teampass_roles_title', 'id = %i', 7], $calls[0]);
    }

    public function testAdGroupMappingIsReservedToAdministrators(): void
    {
        $mapping = ['roleId' => 99, 'adGroupId' => 'CN=Staff', 'adGroupLabel' => 'Staff'];

        [$response, $calls] = $this->handle('map_role_with_adgroup', [], $mapping);
        $this->assertTrue($response['error']);
        $this->assertSame([], $calls);

        [$response] = $this->handle('get_list_of_groups_in_ldap', [], null);
        $this->assertTrue($response['error']);

        [$response, $calls] = $this->handle('map_role_with_adgroup', ['user-admin' => 1], $mapping);
        $this->assertFalse($response['error']);
        $this->assertSame('insert', $calls[1][0]);
    }

    /** Build a change_access_right_on_folder submission. */
    private function rights(array $folders, int $propagate, int $roleId = 7): array
    {
        return ['roleId' => $roleId, 'selectedFolders' => $folders, 'access' => 'W', 'propagate' => $propagate];
    }

    /** Keep the calls that change the database. */
    private function writes(array $calls): array
    {
        return array_values(array_filter($calls, fn ($call) => in_array($call[0], ['delete', 'insert', 'update', 'begin'], true)));
    }

    /**
     * Evaluate one case of the shipped roles.queries.php switch as a manager.
     *
     * @return array{0: array, 1: array} Decoded response and recorded database calls.
     */
    private function handle(string $action, array $sessionOverrides, ?array $submission): array
    {
        DB::$calls = [];
        Fixture::$session = array_merge([
            'key' => 'fixture-key',
            'user-id' => 10,
            'user-admin' => 0,
            'user-read_only' => 0,
            'user-accessible_folders' => ['1', '2', '3', '6', '7'],
            'user-no_access_folders' => ['3'],
        ], $sessionOverrides);
        $session = new class {
            /** Read a fixture session value. */
            public function get(string $key) { return Fixture::$session[$key] ?? null; }
            /** Write a fixture session value. */
            public function set(string $key, $value): void { Fixture::$session[$key] = $value; }
        };
        $tree = new class {
            /** Mirror NestedTree: an unknown node yields the whole tree. */
            public function getDescendants($folderId = 0, $includeSelf = false, $childrenOnly = false, $idsOnly = false): array
            {
                $children = [1 => [2, 3], 4 => [5], 6 => [7]];
                if ((int) $folderId !== 0) {
                    return $children[(int) $folderId] ?? ($idsOnly ? [1, 2, 3, 4, 5, 6, 7] : []);
                }
                $nodes = [];
                foreach ([1 => 0, 2 => 1, 3 => 1, 4 => 0, 5 => 4, 6 => 0, 7 => 6] as $id => $parentId) {
                    $nodes[$id] = (object) [
                        'id' => (string) $id, 'parent_id' => (string) $parentId,
                        'title' => 'Folder ' . $id, 'nlevel' => $parentId === 0 ? 1 : 2,
                    ];
                }
                return $nodes;
            }
            /** Paths are not under test. */
            public function getPath($folderId, $includeSelf): array { return []; }
        };
        $lang = new class {
            /** Return the translation key itself. */
            public function get(string $key): string { return $key; }
        };
        // The matrix reads its role id from the POST body
        $request = (object) ['request' => new class {
            /** Return the role id of the fixture. */
            public function get(string $key, $default = null) { return Fixture::$roleIdInput; }
        }];
        $SETTINGS = ['ldap_type' => 'ActiveDirectory'];
        $post_key = 'fixture-key';
        $post_data = json_encode($submission);

        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/roles.queries.php');
        $start = strpos($source, "case '" . $action . "':");
        if ($start === false) {
            $start = strpos($source, 'case "' . $action . '":');
        }
        $this->assertNotFalse($start, $action);
        // The next case of the top-level switch, or the end of that switch.
        $end = preg_match("/\n {8}case ['\"]/", $source, $match, PREG_OFFSET_CAPTURE, $start + 1) === 1
            ? $match[0][1]
            : strpos($source, "\n    }\n", $start);

        // Test-only: runs a slice of the shipped handler file (never user input) against the
        // namespaced stubs above, the pattern RenewalMigrationTest already uses.
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . '; switch ("' . $action . '") {' . substr($source, $start, $end - $start) . "\n}");
        } finally {
            $response = json_decode((string) ob_get_clean(), true);
        }

        return [$response, DB::$calls];
    }
}

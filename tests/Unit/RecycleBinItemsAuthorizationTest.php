<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace RecycleBinItemsRuntime;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/recycle_bin_logic.php';

/** In-memory instance: existing folders, recycled folders, items, and the session. */
final class Fixture
{
    /** @var array<int, true> Folders present in nested_tree. */
    public static array $folders = [];

    /** @var array<int, array{parent_id: int, title: string}> Recycled folders (misc). */
    public static array $deletedFolders = [];

    /** @var array<int, array{id_tree: int, inactif: int, deleted_at: ?string, restricted?: bool}> */
    public static array $items = [];

    /** @var array<string, mixed> */
    public static array $session = [];
}

/** Record the side effects that matter to the assertions. */
final class Trace
{
    /** @var list<int> */
    public static array $purged = [];

    /** @var list<int> Items reactivated one by one. */
    public static array $restoredItems = [];

    /** @var list<int> Folders whose items were reactivated. */
    public static array $restoredItemFolders = [];

    /** @var list<int> Folders put back in nested_tree. */
    public static array $insertedFolders = [];

    /** @var list<string> Recycle-bin entries removed from misc. */
    public static array $removedEntries = [];

    /** @var list<int> */
    public static array $logged = [];

    /** @var list<array<int, mixed>> Arguments of every recycled-state lookup. */
    public static array $lookups = [];
}

/** Session stub read by the production adapters. */
final class SessionManager
{
    /** Return an accessor over the fixture session. */
    public static function getSession(): object
    {
        return new class () {
            /** Return a fixture session value. */
            public function get(string $key): mixed
            {
                return Fixture::$session[$key] ?? null;
            }
        };
    }
}

/** Answer the handler's queries from the fixture and record every write. */
final class DB
{
    /** Emulate the recycled-state lookup: state and folder existence, then the inlined folder scope. */
    public static function queryFirstColumn(string $sql, mixed ...$args): array
    {
        Trace::$lookups[] = [$sql, ...$args];
        [$ids, $inactif, $nullAs, $empty] = $args;
        if (str_contains($sql, '(1 = 0)') === true) {
            return [];
        }
        $scope = null;
        if (preg_match('/i\.id_tree IN \(([0-9,]+)\)/', $sql, $matches) === 1) {
            $scope = array_map('intval', explode(',', $matches[1]));
        }

        return array_values(array_filter(
            $ids,
            static fn (int $id): bool => isset(Fixture::$items[$id])
                && Fixture::$items[$id]['inactif'] === $inactif
                && trim((string) (Fixture::$items[$id]['deleted_at'] ?? $nullAs)) !== $empty
                && isset(Fixture::$folders[Fixture::$items[$id]['id_tree']])
                && ($scope === null || in_array(Fixture::$items[$id]['id_tree'], $scope, true))
        ));
    }

    /** Return the soft-deleted items of a folder, or the items of restored folders. */
    public static function query(string $sql, mixed ...$args): array
    {
        $folderIds = is_array($args[0]) ? $args[0] : [(int) $args[0]];
        $rows = [];
        foreach (Fixture::$items as $id => $item) {
            if (in_array($item['id_tree'], $folderIds, true) === true
                && (str_contains($sql, 'inactif = %i') === false || $item['inactif'] === 1)
            ) {
                $rows[] = ['id' => $id, 'label' => 'label'];
            }
        }

        return $rows;
    }

    /** @var list<string> SQL of every hidden-item count. */
    public static array $counts = [];

    /** Answer folder existence checks, hidden-item counts and item label lookups. */
    public static function queryFirstField(string $sql, mixed ...$args): mixed
    {
        if (str_contains($sql, 'COUNT(*)') === true) {
            self::$counts[] = $sql;
            [$folderIds, $inactif] = $args;

            return count(array_filter(
                Fixture::$items,
                static fn (array $item): bool => in_array($item['id_tree'], $folderIds, true)
                    && $item['inactif'] === $inactif
                    && ($item['restricted'] ?? false) === true
            ));
        }
        if (str_contains($sql, 'nested_tree') === true) {
            return isset(Fixture::$folders[(int) $args[0]]) ? (int) $args[0] : null;
        }

        return 'label';
    }

    /** Record a reactivation instead of running it. */
    public static function update(string $table, array $data, string $where, mixed ...$args): void
    {
        if ($table !== 'items') {
            return;
        }
        if ($where === 'id_tree IN %li') {
            Trace::$restoredItemFolders = array_merge(Trace::$restoredItemFolders, $args[0]);
            return;
        }
        Trace::$restoredItems[] = (int) $args[0];
    }

    /** Record the removal of a recycle-bin entry. */
    public static function delete(string $table, string $where, mixed ...$args): void
    {
        if ($table === 'misc') {
            Trace::$removedEntries[] = (string) $args[1];
        }
    }

    /** Record a folder put back in the tree. */
    public static function insert(string $table, array $data): void
    {
        if ($table === 'nested_tree') {
            Trace::$insertedFolders[] = (int) $data['id'];
            Fixture::$folders[(int) $data['id']] = true;
        }
    }
}

/** Return the table name unchanged. */
function prefixTable(string $table): string
{
    return $table;
}

/** Pass decoded payloads through and encode responses as plain JSON. */
function prepareExchangedData(mixed $data, string $type): mixed
{
    return $type === 'decode' ? $data : (string) json_encode($data);
}

/** Return the recycled folders of the fixture, in the production format. */
function tpGetDeletedFoldersFromMisc(): array
{
    $folders = [];
    foreach (Fixture::$deletedFolders as $id => $folder) {
        $folders[$id] = ['id' => $id] + $folder;
    }

    return $folders;
}

/** The caller holds one role; no fixture item carries a restriction. */
function securityPostureUserRoleIds(int $userId): array
{
    return [3];
}

/** Ignore folder complexity repairs. */
function tpEnsureFolderComplexity(int $folderId, int $complexity): void
{
}

/** Record the item log written by the restore case. */
function logItems(array $settings, int $itemId, string $label, int $userId, string $action, string $login): void
{
    Trace::$logged[] = $itemId;
}

/** Ignore cache refreshes. */
function updateCacheTable(string $action, ?int $id): void
{
}

/** Record a purge instead of running it. */
function tpHardDeleteItem(int $itemId): void
{
    Trace::$purged[] = $itemId;
}

/**
 * GHSA-4jq3-w38q-6wrq: delete_selected_objects hard-deleted any posted item id, and the recycle bin
 * showed every recycled element to managers and HR managers. The bin now acts on recycled
 * elements only, and on the ones the caller may see: administrators see everything, anybody else
 * the folders and items they can see in the Items page.
 */
final class RecycleBinItemsAuthorizationTest extends TestCase
{
    private static string $source = '';

    /** Production helpers run against the stubs of this namespace. */
    private const PRODUCTION_FUNCTIONS = [
        'tpRecycleBinVisibleDeletedFolders',
        'tpRecycleBinItemScopeSql',
        'tpRecycledItemIds',
        'tpRecycleBinCanPurgeSubtree',
        'tpBuildDeletedFoldersChildrenMap',
        'tpCollectDeletedFolderSubtree',
        'tpGetDeletedFolderDepth',
    ];

    private const MANAGER = [
        'key' => 'fixture-key', 'user-read_only' => 0, 'user-id' => 9, 'user-login' => 'mgr',
        'user-admin' => 0, 'user-accessible_folders' => [5, 20, 23, 50], 'user-personal_folders' => [50],
    ];

    private const ADMIN = [
        'key' => 'fixture-key', 'user-read_only' => 0, 'user-id' => 1, 'user-login' => 'admin',
        'user-admin' => 1, 'user-accessible_folders' => [], 'user-personal_folders' => [],
    ];

    public static function setUpBeforeClass(): void
    {
        self::$source = str_replace(
            "\r\n",
            "\n",
            (string) file_get_contents(__DIR__ . '/../../app/sources/utilities.queries.php')
        );

        foreach (self::PRODUCTION_FUNCTIONS as $name) {
            $start = strpos(self::$source, 'function ' . $name . '(');
            self::assertNotFalse($start, $name . ' must exist.');
            $end = strpos(self::$source, "\n}\n", $start);
            self::assertNotFalse($end);
            if (function_exists(__NAMESPACE__ . '\\' . $name) === false) {
                eval('namespace ' . __NAMESPACE__ . '; ' . substr(self::$source, $start, $end - $start + 2));
            }
        }
    }

    protected function setUp(): void
    {
        // 5 and 6 are shared folders, 50 the manager's personal root, 60 somebody else's.
        Fixture::$folders = [5 => true, 6 => true, 50 => true, 60 => true];
        Fixture::$deletedFolders = [
            20 => ['parent_id' => 5, 'title' => 'Seen'],
            21 => ['parent_id' => 20, 'title' => 'Hidden child'],
            23 => ['parent_id' => 21, 'title' => 'Seen under hidden'],
            30 => ['parent_id' => 6, 'title' => 'Hidden'],
            40 => ['parent_id' => 50, 'title' => 'Own personal'],
            41 => ['parent_id' => 40, 'title' => 'Own personal child'],
            70 => ['parent_id' => 60, 'title' => 'Foreign personal'],
        ];
        Fixture::$items = [
            10 => ['id_tree' => 5, 'inactif' => 1, 'deleted_at' => '1700000000'],
            11 => ['id_tree' => 5, 'inactif' => 0, 'deleted_at' => null],
            12 => ['id_tree' => 5, 'inactif' => 1, 'deleted_at' => '  '],
            14 => ['id_tree' => 6, 'inactif' => 1, 'deleted_at' => '1700000000'],
            15 => ['id_tree' => 60, 'inactif' => 1, 'deleted_at' => '1700000000'],
            16 => ['id_tree' => 50, 'inactif' => 1, 'deleted_at' => '1700000000'],
            17 => ['id_tree' => 99, 'inactif' => 1, 'deleted_at' => '1700000000'],
            200 => ['id_tree' => 20, 'inactif' => 1, 'deleted_at' => '1700000000'],
            210 => ['id_tree' => 21, 'inactif' => 1, 'deleted_at' => '1700000000'],
            300 => ['id_tree' => 30, 'inactif' => 1, 'deleted_at' => '1700000000'],
            400 => ['id_tree' => 40, 'inactif' => 1, 'deleted_at' => '1700000000'],
            410 => ['id_tree' => 41, 'inactif' => 1, 'deleted_at' => '1700000000'],
            700 => ['id_tree' => 70, 'inactif' => 1, 'deleted_at' => '1700000000'],
        ];
        Fixture::$session = self::MANAGER;
        Trace::$purged = [];
        Trace::$restoredItems = [];
        Trace::$restoredItemFolders = [];
        Trace::$insertedFolders = [];
        Trace::$removedEntries = [];
        Trace::$logged = [];
        Trace::$lookups = [];
        DB::$counts = [];
    }

    /**
     * Run one recycle-bin case of the shipped handler with the given selection.
     *
     * @param list<mixed> $folders
     * @param list<mixed> $items
     * @return array<string, mixed>
     */
    private function request(string $type, array $folders, array $items): array
    {
        $next = $type === 'restore_selected_objects'
            ? "case 'delete_selected_objects':"
            : "case 'purge_logs':";
        $start = strpos(self::$source, "case '" . $type . "':");
        self::assertNotFalse($start);
        $end = strpos(self::$source, $next, $start);
        self::assertNotFalse($end);

        $session = SessionManager::getSession();
        $lang = new class () {
            /** Return stable error keys independently of locale. */
            public function get(string $key): string
            {
                return $key;
            }
        };
        $tree = new class () {
            /** Ignore the nested-set rebuild. */
            public function rebuild(): void
            {
            }
        };
        $post_key = 'fixture-key';
        $post_data = ['folders' => $folders, 'items' => $items];
        $SETTINGS = [];

        ob_start();
        eval('namespace ' . __NAMESPACE__ . '; switch ($type) {' . substr(self::$source, $start, $end - $start) . '}');

        return json_decode((string) ob_get_clean(), true);
    }

    /** A manager purges the recycled items they can see, and nothing live, hidden or foreign. */
    public function testManagerPurgesOnlyVisibleRecycledItems(): void
    {
        $response = $this->request('delete_selected_objects', [], ['10', '11', '12', '14', '15', '16', '17', '999', '10']);

        self::assertFalse($response['error']);
        self::assertSame([10, 16], Trace::$purged);
    }

    /** An administrator keeps the whole recycle bin, but still never purges a live item. */
    public function testAdministratorPurgesEveryRecycledItemButNoLiveOne(): void
    {
        Fixture::$session = self::ADMIN;
        $this->request('delete_selected_objects', [], ['10', '11', '12', '14', '15', '16', '17']);

        self::assertSame([10, 14, 15, 16], Trace::$purged);
    }

    /** Restoring follows the same scope, and logs only what was restored. */
    public function testManagerRestoresOnlyVisibleRecycledItems(): void
    {
        $response = $this->request('restore_selected_objects', [], ['10', '11', '12', '14', '15', '16', '17']);

        self::assertFalse($response['error']);
        self::assertSame([10, 16], Trace::$restoredItems);
        self::assertSame([10, 16], Trace::$logged);
    }

    /**
     * A folder purge never reaches a folder hidden from the caller. A subtree holding one is not
     * purged at all, so no hidden subfolder is destroyed unseen or left without its parent.
     */
    public function testManagerPurgesOnlyFullyVisibleSubtrees(): void
    {
        $this->request('delete_selected_objects', ['20', '21', '23', '30', '40', '70'], []);

        self::assertSame(['f23', 'f40', 'f41'], Trace::$removedEntries);
        self::assertSame([400, 410], Trace::$purged);
    }

    /** A subtree holding an item restricted away from the caller is not purged at all. */
    public function testRestrictedItemBlocksTheFolderPurge(): void
    {
        Fixture::$items[410]['restricted'] = true;
        $this->request('delete_selected_objects', ['40'], []);

        self::assertSame([], Trace::$removedEntries);
        self::assertSame([], Trace::$purged);
        self::assertCount(1, DB::$counts);
        self::assertStringContainsString('WHERE i.id_tree IN %li AND i.inactif = %i', DB::$counts[0]);
        self::assertMatchesRegularExpression('/AND NOT \\(\\(COALESCE\\(i\\.restricted_to/', DB::$counts[0]);
    }

    /** Administrators purge restricted items with their folder, as before. */
    public function testAdministratorPurgesRestrictedItemsWithTheirFolder(): void
    {
        Fixture::$session = self::ADMIN;
        Fixture::$items[410]['restricted'] = true;
        $this->request('delete_selected_objects', ['40'], []);

        self::assertSame(['f40', 'f41'], Trace::$removedEntries);
        self::assertSame([400, 410], Trace::$purged);
        self::assertSame([], DB::$counts);
    }

    /** Restoring a folder brings its restricted items back with it: restrictions are kept. */
    public function testRestrictedItemsAreRestoredWithTheirFolder(): void
    {
        Fixture::$items[410]['restricted'] = true;
        $this->request('restore_selected_objects', ['40'], []);

        self::assertSame([40, 41], Trace::$insertedFolders);
        self::assertSame([40, 41], Trace::$restoredItemFolders);
    }

    /** An administrator purges whole subtrees, as before. */
    public function testAdministratorPurgesWholeSubtrees(): void
    {
        Fixture::$session = self::ADMIN;
        $this->request('delete_selected_objects', ['20', '30', '70'], []);

        sort(Trace::$removedEntries);
        self::assertSame(['f20', 'f21', 'f23', 'f30', 'f70'], Trace::$removedEntries);
        sort(Trace::$purged);
        self::assertSame([200, 210, 300, 700], Trace::$purged);
    }

    /** A folder restore never restores a hidden folder's content. */
    public function testManagerRestoresOnlyVisibleFolders(): void
    {
        $this->request('restore_selected_objects', ['20', '30', '70'], []);

        self::assertSame([20], Trace::$insertedFolders);
        self::assertSame([20], Trace::$restoredItemFolders);
    }

    /** A hidden deleted ancestor comes back as an empty container, never with its items. */
    public function testHiddenAncestorIsRestoredWithoutItsItems(): void
    {
        $this->request('restore_selected_objects', ['23'], []);

        self::assertSame([20, 21, 23], Trace::$insertedFolders);
        self::assertSame([23], Trace::$restoredItemFolders);
    }

    /** The lookup requires the soft-deleted state, an existing folder and the caller's scope. */
    public function testLookupCombinesStateAndScope(): void
    {
        $this->request('delete_selected_objects', [], ['11', '10']);

        self::assertCount(1, Trace::$lookups);
        [$sql, $ids, $inactif, $nullAs, $empty] = Trace::$lookups[0];
        self::assertMatchesRegularExpression(
            '/INNER JOIN nested_tree AS n ON \(n\.id = i\.id_tree\)\s+'
            . 'WHERE i\.id IN %li AND i\.inactif = %i AND TRIM\(COALESCE\(i\.deleted_at, %s\)\) <> %s\s+'
            . 'AND \(i\.id_tree IN \(5,20,23,50\) AND /',
            (string) $sql
        );
        self::assertStringContainsString('restriction_to_roles', (string) $sql);
        self::assertSame([11, 10], $ids);
        self::assertSame([1, '', ''], [$inactif, $nullAs, $empty]);
    }

    /** Ids that are not positive integers never reach the database. */
    public function testInvalidIdsAreDroppedBeforeAnyLookup(): void
    {
        $response = $this->request('delete_selected_objects', [], ['0', '-3', 'abc', ['10']]);

        self::assertFalse($response['error']);
        self::assertSame([], Trace::$lookups);
        self::assertSame([], Trace::$purged);
    }

    /** The listing applies the same scope to the folders and to the items it shows. */
    public function testListingIsScoped(): void
    {
        $start = strpos(self::$source, "case 'recycled_bin_elements':");
        $end = strpos(self::$source, "case 'restore_selected_objects':", (int) $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $listing = substr(self::$source, $start, $end - $start);

        self::assertStringContainsString(
            '$deletedFolders = tpRecycleBinVisibleDeletedFolders(tpGetDeletedFoldersFromMisc());',
            $listing
        );
        self::assertStringNotContainsString('$deletedFolders = tpGetDeletedFoldersFromMisc();', $listing);
        self::assertMatchesRegularExpression(
            '/AND TRIM\(COALESCE\(i\.deleted_at, ""\)\) <> ""\s+AND \' \. tpRecycleBinItemScopeSql\(\'i\'\) \. \'/',
            $listing
        );
    }
}

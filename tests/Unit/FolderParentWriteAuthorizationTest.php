<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace FolderParentWriteRuntime;

use PHPUnit\Framework\TestCase;

/** Isolate the existing-folder lookup from any real database or mutation. */
class DB
{
    public static int $reads = 0;

    /** Return a writable shared folder whose current parent is read-only. */
    public static function queryFirstRow(string $sql, ...$args): array
    {
        self::$reads++;
        return ['id' => 3, 'parent_id' => 5, 'title' => 'Child', 'personal_folder' => 0];
    }
}

/** Keep fixture tables distinct from installed tables. */
function prefixTable(string $table): string
{
    return 'fixture_' . $table;
}

/** Apply the request conversions needed by the actual controller preconditions. */
function dataSanitizer(array $data, array $filters): array
{
    foreach ($filters as $key => $filter) {
        $data[$key] = $filter === 'cast:integer' ? (int) $data[$key]
            : htmlspecialchars(trim((string) $data[$key]), ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

/** Decode fixture requests and capture error responses without encryption. */
function prepareExchangedData(array $data, string $mode): array|string
{
    return $mode === 'decode' ? $data : (string) json_encode($data);
}

/** Exercise both shipped handler preconditions up to the parent lookup/write boundary. */
class FolderParentWriteAuthorizationTest extends TestCase
{
    private function request(string $action, int $parentId, array $overrides = []): array
    {
        DB::$reads = 0;
        $session = new class(array_merge([
            'key' => 'fixture-key', 'user-admin' => 0, 'user-read_only' => 0, 'user-id' => 1,
            'user-accessible_folders' => [3, 5, 7], 'user-read_only_folders' => ['5', '7'],
        ], $overrides)) {
            /** Store the simulated session scope. */
            public function __construct(private array $values) {}
            /** Read a simulated session value. */
            public function get(string $key): mixed { return $this->values[$key] ?? null; }
        };
        $lang = new class {
            /** Return stable error keys independently of locale. */
            public function get(string $key): string { return $key; }
        };
        $post_key = 'fixture-key';
        $post_data = ['id' => 3, 'title' => 'Child', 'parentId' => $parentId, 'complexity' => 60];
        $SETTINGS = [];
        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/folders.queries.php'));
        $start = strpos($source, "case '" . $action . "':");
        $boundary = $action === 'add_folder' ? '// Check if parent folder is personal' : '//check if parent folder is personal';
        $end = strpos($source, $boundary, $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $allowed = false;
        ob_start();
        eval('namespace FolderParentWriteRuntime; switch ($action) {' . substr($source, $start, $end - $start) . '$allowed = true; }');
        $response = (string) ob_get_clean();
        return $response === '' ? ['error' => false, 'allowed' => $allowed] : json_decode($response, true);
    }

    /** Direct posts cannot create or move folders into read-only destinations. */
    public function testBothHandlersRejectReadOnlyParentsWithIntegerOrStringSessionIds(): void
    {
        foreach (['add_folder', 'update_folder'] as $action) {
            foreach ([[5, 7], ['5', '7']] as $ids) {
                $result = $this->request($action, 7, ['user-read_only_folders' => $ids]);
                self::assertTrue($result['error'], $action);
                self::assertSame('error_not_allowed_to', $result['message']);
                self::assertSame($action === 'add_folder' ? 0 : 1, DB::$reads);
            }
        }
    }

    /** The parent restriction preserves root/admin handling and writable parents. */
    public function testBothHandlersPermitWritableParentsAndLeaveRootAndAdminGatesIntact(): void
    {
        foreach (['add_folder', 'update_folder'] as $action) {
            foreach ([[7, ['user-admin' => 1]], [0, []], [7, ['user-read_only_folders' => null]]] as [$id, $session]) {
                self::assertTrue($this->request($action, $id, $session)['allowed'], $action);
            }
        }
    }

    /** Renaming a writable child is allowed when its read-only parent is unchanged. */
    public function testUpdateDoesNotRejectAnUnchangedReadOnlyParent(): void
    {
        self::assertTrue($this->request('update_folder', 5)['allowed']);
        self::assertTrue($this->request('add_folder', 5)['error']);
    }
}

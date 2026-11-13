<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace ReadOnlyFolderEditRuntime;

use PHPUnit\Framework\TestCase;

/** Decide the item access answer of the fixture. */
final class Access
{
    public static bool $granted = true;
}

/** Apply the request conversions needed by the controller preconditions. */
function dataSanitizer(array $data, array $filters): array
{
    foreach ($filters as $key => $filter) {
        $data[$key] = $filter === 'cast:integer' ? (int) $data[$key]
            : htmlspecialchars(trim((string) $data[$key]), ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

/** Decode fixture requests and capture error responses without encryption. */
function prepareExchangedData(array|string $data, string $mode): array|string
{
    return $mode === 'decode' ? (array) $data : (string) json_encode($data);
}

/** Answer the item access check without a database. */
function accessToItemIsGranted(int $itemId, array $settings): bool|string
{
    return Access::$granted ? true : 'ERR_FOLDER_NOT_ALLOWED';
}

/**
 * GHSA-32hp-p55x-wr8v: a folder a user only reads through a role (R) cannot be edited through
 * either update_folder handler, and forcing the release of an edit lock is reserved to the
 * Database page audience.
 *
 * The session holds folder ids as the database returns them (strings), which is what made the
 * strict comparison of folders.queries.php never match.
 */
final class ReadOnlyFolderEditAuthorizationTest extends TestCase
{
    /**
     * Run a handler case of a source file up to a boundary, with a simulated session.
     * Like the other authorization sentinels, it evaluates the shipped source, never external input.
     */
    private function runCase(string $file, string $action, string $boundary, array $sessionValues, array $locals): array
    {
        $session = new class(array_merge([
            'key' => 'fixture-key', 'user-admin' => 0, 'user-manager' => 0, 'user-can_manage_all_users' => 0,
            'user-read_only' => 0, 'user-id' => 1, 'user-accessible_folders' => ['3', '5'],
            'user-read_only_folders' => ['3'],
        ], $sessionValues)) {
            /** Store the simulated session scope. */
            public function __construct(private array $values) {}
            /** Read a simulated session value. */
            public function get(string $key): mixed { return $this->values[$key] ?? null; }
        };
        $lang = new class {
            /** Return stable error keys independently of locale. */
            public function get(string $key): string { return $key; }
        };
        $SETTINGS = [];
        extract($locals);
        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/' . $file));
        $start = strpos($source, "case '" . $action . "':");
        self::assertNotFalse($start, $file . ' ' . $action);
        $end = strpos($source, $boundary, $start);
        self::assertNotFalse($end, $file . ' ' . $boundary);
        $allowed = false;
        ob_start();
        eval('namespace ReadOnlyFolderEditRuntime; switch ($action) {' . substr($source, $start, $end - $start) . '$allowed = true; }');
        $response = (string) ob_get_clean();
        if ($response === '') {
            return ['allowed' => $allowed, 'error' => null];
        }
        $decoded = json_decode($response, true);
        $error = $decoded['message'] ?? ($decoded[0]['error'] ?? $response);
        return ['allowed' => $allowed, 'error' => $error];
    }

    /** Run folders.queries.php update_folder up to the end of the per-folder read-only guard. */
    private function foldersUpdate(array $session): array
    {
        return $this->runCase('folders.queries.php', 'update_folder', '// check if title is numeric', $session, [
            'post_key' => 'fixture-key',
            'post_data' => ['id' => 3, 'title' => 'Renamed', 'parentId' => 5, 'complexity' => 0],
        ]);
    }

    /** Run items.queries.php update_folder up to the end of its access checks. */
    private function itemsUpdate(array $session): array
    {
        return $this->runCase('items.queries.php', 'update_folder', "// Check if title doesn't contains html codes", $session, [
            'inputData' => [
                'key' => 'fixture-key',
                'data' => ['folder' => '3', 'title' => 'Renamed', 'complexity' => 0],
            ],
        ]);
    }

    /** Run items.queries.php free_item_for_edition up to the lock deletion. */
    private function freeLock(array $session, bool $itemGranted): array
    {
        Access::$granted = $itemGranted;
        return $this->runCase('items.queries.php', 'free_item_for_edition', '// Do', $session, [
            'inputData' => ['key' => 'fixture-key', 'id' => 42],
        ]);
    }

    /** Both update_folder handlers refuse a read-only folder, whatever the type of the session ids. */
    public function testReadOnlyFolderCannotBeEditedThroughEitherHandler(): void
    {
        foreach ([['3'], [3]] as $ids) {
            $folders = $this->foldersUpdate(['user-read_only_folders' => $ids]);
            self::assertFalse($folders['allowed']);
            self::assertSame('error_not_allowed_to', $folders['error']);

            $items = $this->itemsUpdate(['user-read_only_folders' => $ids]);
            self::assertFalse($items['allowed']);
            self::assertSame('error_not_allowed_to', $items['error']);
        }
    }

    /** A writable folder and an administrator keep going past the guard. */
    public function testWritableFolderAndAdministratorAreNotRefused(): void
    {
        foreach ([['user-read_only_folders' => ['5']], ['user-read_only_folders' => null], ['user-admin' => 1]] as $session) {
            self::assertTrue($this->foldersUpdate($session)['allowed']);
            self::assertTrue($this->itemsUpdate($session)['allowed']);
        }
    }

    /** A user outside the Database page audience cannot release another user's lock. */
    public function testOrdinaryUserCannotForceAnEditLockRelease(): void
    {
        $result = $this->freeLock([], true);
        self::assertFalse($result['allowed']);
        self::assertSame('not_allowed', $result['error']);
    }

    /** Administrators release any lock; managers and HR managers only on items they reach. */
    public function testDatabasePageAudienceCanForceAnEditLockRelease(): void
    {
        self::assertTrue($this->freeLock(['user-admin' => 1], false)['allowed']);
        foreach ([['user-manager' => 1], ['user-can_manage_all_users' => 1]] as $session) {
            self::assertTrue($this->freeLock($session, true)['allowed']);
            self::assertSame('not_allowed', $this->freeLock($session, false)['error']);
        }
    }
}

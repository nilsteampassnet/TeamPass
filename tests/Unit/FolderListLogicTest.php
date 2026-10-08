<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/folder_list_logic.php';

/** Regression coverage for large folder lists and damaged tree snapshots. */
class FolderListLogicTest extends TestCase
{
    /** Preserve root-to-parent ordering even when names repeat. */
    public function testPathsKeepRootOrderAndDuplicateNames(): void
    {
        $nodes = [
            7 => (object) ['parent_id' => 0, 'title' => 'Servers'],
            8 => (object) ['parent_id' => 7, 'title' => 'Servers'],
            9 => (object) ['parent_id' => 8, 'title' => 'Production'],
        ];
        self::assertSame(['ids' => [7, 8], 'titles' => ['Servers', 'Servers']], folderListAncestorPath($nodes, 9));
        self::assertSame(['ids' => [], 'titles' => []], folderListAncestorPath($nodes, 7));
    }

    /** Bound traversal for incomplete or corrupt snapshots. */
    public function testMissingParentsAndCyclesTerminateWithoutIncludingTheFolderItself(): void
    {
        $nodes = [
            7 => (object) ['parent_id' => 8, 'title' => 'A'],
            8 => (object) ['parent_id' => 7, 'title' => 'B'],
            9 => (object) ['parent_id' => 99, 'title' => 'Orphan'],
        ];
        self::assertSame(['ids' => [8], 'titles' => ['B']], folderListAncestorPath($nodes, 7));
        self::assertSame(['ids' => [], 'titles' => []], folderListAncestorPath($nodes, 9));
    }

    /** Exercise production-sized path resolution without any database connection. */
    public function testThousandsOfPathsNeedNoDatabaseConnection(): void
    {
        $nodes = [1 => (object) ['parent_id' => 0, 'title' => 'Root']];
        for ($id = 2; $id <= 3161; $id++) {
            $nodes[$id] = (object) ['parent_id' => 1, 'title' => 'Folder ' . $id];
        }
        foreach ($nodes as $id => $node) {
            self::assertSame($id === 1 ? [] : [1], folderListAncestorPath($nodes, $id)['ids']);
        }
    }

    /** Prevent the two parent-list entry points from restoring per-folder SQL reads. */
    public function testParentListsNeverReadOnePathPerFolderFromTheDatabase(): void
    {
        $page = (string) file_get_contents(__DIR__ . '/../../app/pages/folders.php');
        $queries = (string) file_get_contents(__DIR__ . '/../../app/sources/folders.queries.php');
        $refresh = substr($queries, (int) strpos($queries, "case 'refresh_folders_list':"));
        self::assertStringNotContainsString('->getPath(', $page);
        self::assertStringNotContainsString('->getPath(', $refresh);
    }
}

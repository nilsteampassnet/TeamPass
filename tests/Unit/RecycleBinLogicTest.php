<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/recycle_bin_logic.php';

/** Scope of the recycle bin for administrators and managers (GHSA-4jq3-w38q-6wrq). */
final class RecycleBinLogicTest extends TestCase
{
    /** @var array<int, array{parent_id: int}> */
    private const DELETED = [
        20 => ['parent_id' => 5],   // shared, accessible
        21 => ['parent_id' => 20],  // shared, not accessible
        40 => ['parent_id' => 50],  // under the caller's personal root
        41 => ['parent_id' => 40],  // deeper in the caller's personal tree
        70 => ['parent_id' => 60],  // under somebody else's personal root
        80 => ['parent_id' => 81],  // cycle between two deleted folders
        81 => ['parent_id' => 80],
    ];

    public function testAdministratorSeesEveryRecycledFolder(): void
    {
        self::assertSame(self::DELETED, recycleBinVisibleDeletedFolders(self::DELETED, true, [], []));
    }

    public function testManagerSeesAccessibleAndOwnPersonalFoldersOnly(): void
    {
        $visible = recycleBinVisibleDeletedFolders(self::DELETED, false, [5, '20', 50], ['50']);

        self::assertSame([20, 40, 41], array_keys($visible));
    }

    public function testAccessIsPerFolderAndNeverInherited(): void
    {
        // Folder 5 is accessible, its deleted child 20 is not: 20 stays hidden.
        self::assertSame([], recycleBinVisibleDeletedFolders(self::DELETED, false, [5], []));
        // An accessible deleted parent does not open its deleted child either.
        self::assertSame([20], array_keys(recycleBinVisibleDeletedFolders(self::DELETED, false, [20], [])));
    }

    public function testCycleAndForeignPersonalTreeFailClosed(): void
    {
        // A parent_id cycle never resolves to an existing personal folder.
        $visible = recycleBinVisibleDeletedFolders(self::DELETED, false, [], [80, 81]);
        self::assertArrayNotHasKey(80, $visible);
        self::assertArrayNotHasKey(81, $visible);
        // Another user's personal tree stays hidden.
        self::assertArrayNotHasKey(70, recycleBinVisibleDeletedFolders(self::DELETED, false, [5, 20], [50]));
    }

    public function testAdministratorItemScopeIsOpen(): void
    {
        self::assertSame('(1 = 1)', recycleBinItemScopeSql('i', true, [], 1, [], 'teampass_restriction_to_roles'));
    }

    public function testManagerItemScopeCombinesFoldersAndRestrictions(): void
    {
        $sql = recycleBinItemScopeSql('i', false, ['5', 20, 20, 0, -1], 9, [3], 'teampass_restriction_to_roles');

        self::assertStringStartsWith('(i.id_tree IN (5,20) AND ', $sql);
        self::assertStringContainsString(
            itemRestrictionSqlPredicate(9, [3], 'i', 'teampass_restriction_to_roles'),
            $sql
        );
    }

    public function testItemScopeFailsClosed(): void
    {
        self::assertSame('(1 = 0)', recycleBinItemScopeSql('i', false, [], 9, [3], 'teampass_restriction_to_roles'));
        self::assertSame('(1 = 0)', recycleBinItemScopeSql('i; DROP', true, [5], 9, [3], 'teampass_restriction_to_roles'));
    }
}

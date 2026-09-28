<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/item_access_logic.php';

/**
 * Behavioural tests for the folder scope that gates web item access.
 */
class ItemAccessLogicTest extends TestCase
{
    /** A parent the tree only shows as blocked is outside the scope. */
    public function testBlockedAncestorOfAnAccessibleFolderIsOutOfScope(): void
    {
        // The user reaches folder 11 only; its parent 10 is displayed as blocked.
        self::assertFalse(itemAccessFolderIsInScope(10, [11], [], [], []));
        self::assertTrue(itemAccessFolderIsInScope(11, [11], [], [], []));
    }

    /** Session arrays hold numeric strings as well as integers. */
    public function testSessionStringIdsAreAccepted(): void
    {
        self::assertTrue(itemAccessFolderIsInScope(11, ['11'], [], [], []));
        self::assertTrue(itemAccessFolderIsInScope(21, [], ['20', '21'], [], []));
    }

    /** A personal folder created during the session is only in user-personal_folders. */
    public function testOwnPersonalFoldersAreInScope(): void
    {
        self::assertTrue(itemAccessFolderIsInScope(21, [11], [20, 21], [], []));
    }

    /** Explicit denials and other users' personal folders win over any grant. */
    public function testDenialsWinOverGrants(): void
    {
        self::assertFalse(itemAccessFolderIsInScope(11, [11], [], ['11'], []));
        self::assertFalse(itemAccessFolderIsInScope(30, [30], [], [], [30]));
    }

    /** The synthetic root and an empty scope never authorize anything. */
    public function testRootAndEmptyScopeAreOutOfScope(): void
    {
        self::assertFalse(itemAccessFolderIsInScope(0, [0, 11], [], [], []));
        self::assertFalse(itemAccessFolderIsInScope(11, [], [], [], []));
    }

    /** Role delete rights follow the least-permissive rule. */
    public function testFolderDeleteRightsUseTheLeastPermissiveRole(): void
    {
        foreach ([['W'], ['NE'], ['W', 'NE']] as $allowedTypes) {
            self::assertTrue(itemAccessFolderAllowsDelete(true, false, false, false, false, $allowedTypes));
        }

        foreach ([[], ['R'], ['ND'], ['NDNE'], ['W', 'ND'], ['NE', 'R'], ['unknown']] as $deniedTypes) {
            self::assertFalse(itemAccessFolderAllowsDelete(true, false, false, false, false, $deniedTypes));
        }
    }

    /** Scope and read-only restrictions win over direct and personal grants. */
    public function testFolderDeleteRightsRespectScopeAndReadOnlyRestrictions(): void
    {
        self::assertFalse(itemAccessFolderAllowsDelete(false, false, false, true, false, []));
        self::assertFalse(itemAccessFolderAllowsDelete(true, true, false, true, false, []));
        self::assertFalse(itemAccessFolderAllowsDelete(true, false, true, true, true, []));
        self::assertTrue(itemAccessFolderAllowsDelete(true, false, false, true, false, []));
        self::assertTrue(itemAccessFolderAllowsDelete(true, false, false, false, true, []));
    }

    /** Folder rights check the scope before any shortcut can grant access. */
    public function testFolderAccessRightsCheckTheScopeFirst(): void
    {
        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/items.queries.php'));
        self::assertStringContainsString("require_once __DIR__ . '/item_access_logic.php';", $source);

        $start = strpos($source, 'function getCurrentFolderAccessRights(');
        self::assertIsInt($start);
        $end = strpos($source, "\n}\n", $start);
        self::assertIsInt($end);
        $body = substr($source, $start, $end - $start);

        $scope = strpos($body, 'itemAccessFolderIsInScope(');
        self::assertIsInt($scope);
        foreach ([
            "get('user-read_only_folders')",
            "get('user-allowed_folders_by_definition')",
            'getUserVisibleFolders(',
        ] as $shortcut) {
            $position = strpos($body, $shortcut);
            self::assertIsInt($position, $shortcut);
            self::assertLessThan($position, $scope, "the folder scope must be checked before $shortcut");
        }
    }
}

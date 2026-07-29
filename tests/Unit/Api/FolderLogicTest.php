<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Stubs/folder_pure_functions.php';

/**
 * Behavioural tests for the DB-free folder API decision logic.
 *
 * Covers the create/update/delete decisions extracted into
 * tests/Stubs/folder_pure_functions.php: the permission gate, personal-root
 * detection, the private-flag resolution (§3.2), move validation
 * (circular / descendant / cross-domain), the complexity rules and the
 * recycle-bin payload shape.
 *
 * These lock the documented contract so future evolutions cannot silently
 * change the behaviour without a failing test.
 */
class FolderLogicTest extends TestCase
{
    /** Password-strength levels accepted by the API (TP_PW_STRENGTH_1..5). */
    private const VALID_STRENGTHS = [0, 20, 38, 48, 60];

    // -------------------------------------------------------------------------
    // Global permission gate
    // -------------------------------------------------------------------------

    public function testPersonalFolderAlwaysPassesTheGate(): void
    {
        // A personal folder passes even with every privileged flag off
        self::assertTrue(
            folderGlobalPermissionGate(true, false, false, false, false, false)
        );
    }

    public function testEachPrivilegedFlagOpensTheGate(): void
    {
        self::assertTrue(folderGlobalPermissionGate(false, true, false, false, false, false), 'admin');
        self::assertTrue(folderGlobalPermissionGate(false, false, true, false, false, false), 'manager');
        self::assertTrue(folderGlobalPermissionGate(false, false, false, true, false, false), 'can_manage_all_users');
        self::assertTrue(folderGlobalPermissionGate(false, false, false, false, true, false), 'enable_user_can_create_folders');
        self::assertTrue(folderGlobalPermissionGate(false, false, false, false, false, true), 'can_create_root_folder');
    }

    public function testUnprivilegedSharedFolderIsBlocked(): void
    {
        // A standard user with write access but no privileged flag cannot
        // create/update/delete a shared folder (mirrors the web handler).
        self::assertFalse(
            folderGlobalPermissionGate(false, false, false, false, false, false)
        );
    }

    // -------------------------------------------------------------------------
    // Personal root detection
    // -------------------------------------------------------------------------

    public function testPersonalRootIsDetected(): void
    {
        self::assertTrue(folderIsPersonalRoot(0, 1, '42', 42));
    }

    public function testPersonalSubfolderIsNotARoot(): void
    {
        // A personal subfolder has a non-zero parent and an arbitrary title
        self::assertFalse(folderIsPersonalRoot(7, 1, 'My subfolder', 42));
    }

    public function testSharedRootIsNotAPersonalRoot(): void
    {
        self::assertFalse(folderIsPersonalRoot(0, 0, 'Shared', 42));
    }

    public function testAnotherUsersRootIsNotThisUsersRoot(): void
    {
        // title '99' belongs to user 99, not the requesting user 42
        self::assertFalse(folderIsPersonalRoot(0, 1, '99', 42));
    }

    // -------------------------------------------------------------------------
    // Required create params (§3.2)
    // -------------------------------------------------------------------------

    public function testSharedCreateRequiresParentAndComplexity(): void
    {
        self::assertSame(['title', 'parent_id', 'complexity'], folderCreateRequiredParams(false));
    }

    public function testPrivateCreateRequiresOnlyTitle(): void
    {
        self::assertSame(['title'], folderCreateRequiredParams(true));
    }

    public function testComplexityRequiredOnlyForSharedTarget(): void
    {
        self::assertTrue(folderComplexityRequired(false), 'shared target requires complexity');
        self::assertFalse(folderComplexityRequired(true), 'personal target does not');
    }

    // -------------------------------------------------------------------------
    // Complexity / access-right validation
    // -------------------------------------------------------------------------

    public function testValidComplexityAccepted(): void
    {
        foreach (self::VALID_STRENGTHS as $strength) {
            self::assertTrue(folderIsValidComplexity($strength, self::VALID_STRENGTHS));
        }
    }

    public function testInvalidComplexityRejected(): void
    {
        self::assertFalse(folderIsValidComplexity(17, self::VALID_STRENGTHS));
        self::assertFalse(folderIsValidComplexity(-1, self::VALID_STRENGTHS));
    }

    public function testAccessRightValidation(): void
    {
        foreach (['R', 'W', 'NE', 'ND', 'NDNE'] as $ar) {
            self::assertTrue(folderIsValidAccessRight($ar));
        }
        self::assertFalse(folderIsValidAccessRight('X'));
        self::assertFalse(folderIsValidAccessRight(''));
        self::assertFalse(folderIsValidAccessRight('w'), 'case-sensitive');
    }

    // -------------------------------------------------------------------------
    // Private create resolution (§3.2)
    // -------------------------------------------------------------------------

    public function testPrivateCreateWithoutParentUsesPersonalRoot(): void
    {
        // private=true, pf enabled, root exists, no parent provided → personal
        $res = folderResolveCreateTarget(true, true, true, false, null, false);
        self::assertSame('ok', $res['status']);
        self::assertSame(1, $res['is_personal']);
    }

    public function testPrivateCreateBlockedWhenPersonalFoldersDisabled(): void
    {
        $res = folderResolveCreateTarget(true, false, true, false, null, false);
        self::assertSame('pf_disabled', $res['status']);
    }

    public function testPrivateCreateBlockedWhenNoPersonalRoot(): void
    {
        $res = folderResolveCreateTarget(true, true, false, false, null, false);
        self::assertSame('no_personal_root', $res['status']);
    }

    public function testPrivateCreateWithSharedParentRejected(): void
    {
        // private=true but the provided parent is a shared folder → 422
        $res = folderResolveCreateTarget(true, true, true, true, 0, true);
        self::assertSame('parent_not_personal', $res['status']);
    }

    public function testPrivateCreateWithForeignPersonalParentRejected(): void
    {
        // parent is personal but not inside the caller's own personal tree
        $res = folderResolveCreateTarget(true, true, true, true, 1, false);
        self::assertSame('parent_not_personal', $res['status']);
    }

    public function testPrivateCreateWithOwnPersonalParentAccepted(): void
    {
        $res = folderResolveCreateTarget(true, true, true, true, 1, true);
        self::assertSame('ok', $res['status']);
        self::assertSame(1, $res['is_personal']);
    }

    public function testParentDerivedPersonalFolder(): void
    {
        // No private flag, but the parent is a personal folder → personal (C2 fix)
        $res = folderResolveCreateTarget(false, false, false, true, 1, true);
        self::assertSame('ok', $res['status']);
        self::assertSame(1, $res['is_personal']);
    }

    public function testParentDerivedSharedFolder(): void
    {
        // No private flag, parent is a shared folder → shared
        $res = folderResolveCreateTarget(false, false, false, true, 0, false);
        self::assertSame('ok', $res['status']);
        self::assertSame(0, $res['is_personal']);
    }

    public function testRootCreateIsShared(): void
    {
        // No private flag, no parent (root) → shared
        $res = folderResolveCreateTarget(false, false, false, false, null, false);
        self::assertSame('ok', $res['status']);
        self::assertSame(0, $res['is_personal']);
    }

    // -------------------------------------------------------------------------
    // Move validation
    // -------------------------------------------------------------------------

    public function testMoveIntoSelfIsCircular(): void
    {
        self::assertSame('circular', folderMoveValidation(10, 10, [], 0, 0));
    }

    public function testMoveIntoDescendantIsRejected(): void
    {
        self::assertSame('descendant', folderMoveValidation(10, 12, [11, 12, 13], 0, 0));
    }

    public function testMoveToRootIsNotADescendant(): void
    {
        // parent 0 (root) is never treated as a descendant
        self::assertSame('ok', folderMoveValidation(10, 0, [11, 12], 0, 0));
    }

    public function testCrossDomainMoveSharedToPersonalRejected(): void
    {
        self::assertSame('cross_domain', folderMoveValidation(10, 20, [], 0, 1));
    }

    public function testCrossDomainMovePersonalToSharedRejected(): void
    {
        self::assertSame('cross_domain', folderMoveValidation(10, 20, [], 1, 0));
    }

    public function testSameDomainMoveAccepted(): void
    {
        self::assertSame('ok', folderMoveValidation(10, 20, [11], 0, 0));
        self::assertSame('ok', folderMoveValidation(10, 20, [11], 1, 1));
    }

    public function testCircularTakesPriorityOverEverything(): void
    {
        // Even if domains differ, self-move is reported as circular first
        self::assertSame('circular', folderMoveValidation(10, 10, [10], 0, 1));
    }

    // -------------------------------------------------------------------------
    // Complexity ceiling on update
    // -------------------------------------------------------------------------

    public function testComplexityCeilingViolatedForSharedNonPrivileged(): void
    {
        self::assertTrue(
            folderComplexityCeilingViolated(20, 38, false, false, false, false)
        );
    }

    public function testComplexityCeilingRespectedWhenAtOrAboveParent(): void
    {
        self::assertFalse(folderComplexityCeilingViolated(38, 38, false, false, false, false));
        self::assertFalse(folderComplexityCeilingViolated(48, 38, false, false, false, false));
    }

    public function testComplexityCeilingSkippedForPersonalTarget(): void
    {
        self::assertFalse(
            folderComplexityCeilingViolated(0, 60, true, false, false, false)
        );
    }

    public function testComplexityCeilingBypassedByAdmin(): void
    {
        self::assertFalse(
            folderComplexityCeilingViolated(0, 60, false, true, false, false)
        );
    }

    public function testComplexityCeilingBypassedByManagerWithManageAll(): void
    {
        self::assertFalse(
            folderComplexityCeilingViolated(0, 60, false, false, true, true)
        );
    }

    public function testComplexityCeilingAppliesToManagerWithoutManageAll(): void
    {
        // manager but not can_manage_all_users → the ceiling still applies
        self::assertTrue(
            folderComplexityCeilingViolated(0, 60, false, false, true, false)
        );
    }

    public function testComplexityCeilingSkippedWhenParentHasNoRequirement(): void
    {
        self::assertFalse(
            folderComplexityCeilingViolated(0, null, false, false, false, false)
        );
    }

    // -------------------------------------------------------------------------
    // "Nothing to update" guard
    // -------------------------------------------------------------------------

    public function testUpdateWithNoFieldIsRejected(): void
    {
        $fields = ['title', 'parent_id', 'complexity', 'duration', 'create_auth_without', 'edit_auth_without', 'icon', 'icon_selected'];
        self::assertFalse(folderUpdateHasUpdatableField(['id' => 5], $fields));
    }

    public function testUpdateWithOneFieldIsAccepted(): void
    {
        $fields = ['title', 'parent_id', 'complexity', 'duration', 'create_auth_without', 'edit_auth_without', 'icon', 'icon_selected'];
        self::assertTrue(folderUpdateHasUpdatableField(['id' => 5, 'title' => 'x'], $fields));
    }

    public function testUpdateWithEmptyStringFieldStillCountsAsPresent(): void
    {
        // array_key_exists — an explicit empty value is still "provided"
        $fields = ['title', 'icon'];
        self::assertTrue(folderUpdateHasUpdatableField(['id' => 5, 'icon' => ''], $fields));
    }

    // -------------------------------------------------------------------------
    // Recycle-bin payload
    // -------------------------------------------------------------------------

    public function testRecycleBinPayloadContainsAllRestoreKeys(): void
    {
        $node = [
            'id' => 57,
            'parent_id' => 3,
            'title' => 'Marketing',
            'nleft' => 10,
            'nright' => 19,
            'nlevel' => 2,
            'bloquer_creation' => 0,
            'bloquer_modification' => 1,
            'personal_folder' => 0,
            'renewal_period' => 30,
            'categories' => 'a;b',
        ];

        $data = folderBuildRecycleBinData($node, 42, 'jdoe');

        // Every key consumed by tpParseFolderDeletedValeur() must be present
        foreach (['id', 'parent_id', 'title', 'nleft', 'nright', 'nlevel',
                  'bloquer_creation', 'bloquer_modification', 'personal_folder',
                  'renewal_period'] as $key) {
            self::assertArrayHasKey($key, $data, "restore key '$key' missing");
        }
        self::assertSame(42, $data['deleted_by']);
        self::assertSame('jdoe', $data['deleted_by_login']);
        self::assertSame('Marketing', $data['title']);
        self::assertSame(1, $data['bloquer_modification']);
    }

    public function testRecycleBinPayloadOptionalKeysOmittedWhenAbsent(): void
    {
        $node = [
            'id' => 1, 'parent_id' => 0, 'title' => 'T',
            'nleft' => 1, 'nright' => 2, 'nlevel' => 1,
        ];
        $data = folderBuildRecycleBinData($node, 1, 'u');

        self::assertArrayNotHasKey('fa_icon', $data);
        self::assertArrayNotHasKey('fa_icon_selected', $data);
        self::assertArrayNotHasKey('is_template', $data);
        // Missing numeric fields default to 0
        self::assertSame(0, $data['renewal_period']);
        self::assertSame('', $data['categories']);
    }

    public function testRecycleBinPayloadIncludesOptionalIcons(): void
    {
        $node = [
            'id' => 1, 'parent_id' => 0, 'title' => 'T',
            'nleft' => 1, 'nright' => 2, 'nlevel' => 1,
            'fa_icon' => 'fa-folder', 'fa_icon_selected' => 'fa-folder-open',
            'is_template' => 1,
        ];
        $data = folderBuildRecycleBinData($node, 1, 'u');

        self::assertSame('fa-folder', $data['fa_icon']);
        self::assertSame('fa-folder-open', $data['fa_icon_selected']);
        self::assertSame(1, $data['is_template']);
    }

    public function testRecycleBinPayloadSurvivesJsonRoundTrip(): void
    {
        // The production code json_encodes this with UNESCAPED flags and the
        // restore parser json_decodes it — the round trip must be lossless.
        $node = [
            'id' => 5, 'parent_id' => 2, 'title' => 'Ünïcode / slash',
            'nleft' => 4, 'nright' => 5, 'nlevel' => 3,
            'personal_folder' => 1,
        ];
        $data = folderBuildRecycleBinData($node, 7, 'owner');

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        self::assertIsString($json);
        $decoded = json_decode($json, true);

        self::assertSame($data, $decoded, 'recycle-bin payload must round-trip through JSON unchanged');
        self::assertStringContainsString('Ünïcode / slash', $json, 'unicode and slashes must stay unescaped');
    }

    // -------------------------------------------------------------------------
    // Delete node skipping
    // -------------------------------------------------------------------------

    public function testDeleteSkipsPersonalRoot(): void
    {
        self::assertTrue(folderDeleteShouldSkipNode(0, 1));
    }

    public function testDeleteSkipsOrphanNode(): void
    {
        self::assertTrue(folderDeleteShouldSkipNode(-1, 0));
    }

    public function testDeleteDoesNotSkipRegularFolders(): void
    {
        self::assertFalse(folderDeleteShouldSkipNode(3, 0), 'shared subfolder');
        self::assertFalse(folderDeleteShouldSkipNode(3, 1), 'personal subfolder');
        self::assertFalse(folderDeleteShouldSkipNode(0, 0), 'shared root branch');
    }

    // -------------------------------------------------------------------------
    // Access level resolution — least permissive wins (docs/features/rights.md)
    // -------------------------------------------------------------------------

    /**
     * The regression this guards: the API used to treat R + W as writable
     * (hasReadOnly && !hasWrite), while the web gives R absolute priority.
     */
    public function testReadOnlyWinsOverWrite(): void
    {
        self::assertSame('R', folderResolveAccessType(['W', 'R']));
        self::assertSame('R', folderResolveAccessType(['R', 'W']));
        self::assertSame('R', folderResolveAccessType(['ND', 'R', 'W']));
    }

    public function testNoDeleteAndNoEditCombineIntoNdne(): void
    {
        self::assertSame('NDNE', folderResolveAccessType(['ND', 'NE']));
        self::assertSame('NDNE', folderResolveAccessType(['NE', 'ND']));
    }

    public function testRestrictionWinsOverPlainWrite(): void
    {
        self::assertSame('ND', folderResolveAccessType(['W', 'ND']));
        self::assertSame('NE', folderResolveAccessType(['W', 'NE']));
        self::assertSame('NDNE', folderResolveAccessType(['W', 'NDNE']));
    }

    public function testSingleTypeAndEmptySetResolution(): void
    {
        self::assertSame('W', folderResolveAccessType(['W']));
        self::assertSame('R', folderResolveAccessType(['R']));
        // No role defines the folder: visibility is decided upstream by folders_list
        self::assertSame('W', folderResolveAccessType([]));
    }

    public function testAccessFlagsMatchDocumentedMatrix(): void
    {
        // Same matrix as docs/features/rights.md and getRoleBasedAccess()
        self::assertSame(
            ['type' => 'W', 'create' => true, 'edit' => true, 'delete' => true],
            folderAccessFlagsFromType('W')
        );
        self::assertSame(
            ['type' => 'ND', 'create' => true, 'edit' => true, 'delete' => false],
            folderAccessFlagsFromType('ND')
        );
        self::assertSame(
            ['type' => 'NE', 'create' => true, 'edit' => false, 'delete' => true],
            folderAccessFlagsFromType('NE')
        );
        self::assertSame(
            ['type' => 'NDNE', 'create' => true, 'edit' => false, 'delete' => false],
            folderAccessFlagsFromType('NDNE')
        );
        self::assertSame(
            ['type' => 'R', 'create' => false, 'edit' => false, 'delete' => false],
            folderAccessFlagsFromType('R')
        );
    }

    /**
     * is_readonly must stay strictly equivalent to "resolved type is R" —
     * ND/NE/NDNE are writable folders carrying a restriction.
     */
    public function testOnlyReadOnlyTypeIsFlaggedReadOnly(): void
    {
        foreach (['W', 'ND', 'NE', 'NDNE'] as $type) {
            self::assertNotSame('R', folderAccessFlagsFromType($type)['type'], $type . ' is not read-only');
            self::assertTrue(folderAccessFlagsFromType($type)['create'], $type . ' allows item creation');
        }
        self::assertFalse(folderAccessFlagsFromType('R')['create']);
    }

    // -------------------------------------------------------------------------
    // Accessible folders list (admin / AD roles / forbidden folders)
    // -------------------------------------------------------------------------

    public function testForbiddenFolderIsSubtractedFromEveryGrant(): void
    {
        // Folder 7 is granted directly AND by a role, but explicitly denied
        $folders = folderBuildAccessibleList(
            false,
            [7, 8],
            [7, 9],
            [12],
            [7],
            []
        );

        self::assertNotContains(7, $folders, 'an explicitly denied folder must never be accessible');
        self::assertSame([8, 9, 12], $folders);
    }

    public function testAdministratorSeesEverySharedFolder(): void
    {
        $folders = folderBuildAccessibleList(
            true,
            [],
            [],
            [12],
            [],
            [1, 2, 3]
        );

        self::assertSame([1, 2, 3, 12], $folders, 'an admin without roles still gets all shared folders');
    }

    public function testAdministratorIsExemptFromTheDenyList(): void
    {
        // Mirrors identAdmin(), which resets user-no_access_folders to an empty list
        $folders = folderBuildAccessibleList(true, [], [], [], [2], [1, 2, 3]);

        self::assertContains(2, $folders);
    }

    public function testAdRolesContributeToTheAccessibleList(): void
    {
        // roleFolders carries both manual and AD-sourced roles after the merge
        $folders = folderBuildAccessibleList(false, [], [4, 5], [], [], []);

        self::assertSame([4, 5], $folders);
    }

    // -------------------------------------------------------------------------
    // Create / update input validation
    // -------------------------------------------------------------------------

    /**
     * The documented example { "title": "…", "private": true } sends no
     * access_rights; the controller forwards '' and the model must default to W
     * instead of failing the enum check with a 422.
     */
    public function testOmittedAccessRightsDefaultsToWrite(): void
    {
        self::assertSame('W', folderEffectiveAccessRights(''));
        self::assertTrue(folderIsValidAccessRight(folderEffectiveAccessRights('')));
    }

    public function testExplicitAccessRightsIsPreserved(): void
    {
        self::assertSame('R', folderEffectiveAccessRights('R'));
        self::assertSame('NDNE', folderEffectiveAccessRights('NDNE'));
    }

    public function testWhitespaceOnlyTitleIsRejected(): void
    {
        self::assertFalse(folderIsValidTitle(''));
        self::assertFalse(folderIsValidTitle('   '));
        self::assertFalse(folderIsValidTitle("\t\n"));
        self::assertTrue(folderIsValidTitle('Production'));
    }

    public function testUpdateComplexityMustBeAKnownLevel(): void
    {
        $levels = [0, 20, 38, 48, 60];

        self::assertTrue(folderIsValidComplexity(0, $levels));
        self::assertTrue(folderIsValidComplexity(60, $levels));
        self::assertFalse(folderIsValidComplexity(999, $levels), 'arbitrary value must be rejected on update');
        self::assertFalse(folderIsValidComplexity(-5, $levels));
        self::assertFalse(folderIsValidComplexity(37, $levels));
    }
}

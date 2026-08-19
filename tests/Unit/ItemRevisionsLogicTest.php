<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/item_revisions_logic.php';

/**
 * Behavioural tests of the item revision journal decision logic.
 */
class ItemRevisionsLogicTest extends TestCase
{
    /**
     * Every audit action describing a content change must allocate a revision.
     */
    public function testContentChangesBump(): void
    {
        foreach (['at_creation', 'at_modification', 'at_delete', 'at_restored', 'at_copy', 'at_import'] as $action) {
            self::assertTrue(itemRevisionShouldBump($action), $action . ' must bump');
        }
    }

    /**
     * Reads must never bump: an offline client would re-download an item nobody touched,
     * and merely opening an item card is enough to trigger them.
     */
    public function testReadsNeverBump(): void
    {
        $reads = [
            'at_shown',
            'at_password_shown',
            'at_password_copied',
            'at_password_shown_edit_form',
            'at_export',
            'at_access',
            'at_manual',
        ];

        foreach ($reads as $action) {
            self::assertFalse(itemRevisionShouldBump($action), $action . ' must not bump');
        }
    }

    /**
     * An unknown action is not a change either — the whitelist is closed.
     */
    public function testUnknownActionDoesNotBump(): void
    {
        self::assertFalse(itemRevisionShouldBump(''));
        self::assertFalse(itemRevisionShouldBump('at_something_new'));
    }

    /**
     * A copy and an import both materialize a new item.
     */
    public function testCreationLikeActionsMapToCreated(): void
    {
        self::assertSame('created', itemRevisionJournalAction('at_creation'));
        self::assertSame('created', itemRevisionJournalAction('at_copy'));
        self::assertSame('created', itemRevisionJournalAction('at_import'));
    }

    public function testDeleteAndRestoreMapToTheirOwnActions(): void
    {
        self::assertSame('deleted', itemRevisionJournalAction('at_delete'));
        self::assertSame('restored', itemRevisionJournalAction('at_restored'));
    }

    /**
     * A modification is a move only when its reason says so. The distinction matters:
     * a move is the only change that can push an item out of a client's visible folders.
     */
    public function testModificationIsAMoveOnlyWhenTheReasonSaysSo(): void
    {
        self::assertSame('updated', itemRevisionJournalAction('at_modification'));
        self::assertSame('updated', itemRevisionJournalAction('at_modification', 'at_label'));
        self::assertSame('updated', itemRevisionJournalAction('at_modification', 'at_pw'));
        self::assertSame('moved', itemRevisionJournalAction('at_modification', 'at_moved : Src -> Dst'));
    }

    /**
     * logItems() appends a source marker to the reason on API calls, and the reason of a
     * move is built by concatenation — neither may hide the move.
     */
    public function testMoveIsDetectedThroughTheApiSourceMarker(): void
    {
        self::assertSame(
            'moved',
            itemRevisionJournalAction('at_modification', 'at_moved : Src -> Dst | tp_src=api')
        );
        self::assertSame('moved', itemRevisionJournalAction('at_modification', ' at_moved : A -> B'));
    }

    /**
     * A reason merely mentioning a move elsewhere is not a move.
     */
    public function testReasonNotStartingWithTheMovePrefixIsNotAMove(): void
    {
        self::assertFalse(itemRevisionReasonIsMove(null));
        self::assertFalse(itemRevisionReasonIsMove(''));
        self::assertFalse(itemRevisionReasonIsMove('at_label'));
        self::assertFalse(itemRevisionReasonIsMove('at_description : moved the at_moved text'));
        self::assertTrue(itemRevisionReasonIsMove('at_moved : A -> B'));
    }

    /**
     * A disappearance must always be journalled, even when the item already got a revision
     * earlier in the same request — otherwise the client never learns the item is gone.
     */
    public function testTerminalActionsAreRecognised(): void
    {
        self::assertTrue(itemRevisionIsTerminalAction('deleted'));
        self::assertTrue(itemRevisionIsTerminalAction('purged'));

        foreach (['created', 'updated', 'restored', 'moved'] as $action) {
            self::assertFalse(itemRevisionIsTerminalAction($action), $action . ' is not terminal');
        }
    }

    /**
     * A second call in the same request rewrites the stored row only when it knows
     * something the first one did not.
     */
    public function testEntryIsUpgradedOnlyWhenTheLaterCallKnowsMore(): void
    {
        // A plain second update carries nothing new.
        self::assertFalse(itemRevisionShouldUpgradeEntry('updated', null));
        self::assertFalse(itemRevisionShouldUpgradeEntry('created', null));

        // A deletion following an update must overwrite the action.
        self::assertTrue(itemRevisionShouldUpgradeEntry('deleted', null));
        self::assertTrue(itemRevisionShouldUpgradeEntry('purged', null));

        // A move carries the source folder, which drives out-of-scope removal.
        self::assertTrue(itemRevisionShouldUpgradeEntry('moved', 12));
    }

    /**
     * A move reported without a usable source folder adds nothing to the stored row.
     */
    public function testMoveWithoutSourceFolderDoesNotUpgrade(): void
    {
        self::assertFalse(itemRevisionShouldUpgradeEntry('moved', null));
        self::assertFalse(itemRevisionShouldUpgradeEntry('moved', 0));
    }

    public function testLimitIsClamped(): void
    {
        self::assertSame(ITEM_REVISION_DEFAULT_LIMIT, itemRevisionNormalizeLimit(null));
        self::assertSame(ITEM_REVISION_DEFAULT_LIMIT, itemRevisionNormalizeLimit(0));
        self::assertSame(ITEM_REVISION_DEFAULT_LIMIT, itemRevisionNormalizeLimit(-5));
        self::assertSame(50, itemRevisionNormalizeLimit(50));
        self::assertSame(ITEM_REVISION_MAX_LIMIT, itemRevisionNormalizeLimit(999999));
    }

    /**
     * A first synchronization can never be a delta: items untouched since tracking was
     * installed are still at revision 0 and have no journal entry at all.
     */
    public function testCursorZeroAlwaysForcesAFullSync(): void
    {
        self::assertTrue(itemRevisionNeedsFullSync(0, null));
        self::assertTrue(itemRevisionNeedsFullSync(0, 1));
        self::assertTrue(itemRevisionNeedsFullSync(0, 4000));
        self::assertTrue(itemRevisionNeedsFullSync(-1, 1));
    }

    /**
     * A cursor older than the retained window cannot be served: the entries proving what
     * changed have been pruned.
     */
    public function testCursorOlderThanRetentionForcesAFullSync(): void
    {
        // The journal starts at 500, so a client at 498 has lost revision 499.
        self::assertTrue(itemRevisionNeedsFullSync(498, 500));

        // A client at 499 is served correctly: everything above its cursor is still there.
        self::assertFalse(itemRevisionNeedsFullSync(499, 500));
        self::assertFalse(itemRevisionNeedsFullSync(600, 500));
    }

    /**
     * An empty journal means nothing ever changed, not that history was lost.
     */
    public function testEmptyJournalDoesNotForceAFullSyncForAnEstablishedClient(): void
    {
        self::assertFalse(itemRevisionNeedsFullSync(42, null));
    }

    /**
     * An item modified three times since the cursor is one download, not three.
     */
    public function testScanKeepsOnlyTheLastEntryPerItem(): void
    {
        $rows = [
            ['revision' => 10, 'item_id' => 7, 'action' => 'updated'],
            ['revision' => 11, 'item_id' => 9, 'action' => 'created'],
            ['revision' => 12, 'item_id' => 7, 'action' => 'moved'],
            ['revision' => 13, 'item_id' => 7, 'action' => 'deleted'],
        ];

        $winners = itemRevisionDedupeScan($rows);

        self::assertCount(2, $winners);
        self::assertSame(13, (int) $winners[7]['revision']);
        self::assertSame('deleted', $winners[7]['action']);
        self::assertSame(11, (int) $winners[9]['revision']);
    }

    public function testScanIgnoresRowsWithoutAnItem(): void
    {
        self::assertSame([], itemRevisionDedupeScan([['revision' => 3, 'item_id' => 0]]));
        self::assertSame([], itemRevisionDedupeScan([]));
    }

    public function testDisappearedItemsAreClassifiedAsRemoved(): void
    {
        $purged = itemRevisionClassifyScanRow(7, [7 => true], false, false);
        self::assertSame('removed', $purged['classification']);
        self::assertSame('purged', $purged['reason']);

        $deleted = itemRevisionClassifyScanRow(7, [7 => true], true, true);
        self::assertSame('removed', $deleted['classification']);
        self::assertSame('deleted', $deleted['reason']);
    }

    /**
     * The case the source folder is journalled for: the item still exists, but the caller
     * cannot read it any more, so their cached copy must go.
     */
    public function testItemLeavingTheCallerScopeIsRemoved(): void
    {
        $result = itemRevisionClassifyScanRow(7, [9 => true], true, false);

        self::assertSame('removed', $result['classification']);
        self::assertSame('out_of_scope', $result['reason']);
    }

    public function testReadableItemIsClassifiedAsChanged(): void
    {
        $result = itemRevisionClassifyScanRow(7, [7 => true, 9 => true], true, false);

        self::assertSame('changed', $result['classification']);
        self::assertSame('', $result['reason']);
    }

    /**
     * A purge wins over visibility: the row is gone whatever the caller could read.
     */
    public function testPurgeIsReportedEvenWhenTheItemWasNotVisible(): void
    {
        $result = itemRevisionClassifyScanRow(7, [], false, false);

        self::assertSame('purged', $result['reason']);
    }

    public function testCursorAdvancesToTheHighestScannedRevision(): void
    {
        self::assertSame(42, itemRevisionResolveCursor(10, [11, 42, 30]));
    }

    public function testCursorNeverGoesBackwards(): void
    {
        self::assertSame(50, itemRevisionResolveCursor(50, []));
        self::assertSame(50, itemRevisionResolveCursor(50, [12, 30]));
    }

    /**
     * Right after a creation the encryption keys are still being distributed: the item is
     * visible but not yet readable. Stepping over it would hide it from that client forever.
     */
    public function testCursorStopsBeforeAChangeThatCouldNotBeDelivered(): void
    {
        self::assertSame(24, itemRevisionResolveCursor(10, [20, 25, 30], [25, 30]));
    }

    public function testUndeliverableChangeNeverPushesTheCursorBelowTheClientOne(): void
    {
        // The very first scanned revision could not be delivered: stay where we were.
        self::assertSame(10, itemRevisionResolveCursor(10, [11, 12], [11]));
    }

    public function testRetentionFallsBackToTheDefaultRatherThanToKeepForever(): void
    {
        self::assertSame(ITEM_REVISION_DEFAULT_RETENTION_DAYS, itemRevisionResolveRetentionDays(null));
        self::assertSame(ITEM_REVISION_DEFAULT_RETENTION_DAYS, itemRevisionResolveRetentionDays(''));
        self::assertSame(ITEM_REVISION_DEFAULT_RETENTION_DAYS, itemRevisionResolveRetentionDays('nonsense'));
    }

    public function testRetentionAcceptsNumericStringsAndDisablesOnZero(): void
    {
        self::assertSame(30, itemRevisionResolveRetentionDays('30'));
        self::assertSame(30, itemRevisionResolveRetentionDays(30));
        self::assertSame(0, itemRevisionResolveRetentionDays('0'));
        self::assertSame(0, itemRevisionResolveRetentionDays(-10));
    }

    public function testPruningIsSkippedWhenDisabled(): void
    {
        self::assertNull(itemRevisionPruneCutoff(0, 1_800_000_000));
        self::assertNull(itemRevisionPruneCutoff(-1, 1_800_000_000));
    }

    public function testPruneCutoffIsTheRetentionWindowBeforeNow(): void
    {
        self::assertSame(1_800_000_000 - (90 * 86400), itemRevisionPruneCutoff(90, 1_800_000_000));
        self::assertSame(1_800_000_000 - 86400, itemRevisionPruneCutoff(1, 1_800_000_000));
    }
}

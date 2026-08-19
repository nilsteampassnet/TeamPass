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
}

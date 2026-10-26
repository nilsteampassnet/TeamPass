<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/attachment_pending_logic.php';

/**
 * Behavioural tests for the attachments a session uploaded but has not attached yet.
 */
class AttachmentPendingLogicTest extends TestCase
{
    /** A file is only listed under the target it was uploaded for. */
    public function testFilesAreListedPerTarget(): void
    {
        $pending = attachmentPendingRecord([], 7, 123456789);
        $pending = attachmentPendingRecord($pending, 8, 123456789);
        $pending = attachmentPendingRecord($pending, 9, 42);

        self::assertSame([7, 8], attachmentPendingFileIds($pending, 123456789));
        self::assertSame([9], attachmentPendingFileIds($pending, 42));
        self::assertSame([], attachmentPendingFileIds($pending, 43));
    }

    /** Another session's files are unknown here: an empty map attaches nothing. */
    public function testEmptyMapListsNothing(): void
    {
        self::assertSame([], attachmentPendingFileIds([], 42));
    }

    /** Invalid ids are never recorded nor matched. */
    public function testInvalidIdsAreIgnored(): void
    {
        self::assertSame([], attachmentPendingRecord([], 0, 42));
        self::assertSame([], attachmentPendingRecord([], 7, 0));
        self::assertSame([], attachmentPendingFileIds([7 => 0], 0));
    }

    /** Session data may hold numeric strings. */
    public function testStringValuesAreAccepted(): void
    {
        self::assertSame([7], attachmentPendingFileIds(['7' => '42'], 42));
    }

    /** Forgetting a target leaves the other targets untouched. */
    public function testForgetOnlyRemovesTheTarget(): void
    {
        $pending = [7 => 42, 8 => 42, 9 => 123456789];

        self::assertSame([9 => 123456789], attachmentPendingForget($pending, 42));
        self::assertSame($pending, attachmentPendingForget($pending, 43));
    }

    /** Beyond the limit, the oldest entries are dropped first. */
    public function testOldestEntriesAreDroppedBeyondTheLimit(): void
    {
        $pending = [];
        foreach ([1, 2, 3, 4] as $fileId) {
            $pending = attachmentPendingRecord($pending, $fileId, 42, 3);
        }

        self::assertSame([2 => 42, 3 => 42, 4 => 42], $pending);
    }

    /** Recording a file again moves it to the newest position. */
    public function testRecordingAgainRefreshesTheEntry(): void
    {
        $pending = attachmentPendingRecord([1 => 42, 2 => 42], 1, 43, 2);
        $pending = attachmentPendingRecord($pending, 3, 42, 2);

        self::assertSame([1 => 43, 3 => 42], $pending);
    }
}

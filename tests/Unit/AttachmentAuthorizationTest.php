<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static guards for the authorization of item attachments (GHSA-r6r4-5mr3-rppm).
 *
 * delete_attached_file only checked that the item's folder was visible, which
 * read-only folders are: a read-only member destroyed any attachment in it. And the
 * staging id an attachment is uploaded under came from the client and was trusted
 * as is: new_item moved every file of the item it designated onto the caller's new
 * item, whose sharekey they already held, and confirm_attachments published any
 * unconfirmed row planted on the item being saved. Removing an attachment now
 * requires the edit right, and only the files the session uploaded itself may be
 * attached.
 */
class AttachmentAuthorizationTest extends TestCase
{
    private function source(string $file): string
    {
        $path = __DIR__ . '/../../app/sources/' . $file;
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);

        return $content;
    }

    /**
     * Returns the part of $file between two markers.
     */
    private function block(string $file, string $startMarker, string $endMarker): string
    {
        $content = $this->source($file);
        $start = strpos($content, $startMarker);
        self::assertNotFalse($start, 'Marker not found: ' . $startMarker);
        $end = strpos($content, $endMarker, $start + strlen($startMarker));
        self::assertNotFalse($end, 'Marker not found: ' . $endMarker);

        return substr($content, $start, $end - $start);
    }

    public function testDeleteAttachedFileRequiresTheEditRightBeforeDeleting(): void
    {
        $block = $this->block('items.queries.php', "case 'delete_attached_file':", 'DB::delete(');

        self::assertMatchesRegularExpression(
            '/\$deleteFileRights = getCurrentAccessRights\(\s*\(int\) \$session->get\(\'user-id\'\),'
            . '\s*\(int\) \$data\[\'id_item\'\],\s*\(int\) \$data_item\[\'id_tree\'\]\s*\);'
            . '\s*if \(\$deleteFileRights\[\'error\'\] \|\| \$deleteFileRights\[\'edit\'\] !== true\) \{'
            . '.*?error_no_edit_right.*?break;/s',
            $block,
            'Removing an attachment requires the edit right on its item, before any deletion.'
        );
        self::assertStringNotContainsString(
            "user-accessible_folders",
            $block,
            'Folder visibility also admits read-only folders: it must not authorize the deletion.'
        );
    }

    public function testNewItemOnlyAttachesTheFilesTheSessionStaged(): void
    {
        $block = $this->block(
            'items.queries.php',
            '// Check if any files have been added',
            '// Create new task for the new item'
        );

        self::assertStringContainsString(
            'attachmentPendingFileIds($pendingAttachments, (int) $post_uploaded_file_id)',
            $block
        );
        self::assertStringContainsString('WHERE id IN %li AND id_item = %i AND confirmed = 0', $block);
        self::assertDoesNotMatchRegularExpression(
            '/WHERE id_item = %[si]\'/',
            $block,
            'Selecting the files by the client staging id alone moves another item\'s attachments.'
        );
    }

    public function testConfirmAttachmentsOnlyConfirmsTheSessionUploads(): void
    {
        $block = $this->block('items.queries.php', "case 'confirm_attachments':", '$data = array(');

        self::assertMatchesRegularExpression(
            '/\$fileIdsToConfirm = attachmentPendingFileIds\(\$pendingAttachments, \$inputData\[\'itemId\'\]\);/',
            $block
        );
        self::assertStringContainsString('WHERE id IN %li AND id_item = %i AND confirmed = 0', $block);
        self::assertDoesNotMatchRegularExpression(
            '/WHERE id_item = %i AND confirmed = %i\'/',
            $block,
            'Confirming every unconfirmed row publishes files planted by other users.'
        );
    }

    public function testDiscardingAStagingIdOnlyRemovesTheSessionFiles(): void
    {
        $block = $this->block(
            'items.queries.php',
            "case 'delete_uploaded_files_but_not_saved':",
            'foreach ($rows as $file) {'
        );

        self::assertMatchesRegularExpression(
            '/\} else \{\s*\$stagedFileIds = attachmentPendingFileIds\(\$pendingAttachments, \$inputData\[\'itemId\'\]\);'
            . '.*?WHERE id IN %li AND id_item = %i AND confirmed = %i/s',
            $block,
            'Without an existing item to check rights on, only the session\'s own staged files may go.'
        );
    }

    public function testUploadChecksTheTargetOfBothBranches(): void
    {
        $block = $this->block('upload.attachments.php', '// Permission check', '$cleanupTargetDir');

        self::assertStringContainsString(
            'if ((int) ($post_isNewItem ?? 0) === 1) {',
            $block,
            'A missing isNewItem must be treated as an existing item, whose edit right is checked.'
        );
        self::assertMatchesRegularExpression(
            '/SELECT id FROM \' \. prefixTable\(\'items\'\) \. \' WHERE id = %i\',\s*\(int\) \$post_randomId\s*\);'
            . '\s*if \(DB::count\(\) > 0\) \{\s*handleAttachmentError/s',
            $block,
            'A staging id must never designate an existing item.'
        );
        self::assertMatchesRegularExpression(
            '/\} elseif \(\s*empty\(\$post_itemId\) === true\s*'
            . '\|\| !userCanEditItem\(\(int\) \$session->get\(\'user-id\'\), \(int\) \$post_itemId\)\s*\) \{/',
            $block,
            'Uploading to an existing item requires the edit right on it.'
        );
    }

    public function testUploadRecordsTheFileForTheSession(): void
    {
        $block = $this->block('upload.attachments.php', '$newID = DB::insertId();', '// Store the key for users');

        self::assertMatchesRegularExpression(
            '/\$session->set\(\s*\'user-pending_attachments\',\s*attachmentPendingRecord\(/',
            $block
        );
    }
}

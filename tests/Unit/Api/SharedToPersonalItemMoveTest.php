<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the API shared-to-personal item move.
 *
 * The move used to change the folder only: every user of the source folder kept a sharekey on an
 * item that now sat in someone's personal folder, which breaks the SEC-8 invariant the web move
 * and item creation enforce.
 */
class SharedToPersonalItemMoveTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $source = file_get_contents(__DIR__ . '/../../..' . $relativePath);
        self::assertIsString($source);

        return $source;
    }

    private function section(string $source, string $startMarker, string $endMarker): string
    {
        $start = strpos($source, $startMarker);
        self::assertIsInt($start, 'Start marker must exist: ' . $startMarker);
        $end = strpos($source, $endMarker, $start + strlen($startMarker));
        self::assertIsInt($end, 'End marker must exist after: ' . $startMarker);

        return substr($source, $start, $end - $start);
    }

    public function testApiMoveChecksKeysBeforeWritingAndPurgesWithTheFolderChange(): void
    {
        $update = $this->section(
            $this->source('/app/api/Model/ItemModel.php'),
            'public function updateItem(',
            'private function getFolderTitle('
        );

        $check = strpos($update, 'userHoldsEveryItemSharekey($itemId');
        $folderChange = strpos($update, "\$updateData['id_tree'] = \$newFolderId;\n                    \$updateData['perso']");
        $begin = strpos($update, 'DB::startTransaction();');
        $write = strpos($update, 'prefixTable(\'items\'),', (int) $begin);
        $purge = strpos($update, 'deleteForeignItemSharekeys($itemId');
        $commit = strpos($update, 'DB::commit();', (int) $purge);
        $rollback = strpos($update, 'DB::rollback();', (int) $commit);

        foreach (['check' => $check, 'folder change' => $folderChange, 'transaction' => $begin, 'write' => $write, 'purge' => $purge, 'commit' => $commit, 'rollback' => $rollback] as $label => $position) {
            self::assertIsInt($position, $label . ' must exist');
        }
        self::assertLessThan($folderChange, $check, 'The key check must run before the move is prepared');
        self::assertLessThan($write, $begin);
        self::assertLessThan($purge, $write, 'Keys are purged only once the item row is written');
        self::assertLessThan($commit, $purge);
        self::assertLessThan($rollback, $commit);
    }

    public function testPurgeKeepsTheOwnerAndTheRecoveryKeyOnEveryFamily(): void
    {
        $purge = $this->section(
            $this->source('/app/sources/main.functions.php'),
            'function deleteForeignItemSharekeys(',
            'function userHoldsEveryItemSharekey('
        );

        self::assertStringContainsString('[$ownerId, TP_USER_ID, API_USER_ID, OTV_USER_ID, SSH_USER_ID]', $purge);
        foreach (['sharekeys_items', 'sharekeys_files', 'sharekeys_fields', 'sharekeys_logs'] as $table) {
            self::assertStringContainsString("prefixTable('" . $table . "')", $purge, $table . ' must be purged');
        }
        self::assertStringNotContainsString('startTransaction', $purge, 'The caller owns the transaction');
    }

    public function testKeyCheckCoversEveryEncryptedObject(): void
    {
        $check = $this->section(
            $this->source('/app/sources/main.functions.php'),
            'function userHoldsEveryItemSharekey(',
            "\n}\n"
        );

        foreach (['sharekeys_items', 'sharekeys_fields', 'sharekeys_files'] as $table) {
            self::assertStringContainsString("prefixTable('" . $table . "')", $check, $table . ' must be checked');
        }
        self::assertStringContainsString('share_key != ""', $check, 'A blanked key is not a usable key');
    }

    public function testBackgroundFanOutRechecksTheItemFolderAfterDistributing(): void
    {
        $fanOut = $this->section(
            $this->source('/app/scripts/background_tasks___worker.php'),
            'private function processSubTasks(',
            'if (count($failedSubtasks) > 0) {'
        );

        $loopEnd = strrpos($fanOut, 'foreach ($subtasks as $subtask)');
        $recheck = strpos($fanOut, "restrictItemSharekeysToOwnerIfPersonal((int) (\$arguments['item_id'] ?? 0));");
        self::assertIsInt($loopEnd);
        self::assertIsInt($recheck, 'A move into a personal folder racing the fan-out must be caught');
        self::assertLessThan($recheck, $loopEnd, 'The recheck must run once the keys are written');

        $restrict = $this->section(
            $this->source('/app/sources/main.functions.php'),
            'function restrictItemSharekeysToOwnerIfPersonal(',
            'function userHoldsEveryItemSharekey('
        );
        self::assertStringContainsString('root.personal_folder = 1 AND root.parent_id = 0', $restrict);
        self::assertStringContainsString('deleteForeignItemSharekeys($itemId, (int) $personalRoot[\'owner_id\']);', $restrict);
    }

    public function testPersonalItemCleanupSharesThePurge(): void
    {
        $cleanup = $this->section(
            $this->source('/app/sources/main.functions.php'),
            'function EnsurePersonalItemHasOnlyKeysForOwner(',
            'function deleteForeignItemSharekeys('
        );

        self::assertStringContainsString('deleteForeignItemSharekeys($itemId, $userId);', $cleanup);
    }
}

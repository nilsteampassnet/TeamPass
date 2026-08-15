<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the shared synchronous personal-to-shared item move path.
 */
class PersonalToSharedItemMoveTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../..' . $relativePath;
        self::assertFileExists($path, $relativePath . ' must exist');
        $source = file_get_contents($path);
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

    public function testApiAndWebUseTheSameSynchronousMoveFunction(): void
    {
        $apiModel = $this->source('/app/api/Model/ItemModel.php');
        $webQueries = $this->source('/app/sources/items.queries.php');

        self::assertStringContainsString(
            'movePersonalItemToSharedFolderSynchronously(',
            $apiModel
        );
        self::assertStringContainsString(
            'movePersonalItemToSharedFolderSynchronously(',
            $webQueries
        );
        self::assertStringNotContainsString(
            'orphaned row deleted during personal→public move',
            $webQueries,
            'A missing field sharekey must abort the move, never delete the field.'
        );
    }

    public function testMoveRecoversAllExistingWebObjectKeyFamiliesBeforePublishing(): void
    {
        $functions = $this->source('/app/sources/main.functions.php');
        $move = $this->section(
            $functions,
            'function movePersonalItemToSharedFolderSynchronously(',
            'function finalizeItemMoveSideEffects('
        );

        self::assertStringContainsString("'sharekeys_items'", $move);
        self::assertStringContainsString("'sharekeys_fields'", $move);
        self::assertStringContainsString("'sharekeys_files'", $move);
        self::assertStringContainsString('decryptUserObjectKeyWithMigration(', $move);
        self::assertSame(3, substr_count($move, 'storeUsersShareKey('));
        self::assertStringNotContainsString("'sharekeys_logs'", $move);
        self::assertStringNotContainsString("DB::delete(prefixTable('categories_items')", $move);
    }

    public function testMoveIsLockedTransactionalAndPublishesTheFolderChangeLast(): void
    {
        $functions = $this->source('/app/sources/main.functions.php');
        $move = $this->section(
            $functions,
            'function movePersonalItemToSharedFolderSynchronously(',
            'function finalizeItemMoveSideEffects('
        );

        $begin = strpos($move, 'DB::startTransaction();');
        $lock = strpos($move, 'FOR UPDATE');
        $sharekeys = strpos($move, 'storeUsersShareKey(');
        $publish = strpos($move, "'id_tree' => \$targetFolderId");
        $commit = strpos($move, 'DB::commit();');
        $rollback = strpos($move, 'DB::rollback();');

        self::assertIsInt($begin);
        self::assertIsInt($lock);
        self::assertIsInt($sharekeys);
        self::assertIsInt($publish);
        self::assertIsInt($commit);
        self::assertIsInt($rollback);
        self::assertLessThan($lock, $begin);
        self::assertLessThan($sharekeys, $lock);
        self::assertLessThan($publish, $sharekeys);
        self::assertLessThan($commit, $publish);
        self::assertGreaterThan($commit, $rollback);
        self::assertStringNotContainsString('storeTask(', $move);
    }

    public function testApiAndWebShareTheSamePostCommitMoveEffects(): void
    {
        $functions = $this->source('/app/sources/main.functions.php');
        $apiModel = $this->source('/app/api/Model/ItemModel.php');
        $webQueries = $this->source('/app/sources/items.queries.php');
        $effects = $this->section(
            $functions,
            'function finalizeItemMoveSideEffects(',
            'function insertOrUpdateSharekey('
        );

        self::assertStringContainsString('finalizeItemMoveSideEffects(', $apiModel);
        self::assertStringContainsString('finalizeItemMoveSideEffects(', $webQueries);
        self::assertStringContainsString('logItems(', $effects);
        self::assertStringContainsString("updateCacheTable('update_value'", $effects);
        self::assertSame(2, substr_count($effects, 'adjustFolderItemsCounter('));
        self::assertSame(2, substr_count($effects, "emitWebSocketEvent('item_moved'"));
    }

    public function testApiMapsEachMoveFailureToItsOwnStatusCode(): void
    {
        $apiModel = $this->source('/app/api/Model/ItemModel.php');

        // Unrecoverable key material and a concurrent change are different problems for the
        // client: one is permanent, the other is worth retrying.
        self::assertStringContainsString('catch (UnexpectedValueException $exception)', $apiModel);
        self::assertStringContainsString('catch (RuntimeException $exception)', $apiModel);
        self::assertStringContainsString("'error_header' => 'HTTP/1.1 422 Unprocessable Entity'", $apiModel);
        self::assertStringContainsString("'error_header' => 'HTTP/1.1 409 Conflict'", $apiModel);

        // UnexpectedValueException extends RuntimeException: catching the parent first would
        // swallow the key failure and report a retryable conflict instead.
        $keyCatch = strpos($apiModel, 'catch (UnexpectedValueException $exception)');
        $conflictCatch = strpos($apiModel, 'catch (RuntimeException $exception)');
        self::assertIsInt($keyCatch);
        self::assertIsInt($conflictCatch);
        self::assertLessThan($conflictCatch, $keyCatch);
    }

    public function testApiConflictStatusHasAReasonPhrase(): void
    {
        $baseController = $this->source('/app/api/Controller/Api/BaseController.php');

        self::assertStringContainsString("409 => 'Conflict'", $baseController);
    }

    public function testApiRequiresTheMoveToBeSeparateFromConflictingUpdatesOnly(): void
    {
        $apiModel = $this->source('/app/api/Model/ItemModel.php');

        // The guard must key off the fields that would actually be written, not off "any key
        // other than id/folder_id": an unrelated extra key in the payload is not a conflict.
        self::assertStringContainsString('$conflictingUpdateFields = array_intersect(', $apiModel);
        self::assertStringNotContainsString('$additionalMoveFields = array_diff(', $apiModel);
        self::assertStringContainsString(
            'A personal-to-shared move must be requested separately from other item updates. ',
            $apiModel
        );
    }

    public function testApiNeverLeaksInternalErrorMessages(): void
    {
        $apiModel = $this->source('/app/api/Model/ItemModel.php');
        $update = $this->section(
            $apiModel,
            'public function updateItem(',
            'private function getFolderTitle('
        );

        self::assertStringContainsString(
            "'error_message' => 'An internal error occurred while updating the item.'",
            $update
        );
        self::assertStringNotContainsString(
            "'HTTP/1.1 500 Internal Server Error',\n                'error_message' => \$e->getMessage()",
            $update,
            'A database error message must never reach the API client.'
        );
    }

    public function testApiAppliesMoveSideEffectsToEveryFolderTransition(): void
    {
        $apiModel = $this->source('/app/api/Model/ItemModel.php');
        $update = $this->section(
            $apiModel,
            'public function updateItem(',
            'private function getFolderTitle('
        );

        // A shared-to-shared move is still a move: it must produce the same audit trail,
        // counters and WebSocket events as the personal-to-shared one.
        self::assertStringContainsString('$isActualMove = $newFolderId !== $sourceFolderId;', $update);
        self::assertSame(2, substr_count($update, '$moveContext = ['));
        self::assertStringContainsString('if (is_array($moveContext)) {', $update);
    }

    public function testMassMoveReusesTheSharedMoveImplementation(): void
    {
        $webQueries = $this->source('/app/sources/items.queries.php');
        $massMove = $this->section(
            $webQueries,
            "case 'mass_move_items':",
            "case 'mass_delete_items':"
        );

        self::assertStringContainsString('movePersonalItemToSharedFolderSynchronously(', $massMove);
        self::assertStringContainsString('finalizeItemMoveSideEffects(', $massMove);

        // The hand-rolled RSA fan-out must be gone from the mass path too, otherwise the two
        // move implementations drift apart again.
        self::assertStringNotContainsString('encryptUserObjectKey(', $massMove);
        self::assertStringNotContainsString('insertOrUpdateSharekey(', $massMove);
        self::assertStringNotContainsString('$tpUsersIDs', $massMove);

        // A failed item must be reported, not silently published without keys.
        self::assertStringContainsString('$failedItems++;', $massMove);
        self::assertStringContainsString('mass_move_partially_failed_keys', $massMove);
    }

    public function testMoveDecryptsSourceKeysBeforeOpeningTheTransaction(): void
    {
        $functions = $this->source('/app/sources/main.functions.php');
        $move = $this->section(
            $functions,
            'function movePersonalItemToSharedFolderSynchronously(',
            'function finalizeItemMoveSideEffects('
        );

        // Decryption is the slow part: keeping it outside the transaction bounds how long the
        // item row stays locked during the fan-out.
        $decrypt = strpos($move, 'decryptUserObjectKeyWithMigration(');
        $begin = strpos($move, 'DB::startTransaction();');
        self::assertIsInt($decrypt);
        self::assertIsInt($begin);
        self::assertLessThan($begin, $decrypt);

        // Reading outside the transaction is only safe if the state is revalidated under the lock.
        self::assertStringContainsString('$lockedItem', $move);
        self::assertStringContainsString('throw new RuntimeException(', $move);

        // Locking through a join would also lock the folder row for the whole fan-out.
        self::assertStringNotContainsString('INNER JOIN', $move);
    }

    public function testMoveResolvesPersonalFoldersTheSameWayAsTheApiCaller(): void
    {
        $functions = $this->source('/app/sources/main.functions.php');
        $apiModel = $this->source('/app/api/Model/ItemModel.php');
        $resolver = $this->section(
            $functions,
            'function getFolderIdentityWithPersonalFlag(',
            'function movePersonalItemToSharedFolderSynchronously('
        );
        $move = $this->section(
            $functions,
            'function movePersonalItemToSharedFolderSynchronously(',
            'function finalizeItemMoveSideEffects('
        );

        // Both sides must treat a legacy personal subfolder (own flag never written) as personal,
        // otherwise the caller enters the branch and the callee rejects the move.
        self::assertStringContainsString('personal_root', $resolver);
        self::assertStringContainsString('personal_root', $apiModel);
        self::assertSame(2, substr_count($move, 'getFolderIdentityWithPersonalFlag('));
        self::assertStringNotContainsString('source_folder.personal_folder', $move);
    }

    public function testMassMoveCanSkipThePerItemCacheAndCounterWork(): void
    {
        $functions = $this->source('/app/sources/main.functions.php');
        $effects = $this->section(
            $functions,
            'function finalizeItemMoveSideEffects(',
            'function insertOrUpdateSharekey('
        );

        // The mass path batches cache and counters once for the whole selection.
        self::assertStringContainsString('bool $refreshCache = true', $effects);
        self::assertStringContainsString('bool $adjustCounters = true', $effects);

        // The audit log and the notifications are never optional.
        $logItems = strpos($effects, 'logItems(');
        self::assertIsInt($logItems);
        self::assertStringNotContainsString('if ($refreshCache === true) {
        logItems(', $effects);
    }

    public function testMoveHelperDocumentsThatCallersOwnAuthorization(): void
    {
        $functions = $this->source('/app/sources/main.functions.php');
        $docblock = $this->section(
            $functions,
            ' * Move a personal item to a shared folder',
            'function movePersonalItemToSharedFolderSynchronously('
        );

        self::assertStringContainsString('performs NO authorization check', $docblock);
    }
}

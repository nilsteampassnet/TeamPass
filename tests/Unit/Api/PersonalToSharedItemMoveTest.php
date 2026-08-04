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

    public function testApiReturnsAValidationErrorWhenSourceKeysCannotBeRecovered(): void
    {
        $apiModel = $this->source('/app/api/Model/ItemModel.php');

        self::assertStringContainsString(
            'catch (InvalidArgumentException | UnexpectedValueException $exception)',
            $apiModel
        );
        self::assertStringContainsString(
            "'error_header' => 'HTTP/1.1 422 Unprocessable Entity'",
            $apiModel
        );
    }

    public function testApiRequiresTheMoveToBeSeparateFromOtherUpdates(): void
    {
        $apiModel = $this->source('/app/api/Model/ItemModel.php');

        self::assertStringContainsString('$additionalMoveFields = array_diff(', $apiModel);
        self::assertStringContainsString("['id', 'folder_id']", $apiModel);
        self::assertStringContainsString(
            'A personal-to-shared move must be requested separately from other item updates.',
            $apiModel
        );
    }
}

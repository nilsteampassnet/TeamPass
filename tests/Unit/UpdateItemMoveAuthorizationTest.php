<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static guards for the folder rights of update_item (GHSA-vxv5-cr34-5q7g).
 *
 * The item edit form can also move the item to another folder. update_item used
 * to compute the rights once, on the client-supplied destination, and read the
 * "delete on the old folder" answer from it: a user holding the ND level on the
 * item's folder moved the item into any folder of their own (their personal
 * folder included), which is exactly what ND forbids. The rights must be read on
 * the folder the item actually lives in, and the destination checked on its own,
 * like move_item and mass_move_items do.
 */
class UpdateItemMoveAuthorizationTest extends TestCase
{
    /**
     * Returns the update_item case from its label to the edition lock check,
     * which is the block that decides whether the caller may save.
     */
    private function updateItemRightsBlock(): string
    {
        $path = __DIR__ . '/../../app/sources/items.queries.php';
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);

        $start = strpos($content, "case 'update_item':");
        self::assertNotFalse($start, 'The update_item case must exist.');
        $end = strpos($content, "if (\$editionLockForSave['allowed'] !== true)", $start);
        self::assertNotFalse($end, 'The edition lock check of update_item must exist.');

        return substr($content, $start, $end - $start);
    }

    public function testSourceFolderIsTheItemsRealFolder(): void
    {
        self::assertMatchesRegularExpression(
            '/\$originalFolderId = \(int\) \(\$dataItem\[\'id_tree\'\] \?\? \$inputData\[\'folderId\'\]\);/',
            $this->updateItemRightsBlock(),
            'The source folder must come from the item row, not from the request.'
        );
    }

    public function testRightsAreNeverComputedOnTheRequestedFolderAlone(): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/getCurrentAccessRights\(\s*\$session->get\(\'user-id\'\),\s*\$inputData\[\'itemId\'\],\s*\$inputData\[\'folderId\'\],?\s*\)/',
            $this->updateItemRightsBlock(),
            'Rights computed on the client-supplied folder let a move bypass the source folder rights.'
        );
    }

    public function testEditRightIsRequiredOnTheSourceFolder(): void
    {
        self::assertMatchesRegularExpression(
            '/\$checkRights = getCurrentAccessRights\(\s*\$session->get\(\'user-id\'\),\s*\$inputData\[\'itemId\'\],\s*\$originalFolderId,?\s*\);\s*if \(\$checkRights\[\'error\'\] \|\| !\$checkRights\[\'edit\'\]\) \{/s',
            $this->updateItemRightsBlock(),
            'Saving an item requires the edit right on the folder it lives in.'
        );
    }

    public function testMoveRequiresDeleteOnSourceAndEditOnDestination(): void
    {
        self::assertMatchesRegularExpression(
            '/if \(\$originalFolderId !== \$targetFolderId\) \{'
            . '.*?if \(!\$checkRights\[\'delete\'\]\) \{.*?error_no_delete_right.*?break;'
            . '.*?\$destinationRights = getCurrentAccessRights\(\s*\$session->get\(\'user-id\'\),\s*\$inputData\[\'itemId\'\],\s*\$targetFolderId,?\s*\);'
            . '\s*if \(\$destinationRights\[\'error\'\] \|\| !\$destinationRights\[\'edit\'\]\) \{.*?error_no_edit_right.*?break;/s',
            $this->updateItemRightsBlock(),
            'A move must check delete on the source folder and edit on the destination, like move_item.'
        );
    }
}

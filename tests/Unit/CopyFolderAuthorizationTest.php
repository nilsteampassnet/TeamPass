<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static guards for the authorization of copy_folder (GHSA-q47m-rvr6-jqw7).
 *
 * The destination was only checked against the caller's read-only folders, and the
 * "personal" flag that satisfies the creation gate was read from any folder row: any
 * user could graft folders and items into another user's personal tree, or into a
 * folder they cannot see. The items are also decrypted with the TP_USER key and the
 * copies carry no restriction, so an item the caller is restricted from must never
 * be copied.
 */
class CopyFolderAuthorizationTest extends TestCase
{
    /**
     * Returns the copy_folder case, from its label to the next case.
     */
    private function copyFolderBlock(): string
    {
        $path = __DIR__ . '/../../app/sources/folders.queries.php';
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);

        $start = strpos($content, "case 'copy_folder':");
        self::assertNotFalse($start, 'The copy_folder case must exist.');
        $end = strpos($content, "case 'refresh_folders_list':", $start);
        self::assertNotFalse($end, 'The case following copy_folder must exist.');

        return substr($content, $start, $end - $start);
    }

    public function testTargetMustBeAnAccessibleFolder(): void
    {
        self::assertMatchesRegularExpression(
            '/\(int\) \$post_target_folder_id !== 0\s*&& in_array\(\s*\(int\) \$post_target_folder_id,\s*'
            . 'array_map\(\'intval\', \(array\) \$session->get\(\'user-accessible_folders\'\)\),\s*true\s*\) === false/',
            $this->copyFolderBlock(),
            'copy_folder must refuse a target folder the caller cannot access.'
        );
    }

    public function testTargetIsCheckedBeforeThePersonalFlagIsTrusted(): void
    {
        $block = $this->copyFolderBlock();
        $accessCheck = strpos($block, "(array) \$session->get('user-accessible_folders')");
        $personalFlag = strpos($block, '$isPersonal = ');
        self::assertNotFalse($accessCheck);
        self::assertNotFalse($personalFlag);
        self::assertLessThan(
            $personalFlag,
            $accessCheck,
            'The personal flag of the target satisfies the creation gate: the target must be authorized first.'
        );
    }

    public function testRestrictedItemsAreNotCopied(): void
    {
        self::assertMatchesRegularExpression(
            '/FROM \' \. prefixTable\(\'items\'\) \. \' AS i\s*WHERE i\.id_tree = %i\s*'
            . 'AND \' \. itemRestrictionSqlPredicate\(\s*\(int\) \$session->get\(\'user-id\'\),/',
            $this->copyFolderBlock(),
            'copy_folder must skip the items the caller is restricted from.'
        );
    }
}

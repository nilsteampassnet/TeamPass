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
 * be copied, and every copied folder must belong to the caller's current item access
 * scope (the read-only list still holds the folders forbidden to the user).
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

    public function testScopeIsRefreshedBeforeAnyFolderIsChecked(): void
    {
        $block = $this->copyFolderBlock();
        $refresh = strpos($block, 'if (refreshUserFolderPermissionScope($SETTINGS) === false) {');
        $firstCheck = strpos($block, "\$session->get('user-read_only_folders')");
        self::assertNotFalse($refresh, 'copy_folder must resolve the folder scope from the database.');
        self::assertNotFalse($firstCheck);
        self::assertLessThan(
            $firstCheck,
            $refresh,
            'The scope must be refreshed before the source and target folders are checked.'
        );
    }

    public function testCopiedFoldersFollowTheItemAccessScope(): void
    {
        $content = file_get_contents(__DIR__ . '/../../app/sources/folders.queries.php');
        self::assertIsString($content);
        self::assertStringContainsString(
            "require_once __DIR__ . '/item_access_logic.php';",
            $content,
            'folders.queries.php must load itemAccessFolderIsInScope().'
        );

        $block = $this->copyFolderBlock();
        self::assertMatchesRegularExpression(
            '/if \(itemAccessFolderIsInScope\(\s*\(int\) \$node->id,\s*'
            . '\(array\) \$session->get\(\'user-accessible_folders\'\),\s*'
            . '\(array\) \$session->get\(\'user-personal_folders\'\),\s*'
            . '\(array\) \$session->get\(\'user-no_access_folders\'\),\s*'
            . '\(array\) \$session->get\(\'user-forbiden_personal_folders\'\)\s*\) === false\) \{\s*continue;/',
            $block,
            'Each copied folder must be in the caller\'s item access scope, forbidden folders excluded.'
        );
        self::assertStringNotContainsString(
            '$array_all_visible_folders',
            $block,
            'The read-only list keeps forbidden folders: it must not grant access to a copied folder.'
        );
    }

    public function testCopiedItemsCarryThePersonalFlag(): void
    {
        self::assertStringContainsString(
            "'perso' => (int) \$nodeInfo->personal_folder,",
            $this->copyFolderBlock(),
            'An item copied into a personal folder must be stored as a personal item.'
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

<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static guards on how a personal folder is recognised.
 *
 * getAllPersonalFolderIds() returns the folders whose own personal_folder flag is set. That is
 * not the same set as "the folders sitting in a personal tree": a sub-folder created under a
 * personal root keeps the flag at 0 when it was never written (legacy data, copy_folder, import).
 *
 * Every place that uses the list to keep personal objects OUT of something must therefore resolve
 * it by containment - getPersonalFolderIdsWithDescendants() - or the objects it means to protect
 * leak through the hole. items.perso is no fallback either: it is 0 on items created while the
 * client sent folder_is_personal = 0, which is exactly why the exclusion is folder based.
 */
class PersonalFolderContainmentTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . ltrim($relativePath, '/');
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);

        return $content;
    }

    public function testSharekeysRepairExcludesPersonalObjectsByContainment(): void
    {
        $trait = $this->source('app/scripts/traits/SharekeysRepairTrait.php');

        self::assertStringContainsString(
            '$personalFolders = getPersonalFolderIdsWithDescendants();',
            $trait,
            'A flag-based list lets the repair task fan a personal item out to every user (SEC-8).'
        );
        self::assertStringNotContainsString(
            'getAllPersonalFolderIds()',
            $trait
        );
    }

    public function testSharekeysRepairFiltersEveryScopeOnTheFolderList(): void
    {
        $trait = $this->source('app/scripts/traits/SharekeysRepairTrait.php');

        // The items scope carries the object itself (alias "o"), fields and files join their
        // parent item (alias "i"): both fragments must exist, or one scope stays unfiltered.
        self::assertStringContainsString("' AND i.id_tree NOT IN ('", $trait);
        self::assertStringContainsString("' AND o.id_tree NOT IN ('", $trait);
        self::assertStringNotContainsString(
            'n.personal_folder = 0',
            $trait,
            'The nested_tree join tested the folder flag, which is the bug being guarded here.'
        );
    }

    public function testForeignPersonalFolderListIsResolvedByContainment(): void
    {
        $mainFunctions = $this->source('app/sources/main.functions.php');

        self::assertMatchesRegularExpression(
            '/function getForeignPersonalFolderIds\(int \$userId\): array\s*\{\s*\$allPersonalFolders = getPersonalFolderIdsWithDescendants\(\);/s',
            $mainFunctions,
            'This list is subtracted when keys are redistributed from TP_USER; a missing folder '
            . 'hands the target user a sharekey on another user\'s personal items.'
        );
    }

    public function testContainmentHelperKeepsUsingTheNestedTreeBounds(): void
    {
        $mainFunctions = $this->source('app/sources/main.functions.php');

        self::assertMatchesRegularExpression(
            '/function getPersonalFolderIdsWithDescendants\(\): array.*?folder\.nleft >= personal_root\.nleft.*?folder\.nright <= personal_root\.nright/s',
            $mainFunctions,
            'Containment is the MPTT bound check; anything else reintroduces the flag dependency.'
        );
    }
}

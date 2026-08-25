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

    public function testRestoreSharekeysScopesExcludePersonalObjectsByContainment(): void
    {
        $mainFunctions = $this->source('app/sources/main.functions.php');

        self::assertMatchesRegularExpression(
            '/function restoreSharekeysScopeDefs\(\): array\s*\{\s*\$personalFolders = getPersonalFolderIdsWithDescendants\(\);/s',
            $mainFunctions,
            'A flag-based list lets the repair task fan a personal item out to every user (SEC-8).'
        );

        // The items scope carries the object itself (alias "o"), fields and files join their
        // parent item (alias "i"): every scope must apply the exclusion, on its own alias.
        self::assertMatchesRegularExpression(
            '/function restoreSharekeysScopeDefs\(\).*?\$notPersonal\(\'o\'\).*?\$notPersonal\(\'i\'\).*?\$notPersonal\(\'i\'\)/s',
            $mainFunctions,
            'A scope with no exclusion hands its personal objects to every eligible user.'
        );
    }

    public function testRestoreSharekeysScopesAreDefinedOnlyOnce(): void
    {
        // The Tools analysis is read as a prediction of what the repair task will do, so the two
        // must describe the same object set. One definition, shared, is what guarantees it.
        self::assertStringContainsString(
            'function restoreSharekeysScopeDefs(): array',
            $this->source('app/sources/main.functions.php')
        );
        self::assertStringNotContainsString(
            'function restoreSharekeysScopeDefs',
            $this->source('app/sources/tools.queries.php'),
            'A second copy is how the analysis and the repair drifted apart in the first place.'
        );
    }

    public function testSharekeysRepairTaskBuildsOnTheSharedScopes(): void
    {
        $trait = $this->source('app/scripts/traits/SharekeysRepairTrait.php');

        self::assertStringContainsString(
            'foreach (restoreSharekeysScopeDefs() as $scopeName => $def) {',
            $trait
        );
        self::assertStringNotContainsString('getAllPersonalFolderIds()', $trait);
        self::assertStringNotContainsString(
            'n.personal_folder = 0',
            $trait,
            'The nested_tree join tested the folder flag, which is the bug being guarded here.'
        );

        // The task must not name the object sources any more: that is the shared definition's job.
        foreach (["prefixTable('items')", "prefixTable('categories_items')", "prefixTable('files')"] as $source) {
            self::assertStringNotContainsString($source, $trait);
        }
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
